<?php
/**
 * Perform - Unit Tests Bootstrap file
 *
 * @since 1.2.2
 */
class Perform_Unit_Tests_Bootstrap
{
	/**
	 * Main Instance.
	 *
	 * @since  1.2.2
	 * @access protected
	 *
	 * @var \Perform_Unit_Tests_Bootstrap instance
	 */
	protected static $instance = null;

	/**
	 * WP Tests Directory.
	 *
	 * @since  1.2.2
	 * @access public
	 *
	 * @var string directory where wordpress-tests-lib is installed.
	 */
	public $wp_tests_dir;

	/**
	 * Tests Directory.
	 *
	 * @since  1.2.2
	 * @access public
	 *
	 * @var string testing directory.
	 */
	public $tests_dir;

	/**
	 * Plugins Directory.
	 *
	 * @since  1.2.2
	 * @access public
	 *
	 * @var string plugin directory.
	 */
	public $plugin_dir;

	/**
	 * Get the single class instance.
	 *
	 * @since  1.2.2
	 * @access public
	 *
	 * @return Perform_Unit_Tests_Bootstrap
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup the unit testing environment
	 *
	 * @since  1.2.2
	 * @access public
	 *
	 * @return void
	 */
	public function __construct()
	{
		ini_set( 'display_errors', 'on' );
		error_reporting(E_ALL);

		// Ensure server variable is set for WP email functions.
		if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
			$_SERVER['SERVER_NAME'] = 'localhost';
		}

		$this->tests_dir    = dirname( __FILE__ );
		$this->plugin_dir   = dirname( $this->tests_dir );
		$this->wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib';
		$manual_bootstrap   = isset( $GLOBALS['manual_bootstrap'] ) ? (bool) $GLOBALS['manual_bootstrap'] : true;

		// Load test function so tests_add_filter() is available.
		require_once( $this->wp_tests_dir . '/includes/functions.php' );

		// Load Perform.
		tests_add_filter( 'muplugins_loaded', array( $this, 'load_perform' ) );

		// Uninstall Perform.
		tests_add_filter( 'plugins_loaded', array( $this, 'uninstall_perform' ), 0 );

		// Install Perform.
		tests_add_filter( 'setup_theme', array( $this, 'install_give' ) );

		// Load the WP testing environment.
		if ( $manual_bootstrap ) {
			require_once($this->wp_tests_dir . '/includes/bootstrap.php');
			// Load testing framework.
			// Note: you must copy code of this function to your include function of bootstrap class.
			$this->includes();
		}
	}

	/**
	 * Uninstall Perform
	 *
	 * @since  1.2.2
	 * @access public
	 */
	public function uninstall_give()
	{
		give_update_option('uninstall_on_delete', 'enabled');
		// Prevent missing object issue.
		Give()->roles = new Give_Roles();
		// clean existing install first
		define('WP_UNINSTALL_PLUGIN', true);
		include($this->plugin_dir . '/uninstall.php');
	}

	/**
	 * Load Give
	 *
	 * @since  1.2.2
	 * @access public
	 *
	 * @return void
	 */
	public function load_give() {
		require_once($this->plugin_dir . '/perform.php');
	}

	/**
	 * Install Perform after the test environment and have been loaded.
	 *
	 * @since  1.2.2
	 * @access public
	 *
	 * @global WP_Roles $wp_roles
	 */
	public function install_perform() {

		echo 'Installing Perform...' . PHP_EOL;

		perform_install();

		$current_user = new WP_User( 1 );
		$current_user->set_role( 'administrator' );

		wp_update_user([
			'ID'         => 1,
			'first_name' => 'Admin',
			'last_name'  => 'User',
		]);
	}

	/**
	 * Load Give-specific test cases
	 *
	 * @since  1.2.2
	 * @access public
	 *
	 * @return void
	 */
	public function includes() {

	}
}

Perform_Unit_Tests_Bootstrap::instance();
