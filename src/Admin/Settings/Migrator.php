<?php
/**
 * Perform - Settings Migrator.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Migrator {
	/**
	 * Migrate legacy option groups to perform_settings only when it does not exist.
	 *
	 * @return void
	 */
	public static function maybe_migrate_legacy_settings() {
		$current = get_option( 'perform_settings', null );
		if ( is_array( $current ) ) {
			return;
		}

		$legacy_option_groups = [
			'perform_common',
			'perform_cdn',
			'perform_ssl',
			'perform_woocommerce',
			'perform_advanced',
		];

		$migrated = [];

		foreach ( $legacy_option_groups as $option_name ) {
			$legacy_values = get_option( $option_name, [] );
			if ( ! is_array( $legacy_values ) || empty( $legacy_values ) ) {
				continue;
			}

			$migrated = array_merge( $migrated, $legacy_values );
		}

		if ( empty( $migrated ) ) {
			return;
		}

		update_option( 'perform_settings', $migrated, false );
	}
}
