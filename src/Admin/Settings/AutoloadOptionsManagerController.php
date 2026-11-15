<?php
/**
 * Authorized, current-site autoload management endpoints.
 *
 * @package Perform
 */
namespace Perform\Admin\Settings;
use Throwable;

/** Routes explicit administrator requests. */
final class AutoloadOptionsManagerController {
	/** Register AJAX endpoints. */
	public function __construct() {
		foreach ( [ 'list', 'change', 'undo' ] as $action ) {
			$hook = 'list' === $action ? 'perform_list_autoload_options' : 'perform_' . $action . '_option_autoload';
			add_action( 'wp_ajax_' . $hook, [ $this, $action . '_options' ] );
		}
	}

	/** Require current-site authority and an action-specific nonce. */
	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $this->input( 'nonce' ), 'perform_manage_autoload_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission or security check failed.', 'perform' ) ], 403 );
		}
	}

	/** Read scalar input only; preserve exact option names for identity checks. */
	private function input( string $key ): string {
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}

	/** List searchable option metadata. */
	public function list_options(): void {
		$this->authorize();
		try {
			$result = AutoloadOptionsManager::listing( $this->input( 'search' ), (int) $this->input( 'page' ), $this->input( 'view' ) );
		} catch ( Throwable $error ) {
			wp_send_json_error( [ 'message' => __( 'Options could not be loaded. Please retry.', 'perform' ) ], 500 );
		}
		wp_send_json_success( $result );
	}

	/** Change only an option's autoload policy. */
	public function change_options(): void {
		$this->mutate( false );
	}

	/** Restore the exact policy recorded before the managed change. */
	public function undo_options(): void {
		$this->mutate( true );
	}

	/** Handle a reversible transaction; never return database errors or values. */
	private function mutate( bool $undo ): void {
		$this->authorize();
		try {
			$result = AutoloadOptionsManager::change( $this->input( 'name' ), $this->input( 'token' ), $undo ? 'off' : $this->input( 'autoload' ), $undo );
		} catch ( \RuntimeException $error ) {
			wp_send_json_error(
				[
					'message' => $error->getMessage(),
					'code'    => 'stale',
				],
				409
			);
		} catch ( Throwable $error ) {
			wp_send_json_error( [ 'message' => __( 'The change could not be confirmed. Refresh before trying again.', 'perform' ) ], 500 );
		}
		wp_send_json_success( $result );
	}
}
