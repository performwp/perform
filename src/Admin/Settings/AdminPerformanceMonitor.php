<?php
/**
 * Perform - Admin Performance Monitor.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Captures bounded aggregate request metrics when explicitly enabled.
 */
final class AdminPerformanceMonitor {
	const OPTION_NAME       = 'perform_admin_performance_monitor';
	const SCHEMA_VERSION    = 1;
	const RETENTION_DAYS    = 14;
	const MAX_CONTEXTS      = 50;
	const SLOW_DURATION_MS  = 1000;
	const HIGH_MEMORY_BYTES = 134217728;
	const HIGH_QUERY_COUNT  = 100;

	/**
	 * Approximate request start time.
	 *
	 * @var float
	 */
	private $start_time;

	/**
	 * Current wp-admin screen ID when available.
	 *
	 * @var string
	 */
	private $screen_id = '';

	/**
	 * Register monitoring only when explicitly enabled.
	 *
	 * @param array<string, mixed>|null $settings Optional already-loaded settings.
	 */
	public function __construct( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : Helpers::get_settings();
		$settings = is_array( $settings ) ? $settings : [];
		if ( empty( $settings['enable_admin_performance_monitor'] ) ) {
			return;
		}

		$request_start    = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) && is_numeric( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : microtime( true );
		$this->start_time = min( microtime( true ), $request_start );

		add_action( 'current_screen', [ $this, 'capture_screen' ] );
		add_action( 'shutdown', [ $this, 'capture_request' ], PHP_INT_MAX );
	}

	/**
	 * Remember the stable WordPress screen ID without storing the request URL.
	 *
	 * @param mixed $screen Current screen object.
	 *
	 * @return void
	 */
	public function capture_screen( $screen ) {
		if ( is_object( $screen ) && isset( $screen->id ) && is_scalar( $screen->id ) ) {
			$this->screen_id = sanitize_key( (string) $screen->id );
		}
	}

	/**
	 * Capture the completed request as one bounded aggregate sample.
	 *
	 * @return void
	 */
	public function capture_request() {
		$settings = Helpers::get_settings();
		if ( ! is_array( $settings ) || empty( $settings['enable_admin_performance_monitor'] ) ) {
			return;
		}

		$context = $this->get_request_context();
		if ( empty( $context ) || $this->should_skip_request( $context ) ) {
			return;
		}

		global $wpdb;

		$query_count = is_object( $wpdb ) && isset( $wpdb->num_queries ) ? (int) $wpdb->num_queries : 0;
		$duration_ms = max( 0, ( microtime( true ) - $this->start_time ) * 1000 );

		self::record_sample(
			[
				'type'             => $context['type'],
				'identifier'       => $context['identifier'],
				'capabilityBucket' => self::get_capability_bucket(),
				'durationMs'       => round( $duration_ms, 2 ),
				'peakMemoryBytes'  => max( 0, (int) memory_get_peak_usage( true ) ),
				'queryCount'       => max( 0, $query_count ),
			]
		);
	}

