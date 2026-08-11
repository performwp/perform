<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\AutoloadOptionsAudit;
use Perform\Admin\Settings\AutoloadOptionsAuditController;

final class Tests_Autoload_Options_Audit extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options']          = [];
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']      = true;
		$GLOBALS['perform_test_ext_object_cache'] = true;
		$GLOBALS['perform_test_is_multisite']     = false;
		$GLOBALS['perform_test_blog_id']          = 1;
		$GLOBALS['perform_test_autoload_values']  = [ 'yes', 'on', 'auto-on', 'auto' ];
		$GLOBALS['wpdb']                          = new Perform_Test_Autoload_Wpdb();
		$_POST                                    = [];
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_nonce_valid'],
			$GLOBALS['perform_test_ext_object_cache'],
			$GLOBALS['perform_test_is_multisite'],
			$GLOBALS['perform_test_blog_id'],
			$GLOBALS['perform_test_autoload_values'],
			$GLOBALS['perform_test_json_response'],
			$GLOBALS['wpdb']
		);
		$_POST = [];
	}

	public function test_refresh_collects_bounded_names_and_sizes_without_values() {
		$GLOBALS['wpdb']->summary = [
			'option_count' => 45,
			'total_size'   => 912345,
		];

		for ( $index = 0; $index < 25; ++$index ) {
			$GLOBALS['wpdb']->options_rows[] = [
				'option_name'  => 0 === $index ? 'perform_settings' : 'plugin_' . $index,
				'autoload'     => 'auto-on',
				'size_bytes'   => 50000 - $index,
				'option_value' => 'must-not-leak',
			];
		}

		$snapshot = AutoloadOptionsAudit::refresh();

		$this->assertSame( 45, $snapshot['totals']['count'] );
		$this->assertSame( 912345, $snapshot['totals']['sizeBytes'] );
		$this->assertTrue( $snapshot['objectCache']['persistent'] );
		$this->assertSame( [ 'yes', 'on', 'auto-on', 'auto' ], $snapshot['autoloadValues'] );
		$this->assertCount( 20, $snapshot['largestOptions'] );
		$this->assertSame( 'Perform', $snapshot['largestOptions'][0]['ownership']['label'] );
		$this->assertArrayNotHasKey( 'option_value', $snapshot['largestOptions'][0] );
		$this->assertStringContainsString( 'LIMIT %d', $GLOBALS['wpdb']->queries[1] );
		$this->assertSame( AutoloadOptionsAudit::RESULT_LIMIT, end( $GLOBALS['wpdb']->prepare_args[1] ) );
		$this->assertSame( $snapshot, $GLOBALS['perform_test_options'][ AutoloadOptionsAudit::OPTION_NAME ] );
	}

	public function test_cached_snapshot_is_marked_stale_without_refreshing_database() {
		$GLOBALS['perform_test_options'][ AutoloadOptionsAudit::OPTION_NAME ] = [
			'schemaVersion' => AutoloadOptionsAudit::SCHEMA_VERSION,
			'status'        => 'ready',
			'expiresAt'     => time() - 1,
		];

		$snapshot = AutoloadOptionsAudit::get_cached_snapshot();

		$this->assertTrue( $snapshot['isStale'] );
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}

	public function test_empty_snapshot_is_per_site_and_does_not_run_a_query() {
		$GLOBALS['perform_test_is_multisite'] = true;
		$GLOBALS['perform_test_blog_id']      = 7;

		$snapshot = AutoloadOptionsAudit::get_cached_snapshot();

		$this->assertSame( 'not-run', $snapshot['status'] );
		$this->assertTrue( $snapshot['isMultisite'] );
		$this->assertSame( 7, $snapshot['siteId'] );
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}

	public function test_refresh_endpoint_requires_manage_options() {
		$GLOBALS['perform_test_current_user_can']['manage_options'] = false;

		$this->run_controller_request();

		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 'Insufficient permissions.', $GLOBALS['perform_test_json_response']['data']['message'] );
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}

	public function test_refresh_endpoint_requires_valid_nonce() {
		$GLOBALS['perform_test_nonce_valid'] = false;
		$_POST['nonce']                      = 'invalid';

		$this->run_controller_request();

		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 'Security check failed.', $GLOBALS['perform_test_json_response']['data']['message'] );
		$this->assertSame( [], $GLOBALS['wpdb']->queries );
	}

	public function test_refresh_endpoint_returns_the_safe_snapshot() {
		$_POST['nonce'] = 'valid';

		$this->run_controller_request();

		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 'ready', $GLOBALS['perform_test_json_response']['data']['audit']['status'] );
	}

	private function run_controller_request() {
		try {
			( new AutoloadOptionsAuditController() )->refresh();
			$this->fail( 'Expected the JSON response to end the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
		}
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Focused database fixture for this service.
final class Perform_Test_Autoload_Wpdb {
	/** @var string */
	public $options = 'wp_options';

	/** @var string */
	public $last_error = '';

	/** @var array<string, int> */
	public $summary = [
		'option_count' => 0,
		'total_size'   => 0,
	];

	/** @var array<int, array<string, mixed>> */
	public $options_rows = [];

	/** @var array<int, string> */
	public $queries = [];

	/** @var array<int, array<int, mixed>> */
	public $prepare_args = [];

	public function prepare( $query, $args ) {
		$this->queries[]      = $query;
		$this->prepare_args[] = $args;
		return $query;
	}

	public function get_row( $query, $output ) {
		return $this->summary;
	}

	public function get_results( $query, $output ) {
		return $this->options_rows;
	}
}
