<?php
/**
 * Perform Module - Remove REST API Links.
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Remove_Rest_API_Links
 *
 * @since 1.0.0
 */
class Perform_Remove_Rest_API_Links {
	
	/**
	 * Perform_Remove_Rest_API_Links constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		// Remove REST API Links using required action hooks.
		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		
	}
	
}