<?php
/**
 * Perform - SSL Manager.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Modules
 * @author     PerformWP <hello@performwp.com>
 */

namespace Perform\Modules;

use Perform\Includes\Helpers;

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ssl_Manager {

	/**
	 * Is SSL Enabled?
	 *
	 * @since 1.2.2
	 *
	 * @var string
	 */
	public $is_ssl_enabled;

	/**
	 * Constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @throws Exception
	 *
	 * @return void
	 */
	public function __construct() {
		$this->is_ssl_enabled = Helpers::get_option( 'enable_ssl', 'perform_ssl', false );

		// Proceed, only if site accessed with non-HTTP url.
		if ( ! is_ssl() && $this->is_ssl_enabled ) {
			$this->wp_redirect_to_ssl();
		}
	}

	/**
	 * Auto Redirect users to HTTPS using wp_safe_redirect().
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function wp_redirect_to_ssl() {
		// Prefer the configured site URL host instead of trusting HTTP_HOST.
		$host = parse_url( home_url(), PHP_URL_HOST );

		// Fallback to server host if site URL parsing fails.
		if ( empty( $host ) && ! empty( $_SERVER['HTTP_HOST'] ) ) {
			$host = sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) );
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';

		$redirect_url = set_url_scheme( 'https://' . $host . $uri, 'https' );
		$redirect_url = apply_filters( 'perform_wp_redirect_url_to_ssl', $redirect_url );

		wp_safe_redirect( esc_url_raw( $redirect_url ), 301 );
		exit;
	}
}
