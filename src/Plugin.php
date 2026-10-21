<?php
namespace Perform;

use Perform\Admin;
use Perform\Admin\Settings;
use Perform\Modules;
use Perform\Includes\Helpers;

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

		// Ensure legacy settings are migrated to perform_settings.
		Settings\Migrator::maybe_migrate_legacy_settings();

		// Load Admin Files.
		new Settings\Menu();
		new Settings\AutoloadOptionsAuditController();
		new Settings\SiteInventoryController();
		new Settings\AdminPerformanceMonitorController();
		new Settings\AdminAssetAuditController();
		new Settings\CronPressureAuditController();
		new Settings\ActionSchedulerAuditController();
		new Admin\Actions();
		new Admin\Filters();

		// Load Frontend Files.
		new Includes\Actions();
		new Includes\Filters();

		// Centralized module loader - preserves backward compatibility with
		// modules that still register hooks in their constructors while
		// supporting new modules implementing ModuleInterface.
		$settings = Helpers::get_settings();
		$settings = is_array( $settings ) ? $settings : [];
		new Settings\AdminPerformanceMonitor( $settings );
		new Settings\AdminAssetAudit( $settings );

		$loader = new Modules\Loader( $settings );

		$loader->register_modules( Modules\Registry::all() );
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
		$perform_fs = fs_dynamic_init(
			[
				'id'             => '18658',
				'slug'           => 'perform',
				'type'           => 'plugin',
				'public_key'     => 'pk_d4518e758d9fc19cb25ffe77371fa',
				'is_premium'     => false,
				'has_addons'     => false,
				'has_paid_plans' => false,
				'menu'           => [
					'slug'   => 'perform_settings',
					'parent' => [
						'slug' => 'options-general.php',
					],
				],
			]
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
