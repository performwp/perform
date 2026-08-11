<?php
/**
 * Perform - Privacy-safe local site inventory.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

use Perform\Includes\Helpers;
use RuntimeException;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collects a bounded site-shape snapshot for administrator guidance.
 */
final class SiteInventory {
	const OPTION_NAME     = 'perform_site_inventory';
	const SCHEMA_VERSION  = 1;
	const CACHE_TTL       = 86400;
	const PLUGIN_LIMIT    = 100;
	const POST_TYPE_LIMIT = 30;
	const MODULE_LIMIT    = 50;

	/**
	 * Return a cached snapshot without collecting fresh data.
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
	 * Collect and store a fresh snapshot for the current site.
	 *
	 * @return array<string, mixed>
	 * @throws RuntimeException When the snapshot cannot be persisted.
	 */
	public static function refresh() {
		$started_at       = microtime( true );
		$memory_at_start  = memory_get_usage( true );
		$peak_at_start    = memory_get_peak_usage( true );
		$generated_at     = time();
		$plugin_inventory = self::collect_plugins();
		$post_types       = self::collect_post_types();
		$snapshot         = [
			'schemaVersion' => self::SCHEMA_VERSION,
			'status'        => 'ready',
			'generatedAt'   => $generated_at,
			'expiresAt'     => $generated_at + self::CACHE_TTL,
			'isStale'       => false,
			'scope'         => 'site',
			'isMultisite'   => is_multisite(),
			'siteId'        => get_current_blog_id(),
			'environment'   => self::collect_environment(),
			'plugins'       => $plugin_inventory,
			'theme'         => self::collect_theme(),
			'postTypes'     => $post_types,
			'configuration' => self::collect_configuration(),
			'patterns'      => self::collect_patterns( $post_types ),
			'perform'       => self::collect_perform_modules(),
			'signals'       => self::get_signal_states( $plugin_inventory ),
		];

		$snapshot['collection'] = [
			'durationMs'           => round( max( 0, microtime( true ) - $started_at ) * 1000, 2 ),
			'memoryDeltaBytes'     => max( 0, memory_get_usage( true ) - $memory_at_start ),
			'peakMemoryDeltaBytes' => max( 0, memory_get_peak_usage( true ) - $peak_at_start ),
			'bounded'              => true,
		];

		$is_saved = update_option( self::OPTION_NAME, $snapshot, false );
		if ( ! $is_saved && get_option( self::OPTION_NAME ) !== $snapshot ) {
			throw new RuntimeException( __( 'The site inventory could not be saved.', 'perform' ) );
		}

		return $snapshot;
	}

