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
		$tabs                    = Helpers::get_settings_tabs();
		$tabs['inventory']       = esc_html__( 'Site Inventory', 'perform' );
		$tabs['database']        = esc_html__( 'Database', 'perform' );
		$tabs['admin-monitor']   = esc_html__( 'Admin Monitor', 'perform' );
		$tabs['admin-assets']    = esc_html__( 'Admin Assets', 'perform' );
		$tabs['scheduled-tasks'] = esc_html__( 'Scheduled Tasks', 'perform' );
		$tabs['cache-stats']     = esc_html__( 'Cache Stats', 'perform' );

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

		// If the JS sent a JSON payload in `data`, decode it. Otherwise fall back to regular POST fields.
		$posted_data = [];
		if ( isset( $_POST['data'] ) ) {
			$raw     = sanitize_textarea_field( wp_unslash( $_POST['data'] ) );
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$posted_data = $decoded;
			} else {
				// Fallback: clean the entire $_POST array.
				$posted_data = Helpers::clean( wp_unslash( $_POST ) );
			}
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

			$field_def = Helpers::find_field_by_id( $key );

			if ( is_array( $val ) ) {
				if ( $field_def && isset( $field_def['type'] ) && 'textarea' === $field_def['type'] ) {
					$raw_val = implode( "\n", array_map( 'strval', $val ) );
				} else {
					// If value is an array, recursively clean it for list-type fields.
					$sanitized_post[ $key ] = Helpers::clean( $val );
					continue;
				}
			} else {
				$raw_val = is_scalar( $val ) ? wp_unslash( $val ) : '';
			}

			// Keep existing secrets when UI sends masked placeholder.
			if ( in_array( $key, ClientPayload::get_sensitive_keys(), true ) && ClientPayload::is_masked_secret( $raw_val ) ) {
				continue;
			}

			if ( $field_def && isset( $field_def['type'] ) ) {
				switch ( $field_def['type'] ) {
					case 'toggle':
						// Normalize truthy values to 1, else 0.
						$sanitized_post[ $key ] = ! empty( $raw_val ) && '0' !== $raw_val ? 1 : 0;
						break;
					case 'textarea':
						$sanitized_post[ $key ] = sanitize_textarea_field( $raw_val );
						break;
					case 'url':
						$sanitized_post[ $key ] = esc_url_raw( $raw_val );
						break;
					case 'select':
						// Ensure value is one of allowed options when provided.
						$opts  = $field_def['options'] ?? [];
						$is_ok = false;
						if ( is_array( $opts ) && ! empty( $opts ) ) {
							// If associative array (value=>label) check keys, otherwise check values.
							$keys = array_keys( $opts );
							$vals = array_values( $opts );
							if ( array_diff_key( $opts, array_values( $opts ) ) ) {
								$is_ok = in_array( $raw_val, $keys, true );
							} else {
								$is_ok = in_array( $raw_val, $vals, true );
							}
						}
						$sanitized_post[ $key ] = $is_ok ? sanitize_text_field( $raw_val ) : '';
						break;
					case 'number':
						$sanitized_post[ $key ] = is_numeric( $raw_val ) ? intval( $raw_val ) : 0;
						break;
					default:
						$sanitized_post[ $key ] = sanitize_text_field( $raw_val );
				}
			} else {
				// No field definition found; fall back to a safe cleaning.
				$sanitized_post[ $key ] = is_scalar( $raw_val ) ? sanitize_text_field( $raw_val ) : Helpers::clean( $raw_val );
			}
		}

		// Merge sanitized values with existing settings to preserve missing keys.
		$new_settings = wp_parse_args( $sanitized_post, is_array( $settings ) ? $settings : [] );

		// Handle newline-separated lists.
		$new_settings['dns_prefetch'] = $this->normalize_multiline_setting( $new_settings['dns_prefetch'] ?? '' );
		$new_settings['preconnect']   = $this->normalize_multiline_setting( $new_settings['preconnect'] ?? '' );

		foreach ( $this->get_cache_bypass_list_setting_keys() as $setting_key ) {
			$new_settings[ $setting_key ] = $this->normalize_rule_list_setting( $new_settings[ $setting_key ] ?? '' );
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
					return sanitize_text_field( wp_unslash( $line ) );
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
					return is_scalar( $item ) ? sanitize_text_field( wp_unslash( $item ) ) : '';
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
