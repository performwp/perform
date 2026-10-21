<?php
/**
 * Perform - WP-Cron pressure diagnostic controller.
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
 * Handles authorized refresh and reset requests.
 */
final class CronPressureAuditController {
	/**
	 * Register WordPress hooks.
	 */
	public function __construct() {
		add_action( 'wp_ajax_perform_refresh_cron_pressure_audit', [ $this, 'refresh' ] );
		add_action( 'wp_ajax_perform_clear_cron_pressure_audit', [ $this, 'clear' ] );
	}

	/**
	 * Refresh the current-site snapshot.
	 *
	 * @return void
	 */
	public function refresh() {
		$this->authorize();

		try {
			$audit = CronPressureAudit::refresh();
		} catch ( Throwable $exception ) {
			wp_send_json_error(
				[ 'message' => esc_html__( 'The scheduled-task diagnostic could not be refreshed. Please try again.', 'perform' ) ],
				500
			);
		}

		wp_send_json_success(
			[
				'message' => esc_html__( 'Scheduled-task diagnostic refreshed.', 'perform' ),
				'audit'   => $audit,
			]
		);
	}

	/**
	 * Clear only Perform's cached snapshot.
	 *
	 * @return void
	 */
	public function clear() {
		$this->authorize();
		CronPressureAudit::clear();

		wp_send_json_success(
			[
				'message' => esc_html__( 'Scheduled-task diagnostic cleared.', 'perform' ),
				'audit'   => CronPressureAudit::get_cached_snapshot(),
			]
		);
	}

	/**
	 * Enforce capability and nonce checks.
	 *
	 * @return void
	 */
	private function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Insufficient permissions.', 'perform' ) ], 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'perform_cron_pressure_audit' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'perform' ) ], 403 );
		}
	}
}
