<?php
/**
 * Perform | Remove RSD Link Module
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
 * Remove RSD Link Module Class.
 *
 * @since 2.0.0
 */
class RemoveRsdLink extends AbstractModule {
    protected static $option_key = 'remove_rsd_link';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->remove_rsd_link();
	}

	/**
	 * Remove RSD link.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_rsd_link() {
		remove_action( 'wp_head', 'rsd_link' );
	}
}
