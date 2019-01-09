<?php
/**
 * Perform Module - Heartbeat API Module.
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Heartbeat_Module
 *
 * @since 1.0.0
 */
class Perform_Heartbeat_Module {
	
	/**
	 * Perform_Heartbeat_Module constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		add_action( 'init', array( $this, 'disable_heartbeat' ) );
		
	}
	
	/**
	 * This function is used to disable heartbeat API.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_heartbeat() {
		
		$disable_heartbeat = perform_get_option( 'disable_heartbeat', 'perform_common' );
		
		if( 'disable_everywhere' === $disable_heartbeat ) {
			
			// Disable Heartbeat API everywhere.
			wp_deregister_script('heartbeat');
		} elseif ( 'allow_posts' === $disable_heartbeat ) {
			
			// Allow Heartbeat API, only on posts pages in admin.
			global $pagenow;
			
			if( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
				wp_deregister_script('heartbeat');
			}
		}
	}
	
	/**
	 * This function is used to set the heartbeat API frequency.
	 *
	 * @param array $settings List of settings.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function heartbeat_frequency($settings) {
		
		$heartbeat_frequency = perform_get_option( 'heartbeat_frequency', 'perform_common' );
		
		// Set Heartbeat API frequency based on your needs.
		if( ! empty( $heartbeat_frequency ) ) {
			$settings['interval'] = $heartbeat_frequency;
		}
		
		return $settings;
	}
}