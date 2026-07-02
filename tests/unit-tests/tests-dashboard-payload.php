<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\DashboardPayload;

final class Tests_Dashboard_Payload extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_options'] );
	}

	public function test_cache_summary_uses_existing_stats_without_writes() {
		$GLOBALS['perform_test_options']['perform_cache_stats'] = [
			'hits'               => 80,
			'misses'             => 15,
			'stale_hits'         => 5,
			'bypasses'           => 7,
			'preload_queue_size' => 3,
			'preload_requests'   => 11,
			'slow_uncached'      => [
				'/slow' => 1200,
			],
		];

		$payload = DashboardPayload::get_data();

		$this->assertTrue( $payload['cache']['hasStats'] );
		$this->assertSame( 85.0, $payload['cache']['hitRatio'] );
		$this->assertSame( 80, $payload['cache']['hits'] );
		$this->assertSame( 15, $payload['cache']['misses'] );
		$this->assertSame( 5, $payload['cache']['staleHits'] );
		$this->assertSame( 7, $payload['cache']['bypasses'] );
		$this->assertSame( 3, $payload['cache']['preloadQueueSize'] );
		$this->assertSame( 11, $payload['cache']['preloadRequests'] );
		$this->assertSame( 1, $payload['cache']['slowUncachedCount'] );
	}

	public function test_assets_manager_summary_counts_rule_coverage_not_bytes_saved() {
		$GLOBALS['perform_test_options']['perform_assets_manager_options'] = [
			'disabled' => [
				'js'      => [
					'first-script'  => [
						'everywhere' => 1,
					],
					'second-script' => [
						'current' => [ 42 ],
					],
				],
				'css'     => [
					'theme-style' => [
						'everywhere' => 1,
					],
				],
				'plugins' => [
					'example-plugin' => [
						'everywhere' => 1,
					],
				],
			],
			'enabled'  => [
				'js'  => [
					'first-script' => [
						'current' => [ 42, 43 ],
					],
				],
				'css' => [
					'theme-style' => [
						'current' => [ 42 ],
					],
				],
			],
		];

		$payload = DashboardPayload::get_data();

		$this->assertSame( 2, $payload['assetsManager']['disabledJsHandles'] );
		$this->assertSame( 1, $payload['assetsManager']['disabledCssHandles'] );
		$this->assertSame( 1, $payload['assetsManager']['groupDisabledRules'] );
		$this->assertSame( 3, $payload['assetsManager']['currentPageExceptions'] );
		$this->assertSame( 1, $payload['assetsManager']['currentPageDisabledRules'] );
	}

	public function test_empty_dashboard_payload_has_safe_defaults() {
		$payload = DashboardPayload::get_data();

		$this->assertFalse( $payload['cache']['hasStats'] );
		$this->assertSame( 0.0, $payload['cache']['hitRatio'] );
		$this->assertSame( 0, $payload['assetsManager']['disabledJsHandles'] );
		$this->assertSame( 0, $payload['assetsManager']['disabledCssHandles'] );
		$this->assertNotEmpty( $payload['changelog'] );
	}
}
