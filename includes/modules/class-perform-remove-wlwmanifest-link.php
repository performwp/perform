<?php
/**
 * Perform Module - Remove wlwmanifest Link.
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Remove_WLWManifest_Link
 *
 * @since 1.0.0
 */
class Perform_Remove_WLWManifest_Link {
	
	/**
	 * Perform_Remove_WLWManifest_Link constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		remove_action( 'wp_head', 'wlwmanifest_link' );
		
	}
}