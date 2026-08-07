<?php
/**
 * Perform - Admin Dashboard Payload.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DashboardPayload {
	/**
	 * Build the read-only dashboard payload for the admin app.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_data() {
		return [
			'version'       => defined( 'PERFORM_VERSION' ) ? PERFORM_VERSION : '',
			'links'         => self::get_links(),
			'cache'         => self::get_cache_summary(),
			'assetsManager' => self::get_assets_manager_summary(),
			'changelog'     => self::get_changelog_items(),
		];
	}

	/**
	 * Get dashboard links.
	 *
	 * @return array<string, string>
	 */
	private static function get_links() {
		$docs_url = defined( 'PERFORM_PLUGIN_DOCS_URL' ) ? PERFORM_PLUGIN_DOCS_URL : 'https://performwp.com/docs/';

		return [
			'docs'          => esc_url( $docs_url ),
			'support'       => esc_url( 'https://wordpress.org/support/plugin/perform/' ),
			'assetsManager' => esc_url( $docs_url . 'assets-manager/' ),
			'cache'         => esc_url( $docs_url . 'page-cache/' ),
			'releaseNotes'  => esc_url( 'https://performwp.com/' ),
		];
	}

	/**
	 * Build cache statistics summary from the existing stats option.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_cache_summary() {
		$stats = get_option( 'perform_cache_stats', [] );
		$stats = is_array( $stats ) ? $stats : [];

		$hits       = self::get_int( $stats, 'hits' );
		$misses     = self::get_int( $stats, 'misses' );
		$stale_hits = self::get_int( $stats, 'stale_hits' );
		$bypasses   = self::get_int( $stats, 'bypasses' );
		$requests   = $hits + $misses + $stale_hits;
		$hit_ratio  = 0 < $requests ? round( ( ( $hits + $stale_hits ) / $requests ) * 100, 1 ) : 0.0;

		return [
			'hasStats'          => ! empty( $stats ),
			'hitRatio'          => $hit_ratio,
			'hits'              => $hits,
			'misses'            => $misses,
			'staleHits'         => $stale_hits,
			'bypasses'          => $bypasses,
			'preloadQueueSize'  => self::get_int( $stats, 'preload_queue_size' ),
			'preloadRequests'   => self::get_int( $stats, 'preload_requests' ),
			'slowUncachedCount' => isset( $stats['slow_uncached'] ) && is_array( $stats['slow_uncached'] ) ? count( $stats['slow_uncached'] ) : 0,
		];
	}

	/**
	 * Build Assets Manager rule coverage summary from existing options.
	 *
	 * @return array<string, int>
	 */
	private static function get_assets_manager_summary() {
		$options  = get_option( 'perform_assets_manager_options', [] );
		$options  = is_array( $options ) ? $options : [];
		$disabled = isset( $options['disabled'] ) && is_array( $options['disabled'] ) ? $options['disabled'] : [];
		$enabled  = isset( $options['enabled'] ) && is_array( $options['enabled'] ) ? $options['enabled'] : [];

		return [
			'disabledJsHandles'        => self::count_handle_rules( $disabled, 'js' ),
			'disabledCssHandles'       => self::count_handle_rules( $disabled, 'css' ),
			'groupDisabledRules'       => self::count_group_rules( $disabled ),
			'currentPageExceptions'    => self::count_current_page_exceptions( $enabled ),
			'currentPageDisabledRules' => self::count_current_page_exceptions( $disabled ),
		];
	}

	/**
	 * Get local release notes for the current bundled line.
	 *
	 * @return array<int, string>
	 */
	private static function get_changelog_items() {
		return [
			__( 'Dashboard, diagnostics, cache, and Assets Manager work in 1.7.0 remains release-candidate content until the owner approves publication.', 'perform' ),
			__( 'Runtime diagnostics summarize cache, CDN, menu cache, and dynamic request prerequisites without writing new tracking data.', 'perform' ),
			__( 'Assets Manager summary reports configured rule coverage, not historical byte savings.', 'perform' ),
		];
	}

	/**
	 * Count disabled or enabled handle rule groups for one asset type.
	 *
	 * @param array<string, mixed> $rules Rules.
	 * @param string              $type Asset type.
	 *
	 * @return int
	 */
	private static function count_handle_rules( array $rules, $type ) {
		if ( empty( $rules[ $type ] ) || ! is_array( $rules[ $type ] ) ) {
			return 0;
		}

		return count(
			array_filter(
				$rules[ $type ],
				static function ( $rule ) {
					return is_array( $rule ) && ! empty( $rule );
				}
			)
		);
	}

	/**
	 * Count group-level disabled rules.
	 *
	 * @param array<string, mixed> $disabled Disabled rules.
	 *
	 * @return int
	 */
	private static function count_group_rules( array $disabled ) {
		$count = 0;

		foreach ( $disabled as $category => $groups ) {
			if ( in_array( $category, [ 'js', 'css' ], true ) || ! is_array( $groups ) ) {
				continue;
			}

			foreach ( $groups as $group_rule ) {
				if ( is_array( $group_rule ) && ! empty( $group_rule ) ) {
					++$count;
				}
			}
		}

		return $count;
	}

	/**
	 * Count current-page rule entries across JS/CSS maps.
	 *
	 * @param array<string, mixed> $rules Rules.
	 *
	 * @return int
	 */
	private static function count_current_page_exceptions( array $rules ) {
		$count = 0;

		foreach ( [ 'js', 'css' ] as $type ) {
			if ( empty( $rules[ $type ] ) || ! is_array( $rules[ $type ] ) ) {
				continue;
			}

			foreach ( $rules[ $type ] as $rule ) {
				if ( isset( $rule['current'] ) && is_array( $rule['current'] ) ) {
					$count += count( array_filter( $rule['current'], 'is_scalar' ) );
				}
			}
		}

		return $count;
	}

	/**
	 * Get an integer stat from an array.
	 *
	 * @param array<string, mixed> $stats Stats.
	 * @param string              $key Key.
	 *
	 * @return int
	 */
	private static function get_int( array $stats, $key ) {
		return isset( $stats[ $key ] ) && is_numeric( $stats[ $key ] ) ? max( 0, (int) $stats[ $key ] ) : 0;
	}
}
