<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Assets\AssetsManager;

final class Tests_Assets_Manager extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['perform_test_get_option_counts'] = [];
		$GLOBALS['perform_test_options']           = [];
		$GLOBALS['perform_test_current_filter']    = 'script_loader_src';
		$GLOBALS['perform_test_queried_object_id'] = 42;
		$GLOBALS['perform_test_the_id']            = 0;
		$GLOBALS['perform_test_post_type']         = 'post';
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_get_option_counts'],
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_current_filter'],
			$GLOBALS['perform_test_queried_object_id'],
			$GLOBALS['perform_test_the_id'],
			$GLOBALS['perform_test_post_type'],
			$GLOBALS['perform_test_is_front_page'],
			$GLOBALS['perform_test_is_home']
		);

		parent::tearDown();
	}

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

	public function test_current_page_disabled_asset_uses_cached_options() {
		$GLOBALS['perform_test_options'] = [
			'perform_assets_manager_options' => [
				'disabled' => [
					'js' => [
						'example-handle' => [
							'current' => [ 42 ],
						],
					],
				],
			],
		];

		$manager = new AssetsManager();

		$this->assertFalse( $manager->dequeue_assets( 'https://example.com/wp-content/plugins/example/app.js', 'example-handle' ) );
		$this->assertFalse( $manager->dequeue_assets( 'https://example.com/wp-content/plugins/example/app.js', 'example-handle' ) );
		$this->assertSame( 1, $GLOBALS['perform_test_get_option_counts']['perform_assets_manager_options'] );
	}

	public function test_current_page_enabled_exception_preserves_asset() {
		$GLOBALS['perform_test_options'] = [
			'perform_assets_manager_options' => [
				'disabled' => [
					'js' => [
						'example-handle' => [
							'everywhere' => 1,
						],
					],
				],
				'enabled'  => [
					'js' => [
						'example-handle' => [
							'current' => [ 42 ],
						],
					],
				],
			],
		];

		$manager = new AssetsManager();
		$src     = 'https://example.com/wp-content/plugins/example/app.js';

		$this->assertSame( $src, $manager->dequeue_assets( $src, 'example-handle' ) );
	}

	public function test_group_disabled_rule_matches_asset_source_group() {
		$GLOBALS['perform_test_options'] = [
			'perform_assets_manager_options' => [
				'disabled' => [
					'plugins' => [
						'example' => [
							'everywhere' => 1,
						],
					],
				],
			],
		];

		$manager = new AssetsManager();

		$this->assertFalse( $manager->dequeue_assets( 'https://example.com/wp-content/plugins/example/app.js', 'example-handle' ) );
	}
}
