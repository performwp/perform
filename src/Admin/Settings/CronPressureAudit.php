<?php
/**
 * Perform - WP-Cron pressure diagnostics.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use RuntimeException;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects a bounded, read-only scheduled-task pressure snapshot.
 */
final class CronPressureAudit {
	const OPTION_NAME     = 'perform_cron_pressure_audit';
	const SCHEMA_VERSION  = 1;
	const CACHE_TTL       = 3600;
	const EVENT_LIMIT     = 5000;
	const HOOK_LIMIT      = 20;
	const OVERDUE_GRACE   = 300;
	const CLUSTER_WINDOW  = 300;
	const CLUSTER_HORIZON = 3600;

	/**
	 * Get a cached snapshot without scanning scheduled tasks.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_cached_snapshot() {
		$snapshot = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $snapshot ) || self::SCHEMA_VERSION !== (int) ( $snapshot['schemaVersion'] ?? 0 ) ) {
			return self::get_empty_snapshot();
		}

		$snapshot['isStale'] = time() >= (int) ( $snapshot['expiresAt'] ?? 0 );

		return $snapshot;
	}

	/**
	 * Run and persist a fresh read-only audit.
	 *
	 * @return array<string, mixed>
	 * @throws RuntimeException When the snapshot cannot be persisted.
	 */
	public static function refresh() {
		$events = function_exists( '_get_cron_array' ) ? _get_cron_array() : get_option( 'cron', [] );

		/**
		 * Filters the cron event array used by the diagnostic.
		 *
		 * @param array<string|int, mixed> $events WordPress cron array.
		 */
		$events = apply_filters( 'perform_cron_pressure_events', is_array( $events ) ? $events : [] );
		$events = is_array( $events ) ? $events : [];

		$now            = time();
		$scanned        = 0;
		$due            = 0;
		$overdue        = 0;
		$recurring      = 0;
		$next_hour      = 0;
		$hook_counts    = [];
		$cluster_counts = [];
		$was_truncated  = false;
		$generated_at   = $now;

		ksort( $events, SORT_NUMERIC );
		foreach ( $events as $timestamp => $hooks ) {
			if ( ! is_numeric( $timestamp ) || ! is_array( $hooks ) ) {
				continue;
			}

			$timestamp = (int) $timestamp;
			foreach ( $hooks as $hook => $instances ) {
				if ( ! is_array( $instances ) ) {
					continue;
				}

				$hook = self::normalize_hook( $hook );
				if ( '' === $hook ) {
					continue;
				}

				foreach ( $instances as $instance ) {
					if ( self::EVENT_LIMIT <= $scanned ) {
						$was_truncated = true;
						break 3;
					}

					$instance = is_array( $instance ) ? $instance : [];
					++$scanned;
					$hook_counts[ $hook ] = isset( $hook_counts[ $hook ] ) ? $hook_counts[ $hook ] + 1 : 1;

					if ( $timestamp <= $now ) {
						++$due;
					}
					if ( $timestamp < $now - self::OVERDUE_GRACE ) {
						++$overdue;
					}
					if ( ! empty( $instance['schedule'] ) || ! empty( $instance['interval'] ) ) {
						++$recurring;
					}
					if ( $timestamp > $now && $timestamp <= $now + self::CLUSTER_HORIZON ) {
						++$next_hour;
						$bucket                    = (int) floor( ( $timestamp - $now ) / self::CLUSTER_WINDOW );
						$cluster_counts[ $bucket ] = isset( $cluster_counts[ $bucket ] ) ? $cluster_counts[ $bucket ] + 1 : 1;
					}
				}
			}
		}

		$peak_cluster = empty( $cluster_counts ) ? 0 : max( $cluster_counts );
		$snapshot     = [
			'schemaVersion'  => self::SCHEMA_VERSION,
			'status'         => 'ready',
			'generatedAt'    => $generated_at,
			'expiresAt'      => $generated_at + self::CACHE_TTL,
			'isStale'        => false,
			'isMultisite'    => is_multisite(),
			'siteId'         => get_current_blog_id(),
			'wpCronDisabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'limits'         => [
				'eventLimit'     => self::EVENT_LIMIT,
				'hookLimit'      => self::HOOK_LIMIT,
				'overdueGrace'   => self::OVERDUE_GRACE,
				'clusterWindow'  => self::CLUSTER_WINDOW,
				'clusterHorizon' => self::CLUSTER_HORIZON,
			],
			'totals'         => [
				'scanned'     => $scanned,
				'due'         => $due,
				'overdue'     => $overdue,
				'recurring'   => $recurring,
				'nextHour'    => $next_hour,
				'peakCluster' => $peak_cluster,
				'truncated'   => $was_truncated,
			],
			'frequentHooks'  => self::normalize_hook_counts( $hook_counts ),
			'guidance'       => self::get_guidance( $due, $overdue, $peak_cluster, $was_truncated ),
		];

