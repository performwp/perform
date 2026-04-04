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
		$this->stats[ $key ] = isset( $this->stats[ $key ] ) ? ( (int) $this->stats[ $key ] + 1 ) : 1;
		$this->dirty = true;
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
