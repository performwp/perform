<?php
/**
 * Perform | Disable XMLRPC Module
 *
 * @package Perform
 * @subpackage Modules
 * @author PerformWP <hello@performwp.com>
 */

namespace Perform\Modules\Basic;

use Perform\Includes\Helpers;
use Perform\Modules\AbstractModule;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Disable XMLRPC Module Class.
 *
 * @since 2.0.0
 */
class DisableXmlrpc extends AbstractModule {

	/**
	 * Get the option key for this module.
	 *
	 * @return string
	 */
	protected static $option_key = 'disable_xmlrpc';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->disable_xmlrpc();
		// In addition to the xmlrpc_enabled filter, remove all XML-RPC
		// methods so xmlrpc.php doesn't expose functionality (pingbacks,
		// publishing, etc.). This prevents the endpoint from being useful.
		add_filter( 'xmlrpc_methods', [ $this, 'disable_all_xmlrpc_methods' ] );
	}

	/**
	 * Disable XMLRPC.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_xmlrpc() {
		// Disable XMLRPC.
		add_filter( 'xmlrpc_enabled', '__return_false' );
	}

	/**
	 * Disable all XMLRPC methods.
	 *
	 * @param array $methods XMLRPC methods.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function disable_all_xmlrpc_methods( $methods ) {
		return [];
	}
}
