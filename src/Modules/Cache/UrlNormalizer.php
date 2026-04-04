<?php
/**
 * Perform - Cache URL Normalizer.
 *
 * @package Perform
 * @subpackage Modules/Cache
 */

namespace Perform\Modules\Cache;

use Perform\Includes\Helpers;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class UrlNormalizer {
	/**
	 * Normalize URL for cache keying.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	public function normalize( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return esc_url_raw( $url );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : 'https';
		$host   = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '/';
		$path   = '/' . ltrim( $path, '/' );

		$query_params = [];
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query_params );
		}

		$ignore_params = [
			'fbclid',
			'gclid',
			'msclkid',
			'mc_cid',
			'mc_eid',
		];
		$separate_params_raw = (string) Helpers::get_option( 'cache_separate_query_params', 'perform_settings', '' );
		$separate_params     = array_filter( array_map( 'trim', explode( ',', $separate_params_raw ) ) );

		$filtered = [];
		foreach ( $query_params as $key => $value ) {
			$key_str = strtolower( (string) $key );
			if ( 0 === strpos( $key_str, 'utm_' ) || in_array( $key_str, $ignore_params, true ) ) {
				continue;
			}

			if ( ! empty( $separate_params ) && ! in_array( $key_str, $separate_params, true ) ) {
				continue;
			}

			$filtered[ $key_str ] = $value;
		}

		$query = '';
		if ( ! empty( $filtered ) ) {
			ksort( $filtered );
			$query = http_build_query( $filtered );
		}

		return $scheme . '://' . $host . $path . ( '' !== $query ? '?' . $query : '' );
	}

	/**
	 * Build cache key for normalized URL.
	 *
	 * @param string $normalized_url Normalized URL.
	 *
	 * @return string
	 */
	public function key_for_url( $normalized_url ) {
		return md5( $normalized_url );
	}
}
