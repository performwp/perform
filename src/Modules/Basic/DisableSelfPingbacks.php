<?php
/**
 * Perform | Disable Self Pingbacks Module
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
 * Disable Self Pingbacks Module Class.
 *
 * @since 2.0.0
 */
class DisableSelfPingbacks extends AbstractModule {
	protected static $option_key = 'disable_self_pingbacks';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'pre_ping', [ $this, 'disable_self_pingbacks' ], 99 );
	}

	/**
	 * Disable Self Pingbacks.
	 *
	 * @param array $links List of links.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_self_pingbacks( &$links ) {
		$home = get_option( 'home' );

		foreach ( $links as $key => $link ) {
			if ( 0 === strpos( $link, $home ) ) {
				unset( $links[ $key ] );
			}
		}
	}
}
