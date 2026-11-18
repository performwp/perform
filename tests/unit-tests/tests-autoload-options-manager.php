<?php
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Transaction fake kept beside its focused tests.
use Perform\Admin\Settings\AutoloadOptionsManager;
use Perform\Admin\Settings\AutoloadOptionsManagerController;
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( $scheme = 'auth' ) {
		return 'synthetic-test-signing-key';
	}
}

/** Transaction fake: metadata only, with explicit failed-write injection. */
final class Perform_Autoload_Test_DB {
	public $options    = 'wp_options';
	public $last_error = '';
	public $row;
	public $journal       = null;
	public $fail_journal  = false;
	public $fail_cas      = false;
	public $queries       = [];
	public $listing_query = [];
	private $prepared     = [];
	private $snapshot;
	public function prepare( $sql, ...$params ) {
		$key                    = 'prepared-' . count( $this->prepared );
		$this->prepared[ $key ] = [ $sql, is_array( $params[0] ?? null ) ? $params[0] : $params ];
		return $key;
	}
	public function get_row( $key, $format ) {
		list( $sql, $params ) = $this->prepared[ $key ];
		return 0 === strpos( $sql, 'SHOW TABLE' ) ? [ 'Engine' => 'InnoDB' ] : ( 0 === strcasecmp( $params[0], $this->row['option_name'] ) ? $this->row : null );
	}
	public function get_var( $key ) {
		list( $sql ) = $this->prepared[ $key ] ?? [ $key ];
		return 0 === strpos( $sql, 'SELECT COUNT' ) ? 1 : $this->journal;
	}
	public function get_results( $key, $format ) {
		$this->listing_query = $this->prepared[ $key ];
		return [ $this->row ];
	}
	public function esc_like( $value ) {
		return addcslashes( $value, '_%\\' );
	}
	public function query( $key ) {
		list( $sql, $params ) = $this->prepared[ $key ] ?? [ $key, [] ];
		$this->queries[]      = $sql;
		if ( 'START TRANSACTION' === $sql ) {
			$this->snapshot = [ $this->row, $this->journal ];
			return 0;
		}
		if ( 'ROLLBACK' === $sql ) {
			list( $this->row, $this->journal ) = $this->snapshot;
			return 0;
		}
		if ( 'COMMIT' === $sql ) {
			return 0;
		}
		if ( 0 === strpos( $sql, 'INSERT INTO' ) ) {
			$this->journal = null === $this->journal ? '{}' : $this->journal;
			return 1;
		}
		if ( false !== strpos( $sql, 'SET autoload=' ) ) {
			if ( $this->fail_cas || $params[1] !== $this->row['option_id'] || $params[3] !== $this->row['autoload'] || $params[4] !== $this->row['value_hash'] ) {
				return 0;
			}
			$this->row['autoload'] = $params[0];
			return 1;
		}
		if ( $this->fail_journal ) {
			return false;
		}
		$this->journal = $params[0];
		return 1;
	}
}

