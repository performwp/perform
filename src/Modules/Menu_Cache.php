<?php
/**
 * Perform - Menu Cache.
 *
 * @package    Perform
 * @subpackage Includes
 * @since      1.2.0
 * @author     PerformWP <hello@performwp.com>
 */

namespace Perform\Modules;

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Cache
 *
 * Optimized and enhanced for improved performance.
 */
class Menu_Cache {

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
	 * Menu_Cache Constructor.
	 *
	 * @since  1.2.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {
		add_filter( 'pre_wp_nav_menu', [ $this, 'cache_nav_menu_output' ], 10, 2 );
		add_filter( 'wp_nav_menu', [ $this, 'cache_nav_menu' ], 10, 2 );
		add_action( 'wp_update_nav_menu', [ $this, 'update_nav_menu_cache' ], 10, 2 );
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
		if ( empty( $args ) || ! is_object( $args ) ) {
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

		// Generate a unique cache key for the menu.
		global $wp_query;
		$menu_signature = md5( wp_json_encode( $args ) . $wp_query->query_vars_hash );

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
		if ( empty( $args ) || ! is_object( $args ) || empty( $args->menu->term_id ) ) {
			return $nav_menu;
		}

		// Generate a unique cache key for the menu.
		global $wp_query;
		$menu_signature = md5( wp_json_encode( $args ) . $wp_query->query_vars_hash );

		// Set menu cache with a 6-month expiration.
		set_transient( 'perform_menu_cache_' . $menu_signature, $nav_menu, 15552000 );

		// Store a reference to this version of the menu, so we can purge it when needed.
		$cached_versions = get_transient( 'perform_menu_cache_menuid_' . $args->menu->term_id );
		if ( false === $cached_versions ) {
			$cached_versions = [];
		} else {
			$cached_versions = json_decode( $cached_versions, true );
		}

		if ( ! in_array( $menu_signature, $cached_versions, true ) ) {
			$cached_versions[] = $menu_signature;
		}

		// Update the cached versions reference with a 6-month expiration.
		set_transient( 'perform_menu_cache_menuid_' . $args->menu->term_id, wp_json_encode( $cached_versions ), 15552000 );

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

		if ( is_array( $menu_data ) && isset( $menu_data['menu-name'] ) ) {

			$menu = wp_get_nav_menu_object( $menu_data['menu-name'] );

			if ( isset( $menu->term_id ) ) {

				// Get all cached versions of this menu and delete them.
				$cached_versions = get_transient( 'perform_menu_cache_menuid_' . $menu->term_id );

				if ( false !== $cached_versions ) {

					$cached_versions = json_decode( $cached_versions, true );

					foreach ( $cached_versions as $menu_signature ) {
						delete_transient( 'perform_menu_cache_' . $menu_signature );
					}

					// Reset the cached versions reference.
					set_transient( 'perform_menu_cache_menuid_' . $menu->term_id, wp_json_encode( [] ), 15552000 );
				}
			}
		}
	}
}