	/**
	 * Record one privacy-filtered sample into bounded per-site aggregates.
	 *
	 * @param array<string, mixed> $sample Raw sample.
	 * @param int|null             $now Optional Unix timestamp for tests.
	 *
	 * @return array<string, mixed> Updated snapshot.
	 */
	public static function record_sample( array $sample, $now = null ) {
		$now        = null === $now ? time() : max( 0, (int) $now );
		$type       = self::normalize_type( $sample['type'] ?? '' );
		$identifier = self::normalize_identifier( $type, $sample['identifier'] ?? '' );

		if ( '' === $type || '' === $identifier ) {
			return self::get_snapshot();
		}

		$snapshot = self::get_stored_snapshot();
		$contexts = is_array( $snapshot['contexts'] ?? null ) ? $snapshot['contexts'] : [];
		$cutoff   = $now - ( self::RETENTION_DAYS * 86400 );
		$contexts = array_values(
			array_filter(
				$contexts,
				static function ( $context ) use ( $cutoff ) {
					return is_array( $context ) && (int) ( $context['lastSeen'] ?? 0 ) >= $cutoff;
				}
			)
		);

		$capability_bucket = self::normalize_capability_bucket( $sample['capabilityBucket'] ?? '' );
		$key               = $type . ':' . $identifier . ':' . $capability_bucket;
		$duration_ms       = min( 3600000, max( 0, (float) ( $sample['durationMs'] ?? 0 ) ) );
		$memory_bytes      = min( PHP_INT_MAX, max( 0, (int) ( $sample['peakMemoryBytes'] ?? 0 ) ) );
		$query_count       = min( 1000000, max( 0, (int) ( $sample['queryCount'] ?? 0 ) ) );
		$matched           = false;

		foreach ( $contexts as &$context ) {
			if ( (string) ( $context['key'] ?? '' ) !== $key ) {
				continue;
			}

			$count                       = max( 0, (int) ( $context['count'] ?? 0 ) );
			$context['count']            = $count + 1;
			$context['totalDurationMs']  = round( max( 0, (float) ( $context['totalDurationMs'] ?? 0 ) ) + $duration_ms, 2 );
			$context['maxDurationMs']    = round( max( (float) ( $context['maxDurationMs'] ?? 0 ), $duration_ms ), 2 );
			$context['maxMemoryBytes']   = max( (int) ( $context['maxMemoryBytes'] ?? 0 ), $memory_bytes );
			$context['maxQueryCount']    = max( (int) ( $context['maxQueryCount'] ?? 0 ), $query_count );
			$context['slowRequestCount'] = max( 0, (int) ( $context['slowRequestCount'] ?? 0 ) ) + ( $duration_ms >= self::SLOW_DURATION_MS ? 1 : 0 );
			$context['lastSeen']         = $now;
			$matched                     = true;
			break;
		}
		unset( $context );

		if ( ! $matched ) {
			$contexts[] = [
				'key'              => $key,
				'type'             => $type,
				'identifier'       => $identifier,
				'capabilityBucket' => $capability_bucket,
				'count'            => 1,
				'totalDurationMs'  => round( $duration_ms, 2 ),
				'maxDurationMs'    => round( $duration_ms, 2 ),
				'maxMemoryBytes'   => $memory_bytes,
				'maxQueryCount'    => $query_count,
				'slowRequestCount' => $duration_ms >= self::SLOW_DURATION_MS ? 1 : 0,
				'lastSeen'         => $now,
			];
		}

		usort(
			$contexts,
			static function ( $left, $right ) {
				return (int) ( $right['lastSeen'] ?? 0 ) <=> (int) ( $left['lastSeen'] ?? 0 );
			}
		);

		$snapshot = [
			'schemaVersion' => self::SCHEMA_VERSION,
			'updatedAt'     => $now,
			'contexts'      => array_slice( $contexts, 0, self::MAX_CONTEXTS ),
		];

		update_option( self::OPTION_NAME, $snapshot, false );

		return self::prepare_snapshot( $snapshot );
	}

	/**
	 * Get the current bounded report payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_snapshot() {
		return self::prepare_snapshot( self::get_stored_snapshot() );
	}

	/**
	 * Get the normalized stored shape without derived display fields.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_stored_snapshot() {
		$snapshot = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $snapshot ) || self::SCHEMA_VERSION !== (int) ( $snapshot['schemaVersion'] ?? 0 ) ) {
			$snapshot = [
				'schemaVersion' => self::SCHEMA_VERSION,
				'updatedAt'     => 0,
				'contexts'      => [],
			];
		}

		return $snapshot;
	}

	/**
	 * Delete all aggregate metrics for the current site.
	 *
	 * @return bool
	 */
	public static function clear() {
		return delete_option( self::OPTION_NAME );
	}

	/**
	 * Add derived display fields without storing more data.
	 *
	 * @param array<string, mixed> $snapshot Stored snapshot.
	 *
	 * @return array<string, mixed>
	 */
	private static function prepare_snapshot( array $snapshot ) {
		$contexts = [];
		foreach ( (array) ( $snapshot['contexts'] ?? [] ) as $context ) {
			if ( ! is_array( $context ) ) {
				continue;
			}

			$count                        = max( 1, (int) ( $context['count'] ?? 1 ) );
			$context['averageDurationMs'] = round( max( 0, (float) ( $context['totalDurationMs'] ?? 0 ) ) / $count, 2 );
			$context['reasons']           = self::get_review_reasons( $context );
			$context['status']            = empty( $context['reasons'] ) ? 'observing' : 'review';
			$contexts[]                   = $context;
		}

		usort(
			$contexts,
			static function ( $left, $right ) {
				return (float) ( $right['maxDurationMs'] ?? 0 ) <=> (float) ( $left['maxDurationMs'] ?? 0 );
			}
		);

		$settings = Helpers::get_settings();

		return [
			'schemaVersion' => self::SCHEMA_VERSION,
			'enabled'       => ! empty( $settings['enable_admin_performance_monitor'] ),
			'status'        => empty( $contexts ) ? 'empty' : 'ready',
			'updatedAt'     => max( 0, (int) ( $snapshot['updatedAt'] ?? 0 ) ),
			'retentionDays' => self::RETENTION_DAYS,
			'maxContexts'   => self::MAX_CONTEXTS,
			'thresholds'    => [
				'durationMs'  => self::SLOW_DURATION_MS,
				'memoryBytes' => self::HIGH_MEMORY_BYTES,
				'queryCount'  => self::HIGH_QUERY_COUNT,
			],
			'contexts'      => $contexts,
		];
	}

