<?php
/**
 * Perform - CDN Manager.
 *
 * @since 1.0.0
 *
 * @package    Perform
 * @subpackage Modules
 * @author     PerformWP <hello@performwp.com>
 */

namespace Perform\Modules\CDN;

use Perform\Includes\Helpers;
use Perform\Modules\AbstractModule;

// Bail out, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CDNManager
 *
 * @since 1.0.0
 */
class CDNManager extends AbstractModule {
	/**
	 * Toggle setting used to activate the module.
	 *
	 * @var string
	 */
	protected static $option_key = 'enable_cdn';

	/**
	 * Normalized request-local CDN settings.
	 *
	 * @var array<string, mixed>|null
	 */
	private $normalized_settings = null;

	/**
	 * Determine whether this module should be loaded.
	 *
	 * @return bool
	 */
	public function should_load(): bool {
		$settings = $this->get_normalized_settings();

		return $settings['enabled'] && '' !== $settings['cdn_url'];
	}

	/**
	 * Register hooks and filters for this module.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', [ $this, 'rewrite_with_cdn' ], 1 );
	}

	/**
	 * This function is introduced to act as an output buffer to fetch HTML to replace the URLs.
	 *
	 * @since 1.2.2
	 */
	public function rewrite_with_cdn() {
		// Avoid buffering non-HTML/frontend contexts.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_trackback() || is_preview() ) {
			return;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		ob_start( [ $this, 'rewrite_with_cdn_url' ] );
	}

	/**
	 * This function will act as wrapper to rewrite the HTML with CDN URL.
	 *
	 * @param mixed $html HTML content.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return mixed
	 */
	public function rewrite_with_cdn_url( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		$settings = $this->get_normalized_settings();
		if ( '' === $settings['cdn_url'] ) {
			return $html;
		}

		$site_url    = quotemeta( get_option( 'home' ) );
		$url_regex   = '(https?:|)' . substr( $site_url, strpos( $site_url, '//' ) );
		$directories = implode(
			'|',
			array_map( 'quotemeta', $settings['directories'] )
		);

		$regex         = '#(?<=[(\"\'])(?:' . $url_regex . ')?/(?:((?:' . $directories . ')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
		$html_with_cdn = preg_replace_callback( $regex, [ $this, 'rewrited_cdn_url' ], $html );

		return $html_with_cdn;
	}

	/**
	 * This function will rewrite the CDN URL to the HTML based on the settings.
	 *
	 * @param string $url URL to replace with CDN URL.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return string
	 */
	public function rewrited_cdn_url( $url ) {
		$settings = $this->get_normalized_settings();
		$cdn_url  = $settings['cdn_url'];

		if ( '' !== $cdn_url ) {
			// Don't Rewrite URL, if Excluded.
			foreach ( $settings['exclusions'] as $exclusion ) {
				if ( false !== stristr( $url[0], $exclusion ) ) {
					return $url[0];
				}
			}

			// Don't Rewrite if Previewing.
			$is_preview_request = isset( $_GET['preview'] ) && 'true' === sanitize_text_field( wp_unslash( $_GET['preview'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview bypass signal.
			if (
				is_admin_bar_showing() &&
				$is_preview_request
			) {
				return $url[0];
			}

			$site_url = get_option( 'home' );
			$site_url = substr( $site_url, strpos( $site_url, '//' ) );

			// Replace URL w/ No HTTP/S Prefix.
			if ( strpos( $url[0], '//' ) === 0 ) {
				return str_replace( $site_url, $cdn_url, $url[0] );
			}

			// Found Site URL, Replace Non Relative URL w/ HTTP/S Prefix.
			if ( strstr( $url[0], $site_url ) ) {
				return str_replace( [ 'http:' . $site_url, 'https:' . $site_url ], $cdn_url, $url[0] );
			}

			// Replace Relative URL.
			return $cdn_url . $url[0];
		}

		return $url[0];
	}

	/**
	 * Read a CDN-related setting from injected settings first.
	 *
	 * @param string $key            Setting key.
	 * @param mixed  $fallback_value Default value when the setting is missing.
	 *
	 * @return mixed
	 */
	private function read_setting( string $key, $fallback_value = '' ) {
		if ( is_array( $this->settings ) && array_key_exists( $key, $this->settings ) ) {
			return $this->settings[ $key ];
		}

		return Helpers::get_option( $key, 'perform_settings', $fallback_value );
	}

	/**
	 * Get normalized settings used by load checks and rewrites.
	 *
	 * @return array<string, mixed>
	 */
	private function get_normalized_settings(): array {
		if ( is_array( $this->normalized_settings ) ) {
			return $this->normalized_settings;
		}

		$this->normalized_settings = [
			'enabled'     => ! empty( $this->read_setting( 'enable_cdn', false ) ),
			'cdn_url'     => $this->normalize_cdn_url( $this->read_setting( 'cdn_url', '' ) ),
			'directories' => $this->normalize_csv_setting( $this->read_setting( 'cdn_directories', '' ), [ 'wp-content', 'wp-includes' ] ),
			'exclusions'  => $this->normalize_csv_setting( $this->read_setting( 'cdn_exclusions', '' ) ),
		];

		return $this->normalized_settings;
	}

	/**
	 * Normalize a comma-separated list setting.
	 *
	 * @param mixed    $value    Raw setting value.
	 * @param string[] $fallback Default values when the setting is empty.
	 *
	 * @return string[]
	 */
	private function normalize_csv_setting( $value, array $fallback = [] ): array {
		if ( is_array( $value ) ) {
			$items = $value;
		} elseif ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
			$items = explode( ',', (string) $value );
		} else {
			return $fallback;
		}

		$items = array_values(
			array_filter(
				array_map(
					static function ( $item ): string {
						return trim( (string) $item );
					},
					$items
				),
				static function ( string $item ): bool {
					return '' !== $item;
				}
			)
		);

		return ! empty( $items ) ? $items : $fallback;
	}

	/**
	 * Normalize the configured CDN URL for safe runtime checks.
	 *
	 * @param mixed $cdn_url Raw CDN URL setting.
	 *
	 * @return string
	 */
	private function normalize_cdn_url( $cdn_url ): string {
		if ( ! is_scalar( $cdn_url ) ) {
			return '';
		}

		$cdn_url = trim( (string) $cdn_url );
		if ( '' === $cdn_url ) {
			return '';
		}

		$scheme = strtolower( (string) wp_parse_url( $cdn_url, PHP_URL_SCHEME ) );
		$host   = (string) wp_parse_url( $cdn_url, PHP_URL_HOST );

		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) || '' === $host ) {
			return '';
		}

		return $cdn_url;
	}
}
