<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Cache\CacheActivityService;

final class Tests_Cache_Activity_Service extends TestCase {
	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_options']['perform_cache_stats'] );
	}

	public function test_export_contains_scalar_and_map_activity() {
		$service = new CacheActivityService();
		$csv     = $service->export_csv(
			[
				'hits'       => 24,
				'top_misses' => [
					'/pricing/' => 3,
				],
			]
		);

		$this->assertStringContainsString( 'Metric,Item,Value', $csv );
		$this->assertStringContainsString( 'Hits,,24', $csv );
		$this->assertStringContainsString( '"Top Misses",/pricing/,3', $csv );
	}

	public function test_empty_export_contains_only_the_header() {
		$service = new CacheActivityService();

		$this->assertSame( "Metric,Item,Value\n", $service->export_csv( [] ) );
	}

	public function test_export_is_bounded_and_ignores_unsupported_values() {
		$map = [];
		for ( $index = 0; $index < CacheActivityService::MAX_EXPORT_ROWS + 50; ++$index ) {
			$map[ '/page-' . $index . '/' ] = $index;
		}

		$service = new CacheActivityService();
		$rows    = $service->get_export_rows(
			[
				'top_misses'  => $map,
				'unsupported' => new stdClass(),
			]
		);

		$this->assertCount( CacheActivityService::MAX_EXPORT_ROWS, $rows );
	}

	public function test_export_neutralizes_spreadsheet_formulas() {
		$service = new CacheActivityService();
		$rows    = $service->get_export_rows(
			[
				'top_misses' => [
					'=HYPERLINK("https://example.com")' => 1,
				],
			]
		);

		$this->assertSame( '\'=HYPERLINK("https://example.com")', $rows[0][1] );
	}

	public function test_clear_removes_only_cache_activity() {
		$GLOBALS['perform_test_options']['perform_cache_stats'] = [ 'hits' => 10 ];
		$GLOBALS['perform_test_options']['perform_settings']    = [ 'enable_page_cache' => 1 ];

		( new CacheActivityService() )->clear();

		$this->assertArrayNotHasKey( 'perform_cache_stats', $GLOBALS['perform_test_options'] );
		$this->assertSame(
			[ 'enable_page_cache' => 1 ],
			$GLOBALS['perform_test_options']['perform_settings']
		);
	}
}
