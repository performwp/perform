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
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-emojis.php';
		
		// Init Module.
		new Perform_Disable_Emojis();
	}

	/**
	 * Disable Embed's
	 *
	 * @since 1.0.0
	 */
	$is_embeds_disabled = perform_get_option( 'disable_embeds', 'perform_common' );

	if ( $is_embeds_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-embeds.php';

		// Init Module.
		new Perform_Disable_Embeds();
	}

	/**
	 * Remove Query Strings.
	 *
	 * @since 1.0.0
	 */
	$is_query_strings_removed = perform_get_option( 'remove_query_strings', 'perform_common' );

	if ( $is_query_strings_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-query-strings.php';

		// Init Module.
		new Perform_Remove_Query_Strings();
	}

	/**
	 * Disable XML-RPC.
	 *
	 * @since 1.0.0
	 */
	$is_xmlrpc_disabled = perform_get_option( 'disable_xmlrpc', 'perform_common' );

	if ( $is_xmlrpc_disabled ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-disable-xmlrpc.php';

		// Init Module.
		new Perform_Disable_XMLRPC();
	}

	/**
	 * Remove jQuery Migrate.
	 *
	 * @since 1.0.0
	 */
	$is_migrate_js_removed = perform_get_option( 'remove_jquery_migrate', 'perform_common' );

	if ( $is_migrate_js_removed ) {

		// Load Module.
		require_once PERFORM_PLUGIN_DIR . 'includes/modules/class-perform-remove-jquery-migrate.php';

		// Init Module.
		new Perform_Remove_jQuery_Migrate();
	}
}

add_action( 'init', 'perform_load_modules_on_init' );