<?php
/**
 * Perform Module - Remove Shortlink
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Remove_Shortlink
 *
 * @since 1.0.0
 */
class Perform_Remove_Shortlink {
	
	/**
	 * Perform_Remove_Shortlink constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		
	}
}