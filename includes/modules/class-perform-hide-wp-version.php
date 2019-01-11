<?php
/**
 * Perform Module - Hide WordPress Version.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Hide WordPress Version
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Hide_WP_Version
 *
 * @since 1.0.0
 */
class Perform_Hide_WP_Version {
	
	/**
	 * Perform_Hide_WP_Version constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		// Remove WP Generator using action hook.
		remove_action( 'wp_head', 'wp_generator' );
		
		// Return empty string to the generator using filter hook.
		add_filter( 'the_generator', __return_empty_string() );
		
	}
}