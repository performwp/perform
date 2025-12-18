<?php
/**
 * Perform | Remove jQuery Migrate Module
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
 * Remove jQuery Migrate Module Class.
 *
 * @since 2.0.0
 */
class RemoveJqueryMigrate extends AbstractModule {
    protected static $option_key = 'remove_jquery_migrate';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'wp_default_scripts', [ $this, 'remove_jquery_migrate' ], 99 );
	}

	/**
	 * Remove jQuery Migrate.
	 *
	 * @param WP_Scripts $scripts WP_Scripts object.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_jquery_migrate( $scripts ) {
		if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
			$script = $scripts->registered['jquery'];
			if ( $script->deps ) {
				$script->deps = array_diff( $script->deps, [ 'jquery-migrate' ] );
			}
		}
	}
}
