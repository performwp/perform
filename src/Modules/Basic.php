<?php
/**
 * Perform | Basic
 *
 * @package Perform
 * @subpackage Modules
 * @author PerformWP <hello@performwp.com>
 */

namespace Perform\Modules;

use Perform\Includes\Helpers;
use function woocommerce_is_account_page;
use function woocommerce_is_checkout;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Basic Settings Module Class.
 *
 * @since 2.0.0
 */
class Basic implements ModuleInterface {

	/**
	 * Admin Settings
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @var $settings
	 */
	public $settings;

	/**
	 * Constructor.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function __construct( array $settings = [] ) {
		$this->settings = ! empty( $settings ) ? $settings : Helpers::get_settings();
	}

	/**
	 * Determine whether this module should be loaded.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		// All settings have been migrated to individual modules.
		return false;
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		// All functionality has been migrated to individual modules.
	}
}
