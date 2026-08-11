<?php
/**
 * Perform - Actionable local system health signals.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use Throwable;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a bounded, read-only runtime health summary without exposing paths.
 */
final class SystemHealth {
	const STATUS_GOOD        = 'good';
	const STATUS_REVIEW      = 'review';
	const STATUS_ACTION      = 'action-recommended';
	const STATUS_UNAVAILABLE = 'unavailable';

	/**
	 * Get current-site system health signals.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_data() {
		$signals = [
			self::php_version_signal(),
			self::memory_limit_signal(),
			self::execution_time_signal(),
			self::opcache_signal(),
			self::object_cache_signal(),
			self::database_signal(),
			self::http_signal(),
			self::upload_signal(),
			self::debug_log_signal(),
		];

		/**
		 * Filters detected system-health signals before they are normalized.
		 *
		 * @param array<int, array<string, mixed>> $signals Detected signals.
		 */
		$signals = apply_filters( 'perform_system_health_signals', $signals );
		$signals = self::normalize_signals( is_array( $signals ) ? $signals : [] );
		$summary = [
			'good'              => 0,
			'review'            => 0,
			'actionRecommended' => 0,
			'unavailable'       => 0,
		];

		foreach ( $signals as $signal ) {
			$key = self::STATUS_ACTION === $signal['status'] ? 'actionRecommended' : $signal['status'];
			if ( isset( $summary[ $key ] ) ) {
				++$summary[ $key ];
			}
		}

