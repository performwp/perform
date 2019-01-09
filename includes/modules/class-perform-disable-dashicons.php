<?php
/**
 * Perform Module - Disable Dashicons JS
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Disable_Dashicons
 *
 * @since 1.0.0
 */
class Perform_Disable_Dashicons {
	
	/**
	 * Perform_Disable_Dashicons constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		add_action( 'wp_enqueue_scripts', array( $this, 'disable_dashicons' ) );
		
	}
	
	/**
	 * This function is used to disable dashicons JS when user is not logged in.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_dashicons() {
		
		if ( is_user_logged_in() ) {
			return;
		}
		
		// Dequeue Dashicons Stylesheet.
		wp_dequeue_style( 'dashicons' );
		
		// Deregister Dashicons Stylesheet.
		wp_deregister_style( 'dashicons' );
	}
	
}