<?php
/**
 * Perform - Menu Cache.
 *
 * @package    Perform
 * @subpackage Includes
 * @since      1.2.0
 * @author     PerformWP <hello@performwp.com>
 */

namespace Perform\Modules\MenuCache;

use Perform\Includes\Helpers;
use Perform\Modules\ModuleInterface;

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MenuCache
 *
 * Optimized and enhanced for improved performance.
 */
class MenuCache implements ModuleInterface {
	const TRACKED_SIGNATURE_LIMIT = 100;
	const CACHE_TTL               = 15552000;

	/**
	 * Log Menu Start.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @var $log_menu_start
	 */
	public $log_menu_start;

	/**
	 * Log Menu End.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @var $log_menu_end
	 */
	public $log_menu_end;

	/**
	 * Cached Menu Time.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @var $cached_menu_time
	 */
	public $cached_menu_time;

	/**
	 * Uncached Menu Time.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @var $uncached_menu_time
	 */
	public $uncached_menu_time;

	/**
	 * Cached Total Time.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @var $cached_total_time
	 */
	public $cached_total_time;

	/**
	 * Uncached Total Time.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @var $uncached_total_time
	 */
	public $uncached_total_time;

	/**
	 * Determine whether this module should be loaded.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		$settings = Helpers::get_settings();
		if ( empty( $settings['enable_navigation_menu_cache'] ) ) {
			return false;
		}

		return $this->supports_current_theme();
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'pre_wp_nav_menu', [ $this, 'cache_nav_menu_output' ], 10, 2 );
		add_filter( 'wp_nav_menu', [ $this, 'cache_nav_menu' ], 10, 2 );
		add_action( 'wp_update_nav_menu', [ $this, 'update_nav_menu_cache' ], 10, 2 );
	}

	/**
	 * Determine whether menu caching is appropriate for the active theme.
	 *
	 * Block themes render navigation through block output, so wp_nav_menu()
	 * caching is only enabled by default for classic/non-block themes.
	 *
	 * @return bool
	 */
	private function supports_current_theme(): bool {
		$is_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
		$supported      = ! $is_block_theme;

		return (bool) apply_filters( 'perform_menu_cache_supports_current_theme', $supported, $is_block_theme );
	}

	/**
	 * Determine whether the current request can safely use shared menu output.
	 *
	 * @return bool
	 */
	private function is_cacheable_request(): bool {
		$cacheable = ! is_admin() && ! is_user_logged_in();

		return (bool) apply_filters( 'perform_menu_cache_is_cacheable_request', $cacheable );
	}

	/**
	 * This function is used to output the cached navigation menu.
	 *
	 * @param string|null $output Nav menu output to short-circuit with. Default: null.
	 * @param stdClass    $args   An object containing wp_nav_menu() arguments.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @return string|null
	 */
	public function cache_nav_menu_output( $output, $args ) {

		// Validate input arguments.
		if ( ! $this->is_cacheable_request() || empty( $args ) || ! is_object( $args ) ) {
			return $output;
		}

		// Fetch the navigation menu object of a specific menu.
		$menu = ! empty( $args->menu ) ? wp_get_nav_menu_object( $args->menu ) : null;

		// Fetch all navigation menu locations.
		$locations = get_nav_menu_locations();

		// Fetch the navigation menu object based on theme location.
		if ( ! $menu && ! empty( $args->theme_location ) && isset( $locations[ $args->theme_location ] ) ) {
			$menu = wp_get_nav_menu_object( $locations[ $args->theme_location ] );
		}

		// If unable to find a menu, fetch the first menu that has items.
		if ( ! $menu ) {
			$menus = wp_get_nav_menus();
			foreach ( $menus as $maybe_menu ) {
				$menu_items = wp_get_nav_menu_items( $maybe_menu->term_id, [ 'update_post_term_cache' => false ] );
				if ( $menu_items ) {
					$menu = $maybe_menu;
					break;
				}
			}
		}

		if ( empty( $menu ) ) {
			return $output;
		}

		$args->menu = $menu;

		// Generate a stable cache key for output-affecting menu inputs.
		$menu_signature = $this->get_menu_signature( $args );

		// Attempt to retrieve cached menu output.
		$cached_output = get_transient( 'perform_menu_cache_' . $menu_signature );
		if ( false !== $cached_output ) {
			return $cached_output;
		}

		return $output;
	}

	/**
	 * Cache the HTML content output for navigation menus.
	 *
	 * @see wp_nav_menu()
	 *
	 * @param string   $nav_menu The HTML content for the navigation menu.
	 * @param stdClass $args     An object containing wp_nav_menu() arguments.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @return string The HTML content for the navigation menu.
	 */
	public function cache_nav_menu( $nav_menu, $args ) {

		// Validate input arguments.
		if ( ! $this->is_cacheable_request() || empty( $args ) || ! is_object( $args ) || empty( $args->menu->term_id ) ) {
			return $nav_menu;
		}

		// Generate a stable cache key for output-affecting menu inputs.
		$menu_signature = $this->get_menu_signature( $args );

		// Set menu cache with a 6-month expiration.
		set_transient( 'perform_menu_cache_' . $menu_signature, $nav_menu, self::CACHE_TTL );

		// Store a reference to this version of the menu, so we can purge it when needed.
		$reference_key   = 'perform_menu_cache_menuid_' . absint( $args->menu->term_id );
		$cached_versions = $this->get_tracked_signatures( get_transient( $reference_key ) );

		if ( ! in_array( $menu_signature, $cached_versions, true ) ) {
			$cached_versions[] = $menu_signature;
			if ( self::TRACKED_SIGNATURE_LIMIT < count( $cached_versions ) ) {
				$expired_signature = array_shift( $cached_versions );
				if ( is_string( $expired_signature ) ) {
					delete_transient( 'perform_menu_cache_' . $expired_signature );
				}
			}
		}

		// Update the cached versions reference with a 6-month expiration.
		set_transient( $reference_key, wp_json_encode( $cached_versions ), self::CACHE_TTL );

		return $nav_menu;
	}

