<?php
/**
 * PHPUnit bootstrap for lightweight unit tests.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/perform-test-content' );
}

if ( ! defined( 'PHP_URL_SCHEME' ) ) {
	define( 'PHP_URL_SCHEME', 0 );
}

if ( ! defined( 'PHP_URL_HOST' ) ) {
	define( 'PHP_URL_HOST', 1 );
}

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( $file ) {
		$basename = basename( (string) $file );
		$slug     = 'perform.php' === $basename ? 'perform' : basename( dirname( (string) $file ) );

		return $slug . '/' . $basename;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( $file ) {
		return rtrim( dirname( (string) $file ), '/\\' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( $file ) {
		$slug = 'perform.php' === basename( (string) $file ) ? 'perform' : basename( dirname( (string) $file ) );

		return 'https://example.com/wp-content/plugins/' . $slug . '/';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default_value = false ) {
		if ( ! isset( $GLOBALS['perform_test_get_option_counts'] ) || ! is_array( $GLOBALS['perform_test_get_option_counts'] ) ) {
			$GLOBALS['perform_test_get_option_counts'] = [];
		}

		$GLOBALS['perform_test_get_option_counts'][ $name ] = isset( $GLOBALS['perform_test_get_option_counts'][ $name ] ) ? $GLOBALS['perform_test_get_option_counts'][ $name ] + 1 : 1;

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

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $name ) {
		if ( ! isset( $GLOBALS['perform_test_options'] ) || ! is_array( $GLOBALS['perform_test_options'] ) ) {
			$GLOBALS['perform_test_options'] = [];
		}

		$exists = array_key_exists( $name, $GLOBALS['perform_test_options'] );
		unset( $GLOBALS['perform_test_options'][ $name ] );

		return $exists;
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

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/' ) . '/';
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

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return add_action( $hook_name, $callback, $priority, $accepted_args );
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

if ( ! function_exists( 'current_filter' ) ) {
	function current_filter() {
		return isset( $GLOBALS['perform_test_current_filter'] ) ? (string) $GLOBALS['perform_test_current_filter'] : '';
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return isset( $GLOBALS['perform_test_queried_object_id'] ) ? (int) $GLOBALS['perform_test_queried_object_id'] : 0;
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid -- WordPress core compatibility shim.
	function get_the_ID() {
		return isset( $GLOBALS['perform_test_the_id'] ) ? (int) $GLOBALS['perform_test_the_id'] : 0;
	}
}

if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page() {
		return ! empty( $GLOBALS['perform_test_is_front_page'] );
	}
}

if ( ! function_exists( 'is_home' ) ) {
	function is_home() {
		return ! empty( $GLOBALS['perform_test_is_home'] );
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	function get_post_type() {
		return isset( $GLOBALS['perform_test_post_type'] ) ? (string) $GLOBALS['perform_test_post_type'] : 'post';
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax() {
		return ! empty( $GLOBALS['perform_test_wp_doing_ajax'] );
	}
}

if ( ! function_exists( 'wp_doing_cron' ) ) {
	function wp_doing_cron() {
		return ! empty( $GLOBALS['perform_test_wp_doing_cron'] );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in() {
		return ! empty( $GLOBALS['perform_test_is_user_logged_in'] );
	}
}

if ( ! function_exists( 'is_preview' ) ) {
	function is_preview() {
		return ! empty( $GLOBALS['perform_test_is_preview'] );
	}
}

if ( ! function_exists( 'is_feed' ) ) {
	function is_feed() {
		return ! empty( $GLOBALS['perform_test_is_feed'] );
	}
}

if ( ! function_exists( 'is_trackback' ) ) {
	function is_trackback() {
		return ! empty( $GLOBALS['perform_test_is_trackback'] );
	}
}

if ( ! function_exists( 'is_robots' ) ) {
	function is_robots() {
		return ! empty( $GLOBALS['perform_test_is_robots'] );
	}
}

if ( ! function_exists( 'is_search' ) ) {
	function is_search() {
		return ! empty( $GLOBALS['perform_test_is_search'] );
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

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook ) {
		$scheduled = isset( $GLOBALS['perform_test_scheduled_events'] ) && is_array( $GLOBALS['perform_test_scheduled_events'] ) ? $GLOBALS['perform_test_scheduled_events'] : [];

		return $scheduled[ $hook ] ?? false;
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

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id() {
		return isset( $GLOBALS['perform_test_blog_id'] ) ? (int) $GLOBALS['perform_test_blog_id'] : 1;
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || mkdir( $target, 0777, true );
	}
}

if ( ! function_exists( 'wp_is_writable' ) ) {
	function wp_is_writable( $path ) {
		return is_writable( $path );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		return is_file( $file ) ? unlink( $file ) : false;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( $url, $args = [] ) {
		$GLOBALS['perform_test_remote_post_calls'][] = [
			'url'  => $url,
			'args' => $args,
		];
		return $GLOBALS['perform_test_remote_post_response'] ?? [ 'response' => [ 'code' => 200 ] ];
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = [] ) {
		$GLOBALS['perform_test_scheduled_single_events'][] = [
			'timestamp' => $timestamp,
			'hook'      => $hook,
			'args'      => $args,
		];
		return true;
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post_id = 0 ) {
		return $GLOBALS['perform_test_permalinks'][ (int) $post_id ] ?? 'https://example.com/post-' . (int) $post_id;
	}
}

if ( ! function_exists( 'get_post_type_archive_link' ) ) {
	function get_post_type_archive_link( $post_type ) {
		return $GLOBALS['perform_test_post_type_archives'][ $post_type ] ?? 'https://example.com/' . $post_type . '/';
	}
}

if ( ! function_exists( 'get_object_taxonomies' ) ) {
	function get_object_taxonomies( $post_type, $output = 'names' ) {
		return $GLOBALS['perform_test_taxonomies'][ $post_type ] ?? [];
	}
}

if ( ! function_exists( 'get_the_terms' ) ) {
	function get_the_terms( $post_id, $taxonomy ) {
		return $GLOBALS['perform_test_post_terms'][ (int) $post_id ][ $taxonomy ] ?? [];
	}
}

if ( ! function_exists( 'get_term_link' ) ) {
	function get_term_link( $term, $taxonomy = '' ) {
		if ( is_object( $term ) && isset( $term->link ) ) {
			return $term->link;
		}
		return 'https://example.com/term-' . (int) $term . '/';
	}
}

if ( ! function_exists( 'get_term' ) ) {
	function get_term( $term_id, $taxonomy = '' ) {
		return $GLOBALS['perform_test_terms'][ (int) $term_id ] ?? false;
	}
}

if ( ! function_exists( 'get_term_by' ) ) {
	function get_term_by( $field, $value, $taxonomy = '' ) {
		return $GLOBALS['perform_test_terms_by_tt_id'][ (int) $value ] ?? false;
	}
}

if ( ! function_exists( 'get_comment' ) ) {
	function get_comment( $comment_id ) {
		return $GLOBALS['perform_test_comments'][ (int) $comment_id ] ?? false;
	}
}

if ( ! function_exists( 'get_objects_in_term' ) ) {
	function get_objects_in_term( $term_ids, $taxonomies, $args = [] ) {
		$term_id = (int) ( is_array( $term_ids ) ? reset( $term_ids ) : $term_ids );
		$ids     = $GLOBALS['perform_test_term_object_ids'][ $term_id ] ?? [];
		return array_slice( $ids, 0, isset( $args['number'] ) ? (int) $args['number'] : count( $ids ) );
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	function wp_is_post_revision( $post_id ) {
		return false;
	}
}

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	function wp_is_post_autosave( $post_id ) {
		return false;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce' ) {
		return '<input type="hidden" name="' . $name . '" value="test-nonce" />';
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
		return ! empty( $GLOBALS['perform_test_nonce_valid'] );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( $location, $status = 302 ) {
		$GLOBALS['perform_test_redirect'] = $location;
		return true;
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '' ) {
		throw new RuntimeException( (string) $message );
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true ) {
		return '';
	}
}

require_once dirname( __DIR__ ) . '/config/constants.php';

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
