<?php
/**
 * Perform Module - Limit Autosave Intervals.
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Limit_Autosave
 *
 * @since 1.0.0
 */
class Perform_Limit_Autosave {
	
	/**
	 * Perform_Limit_Autosave constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		$autosave_limit = perform_get_option( 'autosave_interval', 'perform_common' );
		
		// Define Autosave Limit.
		define('AUTOSAVE_INTERVAL', $autosave_limit );
		
	}
}