	/**
	 * Clears and updates the menu cache.
	 *
	 * Fires after a navigation menu has been successfully updated.
	 *
	 * @param int   $menu_id   ID of the updated menu.
	 * @param array $menu_data An array of menu data.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @return void
	 */
	public function update_nav_menu_cache( $menu_id, $menu_data = null ) {

		$menu_id = absint( $menu_id );
		if ( 0 === $menu_id && is_array( $menu_data ) && isset( $menu_data['menu-name'] ) ) {
			$menu    = wp_get_nav_menu_object( $menu_data['menu-name'] );
			$menu_id = isset( $menu->term_id ) ? absint( $menu->term_id ) : 0;
		}

		if ( 0 === $menu_id ) {
			return;
		}

		$reference_key   = 'perform_menu_cache_menuid_' . $menu_id;
		$cached_versions = $this->get_tracked_signatures( get_transient( $reference_key ) );
		foreach ( $cached_versions as $menu_signature ) {
			delete_transient( 'perform_menu_cache_' . $menu_signature );
		}

		set_transient( $reference_key, wp_json_encode( [] ), self::CACHE_TTL );
	}

	/**
	 * Build a stable signature from output-affecting menu inputs.
	 *
	 * Full query state is excluded by default because classic menu output does
	 * not normally vary by page. Sites with query-aware walkers can opt in with
	 * `perform_menu_cache_query_sensitive` or provide a bounded custom segment
	 * through `perform_menu_cache_key_segments`.
	 *
	 * @param object $args wp_nav_menu() arguments.
	 * @return string
	 */
	private function get_menu_signature( $args ) {
		$menu_id = isset( $args->menu->term_id ) ? absint( $args->menu->term_id ) : 0;
		$keys    = [
			'menu'                 => $menu_id,
			'theme_location'       => isset( $args->theme_location ) ? (string) $args->theme_location : '',
			'menu_class'           => isset( $args->menu_class ) ? (string) $args->menu_class : '',
			'menu_id'              => isset( $args->menu_id ) ? (string) $args->menu_id : '',
			'container'            => isset( $args->container ) ? (string) $args->container : '',
			'container_class'      => isset( $args->container_class ) ? (string) $args->container_class : '',
			'container_id'         => isset( $args->container_id ) ? (string) $args->container_id : '',
			'container_aria_label' => isset( $args->container_aria_label ) ? (string) $args->container_aria_label : '',
			'before'               => isset( $args->before ) ? (string) $args->before : '',
			'after'                => isset( $args->after ) ? (string) $args->after : '',
			'link_before'          => isset( $args->link_before ) ? (string) $args->link_before : '',
			'link_after'           => isset( $args->link_after ) ? (string) $args->link_after : '',
			'depth'                => isset( $args->depth ) ? (int) $args->depth : 0,
			'items_wrap'           => isset( $args->items_wrap ) ? (string) $args->items_wrap : '',
			'item_spacing'         => isset( $args->item_spacing ) ? (string) $args->item_spacing : '',
			'fallback_cb'          => $this->normalize_callable( $args->fallback_cb ?? null ),
			'walker'               => is_object( $args->walker ?? null ) ? get_class( $args->walker ) : '',
			'locale'               => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
		];

		$query_sensitive = (bool) apply_filters( 'perform_menu_cache_query_sensitive', false, $args );
		if ( $query_sensitive ) {
			global $wp_query;
			$keys['query'] = is_object( $wp_query ) && isset( $wp_query->query_vars_hash ) ? (string) $wp_query->query_vars_hash : '';
		}

		$segments         = apply_filters( 'perform_menu_cache_key_segments', [], $args );
		$keys['segments'] = $this->normalize_segments( $segments );

		return md5( (string) wp_json_encode( $keys ) );
	}

	/**
	 * Normalize callback identity without serializing object state.
	 *
	 * @param mixed $callback Callback value.
	 * @return string
	 */
	private function normalize_callable( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}
		if ( is_array( $callback ) && 2 === count( $callback ) ) {
			$owner = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return $owner . '::' . (string) $callback[1];
		}
		return is_object( $callback ) ? get_class( $callback ) : '';
	}

	/**
	 * Normalize custom segmentation to a small scalar-only shape.
	 *
	 * @param mixed $segments Filtered key segments.
	 * @return array<string, int|float|string|bool|null>
	 */
	private function normalize_segments( $segments ) {
		if ( ! is_array( $segments ) ) {
			return [];
		}

		$normalized = [];
		foreach ( array_slice( $segments, 0, 20, true ) as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || ! ( is_scalar( $value ) || null === $value ) ) {
				continue;
			}
			$normalized[ $key ] = is_string( $value ) ? substr( $value, 0, 191 ) : $value;
		}

		return $normalized;
	}

	/**
	 * Decode and validate the bounded purge reference list.
	 *
	 * @param mixed $stored Stored transient value.
	 * @return array<int, string>
	 */
	private function get_tracked_signatures( $stored ) {
		$decoded = is_string( $stored ) ? json_decode( $stored, true ) : $stored;
		if ( ! is_array( $decoded ) ) {
			return [];
		}

		$signatures = array_values(
			array_filter(
				$decoded,
				static function ( $signature ) {
					return is_string( $signature ) && 1 === preg_match( '/^[a-f0-9]{32}$/', $signature );
				}
			)
		);

		return array_slice( $signatures, -self::TRACKED_SIGNATURE_LIMIT );
	}
}
