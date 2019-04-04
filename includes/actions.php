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
 * @since 1.0.0
 *
 * @todo Implement this feature in 1.1.0
 */
function perform_enqueue_styles() {
	wp_enqueue_style( 'perform', PERFORM_PLUGIN_URL . 'assets/dist/css/perform.css' );
}
add_action( 'wp_enqueue_scripts', 'perform_enqueue_styles' );
