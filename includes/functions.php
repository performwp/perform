<?php
/**
 * Perform Functions
 *
 * @since 1.0.0
 */

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This function will be called on init and will load modules on init based on the settings.
 *
 * @since 1.0.0
 */
function perform_load_modules_on_init() {
	
	/**
	 * Disable Emoji's
	 *
	 * @since 1.0.0
	 */
	$is_emojis_disabled = perform_get_option( 'disable_emojis', 'perform_common' );
	
	if ( $is_emojis_disabled ) {
		
		// Load Module.
		require_once './modules/class.disable-emojis.php';
		
		// Init Module.
		new Perform_Disable_Emojis();
	}
}
add_action( 'init', 'perform_load_modules_on_init' );