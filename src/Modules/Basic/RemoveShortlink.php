<?php
/**
 * Perform | Remove Shortlink Module
 *
 * @package Perform
 * @subpackage Modules
 * @author PerformWP <hello@performwp.com>
 */

namespace Perform\Modules\Basic;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remove Shortlink Module Class.
 *
 * @since 2.0.0
 */
class RemoveShortlink extends AbstractModule {
    protected static $option_key = 'remove_shortlink';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->remove_shortlink();
	}

	/**
	 * Remove Shortlink.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_shortlink() {
		// Remove HTML link.
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );

		// Remove HTTP header.
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
	}
}
