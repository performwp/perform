<?php
/**
 * Perform - Full Page Cache Module
 *
 * @package Perform
 * @subpackage Modules/Cache
 * @since 1.6.0
 */

namespace Perform\Modules\Cache;

use Perform\Includes\Helpers;
use Perform\Modules\ModuleInterface;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PageCache implements ModuleInterface {
	/**
	 * Cache directory.
	 *
	 * @var string
	 */
	private $cache_dir = '';

	/**
	 * Generation captured when this request became eligible to write.
	 *
	 * @var int
	 */
	private $request_generation = 0;

	/**
	 * URLs captured before a post mutation removes their old routing context.
	 *
	 * @var array<int, array<int, string>>
	 */
	private $captured_post_urls = [];

	/**
	 * Maximum files deleted from obsolete cache generations per cron run.
	 *
	 * @var int
	 */
	private $cleanup_batch_size = 100;

	/**
	 * Maximum Cloudflare URLs purged per request.
	 *
	 * @var int
	 */
	private $cloudflare_batch_size = 30;

	/** @var int */
	private $cloudflare_metadata_scan_limit = 100;

	/**
	 * Maximum retry attempts for a failed Cloudflare URL batch.
	 *
	 * @var int
	 */
	private $cloudflare_max_retries = 3;

	/** @var bool */
	private $site_purge_requested = false;

	/**
	 * Current cache key for the request.
	 *
	 * @var string
	 */
	private $current_cache_key = '';

	/**
	 * Current normalized URL for the request.
	 *
	 * @var string
	 */
	private $current_url = '';

	/**
	 * Whether this request should be written to cache.
	 *
	 * @var bool
	 */
	private $should_write_cache = false;

	/**
	 * Current cache bypass reason for the request.
	 *
	 * @var string
	 */
	private $current_bypass_reason = '';

	/**
	 * Request start time.
	 *
	 * @var float
	 */
	private $request_start = 0.0;

	/**
	 * Lock transient key.
	 *
	 * @var string
	 */
	private $lock_key = '';

	/**
	 * Stats storage service.
	 *
	 * @var StatsStore|null
	 */
	private $stats_store = null;

	/**
	 * URL normalizer service.
	 *
	 * @var UrlNormalizer|null
	 */
	private $url_normalizer = null;

	/**
	 * Maximum number of child sitemap documents to fetch per seed run.
	 *
	 * @var int
	 */
	private $preload_sitemap_child_limit = 20;

	/**
	 * Maximum number of unique sitemap URLs to collect per seed run.
	 *
	 * @var int
	 */
	private $preload_sitemap_url_cap = 500;

	/**
	 * Determine whether this module should be loaded.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		// Invalidation must remain available while the page cache is disabled so a
		// later enable cannot expose entries written by an earlier configuration.
		return true;
	}

	/**
	 * Register hooks and filters for cache module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->cache_dir      = trailingslashit( WP_CONTENT_DIR ) . 'cache/perform/';
		$this->stats_store    = new StatsStore();
		$this->url_normalizer = new UrlNormalizer();

		add_filter( 'cron_schedules', [ $this, 'register_cron_schedule' ] );
		add_action( 'init', [ $this, 'maybe_schedule_events' ] );

		add_action( 'template_redirect', [ $this, 'maybe_serve_cache' ], 0 );
		add_action( 'template_redirect', [ $this, 'start_output_buffer' ], 9999 );
		add_action( 'shutdown', [ $this, 'record_slow_uncached_request' ], 9999 );
		add_action( 'shutdown', [ $this, 'flush_stats' ], 10000 );

		add_action( 'pre_post_update', [ $this, 'capture_post_urls_before_mutation' ], 10, 2 );
		add_action( 'wp_trash_post', [ $this, 'capture_post_urls_before_removal' ] );
		add_action( 'untrash_post', [ $this, 'capture_post_urls_before_removal' ] );
		add_action( 'before_delete_post', [ $this, 'capture_post_urls_before_removal' ] );
		add_action( 'save_post', [ $this, 'purge_related_urls_for_post' ], 10, 3 );
		add_action( 'deleted_post', [ $this, 'purge_related_urls_for_deleted_post' ], 10, 1 );
		add_action( 'trashed_post', [ $this, 'purge_related_urls_for_deleted_post' ], 10, 1 );
		add_action( 'untrashed_post', [ $this, 'purge_related_urls_for_untrashed_post' ], 10, 1 );
		add_action( 'transition_post_status', [ $this, 'purge_related_urls_for_status_transition' ], 10, 3 );
		add_action( 'set_object_terms', [ $this, 'purge_related_urls_for_object_terms' ], 10, 6 );
		add_action( 'comment_post', [ $this, 'purge_related_urls_for_comment' ], 10, 1 );
		add_action( 'edit_comment', [ $this, 'purge_related_urls_for_comment' ] );
		add_action( 'transition_comment_status', [ $this, 'purge_related_urls_for_comment_status' ], 10, 3 );
		add_action( 'deleted_comment', [ $this, 'purge_related_urls_for_comment' ] );
		add_action( 'created_term', [ $this, 'purge_related_urls_for_term' ], 10, 3 );
		add_action( 'edited_term', [ $this, 'purge_related_urls_for_term' ], 10, 3 );
		add_action( 'delete_term', [ $this, 'purge_related_urls_for_deleted_term' ], 10, 5 );
		add_action( 'added_term_meta', [ $this, 'purge_related_urls_for_term_meta' ], 10, 2 );
		add_action( 'updated_term_meta', [ $this, 'purge_related_urls_for_term_meta' ], 10, 2 );
		add_action( 'deleted_term_meta', [ $this, 'purge_related_urls_for_term_meta' ], 10, 2 );
		add_action( 'wp_update_nav_menu', [ $this, 'purge_site_cache' ] );
		add_action( 'wp_update_nav_menu_item', [ $this, 'purge_site_cache' ] );
		add_action( 'wp_delete_nav_menu', [ $this, 'purge_site_cache' ] );
		add_action( 'switch_theme', [ $this, 'purge_site_cache' ] );
		add_action( 'customize_save_after', [ $this, 'purge_site_cache' ] );
		add_action( 'updated_option', [ $this, 'purge_related_urls_for_option' ], 10, 3 );
		add_filter( 'pre_update_option_perform_settings', [ $this, 'capture_cloudflare_settings_before_update' ], 10, 3 );

		add_action( 'perform_cache_preload_event', [ $this, 'run_preload_batch' ] );
		add_action( 'perform_cache_sitemap_seed_event', [ $this, 'seed_preload_queue_from_sitemap_and_logs' ] );
		add_action( 'perform_cache_cleanup_event', [ $this, 'cleanup_obsolete_generations' ] );
		add_action( 'perform_cache_cloudflare_purge_event', [ $this, 'run_cloudflare_purge_batch' ] );

		add_action( 'admin_menu', [ $this, 'register_observability_page' ] );
		add_action( 'admin_post_perform_purge_page_cache', [ $this, 'handle_manual_purge' ] );
		add_action( 'admin_post_perform_retry_cloudflare_cleanup', [ $this, 'handle_cloudflare_cleanup_retry' ] );
		add_action( 'admin_post_perform_acknowledge_cloudflare_residual', [ $this, 'handle_cloudflare_residual_acknowledgement' ] );
	}

	/**
	 * Register custom cron schedule.
	 *
	 * @param array<string, array<string, int|string>> $schedules Existing schedules.
	 *
	 * @return array<string, array<string, int|string>>
	 */
	public function register_cron_schedule( $schedules ) {
		if ( ! isset( $schedules['perform_cache_every_5_minutes'] ) ) {
			$schedules['perform_cache_every_5_minutes'] = [
				'interval' => 300,
				'display'  => __( 'Every 5 minutes (Perform Cache)', 'perform' ),
			];
		}

		return $schedules;
	}

	/**
	 * Ensure cache cron events are scheduled.
	 *
	 * @return void
	 */
	public function maybe_schedule_events() {
		$preload_enabled = $this->page_cache_enabled() && ! empty( Helpers::get_option( 'enable_cache_preload', 'perform_settings', false ) );

		if ( $preload_enabled && ! wp_next_scheduled( 'perform_cache_preload_event' ) ) {
			wp_schedule_event( time() + 60, 'perform_cache_every_5_minutes', 'perform_cache_preload_event' );
		}

		if ( ! $preload_enabled && wp_next_scheduled( 'perform_cache_preload_event' ) ) {
			wp_clear_scheduled_hook( 'perform_cache_preload_event' );
		}

		if ( $preload_enabled && ! wp_next_scheduled( 'perform_cache_sitemap_seed_event' ) ) {
			wp_schedule_event( time() + 180, 'daily', 'perform_cache_sitemap_seed_event' );
		}

		if ( ! $preload_enabled && wp_next_scheduled( 'perform_cache_sitemap_seed_event' ) ) {
			wp_clear_scheduled_hook( 'perform_cache_sitemap_seed_event' );
		}
	}

	/**
	 * Serve cache when available.
	 *
	 * @return void
	 */
	public function maybe_serve_cache() {
		if ( ! $this->page_cache_enabled() ) {
			return;
		}
		$this->request_start = microtime( true );

		if ( ! $this->is_cacheable_request() ) {
			$bypass_increment = $this->increment_stat( 'bypasses' );
			if ( 0 < $bypass_increment ) {
				$this->record_bypass_reason( $this->current_bypass_reason, $bypass_increment );
			}
			return;
		}

		$this->ensure_cache_dir();

		$this->current_url        = $this->get_normalized_request_url();
		$this->current_cache_key  = $this->get_cache_key_for_url( $this->current_url );
		$this->request_generation = $this->get_cache_generation();
		$this->lock_key           = 'perform_cache_lock_' . $this->current_cache_key;

		$meta = $this->read_cache_meta( $this->current_cache_key );
		$html = $this->read_cache_body( $this->current_cache_key );

		$is_regen_request = $this->is_internal_regen_request();

		if ( $meta && is_string( $html ) && ! $is_regen_request ) {
			$now = time();
			if ( ! empty( $meta['expires'] ) && $now <= (int) $meta['expires'] ) {
				$this->increment_stat( 'hits' );
				$this->send_cache_headers( 'HIT' );
				$this->flush_stats();
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}

			if ( ! empty( $meta['swr_expires'] ) && $now <= (int) $meta['swr_expires'] ) {
				$this->increment_stat( 'stale_hits' );
				$this->send_cache_headers( 'STALE' );
				$this->maybe_trigger_async_regeneration( $this->current_url );
				$this->flush_stats();
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}
		}

		if ( $is_regen_request ) {
			$this->should_write_cache = true;
			return;
		}

		// Miss path: acquire lock to avoid stampede.
		if ( ! $this->acquire_lock() ) {
			$this->increment_stat( 'lock_waits' );

			// If lock is held and stale exists, prefer stale over full uncached render.
			if ( $meta && is_string( $html ) ) {
				$this->increment_stat( 'stale_hits' );
				$this->send_cache_headers( 'STALE-LOCK' );
				$this->flush_stats();
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}

			$miss_increment = $this->increment_stat( 'misses' );
			if ( 0 < $miss_increment ) {
				$this->record_top_miss( $this->current_url, $miss_increment );
			}
			return;
		}

		$this->should_write_cache = true;
		$miss_increment           = $this->increment_stat( 'misses' );
		if ( 0 < $miss_increment ) {
			$this->record_top_miss( $this->current_url, $miss_increment );
		}
	}

	/**
	 * Start output buffering for cache writes.
	 *
	 * @return void
	 */
	public function start_output_buffer() {
		if ( ! $this->should_write_cache ) {
			return;
		}

		ob_start( [ $this, 'store_cache' ] );
	}

	/**
	 * Store response in cache.
	 *
	 * @param string $html HTML output.
	 *
	 * @return string
	 */
	public function store_cache( $html ) {
		try {
			if ( ! $this->should_write_cache || ! $this->page_cache_enabled() || ! is_string( $html ) || '' === $html ) {
				return $html;
			}

			// A global purge may happen while WordPress renders this response. Do not
			// let that request repopulate the generation that was just retired.
			if ( $this->request_generation !== $this->get_cache_generation() ) {
				return $html;
			}

			if ( ! $this->is_cacheable_response( $html ) ) {
				return $html;
			}

			$ttl_seconds  = (int) Helpers::get_option( 'page_cache_ttl', 'perform_settings', 3600 );
			$swr_seconds  = (int) Helpers::get_option( 'page_cache_swr_ttl', 'perform_settings', 21600 );
			$created      = time();
			$meta_payload = [
				'url'         => $this->current_url,
				'created'     => $created,
				'expires'     => $created + max( 60, $ttl_seconds ),
				'swr_expires' => $created + max( 120, $ttl_seconds + $swr_seconds ),
			];

			if ( ! $this->write_cache_body( $this->current_cache_key, $html ) ) {
				return $html;
			}

			if ( $this->request_generation !== $this->get_cache_generation() ) {
				wp_delete_file( $this->get_body_file_path( $this->current_cache_key ) );
				return $html;
			}

			if ( ! $this->write_cache_meta( $this->current_cache_key, $meta_payload ) ) {
				wp_delete_file( $this->get_body_file_path( $this->current_cache_key ) );
			} elseif ( $this->request_generation !== $this->get_cache_generation() ) {
				wp_delete_file( $this->get_body_file_path( $this->current_cache_key ) );
				wp_delete_file( $this->get_meta_file_path( $this->current_cache_key ) );
			}
		} finally {
			$this->release_lock();
		}

		return $html;
	}

	/**
	 * Record uncached slow request timings.
	 *
	 * @return void
	 */
	public function record_slow_uncached_request() {
		if ( ! $this->page_cache_enabled() || 0.0 === $this->request_start || ! $this->is_cacheable_request() ) {
			return;
		}

		// If we served from cache, execution already exited.
		$duration_ms = ( microtime( true ) - $this->request_start ) * 1000;
		$threshold   = (int) Helpers::get_option( 'cache_slow_request_threshold_ms', 'perform_settings', 1200 );

		if ( $duration_ms < max( 200, $threshold ) ) {
			return;
		}

		$url          = '' !== $this->current_url ? $this->current_url : $this->get_normalized_request_url();
		$slow         = $this->get_stat_map( 'slow_uncached' );
		$slow[ $url ] = round( $duration_ms );
		arsort( $slow );
		$slow = array_slice( $slow, 0, 30, true );
		$this->set_stat_map( 'slow_uncached', $slow );
	}

	/**
	 * Purge related URLs when post is updated.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post Post object.
	 * @param bool     $update Whether this is update.
	 *
	 * @return void
	 */
	public function purge_related_urls_for_post( int $post_id, ?\WP_Post $post = null, bool $update = false ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$urls = array_merge( $this->get_captured_post_urls( $post_id ), $this->get_invalidation_urls_for_post( $post_id ) );
		$this->purge_urls( $urls );

		$this->queue_urls_for_preload( $urls );
	}

	/**
	 * Purge related URLs when post is removed.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return void
	 */
	public function purge_related_urls_for_deleted_post( $post_id ) {
		$this->purge_urls( array_merge( $this->get_captured_post_urls( $post_id ), $this->get_invalidation_urls_for_post( $post_id ) ) );
	}

	/** Purge newly restored public URLs after WordPress completes untrash. */
	public function purge_related_urls_for_untrashed_post( int $post_id ): void {
		$this->purge_related_urls_for_post( $post_id );
	}

	/**
	 * Purge taxonomy archives when terms change.
	 *
	 * @param int    $object_id Post object id.
	 * @param array  $terms Terms.
	 * @param array  $tt_ids Taxonomy term taxonomy ids.
	 * @param string $taxonomy Taxonomy.
	 * @param bool   $append Whether append.
	 * @param array  $old_tt_ids Old term taxonomy ids.
	 *
	 * @return void
	 */
	public function purge_related_urls_for_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		$urls = $this->get_invalidation_urls_for_post( $object_id );
		foreach ( (array) $old_tt_ids as $term_taxonomy_id ) {
			$term = get_term_by( 'term_taxonomy_id', $term_taxonomy_id );
			if ( $term ) {
				$link = get_term_link( $term );
				if ( ! is_wp_error( $link ) ) {
					$urls[] = $link;
				}
			}
		}

		$this->purge_urls( $urls );
	}

	/**
	 * Capture URLs before WordPress changes a post's status or permalink.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $data Post data.
	 */
	public function capture_post_urls_before_mutation( int $post_id, array $data = [] ): void {
		$this->capture_post_urls_before_removal( $post_id );
	}

	/** Capture URLs before WordPress removes routing context. */
	public function capture_post_urls_before_removal( int $post_id ): void {
		$this->captured_post_urls[ (int) $post_id ] = $this->get_invalidation_urls_for_post( $post_id );
	}

	/** Purge URLs affected by a public status transition. */
	public function purge_related_urls_for_status_transition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( $new_status === $old_status || empty( $post->ID ) ) {
			return;
		}

		$this->purge_related_urls_for_post( (int) $post->ID );
	}

	/** Purge pages that can render a public comment. */
	public function purge_related_urls_for_comment( int $comment_id ): void {
		$comment = get_comment( $comment_id );
		if ( ! $comment || empty( $comment->comment_post_ID ) ) {
			return;
		}

		$this->purge_related_urls_for_post( (int) $comment->comment_post_ID );
	}

	/** Purge pages for visible comment-status transitions. */
	public function purge_related_urls_for_comment_status( string $new_status, string $old_status, \WP_Comment $comment ): void {
		if ( $new_status !== $old_status && ! empty( $comment->comment_post_ID ) ) {
			$this->purge_related_urls_for_post( (int) $comment->comment_post_ID );
		}
	}

	/** Purge a changed term archive and its home surface. */
	public function purge_related_urls_for_term( int $term_id, int $tt_id = 0, string $taxonomy = '', ?\WP_Term $deleted_term = null ): void {
		$term = is_object( $deleted_term ) ? $deleted_term : get_term( $term_id, $taxonomy );
		$urls = [ home_url( '/' ) ];
		if ( $term ) {
			$link = get_term_link( $term );
			if ( ! is_wp_error( $link ) ) {
				$urls[] = $link;
			}
		}
		$affected = $this->get_affected_object_ids_for_term( $term_id, $taxonomy );
		if ( $affected['overflow'] ) {
			$this->purge_site_cache();
			return;
		}
		$this->purge_urls( array_merge( $urls, $this->get_invalidation_urls_for_posts( $affected['ids'] ) ) );
	}

	/**
	 * Purge affected post URLs supplied by WordPress before a term is deleted.
	 *
	 * @param mixed          $deleted_term Deleted term.
	 * @param array<int,int> $object_ids Object IDs.
	 */
	public function purge_related_urls_for_deleted_term( int $term_id, int $tt_id, string $taxonomy, $deleted_term, array $object_ids = [] ): void {
		$limit = $this->get_term_object_limit();
		if ( count( $object_ids ) > $limit ) {
			$this->purge_site_cache();
			return;
		}
		$urls = array_merge( [ home_url( '/' ) ], $this->get_invalidation_urls_for_posts( array_slice( $object_ids, 0, $limit ) ) );
		if ( $deleted_term instanceof \WP_Term ) {
			$link = get_term_link( $deleted_term );
			if ( ! is_wp_error( $link ) ) {
				$urls[] = $link;
			}
		}
		$this->purge_urls( $urls );
	}

	/**
	 * Purge a term archive when term metadata changes.
	 *
	 * @param mixed $meta_id Metadata ID.
	 */
	public function purge_related_urls_for_term_meta( $meta_id, int $term_id ): void {
		$term     = get_term( $term_id );
		$taxonomy = is_object( $term ) && ! empty( $term->taxonomy ) ? (string) $term->taxonomy : '';
		$this->purge_related_urls_for_term( $term_id, 0, $taxonomy );
	}

	/** Rotate the local cache after a site-wide output change. */
	public function purge_site_cache(): void {
		if ( $this->site_purge_requested ) {
			return;
		}
		$this->site_purge_requested = true;
		$current                    = $this->get_cache_generation();
		$this->queue_cloudflare_tracked_urls( null, $current );
		update_option( $this->get_generation_option_name(), $current + 1, false );
		$this->schedule_cleanup();
	}

	/**
	 * Invalidate when a setting changes public output or cache compatibility.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $value New value.
	 */
	public function purge_related_urls_for_option( string $option, $old_value, $value ): void {
		$global_options = [
			'permalink_structure',
			'category_base',
			'tag_base',
			'page_on_front',
			'page_for_posts',
			'show_on_front',
			'theme_mods_' . get_option( 'stylesheet' ),
			'perform_settings',
		];

		if ( in_array( $option, $global_options, true ) && $old_value !== $value ) {
			$this->purge_site_cache();
		}
	}

	/**
	 * Queue tracked edge URLs with the old credentials before they are replaced.
	 *
	 * @param mixed $value New settings.
	 * @param mixed $old_value Previous settings.
	 * @param mixed $option Option name.
	 * @return mixed
	 */
	public function capture_cloudflare_settings_before_update( $value, $old_value, $option ) {
		if ( ! is_array( $old_value ) || ! is_array( $value ) || ! $this->cloudflare_settings_changed( $old_value, $value ) ) {
			return $value;
		}
		if ( ! empty( $old_value['enable_cloudflare_cache_sync'] ) ) {
			$this->queue_cloudflare_tracked_urls( $old_value, $this->get_cache_generation() );
		}
		return $value;
	}

	/**
	 * Purge homepage cache.
	 *
	 * @return void
	 */
	public function purge_homepage() {
		$this->purge_url( home_url( '/' ) );
	}

	/**
	 * Purge a normalized set of known URLs.
	 *
	 * @param array<int, string> $urls URLs.
	 */
	private function purge_urls( array $urls ): void {
		$urls = array_values( array_unique( array_filter( array_map( 'strval', (array) $urls ) ) ) );
		foreach ( $urls as $url ) {
			$this->purge_url( $url );
		}
	}

	/**
	 * Retrieve and clear pre-mutation post URLs.
	 *
	 * @return array<int, string>
	 */
	private function get_captured_post_urls( int $post_id ): array {
		$post_id = (int) $post_id;
		$urls    = $this->captured_post_urls[ $post_id ] ?? [];
		unset( $this->captured_post_urls[ $post_id ] );

		return $urls;
	}

	/**
	 * Seed preload queue using sitemap and recent traffic.
	 *
	 * @return void
	 */
	public function seed_preload_queue_from_sitemap_and_logs() {
		if ( ! $this->page_cache_enabled() || empty( Helpers::get_option( 'enable_cache_preload', 'perform_settings', false ) ) ) {
			return;
		}

		$urls = [];

		$sitemap_urls = $this->fetch_urls_from_sitemap();
		if ( ! empty( $sitemap_urls ) ) {
			$urls = array_merge( $urls, $sitemap_urls );
		}

		$top_misses = array_keys( $this->get_stat_map( 'top_misses' ) );
		if ( ! empty( $top_misses ) ) {
			$urls = array_merge( $urls, $top_misses );
		}

		$this->queue_urls_for_preload( $urls );
	}

	/**
	 * Process preload queue with adaptive rate.
	 *
	 * @return void
	 */
	public function run_preload_batch() {
		if ( ! $this->page_cache_enabled() || empty( Helpers::get_option( 'enable_cache_preload', 'perform_settings', false ) ) ) {
			return;
		}

		$queue = get_option( 'perform_cache_preload_queue', [] );
		if ( ! is_array( $queue ) || empty( $queue ) ) {
			return;
		}

		$batch_size = $this->get_adaptive_preload_batch_size();
		$processed  = 0;

		while ( $processed < $batch_size && ! empty( $queue ) ) {
			$url = array_shift( $queue );
			if ( ! is_string( $url ) || '' === $url || ! $this->is_site_url( $url ) ) {
				continue;
			}

			wp_remote_get(
				esc_url_raw( $url ),
				[
					'timeout'   => 8,
					'blocking'  => true,
					'headers'   => [
						'X-Perform-Preload' => '1',
					],
					'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				]
			);

			++$processed;
			$this->increment_stat( 'preload_requests' );
		}

		update_option( 'perform_cache_preload_queue', array_values( $queue ), false );
		$this->set_stat_value( 'preload_queue_size', count( $queue ) );
	}

	/**
	 * Register cache observability page.
	 *
	 * @return void
	 */
	public function register_observability_page() {
		add_submenu_page(
			'options-general.php',
			esc_html__( 'Perform Cache Observability', 'perform' ),
			esc_html__( 'Perform Cache Stats', 'perform' ),
			'manage_options',
			'perform_cache_observability',
			[ $this, 'render_observability_page' ]
		);
	}

	/**
	 * Render cache observability dashboard.
	 *
	 * @return void
	 */
	public function render_observability_page() {
		$stats = get_option( 'perform_cache_stats', [] );
		if ( ! is_array( $stats ) ) {
			$stats = [];
		}

		$hits       = (int) ( $stats['hits'] ?? 0 );
		$misses     = (int) ( $stats['misses'] ?? 0 );
		$stale_hits = (int) ( $stats['stale_hits'] ?? 0 );
		$total      = max( 1, ( $hits + $stale_hits + $misses ) );
		$hit_ratio  = round( ( ( $hits + $stale_hits ) / $total ) * 100, 2 );

		$top_misses     = $stats['top_misses'] ?? [];
		$bypass_reasons = $stats['bypass_reasons'] ?? [];
		$slow_uncached  = $stats['slow_uncached'] ?? [];
		$cloudflare     = $this->get_cloudflare_queue_status();
		$residual       = (int) get_option( 'perform_cache_cloudflare_credential_residual_' . $this->get_blog_id(), 0 );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Perform Cache Observability', 'perform' ); ?></h1>
			<p><?php esc_html_e( 'Live cache effectiveness and warmup health metrics.', 'perform' ); ?></p>
			<?php if ( isset( $_GET['perform_cache_purged'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'The local page-cache generation has been invalidated.', 'perform' ); ?></p></div>
				<?php
				if ( ! empty( $_GET['perform_cache_cloudflare_pending'] ) ) :
					?>
					<div class="notice notice-warning"><p><?php esc_html_e( 'Cloudflare URL cleanup is pending.', 'perform' ); ?></p></div><?php endif; ?>
				<?php
				if ( ! empty( $_GET['perform_cache_cloudflare_retryable_failed'] ) ) :
					?>
					<div class="notice notice-error"><p><?php esc_html_e( 'Some Cloudflare URL cleanup batches need a manual retry.', 'perform' ); ?></p></div><?php endif; ?>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="perform_purge_page_cache" />
				<?php wp_nonce_field( 'perform_purge_page_cache' ); ?>
				<?php submit_button( esc_html__( 'Purge Site Page Cache', 'perform' ), 'secondary', 'submit', false ); ?>
			</form>
			<?php if ( $cloudflare['pending'] || $cloudflare['retryable_failed'] || $cloudflare['credential_residuals'] || $residual ) : ?>
				<div class="notice notice-<?php echo $cloudflare['retryable_failed'] || $cloudflare['credential_residuals'] || $residual ? 'error' : 'warning'; ?>"><p><?php echo esc_html( sprintf( __( 'Cloudflare cleanup: %1$d pending, %2$d retryable failures, %3$d old-credential residuals.', 'perform' ), $cloudflare['pending'], $cloudflare['retryable_failed'], max( $cloudflare['credential_residuals'], $residual ) ) ); ?></p></div>
			<?php endif; ?>
			<?php if ( $cloudflare['retryable_failed'] ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="perform_retry_cloudflare_cleanup" />
					<?php wp_nonce_field( 'perform_retry_cloudflare_cleanup' ); ?>
					<?php submit_button( esc_html__( 'Retry Failed Cloudflare Cleanup', 'perform' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>
			<?php if ( $residual ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="perform_acknowledge_cloudflare_residual" />
					<?php wp_nonce_field( 'perform_acknowledge_cloudflare_residual' ); ?>
					<p><?php esc_html_e( 'After purging the old Cloudflare zone externally, acknowledge the residual to retire the old token-free queue and allow local cache cleanup.', 'perform' ); ?></p>
					<?php submit_button( esc_html__( 'Acknowledge External Cloudflare Purge', 'perform' ), 'secondary', 'submit', false ); ?>
				</form>
			<?php endif; ?>

			<table class="widefat striped" style="max-width:900px;">
				<tbody>
					<tr><th><?php esc_html_e( 'Cache Hit Ratio', 'perform' ); ?></th><td><?php echo esc_html( $hit_ratio . '%' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Hits', 'perform' ); ?></th><td><?php echo esc_html( $hits ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Stale Hits', 'perform' ); ?></th><td><?php echo esc_html( $stale_hits ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Misses', 'perform' ); ?></th><td><?php echo esc_html( $misses ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Bypasses', 'perform' ); ?></th><td><?php echo esc_html( (int) ( $stats['bypasses'] ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Lock Waits', 'perform' ); ?></th><td><?php echo esc_html( (int) ( $stats['lock_waits'] ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Preload Queue Size', 'perform' ); ?></th><td><?php echo esc_html( (int) ( $stats['preload_queue_size'] ?? 0 ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Preload Requests', 'perform' ); ?></th><td><?php echo esc_html( (int) ( $stats['preload_requests'] ?? 0 ) ); ?></td></tr>
				</tbody>
			</table>

			<h2><?php esc_html_e( 'Top Missed URLs', 'perform' ); ?></h2>
			<?php $this->render_stat_map_table( $top_misses, esc_html__( 'Misses', 'perform' ) ); ?>

			<h2><?php esc_html_e( 'Bypass Reasons', 'perform' ); ?></h2>
			<?php $this->render_stat_map_table( $bypass_reasons, esc_html__( 'Bypasses', 'perform' ), esc_html__( 'Reason', 'perform' ) ); ?>

			<h2><?php esc_html_e( 'Slow Uncached URLs (ms)', 'perform' ); ?></h2>
			<?php $this->render_stat_map_table( $slow_uncached, esc_html__( 'Render Time (ms)', 'perform' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Render map table for stats.
	 *
	 * @param array<string, int|float> $map Stat map.
	 * @param string                    $value_header Value header label.
	 * @param string                    $key_header Key header label.
	 *
	 * @return void
	 */
	private function render_stat_map_table( $map, $value_header, $key_header = '' ) {
		if ( ! is_array( $map ) || empty( $map ) ) {
			echo '<p>' . esc_html__( 'No data yet.', 'perform' ) . '</p>';
			return;
		}

		if ( '' === $key_header ) {
			$key_header = esc_html__( 'URL', 'perform' );
		}
		?>
		<table class="widefat striped" style="max-width:1200px;">
			<thead>
				<tr>
					<th><?php echo esc_html( $key_header ); ?></th>
					<th><?php echo esc_html( $value_header ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $map as $url => $value ) : ?>
					<tr>
						<td style="word-break:break-all;"><?php echo esc_html( $url ); ?></td>
						<td><?php echo esc_html( (string) $value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Compute URLs impacted by content update.
	 *
	 * @param int $post_id Post id.
	 *
	 * @return array<int, string>
	 */
	private function get_invalidation_urls_for_post( $post_id ) {
		$urls = [ home_url( '/' ) ];

		$permalink = get_permalink( $post_id );
		if ( ! empty( $permalink ) ) {
			$urls[] = $permalink;
		}

		$post_type = get_post_type( $post_id );
		if ( ! empty( $post_type ) ) {
			$archive = get_post_type_archive_link( $post_type );
			if ( ! empty( $archive ) ) {
				$urls[] = $archive;
			}
		}

		$taxonomies = get_object_taxonomies( $post_type, 'names' );
		if ( is_array( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$terms = get_the_terms( $post_id, $taxonomy );
				if ( empty( $terms ) || is_wp_error( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term ) {
					$link = get_term_link( $term );
					if ( ! is_wp_error( $link ) ) {
						$urls[] = $link;
					}
				}
			}
		}

		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * Get invalidation URLs for a bounded set of posts.
	 *
	 * @param array<int, int> $post_ids Post IDs.
	 * @return array<int, string>
	 */
	private function get_invalidation_urls_for_posts( array $post_ids ) {
		$urls = [];
		foreach ( array_slice( array_unique( array_map( 'intval', $post_ids ) ), 0, $this->get_term_object_limit() ) as $post_id ) {
			$urls = array_merge( $urls, $this->get_invalidation_urls_for_post( $post_id ) );
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Find a bounded set of posts related to a term.
	 *
	 * @return array{ids: array<int, int>, overflow: bool}
	 */
	private function get_affected_object_ids_for_term( int $term_id, string $taxonomy ) {
		if ( '' === $taxonomy || ! function_exists( 'get_posts' ) ) {
			return [
				'ids'      => [],
				'overflow' => false,
			];
		}
		$limit      = $this->get_term_object_limit();
		$object_ids = get_posts(
			[
				'fields'         => 'ids',
				'posts_per_page' => $limit + 1,
				'post_status'    => 'any',
				'no_found_rows'  => true,
				'tax_query'      => [
					[
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => [ $term_id ],
					],
				],
			]
		);
		$object_ids = is_array( $object_ids ) ? array_map( 'intval', $object_ids ) : [];
		return [
			'ids'      => array_slice( $object_ids, 0, $limit ),
			'overflow' => count( $object_ids ) > $limit,
		];
	}

	/** @return int */
	private function get_term_object_limit() {
		return max( 1, (int) apply_filters( 'perform_cache_term_invalidation_object_limit', 100 ) );
	}

	/**
	 * Purge a specific URL from local cache and Cloudflare.
	 *
	 * @param string $url Target URL.
	 *
	 * @return void
	 */
	private function purge_url( $url ) {
		$key  = $this->get_cache_key_for_url( $this->normalize_url( $url ) );
		$meta = $this->get_meta_file_path( $key );
		$body = $this->get_body_file_path( $key );

		if ( file_exists( $meta ) ) {
			wp_delete_file( $meta );
		}

		if ( file_exists( $body ) ) {
			wp_delete_file( $body );
		}

		$this->purge_cloudflare_url( $url );
	}

	/** Handle the explicit administrator-only site cache purge. */
	public function handle_manual_purge(): void {
		if ( ! $this->is_manual_purge_authorized() ) {
			wp_die( esc_html__( 'You are not allowed to purge the page cache.', 'perform' ) );
		}

		$this->purge_site_cache();
		$status = $this->get_cloudflare_queue_status();
		wp_safe_redirect(
			add_query_arg(
				[
					'perform_cache_purged'             => '1',
					'perform_cache_cloudflare_pending' => $status['pending'],
					'perform_cache_cloudflare_retryable_failed' => $status['retryable_failed'],
				],
				admin_url( 'options-general.php?page=perform_cache_observability' )
			)
		);
		exit;
	}

	/** Retire explicitly acknowledged old-credential Cloudflare work. */
	public function handle_cloudflare_residual_acknowledgement(): void {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'perform_acknowledge_cloudflare_residual' ) ) {
			wp_die( esc_html__( 'You are not allowed to acknowledge Cloudflare cleanup.', 'perform' ) );
		}
		$this->acknowledge_cloudflare_credential_residuals();
		$this->schedule_cleanup();
		wp_safe_redirect( admin_url( 'options-general.php?page=perform_cache_observability' ) );
		exit;
	}

	/** Retry failed cleanup records that still match the current credentials. */
	public function handle_cloudflare_cleanup_retry(): void {
		if ( ! $this->is_cloudflare_retry_authorized() ) {
			wp_die( esc_html__( 'You are not allowed to retry Cloudflare cleanup.', 'perform' ) );
		}
		$this->retry_current_cloudflare_failures();
		wp_safe_redirect( admin_url( 'options-general.php?page=perform_cache_observability' ) );
		exit;
	}

	/** Retire only records that cannot use the current credentials. */
	private function acknowledge_cloudflare_credential_residuals(): void {
		$key     = 'perform_cache_cloudflare_queue_' . $this->get_blog_id();
		$records = $this->get_cloudflare_queue_records( get_option( $key, [] ) );
		$records = array_values(
			array_filter(
				$records,
				static function ( $record ) {
					return empty( $record['credential_residual'] );
				}
			)
		);
		update_option( $key, $records, false );
		delete_option( 'perform_cache_cloudflare_credential_residual_' . $this->get_blog_id() );
	}

	/** Reset only retryable failures that match the currently configured edge credentials. */
	private function retry_current_cloudflare_failures(): int {
		$settings = Helpers::get_settings();
		if ( ! is_array( $settings ) ) {
			return 0;
		}
		$key         = 'perform_cache_cloudflare_queue_' . $this->get_blog_id();
		$records     = $this->get_cloudflare_queue_records( get_option( $key, [] ) );
		$fingerprint = $this->cloudflare_settings_key( $settings );
		$retries     = 0;
		foreach ( $records as &$record ) {
			if ( empty( $record['failed'] ) || ! empty( $record['credential_residual'] ) || ( $record['fingerprint'] ?? '' ) !== $fingerprint ) {
				continue;
			}
			$record['failed']   = false;
			$record['attempts'] = 0;
			++$retries;
		}
		unset( $record );
		if ( $retries ) {
			update_option( $key, $records, false );
			if ( ! wp_next_scheduled( 'perform_cache_cloudflare_purge_event' ) ) {
				wp_schedule_single_event( time() + 10, 'perform_cache_cloudflare_purge_event' );
			}
		}
		return $retries;
	}

	/** Confirm the administrator capability and nonce before retrying current Cloudflare cleanup. */
	private function is_cloudflare_retry_authorized(): bool {
		return current_user_can( 'manage_options' ) && (bool) check_admin_referer( 'perform_retry_cloudflare_cleanup' );
	}

	/** Confirm the administrator capability and action nonce before purging. */
	private function is_manual_purge_authorized(): bool {
		return current_user_can( 'manage_options' ) && (bool) check_admin_referer( 'perform_purge_page_cache' );
	}

	/**
	 * Queue URLs for warmup.
	 *
	 * @param array<int, string> $urls URLs.
	 *
	 * @return void
	 */
	private function queue_urls_for_preload( $urls ) {
		$queue = get_option( 'perform_cache_preload_queue', [] );
		if ( ! is_array( $queue ) ) {
			$queue = [];
		}

		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) || '' === $url || ! $this->is_site_url( $url ) ) {
				continue;
			}
			$queue[] = esc_url_raw( $url );
		}

		$queue = array_values( array_unique( array_filter( $queue ) ) );
		$queue = array_slice( $queue, 0, 2000 );

		update_option( 'perform_cache_preload_queue', $queue, false );
		$this->set_stat_value( 'preload_queue_size', count( $queue ) );
	}

	/**
	 * Fetch URLs from WP sitemap.
	 *
	 * @return array<int, string>
	 */
	private function fetch_urls_from_sitemap() {
		$index_url = home_url( '/wp-sitemap.xml' );
		$response  = wp_remote_get( $index_url, [ 'timeout' => 8 ] );
		if ( is_wp_error( $response ) ) {
			return [];
		}

		$xml = wp_remote_retrieve_body( $response );
		if ( empty( $xml ) ) {
			return [];
		}

		libxml_use_internal_errors( true );
		$index = simplexml_load_string( $xml );
		if ( false === $index ) {
			return [];
		}

		$urls                   = [];
		$url_cap                = max( 1, (int) $this->preload_sitemap_url_cap );
		$child_sitemap_limit    = max( 1, (int) $this->preload_sitemap_child_limit );
		$child_sitemaps_fetched = 0;

		if ( isset( $index->sitemap ) ) {
			foreach ( $index->sitemap as $sitemap ) {
				if ( count( $urls ) >= $url_cap || $child_sitemaps_fetched >= $child_sitemap_limit ) {
					break;
				}

				if ( empty( $sitemap->loc ) ) {
					continue;
				}

				$child_sitemap_url = esc_url_raw( (string) $sitemap->loc );
				if ( ! $this->is_site_url( $child_sitemap_url ) ) {
					continue;
				}

				++$child_sitemaps_fetched;
				$child_response = wp_remote_get( $child_sitemap_url, [ 'timeout' => 8 ] );
				if ( is_wp_error( $child_response ) ) {
					continue;
				}

				$child_xml = wp_remote_retrieve_body( $child_response );
				if ( empty( $child_xml ) ) {
					continue;
				}

				$child = simplexml_load_string( $child_xml );
				if ( false === $child || ! isset( $child->url ) ) {
					continue;
				}

				foreach ( $child->url as $item ) {
					if ( count( $urls ) >= $url_cap ) {
						break;
					}

					if ( ! empty( $item->loc ) ) {
						$item_url = esc_url_raw( (string) $item->loc );
						if ( $this->is_site_url( $item_url ) ) {
							$urls[ $item_url ] = true;
						}
					}
				}
			}
		}

		return array_keys( $urls );
	}

	/**
	 * Adaptive preload batch size based on host load and resources.
	 *
	 * @return int
	 */
	private function get_adaptive_preload_batch_size() {
		$batch = 5;

		if ( function_exists( 'sys_getloadavg' ) ) {
			$load = sys_getloadavg();
			if ( is_array( $load ) && isset( $load[0] ) ) {
				if ( $load[0] >= 4 ) {
					$batch = 1;
				} elseif ( $load[0] >= 2 ) {
					$batch = 2;
				}
			}
		}

		$memory_limit = wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) );
		if ( $memory_limit > 0 && $memory_limit <= 134217728 ) {
			$batch = min( $batch, 2 );
		}

		return max( 1, (int) apply_filters( 'perform_cache_preload_batch_size', $batch ) );
	}

	/**
	 * Purge Cloudflare URL when enabled.
	 *
	 * @param string $url URL.
	 *
	 * @return void
	 */
	private function purge_cloudflare_url( $url ) {
		if ( ! $this->purge_cloudflare_urls( [ $url ] ) ) {
			$this->queue_cloudflare_inline_urls( [ $url ] );
		}
	}

	/**
	 * Purge a bounded set of Cloudflare URLs.
	 *
	 * @param array<int, string> $urls URLs to purge.
	 *
	 * @return bool
	 */
	/**
	 * @param array<int, string>        $urls URLs.
	 * @param array<string, mixed>|null $settings Settings.
	 */
	private function purge_cloudflare_urls( array $urls, $settings = null ): bool {
		$settings = is_array( $settings ) ? $settings : Helpers::get_settings();
		if ( ! is_array( $settings ) ) {
			return false;
		}
		$enabled = ! empty( $settings['enable_cloudflare_cache_sync'] );
		if ( empty( $enabled ) ) {
			return true;
		}

		$zone_id = trim( (string) ( $settings['cloudflare_zone_id'] ?? '' ) );
		$token   = trim( (string) ( $settings['cloudflare_api_token'] ?? '' ) );
		if ( '' === $zone_id || '' === $token ) {
			return false;
		}

		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', (array) $urls ) ) ) );
		if ( empty( $urls ) ) {
			return true;
		}

		$response = wp_remote_post(
			'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone_id ) . '/purge_cache',
			[
				'timeout' => 10,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( [ 'files' => $urls ] ),
			]
		);

		if ( is_wp_error( $response ) || empty( $response['response']['code'] ) || 200 > (int) $response['response']['code'] || 300 <= (int) $response['response']['code'] ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) && ! empty( $body['success'] );
	}

	/**
	 * Send debug cache header.
	 *
	 * @param string $state Cache state.
	 *
	 * @return void
	 */
	private function send_cache_headers( $state ) {
		if ( ! headers_sent() ) {
			header( 'X-Perform-Cache: ' . sanitize_text_field( $state ) );
		}
	}

	/**
	 * Trigger async regeneration request.
	 *
	 * @param string $url URL.
	 *
	 * @return void
	 */
	private function maybe_trigger_async_regeneration( $url ) {
		if ( get_transient( $this->lock_key ) || ! $this->is_site_url( $url ) ) {
			return;
		}

		$regen_token = $this->create_regen_token( $url );
		if ( ! set_transient( $this->lock_key, $regen_token, 30 ) ) {
			return;
		}

		wp_remote_post(
			esc_url_raw( $url ),
			[
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'headers'   => [
					'X-Perform-Cache-Regen' => $regen_token,
				],
			]
		);
	}

	/**
	 * Create an opaque token for an internal cache regeneration request.
	 *
	 * @param string $url URL being regenerated.
	 *
	 * @return string
	 */
	private function create_regen_token( $url ) {
		return md5( $url . '|' . microtime( true ) . '|' . wp_rand( 1, PHP_INT_MAX ) );
	}

	/**
	 * Confirm a URL belongs to the current site before internal HTTP warmups.
	 *
	 * @param string $url URL to verify.
	 *
	 * @return bool
	 */
	private function is_site_url( $url ) {
		if ( ! is_string( $url ) || '' === trim( $url ) ) {
			return false;
		}

		$site_parts = wp_parse_url( home_url( '/' ) );
		$url_parts  = wp_parse_url( $url );

		if ( empty( $site_parts['host'] ) || false === $url_parts ) {
			return false;
		}

		if ( empty( $url_parts['host'] ) ) {
			$url_parts = wp_parse_url( home_url( $url ) );
		}

		if ( false === $url_parts || empty( $url_parts['host'] ) ) {
			return false;
		}

		$scheme = isset( $url_parts['scheme'] ) ? strtolower( (string) $url_parts['scheme'] ) : '';
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return false;
		}

		$site_port = isset( $site_parts['port'] ) ? (int) $site_parts['port'] : null;
		$url_port  = isset( $url_parts['port'] ) ? (int) $url_parts['port'] : null;

		return strtolower( (string) $site_parts['host'] ) === strtolower( (string) $url_parts['host'] )
			&& $site_port === $url_port;
	}

	/**
	 * Whether current request should bypass cache.
	 *
	 * @return bool
	 */
	private function is_cacheable_request() {
		$this->current_bypass_reason = $this->get_cache_bypass_reason();

		return '' === $this->current_bypass_reason;
	}

	/**
	 * Get the reason the current request should bypass page cache.
	 *
	 * @return string Empty string when cacheable.
	 */
	private function get_cache_bypass_reason() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return 'runtime_context';
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return 'runtime_context';
		}

		if ( is_user_logged_in() || is_preview() || is_feed() || is_trackback() || is_robots() || is_search() ) {
			return 'wordpress_context';
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
			return 'http_method';
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path        = '' !== $request_uri ? wp_parse_url( $request_uri, PHP_URL_PATH ) : '';
		if ( is_string( $path ) ) {
			$path          = $this->normalize_request_path( $path );
			$blocked_paths = [
				'/cart',
				'/checkout',
				'/my-account',
				'/wc-api',
			];
			foreach ( $blocked_paths as $blocked_path ) {
				if ( 0 === strpos( $path, $blocked_path ) ) {
					return 'default_path';
				}
			}

			if ( $this->path_matches_exact_bypass_rule( $path ) ) {
				return 'path_exact';
			}

			if ( $this->path_matches_prefix_bypass_rule( $path ) ) {
				return 'path_prefix';
			}
		}

		if ( isset( $_GET['add-to-cart'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache bypass signal.
			return 'default_query';
		}

		if ( $this->request_has_bypass_query_key() ) {
			return 'query_key';
		}

		$cookies        = $_COOKIE; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$bypass_cookies = [
			'woocommerce_items_in_cart',
			'woocommerce_cart_hash',
			'wp_woocommerce_session_',
			'comment_author_',
			'wordpress_logged_in_',
			'wordpress_sec_',
		];
		foreach ( $cookies as $cookie_name => $cookie_value ) {
			foreach ( $bypass_cookies as $prefix ) {
				if ( 0 === strpos( (string) $cookie_name, $prefix ) ) {
					return 'default_cookie';
				}
			}
		}

		if ( $this->request_has_bypass_cookie_name() ) {
			return 'cookie_name';
		}

		if ( $this->request_has_bypass_cookie_prefix() ) {
			return 'cookie_prefix';
		}

		$custom_reason = $this->get_custom_bypass_reason(
			[
				'method'       => $method,
				'path'         => is_string( $path ) ? $path : '',
				'query_keys'   => $this->get_request_query_keys(),
				'cookie_names' => $this->get_request_cookie_names(),
				'request_uri'  => $request_uri,
			]
		);

		return $custom_reason;
	}

	/**
	 * Normalize a request path for case-insensitive matching.
	 *
	 * @param string $path Request path.
	 *
	 * @return string
	 */
	private function normalize_request_path( $path ) {
		$path = '/' . ltrim( strtolower( rawurldecode( (string) $path ) ), '/' );
		$path = rtrim( $path, '/' );

		return '' === $path ? '/' : $path;
	}

	/**
	 * Determine if the request path matches an exact exclusion rule.
	 *
	 * @param string $path Normalized request path.
	 *
	 * @return bool
	 */
	private function path_matches_exact_bypass_rule( $path ) {
		$rules = $this->get_cache_bypass_rules( 'cache_bypass_exact_paths' );
		foreach ( $rules as $rule ) {
			if ( $path === $this->normalize_request_path( $rule ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the request path matches a prefix exclusion rule.
	 *
	 * @param string $path Normalized request path.
	 *
	 * @return bool
	 */
	private function path_matches_prefix_bypass_rule( $path ) {
		$rules = $this->get_cache_bypass_rules( 'cache_bypass_path_prefixes' );
		foreach ( $rules as $rule ) {
			$prefix = $this->normalize_request_path( $rule );
			if ( '/' === $prefix || 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if any configured query key should bypass cache.
	 *
	 * @return bool
	 */
	private function request_has_bypass_query_key() {
		$bypass_keys = $this->get_cache_bypass_rules( 'cache_bypass_query_params' );
		if ( empty( $bypass_keys ) ) {
			return false;
		}

		$bypass_keys = array_fill_keys( array_map( [ $this, 'normalize_rule_token' ], $bypass_keys ), true );
		foreach ( $this->get_request_query_keys() as $query_key ) {
			if ( isset( $bypass_keys[ $this->normalize_rule_token( $query_key ) ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if any configured cookie name should bypass cache.
	 *
	 * @return bool
	 */
	private function request_has_bypass_cookie_name() {
		$bypass_names = $this->get_cache_bypass_rules( 'cache_bypass_cookie_names' );
		if ( empty( $bypass_names ) ) {
			return false;
		}

		$bypass_names = array_fill_keys( array_map( [ $this, 'normalize_rule_token' ], $bypass_names ), true );
		foreach ( $this->get_request_cookie_names() as $cookie_name ) {
			if ( isset( $bypass_names[ $this->normalize_rule_token( $cookie_name ) ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if any configured cookie prefix should bypass cache.
	 *
	 * @return bool
	 */
	private function request_has_bypass_cookie_prefix() {
		$prefixes = $this->get_cache_bypass_rules( 'cache_bypass_cookie_prefixes' );
		if ( empty( $prefixes ) ) {
			return false;
		}

		$prefixes = array_map( [ $this, 'normalize_rule_token' ], $prefixes );
		foreach ( $this->get_request_cookie_names() as $cookie_name ) {
			$cookie_name = $this->normalize_rule_token( $cookie_name );
			foreach ( $prefixes as $prefix ) {
				if ( '' !== $prefix && 0 === strpos( $cookie_name, $prefix ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get normalized configured bypass rules from settings.
	 *
	 * @param string $option Settings key.
	 *
	 * @return array<int, string>
	 */
	private function get_cache_bypass_rules( $option ) {
		$value = Helpers::get_option( $option, 'perform_settings', [] );
		if ( is_string( $value ) ) {
			$value = preg_split( '/[\r\n,]+/', $value );
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		$rules = [];
		foreach ( $value as $rule ) {
			if ( ! is_scalar( $rule ) ) {
				continue;
			}

			$rule = trim( (string) $rule );
			if ( '' === $rule ) {
				continue;
			}

			$rules[] = $rule;
		}

		return array_values( array_unique( $rules ) );
	}

	/**
	 * Get request query keys without query values.
	 *
	 * @return array<int, string>
	 */
	private function get_request_query_keys() {
		return array_map( 'strval', array_keys( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only cache bypass signal.
	}

	/**
	 * Get request cookie names without cookie values.
	 *
	 * @return array<int, string>
	 */
	private function get_request_cookie_names() {
		return array_map( 'strval', array_keys( $_COOKIE ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Names only.
	}

	/**
	 * Normalize a query or cookie rule token for case-insensitive matching.
	 *
	 * @param string $token Rule token.
	 *
	 * @return string
	 */
	private function normalize_rule_token( $token ) {
		return strtolower( trim( rawurldecode( (string) $token ) ) );
	}

	/**
	 * Get optional custom bypass reason from advanced filter logic.
	 *
	 * @param array<string, mixed> $context Request context without raw query or cookie values.
	 *
	 * @return string Empty string when cacheable.
	 */
	private function get_custom_bypass_reason( array $context ) {
		$reason = apply_filters( 'perform_page_cache_bypass_reason', '', $context );

		if ( true === $reason ) {
			return 'custom_filter';
		}

		if ( is_scalar( $reason ) && '' !== trim( (string) $reason ) ) {
			return $this->normalize_stat_key( (string) $reason );
		}

		return '';
	}

	/**
	 * Record aggregate bypass reason stats.
	 *
	 * @param string $reason Bypass reason.
	 * @param int    $increment Increment amount.
	 *
	 * @return void
	 */
	private function record_bypass_reason( $reason, $increment ) {
		$reason = '' !== $reason ? $reason : 'unknown';
		$reason = $this->normalize_stat_key( $reason );

		$reasons            = $this->get_stat_map( 'bypass_reasons' );
		$reasons[ $reason ] = ( $reasons[ $reason ] ?? 0 ) + $increment;
		$this->set_stat_map( 'bypass_reasons', $reasons );
	}

	/**
	 * Normalize a string for use as a stats-map key.
	 *
	 * @param string $key Stats key.
	 *
	 * @return string
	 */
	private function normalize_stat_key( $key ) {
		$key = strtolower( sanitize_text_field( $key ) );
		$key = preg_replace( '/[^a-z0-9_\-]+/', '_', $key );

		return is_string( $key ) && '' !== trim( $key, '_' ) ? trim( $key, '_' ) : 'custom_filter';
	}

	/**
	 * Whether the rendered response is safe to persist as a page-cache entry.
	 *
	 * @param string $html Rendered response body.
	 *
	 * @return bool
	 */
	private function is_cacheable_response( $html ) {
		$current_status = http_response_code();
		$status_code    = false === $current_status ? 200 : (int) $current_status;
		if ( 200 !== $status_code ) {
			return false;
		}

		$has_content_type = false;

		foreach ( headers_list() as $header_line ) {
			if ( 0 === stripos( $header_line, 'Set-Cookie:' ) ) {
				return false;
			}

			if ( 0 === stripos( $header_line, 'Location:' ) ) {
				return false;
			}

			if ( 0 === stripos( $header_line, 'Content-Type:' ) ) {
				$has_content_type = true;

				if ( false === stripos( $header_line, 'text/html' ) ) {
					return false;
				}
			}
		}

		if ( ! $has_content_type && ! $this->looks_like_html_response( $html ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Lightweight fallback for hosts that have not sent Content-Type yet.
	 *
	 * @param string $html Rendered response body.
	 *
	 * @return bool
	 */
	private function looks_like_html_response( $html ) {
		$body_start = ltrim( substr( $html, 0, 1024 ) );

		return 0 === stripos( $body_start, '<!doctype html' )
			|| 0 === stripos( $body_start, '<html' )
			|| false !== stripos( $body_start, '<html' );
	}

	/**
	 * Normalize current request URL.
	 *
	 * @return string
	 */
	private function get_normalized_request_url() {
		$url = home_url( add_query_arg( [] ) );
		return $this->normalize_url( $url );
	}

	/**
	 * Normalize URL for cache keying.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function normalize_url( $url ) {
		$service = $this->get_url_normalizer();
		return $service->normalize( $url );
	}

	/**
	 * Get cache key for normalized URL.
	 *
	 * @param string $normalized_url Normalized URL.
	 *
	 * @return string
	 */
	private function get_cache_key_for_url( $normalized_url ) {
		$service = $this->get_url_normalizer();
		return $service->key_for_url( $normalized_url );
	}

	/**
	 * Determine if request is internal regeneration.
	 *
	 * @return bool
	 */
	private function is_internal_regen_request() {
		if ( '' === $this->lock_key || empty( $_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] ) ) {
			return false;
		}

		$expected_token = get_transient( $this->lock_key );
		if ( ! is_string( $expected_token ) || '' === $expected_token ) {
			return false;
		}

		$provided_token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] ) );

		return hash_equals( $expected_token, $provided_token );
	}

	/**
	 * Ensure cache dir exists.
	 *
	 * @return void
	 */
	private function ensure_cache_dir() {
		$generation = 0 < $this->request_generation ? $this->request_generation : $this->get_cache_generation();
		$directory  = $this->get_generation_dir( $generation );
		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}
	}

	/**
	 * Acquire stampede lock.
	 *
	 * @return bool
	 */
	private function acquire_lock() {
		if ( '' === $this->lock_key ) {
			return false;
		}

		if ( get_transient( $this->lock_key ) ) {
			return false;
		}

		return set_transient( $this->lock_key, $this->create_regen_token( $this->current_url ), 30 );
	}

	/**
	 * Release stampede lock.
	 *
	 * @return void
	 */
	private function release_lock() {
		if ( '' !== $this->lock_key ) {
			delete_transient( $this->lock_key );
		}
	}

	/**
	 * Get body file path.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string
	 */
	private function get_body_file_path( $key ) {
		$generation = 0 < $this->request_generation ? $this->request_generation : $this->get_cache_generation();
		return $this->get_generation_dir( $generation ) . $key . '.html';
	}

	/**
	 * Get meta file path.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string
	 */
	private function get_meta_file_path( $key ) {
		$generation = 0 < $this->request_generation ? $this->request_generation : $this->get_cache_generation();
		return $this->get_generation_dir( $generation ) . $key . '.meta.json';
	}

	/** Return the current site's cache generation option name. */
	private function get_generation_option_name(): string {
		return 'perform_cache_generation_' . $this->get_blog_id();
	}

	/** Get the current cache generation, initializing legacy sites at one. */
	private function get_cache_generation(): int {
		$generation = (int) get_option( $this->get_generation_option_name(), 1 );
		return max( 1, $generation );
	}

	/** Get a multisite-isolated generation directory. */
	private function get_generation_dir( int $generation ): string {
		return $this->cache_dir . 'site-' . $this->get_blog_id() . '/generation-' . max( 1, (int) $generation ) . '/';
	}

	/** Get the current site ID without making a network-wide assumption. */
	private function get_blog_id(): int {
		return function_exists( 'get_current_blog_id' ) ? max( 1, (int) get_current_blog_id() ) : 1;
	}

	/** Whether frontend cache reads and writes are enabled. */
	private function page_cache_enabled(): bool {
		return ! empty( Helpers::get_option( 'enable_page_cache', 'perform_settings', false ) );
	}

	/** Schedule bounded cleanup of retired local generations. */
	private function schedule_cleanup(): void {
		if ( ! wp_next_scheduled( 'perform_cache_cleanup_event' ) ) {
			wp_schedule_single_event( time() + 10, 'perform_cache_cleanup_event' );
		}
	}

	/** Delete a bounded number of files from retired generations. */
	public function cleanup_obsolete_generations(): void {
		$site_dir  = $this->cache_dir . 'site-' . $this->get_blog_id() . '/';
		$remaining = max( 1, (int) $this->cleanup_batch_size );
		$has_more  = false;
		if ( $this->has_pending_metadata_inventory() ) {
			$this->schedule_cleanup();
			return;
		}
		$has_more = $this->cleanup_directory_files( $this->cache_dir, $remaining, true );
		$current  = $this->get_generation_dir( $this->get_cache_generation() );
		if ( $remaining > 0 && is_dir( $site_dir ) ) {
			foreach ( new \DirectoryIterator( $site_dir ) as $directory ) {
				if ( $directory->isDot() || ! $directory->isDir() || 0 !== strpos( $directory->getFilename(), 'generation-' ) || trailingslashit( $directory->getPathname() ) === $current ) {
					continue;
				}
				if ( $this->cleanup_directory_files( $directory->getPathname(), $remaining, false ) ) {
					$has_more = true;
					break;
				}
				if ( ! $this->directory_has_files( $directory->getPathname() ) ) {
					rmdir( $directory->getPathname() ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				}
			}
		}

		if ( $has_more || $remaining <= 0 ) {
			wp_schedule_single_event( time() + 30, 'perform_cache_cleanup_event' );
		}
	}

	private function cleanup_directory_files( string $directory, int &$remaining, bool $legacy_root ): bool {
		if ( ! is_dir( $directory ) ) {
			return false;
		}
		foreach ( new \DirectoryIterator( $directory ) as $file ) {
			if ( $file->isDot() || ! $file->isFile() ) {
				continue;
			}
			$name = $file->getFilename();
			if ( $legacy_root && ! preg_match( '/^[a-f0-9]{32}(?:\.html|\.meta\.json)$/', $name ) ) {
				continue;
			}
			if ( $remaining <= 0 ) {
				return true;
			}
			wp_delete_file( $file->getPathname() );
			--$remaining;
		}
		return $remaining <= 0 && $this->directory_has_files( $directory, $legacy_root );
	}

	private function directory_has_files( string $directory, bool $legacy_root = false ): bool {
		foreach ( new \DirectoryIterator( $directory ) as $file ) {
			if ( $file->isDot() || ! $file->isFile() ) {
				continue;
			}
			if ( ! $legacy_root || preg_match( '/^[a-f0-9]{32}(?:\.html|\.meta\.json)$/', $file->getFilename() ) ) {
				return true;
			}
		}
		return false;
	}

	/** Queue metadata inventory instead of using Cloudflare purge_everything. */
	/** @param array<string,mixed>|null $settings */
	private function queue_cloudflare_tracked_urls( $settings = null, ?int $generation = null ): void {
		$settings = is_array( $settings ) ? $settings : Helpers::get_settings();
		if ( ! is_array( $settings ) || empty( $settings['enable_cloudflare_cache_sync'] ) ) {
			return;
		}
		$blog_id    = $this->get_blog_id();
		$generation = null === $generation ? $this->get_cache_generation() : $generation;
		$key        = 'perform_cache_cloudflare_queue_' . $blog_id;
		$records    = $this->get_cloudflare_queue_records( get_option( $key, [] ) );
		$records    = $this->queue_cloudflare_source( $records, 'metadata', $generation, $settings );
		if ( $this->has_legacy_metadata_files() ) {
			$records = $this->queue_cloudflare_source( $records, 'legacy_metadata', null, $settings );
		}
		$legacy = get_option( 'perform_cache_urls_' . $blog_id, [] );
		if ( is_array( $legacy ) && ! empty( $legacy ) ) {
			$records = $this->queue_cloudflare_source( $records, 'legacy_option', null, $settings );
		}
		update_option( $key, $records, false );
		if ( ! wp_next_scheduled( 'perform_cache_cloudflare_purge_event' ) ) {
			wp_schedule_single_event( time() + 10, 'perform_cache_cloudflare_purge_event' );
		}
	}

	private function has_legacy_metadata_files(): bool {
		if ( ! is_dir( $this->cache_dir ) ) {
			return false;
		}
		foreach ( new \DirectoryIterator( $this->cache_dir ) as $file ) {
			if ( ! $file->isDot() && $file->isFile() && preg_match( '/^[a-f0-9]{32}\.meta\.json$/', $file->getFilename() ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<int, array<string, mixed>> $records
	 * @param array<string, mixed> $settings
	 * @return array<int, array<string, mixed>>
	 */
	private function queue_cloudflare_source( array $records, string $source, ?int $generation, array $settings ): array {
		$fingerprint = $this->cloudflare_settings_key( $settings );
		foreach ( $records as &$record ) {
			if ( ! in_array( $source, [ $record['source'] ?? '' ], true ) || ! in_array( (int) $generation, [ (int) ( $record['generation'] ?? 0 ) ], true ) ) {
				continue;
			}
			if ( ( $record['fingerprint'] ?? '' ) === $fingerprint && ! empty( $record['failed'] ) ) {
				$record['failed']   = false;
				$record['attempts'] = 0;
			}
			unset( $record );
			return $records;
		}
		unset( $record );
		$records[] = [
			'source'      => $source,
			'generation'  => $generation,
			'cursor'      => 0,
			'zone_id'     => (string) $settings['cloudflare_zone_id'],
			'fingerprint' => $fingerprint,
			'enabled'     => ! empty( $settings['enable_cloudflare_cache_sync'] ),
			'attempts'    => 0,
			'failed'      => false,
		];
		return $records;
	}

	/** @param array<int, string> $urls */
	private function queue_cloudflare_inline_urls( array $urls ): void {
		$settings = Helpers::get_settings();
		if ( ! is_array( $settings ) || empty( $settings['enable_cloudflare_cache_sync'] ) ) {
			return;
		}
		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
		if ( empty( $urls ) ) {
			return;
		}
		$key         = 'perform_cache_cloudflare_queue_' . $this->get_blog_id();
		$records     = $this->get_cloudflare_queue_records( get_option( $key, [] ) );
		$limit       = max( 1, (int) $this->cloudflare_batch_size );
		$fingerprint = $this->cloudflare_settings_key( $settings );
		foreach ( $records as &$record ) {
			if ( ! empty( $record['source'] ) || ! in_array( $fingerprint, [ $record['fingerprint'] ?? '' ], true ) || ! empty( $record['failed'] ) ) {
				continue;
			}
			$current  = isset( $record['urls'] ) && is_array( $record['urls'] ) ? $record['urls'] : [];
			$capacity = $limit - count( $current );
			if ( $capacity <= 0 ) {
				continue;
			}
			$record['urls'] = array_values( array_unique( array_merge( $current, array_splice( $urls, 0, $capacity ) ) ) );
		}
		unset( $record );
		while ( ! empty( $urls ) ) {
			$records[] = [
				'urls'        => array_splice( $urls, 0, $limit ),
				'zone_id'     => (string) $settings['cloudflare_zone_id'],
				'fingerprint' => $fingerprint,
				'enabled'     => true,
				'attempts'    => 0,
				'failed'      => false,
			];
		}
		update_option( $key, $records, false );
		if ( ! wp_next_scheduled( 'perform_cache_cloudflare_purge_event' ) ) {
			wp_schedule_single_event( time() + 10, 'perform_cache_cloudflare_purge_event' );
		}
	}

	/** Purge a bounded batch of tracked URLs from Cloudflare, with retries. */
	public function run_cloudflare_purge_batch(): void {
		$key     = 'perform_cache_cloudflare_queue_' . $this->get_blog_id();
		$records = $this->get_cloudflare_queue_records( get_option( $key, [] ) );
		if ( empty( $records ) ) {
			return;
		}
		foreach ( $records as $index => &$record ) {
			if ( ! empty( $record['failed'] ) ) {
				continue;
			}
			$work  = $this->get_cloudflare_record_work( $record );
			$batch = $work['urls'];
			if ( empty( $batch ) ) {
				if ( isset( $work['cursor'] ) && empty( $work['complete'] ) ) {
					$record['cursor'] = $work['cursor'];
					break;
				}
				$this->retire_cloudflare_record( $record );
				$record['done'] = true;
				continue;
			}
			$settings = Helpers::get_settings();
			if ( ! is_array( $settings ) || ( $record['fingerprint'] ?? '' ) !== $this->cloudflare_settings_key( $settings ) ) {
				$record['failed']              = true;
				$record['credential_residual'] = true;
				update_option( 'perform_cache_cloudflare_credential_residual_' . $this->get_blog_id(), $this->get_cloudflare_residual_count( $record, $batch ), false );
			} else {
				$settings['enable_cloudflare_cache_sync'] = array_key_exists( 'enabled', $record ) ? ! empty( $record['enabled'] ) : true;
				if ( $this->purge_cloudflare_urls( $batch, $settings ) ) {
					if ( isset( $work['cursor'] ) ) {
						$record['cursor'] = $work['cursor'];
						if ( ! empty( $work['complete'] ) ) {
							$record['inventory_complete'] = true;
						}
					} elseif ( isset( $record['source'] ) ) {
						$record['offset'] = (int) ( $record['offset'] ?? 0 ) + count( $batch );
					} else {
						$record['urls'] = array_values( array_diff( $record['urls'], $batch ) );
					}
				} else {
					++$record['attempts'];
					$record['failed'] = $record['attempts'] >= max( 1, (int) $this->cloudflare_max_retries );
				}
			}
			break;
		}
		unset( $record );
		$records = array_values(
			array_filter(
				$records,
				static function ( $record ) {
					return ! isset( $record['done'] );
				}
			)
		);
		update_option( $key, $records, false );
		$active = array_filter(
			$records,
			static function ( $record ) {
				return empty( $record['failed'] );
			}
		);
		if ( ! empty( $active ) ) {
			wp_schedule_single_event( time() + 60, 'perform_cache_cloudflare_purge_event' );
		}
	}

	/**
	 * @param array<string, mixed> $record Record.
	 * @return array{urls: array<int, string>, cursor?: int, complete?: bool}
	 */
	private function get_cloudflare_record_work( array $record ): array {
		if ( isset( $record['source'] ) && 'legacy_option' === $record['source'] ) {
			$urls = get_option( 'perform_cache_urls_' . $this->get_blog_id(), [] );
			return [ 'urls' => is_array( $urls ) ? array_slice( $urls, (int) ( $record['offset'] ?? 0 ), max( 1, (int) $this->cloudflare_batch_size ) ) : [] ];
		}
		if ( isset( $record['source'] ) && in_array( $record['source'], [ 'metadata', 'legacy_metadata' ], true ) ) {
			return $this->get_cloudflare_metadata_urls( $record );
		}
		$urls = isset( $record['urls'] ) && is_array( $record['urls'] ) ? $record['urls'] : [];
		return [ 'urls' => array_slice( $urls, 0, max( 1, (int) $this->cloudflare_batch_size ) ) ];
	}

	/**
	 * @param array<string, mixed> $record
	 * @return array{urls: array<int, string>, cursor: int, complete: bool}
	 */
	private function get_cloudflare_metadata_urls( array $record ): array {
		$directory = 'metadata' === $record['source'] ? $this->get_generation_dir( (int) $record['generation'] ) : $this->cache_dir;
		if ( ! is_dir( $directory ) ) {
			return [
				'urls'     => [],
				'cursor'   => (int) ( $record['cursor'] ?? 0 ),
				'complete' => true,
			];
		}

		$cursor  = max( 0, (int) ( $record['cursor'] ?? 0 ) );
		$index   = 0;
		$urls    = [];
		$limit   = max( 1, (int) $this->cloudflare_batch_size );
		$budget  = max( 1, (int) apply_filters( 'perform_cache_cloudflare_metadata_scan_limit', $this->cloudflare_metadata_scan_limit ) );
		$scanned = 0;
		$files   = new \DirectoryIterator( $directory );
		foreach ( $files as $file ) {
			if ( $file->isDot() ) {
				continue;
			}
			if ( $index++ < $cursor ) {
				continue;
			}
			++$scanned;
			if ( ! $file->isFile() || '.meta.json' !== substr( $file->getFilename(), -10 ) ) {
				if ( $scanned >= $budget ) {
					return [
						'urls'     => [],
						'cursor'   => $index,
						'complete' => false,
					];
				}
				continue;
			}
			$raw  = file_get_contents( $file->getPathname() );
			$data = false === $raw ? null : json_decode( $raw, true );
			$url  = is_array( $data ) && isset( $data['url'] ) ? esc_url_raw( (string) $data['url'] ) : '';
			if ( '' !== $url && $this->is_site_url( $url ) ) {
				$urls[] = $url;
			}
			if ( count( $urls ) >= $limit || $scanned >= $budget ) {
				return [
					'urls'     => array_values( array_unique( $urls ) ),
					'cursor'   => $index,
					'complete' => false,
				];
			}
		}
		return [
			'urls'     => array_values( array_unique( $urls ) ),
			'cursor'   => $index,
			'complete' => true,
		];
	}

	/**
	 * @param array<string, mixed> $record
	 * @param array<int, string> $urls
	 */
	private function get_cloudflare_residual_count( array $record, array $urls ): int {
		if ( isset( $record['urls'] ) && is_array( $record['urls'] ) ) {
			return max( 1, count( $record['urls'] ) - (int) ( $record['offset'] ?? 0 ) );
		}
		return max( 1, count( $urls ) );
	}

	/** @param array<string,mixed> $record */
	private function retire_cloudflare_record( array $record ): void {
		if ( isset( $record['source'] ) && 'legacy_option' === $record['source'] ) {
			delete_option( 'perform_cache_urls_' . $this->get_blog_id() );
		}
	}

	/** Keep local metadata until its bounded Cloudflare inventory is drained. */
	private function has_pending_metadata_inventory(): bool {
		$records = $this->get_cloudflare_queue_records( get_option( 'perform_cache_cloudflare_queue_' . $this->get_blog_id(), [] ) );
		foreach ( $records as $record ) {
			if ( in_array( $record['source'] ?? '', [ 'metadata', 'legacy_metadata' ], true ) && empty( $record['done'] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param mixed $records Queue records.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_cloudflare_queue_records( $records ): array {
		if ( ! is_array( $records ) || empty( $records ) ) {
			return []; }
		if ( isset( $records[0] ) && is_string( $records[0] ) ) {
			$settings = Helpers::get_settings();
			$settings = is_array( $settings ) ? $settings : [];
			return [
				[
					'urls'        => array_values( array_unique( $records ) ),
					'zone_id'     => (string) ( $settings['cloudflare_zone_id'] ?? '' ),
					'fingerprint' => $this->cloudflare_settings_key( $settings ),
					'attempts'    => 0,
					'failed'      => false,
				],
			];
		}
		return $records;
	}

	/** @param array<string,mixed> $settings */
	private function cloudflare_settings_key( array $settings ): string {
		return md5( (string) ( $settings['cloudflare_zone_id'] ?? '' ) . '|' . (string) ( $settings['cloudflare_api_token'] ?? '' ) ); }

	/**
	 * @param array<string, mixed> $old Previous settings.
	 * @param array<string, mixed> $new_settings New settings.
	 */
	private function cloudflare_settings_changed( array $old, array $new_settings ): bool {
		return $this->cloudflare_settings_key( $old ) !== $this->cloudflare_settings_key( $new_settings ) || ! empty( $old['enable_cloudflare_cache_sync'] ) !== ! empty( $new_settings['enable_cloudflare_cache_sync'] );
	}

	/** @return array<string, int> */
	private function get_cloudflare_queue_status() {
		$records  = $this->get_cloudflare_queue_records( get_option( 'perform_cache_cloudflare_queue_' . $this->get_blog_id(), [] ) );
		$settings = Helpers::get_settings();
		$current  = is_array( $settings ) ? $this->cloudflare_settings_key( $settings ) : '';
		$status   = [
			'pending'              => 0,
			'failed'               => 0,
			'retryable_failed'     => 0,
			'credential_residuals' => 0,
		];
		foreach ( $records as $record ) {
			if ( ! empty( $record['failed'] ) ) {
				++$status['failed'];
				if ( ! empty( $record['credential_residual'] ) ) {
					++$status['credential_residuals'];
				} elseif ( ( $record['fingerprint'] ?? '' ) === $current ) {
					++$status['retryable_failed'];
				}
			} else {
				++$status['pending']; }
		}
		return $status;
	}

	/**
	 * Read cache metadata.
	 *
	 * @param string $key Cache key.
	 *
	 * @return array<string, int|string>|null
	 */
	private function read_cache_meta( $key ) {
		$path = $this->get_meta_file_path( $key );
		if ( ! file_exists( $path ) ) {
			return null;
		}

		$raw = file_get_contents( $path );
		if ( false === $raw || '' === $raw ) {
			return null;
		}

		$data = json_decode( $raw, true );
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Read cache body.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string|null
	 */
	private function read_cache_body( $key ) {
		$path = $this->get_body_file_path( $key );
		if ( ! file_exists( $path ) ) {
			return null;
		}

		$raw = file_get_contents( $path );
		return false === $raw ? null : $raw;
	}

	/**
	 * Write cache metadata.
	 *
	 * @param string                     $key Cache key.
	 * @param array<string, int|string> $meta Metadata.
	 *
	 * @return bool
	 */
	private function write_cache_meta( $key, $meta ) {
		$path     = $this->get_meta_file_path( $key );
		$contents = wp_json_encode( $meta );

		if ( ! is_string( $contents ) ) {
			return false;
		}

		return $this->write_file_atomically( $path, $contents );
	}

	/**
	 * Write cache body.
	 *
	 * @param string $key Cache key.
	 * @param string $body Body.
	 *
	 * @return bool
	 */
	private function write_cache_body( $key, $body ) {
		$path = $this->get_body_file_path( $key );
		return $this->write_file_atomically( $path, $body );
	}

	/**
	 * Write cache file through a same-directory temp file and atomic rename.
	 *
	 * @param string $path Target file path.
	 * @param string $contents File contents.
	 *
	 * @return bool
	 */
	private function write_file_atomically( $path, $contents ) {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) || ! wp_is_writable( $directory ) ) {
			return false;
		}

		$tmp_path = $path . '.' . uniqid( 'tmp-', true );
		$written  = file_put_contents( $tmp_path, $contents, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( false === $written ) {
			wp_delete_file( $tmp_path );
			return false;
		}

		if ( ! rename( $tmp_path, $path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			wp_delete_file( $tmp_path );
			return false;
		}

		return true;
	}

	/**
	 * Increment numeric stat key.
	 *
	 * @param string $key Stat key.
	 *
	 * @return int Increment size applied to the counter.
	 */
	private function increment_stat( $key ) {
		$store = $this->get_stats_store();
		return $store->increment( $key );
	}

	/**
	 * Set stat map value.
	 *
	 * @param string $key Stat key.
	 * @param mixed  $value Value.
	 *
	 * @return void
	 */
	private function set_stat_value( $key, $value ) {
		$store = $this->get_stats_store();
		$store->set_value( $key, $value );
	}

	/**
	 * Record top miss URLs.
	 *
	 * @param string $url URL.
	 * @param int    $increment Count increment.
	 *
	 * @return void
	 */
	private function record_top_miss( $url, $increment = 1 ) {
		$map = $this->get_stat_map( 'top_misses' );
		if ( ! isset( $map[ $url ] ) ) {
			$map[ $url ] = 0;
		}
		$map[ $url ] += max( 1, (int) $increment );
		arsort( $map );
		$map = array_slice( $map, 0, 50, true );
		$this->set_stat_map( 'top_misses', $map );
	}

	/**
	 * Get map stat.
	 *
	 * @param string $key Key.
	 *
	 * @return array<string, int|float>
	 */
	private function get_stat_map( $key ) {
		$store = $this->get_stats_store();
		return $store->get_map( $key );
	}

	/**
	 * Set map stat.
	 *
	 * @param string                  $key Key.
	 * @param array<string, int|float> $value Map.
	 *
	 * @return void
	 */
	private function set_stat_map( $key, $value ) {
		$store = $this->get_stats_store();
		$store->set_map( $key, $value );
	}

	/**
	 * Flush in-memory stats to DB.
	 *
	 * @return void
	 */
	public function flush_stats() {
		$store = $this->get_stats_store();
		$store->flush();
	}

	/**
	 * Get or initialize stats store.
	 *
	 * @return StatsStore
	 */
	private function get_stats_store() {
		if ( ! ( $this->stats_store instanceof StatsStore ) ) {
			$this->stats_store = new StatsStore();
		}

		return $this->stats_store;
	}

	/**
	 * Get or initialize URL normalizer.
	 *
	 * @return UrlNormalizer
	 */
	private function get_url_normalizer() {
		if ( ! ( $this->url_normalizer instanceof UrlNormalizer ) ) {
			$this->url_normalizer = new UrlNormalizer();
		}

		return $this->url_normalizer;
	}
}
