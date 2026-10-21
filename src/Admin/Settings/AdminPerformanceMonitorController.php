<?php
/**
 * Perform - Admin Performance Monitor Controller.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminPerformanceMonitorController {
	/**
	 * Register protected report actions.
	 */
	public function __construct() {
		add_action( 'wp_ajax_perform_clear_admin_performance_monitor', [ $this, 'clear' ] );
	}

	/**
	 * Clear aggregate metrics for the current site.
	 *
	 * @return void
	 */
	public function clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to clear admin performance data.', 'perform' ) ], 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'perform_clear_admin_performance_monitor' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'perform' ) ], 403 );
		}
		AdminPerformanceMonitor::clear();

		wp_send_json_success(
			[
				'message'  => __( 'Admin performance data cleared.', 'perform' ),
				'snapshot' => AdminPerformanceMonitor::get_snapshot(),
			]
		);
	}
}
