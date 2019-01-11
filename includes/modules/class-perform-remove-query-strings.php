<?php
/**
 * Perform Module - Remove Query Strings
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Remove Query Strings
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Remove_Query_Strings
 *
 * @since 1.0.0
 */
class Perform_Remove_Query_Strings {
	
	/**
	 * Perform_Remove_Query_Strings constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		// Remove Query Strings only if the scripts and styles are loaded in frontend.
		if( ! is_admin() ) {
			add_filter( 'script_loader_src', array( $this, 'remove_query_strings' ), 15 );
			add_filter( 'style_loader_src', array( $this, 'remove_query_strings' ), 15 );
		}
		
	}
	
	/**
	 * This function is used to split the url based on the text "ver" to remove the query strings.
	 *
	 * @param string $url URL.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function remove_query_strings( $url ){
		
		$output = preg_split( "/(&ver|\?ver)/", $url );
		
		return $output[0];
	}
	
}