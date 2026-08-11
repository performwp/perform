<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\CronPressureAudit;
use Perform\Admin\Settings\CronPressureAuditController;

final class Tests_Cron_Pressure_Audit extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options']          = [];
		$GLOBALS['perform_test_filters']          = [];
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']      = true;
		$GLOBALS['perform_test_is_multisite']     = false;
		$GLOBALS['perform_test_blog_id']          = 1;
		$_POST                                    = [];
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_filters'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_nonce_valid'],
			$GLOBALS['perform_test_is_multisite'],
			$GLOBALS['perform_test_blog_id'],
			$GLOBALS['perform_test_json_response']
		);
		$_POST = [];
	}

	public function test_refresh_collects_bounded_aggregate_pressure_without_arguments() {
		$now = time();
		$GLOBALS['perform_test_filters']['perform_cron_pressure_events'] = [
			$now - 600 => [
				'private_hook' => [
					'first'  => [
						'schedule' => 'hourly',
						'args'     => [ 'secret-token' ],
					],
					'second' => [
						'schedule' => false,
						'args'     => [ 'customer@example.com' ],
					],
				],
			],
			$now - 10  => [
				'other_hook' => [
					'third' => [
						'schedule' => false,
						'args'     => [ 'private-url' ],
					],
				],
			],
			$now + 120 => [
				'private_hook' => [
					'fourth' => [
						'schedule' => 'hourly',
						'interval' => 3600,
					],
					'fifth'  => [
						'schedule' => 'hourly',
						'interval' => 3600,
					],
					'sixth'  => [
						'schedule' => 'hourly',
						'interval' => 3600,
					],
				],
			],
		];

		$audit = CronPressureAudit::refresh();
		$json  = wp_json_encode( $audit );

		$this->assertSame( 6, $audit['totals']['scanned'] );
		$this->assertSame( 3, $audit['totals']['due'] );
		$this->assertSame( 2, $audit['totals']['overdue'] );
		$this->assertSame( 4, $audit['totals']['recurring'] );
		$this->assertSame( 3, $audit['totals']['nextHour'] );
		$this->assertSame( 3, $audit['totals']['peakCluster'] );
		$this->assertSame( 'private_hook', $audit['frequentHooks'][0]['hook'] );
		$this->assertSame( 5, $audit['frequentHooks'][0]['count'] );
		$this->assertStringNotContainsString( 'secret-token', $json );
		$this->assertStringNotContainsString( 'customer@example.com', $json );
		$this->assertStringNotContainsString( 'private-url', $json );
		$this->assertSame( $audit, $GLOBALS['perform_test_options'][ CronPressureAudit::OPTION_NAME ] );
	}

	public function test_refresh_hard_caps_large_schedules() {
		$instances = [];
		for ( $index = 0; $index <= CronPressureAudit::EVENT_LIMIT; ++$index ) {
			$instances[ 'signature-' . $index ] = [ 'schedule' => 'hourly' ];
		}
		$GLOBALS['perform_test_filters']['perform_cron_pressure_events'] = [
			time() + 60 => [ 'large_hook' => $instances ],
		];

		$audit = CronPressureAudit::refresh();

		$this->assertSame( CronPressureAudit::EVENT_LIMIT, $audit['totals']['scanned'] );
		$this->assertTrue( $audit['totals']['truncated'] );
		$this->assertSame( 'review', $audit['guidance']['status'] );
	}

	public function test_cached_snapshot_is_per_site_stale_and_clearable() {
		$GLOBALS['perform_test_is_multisite']                              = true;
		$GLOBALS['perform_test_blog_id']                                   = 9;
		$GLOBALS['perform_test_options'][ CronPressureAudit::OPTION_NAME ] = [
			'schemaVersion' => CronPressureAudit::SCHEMA_VERSION,
			'status'        => 'ready',
			'expiresAt'     => time() - 1,
		];

		$audit = CronPressureAudit::get_cached_snapshot();
		$this->assertTrue( $audit['isStale'] );

		CronPressureAudit::clear();
		$empty = CronPressureAudit::get_cached_snapshot();
		$this->assertSame( 'not-run', $empty['status'] );
		$this->assertTrue( $empty['isMultisite'] );
		$this->assertSame( 9, $empty['siteId'] );
	}

	public function test_refresh_endpoint_requires_capability_and_nonce() {
		$GLOBALS['perform_test_current_user_can']['manage_options'] = false;
		$this->run_controller_request( 'refresh' );
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 'Insufficient permissions.', $GLOBALS['perform_test_json_response']['data']['message'] );

		$GLOBALS['perform_test_current_user_can']['manage_options'] = true;
		$GLOBALS['perform_test_nonce_valid']                        = false;
		$_POST['nonce'] = 'invalid';
		$this->run_controller_request( 'refresh' );
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 'Security check failed.', $GLOBALS['perform_test_json_response']['data']['message'] );
	}

	public function test_controller_refresh_and_clear_return_safe_snapshots() {
		$_POST['nonce'] = 'valid';
		$GLOBALS['perform_test_filters']['perform_cron_pressure_events'] = [];

		$this->run_controller_request( 'refresh' );
		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 'ready', $GLOBALS['perform_test_json_response']['data']['audit']['status'] );

		$this->run_controller_request( 'clear' );
		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 'not-run', $GLOBALS['perform_test_json_response']['data']['audit']['status'] );
	}

	private function run_controller_request( $method ) {
		try {
			$controller = new CronPressureAuditController();
			$controller->{$method}();
			$this->fail( 'Expected the JSON response to end the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
		}
	}
}
