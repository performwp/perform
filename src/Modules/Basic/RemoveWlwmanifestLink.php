<?php
/**
 * Perform | Remove WLW Manifest Link Module
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
 * Remove WLW Manifest Link Module Class.
 *
 * @since 2.0.0
 */
class RemoveWlwmanifestLink extends AbstractModule {

	/**
	 * Get the option key for this module.
	 *
	 * @return string
	 */
	protected static function get_option_key(): string {
		return 'remove_wlwmanifest_link';
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->remove_wlwmanifest_link();
	}

	/**
	 * Remove wlwmanifest link.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_wlwmanifest_link() {
		remove_action( 'wp_head', 'wlwmanifest_link' );
	}
}
