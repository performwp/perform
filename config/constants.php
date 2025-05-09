<?php
// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin version in SemVer format.
if ( ! defined( 'PERFORM_VERSION' ) ) {
	define( 'PERFORM_VERSION', '1.4.0' );
}

// Define plugin root File.
if ( ! defined( 'PERFORM_PLUGIN_FILE' ) ) {
	define( 'PERFORM_PLUGIN_FILE', dirname( dirname( __FILE__ ) ) . '/perform.php' );
}

// Define plugin basename.
if ( ! defined( 'PERFORM_PLUGIN_BASENAME' ) ) {
	define( 'PERFORM_PLUGIN_BASENAME', plugin_basename( PERFORM_PLUGIN_FILE ) );
}

// Define plugin directory Path.
if ( ! defined( 'PERFORM_PLUGIN_DIR' ) ) {
	define( 'PERFORM_PLUGIN_DIR', plugin_dir_path( PERFORM_PLUGIN_FILE ) );
}

// Define plugin directory URL.
if ( ! defined( 'PERFORM_PLUGIN_URL' ) ) {
	define( 'PERFORM_PLUGIN_URL', plugin_dir_url( PERFORM_PLUGIN_FILE ) );
}
