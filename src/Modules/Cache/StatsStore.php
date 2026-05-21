<?php
/**
 * Perform - Cache Stats Store.
 *
 * @package Perform
 * @subpackage Modules/Cache
 */

namespace Perform\Modules\Cache;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StatsStore {
	/**
	 * Option key used for cache stats.
	 *
	 * @var string
	 */
	private $option_key = 'perform_cache_stats';

	/**
	 * In-memory stats buffer.
	 *
	 * @var array<string, mixed>
	 */
	private $stats = [];

	/**
	 * Whether in-memory stats changed and need flush.
	 *
	 * @var bool
	 */
	private $dirty = false;

	/**
	 * High-traffic counters that should be sampled instead of persisted per request.
	 *
	 * @var array<string, bool>
	 */
	private $sampled_counter_keys = [
		'hits'       => true,
		'stale_hits' => true,
		'bypasses'   => true,
		'lock_waits' => true,
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$stats = get_option( $this->option_key, [] );
		$this->stats = is_array( $stats ) ? $stats : [];
	}

	/**
	 * Increment numeric stat key.
	 *
	 * @param string $key Stat key.
	 *
	 * @return void
	 */
	public function increment( $key ) {
		$increment = $this->get_increment_sample_size( $key );
		if ( 0 === $increment ) {
			return;
		}

		$this->stats[ $key ] = isset( $this->stats[ $key ] ) ? ( (int) $this->stats[ $key ] + $increment ) : $increment;
		$this->dirty = true;
	}

	/**
	 * Get sampled increment size for hot cache counters.
	 *
	 * @param string $key Stat key.
	 *
	 * @return int
	 */
	private function get_increment_sample_size( $key ) {
		if ( empty( $this->sampled_counter_keys[ $key ] ) ) {
			return 1;
		}

		$sample_rate = (int) apply_filters( 'perform_cache_stats_sample_rate', 20, $key );
		$sample_rate = max( 1, $sample_rate );

		if ( 1 === $sample_rate ) {
			return 1;
		}

		return 1 === wp_rand( 1, $sample_rate ) ? $sample_rate : 0;
	}

	/**
	 * Set value for stat key.
	 *
	 * @param string $key Stat key.
	 * @param mixed  $value Stat value.
	 *
	 * @return void
	 */
	public function set_value( $key, $value ) {
		$this->stats[ $key ] = $value;
		$this->dirty = true;
	}

	/**
	 * Get map stat.
	 *
	 * @param string $key Stat key.
	 *
	 * @return array<string, int|float>
	 */
	public function get_map( $key ) {
		if ( ! isset( $this->stats[ $key ] ) || ! is_array( $this->stats[ $key ] ) ) {
			return [];
		}

		return $this->stats[ $key ];
	}

	/**
	 * Set map stat.
	 *
	 * @param string                  $key Stat key.
	 * @param array<string, int|float> $value Stat value.
	 *
	 * @return void
	 */
	public function set_map( $key, $value ) {
		$this->stats[ $key ] = $value;
		$this->dirty = true;
	}

	/**
	 * Flush buffered stats to DB if changed.
	 *
	 * @return void
	 */
	public function flush() {
		if ( ! $this->dirty ) {
			return;
		}

		update_option( $this->option_key, $this->stats, false );
		$this->dirty = false;
	}
}