	/**
	 * Get threshold reasons for one aggregate.
	 *
	 * @param array<string, mixed> $context Aggregate context.
	 *
	 * @return array<int, string>
	 */
	private static function get_review_reasons( array $context ) {
		$reasons = [];
		if ( (float) ( $context['maxDurationMs'] ?? 0 ) >= self::SLOW_DURATION_MS ) {
			$reasons[] = 'slow-request';
		}
		if ( (int) ( $context['maxMemoryBytes'] ?? 0 ) >= self::HIGH_MEMORY_BYTES ) {
			$reasons[] = 'high-memory';
		}
		if ( (int) ( $context['maxQueryCount'] ?? 0 ) >= self::HIGH_QUERY_COUNT ) {
			$reasons[] = 'high-query-count';
		}

		return $reasons;
	}

	/**
	 * Resolve a privacy-safe request context.
	 *
	 * @return array<string, string>
	 */
	private function get_request_context() {
		if ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() ) {
			return [
				'type'       => 'cron-adjacent',
				'identifier' => 'wp-cron',
			];
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			$action = isset( $_REQUEST['action'] ) && is_scalar( $_REQUEST['action'] ) ? wp_unslash( $_REQUEST['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Identifier only; no action is executed here.
			$action = self::normalize_identifier( 'ajax', $action );
			if ( 'unknown-action' !== $action && ! has_action( 'wp_ajax_' . $action ) && ! has_action( 'wp_ajax_nopriv_' . $action ) ) {
				$action = 'unknown-action';
			}

			return [
				'type'       => 'ajax',
				'identifier' => $action,
			];
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return [
				'type'       => 'rest',
				'identifier' => 'rest-api',
			];
		}

		if ( is_admin() ) {
			return [
				'type'       => 'admin-page',
				'identifier' => '' !== $this->screen_id ? $this->screen_id : 'admin-page',
			];
		}

		return [];
	}

	/**
	 * Avoid immediately recreating data during the clear request.
	 *
	 * @param array<string, string> $context Request context.
	 *
	 * @return bool
	 */
	private function should_skip_request( array $context ) {
		return 'ajax' === $context['type'] && 'perform_clear_admin_performance_monitor' === $context['identifier'];
	}

	/**
	 * Normalize request type.
	 *
	 * @param mixed $type Request type.
	 *
	 * @return string
	 */
	private static function normalize_type( $type ) {
		$type = is_scalar( $type ) ? sanitize_key( (string) $type ) : '';

		return in_array( $type, [ 'admin-page', 'ajax', 'rest', 'cron-adjacent' ], true ) ? $type : '';
	}

	/**
	 * Normalize an identifier without retaining payloads or query data.
	 *
	 * @param string $type Request type.
	 * @param mixed  $identifier Raw identifier.
	 *
	 * @return string
	 */
	private static function normalize_identifier( $type, $identifier ) {
		$raw = is_scalar( $identifier ) ? strtolower( trim( (string) $identifier ) ) : '';
		if ( 'ajax' === $type && ! preg_match( '/^[a-z0-9_-]{1,80}$/', $raw ) ) {
			return 'unknown-action';
		}

		$identifier = substr( sanitize_key( $raw ), 0, 80 );

		return '' !== $identifier ? $identifier : ( 'ajax' === $type ? 'unknown-action' : '' );
	}

	/**
	 * Resolve a non-identifying capability bucket.
	 *
	 * @return string
	 */
	private static function get_capability_bucket() {
		if ( current_user_can( 'manage_options' ) ) {
			return 'manage-options';
		}
		if ( current_user_can( 'edit_posts' ) ) {
			return 'edit-content';
		}
		if ( is_user_logged_in() ) {
			return 'authenticated';
		}

		return 'system';
	}

	/**
	 * Normalize a caller-supplied capability bucket.
	 *
	 * @param mixed $bucket Capability bucket.
	 *
	 * @return string
	 */
	private static function normalize_capability_bucket( $bucket ) {
		$bucket = is_scalar( $bucket ) ? sanitize_key( (string) $bucket ) : '';

		return in_array( $bucket, [ 'manage-options', 'edit-content', 'authenticated', 'system' ], true ) ? $bucket : 'system';
	}
}
