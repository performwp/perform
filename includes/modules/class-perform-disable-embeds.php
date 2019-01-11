<?php
/**
 * Perform Module - Disable Embeds.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Disable Embeds Module
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Disable_Embeds
 *
 * @since 1.0.0
 */
class Perform_Disable_Embeds {
	
	/**
	 * Perform_Disable_Embeds constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		global $wp;
		$wp->public_query_vars = array_diff( $wp->public_query_vars, array( 'embed' ) );
		
		// Remove filters to disable embeds.
		remove_action( 'rest_api_init', 'wp_oembed_register_route' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		
		// Remove Filters to disable embeds.
		remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 );
		remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );
		
		// Add filters to disable embeds.
		add_filter( 'embed_oembed_discover', '__return_false' );
		add_filter( 'tiny_mce_plugins', array( $this, 'disable_embeds_from_tinymce' ) );
		add_filter( 'rewrite_rules_array', array( $this, 'disable_embeds_from_rewrites' ) );
		
	}
	
	/**
	 * This function will disable embeds from tinymce.
	 *
	 * @param array $plugins List of tinymce plugins.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function disable_embeds_from_tinymce( $plugins ) {
		return array_diff( $plugins, array( 'wpembed' ) );
	}
	
	/**
	 * This function will remove embeds from rewrites.
	 *
	 * @param array $rules List of rewrite rules.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function disable_embeds_from_rewrites( $rules ) {
		
		foreach( $rules as $rule => $rewrite ) {
			if ( false !== strpos( $rewrite, 'embed=true' ) ) {
				unset( $rules[$rule] );
			}
		}
		
		return $rules;
	}
	
}