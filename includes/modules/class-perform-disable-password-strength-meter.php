<?php
/**
 * Perform Module - Disable Password Strength Meter
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Disable_Password_Strength_Meter
 *
 * @since 1.0.0
 */
class Perform_Disable_Password_Strength_Meter {
	
	/**
	 * Perform_Disable_Password_Strength_Meter constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		add_action( 'wp_print_scripts', array( $this, 'disable_password_strength_meter' ), 100 );
		
	}
	
	/**
	 * This function is used to disable password strength meter from WordPress as well as WooCommerce.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_password_strength_meter() {
		
		global $wp;
		
		$action   = filter_input( INPUT_GET, 'action' );
		$wp_check = isset( $wp->query_vars['lost-password'] ) || ( ! empty( $action ) && 'lostpassword' === $action ) || is_page( 'lost_password' );
		$wc_check = ( class_exists( 'WooCommerce' ) && ( is_account_page() || is_checkout() ) );
		
		if( ! $wp_check && ! $wc_check ) {
			
			if( wp_script_is( 'zxcvbn-async', 'enqueued' ) ) {
				wp_dequeue_script( 'zxcvbn-async' );
			}
			
			if( wp_script_is( 'password-strength-meter', 'enqueued' ) ) {
				wp_dequeue_script( 'password-strength-meter' );
			}
			
			if( wp_script_is( 'wc-password-strength-meter', 'enqueued' ) ) {
				wp_dequeue_script( 'wc-password-strength-meter' );
			}
		}
	}
	
}