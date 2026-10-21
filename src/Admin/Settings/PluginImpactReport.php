<?php
/**
 * Perform - Plugin Impact Report.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Joins local inventory identity with measured admin asset evidence.
 */
final class PluginImpactReport {
	const MAX_PLUGINS = 100;

	/**
	 * Build a bounded, evidence-labelled report without running new scans.
	 *
	 * @param array<string, mixed>|null $inventory Optional inventory snapshot.
	 * @param array<string, mixed>|null $asset_audit Optional admin asset snapshot.
	 * @return array<string, mixed>
	 */
	public static function get_data( $inventory = null, $asset_audit = null ) {
		$inventory   = is_array( $inventory ) ? $inventory : SiteInventory::get_cached_snapshot();
		$asset_audit = is_array( $asset_audit ) ? $asset_audit : AdminAssetAudit::get_snapshot();
		$plugins     = is_array( $inventory['plugins']['items'] ?? null ) ? $inventory['plugins']['items'] : [];
		$plugins     = array_values(
			array_filter(
				array_slice( $plugins, 0, self::MAX_PLUGINS ),
				static function ( $plugin ) {
					return is_array( $plugin ) && ! empty( $plugin['active'] );
				}
			)
		);

		$evidence = self::aggregate_admin_assets( (array) ( $asset_audit['screens'] ?? [] ) );
		$items    = [];
		foreach ( $plugins as $plugin ) {
			$slug = sanitize_key( is_scalar( $plugin['slug'] ?? null ) ? (string) $plugin['slug'] : '' );
			if ( '' === $slug ) {
				continue;
			}
			$signals = $evidence[ $slug ] ?? [
				'screens' => [],
				'scripts' => [],
				'styles'  => [],
			];
			$items[] = [
				'slug'          => $slug,
				'name'          => sanitize_text_field( is_scalar( $plugin['name'] ?? null ) ? (string) $plugin['name'] : $slug ),
				'version'       => sanitize_text_field( is_scalar( $plugin['version'] ?? null ) ? (string) $plugin['version'] : '' ),
				'adminAssets'   => [
					'screenCount' => count( $signals['screens'] ),
					'scriptCount' => count( $signals['scripts'] ),
					'styleCount'  => count( $signals['styles'] ),
					'screens'     => array_values( array_slice( array_keys( $signals['screens'] ), 0, AdminAssetAudit::MAX_SCREENS ) ),
				],
				'hasEvidence'   => ! empty( $signals['screens'] ),
				'evidenceState' => ! empty( $signals['screens'] ) ? 'measured-locally' : 'not-observed',
			];
		}

		usort(
			$items,
			static function ( $left, $right ) {
				$asset_compare = ( (int) $right['adminAssets']['scriptCount'] + (int) $right['adminAssets']['styleCount'] ) <=> ( (int) $left['adminAssets']['scriptCount'] + (int) $left['adminAssets']['styleCount'] );
				return 0 !== $asset_compare ? $asset_compare : strcasecmp( (string) $left['name'], (string) $right['name'] );
			}
		);

		$inventory_ready = 'ready' === ( $inventory['status'] ?? '' );
		$audit_enabled   = ! empty( $asset_audit['enabled'] );
		$sample_count    = count( (array) ( $asset_audit['screens'] ?? [] ) );

		return [
			'status'          => $inventory_ready ? 'ready' : 'inventory-needed',
			'scope'           => 'site',
			'isMultisite'     => ! empty( $inventory['isMultisite'] ),
			'items'           => $items,
			'activeCount'     => max( 0, (int) ( $inventory['plugins']['activeCount'] ?? count( $items ) ) ),
			'isTruncated'     => ! empty( $inventory['plugins']['isTruncated'] ),
			'adminAssetAudit' => [
				'enabled'        => $audit_enabled,
				'sampledScreens' => $sample_count,
				'state'          => $audit_enabled && $sample_count > 0 ? 'measured-locally' : 'not-measured',
			],
			'unavailable'     => [
				'frontendAssets' => 'not-measured',
				'queries'        => 'not-attributed',
				'callbacks'      => 'not-attributed',
				'memory'         => 'not-attributed',
			],
		];
	}

	/**
	 * Aggregate only asset handles already attributed with high confidence.
	 *
	 * @param array<int, mixed> $screens Prepared admin asset screens.
	 * @return array<string, array<string, array<string, bool>>>
	 */
	private static function aggregate_admin_assets( array $screens ) {
		$evidence = [];
		foreach ( array_slice( $screens, 0, AdminAssetAudit::MAX_SCREENS ) as $screen ) {
			if ( ! is_array( $screen ) ) {
				continue;
			}
			$raw_screen_id = is_scalar( $screen['id'] ?? null ) ? (string) $screen['id'] : '';
			$screen_id     = sanitize_key( $raw_screen_id );
			if ( '' === $screen_id || $screen_id !== $raw_screen_id ) {
				continue;
			}
			foreach ( array_slice( (array) ( $screen['assets'] ?? [] ), 0, AdminAssetAudit::MAX_ASSETS ) as $asset ) {
				if ( ! is_array( $asset ) || 'high' !== ( $asset['confidence'] ?? '' ) ) {
					continue;
				}
				$raw_source = is_scalar( $asset['source'] ?? null ) ? (string) $asset['source'] : '';
				$raw_handle = is_scalar( $asset['handle'] ?? null ) ? (string) $asset['handle'] : '';
				$source     = sanitize_key( $raw_source );
				$handle     = sanitize_key( $raw_handle );
				$type       = $asset['type'] ?? '';
				if ( '' === $source || $source !== $raw_source || '' === $handle || $handle !== $raw_handle || ! in_array( $type, [ 'script', 'style' ], true ) ) {
					continue;
				}

				if ( ! isset( $evidence[ $source ] ) ) {
					$evidence[ $source ] = [
						'screens' => [],
						'scripts' => [],
						'styles'  => [],
					];
				}
				$evidence[ $source ]['screens'][ $screen_id ] = true;
				$bucket                                       = 'script' === $type ? 'scripts' : 'styles';
				$evidence[ $source ][ $bucket ][ $handle ]    = true;
			}
		}

		return $evidence;
	}
}
