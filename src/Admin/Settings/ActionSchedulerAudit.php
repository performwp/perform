<?php
/**
 * Perform - Action Scheduler diagnostics.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects a bounded, read-only Action Scheduler queue snapshot.
 */
final class ActionSchedulerAudit {
	const OPTION_NAME    = 'perform_action_scheduler_audit';
	const SCHEMA_VERSION = 1;
	const CACHE_TTL      = 3600;
	const RESULT_LIMIT   = 20;

	/**
	 * Return the saved snapshot without querying Action Scheduler tables.
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
	 * Run and save a fresh aggregate snapshot for the current site.
	 *
	 * @return array<string, mixed>
	 * @throws RuntimeException When the database cannot provide a safe snapshot.
	 */
	public static function refresh() {
		global $wpdb;

		if ( ! is_object( $wpdb ) || empty( $wpdb->prefix ) ) {
			throw new RuntimeException( __( 'The database is unavailable.', 'perform' ) );
		}

		$actions_table = $wpdb->prefix . 'actionscheduler_actions';
		$groups_table  = $wpdb->prefix . 'actionscheduler_groups';
		$logs_table    = $wpdb->prefix . 'actionscheduler_logs';
		$has_actions   = self::table_exists( $wpdb, $actions_table );
		$has_groups    = self::table_exists( $wpdb, $groups_table );
		$has_logs      = self::table_exists( $wpdb, $logs_table );

		if ( ! $has_actions ) {
			return self::persist( self::get_unavailable_snapshot() );
		}

		// Table names are derived from the current wpdb prefix. Dynamic status
		// values and limits are passed through prepare below.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$status_query = $wpdb->prepare(
			"SELECT status, COUNT(*) AS action_count FROM {$actions_table} WHERE status IN (%s, %s, %s, %s, %s) GROUP BY status",
			[ 'pending', 'in-progress', 'complete', 'failed', 'canceled' ]
		);
		$status_rows  = $wpdb->get_results( $status_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate persisted below.

		$oldest_query   = $wpdb->prepare(
			"SELECT MIN(scheduled_date_gmt) FROM {$actions_table} WHERE status = %s",
			'pending'
		);
		$oldest_pending = $wpdb->get_var( $oldest_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Read-only aggregate persisted below.

		$hooks_query = $wpdb->prepare(
			"SELECT hook, COUNT(*) AS action_count FROM {$actions_table} GROUP BY hook ORDER BY action_count DESC, hook ASC LIMIT %d",
			self::RESULT_LIMIT
		);
		$hook_rows   = $wpdb->get_results( $hooks_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded aggregate persisted below.

		$group_rows = [];
		if ( $has_groups ) {
			$groups_query = $wpdb->prepare(
				"SELECT action_group.slug AS group_name, COUNT(*) AS action_count FROM {$actions_table} action_queue INNER JOIN {$groups_table} action_group ON action_group.group_id = action_queue.group_id GROUP BY action_group.slug ORDER BY action_count DESC, action_group.slug ASC LIMIT %d",
				self::RESULT_LIMIT
			);
			$group_rows   = $wpdb->get_results( $groups_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Bounded aggregate persisted below.
		}

		$log_count = null;
		if ( $has_logs ) {
			$log_count = $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- wpdb-prefix table; count only, no log messages read.
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $status_rows ) || ! is_array( $hook_rows ) || ! is_array( $group_rows ) || ! empty( $wpdb->last_error ) ) {
			throw new RuntimeException( __( 'The Action Scheduler diagnostic could not be completed.', 'perform' ) );
		}

		$counts = [
			'pending'  => 0,
			'running'  => 0,
			'complete' => 0,
			'failed'   => 0,
			'canceled' => 0,
		];
		foreach ( $status_rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$status = sanitize_key( (string) ( $row['status'] ?? '' ) );
			$key    = 'in-progress' === $status ? 'running' : $status;
			if ( array_key_exists( $key, $counts ) ) {
				$counts[ $key ] = max( 0, (int) ( $row['action_count'] ?? 0 ) );
			}
		}

		$generated_at = time();
		$snapshot     = [
			'schemaVersion' => self::SCHEMA_VERSION,
			'status'        => 'ready',
			'generatedAt'   => $generated_at,
			'expiresAt'     => $generated_at + self::CACHE_TTL,
			'isStale'       => false,
			'isMultisite'   => is_multisite(),
			'siteId'        => get_current_blog_id(),
			'counts'        => $counts,
			'oldestPending' => self::normalize_oldest_pending( $oldest_pending, $generated_at ),
			'topHooks'      => self::normalize_counts( $hook_rows, 'hook' ),
			'topGroups'     => self::normalize_counts( $group_rows, 'group_name' ),
			'storage'       => [
				'actionCount' => array_sum( $counts ),
				'logCount'    => null === $log_count ? null : max( 0, (int) $log_count ),
				'logsPresent' => $has_logs,
			],
			'failedTrend'   => [
				'state'  => 'not-available',
				'reason' => 'requires-log-message-inference',
			],
			'limits'        => [ 'resultLimit' => self::RESULT_LIMIT ],
			'privacy'       => [
				'argumentsRead'   => false,
				'logMessagesRead' => false,
			],
		];

		return self::persist( $snapshot );
	}

	/**
	 * Delete only Perform's saved aggregate.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * @param object $wpdb WordPress database object.
	 * @param string $table Table name.
	 * @return bool
	 */
	private static function table_exists( $wpdb, $table ) {
		$query = $wpdb->prepare( 'SHOW TABLES LIKE %s', $table );
		return $table === $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Feature detection only.
	}

	/**
	 * @param mixed $value Oldest pending GMT value.
	 * @param int $now Current timestamp.
	 * @return array<string, mixed>
	 */
	private static function normalize_oldest_pending( $value, $now ) {
		$timestamp = is_scalar( $value ) ? strtotime( (string) $value . ' UTC' ) : false;
		return [
			'available'  => false !== $timestamp,
			'timestamp'  => false === $timestamp ? 0 : $timestamp,
			'ageSeconds' => false === $timestamp ? 0 : max( 0, $now - $timestamp ),
		];
	}

	/**
	 * @param array<int, mixed> $rows Aggregate rows.
	 * @param string $name_key Name field.
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_counts( array $rows, $name_key ) {
		$items = [];
		foreach ( array_slice( $rows, 0, self::RESULT_LIMIT ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$raw_name = is_scalar( $row[ $name_key ] ?? null ) ? (string) $row[ $name_key ] : '';
			$name     = sanitize_key( $raw_name );
			if ( '' === $name || $name !== $raw_name ) {
				continue;
			}
			$items[] = [
				'name'  => substr( $name, 0, 191 ),
				'count' => max( 0, (int) ( $row['action_count'] ?? 0 ) ),
			];
		}
		return $items;
	}

	/** @return array<string, mixed> */
	private static function get_empty_snapshot() {
		return self::base_snapshot( 'not-run', false );
	}

	/** @return array<string, mixed> */
	private static function get_unavailable_snapshot() {
		return self::base_snapshot( 'not-available', false );
	}

	/**
	 * @param string $status Snapshot status.
	 * @param bool $is_stale Stale flag.
	 * @return array<string, mixed>
	 */
	private static function base_snapshot( $status, $is_stale ) {
		return [
			'schemaVersion' => self::SCHEMA_VERSION,
			'status'        => $status,
			'generatedAt'   => 0,
			'expiresAt'     => 0,
			'isStale'       => $is_stale,
			'isMultisite'   => is_multisite(),
			'siteId'        => get_current_blog_id(),
			'counts'        => [
				'pending'  => 0,
				'running'  => 0,
				'complete' => 0,
				'failed'   => 0,
				'canceled' => 0,
			],
			'oldestPending' => [
				'available'  => false,
				'timestamp'  => 0,
				'ageSeconds' => 0,
			],
			'topHooks'      => [],
			'topGroups'     => [],
			'storage'       => [
				'actionCount' => 0,
				'logCount'    => null,
				'logsPresent' => false,
			],
			'failedTrend'   => [
				'state'  => 'not-available',
				'reason' => 'requires-log-message-inference',
			],
			'limits'        => [ 'resultLimit' => self::RESULT_LIMIT ],
			'privacy'       => [
				'argumentsRead'   => false,
				'logMessagesRead' => false,
			],
		];
	}

	/**
	 * @param array<string, mixed> $snapshot Snapshot.
	 * @return array<string, mixed>
	 * @throws RuntimeException When persistence fails.
	 */
	private static function persist( array $snapshot ) {
		$generated_at            = time();
		$snapshot['generatedAt'] = $generated_at;
		$snapshot['expiresAt']   = $generated_at + self::CACHE_TTL;
		$snapshot['isStale']     = false;
		$is_saved                = update_option( self::OPTION_NAME, $snapshot, false );
		if ( ! $is_saved && get_option( self::OPTION_NAME ) !== $snapshot ) {
			throw new RuntimeException( __( 'The Action Scheduler diagnostic could not be saved.', 'perform' ) );
		}
		return $snapshot;
	}
}
