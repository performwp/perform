<?php
/**
 * PHPUnit bootstrap for lightweight unit tests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default_value = false ) {
		$store = isset( $GLOBALS['perform_test_options'] ) && is_array( $GLOBALS['perform_test_options'] ) ? $GLOBALS['perform_test_options'] : [];
		return array_key_exists( $name, $store ) ? $store[ $name ] : $default_value;
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

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( $handle, $src = '', $deps = [], $ver = false, $media = 'all' ) {
		$GLOBALS['perform_test_enqueued_styles'][] = $handle;
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( $handle, $src = '', $deps = [], $ver = false, $in_footer = false ) {
		$GLOBALS['perform_test_enqueued_scripts'][] = $handle;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		$capabilities = isset( $GLOBALS['perform_test_current_user_can'] ) && is_array( $GLOBALS['perform_test_current_user_can'] ) ? $GLOBALS['perform_test_current_user_can'] : [];
		return ! empty( $capabilities[ $capability ] );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin() {
		return ! empty( $GLOBALS['perform_test_is_admin'] );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return ! empty( $GLOBALS['perform_test_is_user_logged_in'] );
	}
}

if ( ! function_exists( 'wp_is_block_theme' ) ) {
	function wp_is_block_theme() {
		return ! empty( $GLOBALS['perform_test_is_block_theme'] );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook_name, $value, ...$args ) {
		$filters = isset( $GLOBALS['perform_test_filters'] ) && is_array( $GLOBALS['perform_test_filters'] ) ? $GLOBALS['perform_test_filters'] : [];
		if ( ! array_key_exists( $hook_name, $filters ) ) {
			return $value;
		}

		if ( is_callable( $filters[ $hook_name ] ) ) {
			return $filters[ $hook_name ]( $value, ...$args );
		}

		return $filters[ $hook_name ];
	}
}

if ( ! function_exists( 'wp_rand' ) ) {
	function wp_rand( $min = 0, $max = 0 ) {
		if ( isset( $GLOBALS['perform_test_wp_rand'] ) ) {
			return (int) $GLOBALS['perform_test_wp_rand'];
		}

		return (int) $min;
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

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ) {
		return is_scalar( $value ) ? trim( (string) $value ) : $value;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return $value;
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