final class Tests_Autoload_Options_Manager extends TestCase {
	private $previous;
	protected function setUp(): void {
		$this->previous        = [ $GLOBALS['wpdb'] ?? null, $GLOBALS['wp_version'] ?? null ];
		$GLOBALS['wp_version'] = '6.4';
		$GLOBALS['wpdb']       = new Perform_Autoload_Test_DB();
		$GLOBALS['wpdb']->row  = [
			'option_id'   => 42,
			'option_name' => 'third_party_cache',
			'autoload'    => 'yes',
			'value_hash'  => hash( 'sha256', 'fixture value never exposed' ),
			'size_bytes'  => 27,
		];
	}
	protected function tearDown(): void {
		list( $GLOBALS['wpdb'], $GLOBALS['wp_version'] ) = $this->previous;
		unset( $GLOBALS['perform_test_current_user_can'], $GLOBALS['perform_test_nonce_valid'], $GLOBALS['perform_test_blog_id'] );
	}
	private function token() {
		$method = new ReflectionMethod( AutoloadOptionsManager::class, 'token' );
		$method->setAccessible( true );
		return $method->invoke( null, $GLOBALS['wpdb']->row );
	}
	private function reject( callable $action ) {
		try {
			$action();
			$this->fail( 'Expected rejected change.' );
		} catch ( RuntimeException $error ) {
			$this->assertNotSame( '', $error->getMessage() );
		}
	}
	public function test_wordpress_64_changes_no_and_undo_restores_exact_yes() {
		$db     = $GLOBALS['wpdb'];
		$result = AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
		$this->assertSame( 'no', $db->row['autoload'] );
		$this->assertSame( -27, $result['byteDelta'] );
		$result = AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off', true );
		$this->assertSame( 'yes', $db->row['autoload'] );
		$this->assertSame( 27, $result['byteDelta'] );
		$this->assertSame( [], json_decode( $db->journal, true ) );
	}
	public function test_journal_failure_rolls_back_option_and_first_journal_creation() {
		$db               = $GLOBALS['wpdb'];
		$db->fail_journal = true;
		$this->reject(
			function () {
				AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
			}
		);
		$this->assertSame( 'yes', $db->row['autoload'] );
		$this->assertNull( $db->journal );
	}
	public function test_concurrent_cas_failure_rolls_back_journal() {
		$db           = $GLOBALS['wpdb'];
		$db->fail_cas = true;
		$this->reject(
			function () {
				AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
			}
		);
		$this->assertSame( 'yes', $db->row['autoload'] );
		$this->assertNull( $db->journal );
	}
	public function test_value_change_invalidates_review_token() {
		$token                              = $this->token();
		$GLOBALS['wpdb']->row['value_hash'] = hash( 'sha256', 'changed' );
		$this->reject(
			function () use ( $token ) {
				AutoloadOptionsManager::change( 'third_party_cache', $token, 'off' );
			}
		);
		$this->assertNull( $GLOBALS['wpdb']->journal );
	}
	public function test_noop_does_not_create_journal() {
		$this->reject(
			function () {
				AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'on' );
			}
		);
		$this->assertNull( $GLOBALS['wpdb']->journal );
	}
	public function test_wordpress_63_and_critical_options_rejected() {
		$GLOBALS['wp_version'] = '6.3';
		$this->assertNotSame( '', AutoloadOptionsManager::unsupported_reason() );
		$this->reject(
			function () {
				AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
			}
		);
		$GLOBALS['wp_version'] = '6.4';
		$this->reject(
			function () {
				AutoloadOptionsManager::change( 'siteurl', $this->token(), 'off' );
			}
		);
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}
	public function test_changed_value_blocks_undo_even_with_fresh_review_token() {
		AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
		$journal                            = $GLOBALS['wpdb']->journal;
		$GLOBALS['wpdb']->row['value_hash'] = hash( 'sha256', 'new-value' );
		$this->reject(
			function () {
				AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off', true );
			}
		);
		$this->assertSame( $journal, $GLOBALS['wpdb']->journal );
		$this->assertSame( 'no', $GLOBALS['wpdb']->row['autoload'] );
	}
	public function test_controller_rejects_unauthorized_requests_before_db_use() {
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => false ];
		$this->reject(
			function () {
				( new AutoloadOptionsManagerController() )->change_options();
			}
		);
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}
	public function test_site_token_cannot_be_reused_on_another_blog() {
		$token                           = $this->token();
		$GLOBALS['perform_test_blog_id'] = 2;
		$this->reject(
			function () use ( $token ) {
				AutoloadOptionsManager::change( 'third_party_cache', $token, 'off' );
			}
		);
		$this->assertNull( $GLOBALS['wpdb']->journal );
	}
	public function test_recreated_option_cannot_use_original_undo_record() {
		AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
		$journal                           = $GLOBALS['wpdb']->journal;
		$GLOBALS['wpdb']->row['option_id'] = 99;
		$this->reject(
			function () {
				AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off', true );
			}
		);
		$this->assertSame( $journal, $GLOBALS['wpdb']->journal );
	}
	public function test_modern_auto_policy_and_noop_undo_are_exact() {
		$GLOBALS['wp_version']            = '6.6';
		$GLOBALS['wpdb']->row['autoload'] = 'auto';
		AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
		$this->assertSame( 'off', $GLOBALS['wpdb']->row['autoload'] );
		AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off', true );
		$this->assertSame( 'auto', $GLOBALS['wpdb']->row['autoload'] );
		$GLOBALS['wpdb']->row['autoload'] = 'on';
		AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
		AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'on' );
		$result = AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off', true );
		$this->assertSame( 0, $result['byteDelta'] );
		$this->assertSame( [], json_decode( $GLOBALS['wpdb']->journal, true ) );
	}
	public function test_full_journal_does_not_discard_existing_undo_records() {
		$entries = [];
		for ( $i = 0; $i < AutoloadOptionsManager::LIMIT; $i++ ) {
			$entries[ 'other-' . $i ] = [
				'id'       => $i + 100,
				'original' => 'yes',
				'applied'  => 'no',
				'hash'     => str_repeat( 'a', 64 ),
				'time'     => 1,
			];
		}
		$GLOBALS['wpdb']->journal = wp_json_encode( $entries );
		$this->reject(
			function () {
				AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
			}
		);
		$this->assertSame( $entries, json_decode( $GLOBALS['wpdb']->journal, true ) );
		$this->assertSame( 'yes', $GLOBALS['wpdb']->row['autoload'] );
	}
	public function test_controller_requires_valid_action_nonce() {
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']      = false;
		$this->reject(
			function () {
				( new AutoloadOptionsManagerController() )->undo_options();
			}
		);
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}
	public function test_core_filter_preventing_enable_is_rejected_without_mutation() {
		$GLOBALS['wp_version']                   = '6.6';
		$GLOBALS['wpdb']->row['autoload']        = 'off';
		$GLOBALS['perform_test_autoload_values'] = [ 'yes' ];
		try {
			$this->reject(
				function () {
					AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'on' );
				}
			);
			$this->assertSame( 'off', $GLOBALS['wpdb']->row['autoload'] );
			$this->assertNull( $GLOBALS['wpdb']->journal );
		} finally {
			unset( $GLOBALS['perform_test_autoload_values'] );
		}
	}
	public function test_case_insensitive_lookup_cannot_bypass_canonical_protection() {
		$GLOBALS['wpdb']->row['option_name'] = 'siteurl';
		$token                               = $this->token();
		$this->reject(
			function () use ( $token ) {
				AutoloadOptionsManager::change( 'SITEURL', $token, 'off' );
			}
		);
		$this->assertSame( 'yes', $GLOBALS['wpdb']->row['autoload'] );
		$this->assertNull( $GLOBALS['wpdb']->journal );
		$this->assertContains( 'ROLLBACK', $GLOBALS['wpdb']->queries );
	}
	public function test_case_variant_of_editable_name_cannot_reuse_canonical_token() {
		$token = $this->token();
		$this->reject(
			function () use ( $token ) {
				AutoloadOptionsManager::change( 'THIRD_PARTY_CACHE', $token, 'off' );
			}
		);
		$this->assertSame( 'yes', $GLOBALS['wpdb']->row['autoload'] );
		$this->assertNull( $GLOBALS['wpdb']->journal );
	}
	public function test_protected_and_unknown_policy_rows_do_not_receive_tokens() {
		$GLOBALS['wpdb']->row['option_name'] = 'siteurl';
		$list                                = AutoloadOptionsManager::listing();
		$this->assertTrue( $list['items'][0]['protected'] );
		$this->assertSame( '', $list['items'][0]['token'] );
		$GLOBALS['wpdb']->row['option_name'] = 'third_party_cache';
		$GLOBALS['wpdb']->row['autoload']    = 'unexpected';
		$list                                = AutoloadOptionsManager::listing();
		$this->assertTrue( $list['items'][0]['protected'] );
		$this->assertSame( '', $list['items'][0]['token'] );
	}
	public function test_listing_orders_autoloaded_rows_by_size_and_keeps_pages_bounded() {
		$GLOBALS['perform_test_autoload_values'] = [ 'yes', 'auto-on' ];
		AutoloadOptionsManager::listing( 'cache', 2 );
		list( $sql, $params ) = $GLOBALS['wpdb']->listing_query;
		$this->assertStringContainsString( 'autoload IN (%s,%s) ORDER BY size_bytes DESC, option_name ASC LIMIT 25 OFFSET %d', $sql );
		$this->assertSame( [ 1, '%cache%', 'yes', 'auto-on', 25 ], $params );
		AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
		AutoloadOptionsManager::listing( '', 1, 'changed' );
		list( $sql, $params ) = $GLOBALS['wpdb']->listing_query;
		$this->assertStringContainsString( 'option_name IN (%s)', $sql );
		$this->assertStringNotContainsString( 'autoload IN', $sql );
		$this->assertSame( [ 1, 'third_party_cache', 0 ], $params );
	}
	public function test_journal_seed_acquires_write_lock_without_overwriting_existing_records() {
		$existing                 = [
			'another_option' => [
				'id'       => 99,
				'original' => 'yes',
				'applied'  => 'no',
				'hash'     => str_repeat( 'a', 64 ),
				'time'     => 1,
			],
		];
		$GLOBALS['wpdb']->journal = wp_json_encode( $existing );
		AutoloadOptionsManager::change( 'third_party_cache', $this->token(), 'off' );
		$queries = $GLOBALS['wpdb']->queries;
		$this->assertStringContainsString( 'ON DUPLICATE KEY UPDATE option_name=option_name', $queries[1] );
		$this->assertStringNotContainsString( 'INSERT IGNORE', $queries[1] );
		$this->assertSame( $existing['another_option'], json_decode( $GLOBALS['wpdb']->journal, true )['another_option'] );
	}
}
