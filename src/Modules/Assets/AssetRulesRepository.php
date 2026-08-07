<?php
/**
 * Assets Manager rule persistence.
 *
 * @package Perform
 */

namespace Perform\Modules\Assets;

// Bail out if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AssetRulesRepository {
	/**
	 * WordPress option containing Assets Manager rules.
	 */
	private const OPTION_NAME = 'perform_assets_manager_options';

	/**
	 * Read the stored rules.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$options = get_option( self::OPTION_NAME );

		return is_array( $options ) ? $options : [];
	}

	/**
	 * Persist the complete rule set.
	 *
	 * @param array<string, mixed> $options Rule set.
	 *
	 * @return bool
	 */
	public function save( array $options ): bool {
		return update_option( self::OPTION_NAME, $options, false );
	}

	/**
	 * Delete all Assets Manager rules.
	 *
	 * @return bool
	 */
	public function reset(): bool {
		return delete_option( self::OPTION_NAME );
	}
}
