<?php

use PHPUnit\Framework\TestCase;
use Perform\Modules\Cache\PageCache;

final class Tests_Page_Cache extends TestCase {
	protected function setUp(): void {
		$GLOBALS['perform_test_transients']              = [
			'perform_cache_lock_test' => 'expected-token',
		];
		$GLOBALS['perform_test_options']                 = [];
		$GLOBALS['perform_test_filters']                 = [];
		$GLOBALS['perform_test_home_url']                = 'https://example.com';
		$GLOBALS['perform_test_remote_get_map']          = [];
		$GLOBALS['perform_test_remote_get_calls']        = [];
		$GLOBALS['perform_test_remote_post_calls']       = [];
		$GLOBALS['perform_test_actions']                 = [];
		$GLOBALS['perform_test_scheduled_events']        = [];
		$GLOBALS['perform_test_scheduled_single_events'] = [];
		$GLOBALS['perform_test_permalinks']              = [];
		$GLOBALS['perform_test_post_type_archives']      = [];
		$GLOBALS['perform_test_taxonomies']              = [];
		$GLOBALS['perform_test_post_terms']              = [];
		$GLOBALS['perform_test_terms']                   = [];
		$GLOBALS['perform_test_terms_by_tt_id']          = [];
		$GLOBALS['perform_test_comments']                = [];
		$GLOBALS['perform_test_term_object_ids']         = [];
		$GLOBALS['perform_test_blog_id']                 = 1;
		$_COOKIE                   = [];
		$_GET                      = [];
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/';
		unset( $_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] );
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['perform_test_filters'],
			$GLOBALS['perform_test_home_url'],
			$GLOBALS['perform_test_options'],
			$GLOBALS['perform_test_remote_get_calls'],
			$GLOBALS['perform_test_remote_get_map'],
			$GLOBALS['perform_test_remote_post_response'],
			$GLOBALS['perform_test_transients'],
			$GLOBALS['perform_test_actions'],
			$GLOBALS['perform_test_remote_post_calls'],
			$GLOBALS['perform_test_scheduled_events'],
			$GLOBALS['perform_test_scheduled_single_events'],
			$GLOBALS['perform_test_permalinks'],
			$GLOBALS['perform_test_post_type_archives'],
			$GLOBALS['perform_test_taxonomies'],
			$GLOBALS['perform_test_post_terms'],
			$GLOBALS['perform_test_terms'],
			$GLOBALS['perform_test_terms_by_tt_id'],
			$GLOBALS['perform_test_comments'],
			$GLOBALS['perform_test_term_object_ids'],
			$GLOBALS['perform_test_blog_id'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_nonce_valid'],
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
				'enable_page_cache'    => true,
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
				'enable_page_cache'         => true,
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

	public function test_registers_lifecycle_hooks_even_when_page_cache_is_disabled() {
		$page_cache = new PageCache();
		$this->assertTrue( $page_cache->should_load() );
		$page_cache->register();

		$hooks = array_column( $GLOBALS['perform_test_actions'], 'hook' );
		foreach ( [ 'pre_post_update', 'before_delete_post', 'transition_post_status', 'transition_comment_status', 'set_object_terms', 'edited_term', 'updated_term_meta', 'wp_update_nav_menu', 'wp_update_nav_menu_item', 'switch_theme', 'customize_save_after', 'updated_option', 'perform_cache_cleanup_event' ] as $hook ) {
			$this->assertContains( $hook, $hooks );
		}
	}

	public function test_disabled_cache_does_not_start_stats_or_preload_work() {
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_options'] = [ 'perform_settings' => [ 'enable_cache_preload' => true ] ];
		$page_cache->maybe_serve_cache();
		$page_cache->maybe_schedule_events();
		$page_cache->seed_preload_queue_from_sitemap_and_logs();

		$this->assertSame( 0.0, $this->get_private_property( $page_cache, 'request_start' ) );
		$this->assertSame( [], $GLOBALS['perform_test_scheduled_single_events'] );
	}

	public function test_targeted_purge_removes_old_post_url_but_keeps_unaffected_cache_entry() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$GLOBALS['perform_test_permalinks'][7] = 'https://example.com/old-post/';
		$page_cache->capture_post_urls_before_removal( 7 );
		$this->write_cached_url( $page_cache, 'https://example.com/old-post/' );
		$this->write_cached_url( $page_cache, 'https://example.com/unaffected/' );

		$GLOBALS['perform_test_permalinks'][7] = 'https://example.com/new-post/';
		$page_cache->purge_related_urls_for_deleted_post( 7 );

		$this->assertFalse( $this->cache_file_exists( $page_cache, 'https://example.com/old-post/' ) );
		$this->assertTrue( $this->cache_file_exists( $page_cache, 'https://example.com/unaffected/' ) );
	}

