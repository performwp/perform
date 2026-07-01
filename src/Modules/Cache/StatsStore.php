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
	 * Stats snapshot loaded at request start.
	 *
	 * @var array<string, mixed>
	 */
	private $base_stats = [];

	/**
	 * Buffered numeric counter increments.
	 *
	 * @var array<string, int>
	 */
	private $increments = [];

	/**
	 * Buffered scalar values that should replace the stored value.
	 *
	 * @var array<string, mixed>
	 */
	private $values = [];

	/**
	 * Buffered map stats.
	 *
	 * @var array<string, array<string, int|float>>
	 */
	private $maps = [];

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
		'misses'     => true,
		'stale_hits' => true,
		'bypasses'   => true,
		'lock_waits' => true,
	];

	/**
	 * Map stats whose values are additive counters.
	 *
	 * @var array<string, bool>
	 */
	private $additive_map_keys = [
		'bypass_reasons' => true,
		'top_misses'     => true,
	];

	/**
	 * Maximum number of entries to persist for map stats.
	 *
	 * @var array<string, int>
	 */
	private $map_limits = [
		'bypass_reasons' => 20,
		'top_misses'     => 50,
		'slow_uncached'  => 30,
	];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$stats            = get_option( $this->option_key, [] );
		$this->stats      = is_array( $stats ) ? $stats : [];
		$this->base_stats = $this->stats;
	}

	/**
	 * Increment numeric stat key.
	 *
	 * @param string $key Stat key.
	 *
	 * @return int Increment size applied to the counter.
	 */
	public function increment( $key ) {
		$increment = $this->get_increment_sample_size( $key );
		if ( 0 === $increment ) {
			return 0;
		}

		$this->stats[ $key ]      = isset( $this->stats[ $key ] ) ? ( (int) $this->stats[ $key ] + $increment ) : $increment;
		$this->increments[ $key ] = isset( $this->increments[ $key ] ) ? ( $this->increments[ $key ] + $increment ) : $increment;
		$this->dirty              = true;

		return $increment;
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
		$this->stats[ $key ]  = $value;
		$this->values[ $key ] = $value;
		$this->dirty          = true;
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
		$value = $this->sort_and_limit_map( $key, $value );

		$this->stats[ $key ] = $value;
		$this->maps[ $key ]  = $value;
		$this->dirty         = true;
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

		$current = get_option( $this->option_key, [] );
		$current = is_array( $current ) ? $current : [];

		foreach ( $this->increments as $key => $increment ) {
			$current[ $key ] = isset( $current[ $key ] ) ? ( (int) $current[ $key ] + $increment ) : $increment;
		}

		foreach ( $this->values as $key => $value ) {
			$current[ $key ] = $value;
		}

		foreach ( $this->maps as $key => $map ) {
			$current_map = $this->normalize_map( $current[ $key ] ?? [] );

			if ( ! empty( $this->additive_map_keys[ $key ] ) ) {
				$base_map = $this->normalize_map( $this->base_stats[ $key ] ?? [] );
				foreach ( $map as $map_key => $value ) {
					$delta = $value - ( $base_map[ $map_key ] ?? 0 );
					if ( 0 >= $delta ) {
						continue;
					}

					$current_map[ $map_key ] = ( $current_map[ $map_key ] ?? 0 ) + $delta;
				}
			} else {
				foreach ( $map as $map_key => $value ) {
					$current_map[ $map_key ] = isset( $current_map[ $map_key ] ) ? max( $current_map[ $map_key ], $value ) : $value;
				}
			}

			$current[ $key ] = $this->sort_and_limit_map( $key, $current_map );
		}

		update_option( $this->option_key, $current, false );

		$this->stats      = $current;
		$this->base_stats = $current;
		$this->increments = [];
		$this->values     = [];
		$this->maps       = [];
		$this->dirty      = false;
	}

	/**
	 * Normalize a map stat to numeric values.
	 *
	 * @param mixed $map Map value.
	 *
	 * @return array<string, int|float>
	 */
	private function normalize_map( $map ) {
		if ( ! is_array( $map ) ) {
			return [];
		}

		$normalized = [];
		foreach ( $map as $key => $value ) {
			if ( ! is_numeric( $value ) ) {
				continue;
			}

			$normalized[ (string) $key ] = $value + 0;
		}

		return $normalized;
	}

	/**
	 * Sort and limit a map stat.
	 *
	 * @param string                  $key Stat key.
	 * @param array<string, int|float> $map Map value.
	 *
	 * @return array<string, int|float>
	 */
	private function sort_and_limit_map( $key, array $map ) {
		arsort( $map );

		$limit = $this->map_limits[ $key ] ?? 0;
		if ( 0 < $limit ) {
			$map = array_slice( $map, 0, $limit, true );
		}

		return $map;
	}
}
