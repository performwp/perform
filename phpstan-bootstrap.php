<?php
/**
 * Static analysis bootstrap.
 *
 * Provides plugin constants and optional integration functions that normally
 * exist only inside a loaded WordPress runtime.
 *
 * @package Perform
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WP_CONTENT_FOLDERNAME' ) ) {
	define( 'WP_CONTENT_FOLDERNAME', 'wp-content' );
}

if ( ! defined( 'PERFORM_VERSION' ) ) {
	define( 'PERFORM_VERSION', '1.7.0' );
}

if ( ! defined( 'PERFORM_PLUGIN_FILE' ) ) {
	define( 'PERFORM_PLUGIN_FILE', __DIR__ . '/perform.php' );
}

if ( ! defined( 'PERFORM_PLUGIN_BASENAME' ) ) {
	define( 'PERFORM_PLUGIN_BASENAME', 'perform/perform.php' );
}

if ( ! defined( 'PERFORM_PLUGIN_DIR' ) ) {
	define( 'PERFORM_PLUGIN_DIR', __DIR__ . '/' );
}

if ( ! defined( 'PERFORM_PLUGIN_URL' ) ) {
	define( 'PERFORM_PLUGIN_URL', 'https://example.com/wp-content/plugins/perform/' );
}

if ( ! defined( 'PERFORM_PLUGIN_DOCS_URL' ) ) {
	define( 'PERFORM_PLUGIN_DOCS_URL', 'https://performwp.com/docs/' );
}

if ( ! function_exists( 'is_woocommerce' ) ) {
	function is_woocommerce(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_cart' ) ) {
	function is_cart(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_checkout' ) ) {
	function is_checkout(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_account_page' ) ) {
	function is_account_page(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_product' ) ) {
	function is_product(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_product_category' ) ) {
	function is_product_category(): bool {
		return false;
	}
}

if ( ! function_exists( 'is_shop' ) ) {
	function is_shop(): bool {
		return false;
	}
}
