<?php
/**
 * Perform - Frontend Actions
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Frontend Actions
 * @author     Mehul Gohil
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue Styles
 *
 * @since 1.1.0
 *
 * @return void
 */
function perform_enqueue_styles() {
	wp_enqueue_style( 'perform', PERFORM_PLUGIN_URL . 'assets/dist/css/perform.css' );
}
add_action( 'wp_enqueue_scripts', 'perform_enqueue_styles' );

/**
 * Enqueue Scripts
 *
 * @since 1.1.0
 *
 * @return void
 */
function perform_enqueue_scripts() {
	wp_enqueue_script( 'perform', PERFORM_PLUGIN_URL . 'assets/dist/js/perform.js', '', PERFORM_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'perform_enqueue_scripts' );
