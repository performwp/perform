<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Cache\StatsStore;

final class Tests_Cache_Stats_Store extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options'] = [];
		$GLOBALS['perform_test_filters'] = [
			'perform_cache_stats_sample_rate' => 1,
		];
		unset( $GLOBALS['perform_test_wp_rand'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_filters'], $GLOBALS['perform_test_wp_rand'] );
	}

	public function test_flush_merges_buffered_increments_with_latest_option() {
		$GLOBALS['perform_test_options']['perform_cache_stats'] = [
			'hits'       => 10,
			'top_misses' => [
				'/a' => 2,
			],
		];

		$store = new StatsStore();
		$store->increment( 'hits' );

		$top_misses       = $store->get_map( 'top_misses' );
		$top_misses['/a'] = 3;
		$top_misses['/b'] = 1;
		$store->set_map( 'top_misses', $top_misses );

		$GLOBALS['perform_test_options']['perform_cache_stats'] = [
			'hits'       => 20,
			'top_misses' => [
				'/a' => 5,
				'/c' => 4,
			],
		];

		$store->flush();

		$this->assertSame( 21, $GLOBALS['perform_test_options']['perform_cache_stats']['hits'] );
		$this->assertSame( 6, $GLOBALS['perform_test_options']['perform_cache_stats']['top_misses']['/a'] );
		$this->assertSame( 1, $GLOBALS['perform_test_options']['perform_cache_stats']['top_misses']['/b'] );
		$this->assertSame( 4, $GLOBALS['perform_test_options']['perform_cache_stats']['top_misses']['/c'] );
	}

	public function test_sampled_counter_does_not_dirty_when_sample_is_skipped() {
		$GLOBALS['perform_test_options']['perform_cache_stats']             = [
			'hits' => 10,
		];
		$GLOBALS['perform_test_filters']['perform_cache_stats_sample_rate'] = 20;
		$GLOBALS['perform_test_wp_rand']                                    = 2;

		$store = new StatsStore();

		$this->assertSame( 0, $store->increment( 'hits' ) );
		$store->flush();

		$this->assertSame( 10, $GLOBALS['perform_test_options']['perform_cache_stats']['hits'] );
	}
}
