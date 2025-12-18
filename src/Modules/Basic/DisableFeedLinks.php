<?php
/**
 * Perform | Disable Feed Links Module
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
 * Disable Feed Links Module Class.
 *
 * @since 2.0.0
 */
class DisableFeedLinks extends AbstractModule {
    protected static $option_key = 'disable_feed_links';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->disable_rss_feed_links();
	}

	/**
	 * Disable RSS Feed Links.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_rss_feed_links() {
		// Remove feed links.
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
	}
}
