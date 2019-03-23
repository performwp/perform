<?php
/**
 * Perform Module - Disable Emoji's.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Disable Emoji's
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Disable_Emojis
 *
 * @since 1.0.0
 */
class Perform_Disable_Emojis {

	/**
	 * Perform_Disable_Emojis constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {

		// Remove actions to disable emojis.
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		// Remove filters to disable emojis.
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

		// Add filter to disable emojis.
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_emojis_from_tinymce' ) );
		add_filter( 'wp_resource_hints', array( $this, 'disable_emojis_from_dns_prefetch' ), 10, 2 );
		add_filter( 'emoji_svg_url', '__return_false' );

	}

	/**
	 * This function is used to disable emoji's from TinyMCE.
	 *
	 * @param array $plugins List of plugins.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function disable_emojis_from_tinymce( $plugins ) {

		if ( is_array( $plugins ) ) {
			return array_diff( $plugins, array( 'wpemoji' ) );
		} else {
			return array();
		}
	}

	/**
	 * This function is used to disable SVG Emoji URL from DNS Prefetch.
	 *
	 * @param array  $urls          List of URLs.
	 * @param string $relation_type Relation with the URLs.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function disable_emojis_from_dns_prefetch( $urls, $relation_type ) {

		if ( 'dns-prefetch' === $relation_type ) {
			$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2.2.1/svg/' );
			$urls          = array_diff( $urls, array( $emoji_svg_url ) );
		}

		return $urls;
	}

}
