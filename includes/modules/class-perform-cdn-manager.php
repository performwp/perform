<?php
/**
 * Perform Module - CDN Manager.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage CDN Manager
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_CDN_Manager
 *
 * @since 1.0.0
 */
class Perform_CDN_Manager {

	/**
	 * Perform_CDN_Manager constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct() {

		add_action( 'template_redirect', array( $this, 'rewrite_with_cdn' ), 1 );

	}

	/**
	 * This function is introduced to act as an output buffer to fetch HTML to replace the URLs.
	 *
	 * @since 1.2.2
	 */
	public function rewrite_with_cdn() {
		ob_start( array( $this, 'rewrite_with_cdn_url' ) );
	}

	/**
	 * This function will act as wrapper to rewrite the HTML with CDN URL.
	 *
	 * @param mixed $html HTML content.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function rewrite_with_cdn_url( $html ) {

		$site_url        = quotemeta( get_option( 'home' ) );
		$url_regex       = '(https?:|)' . substr( $site_url, strpos( $site_url, '//' ) );
		$directories     = 'wp\-content|wp\-includes';
		$cdn_directories = perform_get_option( 'cdn_directories', 'perform_cdn' );

		if ( ! empty( $cdn_directories ) ) {
			$directory_list = array_map( 'trim', explode( ',', $cdn_directories ) );
			if ( count( $directory_list ) > 0 ) {
				$directories = implode( '|', array_map( 'quotemeta', array_filter( $directory_list ) ) );
			}
		}

		$regex         = '#(?<=[(\"\'])(?:' . $url_regex . ')?/(?:((?:' . $directories . ')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
		$html_with_cdn = preg_replace_callback( $regex, array( $this, 'rewrited_cdn_url' ), $html );

		return $html_with_cdn;

	}

	/**
	 * This function will rewrite the CDN URL to the HTML based on the settings.
	 *
	 * @param string $url URL to replace with CDN URL.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function rewrited_cdn_url( $url ) {

		$cdn_url = perform_get_option( 'cdn_url', 'perform_cdn' );

		if ( ! empty( $cdn_url ) ) {

			$cdn_exclusions = perform_get_option( 'cdn_exclusions', 'perform_cdn' );

			// Don't Rewrite URL, if Excluded.
			if ( ! empty( $cdn_exclusions ) ) {

				$exclusions = array_map( 'trim', explode( ',', $cdn_exclusions ) );

				foreach ( $exclusions as $exclusion ) {
					if ( ! empty( $exclusion ) && false !== stristr( $url[0], $exclusion ) ) {
						return $url[0];
					}
				}
			}

			// Don't Rewrite if Previewing.
			if ( is_admin_bar_showing() && isset( $_GET['preview'] ) && $_GET['preview'] === 'true') {
				return $url[0];
			}

			$site_url = get_option( 'home' );
			$site_url = substr( $site_url, strpos( $site_url, '//' ) );

			// Replace URL w/ No HTTP/S Prefix.
			if ( strpos( $url[0], '//' ) === 0 ) {
				return str_replace( $site_url, $cdn_url, $url[0] );
			}

			// Found Site URL, Replace Non Relative URL w/ HTTP/S Prefix.
			if ( strstr( $url[0], $site_url ) ) {
				return str_replace( array( 'http:' . $site_url, 'https:' . $site_url ), $cdn_url, $url[0] );
			}

			// Replace Relative URL.
			return $cdn_url . $url[0];
		}

		return $url[0];
	}

}
