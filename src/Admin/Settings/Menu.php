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
		add_action( 'in_admin_header', [ $this, 'render_settings_page_header' ] );
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
	 * Render Settings Page Header.
	 *
	 * @since  1.4.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_settings_page_header() {
		$screen = get_current_screen();

		if ( 'settings_page_perform_settings' !== $screen->id ) {
			return;
		}
		?>
		<div id="perform-settings-page" class="perform-settings-page"></div>
		<div class="perform-dashboard-header">
			<div class="perform-dashboard-header-title">
				<img src="<?php echo esc_url( PERFORM_PLUGIN_URL . 'assets/dist/images/logo.png' ); ?>" alt="<?php esc_attr_e( 'PerformWP', 'perform' ); ?>"/>
			</div>
			<?php $this->render_header_navigation(); ?>
		</div>
		<?php
	}

	/**
	 * Render Header Navigation.
	 *
	 * @since  1.4.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_header_navigation() {
		$current_tab = Helpers::get_current_tab();
		$tabs        = apply_filters( 'perform_settings_navigation_tabs', $this->tabs );

		if ( count( $tabs ) === 1 ) {
			return;
		}
		?>
		<ul class="perform-header-navigation">
			<?php foreach ( $tabs as $slug => $tab ) : ?>
				<li>
					<a href="<?php echo esc_url( admin_url( 'options-general.php?page=perform_settings&tab=' . $slug ) ); ?>" class="<?php echo esc_attr( $slug === $current_tab ? 'active' : '' ); ?>">
						<?php echo esc_html( $tab ); ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
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
		?>
		<div class="wrap perform-admin-settings-wrap">
			<div class="perform-admin-settings--left-section">
				<?php $this->render_left_section(); ?>
			</div>
			<div class="perform-admin-settings--right-section">
				<?php $this->render_right_section(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render `Left Section` of the admin settings.
	 *
	 * This section will display the admin settings fields.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_left_section() {
		$fields = Helpers::get_settings_fields();

		$this->render_fields( $fields );
	}

	/**
	 * Render `Right Section` of the admin settings.
	 *
	 * This section will display the help area to ensure better utilizing of the plugin.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function render_right_section() {
		ob_start();

		$check_our_documentation = esc_html__( 'Check our documentation', 'perform' );
		?>
		<div class="performwp-sidebar-section">
			<a href="https://performwp.com/docs/" title="<?php echo $check_our_documentation; ?>" target="_blank">
				<img src="<?php echo PERFORM_PLUGIN_URL . 'assets/dist/images/check-our-documentation.svg'; ?>" alt="<?php echo $check_our_documentation; ?>"/>
			</a>
		</div>
		<?php
		echo ob_get_clean();
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
		$posted_data = Helpers::clean( $_POST );
		$settings    = Helpers::get_settings();

		$new_settings = wp_parse_args( $posted_data, $settings );

		$new_settings['dns_prefetch'] = ! empty( $new_settings['dns_prefetch'] ) ? explode( "\n", $new_settings['dns_prefetch'] ) : '';
		$new_settings['preconnect']   = ! empty( $new_settings['preconnect'] ) ? explode( "\n", $new_settings['preconnect'] ) : '';

		unset( $new_settings['perform_settings_barrier'], $new_settings['_wp_http_referer'], $new_settings['action'] );

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
