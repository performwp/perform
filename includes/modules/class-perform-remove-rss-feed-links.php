<?php
/**
 * Perform Module - Remove RSS Feed Links
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Remove_RSS_Feed_Links
 *
 * @since 1.0.0
 */
class Perform_Remove_RSS_Feed_Links {
	
	/**
	 * Perform_Remove_RSS_Feed_Links constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		
	}
	
}