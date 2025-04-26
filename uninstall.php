<?php
/**
 * Plugin Uninstall Handler.
 *
 * @since 1.0.0
 * @package Perform
 * @subpackage Uninstall
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Handle Perform plugin uninstall process.
 *
 * @since 1.0.0
 */
function perform_handle_plugin_uninstall() {

	$setting_types = array(
		'perform_common',
		'perform_ssl',
		'perform_cdn',
		'perform_woocommerce',
		'perform_advanced',
		'perform_import_export',
		'perform_support',
	);

	if ( function_exists( 'perform_get_option' ) ) {
		$remove_data_on_uninstall = perform_get_option( 'remove_data_on_uninstall', 'perform_advanced' );
	} else {
		$remove_data_on_uninstall = true; // Default to true on uninstall if not accessible.
	}

	if ( $remove_data_on_uninstall ) {

		if ( is_multisite() ) {
			$sites = get_sites( array( 'deleted' => 0 ) );

			if ( ! empty( $sites ) ) {
				foreach ( $sites as $site ) {
					foreach ( $setting_types as $option ) {
						delete_blog_option( (int) $site->blog_id, $option );
					}
				}
			}
		} else {
			foreach ( $setting_types as $option ) {
				delete_option( $option );
			}
		}
	}
}

// Directly run the uninstall handler without needing constants.
perform_handle_plugin_uninstall();