	/**
	 * Collect local runtime versions without external requests.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function collect_environment() {
		global $wp_version, $wpdb;

		$database_version = '';
		if ( is_object( $wpdb ) && is_callable( [ $wpdb, 'db_version' ] ) ) {
			$database_version = sanitize_text_field( (string) $wpdb->db_version() );
		}

		return [
			'wordpress' => self::fact( isset( $wp_version ) ? (string) $wp_version : '' ),
			'php'       => self::fact( PHP_VERSION ),
			'database'  => self::fact( $database_version ),
			'perform'   => self::fact( defined( 'PERFORM_VERSION' ) ? PERFORM_VERSION : '' ),
		];
	}

	/**
	 * Collect a bounded plugin identity inventory.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			$plugin_api = trailingslashit( ABSPATH ) . 'wp-admin/includes/plugin.php';
			if ( file_exists( $plugin_api ) ) {
				require_once $plugin_api;
			}
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			return [
				'state'          => 'not-available',
				'installedCount' => 0,
				'activeCount'    => 0,
				'items'          => [],
				'isTruncated'    => false,
			];
		}

		$plugins        = get_plugins();
		$plugins        = is_array( $plugins ) ? $plugins : [];
		$active_plugins = get_option( 'active_plugins', [] );
		$active_plugins = is_array( $active_plugins ) ? array_map( 'strval', $active_plugins ) : [];
		ksort( $plugins, SORT_NATURAL | SORT_FLAG_CASE );
		$items        = [];
		$active_count = 0;

		foreach ( array_keys( $plugins ) as $plugin_file ) {
			$is_active = function_exists( 'is_plugin_active' )
				? is_plugin_active( (string) $plugin_file )
				: in_array( (string) $plugin_file, $active_plugins, true );

			if ( $is_active ) {
				++$active_count;
			}
		}

		foreach ( array_slice( $plugins, 0, self::PLUGIN_LIMIT, true ) as $plugin_file => $headers ) {
			$headers = is_array( $headers ) ? $headers : [];
			$slug    = dirname( (string) $plugin_file );
			if ( '.' === $slug ) {
				$slug = pathinfo( (string) $plugin_file, PATHINFO_FILENAME );
			}

			$is_active = function_exists( 'is_plugin_active' )
				? is_plugin_active( (string) $plugin_file )
				: in_array( (string) $plugin_file, $active_plugins, true );

			$items[] = [
				'slug'      => sanitize_key( $slug ),
				'name'      => sanitize_text_field( (string) ( $headers['Name'] ?? $slug ) ),
				'version'   => sanitize_text_field( (string) ( $headers['Version'] ?? '' ) ),
				'active'    => $is_active,
				'ownership' => [
					'label'      => __( 'Installed extension', 'perform' ),
					'confidence' => 'high',
				],
			];
		}

		return [
			'state'          => 'measured-locally',
			'installedCount' => count( $plugins ),
			'activeCount'    => $active_count,
			'items'          => $items,
			'isTruncated'    => count( $plugins ) > self::PLUGIN_LIMIT,
		];
	}

	/**
	 * Collect active theme facts without URLs or settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_theme() {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return [ 'state' => 'not-available' ];
		}

		$theme = wp_get_theme();
		if ( ! is_object( $theme ) || ( is_callable( [ $theme, 'exists' ] ) && ! $theme->exists() ) ) {
			return [ 'state' => 'not-available' ];
		}

		$name       = $theme->get( 'Name' );
		$version    = $theme->get( 'Version' );
		$stylesheet = $theme->get_stylesheet();

		return [
			'state'      => 'measured-locally',
			'name'       => sanitize_text_field( (string) $name ),
			'version'    => sanitize_text_field( (string) $version ),
			'stylesheet' => sanitize_key( (string) $stylesheet ),
			'blockTheme' => function_exists( 'wp_is_block_theme' ) ? wp_is_block_theme() : null,
			'ownership'  => [
				'label'      => __( 'Active theme', 'perform' ),
				'confidence' => 'high',
			],
		];
	}

	/**
	 * Collect bounded registered post-type metadata and aggregate counts.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_post_types() {
		if ( ! function_exists( 'get_post_types' ) ) {
			return [
				'state'       => 'not-available',
				'totalCount'  => 0,
				'items'       => [],
				'isTruncated' => false,
			];
		}

		$registered = get_post_types( [], 'objects' );
		$registered = is_array( $registered ) ? $registered : [];
		$builtin    = array_filter(
			$registered,
			static function ( $post_type ) {
				return ! empty( $post_type->_builtin );
			}
		);
		$custom     = array_diff_key( $registered, $builtin );
		ksort( $builtin, SORT_NATURAL | SORT_FLAG_CASE );
		ksort( $custom, SORT_NATURAL | SORT_FLAG_CASE );
		$registered = $builtin + $custom;
		$items      = [];

		foreach ( array_slice( $registered, 0, self::POST_TYPE_LIMIT, true ) as $name => $post_type ) {
			$count = 0;
			if ( function_exists( 'wp_count_posts' ) ) {
				$counts = wp_count_posts( (string) $name, 'readable' );
				if ( is_object( $counts ) ) {
					foreach ( get_object_vars( $counts ) as $status_count ) {
						if ( is_numeric( $status_count ) ) {
							$count += max( 0, (int) $status_count );
						}
					}
				}
			}

			$builtin         = ! empty( $post_type->_builtin );
			$capability_type = (array) $post_type->capability_type;
			$items[]         = [
				'name'           => sanitize_key( (string) $name ),
				'label'          => sanitize_text_field( (string) $post_type->label ),
				'builtIn'        => $builtin,
				'public'         => ! empty( $post_type->public ),
				'showUi'         => ! empty( $post_type->show_ui ),
				'showInRest'     => ! empty( $post_type->show_in_rest ),
				'hasArchive'     => ! empty( $post_type->has_archive ),
				'hierarchical'   => ! empty( $post_type->hierarchical ),
				'capabilityType' => array_values( array_slice( array_map( 'sanitize_key', $capability_type ), 0, 2 ) ),
				'contentCount'   => $count,
				'ownership'      => [
					'label'      => $builtin ? 'WordPress' : __( 'Custom or extension', 'perform' ),
					'confidence' => $builtin ? 'high' : 'unknown',
				],
			];
		}

		return [
			'state'       => 'measured-locally',
			'totalCount'  => count( $registered ),
			'items'       => $items,
			'isTruncated' => count( $registered ) > self::POST_TYPE_LIMIT,
		];
	}

	/**
	 * Collect front-page configuration as booleans, never private URLs or IDs.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_configuration() {
		$show_on_front = (string) get_option( 'show_on_front', 'posts' );

		return [
			'state'        => 'measured-locally',
			'showOnFront'  => in_array( $show_on_front, [ 'page', 'posts' ], true ) ? $show_on_front : 'not-available',
			'frontPageSet' => 0 < (int) get_option( 'page_on_front', 0 ),
			'postsPageSet' => 0 < (int) get_option( 'page_for_posts', 0 ),
		];
	}

	/**
	 * Detect only reliable local site patterns.
	 *
	 * @param array<string, mixed> $post_types Post-type inventory.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_patterns( array $post_types ) {
		$names = [];
		foreach ( $post_types['items'] ?? [] as $post_type ) {
			if ( is_array( $post_type ) && isset( $post_type['name'] ) ) {
				$names[] = (string) $post_type['name'];
			}
		}

		$custom_count = count(
			array_filter(
				$post_types['items'] ?? [],
				static function ( $post_type ) {
					return is_array( $post_type ) && empty( $post_type['builtIn'] );
				}
			)
		);

		return [
			'state'         => 'measured-locally',
			'store'         => class_exists( 'WooCommerce' ) || in_array( 'product', $names, true ),
			'community'     => function_exists( 'bp_is_active' ) || in_array( 'forum', $names, true ),
			'customContent' => $custom_count,
		];
	}

	/**
	 * Collect enabled Perform module labels from the canonical settings schema.
	 *
	 * @return array<string, mixed>
	 */
	private static function collect_perform_modules() {
		$settings = Helpers::get_settings();
		$settings = is_array( $settings ) ? $settings : [];
		$labels   = [];

		foreach ( Helpers::get_settings_fields() as $sections ) {
			foreach ( is_array( $sections ) ? $sections : [] as $section ) {
				foreach ( is_array( $section['fields'] ?? null ) ? $section['fields'] : [] as $field ) {
					if ( is_array( $field ) && isset( $field['id'] ) ) {
						$label = $field['label'] ?? $field['name'] ?? '';
						if ( '' !== $label ) {
							$labels[ (string) $field['id'] ] = sanitize_text_field( (string) $label );
						}
					}
				}
			}
		}

		$enabled = [];
		foreach ( $settings as $key => $value ) {
			if ( 0 !== strpos( (string) $key, 'enable_' ) || empty( $value ) ) {
				continue;
			}

			$enabled[] = [
				'id'    => sanitize_key( (string) $key ),
				'label' => $labels[ $key ] ?? ucwords( str_replace( '_', ' ', substr( (string) $key, 7 ) ) ),
			];
		}

		return [
			'state'        => 'measured-locally',
			'enabledCount' => count( $enabled ),
			'items'        => array_slice( $enabled, 0, self::MODULE_LIMIT ),
			'isTruncated'  => count( $enabled ) > self::MODULE_LIMIT,
		];
	}

