<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\MenuCache\MenuCache;

final class Tests_Menu_Cache extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'enable_navigation_menu_cache' => 1,
			],
		];
		unset( $GLOBALS['perform_test_filters'], $GLOBALS['perform_test_is_block_theme'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['perform_test_filters'], $GLOBALS['perform_test_is_block_theme'] );
	}

	public function test_menu_cache_does_not_load_for_block_themes_by_default() {
		$GLOBALS['perform_test_is_block_theme'] = true;

		$menu_cache = new MenuCache();

		$this->assertFalse( $menu_cache->should_load() );
	}

	public function test_menu_cache_filter_can_opt_in_hybrid_block_themes() {
		$GLOBALS['perform_test_is_block_theme'] = true;
		$GLOBALS['perform_test_filters']        = [
			'perform_menu_cache_supports_current_theme' => true,
		];

		$menu_cache = new MenuCache();

		$this->assertTrue( $menu_cache->should_load() );
	}
}
