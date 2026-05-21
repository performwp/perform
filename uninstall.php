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

	$option_keys = array(
		'perform_settings',
		'perform_common',
		'perform_ssl',
		'perform_cdn',
		'perform_woocommerce',
		'perform_advanced',
		'perform_import_export',
		'perform_support',
		'perform_assets_manager_options',
		'perform_cache_preload_queue',
		'perform_cache_stats',
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
					foreach ( $option_keys as $option ) {
						delete_blog_option( (int) $site->blog_id, $option );
					}
				}
			}
		} else {
			foreach ( $option_keys as $option ) {
				delete_option( $option );
			}
		}
	}
}

// Directly run the uninstall handler without needing constants.
perform_handle_plugin_uninstall();
