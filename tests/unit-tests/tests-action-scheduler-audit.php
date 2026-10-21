<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\ActionSchedulerAudit;
use Perform\Admin\Settings\ActionSchedulerAuditController;

final class Tests_Action_Scheduler_Audit extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options']          = [];
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']      = true;
		$GLOBALS['perform_test_is_multisite']     = false;
		$GLOBALS['perform_test_blog_id']          = 1;
		$GLOBALS['wpdb']                          = new Perform_Test_Action_Scheduler_Wpdb();
		$_POST                                    = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_options'], $GLOBALS['perform_test_current_user_can'], $GLOBALS['perform_test_nonce_valid'], $GLOBALS['perform_test_is_multisite'], $GLOBALS['perform_test_blog_id'], $GLOBALS['perform_test_json_response'], $GLOBALS['wpdb'] );
		$_POST = [];
	}

	public function test_absent_tables_return_clean_unavailable_snapshot() {
		$GLOBALS['wpdb']->existing_tables = [];
		$audit                            = ActionSchedulerAudit::refresh();

		$this->assertSame( 'not-available', $audit['status'] );
		$this->assertSame( 0, $audit['storage']['actionCount'] );
		$this->assertFalse( $audit['privacy']['argumentsRead'] );
		$this->assertCount( 3, $GLOBALS['wpdb']->data_queries );
	}

	public function test_empty_queue_returns_zero_counts() {
		$audit = ActionSchedulerAudit::refresh();

		$this->assertSame( 'ready', $audit['status'] );
		$this->assertSame(
			[
				'pending'  => 0,
				'running'  => 0,
				'complete' => 0,
				'failed'   => 0,
				'canceled' => 0,
			],
			$audit['counts']
		);
		$this->assertSame( 0, $audit['storage']['actionCount'] );
		$this->assertSame( 0, $audit['storage']['logCount'] );
		$this->assertFalse( $audit['oldestPending']['available'] );
	}

	public function test_large_failed_queue_is_aggregated_without_payloads() {
		$GLOBALS['wpdb']->status_rows    = [
			[
				'status'       => 'pending',
				'action_count' => 12000,
			],
			[
				'status'       => 'in-progress',
				'action_count' => 3,
			],
			[
				'status'       => 'failed',
				'action_count' => 47,
			],
			[
				'status'       => 'complete',
				'action_count' => 600000,
			],
		];
		$GLOBALS['wpdb']->oldest_pending = gmdate( 'Y-m-d H:i:s', time() - 7200 );
		$GLOBALS['wpdb']->hook_rows      = [
			[
				'hook'         => 'safe_hook',
				'action_count' => 11900,
				'args'         => 'secret-token',
			],
			[
				'hook'         => 'https://private.example/?token=secret',
				'action_count' => 2,
			],
		];
		$GLOBALS['wpdb']->group_rows     = [
			[
				'group_name'   => 'commerce',
				'action_count' => 12000,
			],
		];
		$GLOBALS['wpdb']->log_count      = 900000;

		$audit = ActionSchedulerAudit::refresh();
		$json  = wp_json_encode( $audit );

		$this->assertSame( 12000, $audit['counts']['pending'] );
		$this->assertSame( 3, $audit['counts']['running'] );
		$this->assertSame( 47, $audit['counts']['failed'] );
		$this->assertSame( 612050, $audit['storage']['actionCount'] );
		$this->assertSame( 900000, $audit['storage']['logCount'] );
		$this->assertSame( 'requires-log-message-inference', $audit['failedTrend']['reason'] );
		$this->assertGreaterThanOrEqual( 7100, $audit['oldestPending']['ageSeconds'] );
		$this->assertSame( 'safe_hook', $audit['topHooks'][0]['name'] );
		$this->assertCount( 1, $audit['topHooks'] );
		$this->assertStringNotContainsString( 'secret-token', $json );
		$this->assertStringNotContainsString( 'private.example', $json );
	}

	public function test_cached_snapshot_is_per_site_stale_and_clearable() {
		$GLOBALS['perform_test_is_multisite']                                 = true;
		$GLOBALS['perform_test_blog_id']                                      = 8;
		$GLOBALS['perform_test_options'][ ActionSchedulerAudit::OPTION_NAME ] = [
			'schemaVersion' => ActionSchedulerAudit::SCHEMA_VERSION,
			'status'        => 'ready',
			'expiresAt'     => time() - 1,
		];

		$this->assertTrue( ActionSchedulerAudit::get_cached_snapshot()['isStale'] );
		ActionSchedulerAudit::clear();
		$empty = ActionSchedulerAudit::get_cached_snapshot();
		$this->assertSame( 'not-run', $empty['status'] );
		$this->assertTrue( $empty['isMultisite'] );
		$this->assertSame( 8, $empty['siteId'] );
	}

	public function test_endpoints_require_capability_and_nonce() {
		$GLOBALS['perform_test_current_user_can']['manage_options'] = false;
		$this->run_controller_request( 'refresh' );
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( [], $GLOBALS['wpdb']->data_queries );

		$GLOBALS['perform_test_current_user_can']['manage_options'] = true;
		$GLOBALS['perform_test_nonce_valid']                        = false;
		$_POST['nonce'] = 'invalid';
		$this->run_controller_request( 'refresh' );
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
	}

	public function test_controller_refresh_and_clear_never_change_queue_rows() {
		$_POST['nonce'] = 'valid';
		$this->run_controller_request( 'refresh' );
		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 0, $GLOBALS['wpdb']->mutation_count );

		$this->run_controller_request( 'clear' );
		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 'not-run', $GLOBALS['perform_test_json_response']['data']['audit']['status'] );
		$this->assertSame( 0, $GLOBALS['wpdb']->mutation_count );
	}

	private function run_controller_request( $method ) {
		try {
			$controller = new ActionSchedulerAuditController();
			$controller->{$method}();
			$this->fail( 'Expected the JSON response to end the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
		}
	}
}

// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound -- Focused database fixture for this service.
final class Perform_Test_Action_Scheduler_Wpdb {
	public $prefix          = 'wp_';
	public $last_error      = '';
	public $existing_tables = [ 'wp_actionscheduler_actions', 'wp_actionscheduler_groups', 'wp_actionscheduler_logs' ];
	public $status_rows     = [];
	public $hook_rows       = [];
	public $group_rows      = [];
	public $oldest_pending  = null;
	public $log_count       = 0;
	public $data_queries    = [];
	public $mutation_count  = 0;

	public function prepare( $query, $args ) {
		return $query . ' /* ' . wp_json_encode( $args ) . ' */';
	}

	public function get_var( $query ) {
		$this->data_queries[] = $query;
		if ( false !== strpos( $query, 'SHOW TABLES LIKE' ) ) {
			foreach ( $this->existing_tables as $table ) {
				if ( false !== strpos( $query, $table ) ) {
					return $table;
				}
			}
			return null;
		}
		if ( false !== strpos( $query, 'MIN(scheduled_date_gmt)' ) ) {
			return $this->oldest_pending;
		}
		if ( false !== strpos( $query, 'actionscheduler_logs' ) ) {
			return $this->log_count;
		}
		return null;
	}

	public function get_results( $query, $output ) {
		$this->data_queries[] = $query;
		if ( false !== strpos( $query, 'GROUP BY status' ) ) {
			return $this->status_rows;
		}
		if ( false !== strpos( $query, 'GROUP BY hook' ) ) {
			return $this->hook_rows;
		}
		if ( false !== strpos( $query, 'GROUP BY action_group.slug' ) ) {
			return $this->group_rows;
		}
		return [];
	}
}
