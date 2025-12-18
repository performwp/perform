<?php
/**
 * Perform | Disable Dashicons Module
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
 * Disable Dashicons Module Class.
 *
 * @since 2.0.0
 */
class DisableDashicons extends AbstractModule {

	/**
	 * Get the option key for this module.
	 *
	 * @return string
	 */
	protected static function get_option_key(): string {
		return 'disable_dashicons';
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', [ $this, 'disable_dashicons' ] );
	}

	/**
	 * Disable Dashicons.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_dashicons() {
		// Bailout, if user is logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		wp_dequeue_style( 'dashicons' );
	}
}
