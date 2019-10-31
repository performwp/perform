<?php
/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and starts the plugin.
 *
 * @since      1.0.0
 * @package    WordPress
 * @subpackage Perform
 * @author     Mehul Gohil
 * @link       https://performwp.com
 *
 * @wordpress-plugin
 *
 * Plugin Name: Perform - Performance Optimization Plugin for WordPress
 * Plugin URI: https://performwp.com/
 * Description: This plugin adds toolset for performance and speed improvements to your WordPress sites.
 * Version: 1.2.2
 * Author: Mehul Gohil
 * Author URI: https://www.mehulgohil.in/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: perform
 * Domain Path: /languages
 */

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check for class Perform existence.
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'Perform' ) ) {

	/**
	 * Class Perform
	 *
	 * @since 1.0.0
	 */
	final class Perform {

		/** Singleton *************************************************************/

		/**
		 * Single Instance for Perform plugin.
		 *
		 * @since  1.0.0
		 * @access protected
		 *
		 * @var    Perform() The one true instance fo Perform plugin
		 */
		protected static $_instance;

		/**
		 * Settings Object
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @var Perform_Admin_Settings object
		 */
		public $settings;

		/**
		 * Config Object to transform.
		 *
		 * @since  1.2.2
		 * @access public
		 *
		 * @var WPConfigTransformer object
		 */
		public $config;

		/**
		 * Throw error on object clone.
		 *
		 * The whole idea of the singleton design pattern is that there is a single
		 * object, therefore we don't want the object to be cloned.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function __clone() {
			// Cloning instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'perform' ), '1.0.0' );
		}

		/**
		 * Disable un-serializing of the class.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function __wakeup() {
			// Un-serializing instances of the class is forbidden.
			_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'perform' ), '1.0.0' );
		}

		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @static
		 * @see    Perform()
		 *
		 * @return Perform
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		/**
		 * Perform constructor.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function __construct() {

			$this->setup_constants();

			// Bailout: Need minimum php version to load plugin.
			if (
				function_exists( 'phpversion' ) &&
				version_compare( PERFORM_REQUIRED_PHP_VERSION, phpversion(), '>' )
			) {
				add_action( 'admin_notices', array( $this, 'minimum_phpversion_notice' ) );

				return;
			}

			$this->includes();

			// Register activation hook.
			register_activation_hook( PERFORM_PLUGIN_FILE, 'perform_install' );

			// Init plugin.
			add_action( 'plugins_loaded', array( $this, 'init' ), 0 );

		}

		/**
		 * Init Perform when WordPress Initializes.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 *
		 * @throws
		 */
		public function init() {

			// Set up localization.
			$this->load_textdomain();

			$this->settings = new Perform_Admin_Settings();
			$this->config   = new WPConfigTransformer( ABSPATH . 'wp-config.php' );
		}

		/**
		 * Setup Constants.
		 *
		 * @since  1.0.0
		 * @access private
		 *
		 * @return void
		 */
		private function setup_constants() {

			// Plugin version.
			if ( ! defined( 'PERFORM_VERSION' ) ) {
				define( 'PERFORM_VERSION', '1.2.2' );
			}

			// Minimum Required PHP version.
			if ( ! defined( 'PERFORM_REQUIRED_PHP_VERSION' ) ) {
				define( 'PERFORM_REQUIRED_PHP_VERSION', '5.6' );
			}

			// Plugin Root File.
			if ( ! defined( 'PERFORM_PLUGIN_FILE' ) ) {
				define( 'PERFORM_PLUGIN_FILE', __FILE__ );
			}

			// Plugin Folder Path.
			if ( ! defined( 'PERFORM_PLUGIN_DIR' ) ) {
				define( 'PERFORM_PLUGIN_DIR', plugin_dir_path( PERFORM_PLUGIN_FILE ) );
			}

			// Plugin Folder URL.
			if ( ! defined( 'PERFORM_PLUGIN_URL' ) ) {
				define( 'PERFORM_PLUGIN_URL', plugin_dir_url( PERFORM_PLUGIN_FILE ) );
			}

			// Plugin Basename.
			if ( ! defined( 'PERFORM_PLUGIN_BASENAME' ) ) {
				define( 'PERFORM_PLUGIN_BASENAME', plugin_basename( PERFORM_PLUGIN_FILE ) );
			}

		}

		/**
		 * Add files to include.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function includes() {

			// Load Admin Files.
			require_once PERFORM_PLUGIN_DIR . '/includes/admin/class-perform-welcome.php';
			require_once PERFORM_PLUGIN_DIR . '/includes/admin/class-perform-admin-settings-api.php';
			require_once PERFORM_PLUGIN_DIR . '/includes/admin/class-perform-admin-settings.php';

			// Load Libraries.
			require_once PERFORM_PLUGIN_DIR . '/includes/libraries/WPConfigTransformer.php';

			// Load Frontend Files.
			require_once PERFORM_PLUGIN_DIR . '/includes/install.php';
			require_once PERFORM_PLUGIN_DIR . '/includes/actions.php';
			require_once PERFORM_PLUGIN_DIR . '/includes/misc-functions.php';
			require_once PERFORM_PLUGIN_DIR . '/includes/functions.php';
			require_once PERFORM_PLUGIN_DIR . '/includes/admin/admin-filters.php';

		}

		/**
		 * Loads the plugin language files.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return void
		 */
		public function load_textdomain() {

			// Set filter for languages directory.
			$lang_dir = dirname( PERFORM_PLUGIN_BASENAME ) . '/languages/';
			$lang_dir = apply_filters( 'perform_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter.
			$locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
			$locale = apply_filters( 'plugin_locale', $locale, 'perform' );

			unload_textdomain( 'perform' );
			load_textdomain( 'perform', WP_LANG_DIR . '/perform/perform-' . $locale . '.mo' );
			load_plugin_textdomain( 'perform', false, $lang_dir );

		}

		/**
		 * Show minimum PHP version notice.
		 *
		 * @since  1.0.0
		 * @access public
		 *
		 * @return mixed
		 */
		public function minimum_phpversion_notice() {
			// Bailout.
			if ( ! is_admin() ) {
				return;
			}

			$notice_desc  = '<p><strong>' . __( 'Your site could be faster and more secure with a newer PHP version.', 'perform' ) . '</strong></p>';
			$notice_desc .= '<p>' . __( 'Hey, we\'ve noticed that you\'re running an outdated version of PHP. PHP is the programming language that WordPress and Perform for WordPress are built on. The version that is currently used for your site is no longer supported. Newer versions of PHP are both faster and more secure. In fact, your version of PHP no longer receives security updates, which is why we\'re sending you this notice.', 'perform' ) . '</p>';
			$notice_desc .= '<p>' . __( 'Hosts have the ability to update your PHP version, but sometimes they don\'t dare to do that because they\'re afraid they\'ll break your site.', 'perform' ) . '</p>';
			$notice_desc .= '<p><strong>' . __( 'To which version should I update?', 'perform' ) . '</strong></p>';
			$notice_desc .= '<p>' . __( 'You should update your PHP version to either 5.6 or to 7.0 or 7.1. On a normal WordPress site, switching to PHP 5.6 should never cause issues. We would however actually recommend you switch to PHP7. There are some plugins that are not ready for PHP7 though, so do some testing first. PHP7 is much faster than PHP 5.6. It\'s also the only PHP version still in active development and therefore the better option for your site in the long run.', 'perform' ) . '</p>';
			$notice_desc .= '<p><strong>' . __( 'Can\'t update? Ask your host!', 'perform' ) . '</strong></p>';
			$notice_desc .= '<p>' . sprintf( __( 'If you cannot upgrade your PHP version yourself, you can send an email to your host. If they don\'t want to upgrade your PHP version, we would suggest you switch hosts. Have a look at one of the recommended %1$sWordPress hosting partners%2$s.', 'perform' ), sprintf( '<a href="%1$s" target="_blank">', esc_url( 'https://wordpress.org/hosting/' ) ), '</a>' ) . '</p>';

			echo sprintf(
				'<div class="notice notice-error">%1$s</div>',
				wp_kses_post( $notice_desc )
			);
		}
	}
}

/**
 * Start Perform plugin
 *
 * Example: <?php $perform = perform(); ?>
 *
 * @since 1.0.0
 * @return object|Perform
 */
function perform() {
	return Perform::instance();
}

perform();
