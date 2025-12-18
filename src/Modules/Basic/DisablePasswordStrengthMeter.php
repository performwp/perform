<?php
/**
 * Perform | Disable Password Strength Meter Module
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
 * Disable Password Strength Meter Module Class.
 *
 * @since 2.0.0
 */
class DisablePasswordStrengthMeter extends AbstractModule {
    protected static $option_key = 'disable_password_strength_meter';

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_print_scripts', [ $this, 'disable_password_strength_meter' ], 100 );
	}

	/**
	 * Disable Password Strength Meter.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function disable_password_strength_meter() {
		if ( wp_script_is( 'password-strength-meter', 'enqueued' ) ) {
			wp_dequeue_script( 'password-strength-meter' );
		}
	}
}