	public function test_removed_term_url_is_purged_with_current_post_urls() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$GLOBALS['perform_test_terms_by_tt_id'][12] = (object) [ 'link' => 'https://example.com/old-term/' ];
		$this->write_cached_url( $page_cache, 'https://example.com/old-term/' );

		$page_cache->purge_related_urls_for_object_terms( 7, [], [], 'category', false, [ 12 ] );

		$this->assertFalse( $this->cache_file_exists( $page_cache, 'https://example.com/old-term/' ) );
	}

	public function test_term_metadata_purges_bounded_affected_post_urls() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$GLOBALS['perform_test_term_object_ids'][4] = [ 8 ];
		$GLOBALS['perform_test_terms'][4]           = (object) [
			'taxonomy' => 'category',
			'link'     => 'https://example.com/term/',
		];
		$GLOBALS['perform_test_permalinks'][8]      = 'https://example.com/affected-post/';
		$this->write_cached_url( $page_cache, 'https://example.com/affected-post/' );

		$page_cache->purge_related_urls_for_term_meta( 1, 4 );

		$this->assertFalse( $this->cache_file_exists( $page_cache, 'https://example.com/affected-post/' ) );
	}

	public function test_global_rotation_rejects_stale_write_and_is_scoped_to_current_blog() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$GLOBALS['perform_test_options'] = [ 'perform_settings' => [ 'enable_page_cache' => true ] ];
		$this->set_private_property( $page_cache, 'request_generation', 1 );
		$this->set_private_property( $page_cache, 'should_write_cache', true );
		$this->set_private_property( $page_cache, 'current_cache_key', 'stale-write' );
		$this->set_private_property( $page_cache, 'current_url', 'https://example.com/stale-write/' );
		$page_cache->purge_site_cache();
		$page_cache->store_cache( '<html><body>stale</body></html>' );

		$this->assertSame( 2, $GLOBALS['perform_test_options']['perform_cache_generation_1'] );
		$this->assertFalse( file_exists( $cache_dir . 'site-1/generation-1/stale-write.html' ) );
		$GLOBALS['perform_test_blog_id'] = 2;
		$this->assertSame( 1, $this->invoke_private( $page_cache, 'get_cache_generation' ) );
	}

	public function test_cleanup_removes_only_a_bounded_obsolete_generation_batch() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cleanup_batch_size', 1 );
		$GLOBALS['perform_test_options']['perform_cache_generation_1'] = 2;
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		file_put_contents( $cache_dir . 'site-1/generation-1/one.html', 'one' );
		file_put_contents( $cache_dir . 'site-1/generation-1/two.html', 'two' );

		$page_cache->cleanup_obsolete_generations();

		$this->assertCount( 1, glob( $cache_dir . 'site-1/generation-1/*' ) );
		$this->assertSame( 'perform_cache_cleanup_event', $GLOBALS['perform_test_scheduled_single_events'][0]['hook'] );
	}

	public function test_cleanup_removes_legacy_root_cache_files_only() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		file_put_contents( $cache_dir . str_repeat( 'a', 32 ) . '.html', 'legacy' );
		file_put_contents( $cache_dir . str_repeat( 'a', 32 ) . '.meta.json', '{}' );
		file_put_contents( $cache_dir . 'keep.txt', 'keep' );

		$page_cache->cleanup_obsolete_generations();

		$this->assertFileDoesNotExist( $cache_dir . str_repeat( 'a', 32 ) . '.html' );
		$this->assertFileDoesNotExist( $cache_dir . str_repeat( 'a', 32 ) . '.meta.json' );
		$this->assertFileExists( $cache_dir . 'keep.txt' );
	}

	public function test_cloudflare_global_purge_uses_tracked_urls_in_bounded_batches() {
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_options'] = [
			'perform_settings'                 => [
				'enable_cloudflare_cache_sync' => true,
				'cloudflare_zone_id'           => 'zone',
				'cloudflare_api_token'         => 'token',
			],
			'perform_cache_cloudflare_queue_1' => [ 'https://example.com/a/', 'https://example.com/b/', 'https://example.com/c/' ],
		];
		$this->set_private_property( $page_cache, 'cloudflare_batch_size', 2 );
		$GLOBALS['perform_test_remote_post_response'] = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"success":true}',
		];
		$page_cache->run_cloudflare_purge_batch();

		$this->assertCount( 1, $GLOBALS['perform_test_remote_post_calls'] );
		$this->assertSame( [ 'https://example.com/a/', 'https://example.com/b/' ], json_decode( $GLOBALS['perform_test_remote_post_calls'][0]['args']['body'], true )['files'] );
		$this->assertSame( [ 'https://example.com/c/' ], $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['urls'] );
	}

	public function test_cloudflare_failed_batch_has_bounded_retries() {
		$page_cache                                   = new PageCache();
		$GLOBALS['perform_test_options']              = [
			'perform_settings'                 => [
				'enable_cloudflare_cache_sync' => true,
				'cloudflare_zone_id'           => 'zone',
				'cloudflare_api_token'         => 'token',
			],
			'perform_cache_cloudflare_queue_1' => [ 'https://example.com/a/' ],
		];
		$GLOBALS['perform_test_remote_post_response'] = new WP_Error( 'request_failed', 'No route' );
		$this->set_private_property( $page_cache, 'cloudflare_max_retries', 2 );

		$page_cache->run_cloudflare_purge_batch();
		$this->assertSame( [ 'https://example.com/a/' ], $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['urls'] );
		$page_cache->run_cloudflare_purge_batch();
		$this->assertTrue( $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['failed'] );
	}

	public function test_global_purge_queues_one_metadata_source_per_generation() {
		$page_cache = new PageCache();
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$this->invoke_private_with_arguments( $page_cache, 'queue_cloudflare_tracked_urls', [ null, 2 ] );
		$this->invoke_private_with_arguments( $page_cache, 'queue_cloudflare_tracked_urls', [ null, 2 ] );
		$this->assertCount( 1, $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] );
		$this->assertSame( 'metadata', $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['source'] );
	}

	public function test_requeueing_a_failed_metadata_source_resets_current_credentials() {
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_options'] = [
			'perform_settings'                 => [
				'enable_cloudflare_cache_sync' => true,
				'cloudflare_zone_id'           => 'zone',
				'cloudflare_api_token'         => 'token',
			],
			'perform_cache_cloudflare_queue_1' => [
				[
					'source'      => 'metadata',
					'generation'  => 2,
					'cursor'      => 0,
					'zone_id'     => 'zone',
					'fingerprint' => md5( 'zone|token' ),
					'attempts'    => 3,
					'failed'      => true,
				],
			],
		];

		$this->invoke_private_with_arguments( $page_cache, 'queue_cloudflare_tracked_urls', [ null, 2 ] );
		$record = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0];
		$this->assertFalse( $record['failed'] );
		$this->assertSame( 0, $record['attempts'] );
	}

	public function test_settings_disable_queues_old_metadata_and_still_purges_edge() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		file_put_contents( $cache_dir . 'site-1/generation-1/a.meta.json', wp_json_encode( [ 'url' => 'https://example.com/a/' ] ) );
		$old = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$new = [
			'enable_cloudflare_cache_sync' => false,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$GLOBALS['perform_test_options']['perform_settings'] = $old;
		$this->invoke_private_with_arguments( $page_cache, 'capture_cloudflare_settings_before_update', [ $new, $old, 'perform_settings' ] );
		$GLOBALS['perform_test_options']['perform_settings'] = $new;
		$GLOBALS['perform_test_remote_post_response']        = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"success":true}',
		];

		$page_cache->run_cloudflare_purge_batch();
		$this->assertCount( 1, $GLOBALS['perform_test_remote_post_calls'] );
		$this->assertTrue( $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['enabled'] );
	}

	public function test_settings_credential_change_keeps_old_source_as_residual() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		file_put_contents( $cache_dir . 'site-1/generation-1/a.meta.json', wp_json_encode( [ 'url' => 'https://example.com/a/' ] ) );
		$old = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'old-zone',
			'cloudflare_api_token'         => 'old-token',
		];
		$new = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'new-zone',
			'cloudflare_api_token'         => 'new-token',
		];
		$GLOBALS['perform_test_options']['perform_settings'] = $old;
		$this->invoke_private_with_arguments( $page_cache, 'capture_cloudflare_settings_before_update', [ $new, $old, 'perform_settings' ] );
		$GLOBALS['perform_test_options']['perform_settings'] = $new;
		$page_cache->run_cloudflare_purge_batch();
		$this->invoke_private_with_arguments( $page_cache, 'queue_cloudflare_tracked_urls', [ $new, 1 ] );

		$record = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0];
		$this->assertCount( 1, $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] );
		$this->assertTrue( $record['credential_residual'] );
		$this->assertSame( md5( 'old-zone|old-token' ), $record['fingerprint'] );
	}

	public function test_metadata_inventory_drains_in_bounded_batches_without_tokens() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cloudflare_batch_size', 1 );
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$directory = $cache_dir . 'site-1/generation-1/';
		mkdir( $directory, 0777, true );
		file_put_contents( $directory . 'bad.meta.json', '{not-json' );
		foreach ( [ 'a', 'b', 'c' ] as $slug ) {
			file_put_contents( $directory . $slug . '.meta.json', wp_json_encode( [ 'url' => 'https://example.com/' . $slug . '/' ] ) );
		}
		$GLOBALS['perform_test_remote_post_response'] = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"success":true}',
		];

		$this->invoke_private_with_arguments( $page_cache, 'queue_cloudflare_tracked_urls', [ null, 1 ] );
		$record = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0];
		$this->assertSame( 'metadata', $record['source'] );
		$this->assertArrayNotHasKey( 'cloudflare_api_token', $record );
		$this->assertStringNotContainsString( 'token', wp_json_encode( $record ) );

		for ( $run = 0; $run < 5; $run++ ) {
			$page_cache->run_cloudflare_purge_batch();
		}

		$purged_urls = array_map(
			static function ( $call ) {
					return json_decode( $call['args']['body'], true )['files'][0];
			},
			$GLOBALS['perform_test_remote_post_calls']
		);
		sort( $purged_urls );
		$this->assertSame( [ 'https://example.com/a/', 'https://example.com/b/', 'https://example.com/c/' ], $purged_urls );
		$this->assertSame( [], $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] );
	}

	public function test_metadata_cursor_is_committed_only_after_cloudflare_success() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cloudflare_batch_size', 1 );
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		file_put_contents( $cache_dir . 'site-1/generation-1/a.meta.json', wp_json_encode( [ 'url' => 'https://example.com/a/' ] ) );
		$this->invoke_private_with_arguments( $page_cache, 'queue_cloudflare_tracked_urls', [ null, 1 ] );
		$GLOBALS['perform_test_remote_post_response'] = new WP_Error( 'request_failed', 'No route' );

		$page_cache->run_cloudflare_purge_batch();
		$this->assertSame( 0, $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['cursor'] );
		$first                                        = json_decode( $GLOBALS['perform_test_remote_post_calls'][0]['args']['body'], true )['files'];
		$GLOBALS['perform_test_remote_post_response'] = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"success":true}',
		];
		$page_cache->run_cloudflare_purge_batch();
		$second = json_decode( $GLOBALS['perform_test_remote_post_calls'][1]['args']['body'], true )['files'];

		$this->assertSame( $first, $second );
		$this->assertGreaterThan( 0, $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['cursor'] );
	}

	public function test_invalid_metadata_scan_advances_in_bounded_runs_without_retiring() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cloudflare_metadata_scan_limit', 2 );
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		foreach ( [ 'one.txt', 'two.txt', 'three.txt' ] as $file ) {
			file_put_contents( $cache_dir . 'site-1/generation-1/' . $file, 'not metadata' );
		}
		$this->invoke_private_with_arguments( $page_cache, 'queue_cloudflare_tracked_urls', [ null, 1 ] );

		$page_cache->run_cloudflare_purge_batch();
		$this->assertNotEmpty( $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] );
		$this->assertGreaterThan( 0, $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['cursor'] );
		$this->assertSame( [], $GLOBALS['perform_test_remote_post_calls'] );
	}

	public function test_targeted_cloudflare_failure_queues_a_token_free_retry() {
		$page_cache = new PageCache();
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$GLOBALS['perform_test_remote_post_response']        = new WP_Error( 'request_failed', 'No route' );

		$this->invoke_private_with_argument( $page_cache, 'purge_cloudflare_url', 'https://example.com/a/' );

		$record = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0];
		$this->assertSame( [ 'https://example.com/a/' ], $record['urls'] );
		$this->assertArrayNotHasKey( 'cloudflare_api_token', $record );
		$this->assertSame( 'perform_cache_cloudflare_purge_event', $GLOBALS['perform_test_scheduled_single_events'][0]['hook'] );
	}

	public function test_inline_cloudflare_retries_are_chunked_and_schedule_once() {
		$page_cache = new PageCache();
		$this->set_private_property( $page_cache, 'cloudflare_batch_size', 2 );
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$urls = [ 'https://example.com/a/', 'https://example.com/b/', 'https://example.com/c/', 'https://example.com/d/', 'https://example.com/e/' ];
		$this->invoke_private_with_argument( $page_cache, 'queue_cloudflare_inline_urls', $urls );
		$this->invoke_private_with_argument( $page_cache, 'queue_cloudflare_inline_urls', [ 'https://example.com/f/' ] );

		$records = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'];
		$this->assertCount( 3, $records );
		$this->assertSame(
			[ 2, 2, 2 ],
			array_map(
				static function ( $record ) {
					return count( $record['urls'] );
				},
				$records
			)
		);
		$this->assertCount( 1, $GLOBALS['perform_test_scheduled_single_events'] );
	}

	public function test_cleanup_waits_for_metadata_inventory_then_removes_retired_files() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$GLOBALS['perform_test_options']['perform_cache_generation_1'] = 2;
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		file_put_contents( $cache_dir . 'site-1/generation-1/retired.meta.json', '{}' );
		$GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] = [
			[
				'source'     => 'metadata',
				'generation' => 1,
				'cursor'     => 0,
				'failed'     => false,
			],
		];

		$page_cache->cleanup_obsolete_generations();
		$this->assertFileExists( $cache_dir . 'site-1/generation-1/retired.meta.json' );
		$this->assertSame( 'perform_cache_cleanup_event', $GLOBALS['perform_test_scheduled_single_events'][0]['hook'] );

		$GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] = [];
		$page_cache->cleanup_obsolete_generations();
		$this->assertFileDoesNotExist( $cache_dir . 'site-1/generation-1/retired.meta.json' );
	}

	public function test_cleanup_schedules_again_when_legacy_files_exactly_exhaust_budget() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cleanup_batch_size', 1 );
		$GLOBALS['perform_test_options']['perform_cache_generation_1'] = 2;
		file_put_contents( $cache_dir . str_repeat( 'a', 32 ) . '.html', 'legacy' );
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		file_put_contents( $cache_dir . 'site-1/generation-1/retired.html', 'retired' );

		$page_cache->cleanup_obsolete_generations();
		$this->assertFileExists( $cache_dir . 'site-1/generation-1/retired.html' );
		$this->assertSame( 'perform_cache_cleanup_event', $GLOBALS['perform_test_scheduled_single_events'][0]['hook'] );
	}

	public function test_empty_metadata_source_is_retired_without_rescheduling() {
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_options'] = [
			'perform_settings'                 => [
				'enable_cloudflare_cache_sync' => true,
				'cloudflare_zone_id'           => 'zone',
				'cloudflare_api_token'         => 'token',
			],
			'perform_cache_cloudflare_queue_1' => [
				[
					'source'      => 'metadata',
					'generation'  => 99,
					'cursor'      => 0,
					'zone_id'     => 'zone',
					'fingerprint' => md5( 'zone|token' ),
					'attempts'    => 0,
					'failed'      => false,
				],
			],
		];

		$page_cache->run_cloudflare_purge_batch();
		$this->assertSame( [], $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] );
		$this->assertSame( [], $GLOBALS['perform_test_scheduled_single_events'] );
	}

	public function test_source_credential_mismatch_is_visible_and_nonfatal() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		file_put_contents( $cache_dir . 'site-1/generation-1/a.meta.json', wp_json_encode( [ 'url' => 'https://example.com/a/' ] ) );
		$GLOBALS['perform_test_options'] = [
			'perform_settings'                 => [
				'enable_cloudflare_cache_sync' => true,
				'cloudflare_zone_id'           => 'new-zone',
				'cloudflare_api_token'         => 'new-token',
			],
			'perform_cache_cloudflare_queue_1' => [
				[
					'source'      => 'metadata',
					'generation'  => 1,
					'cursor'      => 0,
					'zone_id'     => 'old-zone',
					'fingerprint' => md5( 'old-zone|old-token' ),
					'attempts'    => 0,
					'failed'      => false,
				],
			],
		];

		$page_cache->run_cloudflare_purge_batch();
		$this->assertTrue( $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['failed'] );
		$this->assertSame( 1, $GLOBALS['perform_test_options']['perform_cache_cloudflare_credential_residual_1'] );
	}

	public function test_acknowledgement_retires_only_credential_residual_records() {
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_options'] = [
			'perform_cache_cloudflare_credential_residual_1' => 1,
			'perform_cache_cloudflare_queue_1' => [
				[
					'urls'                => [ 'https://example.com/old/' ],
					'failed'              => true,
					'credential_residual' => true,
				],
				[
					'urls'   => [ 'https://example.com/retry/' ],
					'failed' => true,
				],
			],
		];

		$this->invoke_private( $page_cache, 'acknowledge_cloudflare_credential_residuals' );
		$this->assertSame( [ 'https://example.com/retry/' ], $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['urls'] );
		$this->assertArrayNotHasKey( 'perform_cache_cloudflare_credential_residual_1', $GLOBALS['perform_test_options'] );
	}

	public function test_cloudflare_retry_requires_capability_and_nonce() {
		$page_cache = new PageCache();
		$this->assertFalse( $this->invoke_private( $page_cache, 'is_cloudflare_retry_authorized' ) );

		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']      = true;
		$this->assertTrue( $this->invoke_private( $page_cache, 'is_cloudflare_retry_authorized' ) );
	}

	public function test_cloudflare_retry_selectively_resets_current_failures_and_preserves_residuals() {
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_options'] = [
			'perform_settings'                 => [
				'enable_cloudflare_cache_sync' => true,
				'cloudflare_zone_id'           => 'zone',
				'cloudflare_api_token'         => 'token',
			],
			'perform_cache_cloudflare_queue_1' => [
				[
					'urls'        => [ 'https://example.com/retry/' ],
					'fingerprint' => md5( 'zone|token' ),
					'attempts'    => 3,
					'failed'      => true,
				],
				[
					'urls'                => [ 'https://example.com/residual/' ],
					'fingerprint'         => md5( 'old|token' ),
					'attempts'            => 3,
					'failed'              => true,
					'credential_residual' => true,
				],
				[
					'urls'        => [ 'https://example.com/other/' ],
					'fingerprint' => md5( 'other|token' ),
					'attempts'    => 3,
					'failed'      => true,
				],
			],
		];

		$this->assertSame( 1, $this->invoke_private( $page_cache, 'retry_current_cloudflare_failures' ) );
		$records = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'];
		$this->assertFalse( $records[0]['failed'] );
		$this->assertSame( 0, $records[0]['attempts'] );
		$this->assertTrue( $records[1]['failed'] );
		$this->assertTrue( $records[1]['credential_residual'] );
		$this->assertTrue( $records[2]['failed'] );
		$this->assertCount( 1, $GLOBALS['perform_test_scheduled_single_events'] );

		$this->assertSame( 0, $this->invoke_private( $page_cache, 'retry_current_cloudflare_failures' ) );
		$this->assertCount( 1, $GLOBALS['perform_test_scheduled_single_events'] );
	}

	public function test_cloudflare_status_separates_retryable_failures_from_residuals() {
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_options'] = [
			'perform_settings'                 => [
				'cloudflare_zone_id'   => 'zone',
				'cloudflare_api_token' => 'token',
			],
			'perform_cache_cloudflare_queue_1' => [
				[
					'fingerprint' => md5( 'zone|token' ),
					'failed'      => true,
				],
				[
					'fingerprint'         => md5( 'old|token' ),
					'failed'              => true,
					'credential_residual' => true,
				],
			],
		];

		$status = $this->invoke_private( $page_cache, 'get_cloudflare_queue_status' );
		$this->assertSame( 1, $status['retryable_failed'] );
		$this->assertSame( 1, $status['credential_residuals'] );
	}

	public function test_manual_purge_requires_manage_options() {
		$page_cache = new PageCache();
		$this->expectException( RuntimeException::class );
		$page_cache->handle_manual_purge();
	}

	public function test_manual_purge_authorization_requires_valid_nonce() {
		$page_cache                               = new PageCache();
		$GLOBALS['perform_test_current_user_can'] = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']      = true;

		$this->assertTrue( $this->invoke_private( $page_cache, 'is_manual_purge_authorized' ) );
	}

	private function set_private_property( PageCache $page_cache, string $property_name, $value ): void {
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

	private function invoke_private( PageCache $page_cache, string $method ) {
		$reflection = new ReflectionMethod( $page_cache, $method );
		$reflection->setAccessible( true );
		return $reflection->invoke( $page_cache );
	}

	private function temporary_cache_dir(): string {
		$directory = sys_get_temp_dir() . '/perform-page-cache-' . uniqid( '', true ) . '/';
		mkdir( $directory, 0777, true );
		return $directory;
	}

	private function write_cached_url( PageCache $page_cache, string $url ): void {
		$key  = $this->invoke_private_with_argument( $page_cache, 'get_cache_key_for_url', $url );
		$path = $this->invoke_private_with_argument( $page_cache, 'get_body_file_path', $key );
		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0777, true );
		}
		file_put_contents( $path, 'cached' );
		file_put_contents( str_replace( '.html', '.meta.json', $path ), '{}' );
	}

	private function cache_file_exists( PageCache $page_cache, string $url ): bool {
		$key  = $this->invoke_private_with_argument( $page_cache, 'get_cache_key_for_url', $url );
		$path = $this->invoke_private_with_argument( $page_cache, 'get_body_file_path', $key );
		return file_exists( $path );
	}

	private function invoke_private_with_argument( PageCache $page_cache, string $method, $argument ) {
		$reflection = new ReflectionMethod( $page_cache, $method );
		$reflection->setAccessible( true );
		return $reflection->invoke( $page_cache, $argument );
	}

	private function invoke_private_with_arguments( PageCache $page_cache, string $method, array $arguments ) {
		$reflection = new ReflectionMethod( $page_cache, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $page_cache, $arguments );
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
