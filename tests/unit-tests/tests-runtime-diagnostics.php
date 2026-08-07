<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\RuntimeDiagnostics;

final class Tests_Runtime_Diagnostics extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['perform_test_options']          = [];
		$GLOBALS['perform_test_filters']          = [];
		$GLOBALS['perform_test_scheduled_events'] = [];
		$GLOBALS['perform_test_is_block_theme']   = false;
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_filters'],
			$GLOBALS['perform_test_scheduled_events'],
			$GLOBALS['perform_test_is_block_theme']
		);

		parent::tearDown();
	}

	public function test_reports_invalid_cdn_url_as_needs_attention() {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'enable_cdn' => 1,
				'cdn_url'    => 'not-a-valid-url',
			],
		];

		$item = $this->find_item( RuntimeDiagnostics::get_results(), 'CDN configuration' );

		$this->assertSame( 'needs-attention', $item['status'] );
		$this->assertStringContainsString( 'missing or invalid', $item['message'] );
	}

	public function test_reports_unscheduled_preload_when_enabled() {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'enable_cache_preload' => 1,
			],
		];

		$item = $this->find_item( RuntimeDiagnostics::get_results(), 'Cache preload schedule' );

		$this->assertSame( 'warning', $item['status'] );
		$this->assertStringContainsString( 'not scheduled', $item['message'] );
	}

	public function test_reports_menu_cache_warning_for_block_theme_without_filter_opt_in() {
		$GLOBALS['perform_test_options']        = [
			'perform_settings' => [
				'enable_navigation_menu_cache' => 1,
			],
		];
		$GLOBALS['perform_test_is_block_theme'] = true;

		$item = $this->find_item( RuntimeDiagnostics::get_results(), 'Menu cache theme support' );

		$this->assertSame( 'warning', $item['status'] );
		$this->assertStringContainsString( 'block theme', $item['message'] );
	}

	public function test_reports_dynamic_exclusion_warning_when_page_cache_has_no_rules() {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'enable_page_cache' => 1,
			],
		];

		$item = $this->find_item( RuntimeDiagnostics::get_results(), 'Dynamic request exclusions' );

		$this->assertSame( 'warning', $item['status'] );
		$this->assertStringContainsString( 'No site-specific cache bypass rules', $item['message'] );
	}

	public function test_summary_counts_diagnostic_statuses() {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'enable_cdn'           => 1,
				'cdn_url'              => 'bad-url',
				'enable_cache_preload' => 1,
			],
		];

		$results = RuntimeDiagnostics::get_results();

		$this->assertSame( 1, $results['summary']['needs-attention'] );
		$this->assertGreaterThanOrEqual( 1, $results['summary']['warning'] );
		$this->assertCount( 6, $results['items'] );
	}

	private function find_item( array $results, string $label ): array {
		foreach ( $results['items'] as $item ) {
			if ( $label === $item['label'] ) {
				return $item;
			}
		}

		$this->fail( 'Missing diagnostic item: ' . $label );
	}
}
