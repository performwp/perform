<?php
namespace Perform;

use Perform\Admin;
use Perform\Admin\Settings;
use Perform\Modules;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and registers plugin functionality through WordPress hooks.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Registers functionality with WordPress hooks.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register() {
		// Handle plugin activation and deactivation.
		register_activation_hook( PERFORM_PLUGIN_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( PERFORM_PLUGIN_FILE, [ $this, 'deactivate' ] );

		// Register services used throughout the plugin.
		add_action( 'plugins_loaded', [ $this, 'register_services' ] );

		// Load text domain.
		add_action( 'init', [ $this, 'load_plugin_textdomain' ] );
	}

	/**
	 * Registers the individual services of the plugin.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function register_services() {
		// Load Freemius SDK.
		$this->load_freemius();

		// Load Admin Files.
		new Settings\Api();
		new Settings\Menu();
		new Admin\Actions();
		new Admin\Filters();

		// Load Frontend Files.
		new Includes\Actions();
		new Includes\Filters();
		new Modules\Basic();
		new Modules\Cdn_Manager();
		new Modules\Assets_Manager();
		new Modules\Ssl_Manager();
		new Modules\Woocommerce_Manager();
		new Modules\Menu_Cache();
	}

	/**
	 * Loads the Freemius SDK.
	 * 
	 * @since  1.4.0
	 * @access public
	 * 
	 * @return void
	 */
	public function load_freemius() {
		// Include Freemius SDK.
        $perform_fs = fs_dynamic_init( array(
			'id'                  => '18658',
			'slug'                => 'perform',
			'type'                => 'plugin',
			'public_key'          => 'pk_d4518e758d9fc19cb25ffe77371fa',
			'is_premium'          => false,
			'has_addons'          => false,
			'has_paid_plans'      => false,
			'menu'                => array(
				'slug'           => 'perform_settings',
				'parent'         => array(
					'slug' => 'options-general.php',
				),
			),
		) );
	}

	/**
	 * Loads the plugin's translated strings.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'perform',
			false,
			dirname( plugin_basename( PERFORM_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Handles activation procedures during installation and updates.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param bool $network_wide Optional. Whether the plugin is being enabled on
	 *                           all network sites or a single site. Default false.
	 *
	 * @return void
	 */
	public function activate( $network_wide = false ) {}

	/**
	 * Handles deactivation procedures.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function deactivate() {}
}
