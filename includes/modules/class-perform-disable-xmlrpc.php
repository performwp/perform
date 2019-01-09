<?php
/**
 * Perform Module - Disable XMLRPC
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Disable_XMLRPC
 *
 * @since 1.0.0
 */
class Perform_Disable_XMLRPC {
	
	/**
	 * Perform_Disable_XMLRPC constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'pings_open', '__return_false', 9999 );
		add_filter( 'wp_headers', array( $this, 'remove_x_pingback' ) );
		
	}
	
	/**
	 * This function is used to remove headers related to X Pingback.
	 *
	 * @param array $headers List of headers.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function remove_x_pingback( $headers ) {
		
		unset( $headers['X-Pingback'], $headers['x-pingback'] );
		
		return $headers;
		
	}
}