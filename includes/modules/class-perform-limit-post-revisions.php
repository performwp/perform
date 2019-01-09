<?php
/**
 * Perform Module - Limit Post Revisions.
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Limit_Post_Revisions
 *
 * @since 1.0.0
 */
class Perform_Limit_Post_Revisions {
	
	/**
	 * Perform_Limit_Post_Revisions constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		$post_revisions_limit = perform_get_option( 'limit_post_revisions', 'perform_common' );
		
		// Define Post Revisions Limit.
		define('WP_POST_REVISIONS', $post_revisions_limit );
		
	}
}