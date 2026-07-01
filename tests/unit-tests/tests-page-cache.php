<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Cache\PageCache;

final class Tests_Page_Cache extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_transients']       = [
			'perform_cache_lock_test' => 'expected-token',
		];
		$GLOBALS['perform_test_options']          = [];
		$GLOBALS['perform_test_filters']          = [];
		$GLOBALS['perform_test_home_url']         = 'https://example.com';
		$GLOBALS['perform_test_remote_get_map']   = [];
		$GLOBALS['perform_test_remote_get_calls'] = [];
		$_COOKIE                                  = [];
		$_GET                                     = [];
		$_SERVER['REQUEST_METHOD']                = 'GET';
		$_SERVER['REQUEST_URI']                   = '/';
		unset( $_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_filters'],
			$GLOBALS['perform_test_home_url'],
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_remote_get_calls'],
			$GLOBALS['perform_test_remote_get_map'],
			$GLOBALS['perform_test_transients'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI'],
			$_SERVER['HTTP_X_PERFORM_CACHE_REGEN']
		);
		$_COOKIE = [];
		$_GET    = [];
	}

	public function test_internal_regeneration_requires_matching_lock_token() {
		$page_cache = new PageCache();

		$lock_key = new ReflectionProperty( $page_cache, 'lock_key' );
		$lock_key->setAccessible( true );
		$lock_key->setValue( $page_cache, 'perform_cache_lock_test' );

		$is_internal_regen_request = new ReflectionMethod( $page_cache, 'is_internal_regen_request' );
		$is_internal_regen_request->setAccessible( true );

		$_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] = '1';
		$this->assertFalse( $is_internal_regen_request->invoke( $page_cache ) );

		$_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] = 'expected-token';
		$this->assertTrue( $is_internal_regen_request->invoke( $page_cache ) );
	}

	public function test_fetch_urls_from_sitemap_limits_child_sitemap_requests_per_run() {
		$page_cache = new PageCache();

		$this->set_private_property( $page_cache, 'preload_sitemap_child_limit', 3 );
		$this->set_private_property( $page_cache, 'preload_sitemap_url_cap', 20 );

		$GLOBALS['perform_test_remote_get_map'] = [
			'https://example.com/wp-sitemap.xml' => [ 'body' => $this->build_sitemap_index_xml( 5 ) ],
			'https://example.com/sitemap-1.xml'  => [ 'body' => $this->build_urlset_xml( '/page-1', '/page-2' ) ],
			'https://example.com/sitemap-2.xml'  => [ 'body' => $this->build_urlset_xml( '/page-3', '/page-4' ) ],
			'https://example.com/sitemap-3.xml'  => [ 'body' => $this->build_urlset_xml( '/page-5', '/page-6' ) ],
			'https://example.com/sitemap-4.xml'  => [ 'body' => $this->build_urlset_xml( '/page-7', '/page-8' ) ],
			'https://example.com/sitemap-5.xml'  => [ 'body' => $this->build_urlset_xml( '/page-9', '/page-10' ) ],
		];

		$method = new ReflectionMethod( $page_cache, 'fetch_urls_from_sitemap' );
		$method->setAccessible( true );

		$this->assertSame(
			[
				'https://example.com/page-1',
				'https://example.com/page-2',
				'https://example.com/page-3',
				'https://example.com/page-4',
				'https://example.com/page-5',
				'https://example.com/page-6',
			],
			$method->invoke( $page_cache )
		);

		$this->assertSame(
			[
				'https://example.com/wp-sitemap.xml',
				'https://example.com/sitemap-1.xml',
				'https://example.com/sitemap-2.xml',
				'https://example.com/sitemap-3.xml',
			],
			array_column( $GLOBALS['perform_test_remote_get_calls'], 'url' )
		);
	}

	public function test_fetch_urls_from_sitemap_stops_after_reaching_url_cap() {
		$page_cache = new PageCache();

		$this->set_private_property( $page_cache, 'preload_sitemap_child_limit', 5 );
		$this->set_private_property( $page_cache, 'preload_sitemap_url_cap', 3 );

		$GLOBALS['perform_test_remote_get_map'] = [
			'https://example.com/wp-sitemap.xml' => [ 'body' => $this->build_sitemap_index_xml( 2 ) ],
			'https://example.com/sitemap-1.xml'  => [ 'body' => $this->build_urlset_xml( '/page-1', '/page-2', '/page-2', '/page-3', '/page-4' ) ],
			'https://example.com/sitemap-2.xml'  => [ 'body' => $this->build_urlset_xml( '/page-5' ) ],
		];

		$method = new ReflectionMethod( $page_cache, 'fetch_urls_from_sitemap' );
		$method->setAccessible( true );

		$this->assertSame(
			[
				'https://example.com/page-1',
				'https://example.com/page-2',
				'https://example.com/page-3',
			],
			$method->invoke( $page_cache )
		);

		$this->assertSame(
			[
				'https://example.com/wp-sitemap.xml',
				'https://example.com/sitemap-1.xml',
			],
			array_column( $GLOBALS['perform_test_remote_get_calls'], 'url' )
		);
	}

	public function test_seed_preload_queue_keeps_existing_queue_when_sitemap_fetch_fails() {
		$GLOBALS['perform_test_options']        = [
			'perform_settings'            => [
				'enable_cache_preload' => true,
			],
			'perform_cache_preload_queue' => [
				'https://example.com/existing-page',
			],
		];
		$GLOBALS['perform_test_remote_get_map'] = [
			'https://example.com/wp-sitemap.xml' => new WP_Error( 'http_request_failed', 'Timeout' ),
		];

		$page_cache = new PageCache();
		$page_cache->seed_preload_queue_from_sitemap_and_logs();

		$this->assertSame(
			[ 'https://example.com/existing-page' ],
			$GLOBALS['perform_test_options']['perform_cache_preload_queue']
		);
		$this->assertSame(
			[ 'https://example.com/wp-sitemap.xml' ],
			array_column( $GLOBALS['perform_test_remote_get_calls'], 'url' )
		);
	}

	public function test_configured_exact_path_bypasses_cache_without_writes() {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'cache_bypass_exact_paths' => [ '/members/dashboard' ],
			],
		];
		$_SERVER['REQUEST_URI']          = '/members/dashboard?lang=en';

		$page_cache = new PageCache();

		$this->assertFalse( $this->invoke_is_cacheable_request( $page_cache ) );
		$this->assertSame( 'path_exact', $this->get_private_property( $page_cache, 'current_bypass_reason' ) );
	}

	public function test_configured_path_prefix_bypasses_cache_without_changing_exact_behavior() {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'cache_bypass_path_prefixes' => [ '/private' ],
			],
		];
		$_SERVER['REQUEST_URI']          = '/private/report';

		$page_cache = new PageCache();

		$this->assertFalse( $this->invoke_is_cacheable_request( $page_cache ) );
		$this->assertSame( 'path_prefix', $this->get_private_property( $page_cache, 'current_bypass_reason' ) );

		$_SERVER['REQUEST_URI'] = '/public/report';
		$this->assertTrue( $this->invoke_is_cacheable_request( $page_cache ) );
	}

	public function test_configured_query_bypass_is_distinct_from_query_variation() {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'cache_separate_query_params' => 'lang',
				'cache_bypass_query_params'   => [ 'preview_token' ],
			],
		];
		$_GET                            = [
			'lang' => 'en',
		];

		$page_cache = new PageCache();
		$this->assertTrue( $this->invoke_is_cacheable_request( $page_cache ) );

		$_GET['preview_token'] = 'abc123';
		$this->assertFalse( $this->invoke_is_cacheable_request( $page_cache ) );
		$this->assertSame( 'query_key', $this->get_private_property( $page_cache, 'current_bypass_reason' ) );
	}

	public function test_configured_cookie_name_and_prefix_bypass_cache() {
		$GLOBALS['perform_test_options'] = [
			'perform_settings' => [
				'cache_bypass_cookie_names'    => [ 'membership_session' ],
				'cache_bypass_cookie_prefixes' => [ 'experiment_' ],
			],
		];

		$page_cache = new PageCache();

		$_COOKIE = [ 'membership_session' => 'secret-value' ];
		$this->assertFalse( $this->invoke_is_cacheable_request( $page_cache ) );
		$this->assertSame( 'cookie_name', $this->get_private_property( $page_cache, 'current_bypass_reason' ) );

		$_COOKIE = [ 'experiment_variant' => 'b' ];
		$this->assertFalse( $this->invoke_is_cacheable_request( $page_cache ) );
		$this->assertSame( 'cookie_prefix', $this->get_private_property( $page_cache, 'current_bypass_reason' ) );
	}

	public function test_custom_filter_can_bypass_cache_with_named_reason() {
		$GLOBALS['perform_test_filters']['perform_page_cache_bypass_reason'] = static function ( $reason, $context ) {
			return '/dynamic' === $context['path'] ? 'partner area' : $reason;
		};
		$_SERVER['REQUEST_URI'] = '/dynamic';

		$page_cache = new PageCache();

		$this->assertFalse( $this->invoke_is_cacheable_request( $page_cache ) );
		$this->assertSame( 'partner_area', $this->get_private_property( $page_cache, 'current_bypass_reason' ) );
	}

	public function test_bypass_reasons_are_aggregated_in_stats() {
		$GLOBALS['perform_test_options']                                    = [
			'perform_settings' => [
				'cache_bypass_query_params' => [ 'preview_token' ],
			],
		];
		$GLOBALS['perform_test_filters']['perform_cache_stats_sample_rate'] = 1;
		$_GET['preview_token'] = 'abc123';

		$page_cache = new PageCache();
		$page_cache->maybe_serve_cache();
		$page_cache->flush_stats();

		$this->assertSame( 1, $GLOBALS['perform_test_options']['perform_cache_stats']['bypasses'] );
		$this->assertSame( 1, $GLOBALS['perform_test_options']['perform_cache_stats']['bypass_reasons']['query_key'] );
	}

	private function set_private_property( PageCache $page_cache, string $property_name, int $value ): void {
		$property = new ReflectionProperty( $page_cache, $property_name );
		$property->setAccessible( true );
		$property->setValue( $page_cache, $value );
	}

	private function get_private_property( PageCache $page_cache, string $property_name ) {
		$property = new ReflectionProperty( $page_cache, $property_name );
		$property->setAccessible( true );

		return $property->getValue( $page_cache );
	}

	private function invoke_is_cacheable_request( PageCache $page_cache ): bool {
		$method = new ReflectionMethod( $page_cache, 'is_cacheable_request' );
		$method->setAccessible( true );

		return $method->invoke( $page_cache );
	}

	private function build_sitemap_index_xml( int $child_count ): string {
		$items = [];

		for ( $index = 1; $index <= $child_count; $index++ ) {
			$items[] = sprintf( '<sitemap><loc>https://example.com/sitemap-%d.xml</loc></sitemap>', $index );
		}

		return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex>' . implode( '', $items ) . '</sitemapindex>';
	}

	private function build_urlset_xml( string ...$paths ): string {
		$items = [];

		foreach ( $paths as $path ) {
			$items[] = '<url><loc>https://example.com' . $path . '</loc></url>';
		}

		return '<?xml version="1.0" encoding="UTF-8"?><urlset>' . implode( '', $items ) . '</urlset>';
	}
}
