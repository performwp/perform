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
		unset( $GLOBALS['perform_test_remote_get_callback'] );
		$GLOBALS['perform_test_remote_post_calls'] = [];
		unset( $GLOBALS['perform_test_remote_post_callback'] );
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
		$GLOBALS['perform_test_is_multisite']            = false;
		$GLOBALS['wpdb']                                 = new class() {
			public $options          = 'wp_options';
			public $conflict_once    = false;
			public $fail_updates     = false;
			public $insert_race_once = false;
			public $last_update      = '';
			public $query_count      = 0;

			public function prepare( $query, ...$args ) {
				return [ $query, $args ];
			}

			public function get_var( $prepared ) {
				if ( ! is_array( $prepared ) || false === strpos( $prepared[0], 'SELECT option_value' ) ) {
					return null;
				}
				$value = $GLOBALS['perform_test_options'][ $prepared[1][0] ] ?? null;
				return null === $value ? null : maybe_serialize( $value );
			}

			public function query( $prepared ) {
				list( $query, $args ) = $prepared;
				$this->last_update    = $query;
				++$this->query_count;
				if ( $this->fail_updates ) {
					return false;
				}
				if ( false !== strpos( $query, 'INSERT IGNORE' ) ) {
					if ( $this->insert_race_once ) {
						$this->insert_race_once                      = false;
						$GLOBALS['perform_test_options'][ $args[0] ] = [ 'https://example.com/concurrent-insert' ];
						return 0;
					}
					if ( array_key_exists( $args[0], $GLOBALS['perform_test_options'] ) ) {
						return 0;
					}
					$GLOBALS['perform_test_options'][ $args[0] ] = maybe_unserialize( $args[1] );
					return 1;
				}
				if ( $this->conflict_once ) {
					$this->conflict_once                           = false;
					$GLOBALS['perform_test_options'][ $args[1] ][] = 'https://example.com/concurrent-path';
				}
				$current = $GLOBALS['perform_test_options'][ $args[1] ] ?? null;
				if ( null === $current || maybe_serialize( $current ) !== $args[2] ) {
					return 0;
				}
				$GLOBALS['perform_test_options'][ $args[1] ] = maybe_unserialize( $args[0] );
				return 1;
			}
		};
		$_COOKIE                   = [];
		$_GET                      = [];
		$_POST                     = [];
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
			$GLOBALS['perform_test_remote_get_callback'],
			$GLOBALS['perform_test_remote_get_map'],
			$GLOBALS['perform_test_remote_post_response'],
			$GLOBALS['perform_test_transients'],
			$GLOBALS['perform_test_actions'],
			$GLOBALS['perform_test_remote_post_calls'],
			$GLOBALS['perform_test_remote_post_callback'],
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
			$GLOBALS['perform_test_is_multisite'],
			$GLOBALS['wpdb'],
			$GLOBALS['perform_test_current_user_can'],
			$GLOBALS['perform_test_nonce_valid'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI'],
			$_SERVER['HTTP_X_PERFORM_CACHE_REGEN']
		);
		$_COOKIE = [];
		$_GET    = [];
		$_POST   = [];
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

	public function test_preload_batch_preserves_urls_queued_during_a_blocking_request() {
		$first_url                       = 'https://example.com/slow-page';
		$second_url                      = 'https://example.com/next-page';
		$new_url                         = 'https://example.com/newly-invalidated';
		$GLOBALS['perform_test_options'] = [
			'perform_settings'            => [
				'enable_page_cache'    => true,
				'enable_cache_preload' => true,
			],
			'perform_cache_preload_queue' => [ $first_url, $second_url ],
		];
		$GLOBALS['perform_test_filters']['perform_cache_preload_batch_size'] = 1;
		$page_cache = new PageCache();
		$method     = new ReflectionMethod( $page_cache, 'queue_urls_for_preload' );
		$method->setAccessible( true );
		$GLOBALS['perform_test_remote_get_callback'] = static function ( $url ) use ( $method, $page_cache, $new_url, $first_url ): void {
			if ( 'https://example.com/slow-page' === $url ) {
				$method->invoke( $page_cache, [ $new_url, $first_url ] );
			}
		};

		$page_cache->run_preload_batch();

		$this->assertSame( [ $second_url, $new_url, $first_url ], $GLOBALS['perform_test_options']['perform_cache_preload_queue'] );
	}

	public function test_preload_active_claim_prevents_a_second_worker_from_processing_the_same_url() {
		$url                             = 'https://example.com/claimed-page';
		$GLOBALS['perform_test_options'] = [
			'perform_settings'            => [
				'enable_page_cache'    => true,
				'enable_cache_preload' => true,
			],
			'perform_cache_preload_queue' => [ $url ],
		];
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_remote_get_callback'] = static function () use ( $page_cache ): void {
			unset( $GLOBALS['perform_test_remote_get_callback'] );
			$page_cache->run_preload_batch();
		};

		$page_cache->run_preload_batch();

		$this->assertCount( 1, $GLOBALS['perform_test_remote_get_calls'] );
		$this->assertSame( [], $GLOBALS['perform_test_options']['perform_cache_preload_queue'] );
	}

	public function test_preload_reclaims_expired_claim_after_interrupted_worker() {
		$url                             = 'https://example.com/recovered-page';
		$GLOBALS['perform_test_options'] = [
			'perform_settings'            => [
				'enable_page_cache'    => true,
				'enable_cache_preload' => true,
			],
			'perform_cache_preload_queue' => [
				[
					'url'         => $url,
					'claim_id'    => 'interrupted-worker',
					'claim_until' => time() - 1,
				],
			],
		];
		$page_cache                      = new PageCache();

		$page_cache->run_preload_batch();

		$this->assertSame( [ $url ], array_column( $GLOBALS['perform_test_remote_get_calls'], 'url' ) );
		$this->assertSame( [], $GLOBALS['perform_test_options']['perform_cache_preload_queue'] );
	}

	public function test_preload_stale_worker_ack_does_not_remove_a_reclaimed_claim() {
		$url                             = 'https://example.com/reclaimed-page';
		$GLOBALS['perform_test_options'] = [
			'perform_settings'            => [
				'enable_page_cache'    => true,
				'enable_cache_preload' => true,
			],
			'perform_cache_preload_queue' => [ $url ],
		];
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_remote_get_callback'] = static function () use ( $url ): void {
			unset( $GLOBALS['perform_test_remote_get_callback'] );
			$GLOBALS['perform_test_options']['perform_cache_preload_queue'][0] = [
				'url'         => $url,
				'claim_id'    => 'replacement-owner',
				'claim_until' => time() + 300,
			];
		};

		$page_cache->run_preload_batch();

		$this->assertSame( 'replacement-owner', $GLOBALS['perform_test_options']['perform_cache_preload_queue'][0]['claim_id'] );
		$this->assertSame( 1, count( $GLOBALS['perform_test_remote_get_calls'] ) );
	}

	public function test_preload_acknowledgement_retries_after_database_contention_and_lease_expiry() {
		$url                             = 'https://example.com/ack-retry-page';
		$GLOBALS['perform_test_options'] = [
			'perform_settings'            => [
				'enable_page_cache'    => true,
				'enable_cache_preload' => true,
			],
			'perform_cache_preload_queue' => [ $url ],
		];
		$page_cache                      = new PageCache();
		$GLOBALS['perform_test_remote_get_callback'] = static function () use ( $url ): void {
			unset( $GLOBALS['perform_test_remote_get_callback'] );
			$GLOBALS['wpdb']->fail_updates = true;
		};

		$page_cache->run_preload_batch();
		$this->assertSame( $url, $GLOBALS['perform_test_options']['perform_cache_preload_queue'][0]['url'] );

		$GLOBALS['wpdb']->fail_updates = false;
		$GLOBALS['perform_test_options']['perform_cache_preload_queue'][0]['claim_until'] = time() - 1;
		$page_cache->run_preload_batch();

		$this->assertSame( [], $GLOBALS['perform_test_options']['perform_cache_preload_queue'] );
		$this->assertCount( 2, $GLOBALS['perform_test_remote_get_calls'] );
	}

	public function test_preload_queue_discards_malformed_entries_and_stays_bounded() {
		$GLOBALS['perform_test_options']['perform_cache_preload_queue'] = [
			123,
			[ 'unexpected' => 'shape' ],
			'https://example.com/kept-page',
		];
		$page_cache = new PageCache();
		$method     = new ReflectionMethod( $page_cache, 'queue_urls_for_preload' );
		$method->setAccessible( true );
		$method->invoke( $page_cache, [ 'https://example.com/new-page' ] );
		$this->assertSame(
			[ 'https://example.com/kept-page', 'https://example.com/new-page' ],
			$GLOBALS['perform_test_options']['perform_cache_preload_queue']
		);

		$full_queue = [];
		for ( $index = 0; $index < 2000; ++$index ) {
			$full_queue[] = 'https://example.com/page-' . $index;
		}
		$GLOBALS['perform_test_options']['perform_cache_preload_queue'] = $full_queue;
		$method->invoke( $page_cache, [ 'https://example.com/overflow-page' ] );
		$this->assertCount( 2000, $GLOBALS['perform_test_options']['perform_cache_preload_queue'] );
		$this->assertNotContains( 'https://example.com/overflow-page', $GLOBALS['perform_test_options']['perform_cache_preload_queue'] );
	}

	public function test_preload_queue_compare_and_swap_retries_after_concurrent_option_change() {
		$GLOBALS['perform_test_options']['perform_cache_preload_queue'] = [ 'https://example.com/existing' ];
		$GLOBALS['wpdb']->conflict_once                                 = true;
		$page_cache = new PageCache();
		$method     = new ReflectionMethod( $page_cache, 'queue_urls_for_preload' );
		$method->setAccessible( true );

		$method->invoke( $page_cache, [ 'https://example.com/new' ] );

		$this->assertSame(
			[ 'https://example.com/existing', 'https://example.com/concurrent-path', 'https://example.com/new' ],
			$GLOBALS['perform_test_options']['perform_cache_preload_queue']
		);
		$this->assertStringContainsString( 'BINARY option_value = BINARY', $GLOBALS['wpdb']->last_update );
	}

	public function test_preload_queue_retries_option_insert_race_without_overwriting_new_queue() {
		$GLOBALS['wpdb']->insert_race_once = true;
		$page_cache                        = new PageCache();
		$method                            = new ReflectionMethod( $page_cache, 'queue_urls_for_preload' );
		$method->setAccessible( true );

		$method->invoke( $page_cache, [ 'https://example.com/new' ] );

		$this->assertSame(
			[ 'https://example.com/concurrent-insert', 'https://example.com/new' ],
			$GLOBALS['perform_test_options']['perform_cache_preload_queue']
		);
	}

	public function test_preload_queue_mutation_reports_repeated_database_contention() {
		$GLOBALS['perform_test_options']['perform_cache_preload_queue'] = [ 'https://example.com/existing' ];
		$GLOBALS['wpdb']->fail_updates                                  = true;
		$page_cache = new PageCache();
		$method     = new ReflectionMethod( $page_cache, 'mutate_preload_queue' );
		$method->setAccessible( true );
		$result = $method->invoke(
			$page_cache,
			static function ( array $queue ): array {
				$queue[] = 'https://example.com/new';
				return [
					'queue' => $queue,
					'value' => count( $queue ),
				];
			}
		);

		$this->assertFalse( $result['success'] );
		$this->assertSame( 10, $GLOBALS['wpdb']->query_count );
		$this->assertSame( [ 'https://example.com/existing' ], $GLOBALS['perform_test_options']['perform_cache_preload_queue'] );
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

	public function test_commerce_ajax_query_bypasses_cache_by_default() {
		$_GET['wc-ajax'] = 'get_refreshed_fragments';
		$page_cache      = new PageCache();

		$this->assertFalse( $this->invoke_is_cacheable_request( $page_cache ) );
		$this->assertSame( 'default_query', $this->get_private_property( $page_cache, 'current_bypass_reason' ) );
	}

	public function test_wordpress_password_cookie_bypasses_cache_by_default() {
		$_COOKIE['wp-postpass_fixture'] = 'fixture';
		$page_cache                     = new PageCache();

		$this->assertFalse( $this->invoke_is_cacheable_request( $page_cache ) );
		$this->assertSame( 'default_cookie', $this->get_private_property( $page_cache, 'current_bypass_reason' ) );
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

	public function test_legacy_policy_cache_body_is_not_reused_after_upgrade() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$url         = 'https://example.com/password-protected/';
		$legacy_key  = md5( $url );
		$legacy_path = $this->invoke_private_with_argument( $page_cache, 'get_body_file_path', $legacy_key );
		mkdir( dirname( $legacy_path ), 0777, true );
		file_put_contents( $legacy_path, 'legacy private fixture' );

		try {
			$current_key = $this->invoke_private_with_argument( $page_cache, 'get_cache_key_for_url', $url );
			$this->assertNull( $this->invoke_private_with_argument( $page_cache, 'read_cache_body', $current_key ) );
			$this->assertFileExists( $legacy_path );
			$this->write_cached_url( $page_cache, $url );
			$this->assertSame( 'cached', $this->invoke_private_with_argument( $page_cache, 'read_cache_body', $current_key ) );
			$this->assertSame( 'legacy private fixture', file_get_contents( $legacy_path ) );
		} finally {
			foreach ( glob( dirname( $legacy_path ) . '/*' ) as $path ) {
				unlink( $path );
			}
			rmdir( dirname( $legacy_path ) );
			rmdir( dirname( $legacy_path, 2 ) );
			rmdir( $cache_dir );
		}
	}

	public function test_upgrade_removes_owned_cache_bodies_but_preserves_metadata_and_other_files() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$directories              = [ $cache_dir, $cache_dir . 'site-1/generation-1/', $cache_dir . 'site-1/generation-2/', $cache_dir . 'site-2/generation-1/' ];
		$temporary_names          = [
			str_repeat( 'a', 32 ) . '.html.' . uniqid( 'tmp-', true ),
			str_repeat( 'a', 32 ) . '.html.tmp.' . uniqid( '', true ),
			str_repeat( 'a', 32 ) . '.html.tmp-' . str_repeat( '10', 13 ) . '.00000001',
			str_repeat( 'a', 32 ) . '.html.tmp.' . str_repeat( '10', 13 ) . '.00000001',
		];
		$malformed_temporary_name = str_repeat( 'a', 32 ) . '.html.tmp-' . str_repeat( '10', 17 ) . '.00000001';
		foreach ( $directories as $directory ) {
			if ( ! is_dir( $directory ) ) {
				mkdir( $directory, 0777, true );
			}
			file_put_contents( $directory . str_repeat( 'a', 32 ) . '.html', 'private fixture' );
			foreach ( $temporary_names as $temporary_name ) {
				file_put_contents( $directory . $temporary_name, 'interrupted private fixture' );
			}
			file_put_contents( $directory . $malformed_temporary_name, 'unrelated malformed fixture' );
			file_put_contents( $directory . str_repeat( 'a', 32 ) . '.meta.json', '{}' );
			file_put_contents( $directory . 'keep.txt', 'unrelated fixture' );
		}
		$GLOBALS['perform_test_options']['perform_settings']            = [ 'extension_value' => 'retained' ];
		$GLOBALS['perform_test_options']['perform_cache_body_policy_1'] = 2;

		$page_cache->retire_legacy_cache_bodies();

		foreach ( $directories as $directory ) {
			$this->assertFileDoesNotExist( $directory . str_repeat( 'a', 32 ) . '.html' );
			foreach ( $temporary_names as $temporary_name ) {
				$this->assertFileDoesNotExist( $directory . $temporary_name );
			}
			$this->assertSame( 'unrelated malformed fixture', file_get_contents( $directory . $malformed_temporary_name ) );
			$this->assertFileExists( $directory . str_repeat( 'a', 32 ) . '.meta.json' );
			$this->assertSame( 'unrelated fixture', file_get_contents( $directory . 'keep.txt' ) );
		}
		$this->assertSame( [ 'extension_value' => 'retained' ], $GLOBALS['perform_test_options']['perform_settings'] );
		$this->assertSame( 3, $GLOBALS['perform_test_options']['perform_cache_body_policy'] );
		$this->assertSame( 3, $GLOBALS['perform_test_options']['perform_cache_metadata_policy_1'] );
	}

	public function test_retirement_keeps_metadata_available_for_bounded_cloudflare_inventory() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		delete_site_option( 'perform_cache_body_policy' );
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$directory = $cache_dir . 'site-1/generation-1/';
		mkdir( $directory, 0777, true );
		file_put_contents( $directory . str_repeat( 'a', 32 ) . '.html', 'private fixture' );
		file_put_contents( $directory . str_repeat( 'a', 32 ) . '.meta.json', wp_json_encode( [ 'url' => 'https://example.com/protected/' ] ) );
		$other_directory = $cache_dir . 'site-2/generation-1/';
		mkdir( $other_directory, 0777, true );
		file_put_contents( $other_directory . str_repeat( 'b', 32 ) . '.meta.json', wp_json_encode( [ 'url' => 'https://other.example.com/protected/' ] ) );
		file_put_contents( $other_directory . str_repeat( 'b', 32 ) . '.html', 'other private fixture' );
		$GLOBALS['perform_test_options']['perform_settings'] = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'fixture-token',
		];

		$page_cache->retire_legacy_cache_bodies();

		$this->assertFileDoesNotExist( $directory . str_repeat( 'a', 32 ) . '.html' );
		$record = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0];
		$work   = $this->invoke_private_with_argument( $page_cache, 'get_cloudflare_record_work', $record );
		$this->assertSame( [ 'https://example.com/protected/' ], $work['urls'] );
		$this->assertTrue( $work['complete'] );
		$this->assertStringNotContainsString( 'fixture-token', wp_json_encode( $record ) );
		$this->assertFileDoesNotExist( $other_directory . str_repeat( 'b', 32 ) . '.html' );
		$this->assertFileExists( $other_directory . str_repeat( 'b', 32 ) . '.meta.json' );
		$this->assertSame( [], $GLOBALS['perform_test_remote_post_calls'] );
	}

	public function test_each_blog_queues_its_metadata_without_repeating_shared_body_retirement() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$page_cache->retire_legacy_cache_bodies();
		$this->write_cached_url( $page_cache, 'https://example.com/public/' );
		$GLOBALS['perform_test_blog_id'] = 2;
		$other_cache                     = new PageCache();
		$this->set_private_property( $other_cache, 'cache_dir', $cache_dir );
		$other_cache->retire_legacy_cache_bodies();
		$this->assertSame( 3, $GLOBALS['perform_test_options']['perform_cache_metadata_policy_2'] );
		$GLOBALS['perform_test_blog_id'] = 1;
		$this->assertTrue( $this->cache_file_exists( $page_cache, 'https://example.com/public/' ) );
	}

	public function test_retirement_is_once_per_upgrade_and_preserves_new_cache_until_next_plugin_upgrade() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$page_cache->retire_legacy_cache_bodies();
		$this->write_cached_url( $page_cache, 'https://example.com/public/' );
		$page_cache->retire_legacy_cache_bodies();
		$this->assertTrue( $this->cache_file_exists( $page_cache, 'https://example.com/public/' ) );
		$page_cache->reset_cache_body_policy_after_upgrade( null, [ 'type' => 'theme' ] );
		$this->assertSame( 3, $GLOBALS['perform_test_options']['perform_cache_body_policy'] );
		$page_cache->reset_cache_body_policy_after_upgrade( null, [ 'type' => 'plugin' ] );
		$page_cache->retire_legacy_cache_bodies();
		$this->assertFalse( $this->cache_file_exists( $page_cache, 'https://example.com/public/' ) );
	}

	public function test_retirement_does_not_follow_cache_body_symlinks_and_does_not_claim_completion() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$target = $cache_dir . 'keep.txt';
		$link   = $cache_dir . str_repeat( 'a', 32 ) . '.html';
		file_put_contents( $target, 'unrelated fixture' );
		symlink( $target, $link );
		$GLOBALS['perform_test_options']['perform_settings'] = [ 'enable_page_cache' => true ];

		$page_cache->retire_legacy_cache_bodies();

		$this->assertSame( 'unrelated fixture', file_get_contents( $target ) );
		$this->assertTrue( is_link( $link ) );
		$this->assertArrayNotHasKey( 'perform_cache_body_policy', $GLOBALS['perform_test_options'] );
		$this->assertFalse( $this->invoke_private( $page_cache, 'page_cache_enabled' ) );
		$this->assertContains( 'admin_notices', array_column( $GLOBALS['perform_test_actions'], 'hook' ) );
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
		file_put_contents( $cache_dir . 'site-1/generation-1/' . str_repeat( '1', 32 ) . '.html', 'one' );
		file_put_contents( $cache_dir . 'site-1/generation-1/' . str_repeat( '2', 32 ) . '.html', 'two' );

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
		$this->assertSame( 'a.meta.json', $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['cursor_filename'] );
	}

	public function test_cloudflare_batch_does_not_overwrite_a_concurrent_queue_producer() {
		$page_cache                                   = new PageCache();
		$settings                                     = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$GLOBALS['perform_test_options']              = [
			'perform_settings'                 => $settings,
			'perform_cache_cloudflare_queue_1' => [
				[
					'urls'        => [ 'https://example.com/a/', 'https://example.com/b/' ],
					'zone_id'     => 'zone',
					'fingerprint' => md5( 'zone|token' ),
					'enabled'     => true,
					'attempts'    => 0,
					'failed'      => false,
				],
			],
		];
		$GLOBALS['perform_test_remote_post_response'] = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"success":true}',
		];
		$enqueue                                      = new ReflectionMethod( $page_cache, 'queue_cloudflare_inline_urls' );
		$enqueue->setAccessible( true );
		$GLOBALS['perform_test_remote_post_callback'] = static function () use ( $enqueue, $page_cache ): void {
			$enqueue->invoke( $page_cache, [ 'https://example.com/c/' ] );
		};

		$page_cache->run_cloudflare_purge_batch();

		$this->assertSame(
			[ 'https://example.com/a/', 'https://example.com/b/', 'https://example.com/c/' ],
			$GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['urls']
		);
		$this->assertNotEmpty( $GLOBALS['perform_test_scheduled_single_events'] );
	}

	public function test_cloudflare_batch_keeps_same_url_reenqueued_during_purge() {
		$page_cache                                   = new PageCache();
		$settings                                     = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$GLOBALS['perform_test_options']              = [
			'perform_settings'                 => $settings,
			'perform_cache_cloudflare_queue_1' => [
				[
					'urls'        => [ 'https://example.com/a/' ],
					'zone_id'     => 'zone',
					'fingerprint' => md5( 'zone|token' ),
					'enabled'     => true,
					'attempts'    => 0,
					'failed'      => false,
				],
			],
		];
		$GLOBALS['perform_test_remote_post_response'] = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"success":true}',
		];
		$enqueue                                      = new ReflectionMethod( $page_cache, 'queue_cloudflare_inline_urls' );
		$enqueue->setAccessible( true );
		$GLOBALS['perform_test_remote_post_callback'] = static function () use ( $enqueue, $page_cache ): void {
			$enqueue->invoke( $page_cache, [ 'https://example.com/a/' ] );
		};

		$page_cache->run_cloudflare_purge_batch();
		$this->assertSame( [ 'https://example.com/a/' ], $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['urls'] );
		$this->assertNotEmpty( $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['_revision'] );

		unset( $GLOBALS['perform_test_remote_post_callback'] );
		$page_cache->run_cloudflare_purge_batch();
		$this->assertSame( [], $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['urls'] );
		$this->assertCount( 2, $GLOBALS['perform_test_remote_post_calls'] );
		$page_cache->run_cloudflare_purge_batch();
		$this->assertSame( [], $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] );
	}

	public function test_cloudflare_stale_consumer_preserves_record_recreated_after_queue_drain() {
		$page_cache                                   = new PageCache();
		$settings                                     = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$GLOBALS['perform_test_options']              = [
			'perform_settings'                 => $settings,
			'perform_cache_cloudflare_queue_1' => [
				[
					'urls'        => [ 'https://example.com/a/' ],
					'zone_id'     => 'zone',
					'fingerprint' => md5( 'zone|token' ),
					'enabled'     => true,
					'attempts'    => 0,
					'failed'      => false,
					'_revision'   => 0,
				],
			],
		];
		$GLOBALS['perform_test_remote_post_response'] = [
			'response' => [ 'code' => 200 ],
			'body'     => '{"success":true}',
		];
		$enqueue                                      = new ReflectionMethod( $page_cache, 'queue_cloudflare_inline_urls' );
		$enqueue->setAccessible( true );
		$GLOBALS['perform_test_remote_post_callback'] = static function () use ( $enqueue, $page_cache ): void {
			// Model another consumer draining the old record before a producer requeues the URL.
			$GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] = [];
			$enqueue->invoke( $page_cache, [ 'https://example.com/a/' ] );
		};

		$page_cache->run_cloudflare_purge_batch();

		$queue = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'];
		$this->assertCount( 1, $queue );
		$this->assertSame( [ 'https://example.com/a/' ], $queue[0]['urls'] );
		$this->assertNotSame( 0, $queue[0]['_revision'] );
		$this->assertSame( 2, $GLOBALS['wpdb']->query_count );
	}

	public function test_new_cloudflare_inventory_request_restarts_an_older_filename_cursor() {
		$page_cache                      = new PageCache();
		$settings                        = [
			'enable_cloudflare_cache_sync' => true,
			'cloudflare_zone_id'           => 'zone',
			'cloudflare_api_token'         => 'token',
		];
		$GLOBALS['perform_test_options'] = [
			'perform_settings'                 => $settings,
			'perform_cache_cloudflare_queue_1' => [
				[
					'source'             => 'metadata',
					'generation'         => 1,
					'cursor_filename'    => 'z.meta.json',
					'inventory_complete' => true,
					'fingerprint'        => md5( 'zone|token' ),
					'_revision'          => 'previous-scan',
				],
			],
		];
		$this->invoke_private_with_arguments( $page_cache, 'queue_cloudflare_tracked_urls', [ $settings, 1 ] );

		$record = $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0];
		$this->assertArrayNotHasKey( 'cursor_filename', $record );
		$this->assertArrayNotHasKey( 'inventory_complete', $record );
		$this->assertNotSame( 'previous-scan', $record['_revision'] );
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
		$this->assertNotEmpty( $GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'][0]['cursor_filename'] );
		$this->assertSame( [], $GLOBALS['perform_test_remote_post_calls'] );
	}

	public function test_metadata_inventory_keeps_urls_found_before_scan_budget_hits_unrelated_files() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cloudflare_metadata_scan_limit', 2 );
		$directory = $cache_dir . 'site-1/generation-1/';
		mkdir( $directory, 0777, true );
		$metadata_name = str_repeat( 'a', 32 ) . '.meta.json';
		$noise_name    = 'z-operator-note.txt';
		$later_name    = 'zz-later.meta.json';
		// The scanner sorts names, so creation/DirectoryIterator order must not matter.
		file_put_contents( $directory . $later_name, wp_json_encode( [ 'url' => 'https://example.com/later/' ] ) );
		file_put_contents( $directory . $noise_name, 'unrelated' );
		file_put_contents( $directory . $metadata_name, wp_json_encode( [ 'url' => 'https://example.com/kept/' ] ) );
		$record = [
			'source'          => 'metadata',
			'generation'      => 1,
			'cursor_filename' => '',
		];

		$work = $this->invoke_private_with_argument( $page_cache, 'get_cloudflare_metadata_urls', $record );

		$this->assertSame( [ 'https://example.com/kept/' ], $work['urls'] );
		$this->assertSame( $noise_name, $work['cursor_filename'] );
		$this->assertFalse( $work['complete'] );

		$record['cursor_filename'] = $work['cursor_filename'];
		$next_work                 = $this->invoke_private_with_argument( $page_cache, 'get_cloudflare_metadata_urls', $record );
		$this->assertSame( [ 'https://example.com/later/' ], $next_work['urls'] );
		$this->assertSame( $later_name, $next_work['cursor_filename'] );
		$this->assertTrue( $next_work['complete'] );
	}

	public function test_metadata_inventory_cursor_survives_removal_of_earlier_files() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cloudflare_batch_size', 1 );
		$directory = $cache_dir . 'site-1/generation-1/';
		mkdir( $directory, 0777, true );
		foreach ( [ 'a', 'b', 'c' ] as $letter ) {
			file_put_contents( $directory . $letter . '.meta.json', wp_json_encode( [ 'url' => 'https://example.com/' . $letter . '/' ] ) );
		}
		$record = [
			'source'     => 'metadata',
			'generation' => 1,
		];
		$first  = $this->invoke_private_with_argument( $page_cache, 'get_cloudflare_metadata_urls', $record );
		wp_delete_file( $directory . 'a.meta.json' );
		$second = $this->invoke_private_with_argument(
			$page_cache,
			'get_cloudflare_metadata_urls',
			array_merge( $record, [ 'cursor_filename' => $first['cursor_filename'] ] )
		);

		$this->assertSame( [ 'https://example.com/a/' ], $first['urls'] );
		$this->assertSame( [ 'https://example.com/b/' ], $second['urls'] );
	}

	public function test_legacy_numeric_metadata_cursor_restarts_from_the_beginning() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$directory = $cache_dir . 'site-1/generation-1/';
		mkdir( $directory, 0777, true );
		file_put_contents( $directory . 'a.meta.json', wp_json_encode( [ 'url' => 'https://example.com/a/' ] ) );
		file_put_contents( $directory . 'b.meta.json', wp_json_encode( [ 'url' => 'https://example.com/b/' ] ) );

		$work = $this->invoke_private_with_argument(
			$page_cache,
			'get_cloudflare_metadata_urls',
			[
				'source'     => 'metadata',
				'generation' => 1,
				'cursor'     => 9,
			]
		);

		$this->assertSame( 'https://example.com/a/', $work['urls'][0] );
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
		$metadata_path = $cache_dir . 'site-1/generation-1/' . str_repeat( 'a', 32 ) . '.meta.json';
		file_put_contents( $metadata_path, '{}' );
		$GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] = [
			[
				'source'     => 'metadata',
				'generation' => 1,
				'cursor'     => 0,
				'failed'     => false,
			],
		];

		$page_cache->cleanup_obsolete_generations();
		$this->assertFileExists( $metadata_path );
		$this->assertSame( 'perform_cache_cleanup_event', $GLOBALS['perform_test_scheduled_single_events'][0]['hook'] );

		$GLOBALS['perform_test_options']['perform_cache_cloudflare_queue_1'] = [];
		$page_cache->cleanup_obsolete_generations();
		$this->assertFileDoesNotExist( $metadata_path );
	}

	public function test_cleanup_schedules_again_when_legacy_files_exactly_exhaust_budget() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cleanup_batch_size', 1 );
		$GLOBALS['perform_test_options']['perform_cache_generation_1'] = 2;
		file_put_contents( $cache_dir . str_repeat( 'a', 32 ) . '.html', 'legacy' );
		mkdir( $cache_dir . 'site-1/generation-1/', 0777, true );
		file_put_contents( $cache_dir . 'site-1/generation-1/' . str_repeat( 'b', 32 ) . '.html', 'retired' );
		file_put_contents( $cache_dir . 'site-1/generation-1/operator-note.txt', 'keep' );

		$page_cache->cleanup_obsolete_generations();
		$this->assertFileExists( $cache_dir . 'site-1/generation-1/' . str_repeat( 'b', 32 ) . '.html' );
		$this->assertSame( 'perform_cache_cleanup_event', $GLOBALS['perform_test_scheduled_single_events'][0]['hook'] );
	}

	public function test_retired_generation_cleanup_preserves_unrelated_files() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$this->set_private_property( $page_cache, 'cleanup_batch_size', 1 );
		$GLOBALS['perform_test_options']['perform_cache_generation_1'] = 2;
		$directory = $cache_dir . 'site-1/generation-1/';
		mkdir( $directory, 0777, true );
		file_put_contents( $directory . str_repeat( 'a', 32 ) . '.html', 'retired body' );
		file_put_contents( $directory . 'operator-note.txt', 'keep this file' );

		$page_cache->cleanup_obsolete_generations();

		$this->assertFileDoesNotExist( $directory . str_repeat( 'a', 32 ) . '.html' );
		$this->assertSame( 'keep this file', file_get_contents( $directory . 'operator-note.txt' ) );
		$this->assertSame( [], $GLOBALS['perform_test_scheduled_single_events'] );
	}

	public function test_multisite_cleanup_retains_shared_root_metadata_for_other_blog_inventories() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$GLOBALS['perform_test_is_multisite']                          = true;
		$GLOBALS['perform_test_options']['perform_cache_generation_1'] = 2;
		$body_path     = $cache_dir . str_repeat( 'a', 32 ) . '.html';
		$metadata_path = $cache_dir . str_repeat( 'a', 32 ) . '.meta.json';
		file_put_contents( $body_path, 'retired shared body' );
		file_put_contents( $metadata_path, '{"url":"https://example.com/other-blog/"}' );

		$page_cache->cleanup_obsolete_generations();

		$this->assertFileDoesNotExist( $body_path );
		$this->assertFileExists( $metadata_path );
		$this->assertSame( [], $GLOBALS['perform_test_scheduled_single_events'] );
	}

	public function test_retired_generation_cleanup_removes_interrupted_metadata_file_but_preserves_unrelated_files() {
		$page_cache = new PageCache();
		$cache_dir  = $this->temporary_cache_dir();
		$this->set_private_property( $page_cache, 'cache_dir', $cache_dir );
		$GLOBALS['perform_test_options']['perform_cache_generation_1'] = 2;
		$directory = $cache_dir . 'site-1/generation-1/';
		mkdir( $directory, 0777, true );
		$metadata_temporary        = $directory . str_repeat( 'a', 32 ) . '.meta.json.' . uniqid( 'tmp-', true );
		$metadata_temporary_dotted = $directory . str_repeat( 'b', 32 ) . '.meta.json.tmp.' . uniqid( '', true );
		$metadata_temporary_long   = $directory . str_repeat( 'c', 32 ) . '.meta.json.tmp.' . str_repeat( '10', 13 ) . '.00000001';
		file_put_contents( $metadata_temporary, '{"url":"https://example.com/unfinished/"}' );
		file_put_contents( $metadata_temporary_dotted, '{"url":"https://example.com/unfinished-dotted/"}' );
		file_put_contents( $metadata_temporary_long, '{"url":"https://example.com/unfinished-long/"}' );
		file_put_contents( $directory . 'operator-note.txt', 'keep this file' );

		$page_cache->cleanup_obsolete_generations();

		$this->assertFileDoesNotExist( $metadata_temporary );
		$this->assertFileDoesNotExist( $metadata_temporary_dotted );
		$this->assertFileDoesNotExist( $metadata_temporary_long );
		$this->assertSame( 'keep this file', file_get_contents( $directory . 'operator-note.txt' ) );
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

	public function test_registers_cache_stats_content_and_a_hidden_legacy_route() {
		$page_cache = new PageCache();
		$page_cache->register();

		$hooks = array_column( $GLOBALS['perform_test_actions'], 'hook' );
		$this->assertContains( 'perform_settings_cache_stats_content', $hooks );
		$this->assertContains( 'admin_menu', $hooks );
		$this->assertNotContains( 'admin_init', $hooks );

		$GLOBALS['perform_test_submenu_pages']         = [];
		$GLOBALS['perform_test_removed_submenu_pages'] = [];
		$page_cache->register_legacy_observability_route();

		$this->assertSame(
			[ 'perform_cache_observability', 'perform_cache_stats' ],
			array_column( $GLOBALS['perform_test_submenu_pages'], 'menu_slug' )
		);
		$this->assertSame( [ 'manage_options', 'manage_options' ], array_column( $GLOBALS['perform_test_submenu_pages'], 'capability' ) );
		$this->assertSame(
			[ 'perform_cache_observability', 'perform_cache_stats' ],
			array_column( $GLOBALS['perform_test_removed_submenu_pages'], 'submenu_slug' )
		);
		$this->assertContains( 'load-options-general.php_page_perform_cache_observability', array_column( $GLOBALS['perform_test_actions'], 'hook' ) );
		$this->assertContains( 'load-options-general.php_page_perform_cache_stats', array_column( $GLOBALS['perform_test_actions'], 'hook' ) );
	}

	public function test_cache_stats_url_is_canonical_and_drops_unapproved_arguments() {
		$page_cache = new PageCache();
		$method     = new ReflectionMethod( $page_cache, 'get_observability_url' );
		$method->setAccessible( true );

		$this->assertSame(
			'https://example.com/wp-admin/options-general.php?page=perform_settings&tab=cache-stats&perform_cache_purged=1',
			$method->invoke(
				$page_cache,
				[
					'perform_cache_purged' => 1,
					'redirect_to'          => 'https://attacker.example.com',
				]
			)
		);
	}

	public function test_legacy_cache_stats_url_retains_denial_for_unauthorized_users() {
		$_GET['page'] = 'perform_cache_observability';

		$this->expectException( RuntimeException::class );
		( new PageCache() )->maybe_redirect_legacy_observability_page();
	}

	public function test_alternate_legacy_cache_stats_url_retains_denial_for_unauthorized_users() {
		$_GET['page'] = 'perform_cache_stats';

		$this->expectException( RuntimeException::class );
		( new PageCache() )->maybe_redirect_legacy_observability_page();
	}

	public function test_cache_stats_render_preserves_existing_counts_and_actions() {
		$GLOBALS['perform_test_current_user_can']               = [ 'manage_options' => true ];
		$GLOBALS['perform_test_options']['perform_cache_stats'] = [
			'hits'       => 12,
			'stale_hits' => 2,
			'misses'     => 6,
			'top_misses' => [ '/slow-page/' => 4 ],
		];

		ob_start();
		( new PageCache() )->render_observability_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Perform Cache Observability', $output );
		$this->assertStringContainsString( '70%', $output );
		$this->assertStringContainsString( '/slow-page/', $output );
		$this->assertStringContainsString( 'perform_purge_page_cache', $output );
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

	public function test_cache_activity_actions_are_registered() {
		( new PageCache() )->register();

		$hooks = array_column( $GLOBALS['perform_test_actions'], 'hook' );
		$this->assertContains( 'admin_post_perform_export_cache_activity', $hooks );
		$this->assertContains( 'admin_post_perform_clear_cache_activity', $hooks );
	}

	public function test_cache_activity_export_requires_manage_options() {
		$this->expectException( RuntimeException::class );
		( new PageCache() )->handle_cache_activity_export();
	}

	public function test_async_cache_activity_clear_removes_stats_and_returns_canonical_redirect() {
		$GLOBALS['perform_test_current_user_can']               = [ 'manage_options' => true ];
		$GLOBALS['perform_test_nonce_valid']                    = true;
		$GLOBALS['perform_test_options']['perform_cache_stats'] = [ 'hits' => 15 ];
		$_POST['perform_async']                                 = '1';

		try {
			( new PageCache() )->handle_cache_activity_clear();
			$this->fail( 'Expected the test JSON response to terminate the request.' );
		} catch ( RuntimeException $exception ) {
			$this->assertSame( 'perform_test_json_response', $exception->getMessage() );
		}

		$this->assertArrayNotHasKey( 'perform_cache_stats', $GLOBALS['perform_test_options'] );
		$this->assertTrue( $GLOBALS['perform_test_json_response']['success'] );
		$this->assertSame(
			'https://example.com/wp-admin/options-general.php?page=perform_settings&tab=cache-stats&perform_cache_activity_cleared=1',
			$GLOBALS['perform_test_json_response']['data']['redirect']
		);
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
