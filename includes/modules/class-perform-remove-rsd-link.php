<?php
/**
 * Perform Module - Remove RSD Link.
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Remove_RSD_Link
 *
 * @since 1.0.0
 */
class Perform_Remove_RSD_Link {
	
	/**
	 * Perform_Remove_RSD_Link constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		remove_action('wp_head', 'rsd_link');
		
	}
}