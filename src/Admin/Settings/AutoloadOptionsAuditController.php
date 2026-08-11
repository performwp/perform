<?php
/**
 * Perform - Autoloaded options audit controller.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use Throwable;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles authorized manual audit refresh requests.
 */
final class AutoloadOptionsAuditController {
	/**
	 * Register WordPress hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_perform_refresh_autoload_options_audit', [ $this, 'refresh' ] );
	}

	/**
	 * Refresh the audit for the current site.
	 *
	 * @return void
	 */
	public function refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Insufficient permissions.', 'perform' ),
				],
				403
			);
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'perform_refresh_autoload_options_audit' ) ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'Security check failed.', 'perform' ),
				],
				403
			);
		}

		try {
			$audit = AutoloadOptionsAudit::refresh();
		} catch ( Throwable $exception ) {
			wp_send_json_error(
				[
					'message' => esc_html__( 'The database audit could not be refreshed. Please try again.', 'perform' ),
				],
				500
			);
		}

		wp_send_json_success(
			[
				'message' => esc_html__( 'Database audit refreshed.', 'perform' ),
				'audit'   => $audit,
			]
		);
	}
}
