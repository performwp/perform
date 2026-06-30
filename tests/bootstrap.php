<?php
/**
 * PHPUnit bootstrap for lightweight unit tests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'PERFORM_PLUGIN_URL' ) ) {
	define( 'PERFORM_PLUGIN_URL', 'https://example.com/wp-content/plugins/perform/' );
}

if ( ! defined( 'PERFORM_VERSION' ) ) {
	define( 'PERFORM_VERSION', '1.7.0' );
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

		if ( array_key_exists( $name, $GLOBALS['perform_test_options'] ) && $GLOBALS['perform_test_options'][ $name ] === $value ) {
			return false;
		}

		$GLOBALS['perform_test_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $transient ) {
		$store = isset( $GLOBALS['perform_test_transients'] ) && is_array( $GLOBALS['perform_test_transients'] ) ? $GLOBALS['perform_test_transients'] : [];
		return array_key_exists( $transient, $store ) ? $store[ $transient ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $transient, $value, $expiration = 0 ) {
		if ( ! isset( $GLOBALS['perform_test_transients'] ) || ! is_array( $GLOBALS['perform_test_transients'] ) ) {
			$GLOBALS['perform_test_transients'] = [];
		}

		$GLOBALS['perform_test_transients'][ $transient ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $transient ) {
		unset( $GLOBALS['perform_test_transients'][ $transient ] );
		return true;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		if ( ! isset( $GLOBALS['perform_test_actions'] ) || ! is_array( $GLOBALS['perform_test_actions'] ) ) {
			$GLOBALS['perform_test_actions'] = [];
		}

		$GLOBALS['perform_test_actions'][] = [
			'hook'          => $hook_name,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		];

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

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = null, $url = null ) {
		$url  = null === $url ? ( $GLOBALS['perform_test_current_url'] ?? 'https://example.com/' ) : $url;
		$args = is_array( $key ) ? $key : [ $key => $value ];

		$parts = wp_parse_url( $url );
		$query = [];
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		foreach ( $args as $arg_key => $arg_value ) {
			$query[ $arg_key ] = $arg_value;
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host   = $parts['host'] ?? '';
		$path   = $parts['path'] ?? '';
		$result = $scheme . $host . $path;
		$query  = array_filter(
			$query,
			static function ( $arg_value ) {
				return null !== $arg_value && false !== $arg_value;
			}
		);

		return empty( $query ) ? $result : $result . '?' . http_build_query( $query );
	}
}

if ( ! function_exists( 'remove_query_arg' ) ) {
	function remove_query_arg( $key, $url = null ) {
		$url  = null === $url ? ( $GLOBALS['perform_test_current_url'] ?? 'https://example.com/' ) : $url;
		$keys = (array) $key;

		$parts = wp_parse_url( $url );
		$query = [];
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );
		}

		foreach ( $keys as $arg_key ) {
			unset( $query[ $arg_key ] );
		}

		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : '';
		$host   = $parts['host'] ?? '';
		$path   = $parts['path'] ?? '';
		$result = $scheme . $host . $path;

		return empty( $query ) ? $result : $result . '?' . http_build_query( $query );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( $path = '' ) {
		return 'https://example.com/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( $path = '' ) {
		$base = isset( $GLOBALS['perform_test_home_url'] ) ? (string) $GLOBALS['perform_test_home_url'] : 'https://example.com';

		if ( '' === $path ) {
			return $base;
		}

		if ( 0 === strpos( (string) $path, 'http://' ) || 0 === strpos( (string) $path, 'https://' ) ) {
			return (string) $path;
		}

		return rtrim( $base, '/' ) . '/' . ltrim( (string) $path, '/' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return (string) $text;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return (string) $text;
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo esc_attr__( $text, $domain );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		return 'https://example.com/sample-page/';
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $display = true ) {
		if ( $display ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" />';
		}
		return '<input type="hidden" name="' . esc_attr( $name ) . '" />';
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $display = true ) {
		if ( (string) $checked === (string) $current ) {
			if ( $display ) {
				echo 'checked="checked"';
			}
			return 'checked="checked"';
		}
		return '';
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $display = true ) {
		if ( (string) $selected === (string) $current ) {
			if ( $display ) {
				echo 'selected="selected"';
			}
			return 'selected="selected"';
		}
		return '';
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( $args = [], $output = 'names', $operator = 'and' ) {
		return [];
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return 0;
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID() {
		return 0;
	}
}

if ( ! function_exists( 'wp_get_theme' ) ) {
	function wp_get_theme( $stylesheet = null, $theme_root = null ) {
		return new class {
			public function get( $header ) {
				return 'Test Theme';
			}
		};
	}
}

if ( ! function_exists( 'get_plugins' ) ) {
	function get_plugins( $plugin_folder = '' ) {
		return [];
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return (string) $data;
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return (string) $url;
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

if ( ! function_exists( 'is_admin_bar_showing' ) ) {
	function is_admin_bar_showing() {
		return ! empty( $GLOBALS['perform_test_is_admin_bar_showing'] );
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

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		public $code = '';

		/**
		 * Error message.
		 *
		 * @var string
		 */
		public $message = '';

		/**
		 * Constructor.
		 *
		 * @param string $code Error code.
		 * @param string $message Error message.
		 */
		public function __construct( $code = '', $message = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = [] ) {
		if ( ! isset( $GLOBALS['perform_test_remote_get_calls'] ) || ! is_array( $GLOBALS['perform_test_remote_get_calls'] ) ) {
			$GLOBALS['perform_test_remote_get_calls'] = [];
		}

		$GLOBALS['perform_test_remote_get_calls'][] = [
			'url'  => $url,
			'args' => $args,
		];

		$responses = isset( $GLOBALS['perform_test_remote_get_map'] ) && is_array( $GLOBALS['perform_test_remote_get_map'] ) ? $GLOBALS['perform_test_remote_get_map'] : [];
		if ( array_key_exists( $url, $responses ) ) {
			return $responses[ $url ];
		}

		return new WP_Error( 'missing_mock', 'No mocked response registered.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		if ( is_array( $response ) && isset( $response['body'] ) ) {
			return (string) $response['body'];
		}

		return '';
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
