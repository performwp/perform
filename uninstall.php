<?php
/**
 * Uninstall.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Uninstall
 * @author     Mehul Gohil
 */

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Process Uninstall.
 *
 * @since 1.0.0
 */
function perform_uninstall() {


	$setting_types = array(
		'perform_common',
		'perform_ssl',
		'perform_cdn',
		'perform_woocommerce',
		'perform_advanced',
		'perform_import_export',
		'perform_support'
	);

	$remove_data_on_uninstall = perform_get_option( 'remove_data_on_uninstall', 'perform_advanced' );
	if ( $remove_data_on_uninstall ) {

		if ( is_multisite() ) {

			// Get sites list.
			$sites = array_map(
				'get_object_vars',
				get_sites( array(
					'deleted' => 0
				) )
			);

			// Loop through each site and delete all the setting types.
			if ( is_array( $sites ) && count( $sites ) > 0 ) {
				foreach ( $sites as $site ) {
					foreach ( $setting_types as $option ) {
						delete_blog_option( $site['blog_id'], $option );
					}
				}
			}
		} else {

			// Loop through the setting types to delete.
			foreach ( $setting_types as $option ) {
				delete_option( $option );
			}
		}
	}


}

register_uninstall_hook( PERFORM_PLUGIN_FILE, 'perform_uninstall' );
