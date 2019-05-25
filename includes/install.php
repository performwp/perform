<?php
/**
 * Install Perform Plugin.
 *
 * @since 1.0.0
 */

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activate Perform
 *
 * Runs on plugin activate to setup basic needs.
 *
 * @since 1.2.1
 *
 * @param bool $network Is network enabled?
 *
 * @global     $wpdb
 * @return void
 */
function perform_install( $network = false ) {

	global $wpdb;

	if ( is_multisite() && $network ) {

		foreach ( $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs LIMIT 100" ) as $blog_id ) {

			switch_to_blog( $blog_id );
			perform_run_install();
			restore_current_blog();

		}

	} else {

		perform_run_install();

	}
}

function perform_run_install() {

	// Add "Upgraded From" version.
	$current_version = get_option( 'perform_version' );
	if ( $current_version ) {
		update_option( 'perform_version_upgraded_from', $current_version, false );
	}

	/**
	 * Run plugin upgrades.
	 *
	 * @since 1.2.1
	 */
	do_action( 'perform_upgrades' );

	if ( PERFORM_VERSION !== get_option( 'perform_version' ) ) {
		update_option( 'perform_version', PERFORM_VERSION, false );
	}
}

