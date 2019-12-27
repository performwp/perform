<?php
/**
 * Miscellaneous Functions
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Miscellaneous Functions.
 * @author     Mehul Gohil
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This function will check whether Perform plugin has network access?
 *
 * @since 1.0.0
 *
 * @return bool
 */
function perform_has_network_access() {

	// Bailout, if not Multi-Site instance.
	if ( ! is_multisite() ) {
		return true;
	}

	$network = get_site_option( 'perform_network' );
	if (
		! is_super_admin() &&
		! empty( $network['access'] ) &&
		'super' === $network['access']
	) {
		return false;
	}
}

/**
 * Get the value of a settings field
 *
 * @param string $option  settings field name.
 * @param string $section the section name this field belongs to.
 * @param string $default default text if it's not found.
 *
 * @since 1.0.0
 *
 * @return string
 */
function perform_get_option( $option, $section, $default = '' ) {

	$options = get_option( $section );

	if ( isset( $options[ $option ] ) ) {
		return $options[ $option ];
	}

	return $default;

}

/**
 * Check if radio(enabled/disabled) and checkbox(on) is active or not.
 *
 * @since 1.0.0
 *
 * @param string $value Value.
 * @param string $compare_with Compare With.
 *
 * @return bool
 */
function perform_is_setting_enabled( $value, $compare_with = null ) {

	if ( ! is_null( $compare_with ) ) {

		if ( is_array( $compare_with ) ) {
			return in_array( $value, $compare_with, true );
		}

		return ( $value === $compare_with );
	}

	return ( in_array( $value, array( 'enabled', 'on', 'yes' ), true ) ? true : false );
}

/**
 * This function will check whether the assets manager is enabled or not.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function perform_is_assets_manager_enabled() {
	return perform_get_option( 'enable_assets_manager', 'perform_advanced', false );
}

/**
 * This function is used to check whether the WooCommerce plugin is active or not.
 *
 * @since 1.0.1
 *
 * @return bool
 */
function perform_is_woocommerce_active() {

	$is_active = false;

	// Return true when WooCommerce plugin is active.
	if ( class_exists( 'WooCommerce' ) ) {
		$is_active = true;
	}

	return $is_active;
}

/**
 * Clean variables using sanitize_text_field. Arrays are cleaned recursively.
 * Non-scalar values are ignored.
 *
 * @since  1.1.0
 *
 * @param  string|array $var
 *
 * @return string|array
 */
function perform_clean( $var ) {
	if ( is_array( $var ) ) {
		return array_map( 'perform_clean', $var );
	} else {
		return is_scalar( $var ) ? sanitize_text_field( wp_unslash( $var ) ) : $var;
	}
}

/**
 * This function will return the content directory name.
 *
 * @since 1.1.0
 *
 * @return string
 */
function perform_get_content_dir_name() {
	return defined( 'WP_CONTENT_FOLDERNAME' ) ? WP_CONTENT_FOLDERNAME : 'wp-content';
}

/**
 * This function is used to check whether assets manager can be displayed or not.
 *
 * @since 1.2.2
 *
 * @return bool
 */
function perform_can_display_assets_manager() {

	if ( isset( $_GET['perform'] ) ) {
		return true;
	}

	return false;
}
