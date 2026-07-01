<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Assets\AssetsManager;

final class Tests_Assets_Manager extends TestCase {
	public function test_current_object_id_lists_are_normalized_for_strict_comparisons() {
		$manager = new AssetsManager();
		$method  = new ReflectionMethod( $manager, 'normalize_object_id_list' );
		$method->setAccessible( true );

		$this->assertSame( [ 42, 7 ], $method->invoke( $manager, [ '42', 42, '7', 'invalid', 0 ] ) );
	}

	public function test_current_object_id_lookup_matches_legacy_string_ids() {
		$manager = new AssetsManager();
		$method  = new ReflectionMethod( $manager, 'has_current_object_id' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $manager, 42, [ '42' ] ) );
		$this->assertFalse( $method->invoke( $manager, 11, [ '42' ] ) );
	}

	public function test_reset_assets_manager_settings_deletes_saved_options_and_returns_clean_url() {
		$GLOBALS['perform_test_options'] = [
			'perform_assets_manager_options' => [
				'disabled' => [
					'js' => [
						'example' => [
							'everywhere' => 1,
						],
					],
				],
			],
		];
		$_SERVER['REQUEST_URI']          = '/sample-page/?perform=1&keep=1';

		$manager = new AssetsManager();
		$method  = new ReflectionMethod( $manager, 'reset_assets_manager_settings' );
		$method->setAccessible( true );

		$this->assertSame( '/sample-page/?keep=1&perform_reset=1', $method->invoke( $manager ) );
		$this->assertArrayNotHasKey( 'perform_assets_manager_options', $GLOBALS['perform_test_options'] );
	}
}
