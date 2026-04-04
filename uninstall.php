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
		'perform_settings',
		'perform_common',
		'perform_ssl',
		'perform_cdn',
		'perform_woocommerce',
		'perform_advanced',
		'perform_import_export',
		'perform_support',
	);

	$remove_data_on_uninstall = false;

	$current_settings = get_option( 'perform_settings' );
	if ( is_array( $current_settings ) && isset( $current_settings['remove_data_on_uninstall'] ) ) {
		$remove_data_on_uninstall = ! empty( $current_settings['remove_data_on_uninstall'] );
	} elseif ( function_exists( 'perform_get_option' ) ) {
		$remove_data_on_uninstall = (bool) perform_get_option( 'remove_data_on_uninstall', 'perform_advanced' );
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
			delete_option( 'perform_assets_manager_options' );
			delete_option( 'perform_cache_preload_queue' );
			delete_option( 'perform_cache_stats' );
		}
	}
}

// Directly run the uninstall handler without needing constants.
perform_handle_plugin_uninstall();
