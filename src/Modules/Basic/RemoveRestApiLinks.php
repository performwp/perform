<?php
/**
 * Perform | Remove REST API Links Module
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
 * Remove REST API Links Module Class.
 *
 * @since 2.0.0
 */
class RemoveRestApiLinks extends AbstractModule {

	/**
	 * Get the option key for this module.
	 *
	 * @return string
	 */
	protected static function get_option_key(): string {
		return 'remove_rest_api_links';
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->remove_rest_api_links();
	}

	/**
	 * Remove Rest API Links.
	 *
	 * @since  2.0.0
	 * @access public
	 *
	 * @return void
	 */
	public function remove_rest_api_links() {
		// Remove REST API link tag from head.
		remove_action( 'wp_head', 'rest_output_link_wp_head' );

		// Remove REST API link from HTTP headers.
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}
}
