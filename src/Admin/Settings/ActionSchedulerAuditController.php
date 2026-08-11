<?php
/**
 * Perform - Action Scheduler diagnostic controller.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Handles authorized refresh and reset requests. */
final class ActionSchedulerAuditController {
	/** Register WordPress hooks. */
	public function __construct() {
		add_action( 'wp_ajax_perform_refresh_action_scheduler_audit', [ $this, 'refresh' ] );
		add_action( 'wp_ajax_perform_clear_action_scheduler_audit', [ $this, 'clear' ] );
	}

	/** @return void */
	public function refresh() {
		$this->authorize();
		try {
			$audit = ActionSchedulerAudit::refresh();
		} catch ( Throwable $exception ) {
			wp_send_json_error( [ 'message' => esc_html__( 'The Action Scheduler diagnostic could not be refreshed. Please try again.', 'perform' ) ], 500 );
		}
		wp_send_json_success(
			[
				'message' => esc_html__( 'Action Scheduler diagnostic refreshed.', 'perform' ),
				'audit'   => $audit,
			]
		);
	}

	/** @return void */
	public function clear() {
		$this->authorize();
		ActionSchedulerAudit::clear();
		wp_send_json_success(
			[
				'message' => esc_html__( 'Action Scheduler diagnostic cleared.', 'perform' ),
				'audit'   => ActionSchedulerAudit::get_cached_snapshot(),
			]
		);
	}

	/** @return void */
	private function authorize() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Insufficient permissions.', 'perform' ) ], 403 );
		}
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'perform_action_scheduler_audit' ) ) {
			wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'perform' ) ], 403 );
		}
	}
}
