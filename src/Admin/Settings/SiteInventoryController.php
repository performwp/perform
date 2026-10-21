<?php
/**
 * Perform - Local site inventory REST controller.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use Throwable;
use WP_Error;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles authorized manual inventory refresh requests.
 */
final class SiteInventoryController {
	/**
	 * Register REST hooks.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the administrator-only refresh route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			'perform/v1',
			'/site-inventory',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'refresh' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			]
		);
	}

	/**
	 * Restrict inventory identities to administrators.
	 *
	 * @return true|WP_Error
	 */
	public function permissions_check() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'perform_site_inventory_forbidden',
			esc_html__( 'Insufficient permissions.', 'perform' ),
			[ 'status' => 403 ]
		);
	}

	/**
	 * Refresh the inventory for the current site.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function refresh() {
		try {
			$inventory = SiteInventory::refresh();
		} catch ( Throwable $exception ) {
			return new WP_Error(
				'perform_site_inventory_failed',
				esc_html__( 'The site inventory could not be refreshed. Please try again.', 'perform' ),
				[ 'status' => 500 ]
			);
		}

		return [
			'message'   => esc_html__( 'Site inventory refreshed.', 'perform' ),
			'inventory' => $inventory,
		];
	}
}
