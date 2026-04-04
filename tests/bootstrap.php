<?php
/**
 * PHPUnit bootstrap for lightweight unit tests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		$store = isset( $GLOBALS['perform_test_options'] ) && is_array( $GLOBALS['perform_test_options'] ) ? $GLOBALS['perform_test_options'] : [];
		return array_key_exists( $name, $store ) ? $store[ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $name, $value, $autoload = null ) {
		if ( ! isset( $GLOBALS['perform_test_options'] ) || ! is_array( $GLOBALS['perform_test_options'] ) ) {
			$GLOBALS['perform_test_options'] = [];
		}
		$GLOBALS['perform_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
