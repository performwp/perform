<?php
/**
 * Actions
 */

function perform_enqueue_styles() {
	wp_enqueue_style( 'perform', PERFORM_PLUGIN_URL . 'assets/dist/css/perform.css' );
}
add_action( 'wp_enqueue_scripts', 'perform_enqueue_styles' );
