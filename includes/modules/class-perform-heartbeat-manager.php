<?php
/**
 * Perform Module - Heartbeat Manager.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Heartbeat Manager
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Heartbeat_Manager
 *
 * @since 1.0.0
 */
class Perform_Heartbeat_Manager {

	/**
	 * Is Heartbeat Disabled?
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @var bool
	 */
	public $is_disabled = false;

	/**
	 * Perform_Heartbeat_Manager constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {

		$this->is_disabled = perform_get_option( 'disable_heartbeat', 'perform_common' );

		// Disable Hearbeat based on the settings.
		$this->disable_heartbeat();

		// Proceed, only if Heartbeat API is not disabled.
		if ( 'disable_everywhere' !== $this->is_disabled ) {
			add_filter( 'heartbeat_settings', array( $this, 'heartbeat_frequency' ) );
		}
		
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
		
		if ( 'disable_everywhere' === $this->is_disabled ) {
			
			// Disable Heartbeat API everywhere.
			wp_deregister_script('heartbeat');

		} elseif ( 'allow_posts' === $this->is_disabled ) {
			
			// Allow Heartbeat API, only on posts pages in admin.
			global $pagenow;
			
			if ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
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
	public function heartbeat_frequency( $settings ) {
		
		$heartbeat_frequency = perform_get_option( 'heartbeat_frequency', 'perform_common' );
		
		// Set Heartbeat API frequency based on your needs.
		if( ! empty( $heartbeat_frequency ) ) {
			$settings['interval'] = $heartbeat_frequency;
		}
		
		return $settings;
	}
}