		$is_saved = update_option( self::OPTION_NAME, $snapshot, false );
		if ( ! $is_saved && get_option( self::OPTION_NAME ) !== $snapshot ) {
			throw new RuntimeException( __( 'The scheduled-task diagnostic could not be saved.', 'perform' ) );
		}

		return $snapshot;
	}

	/**
	 * Delete only Perform's cached diagnostic snapshot.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Normalize a hook name without reading its arguments.
	 *
	 * @param mixed $hook Hook name.
	 *
	 * @return string
	 */
	private static function normalize_hook( $hook ) {
		$hook = sanitize_key( (string) $hook );

		return substr( $hook, 0, 100 );
	}

	/**
	 * Sort and bound aggregate hook counts.
	 *
	 * @param array<string, int> $counts Hook counts.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_hook_counts( array $counts ) {
		uksort(
			$counts,
			static function ( $left, $right ) use ( $counts ) {
				$count_comparison = $counts[ $right ] <=> $counts[ $left ];

				return 0 !== $count_comparison ? $count_comparison : strcmp( $left, $right );
			}
		);

		$normalized = [];
		foreach ( array_slice( $counts, 0, self::HOOK_LIMIT, true ) as $hook => $count ) {
			$normalized[] = [
				'hook'  => $hook,
				'count' => max( 0, (int) $count ),
			];
		}

		return $normalized;
	}

	/**
	 * Build evidence-bound guidance.
	 *
	 * @param int  $due           Due events.
	 * @param int  $overdue       Overdue events.
	 * @param int  $peak_cluster  Peak next-hour cluster.
	 * @param bool $was_truncated Whether the scan hit its cap.
	 *
	 * @return array<string, string>
	 */
	private static function get_guidance( $due, $overdue, $peak_cluster, $was_truncated ) {
		if ( $was_truncated ) {
			return [
				'status'  => 'review',
				'heading' => __( 'Large schedule detected', 'perform' ),
				'message' => __( 'The bounded scan reached its event limit. Review the most frequent hooks with their extension owners.', 'perform' ),
			];
		}

		if ( 20 < $overdue || 50 < $peak_cluster ) {
			return [
				'status'  => 'action-recommended',
				'heading' => __( 'Scheduled-task pressure needs review', 'perform' ),
				'message' => __( 'Repeated overdue work or a dense upcoming cluster can add request latency. Confirm the responsible hooks and runner configuration before changing schedules.', 'perform' ),
			];
		}

		if ( 0 < $due || 10 < $peak_cluster ) {
			return [
				'status'  => 'review',
				'heading' => __( 'Some scheduled work needs review', 'perform' ),
				'message' => __( 'A small amount of due or clustered work can be normal. Refresh later and compare repeated snapshots before acting.', 'perform' ),
			];
		}

		return [
			'status'  => 'good',
			'heading' => __( 'No immediate schedule pressure detected', 'perform' ),
			'message' => __( 'The bounded local snapshot does not show overdue or densely clustered work.', 'perform' ),
		];
	}

	/**
	 * Get the safe client shape before the first audit.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_empty_snapshot() {
		return [
			'schemaVersion'  => self::SCHEMA_VERSION,
			'status'         => 'not-run',
			'generatedAt'    => 0,
			'expiresAt'      => 0,
			'isStale'        => false,
			'isMultisite'    => is_multisite(),
			'siteId'         => get_current_blog_id(),
			'wpCronDisabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
			'limits'         => [
				'eventLimit'     => self::EVENT_LIMIT,
				'hookLimit'      => self::HOOK_LIMIT,
				'overdueGrace'   => self::OVERDUE_GRACE,
				'clusterWindow'  => self::CLUSTER_WINDOW,
				'clusterHorizon' => self::CLUSTER_HORIZON,
			],
			'totals'         => [
				'scanned'     => 0,
				'due'         => 0,
				'overdue'     => 0,
				'recurring'   => 0,
				'nextHour'    => 0,
				'peakCluster' => 0,
				'truncated'   => false,
			],
			'frequentHooks'  => [],
			'guidance'       => [
				'status'  => 'not-run',
				'heading' => __( 'Run the first scheduled-task check', 'perform' ),
				'message' => __( 'The check runs only when requested and does not change scheduled events.', 'perform' ),
			],
		];
	}
}
