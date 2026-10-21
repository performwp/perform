<?php

use PHPUnit\Framework\TestCase;
use Perform\Admin\Settings\ClientPayload;
use Perform\Admin\Settings\Menu;
use Perform\Includes\Helpers;

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
				'enable_ssl'  => 1,
				'preconnect'  => [ 'https://one.example.com', 'https://two.example.com' ],
				'custom_keep' => 'preserved',
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

	public function test_settings_save_ignores_unknown_scalar_and_nested_array_values() {
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_ssl'  => 0,
			'custom_keep' => 'preserved',
		];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => wp_json_encode(
				[
					'enable_ssl'     => true,
					'unknown_scalar' => 'do-not-store',
					'unknown_nested' => [ 'attempt' => [ 'to' => 'store' ] ],
				]
			),
		];

		$this->save_settings();

		$this->assertSame( 1, $GLOBALS['perform_test_options']['perform_settings']['enable_ssl'] );
		$this->assertSame( 'preserved', $GLOBALS['perform_test_options']['perform_settings']['custom_keep'] );
		$this->assertArrayNotHasKey( 'unknown_scalar', $GLOBALS['perform_test_options']['perform_settings'] );
		$this->assertArrayNotHasKey( 'unknown_nested', $GLOBALS['perform_test_options']['perform_settings'] );
	}

	public function test_settings_save_rejects_nested_array_for_registered_textarea() {
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'preconnect' => [ 'https://existing.example.com' ],
		];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => wp_json_encode(
				[
					'preconnect' => [ 'https://valid.example.com', [ 'nested' => 'value' ] ],
				]
			),
		];

		$this->assert_invalid_save();

		$this->assertSame( [ 'https://existing.example.com' ], $GLOBALS['perform_test_options']['perform_settings']['preconnect'] );
	}

	public function test_settings_save_rejects_invalid_select_value() {
		$GLOBALS['perform_test_options']['perform_settings'] = [ 'page_cache_ttl' => '3600' ];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => wp_json_encode( [ 'page_cache_ttl' => 'never' ] ),
		];

		$this->assert_invalid_save();

		$this->assertSame( [ 'page_cache_ttl' => '3600' ], $GLOBALS['perform_test_options']['perform_settings'] );
	}

	public function test_settings_save_rejects_malformed_json_without_reflecting_input() {
		$GLOBALS['perform_test_options']['perform_settings'] = [ 'enable_ssl' => 0 ];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => '{"cloudflare_api_token":"super-secret",',
		];

		try {
			( new Menu() )->save_settings();
			$this->fail( 'Expected the JSON response to end the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
			$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
			$this->assertSame( 'Settings data is invalid. Please try again.', $GLOBALS['perform_test_json_response']['data']['message'] );
			$this->assertStringNotContainsString( 'super-secret', $GLOBALS['perform_test_json_response']['data']['message'] );
		}

		$this->assertSame( [ 'enable_ssl' => 0 ], $GLOBALS['perform_test_options']['perform_settings'] );
	}

	public function test_settings_save_bounds_oversized_cache_bypass_list() {
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'cache_bypass_exact_paths' => [ '/existing' ],
		];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => wp_json_encode(
				[
					'cache_bypass_exact_paths' => array_map(
						static function ( $index ) {
							return '/private-' . $index;
						},
						range( 1, 125 )
					),
				]
			),
		];

		$this->assert_invalid_save( 'List settings are limited to 100 entries. Please reduce the list and try again.' );

		$this->assertSame( [ '/existing' ], $GLOBALS['perform_test_options']['perform_settings']['cache_bypass_exact_paths'] );
	}

	public function test_settings_save_preserves_unsubmitted_oversized_existing_lists() {
		$existing_list = array_map(
			static function ( $index ) {
				return '/existing-' . $index;
			},
			range( 1, 101 )
		);

		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_ssl'                   => 0,
			'cache_bypass_exact_paths'     => $existing_list,
			'dns_prefetch'                 => $existing_list,
			'cache_bypass_path_prefixes'   => [],
			'cache_bypass_query_params'    => [],
			'cache_bypass_cookie_names'    => [],
			'cache_bypass_cookie_prefixes' => [],
		];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => wp_json_encode( [ 'enable_ssl' => true ] ),
		];

		$this->save_settings();

		$this->assertSame( $existing_list, $GLOBALS['perform_test_options']['perform_settings']['cache_bypass_exact_paths'] );
		$this->assertSame( $existing_list, $GLOBALS['perform_test_options']['perform_settings']['dns_prefetch'] );
	}

	public function test_full_client_payload_preserves_unchanged_oversized_legacy_list() {
		$existing_list = array_map(
			static function ( $index ) {
				return '/existing-' . $index;
			},
			range( 1, 101 )
		);
		$settings      = [
			'enable_ssl'               => 0,
			'cache_bypass_exact_paths' => $existing_list,
		];

		$GLOBALS['perform_test_options']['perform_settings'] = $settings;
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$payload               = $this->build_full_client_payload( $settings );
		$payload['enable_ssl'] = true;
		$_POST                 = [
			'nonce' => 'valid',
			'data'  => wp_json_encode( $payload ),
		];

		$this->save_settings();

		$this->assertSame( 1, $GLOBALS['perform_test_options']['perform_settings']['enable_ssl'] );
		$this->assertSame( $existing_list, $GLOBALS['perform_test_options']['perform_settings']['cache_bypass_exact_paths'] );
	}

	public function test_fresh_full_client_save_persists_runtime_select_defaults() {
		$GLOBALS['perform_test_nonce_valid'] = true;
		$payload                             = $this->build_full_client_payload( [] );
		$payload['enable_ssl']               = true;
		$_POST                               = [
			'nonce' => 'valid',
			'data'  => wp_json_encode( $payload ),
		];

		$this->save_settings();

		$this->assertSame( '3600', $GLOBALS['perform_test_options']['perform_settings']['page_cache_ttl'] );
		$this->assertSame( '21600', $GLOBALS['perform_test_options']['perform_settings']['page_cache_swr_ttl'] );
		$this->assertSame( '1200', $GLOBALS['perform_test_options']['perform_settings']['cache_slow_request_threshold_ms'] );
	}

	public function test_settings_save_persists_valid_cache_ttl() {
		$GLOBALS['perform_test_options']['perform_settings'] = [ 'page_cache_ttl' => '300' ];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => wp_json_encode( [ 'page_cache_ttl' => '3600' ] ),
		];

		$this->save_settings();

		$this->assertSame( '3600', $GLOBALS['perform_test_options']['perform_settings']['page_cache_ttl'] );
	}

	public function test_settings_save_preserves_masked_cloudflare_secret_and_valid_values() {
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'cloudflare_api_token' => 'existing-secret',
		];
		$GLOBALS['perform_test_nonce_valid']                 = true;
		$_POST = [
			'nonce' => 'valid',
			'data'  => wp_json_encode(
				[
					'enable_page_cache'         => true,
					'cloudflare_api_token'      => '__PERFORM_MASKED_SECRET__',
					'dns_prefetch'              => "//one.example.com\n//two.example.com",
					'cache_bypass_query_params' => "preview_token\nab_variant",
				]
			),
		];

		$this->save_settings();

		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame( 1, $GLOBALS['perform_test_options']['perform_settings']['enable_page_cache'] );
		$this->assertSame( 'existing-secret', $GLOBALS['perform_test_options']['perform_settings']['cloudflare_api_token'] );
		$this->assertSame( [ '//one.example.com', '//two.example.com' ], $GLOBALS['perform_test_options']['perform_settings']['dns_prefetch'] );
		$this->assertSame( [ 'preview_token', 'ab_variant' ], $GLOBALS['perform_test_options']['perform_settings']['cache_bypass_query_params'] );
	}

	private function save_settings() {
		try {
			( new Menu() )->save_settings();
			$this->fail( 'Expected the JSON response to end the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
			$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		}
	}

	private function assert_invalid_save( $expected_message = 'Settings data is invalid. Please try again.' ) {
		try {
			( new Menu() )->save_settings();
			$this->fail( 'Expected the JSON response to end the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
			$this->assertFalse( $GLOBALS['perform_test_json_response']['success'] );
			$this->assertSame( $expected_message, $GLOBALS['perform_test_json_response']['data']['message'] );
		}
	}

	/**
	 * Build the values React sends for every registered field.
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 *
	 * @return array<string, mixed>
	 */
	private function build_full_client_payload( array $settings ) {
		$payload = ClientPayload::sanitize_for_client( $settings );

		foreach ( Helpers::get_settings_fields() as $cards ) {
			foreach ( $cards as $card ) {
				foreach ( $card['fields'] ?? [] as $field ) {
					if ( empty( $field['id'] ) || array_key_exists( $field['id'], $payload ) ) {
						continue;
					}

					if ( array_key_exists( 'default', $field ) ) {
						$payload[ $field['id'] ] = $field['default'];
					} elseif ( 'toggle' === ( $field['type'] ?? '' ) ) {
						$payload[ $field['id'] ] = false;
					} elseif ( 'select' === ( $field['type'] ?? '' ) ) {
						$payload[ $field['id'] ] = (string) ( array_key_first( $field['options'] ?? [] ) ?? '' );
					} else {
						$payload[ $field['id'] ] = '';
					}
				}
			}
		}

		return $payload;
	}
}
