<?php
/**
 * Perform | Limit Post Revisions Module
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
 * Limit Post Revisions Module Class.
 *
 * @since 2.0.0
 */
class LimitPostRevisions extends AbstractModule {

	/**
	 * Get the option key for this module.
	 *
	 * @return string
	 */
	protected static function get_option_key(): string {
		return 'limit_post_revisions';
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_revisions_to_keep', [ $this, 'limit_post_revisions' ] );
	}

	/**
	 * Limit Post Revisions.
	 *
	 * @param int $num Number of revisions to keep.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return int
	 */
	public function limit_post_revisions( $num ) {
		return intval( $this->get_setting() );
	}
}