	/**
	 * State labels consumed by the dashboard without recomputing inventory.
	 *
	 * @param array<string, mixed> $plugins Plugin inventory.
	 *
	 * @return array<string, string>
	 */
	private static function get_signal_states( array $plugins ) {
		return [
			'localInventory' => 'measured-locally',
			'pluginIdentity' => (string) ( $plugins['state'] ?? 'not-available' ),
			'labPerformance' => 'needs-separate-test',
			'fieldMetrics'   => 'needs-separate-test',
		];
	}

	/**
	 * Build one measured or unavailable environment fact.
	 *
	 * @param string $value Fact value.
	 *
	 * @return array<string, string>
	 */
	private static function fact( $value ) {
		$value = sanitize_text_field( $value );

		return [
			'state' => '' === $value ? 'not-available' : 'measured-locally',
			'value' => $value,
		];
	}

	/**
	 * Return the safe client shape before the first refresh.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_empty_snapshot() {
		return [
			'schemaVersion' => self::SCHEMA_VERSION,
			'status'        => 'not-run',
			'generatedAt'   => 0,
			'expiresAt'     => 0,
			'isStale'       => false,
			'scope'         => 'site',
			'isMultisite'   => is_multisite(),
			'siteId'        => get_current_blog_id(),
			'environment'   => [],
			'plugins'       => [
				'state' => 'not-available',
				'items' => [],
			],
			'theme'         => [ 'state' => 'not-available' ],
			'postTypes'     => [
				'state' => 'not-available',
				'items' => [],
			],
			'configuration' => [ 'state' => 'not-available' ],
			'patterns'      => [ 'state' => 'not-available' ],
			'perform'       => [
				'state' => 'not-available',
				'items' => [],
			],
			'signals'       => [
				'localInventory' => 'not-available',
				'pluginIdentity' => 'not-available',
				'labPerformance' => 'needs-separate-test',
				'fieldMetrics'   => 'needs-separate-test',
			],
			'collection'    => [ 'bounded' => true ],
		];
	}
}