		return [
			'scope'       => 'site',
			'isMultisite' => is_multisite(),
			'summary'     => $summary,
			'signals'     => $signals,
		];
	}

	/**
	 * Create one normalized-ready signal.
	 *
	 * @param string $id             Stable signal id.
	 * @param string $label          Display label.
	 * @param string $value          Safe display value.
	 * @param string $status         Recommendation status.
	 * @param string $recommendation Plain-language guidance.
	 *
	 * @return array<string, string>
	 */
	private static function signal( $id, $label, $value, $status, $recommendation ) {
		return [
			'id'             => $id,
			'label'          => $label,
			'value'          => $value,
			'status'         => $status,
			'recommendation' => $recommendation,
		];
	}

	/**
	 * Detect PHP version support signal.
	 *
	 * @return array<string, string>
	 */
	private static function php_version_signal() {
		$status = PHP_VERSION_ID >= 80100 && PHP_VERSION_ID < 80400 ? self::STATUS_GOOD : self::STATUS_REVIEW;
		if ( self::STATUS_GOOD === $status ) {
			$copy = __( 'This runtime matches Perform’s actively tested PHP range.', 'perform' );
		} elseif ( PHP_VERSION_ID < 80100 ) {
			$copy = __( 'Review a supported PHP upgrade with your host before changing production.', 'perform' );
		} else {
			$copy = __( 'This runtime is newer than Perform’s current automated PHP test matrix; verify compatibility before rollout.', 'perform' );
		}

		return self::signal( 'php-version', __( 'PHP version', 'perform' ), PHP_VERSION, $status, $copy );
	}

	/**
	 * Detect WordPress memory-limit signal.
	 *
	 * @return array<string, string>
	 */
	private static function memory_limit_signal() {
		$value = defined( 'WP_MEMORY_LIMIT' ) ? (string) WP_MEMORY_LIMIT : (string) ini_get( 'memory_limit' );
		$bytes = self::parse_bytes( $value );
		if ( 0 >= $bytes ) {
			return self::signal( 'memory-limit', __( 'WordPress memory limit', 'perform' ), __( 'Unavailable', 'perform' ), self::STATUS_UNAVAILABLE, __( 'Confirm the effective WordPress memory limit with your host.', 'perform' ) );
		}

		$status = $bytes >= 268435456 ? self::STATUS_GOOD : ( $bytes >= 134217728 ? self::STATUS_REVIEW : self::STATUS_ACTION );
		$copy   = self::STATUS_GOOD === $status
			? __( 'The configured limit provides reasonable headroom for typical administration.', 'perform' )
			: __( 'Review memory pressure and hosting limits before increasing this value.', 'perform' );

		return self::signal( 'memory-limit', __( 'WordPress memory limit', 'perform' ), sanitize_text_field( $value ), $status, $copy );
	}

	/**
	 * Detect maximum execution-time signal.
	 *
	 * @return array<string, string>
	 */
	private static function execution_time_signal() {
		$seconds = (int) ini_get( 'max_execution_time' );
		if ( 0 === $seconds ) {
			return self::signal( 'execution-time', __( 'Maximum execution time', 'perform' ), __( 'Unlimited', 'perform' ), self::STATUS_GOOD, __( 'PHP does not impose a request execution-time limit.', 'perform' ) );
		}

		$status = $seconds >= 60 ? self::STATUS_GOOD : ( $seconds >= 30 ? self::STATUS_REVIEW : self::STATUS_ACTION );
		$copy   = self::STATUS_GOOD === $status
			? __( 'The limit supports ordinary WordPress maintenance tasks.', 'perform' )
			: __( 'Short limits can interrupt imports, updates, or maintenance; review with your host.', 'perform' );

		return self::signal( 'execution-time', __( 'Maximum execution time', 'perform' ), sprintf( __( '%d seconds', 'perform' ), $seconds ), $status, $copy );
	}

	/**
	 * Detect OPcache without changing runtime state.
	 *
	 * @return array<string, string>
	 */
	private static function opcache_signal() {
		$enabled = null;
		if ( function_exists( 'opcache_get_status' ) ) {
			try {
				$status = opcache_get_status( false );
				if ( is_array( $status ) ) {
					$enabled = ! empty( $status['opcache_enabled'] );
				}
			} catch ( Throwable $exception ) {
				$enabled = null;
			}
		}

		if ( null === $enabled ) {
			return self::signal( 'opcache', 'OPcache', __( 'Unavailable', 'perform' ), self::STATUS_UNAVAILABLE, __( 'The runtime did not expose OPcache status.', 'perform' ) );
		}

		return self::signal(
			'opcache',
			'OPcache',
			$enabled ? __( 'Enabled', 'perform' ) : __( 'Disabled', 'perform' ),
			$enabled ? self::STATUS_GOOD : self::STATUS_ACTION,
			$enabled ? __( 'Compiled PHP code is cached by the runtime.', 'perform' ) : __( 'Ask your host whether OPcache can be enabled safely.', 'perform' )
		);
	}

	/**
	 * Detect persistent object-cache usage.
	 *
	 * @return array<string, string>
	 */
	private static function object_cache_signal() {
		$enabled = function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();

		return self::signal(
			'object-cache',
			__( 'Persistent object cache', 'perform' ),
			$enabled ? __( 'Detected', 'perform' ) : __( 'Not detected', 'perform' ),
			$enabled ? self::STATUS_GOOD : self::STATUS_REVIEW,
			$enabled ? __( 'WordPress is using an external object cache.', 'perform' ) : __( 'Busy or database-heavy sites may benefit; confirm support with your host first.', 'perform' )
		);
	}

	/**
	 * Detect a safe database version value.
	 *
	 * @return array<string, string>
	 */
	private static function database_signal() {
		global $wpdb;

		$value = '';
		if ( is_object( $wpdb ) && is_callable( [ $wpdb, 'db_version' ] ) ) {
			$value = sanitize_text_field( (string) $wpdb->db_version() );
		}

		return self::signal(
			'database-version',
			__( 'Database version', 'perform' ),
			'' !== $value ? $value : __( 'Unavailable', 'perform' ),
			'' !== $value ? self::STATUS_GOOD : self::STATUS_UNAVAILABLE,
			'' !== $value ? __( 'Version detected locally; compatibility still depends on the database family and host.', 'perform' ) : __( 'Confirm the database version with your host.', 'perform' )
		);
	}

	/**
	 * Detect bounded HTTP/server hints.
	 *
	 * @return array<string, string>
	 */
	private static function http_signal() {
		$protocol = isset( $_SERVER['SERVER_PROTOCOL'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['SERVER_PROTOCOL'] ) ) ) : '';
		$protocol = preg_match( '/^HTTP\/[0-9.]+$/', $protocol ) ? $protocol : '';
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';
		$family   = '';

		foreach ( [
			'litespeed' => 'LiteSpeed',
			'nginx'     => 'nginx',
			'apache'    => 'Apache',
			'iis'       => 'IIS',
		] as $needle => $label ) {
			if ( false !== strpos( $software, $needle ) ) {
				$family = $label;
				break;
			}
		}

		$value = trim( implode( ' · ', array_filter( [ $protocol, $family ] ) ) );

		return self::signal( 'http-server', __( 'HTTP and server', 'perform' ), '' !== $value ? $value : __( 'Unavailable', 'perform' ), '' !== $value ? self::STATUS_GOOD : self::STATUS_UNAVAILABLE, __( 'This is a local runtime hint, not a public response-performance test.', 'perform' ) );
	}

	/**
	 * Detect upload-directory writability without exposing the path.
	 *
	 * @return array<string, string>
	 */
	private static function upload_signal() {
		if ( ! function_exists( 'wp_get_upload_dir' ) ) {
			return self::signal( 'uploads', __( 'Uploads directory', 'perform' ), __( 'Unavailable', 'perform' ), self::STATUS_UNAVAILABLE, __( 'WordPress did not expose upload-directory status.', 'perform' ) );
		}

		$uploads     = wp_get_upload_dir();
		$has_error   = is_array( $uploads ) && ! empty( $uploads['error'] );
		$is_writable = is_array( $uploads ) && ! empty( $uploads['basedir'] ) && function_exists( 'wp_is_writable' ) && wp_is_writable( $uploads['basedir'] );

		return self::signal(
			'uploads',
			__( 'Uploads directory', 'perform' ),
			$is_writable ? __( 'Writable', 'perform' ) : __( 'Needs review', 'perform' ),
			$is_writable && ! $has_error ? self::STATUS_GOOD : self::STATUS_ACTION,
			$is_writable && ! $has_error ? __( 'WordPress reports that media storage is writable.', 'perform' ) : __( 'Review media storage permissions with your host; the filesystem path is intentionally hidden.', 'perform' )
		);
	}

	/**
	 * Detect debug-log configuration without reading or exposing the file.
	 *
	 * @return array<string, string>
	 */
	private static function debug_log_signal() {
		$enabled = defined( 'WP_DEBUG_LOG' ) && false !== WP_DEBUG_LOG;

		return self::signal(
			'debug-log',
			__( 'Debug logging', 'perform' ),
			$enabled ? __( 'Enabled', 'perform' ) : __( 'Not enabled', 'perform' ),
			$enabled ? self::STATUS_REVIEW : self::STATUS_GOOD,
			$enabled ? __( 'Confirm logging is intentional and monitor its size on production sites.', 'perform' ) : __( 'Perform does not expose or read any debug-log path or contents.', 'perform' )
		);
	}

	/**
	 * Normalize filtered values to the client contract.
	 *
	 * @param array<int, mixed> $signals Candidate signals.
	 *
	 * @return array<int, array<string, string>>
	 */
	private static function normalize_signals( array $signals ) {
		$normalized = [];
		$allowed    = [ self::STATUS_GOOD, self::STATUS_REVIEW, self::STATUS_ACTION, self::STATUS_UNAVAILABLE ];

		foreach ( array_slice( $signals, 0, 20 ) as $signal ) {
			if ( ! is_array( $signal ) ) {
				continue;
			}

			$id = sanitize_key( (string) ( $signal['id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}

			$status       = (string) ( $signal['status'] ?? self::STATUS_UNAVAILABLE );
			$normalized[] = [
				'id'             => $id,
				'label'          => sanitize_text_field( (string) ( $signal['label'] ?? $id ) ),
				'value'          => sanitize_text_field( (string) ( $signal['value'] ?? '' ) ),
				'status'         => in_array( $status, $allowed, true ) ? $status : self::STATUS_UNAVAILABLE,
				'recommendation' => sanitize_text_field( (string) ( $signal['recommendation'] ?? '' ) ),
			];
		}

		return $normalized;
	}

	/**
	 * Convert shorthand PHP size values to bytes.
	 *
	 * @param string $value Shorthand value.
	 *
	 * @return int
	 */
	private static function parse_bytes( $value ) {
		$value = trim( $value );
		if ( '' === $value || '-1' === $value ) {
			return '-1' === $value ? PHP_INT_MAX : 0;
		}

		$number = (float) $value;
		$unit   = strtolower( substr( $value, -1 ) );
		if ( 'g' === $unit ) {
			$number *= 1024;
		}
		if ( in_array( $unit, [ 'g', 'm' ], true ) ) {
			$number *= 1024;
		}
		if ( in_array( $unit, [ 'g', 'm', 'k' ], true ) ) {
			$number *= 1024;
		}

		return (int) $number;
	}
}
