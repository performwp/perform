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
		$settings = self::normalize_textarea_values_for_client( $settings );

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
	 * Normalize stored textarea arrays to strings for React textarea controls.
	 *
	 * @param array<string, mixed> $settings Stored settings.
	 *
	 * @return array<string, mixed>
	 */
	private static function normalize_textarea_values_for_client( array $settings ) {
		$textarea_keys = self::get_textarea_field_keys();

		foreach ( $textarea_keys as $key ) {
			if ( empty( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
				continue;
			}

			$settings[ $key ] = implode(
				"\n",
				array_filter(
					array_map(
						static function ( $value ) {
							return is_scalar( $value ) ? (string) $value : '';
						},
						$settings[ $key ]
					),
					static function ( $value ) {
						return '' !== $value;
					}
				)
			);
		}

		return $settings;
	}

	/**
	 * Get field ids for textarea controls.
	 *
	 * @return array<int, string>
	 */
	private static function get_textarea_field_keys() {
		$keys   = [];
		$fields = \Perform\Includes\Helpers::get_settings_fields();

		foreach ( $fields as $cards ) {
			if ( ! is_array( $cards ) ) {
				continue;
			}

			foreach ( $cards as $card ) {
				if ( empty( $card['fields'] ) || ! is_array( $card['fields'] ) ) {
					continue;
				}

				foreach ( $card['fields'] as $field ) {
					if ( isset( $field['id'], $field['type'] ) && 'textarea' === $field['type'] ) {
						$keys[] = (string) $field['id'];
					}
				}
			}
		}

		return array_values( array_unique( $keys ) );
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
