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
	 * Tabs for the settings page.
	 *
	 * @since 2.0.0
	 * @access public
	 *
	 * @var array
	 */
	public $tabs = [];

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		$this->prefix = 'perform_';
		$this->tabs   = Helpers::get_settings_tabs();

		add_action( 'admin_menu', [ $this, 'register_admin_menu' ], 9 );
		add_action( 'wp_ajax_perform_save_settings', [ $this, 'save_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] ); // Added for React settings localization
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
		$settings    = Helpers::get_settings();

		$new_settings = wp_parse_args( $posted_data, $settings );

		$new_settings['dns_prefetch'] = ! empty( $new_settings['dns_prefetch'] ) ? explode( "\n", $new_settings['dns_prefetch'] ) : '';
		$new_settings['preconnect']   = ! empty( $new_settings['preconnect'] ) ? explode( "\n", $new_settings['preconnect'] ) : '';

		// Remove control fields that should not be stored
		unset( $new_settings['perform_settings_barrier'], $new_settings['_wp_http_referer'], $new_settings['action'], $new_settings['nonce'], $new_settings['data'] );

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
