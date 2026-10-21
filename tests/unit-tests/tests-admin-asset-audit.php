<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\AdminAssetAudit;
use Perform\Admin\Settings\AdminAssetAuditController;

final class Tests_Admin_Asset_Audit extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options']          = [];
		$GLOBALS['perform_test_actions']          = [];
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']      = true;
		$_POST                                    = [];
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_actions'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_nonce_valid'],
			$GLOBALS['perform_test_json_response']
		);
		$_POST = [];
	}

	public function test_disabled_mode_registers_no_capture_hooks() {
		new AdminAssetAudit( [ 'enable_admin_asset_audit' => 0 ] );

		$this->assertSame( [], $GLOBALS['perform_test_actions'] );
		$this->assertArrayNotHasKey( AdminAssetAudit::OPTION_NAME, $GLOBALS['perform_test_options'] );
	}

	public function test_enabled_mode_registers_bounded_admin_capture_hooks() {
		new AdminAssetAudit( [ 'enable_admin_asset_audit' => 1 ] );

		$this->assertSame( [ 'current_screen', 'admin_print_footer_scripts' ], array_column( $GLOBALS['perform_test_actions'], 'hook' ) );
	}

	public function test_source_attribution_strips_urls_and_has_safe_fallbacks() {
		$this->assertSame(
			[
				'source'     => 'sample-plugin',
				'confidence' => 'high',
			],
			AdminAssetAudit::classify_source( 'https://example.test/wp-content/plugins/sample-plugin/admin.js?nonce=secret' )
		);
		$this->assertSame(
			[
				'source'     => 'wordpress-core',
				'confidence' => 'high',
			],
			AdminAssetAudit::classify_source( '/wp-includes/js/jquery/jquery.js' )
		);
		$this->assertSame(
			[
				'source'     => 'unknown',
				'confidence' => 'low',
			],
			AdminAssetAudit::classify_source( 'https://cdn.example.test/file.js?token=secret' )
		);
	}

	public function test_samples_are_deduplicated_bounded_and_mark_repeated_presence() {
		$this->enable_audit();
		$asset = [
			'type'       => 'script',
			'handle'     => 'sample-admin',
			'source'     => 'sample-plugin',
			'confidence' => 'high',
		];
		AdminAssetAudit::record_sample( 'dashboard', [ $asset, $asset ], 1700000000 );
		AdminAssetAudit::record_sample( 'edit-post', [ $asset ], 1700000001 );
		$snapshot = AdminAssetAudit::record_sample( 'plugins', [ $asset ], 1700000002 );

		$this->assertCount( 3, $snapshot['screens'] );
		$this->assertCount( 1, $snapshot['repeated'] );
		$this->assertSame( 3, $snapshot['repeated'][0]['screenCount'] );
		$this->assertSame( 'sample-plugin', $snapshot['repeated'][0]['source'] );
		$stored = wp_json_encode( $GLOBALS['perform_test_options'][ AdminAssetAudit::OPTION_NAME ] );
		$this->assertStringNotContainsString( 'example.test', $stored );
		$this->assertStringNotContainsString( 'nonce', $stored );
		$this->assertStringNotContainsString( 'secret', $stored );
	}

	public function test_retention_and_screen_limit_are_enforced() {
		$this->enable_audit();
		$now = 1700000000;
		AdminAssetAudit::record_sample( 'expired', [], $now - ( 8 * 86400 ) );
		for ( $index = 0; $index < 35; ++$index ) {
			AdminAssetAudit::record_sample( 'screen-' . $index, [], $now + $index );
		}

		$snapshot = AdminAssetAudit::get_snapshot();
		$this->assertCount( AdminAssetAudit::MAX_SCREENS, $snapshot['screens'] );
		$this->assertNotContains( 'expired', array_column( $snapshot['screens'], 'id' ) );
		$this->assertContains( 'screen-34', array_column( $snapshot['screens'], 'id' ) );
	}

	public function test_malformed_stored_rows_are_ignored_without_exposing_values() {
		$this->enable_audit();
		$GLOBALS['perform_test_options'][ AdminAssetAudit::OPTION_NAME ] = [
			'schemaVersion' => AdminAssetAudit::SCHEMA_VERSION,
			'updatedAt'     => 1700000000,
			'screens'       => [
				'bad',
				[
					'id'     => 'safe-screen',
					'assets' => [
						null,
						[
							'type'   => 'script',
							'handle' => 'safe-handle',
							'source' => 'unsafe source?token=secret',
						],
					],
				],
			],
		];

		$snapshot = AdminAssetAudit::get_snapshot();
		$this->assertCount( 1, $snapshot['screens'] );
		$this->assertSame( 'safe-screen', $snapshot['screens'][0]['id'] );
		$this->assertSame( 'unknown', $snapshot['screens'][0]['assets'][0]['source'] );
		$this->assertStringNotContainsString( 'secret', wp_json_encode( $snapshot ) );
		$this->assertStringNotContainsString( '?', wp_json_encode( $snapshot ) );
	}

	public function test_clear_requires_capability_and_nonce_then_removes_snapshot() {
		$this->enable_audit();
		AdminAssetAudit::record_sample( 'dashboard', [], 1700000000 );
		$GLOBALS['perform_test_current_user_can']['manage_options'] = false;
		$this->run_clear();
		$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );

		$GLOBALS['perform_test_current_user_can']['manage_options'] = true;
		$GLOBALS['perform_test_nonce_valid']                        = true;
		$_POST['nonce'] = 'valid';
		$this->run_clear();
		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( [], $GLOBALS['perform_test_json_response']['data']['snapshot']['screens'] );
	}

	private function enable_audit() {
		$GLOBALS['perform_test_options']['perform_settings'] = [ 'enable_admin_asset_audit' => 1 ];
	}

	private function run_clear() {
		try {
			( new AdminAssetAuditController() )->clear();
			$this->fail( 'Expected JSON response.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
		}
	}
}
