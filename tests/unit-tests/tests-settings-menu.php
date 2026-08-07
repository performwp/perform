<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\Menu;

final class Tests_Settings_Menu extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_actions']          = [];
		$GLOBALS['perform_test_options_pages']    = [];
		$GLOBALS['perform_test_submenu_pages']    = [];
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_options']          = [];
		$_GET                                     = [];
		$_POST                                    = [];
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_actions'],
			$GLOBALS['perform_test_options_pages'],
			$GLOBALS['perform_test_submenu_pages'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_nonce_valid'],
			$GLOBALS['perform_test_json_response']
		);
		$_GET  = [];
		$_POST = [];
	}

	public function test_registers_one_canonical_settings_page_without_a_cache_stats_submenu() {
		$menu = new Menu();
		$menu->register_admin_menu();

		$this->assertCount( 1, $GLOBALS['perform_test_options_pages'] );
		$this->assertSame( 'perform_settings', $GLOBALS['perform_test_options_pages'][0]['menu_slug'] );
		$this->assertSame( 'manage_options', $GLOBALS['perform_test_options_pages'][0]['capability'] );
		$this->assertSame( [], $GLOBALS['perform_test_submenu_pages'] );
	}

	public function test_cache_stats_is_a_known_canonical_tab_and_unknown_tabs_fall_back() {
		$this->assertArrayHasKey( 'cache-stats', Menu::get_navigation_tabs() );
		$this->assertArrayHasKey( 'database', Menu::get_navigation_tabs() );

		$_GET['tab'] = 'cache-stats';
		$this->assertSame( 'cache-stats', Menu::get_requested_tab() );

		$_GET['tab'] = 'unknown<script>';
		$this->assertSame( 'dashboard', Menu::get_requested_tab() );
	}

	public function test_settings_save_preserves_option_name_shape_and_unposted_values() {
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_ssl'  => 0,
			'preconnect'  => [ 'https://existing.example.com' ],
			'custom_keep' => 'preserved',
		];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => wp_json_encode(
				[
					'enable_ssl' => true,
					'preconnect' => "https://one.example.com\nhttps://two.example.com",
				]
			),
		];

		try {
			( new Menu() )->save_settings();
			$this->fail( 'Expected the JSON response to end the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
			$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		}

		$this->assertSame(
			[
				'enable_ssl'                   => 1,
				'preconnect'                   => [ 'https://one.example.com', 'https://two.example.com' ],
				'custom_keep'                  => 'preserved',
				'dns_prefetch'                 => '',
				'cache_bypass_exact_paths'     => [],
				'cache_bypass_path_prefixes'   => [],
				'cache_bypass_query_params'    => [],
				'cache_bypass_cookie_names'    => [],
				'cache_bypass_cookie_prefixes' => [],
			],
			$GLOBALS['perform_test_options']['perform_settings']
		);
	}

	public function test_multiline_settings_preserve_line_boundaries() {
		$menu   = new Menu();
		$method = new ReflectionMethod( $menu, 'normalize_multiline_setting' );
		$method->setAccessible( true );

		$this->assertSame(
			[
				'https://fonts.example.com',
				'https://cdn.example.com',
			],
			$method->invoke( $menu, "https://fonts.example.com\nhttps://cdn.example.com" )
		);
	}

	public function test_existing_multiline_arrays_keep_their_storage_shape() {
		$menu   = new Menu();
		$method = new ReflectionMethod( $menu, 'normalize_multiline_setting' );
		$method->setAccessible( true );

		$this->assertSame(
			[
				'https://fonts.example.com',
				'https://cdn.example.com',
			],
			$method->invoke( $menu, [ 'https://fonts.example.com', '', 'https://cdn.example.com' ] )
		);
	}

	public function test_cache_bypass_rule_lists_are_sanitized_and_deduplicated() {
		$menu   = new Menu();
		$method = new ReflectionMethod( $menu, 'normalize_rule_list_setting' );
		$method->setAccessible( true );

		$this->assertSame(
			[
				'/private',
				'/members',
				'preview_token',
			],
			$method->invoke( $menu, " /private \n/members,preview_token\n/private" )
		);
	}
}
