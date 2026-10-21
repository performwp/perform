<?php
/**
 * Perform - Admin Asset Audit.
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
 * Captures bounded script and style inventories for sampled wp-admin screens.
 */
final class AdminAssetAudit {
	const OPTION_NAME      = 'perform_admin_asset_audit';
	const SCHEMA_VERSION   = 1;
	const RETENTION_DAYS   = 7;
	const MAX_SCREENS      = 30;
	const MAX_ASSETS       = 100;
	const GLOBAL_THRESHOLD = 3;

	/** @var string */
	private $screen_id = '';

	/**
	 * Register capture hooks only when explicitly enabled.
	 *
	 * @param array<string, mixed>|null $settings Optional settings snapshot.
	 */
	public function __construct( $settings = null ) {
		$settings = is_array( $settings ) ? $settings : Helpers::get_settings();
		$settings = is_array( $settings ) ? $settings : [];
		if ( empty( $settings['enable_admin_asset_audit'] ) ) {
			return;
		}

		add_action( 'current_screen', [ $this, 'capture_screen' ] );
		add_action( 'admin_print_footer_scripts', [ $this, 'capture_assets' ], PHP_INT_MAX );
	}

	/**
	 * Remember a stable screen ID, never the request URL.
	 *
	 * @param mixed $screen Current screen object.
	 * @return void
	 */
	public function capture_screen( $screen ) {
		if ( is_object( $screen ) && isset( $screen->id ) && is_scalar( $screen->id ) ) {
			$this->screen_id = sanitize_key( (string) $screen->id );
		}
	}

	/**
	 * Capture the final queues after wp-admin footer enqueue processing.
	 *
	 * @return void
	 */
	public function capture_assets() {
		$settings = Helpers::get_settings();
		if ( empty( $settings['enable_admin_asset_audit'] ) || '' === $this->screen_id ) {
			return;
		}

		global $wp_scripts, $wp_styles;

		$assets = array_merge(
			self::read_queue( $wp_scripts, 'script' ),
			self::read_queue( $wp_styles, 'style' )
		);
		self::record_sample( $this->screen_id, $assets );
	}

	/**
	 * Convert a dependency queue to privacy-safe aggregate rows.
	 *
	 * @param mixed  $registry WordPress dependency registry.
	 * @param string $type Asset type.
	 * @return array<int, array<string, string>>
	 */
	private static function read_queue( $registry, $type ) {
		if ( ! is_object( $registry ) || ! isset( $registry->queue ) || ! is_array( $registry->queue ) ) {
			return [];
		}

		$assets = [];
		foreach ( array_slice( $registry->queue, 0, self::MAX_ASSETS ) as $handle ) {
			$handle = sanitize_key( is_scalar( $handle ) ? (string) $handle : '' );
			if ( '' === $handle ) {
				continue;
			}

			$src = '';
			if ( isset( $registry->registered[ $handle ] ) && is_object( $registry->registered[ $handle ] ) && isset( $registry->registered[ $handle ]->src ) && is_scalar( $registry->registered[ $handle ]->src ) ) {
				$src = (string) $registry->registered[ $handle ]->src;
			}

			$source   = self::classify_source( $src );
			$assets[] = [
				'type'       => $type,
				'handle'     => $handle,
				'source'     => $source['source'],
				'confidence' => $source['confidence'],
			];
		}

		return $assets;
	}

	/**
	 * Record one bounded screen sample.
	 *
	 * @param string                            $screen_id Stable WordPress screen ID.
	 * @param array<int, array<string, mixed>> $assets Raw assets.
	 * @param int|null                         $now Optional test timestamp.
	 * @return array<string, mixed>
	 */
	public static function record_sample( $screen_id, array $assets, $now = null ) {
		$screen_id = sanitize_key( (string) $screen_id );
		if ( '' === $screen_id ) {
			return self::get_snapshot();
		}

		$now      = null === $now ? time() : max( 0, (int) $now );
		$snapshot = self::get_stored_snapshot();
		$screens  = is_array( $snapshot['screens'] ?? null ) ? $snapshot['screens'] : [];
		$cutoff   = $now - ( self::RETENTION_DAYS * 86400 );
		$screens  = array_values(
			array_filter(
				$screens,
				static function ( $screen ) use ( $cutoff ) {
					return is_array( $screen ) && (int) ( $screen['lastSeen'] ?? 0 ) >= $cutoff;
				}
			)
		);

		$normalized = [];
		foreach ( array_slice( $assets, 0, self::MAX_ASSETS ) as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$type   = in_array( $asset['type'] ?? '', [ 'script', 'style' ], true ) ? $asset['type'] : '';
			$handle = sanitize_key( is_scalar( $asset['handle'] ?? null ) ? (string) $asset['handle'] : '' );
			if ( '' === $type || '' === $handle ) {
				continue;
			}

			$raw_source         = is_scalar( $asset['source'] ?? null ) ? (string) $asset['source'] : '';
			$source             = sanitize_key( $raw_source );
			$source             = ( '' !== $source && $source === $raw_source ) ? $source : 'unknown';
			$key                = $type . ':' . $handle;
			$normalized[ $key ] = [
				'type'       => $type,
				'handle'     => $handle,
				'source'     => $source,
				'confidence' => in_array( $asset['confidence'] ?? '', [ 'high', 'medium', 'low' ], true ) ? $asset['confidence'] : 'low',
			];
		}

		$next  = [
			'id'       => $screen_id,
			'lastSeen' => $now,
			'assets'   => array_values( $normalized ),
		];
		$found = false;
		foreach ( $screens as &$screen ) {
			if ( (string) ( $screen['id'] ?? '' ) === $screen_id ) {
				$screen = $next;
				$found  = true;
				break;
			}
		}
		unset( $screen );
		if ( ! $found ) {
			$screens[] = $next;
		}

		usort(
			$screens,
			static function ( $left, $right ) {
				return (int) ( $right['lastSeen'] ?? 0 ) <=> (int) ( $left['lastSeen'] ?? 0 );
			}
		);

		$snapshot = [
			'schemaVersion' => self::SCHEMA_VERSION,
			'updatedAt'     => $now,
			'screens'       => array_slice( $screens, 0, self::MAX_SCREENS ),
		];
		update_option( self::OPTION_NAME, $snapshot, false );

		return self::prepare_snapshot( $snapshot );
	}

