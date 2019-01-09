<?php
/**
 * Perform Module - SSL Manager Module.
 *
 * @since 1.0.0
 *
 * @package Perform
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_SSL_Manager
 *
 * @since 1.0.0
 */
class Perform_SSL_Manager {

	/**
	 * Perform_SSL_Manager constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'wp', array( $this, 'wp_redirect_to_ssl' ), 40, 3 );

	}

	/**
	 * Redirect using wp_redirect()
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wp_redirect_to_ssl() {

		$server_data = filter_input_array( INPUT_SERVER );

		// Bailout, if HTTP Host doesn't exist in server variables.
		if ( ! array_key_exists( 'HTTP_HOST', $server_data ) ) {
			return;
		}

		$is_ssl_enabled = perform_is_setting_enabled( perform_get_option( 'enable_ssl', 'perform_ssl', false ) );

		if ( ! is_ssl() && $is_ssl_enabled ) {

			$redirect_url = "https://{$server_data['HTTP_HOST']}{$server_data['REQUEST_URI']}";
			$redirect_url = apply_filters( 'perform_wp_redirect_url_to_ssl', $redirect_url );

			wp_safe_redirect( $redirect_url, 301 );
			exit;

		}
	}
}