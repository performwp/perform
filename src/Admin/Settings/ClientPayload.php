<?php
/**
 * Perform - Admin Settings Client Payload.
 *
 * @package Perform
 * @subpackage Admin/Settings
 */

namespace Perform\Admin\Settings;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ClientPayload {
	/**
	 * Placeholder used in UI payload for secret values.
	 */
	const MASKED_SECRET = '__PERFORM_MASKED_SECRET__';

	/**
	 * Settings keys that should not expose raw values to browser payloads.
	 *
	 * @return array<int, string>
	 */
	public static function get_sensitive_keys() {
		return [
			'cloudflare_api_token',
		];
	}

	/**
	 * Sanitize stored settings before localizing to JS.
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize_for_client( array $settings ) {
		foreach ( self::get_sensitive_keys() as $key ) {
			if ( ! array_key_exists( $key, $settings ) ) {
				continue;
			}

			$value = $settings[ $key ];
			if ( ! is_scalar( $value ) ) {
				$settings[ $key ] = '';
				continue;
			}

			$settings[ $key ] = '' !== trim( (string) $value ) ? self::MASKED_SECRET : '';
		}

		return $settings;
	}

	/**
	 * Check whether a value is the masked placeholder.
	 *
	 * @param mixed $value Value from request.
	 *
	 * @return bool
	 */
	public static function is_masked_secret( $value ) {
		return is_scalar( $value ) && self::MASKED_SECRET === (string) $value;
	}
}
