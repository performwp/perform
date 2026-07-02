<?php
/**
 * Perform - Runtime Diagnostics.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RuntimeDiagnostics {
	/**
	 * Build read-only diagnostics for the settings UI.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_results() {
		$settings = Helpers::get_settings();
		$settings = is_array( $settings ) ? $settings : [];

		$items = [
			self::diagnose_page_cache_storage( $settings ),
			self::diagnose_page_cache_dropin(),
			self::diagnose_cache_preload_schedule( $settings ),
			self::diagnose_dynamic_cache_exclusions( $settings ),
			self::diagnose_menu_cache_theme_support( $settings ),
			self::diagnose_cdn_configuration( $settings ),
		];

		$summary = [
			'ready'           => 0,
			'warning'         => 0,
			'needs-attention' => 0,
		];

		foreach ( $items as $item ) {
			$status = isset( $item['status'] ) ? (string) $item['status'] : 'warning';
			if ( ! isset( $summary[ $status ] ) ) {
				$status = 'warning';
			}

			++$summary[ $status ];
		}

		return [
			'summary' => $summary,
			'items'   => $items,
		];
	}

	/**
	 * Check whether page-cache storage appears writable without creating files.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, string>
	 */
	private static function diagnose_page_cache_storage( array $settings ) {
		if ( empty( $settings['enable_page_cache'] ) ) {
			return self::item(
				'ready',
				__( 'Page cache storage', 'perform' ),
				__( 'Page cache is disabled, so no cache storage is required yet.', 'perform' ),
				__( 'Enable full-page cache when you are ready to test storage prerequisites.', 'perform' )
			);
		}

		$cache_dir      = self::get_cache_dir();
		$cache_parent   = dirname( $cache_dir );
		$storage_ready  = is_dir( $cache_dir ) ? is_writable( $cache_dir ) : ( is_dir( $cache_parent ) && is_writable( $cache_parent ) );
		$storage_status = $storage_ready ? 'ready' : 'needs-attention';

		return self::item(
			$storage_status,
			__( 'Page cache storage', 'perform' ),
			$storage_ready
				? __( 'Perform can use the cache storage location for generated HTML files.', 'perform' )
				: __( 'Perform cannot confirm writable cache storage for generated HTML files.', 'perform' ),
			$storage_ready
				? __( 'No action needed.', 'perform' )
				: __( 'Ask your host to allow WordPress to write to its cache directory before relying on full-page cache.', 'perform' )
		);
	}

	/**
	 * Check whether another page-cache drop-in is present.
	 *
	 * @return array<string, string>
	 */
	private static function diagnose_page_cache_dropin() {
		$dropin_exists = file_exists( trailingslashit( self::get_wp_content_dir() ) . 'advanced-cache.php' );

		return self::item(
			$dropin_exists ? 'warning' : 'ready',
			__( 'Page cache drop-in', 'perform' ),
			$dropin_exists
				? __( 'A page-cache drop-in is present, so another cache layer may already be active.', 'perform' )
				: __( 'No page-cache drop-in was detected.', 'perform' ),
			$dropin_exists
				? __( 'Review host or plugin-level page caching before enabling overlapping cache behavior.', 'perform' )
				: __( 'No action needed.', 'perform' )
		);
	}

	/**
	 * Check cache preload scheduling prerequisites.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, string>
	 */
	private static function diagnose_cache_preload_schedule( array $settings ) {
		if ( empty( $settings['enable_cache_preload'] ) ) {
			return self::item(
				'ready',
				__( 'Cache preload schedule', 'perform' ),
				__( 'Adaptive cache preload is disabled.', 'perform' ),
				__( 'Enable preload only after page cache is working reliably.', 'perform' )
			);
		}

		$scheduled = function_exists( 'wp_next_scheduled' ) && wp_next_scheduled( 'perform_cache_preload_event' );

		return self::item(
			$scheduled ? 'ready' : 'warning',
			__( 'Cache preload schedule', 'perform' ),
			$scheduled
				? __( 'The cache preload event is scheduled.', 'perform' )
				: __( 'The cache preload event is not scheduled yet.', 'perform' ),
			$scheduled
				? __( 'No action needed.', 'perform' )
				: __( 'Save settings again or confirm WordPress cron is running on this site.', 'perform' )
		);
	}

	/**
	 * Check whether page cache is enabled without site-specific exclusions.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, string>
	 */
	private static function diagnose_dynamic_cache_exclusions( array $settings ) {
		if ( empty( $settings['enable_page_cache'] ) ) {
			return self::item(
				'ready',
				__( 'Dynamic request exclusions', 'perform' ),
				__( 'Page cache is disabled, so dynamic request exclusions are not required yet.', 'perform' ),
				__( 'Add exclusions before enabling page cache on sites with member areas, custom checkouts, or preview tokens.', 'perform' )
			);
		}

		$has_exclusions = ! empty( $settings['cache_bypass_exact_paths'] )
			|| ! empty( $settings['cache_bypass_path_prefixes'] )
			|| ! empty( $settings['cache_bypass_query_params'] )
			|| ! empty( $settings['cache_bypass_cookie_names'] )
			|| ! empty( $settings['cache_bypass_cookie_prefixes'] );

		return self::item(
			$has_exclusions ? 'ready' : 'warning',
			__( 'Dynamic request exclusions', 'perform' ),
			$has_exclusions
				? __( 'Site-specific cache bypass rules are configured.', 'perform' )
				: __( 'No site-specific cache bypass rules are configured.', 'perform' ),
			$has_exclusions
				? __( 'Review bypass rules after adding new dynamic workflows.', 'perform' )
				: __( 'Add path, query, or cookie exclusions for dynamic workflows before broad cache rollout.', 'perform' )
		);
	}

	/**
	 * Check whether menu cache is enabled on a block theme without opt-in support.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, string>
	 */
	private static function diagnose_menu_cache_theme_support( array $settings ) {
		if ( empty( $settings['enable_navigation_menu_cache'] ) ) {
			return self::item(
				'ready',
				__( 'Menu cache theme support', 'perform' ),
				__( 'Menu cache is disabled.', 'perform' ),
				__( 'Use menu cache primarily with classic themes that render menus through wp_nav_menu().', 'perform' )
			);
		}

		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
		$supported      = (bool) apply_filters( 'perform_menu_cache_supports_current_theme', ! $is_block_theme, $is_block_theme );

		return self::item(
			$supported ? 'ready' : 'warning',
			__( 'Menu cache theme support', 'perform' ),
			$supported
				? __( 'The active theme is eligible for menu cache.', 'perform' )
				: __( 'The active block theme is not expected to benefit from menu cache by default.', 'perform' ),
			$supported
				? __( 'No action needed.', 'perform' )
				: __( 'Leave menu cache disabled unless custom theme code opts in through the compatibility filter.', 'perform' )
		);
	}

	/**
	 * Check CDN settings required for safe URL rewriting.
	 *
	 * @param array<string, mixed> $settings Settings.
	 *
	 * @return array<string, string>
	 */
	private static function diagnose_cdn_configuration( array $settings ) {
		if ( empty( $settings['enable_cdn'] ) ) {
			return self::item(
				'ready',
				__( 'CDN configuration', 'perform' ),
				__( 'CDN rewriting is disabled.', 'perform' ),
				__( 'Enable CDN rewriting only after confirming the CDN origin URL and included directories.', 'perform' )
			);
		}

		$cdn_url = isset( $settings['cdn_url'] ) && is_scalar( $settings['cdn_url'] ) ? trim( (string) $settings['cdn_url'] ) : '';
		$scheme  = strtolower( (string) wp_parse_url( $cdn_url, PHP_URL_SCHEME ) );
		$host    = (string) wp_parse_url( $cdn_url, PHP_URL_HOST );
		$ready   = '' !== $cdn_url && in_array( $scheme, [ 'http', 'https' ], true ) && '' !== $host;

		return self::item(
			$ready ? 'ready' : 'needs-attention',
			__( 'CDN configuration', 'perform' ),
			$ready
				? __( 'CDN rewriting has a valid CDN URL.', 'perform' )
				: __( 'CDN rewriting is enabled but the CDN URL is missing or invalid.', 'perform' ),
			$ready
				? __( 'Confirm included directories and exclusions before broad rollout.', 'perform' )
				: __( 'Enter a full CDN URL with http or https before enabling CDN rewriting.', 'perform' )
		);
	}

	/**
	 * Build a diagnostic item.
	 *
	 * @param string $status Status.
	 * @param string $label Label.
	 * @param string $message Message.
	 * @param string $action Action.
	 *
	 * @return array<string, string>
	 */
	private static function item( $status, $label, $message, $action ) {
		return [
			'status'  => $status,
			'label'   => $label,
			'message' => $message,
			'action'  => $action,
		];
	}

	/**
	 * Get WordPress content directory.
	 *
	 * @return string
	 */
	private static function get_wp_content_dir() {
		return defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : dirname( ABSPATH ) . '/wp-content';
	}

	/**
	 * Get Perform cache directory.
	 *
	 * @return string
	 */
	private static function get_cache_dir() {
		return trailingslashit( self::get_wp_content_dir() ) . 'cache/perform/';
	}
}
