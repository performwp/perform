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
	 * Determine whether this module should be loaded.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		return ! empty( Helpers::get_option( 'enable_page_cache', 'perform_settings', false ) );
	}

	/**
	 * Register hooks and filters for cache module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->cache_dir = trailingslashit( WP_CONTENT_DIR ) . 'cache/perform/';

		add_filter( 'cron_schedules', [ $this, 'register_cron_schedule' ] );
		add_action( 'init', [ $this, 'maybe_schedule_events' ] );

		add_action( 'template_redirect', [ $this, 'maybe_serve_cache' ], 0 );
		add_action( 'template_redirect', [ $this, 'start_output_buffer' ], 9999 );
		add_action( 'shutdown', [ $this, 'record_slow_uncached_request' ], 9999 );

		add_action( 'save_post', [ $this, 'purge_related_urls_for_post' ], 10, 3 );
		add_action( 'deleted_post', [ $this, 'purge_related_urls_for_deleted_post' ], 10, 1 );
		add_action( 'trashed_post', [ $this, 'purge_related_urls_for_deleted_post' ], 10, 1 );
		add_action( 'set_object_terms', [ $this, 'purge_related_urls_for_object_terms' ], 10, 6 );
		add_action( 'switch_theme', [ $this, 'purge_homepage' ] );

		add_action( 'perform_cache_preload_event', [ $this, 'run_preload_batch' ] );
		add_action( 'perform_cache_sitemap_seed_event', [ $this, 'seed_preload_queue_from_sitemap_and_logs' ] );

		add_action( 'admin_menu', [ $this, 'register_observability_page' ] );
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
		$preload_enabled = ! empty( Helpers::get_option( 'enable_cache_preload', 'perform_settings', false ) );

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
		$this->request_start = microtime( true );

		if ( ! $this->is_cacheable_request() ) {
			$this->increment_stat( 'bypasses' );
			return;
		}

		$this->ensure_cache_dir();

		$this->current_url       = $this->get_normalized_request_url();
		$this->current_cache_key = $this->get_cache_key_for_url( $this->current_url );
		$this->lock_key          = 'perform_cache_lock_' . $this->current_cache_key;

		$meta = $this->read_cache_meta( $this->current_cache_key );
		$html = $this->read_cache_body( $this->current_cache_key );

		$is_regen_request = $this->is_internal_regen_request();

		if ( $meta && is_string( $html ) && ! $is_regen_request ) {
			$now = time();
			if ( ! empty( $meta['expires'] ) && $now <= (int) $meta['expires'] ) {
				$this->increment_stat( 'hits' );
				$this->send_cache_headers( 'HIT' );
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}

			if ( ! empty( $meta['swr_expires'] ) && $now <= (int) $meta['swr_expires'] ) {
				$this->increment_stat( 'stale_hits' );
				$this->send_cache_headers( 'STALE' );
				$this->maybe_trigger_async_regeneration( $this->current_url );
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}
		}

		// Miss path: acquire lock to avoid stampede.
		if ( ! $this->acquire_lock() ) {
			$this->increment_stat( 'lock_waits' );

			// If lock is held and stale exists, prefer stale over full uncached render.
			if ( $meta && is_string( $html ) ) {
				$this->increment_stat( 'stale_hits' );
				$this->send_cache_headers( 'STALE-LOCK' );
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				exit;
			}

			$this->increment_stat( 'misses' );
			$this->record_top_miss( $this->current_url );
			return;
		}

		$this->should_write_cache = true;
		$this->increment_stat( 'misses' );
		$this->record_top_miss( $this->current_url );
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
			if ( ! $this->should_write_cache || ! is_string( $html ) || '' === $html ) {
				return $html;
			}

			if ( http_response_code() >= 400 ) {
				return $html;
			}

			// Don't cache responses setting cookies.
			foreach ( headers_list() as $header_line ) {
				if ( 0 === stripos( $header_line, 'Set-Cookie:' ) ) {
					return $html;
				}
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

			$this->write_cache_meta( $this->current_cache_key, $meta_payload );
			$this->write_cache_body( $this->current_cache_key, $html );
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
		if ( 0.0 === $this->request_start || ! $this->is_cacheable_request() ) {
			return;
		}

		// If we served from cache, execution already exited.
		$duration_ms = ( microtime( true ) - $this->request_start ) * 1000;
		$threshold   = (int) Helpers::get_option( 'cache_slow_request_threshold_ms', 'perform_settings', 1200 );

		if ( $duration_ms < max( 200, $threshold ) ) {
			return;
		}

		$url  = '' !== $this->current_url ? $this->current_url : $this->get_normalized_request_url();
		$slow = $this->get_stat_map( 'slow_uncached' );
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
	public function purge_related_urls_for_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$urls = $this->get_invalidation_urls_for_post( $post_id );
		foreach ( $urls as $url ) {
			$this->purge_url( $url );
		}

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
		$urls = $this->get_invalidation_urls_for_post( $post_id );
		foreach ( $urls as $url ) {
			$this->purge_url( $url );
		}
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
		foreach ( $urls as $url ) {
			$this->purge_url( $url );
		}
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
	 * Seed preload queue using sitemap and recent traffic.
	 *
	 * @return void
	 */
	public function seed_preload_queue_from_sitemap_and_logs() {
		if ( empty( Helpers::get_option( 'enable_cache_preload', 'perform_settings', false ) ) ) {
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
		if ( empty( Helpers::get_option( 'enable_cache_preload', 'perform_settings', false ) ) ) {
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
			if ( ! is_string( $url ) || '' === $url ) {
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

			$processed++;
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

		$top_misses    = $stats['top_misses'] ?? [];
		$slow_uncached = $stats['slow_uncached'] ?? [];
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Perform Cache Observability', 'perform' ); ?></h1>
			<p><?php esc_html_e( 'Live cache effectiveness and warmup health metrics.', 'perform' ); ?></p>

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
	 *
	 * @return void
	 */
	private function render_stat_map_table( $map, $value_header ) {
		if ( ! is_array( $map ) || empty( $map ) ) {
			echo '<p>' . esc_html__( 'No data yet.', 'perform' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="max-width:1200px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'URL', 'perform' ); ?></th>
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
			if ( ! is_string( $url ) || '' === $url ) {
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

		$urls = [];
		if ( isset( $index->sitemap ) ) {
			foreach ( $index->sitemap as $sitemap ) {
				if ( empty( $sitemap->loc ) ) {
					continue;
				}

				$child_response = wp_remote_get( (string) $sitemap->loc, [ 'timeout' => 8 ] );
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
					if ( ! empty( $item->loc ) ) {
						$urls[] = esc_url_raw( (string) $item->loc );
					}
				}
			}
		}

		return array_slice( array_values( array_unique( array_filter( $urls ) ) ), 0, 500 );
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
		$enabled = Helpers::get_option( 'enable_cloudflare_cache_sync', 'perform_settings', false );
		if ( empty( $enabled ) ) {
			return;
		}

		$zone_id = trim( (string) Helpers::get_option( 'cloudflare_zone_id', 'perform_settings', '' ) );
		$token   = trim( (string) Helpers::get_option( 'cloudflare_api_token', 'perform_settings', '' ) );
		if ( '' === $zone_id || '' === $token ) {
			return;
		}

		wp_remote_post(
			'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $zone_id ) . '/purge_cache',
			[
				'timeout' => 10,
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( [ 'files' => [ esc_url_raw( $url ) ] ] ),
			]
		);
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
		if ( get_transient( $this->lock_key ) ) {
			return;
		}

		set_transient( $this->lock_key, 1, 30 );

		wp_remote_post(
			esc_url_raw( $url ),
			[
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'headers'   => [
					'X-Perform-Cache-Regen' => '1',
				],
			]
		);
	}

	/**
	 * Whether current request should bypass cache.
	 *
	 * @return bool
	 */
	private function is_cacheable_request() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}

		if ( is_user_logged_in() || is_preview() || is_feed() || is_trackback() || is_robots() || is_search() ) {
			return false;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( ! in_array( $method, [ 'GET', 'HEAD' ], true ) ) {
			return false;
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		if ( is_string( $path ) ) {
			$path = untrailingslashit( strtolower( $path ) );
			$blocked_paths = [
				'/cart',
				'/checkout',
				'/my-account',
				'/wc-api',
			];
			foreach ( $blocked_paths as $blocked_path ) {
				if ( 0 === strpos( $path, $blocked_path ) ) {
					return false;
				}
			}
		}

		if ( isset( $_GET['add-to-cart'] ) ) {
			return false;
		}

		$cookies = $_COOKIE; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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
					return false;
				}
			}
		}

		return true;
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
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return esc_url_raw( $url );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '/';
		$path   = '/' . ltrim( $path, '/' );

		$query_params = [];
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_params );
		}

		$ignore_params = [
			'fbclid',
			'gclid',
			'msclkid',
			'mc_cid',
			'mc_eid',
		];
		$separate_params_raw = (string) Helpers::get_option( 'cache_separate_query_params', 'perform_settings', '' );
		$separate_params     = array_filter( array_map( 'trim', explode( ',', $separate_params_raw ) ) );

		$filtered = [];
		foreach ( $query_params as $key => $value ) {
			$key_str = strtolower( (string) $key );
			if ( 0 === strpos( $key_str, 'utm_' ) || in_array( $key_str, $ignore_params, true ) ) {
				continue;
			}

			if ( ! empty( $separate_params ) && ! in_array( $key_str, $separate_params, true ) ) {
				continue;
			}

			$filtered[ $key_str ] = $value;
		}

		if ( ! empty( $filtered ) ) {
			ksort( $filtered );
			$query = http_build_query( $filtered );
		} else {
			$query = '';
		}

		return $scheme . '://' . $host . $path . ( '' !== $query ? '?' . $query : '' );
	}

	/**
	 * Get cache key for normalized URL.
	 *
	 * @param string $normalized_url Normalized URL.
	 *
	 * @return string
	 */
	private function get_cache_key_for_url( $normalized_url ) {
		return md5( $normalized_url );
	}

	/**
	 * Determine if request is internal regeneration.
	 *
	 * @return bool
	 */
	private function is_internal_regen_request() {
		return isset( $_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] ) && '1' === sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_PERFORM_CACHE_REGEN'] ) );
	}

	/**
	 * Ensure cache dir exists.
	 *
	 * @return void
	 */
	private function ensure_cache_dir() {
		if ( ! is_dir( $this->cache_dir ) ) {
			wp_mkdir_p( $this->cache_dir );
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

		return set_transient( $this->lock_key, 1, 30 );
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
		return $this->cache_dir . $key . '.html';
	}

	/**
	 * Get meta file path.
	 *
	 * @param string $key Cache key.
	 *
	 * @return string
	 */
	private function get_meta_file_path( $key ) {
		return $this->cache_dir . $key . '.meta.json';
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
	 * @return void
	 */
	private function write_cache_meta( $key, $meta ) {
		$path = $this->get_meta_file_path( $key );
		file_put_contents( $path, wp_json_encode( $meta ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Write cache body.
	 *
	 * @param string $key Cache key.
	 * @param string $body Body.
	 *
	 * @return void
	 */
	private function write_cache_body( $key, $body ) {
		$path = $this->get_body_file_path( $key );
		file_put_contents( $path, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	/**
	 * Increment numeric stat key.
	 *
	 * @param string $key Stat key.
	 *
	 * @return void
	 */
	private function increment_stat( $key ) {
		$stats = get_option( 'perform_cache_stats', [] );
		if ( ! is_array( $stats ) ) {
			$stats = [];
		}

		$stats[ $key ] = isset( $stats[ $key ] ) ? ( (int) $stats[ $key ] + 1 ) : 1;
		update_option( 'perform_cache_stats', $stats, false );
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
		$stats = get_option( 'perform_cache_stats', [] );
		if ( ! is_array( $stats ) ) {
			$stats = [];
		}
		$stats[ $key ] = $value;
		update_option( 'perform_cache_stats', $stats, false );
	}

	/**
	 * Record top miss URLs.
	 *
	 * @param string $url URL.
	 *
	 * @return void
	 */
	private function record_top_miss( $url ) {
		$map = $this->get_stat_map( 'top_misses' );
		if ( ! isset( $map[ $url ] ) ) {
			$map[ $url ] = 0;
		}
		$map[ $url ]++;
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
		$stats = get_option( 'perform_cache_stats', [] );
		if ( ! is_array( $stats ) || ! isset( $stats[ $key ] ) || ! is_array( $stats[ $key ] ) ) {
			return [];
		}

		return $stats[ $key ];
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
		$stats = get_option( 'perform_cache_stats', [] );
		if ( ! is_array( $stats ) ) {
			$stats = [];
		}

		$stats[ $key ] = $value;
		update_option( 'perform_cache_stats', $stats, false );
	}
}
