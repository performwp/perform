<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\AdminPerformanceMonitor;
use Perform\Admin\Settings\AdminPerformanceMonitorController;

final class Tests_Admin_Performance_Monitor extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options']          = [];
		$GLOBALS['perform_test_actions']          = [];
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']      = true;
		$_POST                                    = [];
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_actions'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_nonce_valid'],
			$GLOBALS['perform_test_json_response']
		);
		$_POST = [];
	}

	public function test_disabled_mode_registers_no_capture_hooks() {
		$monitor = new AdminPerformanceMonitor( [ 'enable_admin_performance_monitor' => 0 ] );
		$monitor->capture_request();

		$this->assertSame( [], $GLOBALS['perform_test_actions'] );
		$this->assertArrayNotHasKey( AdminPerformanceMonitor::OPTION_NAME, $GLOBALS['perform_test_options'] );
	}

	public function test_enabled_mode_registers_only_screen_and_shutdown_capture_hooks() {
		new AdminPerformanceMonitor( [ 'enable_admin_performance_monitor' => 1 ] );

		$this->assertSame( [ 'current_screen', 'shutdown' ], array_column( $GLOBALS['perform_test_actions'], 'hook' ) );
	}

	public function test_aggregate_metrics_are_bounded_and_privacy_filtered() {
		$this->enable_monitor();
		$now = 1700000000;

		AdminPerformanceMonitor::record_sample(
			[
				'type'             => 'admin-page',
				'identifier'       => 'edit-book',
				'capabilityBucket' => 'manage-options',
				'durationMs'       => 1200,
				'peakMemoryBytes'  => 140000000,
				'queryCount'       => 140,
			],
			$now
		);
		$snapshot = AdminPerformanceMonitor::record_sample(
			[
				'type'             => 'admin-page',
				'identifier'       => 'edit-book',
				'capabilityBucket' => 'manage-options',
				'durationMs'       => 800,
				'peakMemoryBytes'  => 100000000,
				'queryCount'       => 80,
			],
			$now + 1
		);
		AdminPerformanceMonitor::record_sample(
			[
				'type'             => 'ajax',
				'identifier'       => 'user@example.com?nonce=secret',
				'capabilityBucket' => 'edit-content',
				'durationMs'       => 50,
			],
			$now + 2
		);

		$context = $snapshot['contexts'][0];
		$this->assertSame( 2, $context['count'] );
		$this->assertSame( 1000.0, $context['averageDurationMs'] );
		$this->assertSame( 'review', $context['status'] );
		$this->assertSame( [ 'slow-request', 'high-memory', 'high-query-count' ], $context['reasons'] );

		$stored = wp_json_encode( $GLOBALS['perform_test_options'][ AdminPerformanceMonitor::OPTION_NAME ] );
		$this->assertStringNotContainsString( 'example.com', $stored );
		$this->assertStringNotContainsString( 'secret', $stored );
		$this->assertStringContainsString( 'unknown-action', $stored );
		$this->assertStringNotContainsString( 'username', $stored );
		$this->assertStringNotContainsString( 'queryText', $stored );
	}

	public function test_retention_and_context_limit_are_enforced() {
		$this->enable_monitor();
		$now = 1700000000;

		AdminPerformanceMonitor::record_sample(
			[
				'type'       => 'admin-page',
				'identifier' => 'expired-screen',
			],
			$now - ( 15 * 86400 )
		);

		for ( $index = 0; $index < 55; ++$index ) {
			AdminPerformanceMonitor::record_sample(
				[
					'type'       => 'admin-page',
					'identifier' => 'screen-' . $index,
					'durationMs' => $index,
				],
				$now + $index
			);
		}

		$snapshot    = AdminPerformanceMonitor::get_snapshot();
		$identifiers = array_column( $snapshot['contexts'], 'identifier' );

		$this->assertCount( AdminPerformanceMonitor::MAX_CONTEXTS, $snapshot['contexts'] );
		$this->assertNotContains( 'expired-screen', $identifiers );
		$this->assertContains( 'screen-54', $identifiers );
		$this->assertNotContains( 'screen-0', $identifiers );
	}

	public function test_report_distinguishes_supported_request_types() {
		$this->enable_monitor();
		$types = [
			'admin-page'    => 'dashboard',
			'ajax'          => 'heartbeat',
			'rest'          => 'rest-api',
			'cron-adjacent' => 'wp-cron',
		];

		foreach ( $types as $type => $identifier ) {
			AdminPerformanceMonitor::record_sample(
				[
					'type'       => $type,
					'identifier' => $identifier,
				],
				1700000000
			);
		}

		$this->assertEqualsCanonicalizing( array_keys( $types ), array_column( AdminPerformanceMonitor::get_snapshot()['contexts'], 'type' ) );
	}

	public function test_clear_endpoint_requires_manage_options_and_valid_nonce() {
		$this->enable_monitor();
		AdminPerformanceMonitor::record_sample(
			[
				'type'       => 'admin-page',
				'identifier' => 'dashboard',
			],
			1700000000
		);
		$GLOBALS['perform_test_current_user_can']['manage_options'] = false;

		$this->run_clear_request();
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertArrayHasKey( AdminPerformanceMonitor::OPTION_NAME, $GLOBALS['perform_test_options'] );

		$GLOBALS['perform_test_current_user_can']['manage_options'] = true;
		$GLOBALS['perform_test_nonce_valid']                        = false;
		$_POST['nonce'] = 'invalid';
		$this->run_clear_request();
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertArrayHasKey( AdminPerformanceMonitor::OPTION_NAME, $GLOBALS['perform_test_options'] );
	}

	public function test_clear_endpoint_removes_current_site_aggregates() {
		$this->enable_monitor();
		AdminPerformanceMonitor::record_sample(
			[
				'type'       => 'admin-page',
				'identifier' => 'dashboard',
			],
			1700000000
		);
		$_POST['nonce'] = 'valid';

		$this->run_clear_request();

		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( [], $GLOBALS['perform_test_json_response']['data']['snapshot']['contexts'] );
		$this->assertArrayNotHasKey( AdminPerformanceMonitor::OPTION_NAME, $GLOBALS['perform_test_options'] );
	}

	private function enable_monitor() {
		$GLOBALS['perform_test_options']['perform_settings'] = [ 'enable_admin_performance_monitor' => 1 ];
	}

	private function run_clear_request() {
		try {
			( new AdminPerformanceMonitorController() )->clear();
			$this->fail( 'Expected the JSON response to end the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
		}
	}
}
