<?php
/**
 * Perform - Admin Menu
 * Optimized and enhanced for better performance and maintainability.
 */

namespace Perform\Admin\Settings;

use Perform\Admin\Settings\Api;
use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Menu extends Api {
	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->prefix = 'perform_';

		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 9 );
		add_action( 'wp_ajax_perform_save_settings', [ $this, 'save_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
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
		<?php
	}

	/**
	 * Enqueue admin assets and localize settings data for React app.
	 */
	public function enqueue_admin_assets() {
		$screen = get_current_screen();
		if ( 'settings_page_perform_settings' !== $screen->id ) {
			return;
		}

		// Enqueue your React app script here if not already done.
		wp_enqueue_script(
			'perform-admin-settings',
			PERFORM_PLUGIN_URL . 'assets/dist/js/admin-settings.js',
			[ 'wp-element', 'wp-components', 'wp-i18n' ],
			PERFORM_VERSION,
			true
		);

		// Localize settings data, including fields.
		wp_localize_script(
			'perform-admin-settings',
			'performwpSettings',
			[
				'version' => PERFORM_VERSION,
				'docsUrl' => 'https://performwp.com/docs/',
				'logoUrl' => PERFORM_PLUGIN_URL . 'assets/dist/images/logo.png',
				'nonce'   => wp_create_nonce( 'perform_save_settings' ),
				// Expose currently saved settings so the React app can initialize from persisted values
				'saved'   => Helpers::get_settings(),
				'tabs'    => Helpers::get_settings_tabs(),
				'fields'  => Helpers::get_settings_fields(), // Expose fields here
			]
		);
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
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['nonce'] ), 'perform_save_settings' ) ) {
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
			$raw = wp_unslash( $_POST['data'] );
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				// Clean decoded values recursively
				$posted_data = Helpers::clean( $decoded );
			} else {
				// Fallback: clean the entire $_POST array
				$posted_data = Helpers::clean( $_POST );
			}
		} else {
			$posted_data = Helpers::clean( $_POST );
		}
		$settings = Helpers::get_settings();

		// Per-field sanitization based on field definitions provided by Helpers::get_settings_fields().
		$sanitized_post = [];
		foreach ( $posted_data as $key => $val ) {
			// Skip known control keys early
			if ( in_array( $key, [ 'perform_settings_barrier', '_wp_http_referer', 'action', 'nonce', 'data' ], true ) ) {
				continue;
			}

			// If value is an array, recursively clean it (for list-type fields)
			if ( is_array( $val ) ) {
				$sanitized_post[ $key ] = Helpers::clean( $val );
				continue;
			}

			$field_def = Helpers::find_field_by_id( $key );
			$raw_val   = wp_unslash( $val );

			if ( $field_def && isset( $field_def['type'] ) ) {
				switch ( $field_def['type'] ) {
					case 'toggle':
						// Normalize truthy values to 1, else 0
						$sanitized_post[ $key ] = ! empty( $raw_val ) && '0' !== $raw_val ? 1 : 0;
						break;
					case 'textarea':
						$sanitized_post[ $key ] = sanitize_textarea_field( $raw_val );
						break;
					case 'url':
						$sanitized_post[ $key ] = esc_url_raw( $raw_val );
						break;
					case 'select':
						// Ensure value is one of allowed options when provided
						$opts   = $field_def['options'] ?? [];
						$is_ok  = false;
						if ( is_array( $opts ) && ! empty( $opts ) ) {
							// If associative array (value=>label) check keys, otherwise check values
							$keys = array_keys( $opts );
							$vals = array_values( $opts );
							if ( array_diff_key( $opts, array_values( $opts ) ) ) {
								// associative
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
				// No field definition found – fall back to a safe cleaning
				$sanitized_post[ $key ] = is_scalar( $raw_val ) ? sanitize_text_field( $raw_val ) : Helpers::clean( $raw_val );
			}
		}

		// Merge sanitized values with existing settings to preserve missing keys
		$new_settings = wp_parse_args( $sanitized_post, is_array( $settings ) ? $settings : [] );

		// Handle newline-separated lists
		$new_settings['dns_prefetch'] = ! empty( $new_settings['dns_prefetch'] ) ? explode( "\n", $new_settings['dns_prefetch'] ) : '';
		$new_settings['preconnect']   = ! empty( $new_settings['preconnect'] ) ? explode( "\n", $new_settings['preconnect'] ) : '';

		$is_saved = update_option( 'perform_settings', $new_settings, false );

		if ( $is_saved ) {
			wp_send_json_success( [
				'type'    => 'success',
				'message' => esc_html__( 'Settings saved successfully.', 'perform' ),
			] );
		} else {
			wp_send_json_error( [
				'type'    => 'error',
				'message' => esc_html__( 'Unable to save the settings. Please try again.', 'perform' ),
			] );
		}
	}
}
