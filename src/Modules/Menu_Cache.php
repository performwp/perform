<?php
/**
 * Perform - Menu Cache.
 *
 * @package    Perform
 * @subpackage Includes
 * @since      1.2.0
 * @author     PerformWP <hello@performwp.com>
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu_Cache
 *
 * @since 1.2.0
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

		// Fetch the navigation menu object of a specific menu.
		$menu = wp_get_nav_menu_object( $args->menu );

		// Fetch all navigation menu locations.
		$locations = get_nav_menu_locations();

		// Fetch the navigation menu object based on theme location.
		if (
			! $menu &&
			$args->theme_location &&
			$locations &&
			isset( $locations[ $args->theme_location ] )
		) {
			$menu = wp_get_nav_menu_object( $locations[ $args->theme_location ] );
		}

		// If unable to find a menu, then fetch the first menu that has items.
		if ( ! $menu && ! $args->theme_location ) {

			$menus = wp_get_nav_menus();

			foreach ( $menus as $maybe_menu ) {

				$menu_items = wp_get_nav_menu_items( $maybe_menu->term_id, [ 'update_post_term_cache' => false ] );

				if ( $menu_items ) {
					$menu = $maybe_menu;
					break;
				}
			}
		}

		if ( empty( $args->menu ) ) {
			$args->menu = $menu;
		}

		global $wp_query;
		$menu_signature = md5( wp_json_encode( $args ) . $wp_query->query_vars_hash );

		// We don’t actually need the references to all the cached versions of this menu,
		// but we need to make sure the cache is not out of sync - transients are unreliable.
		$cached_versions = get_transient( 'perform_menu_cache_menuid_' . $args->menu->term_id );

		if ( false !== $cached_versions ) {

			$cached_output = get_transient( 'perform_menu_cache_' . $menu_signature );

			if ( false !== $cached_output ) {
				$output = $cached_output;
			}
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

		global $wp_query;
		$menu_signature = md5( wp_json_encode( $args ) . $wp_query->query_vars_hash );

		if ( isset( $args->menu->term_id ) ) {

			// Set menu cache.
			set_transient( 'perform_menu_cache_' . $menu_signature, $nav_menu );

			// Store a reference to this version of the menu, so we can purge it when needed.
			$cached_versions = get_transient( 'perform_menu_cache_menuid_' . $args->menu->term_id );

			if ( false === $cached_versions ) {
				$cached_versions = [];
			} else {
				$cached_versions = json_decode( $cached_versions );
			}

			if ( ! in_array( $menu_signature, $cached_versions, true ) ) {
				$cached_versions[] = $menu_signature;
			}

			// Set menu item cache.
			set_transient( 'perform_menu_cache_menuid_' . $args->menu->term_id, wp_json_encode( $cached_versions ), 15552000 );
		}

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

					$cached_versions = json_decode( $cached_versions );

					foreach ( $cached_versions as $menu_signature ) {
						delete_transient( 'perform_menu_cache_' . $menu_signature );
					}

					set_transient( 'perform_menu_cache_menuid_' . $menu->term_id, wp_json_encode( [] ), 15552000 );
				}
			}
		}
	}
}
