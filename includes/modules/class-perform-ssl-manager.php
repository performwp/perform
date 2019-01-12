<?php
/**
 * Perform Module - SSL Manager.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage SSL Manager
 * @author     Mehul Gohil
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

		// Proceed, only if site accessed with non-HTTP url.
		if ( ! is_ssl() ) {
			$this->wp_redirect_to_ssl();
		}

	}

	/**
	 * Auto Redirect users to HTTPS using wp_safe_redirect()
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

		$is_ssl_enabled = perform_get_option( 'enable_ssl', 'perform_ssl', false );

		if ( ! is_ssl() && $is_ssl_enabled ) {
			$redirect_url = "https://{$server_data['HTTP_HOST']}{$server_data['REQUEST_URI']}";
			$redirect_url = apply_filters( 'perform_wp_redirect_url_to_ssl', $redirect_url );

			wp_safe_redirect( $redirect_url, 301 );
			exit;

		}
	}
}