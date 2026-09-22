<?php
/**
 * Perform - Admin Menu
 * Optimized and enhanced for better performance and maintainability.
 */

namespace Perform\Admin\Settings;

use Perform\Admin\Settings\ClientPayload;
use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu {
	/**
	 * Maximum accepted JSON payload size for a settings save.
	 *
	 * @var int
	 */
	private const MAX_SETTINGS_PAYLOAD_BYTES = 262144;

	/**
	 * Maximum number of entries stored in a multiline setting.
	 *
	 * @var int
	 */
	private const MAX_LIST_ITEMS = 100;

	/**
	 * Maximum length of a single list entry.
	 *
	 * @var int
	 */
	private const MAX_LIST_ITEM_LENGTH = 2048;

	/**
	 * Maximum length of a scalar setting value.
	 *
	 * @var int
	 */
	private const MAX_SCALAR_LENGTH = 2048;

	/**
	 * Maximum length of a textarea setting value before normalization.
	 *
	 * @var int
	 */
	private const MAX_TEXTAREA_LENGTH = 16384;

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 9 );
		add_action( 'wp_ajax_perform_save_settings', [ $this, 'save_settings' ] );
	}

	/**
	 * Register Admin Menu
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_options_page(
			esc_html__( 'Perform', 'perform' ),
			esc_html__( 'Perform', 'perform' ),
			'manage_options',
			'perform_settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Get tabs shown on the canonical settings screen.
	 *
	 * @return array<string, string>
	 */
	public static function get_navigation_tabs() {
		$tabs                = Helpers::get_settings_tabs();
		$tabs['cache-stats'] = esc_html__( 'Cache Stats', 'perform' );

		return $tabs;
	}

	/**
	 * Resolve the requested settings tab to a known tab slug.
	 *
	 * @return string
	 */
	public static function get_requested_tab() {
		$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'dashboard'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation state.
		$valid_tabs    = array_merge( [ 'dashboard' ], array_keys( self::get_navigation_tabs() ) );

		return in_array( $requested_tab, $valid_tabs, true ) ? $requested_tab : 'dashboard';
	}

	/**
	 * Render Settings Page.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_settings_page() {
		$screen = get_current_screen();

		// Only render on the Perform settings page.
		if ( 'settings_page_perform_settings' !== $screen->id ) {
			return;
		}
		?>
		<div id="perform-settings-page" class="perform-settings-page"></div>
		<template id="perform-cache-stats-template">
			<?php do_action( 'perform_settings_cache_stats_content' ); ?>
		</template>
		<?php
	}

	/**
	 * Save Admin Settings.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function save_settings() {
		// Capability check: ensure the current user can manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'type'    => 'error',
					'message' => esc_html__( 'Insufficient permissions.', 'perform' ),
				]
			);
		}

		// Verify nonce for the AJAX request.
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'perform_save_settings' ) ) {
			wp_send_json_error(
				[
					'type'    => 'error',
					'message' => esc_html__( 'Security check failed.', 'perform' ),
				]
			);
		}

		// If the JS sent a JSON payload in `data`, require a valid object payload.
		$posted_data = [];
		if ( isset( $_POST['data'] ) ) {
			$raw = wp_unslash( $_POST['data'] );
			if ( ! is_string( $raw ) || strlen( $raw ) > self::MAX_SETTINGS_PAYLOAD_BYTES ) {
				$this->send_invalid_payload_error();
			}

			$decoded = json_decode( $raw, true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || array_is_list( $decoded ) ) {
				$this->send_invalid_payload_error();
			}

			$posted_data = $decoded;
		} else {
			$posted_data = Helpers::clean( wp_unslash( $_POST ) );
		}
		$settings = Helpers::get_settings();

		// Per-field sanitization based on field definitions provided by Helpers::get_settings_fields().
		$sanitized_post = [];
		foreach ( $posted_data as $key => $val ) {
			// Skip known control keys early.
			if ( in_array( $key, [ 'perform_settings_barrier', '_wp_http_referer', 'action', 'nonce', 'data' ], true ) ) {
				continue;
			}

			if ( ! is_string( $key ) ) {
				continue;
			}

			$field_def = Helpers::find_field_by_id( $key );
			if ( ! is_array( $field_def ) || empty( $field_def['type'] ) ) {
				// Only declared fields may be persisted through this endpoint.
				continue;
			}

			if ( is_array( $val ) ) {
				if ( 'textarea' !== $field_def['type'] || ! $this->is_scalar_list( $val ) ) {
					$this->send_invalid_payload_error();
				}

				$raw_val = implode( "\n", array_map( 'strval', $val ) );
			} else {
				if ( ! is_scalar( $val ) ) {
					continue;
				}

				$raw_val = wp_unslash( $val );
			}

			// Keep existing secrets when UI sends masked placeholder.
			if ( in_array( $key, ClientPayload::get_sensitive_keys(), true ) && ClientPayload::is_masked_secret( $raw_val ) ) {
				continue;
			}

			if ( 'textarea' === $field_def['type'] && $this->is_list_setting_key( $key ) && $this->get_list_item_count( $raw_val, $key ) > self::MAX_LIST_ITEMS ) {
				if ( $this->is_unchanged_client_list_value( $key, $raw_val, $settings ) ) {
					continue;
				}

				$this->send_list_limit_error();
			}

			switch ( $field_def['type'] ) {
				case 'toggle':
					// Normalize truthy values to 1, else 0.
					$sanitized_post[ $key ] = ! empty( $raw_val ) && '0' !== $raw_val ? 1 : 0;
					break;
				case 'textarea':
					$sanitized_post[ $key ] = sanitize_textarea_field( substr( (string) $raw_val, 0, self::MAX_TEXTAREA_LENGTH ) );
					break;
				case 'url':
					$sanitized_post[ $key ] = esc_url_raw( substr( (string) $raw_val, 0, self::MAX_SCALAR_LENGTH ) );
					break;
				case 'select':
					if ( ! $this->is_valid_select_value( $raw_val, $field_def ) ) {
						$this->send_invalid_payload_error();
					}

					$sanitized_post[ $key ] = sanitize_text_field( substr( (string) $raw_val, 0, self::MAX_SCALAR_LENGTH ) );
					break;
				case 'number':
					$sanitized_post[ $key ] = is_numeric( $raw_val ) ? intval( $raw_val ) : 0;
					break;
				default:
					$sanitized_post[ $key ] = sanitize_text_field( substr( (string) $raw_val, 0, self::MAX_SCALAR_LENGTH ) );
			}
		}

		// Merge sanitized values with existing settings to preserve missing keys.
		$new_settings = wp_parse_args( $sanitized_post, is_array( $settings ) ? $settings : [] );

		// Normalize list values only when this request submitted them. Existing values
		// remain untouched when administrators save an unrelated setting.
		foreach ( array_keys( $sanitized_post ) as $setting_key ) {
			if ( in_array( $setting_key, [ 'dns_prefetch', 'preconnect' ], true ) ) {
				$new_settings[ $setting_key ] = $this->normalize_multiline_setting( $sanitized_post[ $setting_key ] );
			} elseif ( in_array( $setting_key, $this->get_cache_bypass_list_setting_keys(), true ) ) {
				$new_settings[ $setting_key ] = $this->normalize_rule_list_setting( $sanitized_post[ $setting_key ] );
			}
		}

		$is_saved = update_option( 'perform_settings', $new_settings, false );
		if ( ! $is_saved && get_option( 'perform_settings' ) === $new_settings ) {
			$is_saved = true;
		}

		if ( $is_saved ) {
			wp_send_json_success(
				[
					'type'        => 'success',
					'message'     => esc_html__( 'Settings saved successfully.', 'perform' ),
					'diagnostics' => RuntimeDiagnostics::get_results(),
				]
			);
		} else {
			wp_send_json_error(
				[
					'type'    => 'error',
					'message' => esc_html__( 'Unable to save the settings. Please try again.', 'perform' ),
				]
			);
		}
	}

	/**
	 * Send a generic error for malformed settings data without reflecting input.
	 *
	 * @return void
	 */
	private function send_invalid_payload_error() {
		wp_send_json_error(
			[
				'type'    => 'error',
				'message' => esc_html__( 'Settings data is invalid. Please try again.', 'perform' ),
			]
		);
	}

	/**
	 * Send a generic, actionable error when a submitted list exceeds its limit.
	 *
	 * @return void
	 */
	private function send_list_limit_error() {
		wp_send_json_error(
			[
				'type'    => 'error',
				'message' => esc_html__( 'List settings are limited to 100 entries. Please reduce the list and try again.', 'perform' ),
			]
		);
	}

	/**
	 * Determine whether a submitted list contains only scalar entries.
	 *
	 * @param array<mixed> $values Submitted values.
	 *
	 * @return bool
	 */
	private function is_scalar_list( array $values ) {
		foreach ( $values as $value ) {
			if ( ! is_scalar( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine whether a submitted field stores a normalized list.
	 *
	 * @param string $key Settings field identifier.
	 *
	 * @return bool
	 */
	private function is_list_setting_key( $key ) {
		return in_array( $key, [ 'dns_prefetch', 'preconnect' ], true ) || in_array( $key, $this->get_cache_bypass_list_setting_keys(), true );
	}

	/**
	 * Count list entries before normalization so oversized input is rejected.
	 *
	 * @param mixed  $value Submitted list value.
	 * @param string $key   Settings field identifier.
	 *
	 * @return int
	 */
	private function get_list_item_count( $value, $key ) {
		if ( ! is_scalar( $value ) || '' === (string) $value ) {
			return 0;
		}

		$pattern = in_array( $key, $this->get_cache_bypass_list_setting_keys(), true ) ? '/[\r\n,]+/' : '/\r\n|\r|\n/';
		$items   = preg_split( $pattern, (string) $value );

		return is_array( $items ) ? count( $items ) : 0;
	}

	/**
	 * Determine whether an oversized list is the unchanged value sent by the UI.
	 *
	 * Legacy installs can contain oversized lists. The React client sends every
	 * registered field, so preserve an exact client payload match without
	 * rewriting its stored array shape during an unrelated save.
	 *
	 * @param string               $key      Settings field identifier.
	 * @param mixed                $value    Submitted value.
	 * @param array<string, mixed> $settings Stored settings.
	 *
	 * @return bool
	 */
	private function is_unchanged_client_list_value( $key, $value, array $settings ) {
		if ( ! is_scalar( $value ) || ! array_key_exists( $key, $settings ) ) {
			return false;
		}

		$client_settings = ClientPayload::sanitize_for_client( $settings );
		if ( ! array_key_exists( $key, $client_settings ) || ! is_scalar( $client_settings[ $key ] ) ) {
			return false;
		}

		return (string) $value === (string) $client_settings[ $key ];
	}

	/**
	 * Determine whether a submitted select value is one of its declared values.
	 *
	 * @param mixed               $value     Submitted value.
	 * @param array<string, mixed> $field_def Settings field definition.
	 *
	 * @return bool
	 */
	private function is_valid_select_value( $value, array $field_def ) {
		$options = $field_def['options'] ?? [];
		if ( ! is_array( $options ) || empty( $options ) || ! is_scalar( $value ) ) {
			return false;
		}

		$allowed_values = array_is_list( $options ) ? $options : array_keys( $options );
		$allowed_values = array_map( 'strval', $allowed_values );

		return in_array( (string) $value, $allowed_values, true );
	}

	/**
	 * Normalize a textarea setting to the stored newline-list shape.
	 *
	 * @param mixed $value Textarea value.
	 *
	 * @return array<int, string>|string
	 */
	private function normalize_multiline_setting( $value ) {
		if ( is_array( $value ) ) {
			$lines = $value;
		} elseif ( is_scalar( $value ) && '' !== (string) $value ) {
			$lines = preg_split( '/\r\n|\r|\n/', (string) $value );
		} else {
			return '';
		}

		$lines = array_filter(
			array_map(
				static function ( $line ) {
					return is_scalar( $line ) ? sanitize_text_field( substr( (string) wp_unslash( $line ), 0, self::MAX_LIST_ITEM_LENGTH ) ) : '';
				},
				$lines
			),
			static function ( $line ) {
				return '' !== $line;
			}
		);

		return array_values( $lines );
	}

	/**
	 * Normalize a cache exclusion list setting to clean unique tokens.
	 *
	 * @param mixed $value List value.
	 *
	 * @return array<int, string>
	 */
	private function normalize_rule_list_setting( $value ) {
		if ( is_array( $value ) ) {
			$items = $value;
		} elseif ( is_scalar( $value ) && '' !== (string) $value ) {
			$items = preg_split( '/[\r\n,]+/', (string) $value );
		} else {
			return [];
		}

		$items = array_filter(
			array_map(
				static function ( $item ) {
					return is_scalar( $item ) ? sanitize_text_field( substr( (string) wp_unslash( $item ), 0, self::MAX_LIST_ITEM_LENGTH ) ) : '';
				},
				$items
			),
			static function ( $item ) {
				return '' !== trim( (string) $item );
			}
		);

		$items = array_map(
			static function ( $item ) {
				return trim( (string) $item );
			},
			$items
		);

		return array_values( array_unique( $items ) );
	}

	/**
	 * Get cache bypass setting keys that store newline/comma lists.
	 *
	 * @return array<int, string>
	 */
	private function get_cache_bypass_list_setting_keys() {
		return [
			'cache_bypass_exact_paths',
			'cache_bypass_path_prefixes',
			'cache_bypass_query_params',
			'cache_bypass_cookie_names',
			'cache_bypass_cookie_prefixes',
		];
	}
}
