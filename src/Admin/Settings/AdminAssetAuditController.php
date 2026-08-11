<?php
/**
 * Perform - Admin Asset Audit Controller.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminAssetAuditController {
	/** Register protected actions. */
	public function __construct() {
		add_action( 'wp_ajax_perform_clear_admin_asset_audit', [ $this, 'clear' ] );
	}

	/** @return void */
	public function clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to clear admin asset audit data.', 'perform' ) ], 403 );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'perform_clear_admin_asset_audit' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'perform' ) ], 403 );
		}

		AdminAssetAudit::clear();
		wp_send_json_success(
			[
				'message'  => __( 'Admin asset audit cleared.', 'perform' ),
				'snapshot' => AdminAssetAudit::get_snapshot(),
			]
		);
	}
}
