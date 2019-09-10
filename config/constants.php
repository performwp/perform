<?php
/**
 * List of constants.
 *
 * @since 1.2.2
 */

// Exit if access directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

// Plugin version.
if ( ! defined( 'PERFORM_VERSION' ) ) {
	define( 'PERFORM_VERSION', '1.2.1' );
}

// Minimum Required PHP version.
if ( ! defined( 'PERFORM_REQUIRED_PHP_VERSION' ) ) {
	define( 'PERFORM_REQUIRED_PHP_VERSION', '5.6' );
}

// Plugin Root File.
if ( ! defined( 'PERFORM_PLUGIN_FILE' ) ) {
	define( 'PERFORM_PLUGIN_FILE', __FILE__ );
}

// Plugin Folder Path.
if ( ! defined( 'PERFORM_PLUGIN_DIR' ) ) {
	define( 'PERFORM_PLUGIN_DIR', plugin_dir_path( PERFORM_PLUGIN_FILE ) );
}

// Plugin Folder URL.
if ( ! defined( 'PERFORM_PLUGIN_URL' ) ) {
	define( 'PERFORM_PLUGIN_URL', plugin_dir_url( PERFORM_PLUGIN_FILE ) );
}

// Plugin Basename.
if ( ! defined( 'PERFORM_PLUGIN_BASENAME' ) ) {
	define( 'PERFORM_PLUGIN_BASENAME', plugin_basename( PERFORM_PLUGIN_FILE ) );
}
