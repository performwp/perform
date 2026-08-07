<?php
/**
 * Perform - Admin Actions.
 *
 * @package    Perform
 * @subpackage Admin/Actions
 * @since      2.0.0
 * @author     Mehul Gohil
 */

namespace Perform\Admin;

use Perform\Includes\Helpers;
use Perform\Admin\Settings\ClientPayload;
use Perform\Admin\Settings\AutoloadOptionsAudit;
use Perform\Admin\Settings\DashboardPayload;
use Perform\Admin\Settings\Menu;
use Perform\Admin\Settings\RuntimeDiagnostics;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Actions {

	/**
	 * Constructor
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', [ $this, 'registerAssets' ] );
		add_action( 'admin_bar_menu', [ $this, 'add_to_admin_bar' ], 1000, 1 );
	}

	/**
	 * Add Admin Assets.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Preserve existing public method name for release compatibility.
	public function registerAssets() {
		$screen = get_current_screen();
		if ( empty( $screen ) || 'settings_page_perform_settings' !== $screen->id ) {
			return;
		}

		// Loads the WordPress components styles.
		wp_enqueue_style( 'wp-components' );

		wp_register_style( 'perform-admin', PERFORM_PLUGIN_URL . 'assets/dist/css/admin.css', '', PERFORM_VERSION );
		wp_enqueue_style( 'perform-admin' );

		wp_register_script( 'perform-admin', PERFORM_PLUGIN_URL . 'assets/dist/js/admin.min.js', [ 'wp-element', 'wp-components', 'wp-i18n' ], PERFORM_VERSION, true );
		wp_enqueue_script( 'perform-admin' );

			wp_localize_script(
				'perform-admin',
				'performwpSettings',
				[
					'version'            => defined( 'PERFORM_VERSION' ) ? PERFORM_VERSION : '',
					'docsUrl'            => defined( 'PERFORM_PLUGIN_DOCS_URL' ) ? PERFORM_PLUGIN_DOCS_URL : 'https://performwp.com/docs/',
					'logoUrl'            => plugins_url( 'assets/dist/images/logo.png', PERFORM_PLUGIN_FILE ),
					'nonce'              => wp_create_nonce( 'perform_save_settings' ),
					'saved'              => ClientPayload::sanitize_for_client( (array) \Perform\Includes\Helpers::get_settings() ),
					'dashboard'          => DashboardPayload::get_data(),
					'diagnostics'        => RuntimeDiagnostics::get_results(),
					'databaseAudit'      => AutoloadOptionsAudit::get_cached_snapshot(),
					'databaseAuditNonce' => wp_create_nonce( 'perform_refresh_autoload_options_audit' ),
					'sensitiveKeys'      => ClientPayload::get_sensitive_keys(),
					'maskedSecretValue'  => ClientPayload::MASKED_SECRET,
					'tabs'               => Menu::get_navigation_tabs(),
					'activeTab'          => Menu::get_requested_tab(),
					'fields'             => \Perform\Includes\Helpers::get_settings_fields(), // Expose fields to JS
					'cacheActivity'      => [
						'actionUrl'         => admin_url( 'admin-post.php' ),
						'exportNonce'       => wp_create_nonce( 'perform_export_cache_activity' ),
						'clearNonce'        => wp_create_nonce( 'perform_clear_cache_activity' ),
						'downloadName'      => 'perform-cache-activity-' . gmdate( 'Y-m-d' ) . '.csv',
						'heading'           => esc_html__( 'Activity actions', 'perform' ),
						'description'       => esc_html__( 'Export cache activity for review or clear the collected metrics.', 'perform' ),
						'exportLabel'       => esc_html__( 'Export CSV', 'perform' ),
						'exporting'         => esc_html__( 'Exporting…', 'perform' ),
						'exportSuccess'     => esc_html__( 'Cache activity exported.', 'perform' ),
						'exportError'       => esc_html__( 'Cache activity could not be exported.', 'perform' ),
						'clearLabel'        => esc_html__( 'Clear activity', 'perform' ),
						'clearing'          => esc_html__( 'Clearing…', 'perform' ),
						'clearConfirmation' => esc_html__( 'Clear all collected cache activity? This does not clear cached pages.', 'perform' ),
						'clearSuccess'      => esc_html__( 'Cache activity cleared.', 'perform' ),
						'clearError'        => esc_html__( 'Cache activity could not be cleared.', 'perform' ),
					],
				]
			);
	}

	/**
	 * This function is used to add assets manager button in admin bar.
	 *
	 * @param object $wp_admin_bar List of items on admin bar.
	 *
	 * @since  1.1.1
	 * @access public
	 *
	 * @return void
	 */
	public function add_to_admin_bar( $wp_admin_bar ) {
		// Bailout, if conditions below doesn't pass through.
		if ( ! current_user_can( 'manage_options' ) || is_admin() ) {
			return;
		}

		$is_assets_manager_open = isset( $_GET['perform'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display toggle.

		if ( ! $is_assets_manager_open ) {
			$href      = add_query_arg( 'perform', '1' );
			$menu_text = esc_html__( 'Assets Manager', 'perform' );
		} else {
			$href      = remove_query_arg( 'perform' );
			$menu_text = esc_html__( 'Close Assets Manager', 'perform' );
		}

		// Add Parent Menu.
		$wp_admin_bar->add_menu(
			[
				'id'    => 'perform',
				'title' => esc_html__( 'Perform', 'perform' ),
				'href'  => esc_url( admin_url( 'options-general.php?page=perform_settings' ) ),
			]
		);

		// Add Assets Manager Sub-menu.
		$wp_admin_bar->add_menu(
			[
				'parent' => 'perform',
				'id'     => 'assets-manager',
				'title'  => $menu_text,
				'href'   => esc_url( $href ),
			]
		);

		// Add Support Sub-menu.
		$wp_admin_bar->add_menu(
			[
				'parent' => 'perform',
				'id'     => 'support-forum',
				'title'  => esc_html__( 'Support Forum', 'perform' ),
				'href'   => esc_url( 'https://wordpress.org/support/plugin/perform/' ),
			]
		);
	}
}
