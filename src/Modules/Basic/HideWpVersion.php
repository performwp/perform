<?php
/**
 * Perform | Hide WP Version Module
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
 * Hide WP Version Module Class.
 *
 * @since 2.0.0
 */
class HideWpVersion extends AbstractModule {

	/**
	 * Get the option key for this module.
	 *
	 * @return string
	 */
	protected static function get_option_key(): string {
		return 'hide_wp_version';
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hide_wp_version();
	}

	/**
	 * Hide WordPress Version.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function hide_wp_version() {
		// Remove WP Generator using action hook.
		remove_action( 'wp_head', 'wp_generator' );

		// Return empty string to the generator using filter hook.
		add_filter( 'the_generator', '__return_empty_string' );
	}
}
