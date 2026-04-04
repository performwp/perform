<?php
/**
 * Perform | Heartbeat Module
 *
 * @package Perform
 * @subpackage Modules
 * @author PerformWP <hello@performwp.com>
 */

namespace Perform\Modules\Basic;

use Perform\Includes\Helpers;
use Perform\Modules\ModuleInterface;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Heartbeat Module Class.
 *
 * @since 2.0.0
 */
class Heartbeat implements ModuleInterface {

	/**
	 * Determine whether this module should be loaded.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		return ! empty( Helpers::get_option( 'disable_heartbeat', 'perform_settings' ) ) || ! empty( Helpers::get_option( 'heartbeat_frequency', 'perform_settings' ) );
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->disable_heartbeat();

		// Limit Heartbeat frequency when appropriate.
		if ( 'disable_everywhere' !== Helpers::get_option( 'disable_heartbeat', 'perform_settings' ) ) {
			add_filter( 'heartbeat_settings', [ $this, 'heartbeat_frequency' ] );
		}
	}

	/**
	 * Disable Heartbeat.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_heartbeat() {
		$disable_heartbeat = Helpers::get_option( 'disable_heartbeat', 'perform_settings' );

		if ( 'disable_everywhere' === $disable_heartbeat ) {
			wp_deregister_script( 'heartbeat' );
		} elseif ( 'disable_on_dashboard_page' === $disable_heartbeat ) {
			global $pagenow;

			if ( 'index.php' === $pagenow ) {
				wp_deregister_script( 'heartbeat' );
			}
		} elseif ( 'allow_only_on_post_edit_pages' === $disable_heartbeat || 'allow_posts' === $disable_heartbeat ) {
			global $pagenow;

			if ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) {
				wp_deregister_script( 'heartbeat' );
			}
		}
	}

	/**
	 * Heartbeat frequency.
	 *
	 * @param array $settings Heartbeat settings.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return array
	 */
	public function heartbeat_frequency( $settings ) {
		$heartbeat_frequency = Helpers::get_option( 'heartbeat_frequency', 'perform_settings' );

		if ( ! empty( $heartbeat_frequency ) ) {
			$settings['interval'] = intval( $heartbeat_frequency );
		}

		return $settings;
	}
}
