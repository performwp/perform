<?php
/**
 * Perform Module - Disable Self Pingbacks.
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Disable_Self_Pingbacks
 *
 * @since 1.0.0
 */
class Perform_Disable_Self_Pingbacks {
	
	/**
	 * Perform_Disable_Self_Pingbacks constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		add_action( 'pre_ping', array( $this, 'disable_self_pingbacks' ) );
		
	}
	
	/**
	 * This function is used to disable self pingbacks.
	 *
	 * @param array $links List of links.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_self_pingbacks( $links ) {
		
		$home = get_option( 'home' );
		
		foreach( $links as $key => $link ) {
			if ( 0 === strpos( $link, $home ) ) {
				unset( $links[ $key ] );
			}
		}
	}
	
}