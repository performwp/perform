<?php
/**
 * Perform | Disable RSS Feeds Module
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
 * Disable RSS Feeds Module Class.
 *
 * @since 2.0.0
 */
class DisableRssFeeds extends AbstractModule {
    protected static $option_key = 'disable_rss_feeds';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'do_feed', [ $this, 'disable_rss_feeds' ], 1 );
		add_action( 'do_feed_rdf', [ $this, 'disable_rss_feeds' ], 1 );
		add_action( 'do_feed_rss', [ $this, 'disable_rss_feeds' ], 1 );
		add_action( 'do_feed_rss2', [ $this, 'disable_rss_feeds' ], 1 );
		add_action( 'do_feed_atom', [ $this, 'disable_rss_feeds' ], 1 );
	}

	/**
	 * Disable RSS Feeds.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_rss_feeds() {
		wp_die( esc_html__( 'No feed available, please visit the homepage!', 'perform' ) );
	}
}
