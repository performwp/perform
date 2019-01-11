<?php
/**
 * Perform Module - Remove jQuery Migrate JS.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Remove jQuery Migrate.
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Perform_Remove_jQuery_Migrate
 *
 * @since 1.0.0
 */
class Perform_Remove_jQuery_Migrate {
	
	/**
	 * Perform_Remove_jQuery_Migrate constructor.
	 *
	 * @since  1.0.0
	 * @access public
	 */
	public function __construct() {
		
		add_filter( 'wp_default_scripts', array( $this, 'remove_jquery_migrate' ) );
		
	}
	
	/**
	 * This function is used to remove jQuery Migrate JS.
	 *
	 * @param object $scripts List of scripts used.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_jquery_migrate( $scripts ){
		
		if ( is_admin() ) {
			return;
		}
		
		// Remove jQuery first which is loaded with jQuery Migrate JS.
		$scripts->remove( 'jquery' );
		
		// Add jQuery again without loading the jQuery Migrate JS.
		$scripts->add( 'jquery', false, array( 'jquery-core' ), '1.12.4' );
		
	}
	
}