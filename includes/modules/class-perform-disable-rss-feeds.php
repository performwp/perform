<?php
/**
 * Perform Module - Disable RSS Feeds
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Disable RSS Feeds
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Disable_RSS_Feeds
 *
 * @since 1.0.0
 */
class Perform_Disable_RSS_Feeds {
	
	/**
	 * Perform_Disable_RSS_Feeds constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		add_action( 'template_redirect', array( $this, 'disable_rss_feeds', 1 ) );
		
	}
	
	/**
	 * This function is used to disable RSS Feeds.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_rss_feeds() {
		
		// Bailout, if not is feed or is 404 page.
		if( ! is_feed() || is_404() ) {
			return;
		}
		
		$feed = filter_input( INPUT_GET, 'feed' );
		
		// Check for "feed" query parameter.
		if( ! empty( $feed ) ) {
			wp_redirect( esc_url_raw( remove_query_arg( 'feed' ) ), 301 );
			exit;
		}
		
		// Unset "wp_query" feed variable.
		if( 'old' !== get_query_var( 'feed' ) ) {
			set_query_var( 'feed', '' );
		}
		
		// Allow Wordpress redirect to the proper URL.
		redirect_canonical();
		
		// Display error message, if redirect fails.
		$error_message = sprintf(
			__( 'No feed available, please visit the <a href="%s">homepage</a>!', 'perform' ),
			esc_url( home_url( '/' ) )
		);
		wp_die( $error_message );
	}
	
}