	/**
	 * Classify an asset without retaining its URL or query string.
	 *
	 * @param string $src Registered source.
	 * @return array{source:string,confidence:string}
	 */
	public static function classify_source( $src ) {
		$path = strtolower( (string) wp_parse_url( (string) $src, PHP_URL_PATH ) );
		if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $path, $matches ) ) {
			return [
				'source'     => sanitize_key( $matches[1] ),
				'confidence' => 'high',
			];
		}
		if ( preg_match( '#/wp-content/(?:themes|mu-plugins)/([^/]+)/#', $path, $matches ) ) {
			return [
				'source'     => sanitize_key( $matches[1] ),
				'confidence' => 'high',
			];
		}
		if ( false !== strpos( $path, '/wp-admin/' ) || false !== strpos( $path, '/wp-includes/' ) ) {
			return [
				'source'     => 'wordpress-core',
				'confidence' => 'high',
			];
		}

		return [
			'source'     => 'unknown',
			'confidence' => 'low',
		];
	}

	/** @return array<string, mixed> */
	public static function get_snapshot() {
		return self::prepare_snapshot( self::get_stored_snapshot() );
	}

	/** @return bool */
	public static function clear() {
		return delete_option( self::OPTION_NAME );
	}

	/** @return array<string, mixed> */
	private static function get_stored_snapshot() {
		$snapshot = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $snapshot ) || self::SCHEMA_VERSION !== (int) ( $snapshot['schemaVersion'] ?? 0 ) ) {
			$snapshot = [
				'schemaVersion' => self::SCHEMA_VERSION,
				'updatedAt'     => 0,
				'screens'       => [],
			];
		}

		return $snapshot;
	}

	/**
	 * Derive repeated presence from the current bounded screen set.
	 *
	 * @param array<string, mixed> $snapshot Stored snapshot.
	 * @return array<string, mixed>
	 */
	private static function prepare_snapshot( array $snapshot ) {
		$settings = Helpers::get_settings();
		$screens  = [];
		foreach ( (array) ( $snapshot['screens'] ?? [] ) as $screen ) {
			if ( ! is_array( $screen ) ) {
				continue;
			}

			$screen_id = sanitize_key( is_scalar( $screen['id'] ?? null ) ? (string) $screen['id'] : '' );
			if ( '' === $screen_id ) {
				continue;
			}

			$assets = [];
			foreach ( array_slice( (array) ( $screen['assets'] ?? [] ), 0, self::MAX_ASSETS ) as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}
				$type   = in_array( $asset['type'] ?? '', [ 'script', 'style' ], true ) ? $asset['type'] : '';
				$handle = sanitize_key( is_scalar( $asset['handle'] ?? null ) ? (string) $asset['handle'] : '' );
				if ( '' === $type || '' === $handle ) {
					continue;
				}
				$raw_source = is_scalar( $asset['source'] ?? null ) ? (string) $asset['source'] : '';
				$source     = sanitize_key( $raw_source );
				$source     = ( '' !== $source && $source === $raw_source ) ? $source : 'unknown';
				$assets[]   = [
					'type'       => $type,
					'handle'     => $handle,
					'source'     => $source,
					'confidence' => in_array( $asset['confidence'] ?? '', [ 'high', 'medium', 'low' ], true ) ? $asset['confidence'] : 'low',
				];
			}

			$screens[] = [
				'id'       => $screen_id,
				'lastSeen' => max( 0, (int) ( $screen['lastSeen'] ?? 0 ) ),
				'assets'   => $assets,
			];
		}
		$presence = [];
		foreach ( $screens as $screen ) {
			foreach ( $screen['assets'] as $asset ) {
				$key              = $asset['type'] . ':' . $asset['handle'];
				$presence[ $key ] = ( $presence[ $key ] ?? 0 ) + 1;
			}
		}

		$repeated = [];
		foreach ( $screens as &$screen ) {
			foreach ( $screen['assets'] as &$asset ) {
				$key                   = $asset['type'] . ':' . $asset['handle'];
				$asset['screenCount']  = $presence[ $key ] ?? 1;
				$asset['globalSignal'] = ( $asset['screenCount'] >= self::GLOBAL_THRESHOLD );
				if ( $asset['globalSignal'] ) {
					$repeated[ $key ] = $asset;
				}
			}
			unset( $asset );
		}
		unset( $screen );

		usort(
			$repeated,
			static function ( $left, $right ) {
				return (int) $right['screenCount'] <=> (int) $left['screenCount'];
			}
		);

		return [
			'schemaVersion' => self::SCHEMA_VERSION,
			'updatedAt'     => max( 0, (int) ( $snapshot['updatedAt'] ?? 0 ) ),
			'enabled'       => ! empty( $settings['enable_admin_asset_audit'] ),
			'screens'       => $screens,
			'repeated'      => $repeated,
			'limits'        => [
				'screens'         => self::MAX_SCREENS,
				'assetsPerScreen' => self::MAX_ASSETS,
				'retentionDays'   => self::RETENTION_DAYS,
			],
		];
	}
}
