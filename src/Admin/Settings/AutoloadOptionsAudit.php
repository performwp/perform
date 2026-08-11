<?php
/**
 * Perform - Autoloaded options audit.
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
 * Collects a bounded, read-only snapshot of autoloaded option pressure.
 */
final class AutoloadOptionsAudit {
	const OPTION_NAME    = 'perform_autoload_options_audit';
	const SCHEMA_VERSION = 1;
	const CACHE_TTL      = 86400;
	const RESULT_LIMIT   = 20;
	const SIZE_THRESHOLD = 800000;

	/**
	 * Get the cached audit without performing database work.
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
	 * Run and persist a fresh audit for the current site.
	 *
	 * @return array<string, mixed>
	 * @throws RuntimeException When the database audit cannot be completed.
	 */
	public static function refresh() {
		global $wpdb;

		if ( ! is_object( $wpdb ) || empty( $wpdb->options ) ) {
			throw new RuntimeException( __( 'The options table is unavailable.', 'perform' ) );
		}

		$autoload_values = self::get_autoload_values();
		$placeholders    = implode( ', ', array_fill( 0, count( $autoload_values ), '%s' ) );
		$table           = $wpdb->options;

		// The table is the wpdb-owned options table and every autoload value is
		// passed through prepare. PHPCS cannot infer the dynamic placeholder list.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$summary_query = $wpdb->prepare(
			"SELECT COUNT(*) AS option_count, COALESCE(SUM(LENGTH(option_value)), 0) AS total_size FROM {$table} WHERE autoload IN ({$placeholders})",
			$autoload_values
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$summary = $wpdb->get_row( $summary_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; manual diagnostic persisted below.

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$options_query = $wpdb->prepare(
			"SELECT option_name, autoload, LENGTH(option_value) AS size_bytes FROM {$table} WHERE autoload IN ({$placeholders}) ORDER BY size_bytes DESC, option_name ASC LIMIT %d",
			array_merge( $autoload_values, [ self::RESULT_LIMIT ] )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$options = $wpdb->get_results( $options_query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; manual diagnostic persisted below.

		if ( ! is_array( $summary ) || ! is_array( $options ) || ! empty( $wpdb->last_error ) ) {
			throw new RuntimeException( __( 'The options audit could not be completed.', 'perform' ) );
		}

		$generated_at = time();
		$snapshot     = [
			'schemaVersion'  => self::SCHEMA_VERSION,
			'status'         => 'ready',
			'generatedAt'    => $generated_at,
			'expiresAt'      => $generated_at + self::CACHE_TTL,
			'isStale'        => false,
			'isMultisite'    => is_multisite(),
			'siteId'         => get_current_blog_id(),
			'autoloadValues' => $autoload_values,
			'thresholdBytes' => self::SIZE_THRESHOLD,
			'totals'         => [
				'count'     => max( 0, (int) ( $summary['option_count'] ?? 0 ) ),
				'sizeBytes' => max( 0, (int) ( $summary['total_size'] ?? 0 ) ),
			],
			'objectCache'    => [
				'persistent' => wp_using_ext_object_cache(),
			],
			'largestOptions' => self::normalize_options( $options ),
		];

		$is_saved = update_option( self::OPTION_NAME, $snapshot, false );
		if ( ! $is_saved && get_option( self::OPTION_NAME ) !== $snapshot ) {
			throw new RuntimeException( __( 'The audit result could not be saved.', 'perform' ) );
		}

		return $snapshot;
	}

	/**
	 * Get the values WordPress treats as autoload-enabled.
	 *
	 * @return array<int, string>
	 */
	private static function get_autoload_values() {
		if ( function_exists( 'wp_autoload_values_to_autoload' ) ) {
			$values = wp_autoload_values_to_autoload();
			if ( is_array( $values ) && ! empty( $values ) ) {
				return array_values( array_unique( array_map( 'strval', $values ) ) );
			}
		}

		return [ 'yes', 'on', 'auto-on', 'auto' ];
	}

	/**
	 * Normalize database rows without exposing option values.
	 *
	 * @param array<int, mixed> $options Database rows.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function normalize_options( array $options ) {
		$normalized = [];

		foreach ( array_slice( $options, 0, self::RESULT_LIMIT ) as $option ) {
			if ( ! is_array( $option ) || empty( $option['option_name'] ) ) {
				continue;
			}

			$name         = sanitize_text_field( (string) $option['option_name'] );
			$normalized[] = [
				'name'      => $name,
				'sizeBytes' => max( 0, (int) ( $option['size_bytes'] ?? 0 ) ),
				'autoload'  => sanitize_key( (string) ( $option['autoload'] ?? '' ) ),
				'ownership' => self::get_ownership_hint( $name ),
			];
		}

		return $normalized;
	}

	/**
	 * Provide conservative ownership hints for names with reliable prefixes.
	 *
	 * @param string $option_name Option name.
	 *
	 * @return array<string, string>
	 */
	private static function get_ownership_hint( $option_name ) {
		if ( 0 === strpos( $option_name, 'perform_' ) ) {
			return [
				'label'      => 'Perform',
				'confidence' => 'high',
			];
		}

		$core_names = [ 'active_plugins', 'cron', 'rewrite_rules', 'sidebars_widgets' ];
		if ( in_array( $option_name, $core_names, true ) || 0 === strpos( $option_name, 'widget_' ) || 0 === strpos( $option_name, 'wp_user_roles' ) ) {
			return [
				'label'      => 'WordPress',
				'confidence' => 'high',
			];
		}

		return [
			'label'      => __( 'Unknown', 'perform' ),
			'confidence' => 'unknown',
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
			'autoloadValues' => self::get_autoload_values(),
			'thresholdBytes' => self::SIZE_THRESHOLD,
			'totals'         => [
				'count'     => 0,
				'sizeBytes' => 0,
			],
			'objectCache'    => [
				'persistent' => wp_using_ext_object_cache(),
			],
			'largestOptions' => [],
		];
	}
}
