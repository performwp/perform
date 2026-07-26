<?php
/**
 * Perform cache activity export and lifecycle service.
 *
 * @package Perform
 * @subpackage Modules/Cache
 * @since 1.7.0
 */

namespace Perform\Modules\Cache;

// Bailout, if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides bounded, spreadsheet-safe access to cache activity.
 */
class CacheActivityService {
	/**
	 * Cache statistics option.
	 *
	 * @var string
	 */
	public const OPTION_NAME = 'perform_cache_stats';

	/**
	 * Maximum data rows included in one export.
	 *
	 * @var int
	 */
	public const MAX_EXPORT_ROWS = 500;

	/**
	 * Build a bounded CSV document from the current cache activity.
	 *
	 * @param array<string, mixed>|null $stats Optional activity snapshot.
	 *
	 * @return string
	 */
	public function export_csv( ?array $stats = null ): string {
		$stream = fopen( 'php://temp/maxmemory:2097152', 'w+' );
		if ( false === $stream ) {
			return "Metric,Item,Value\n";
		}

		fputcsv( $stream, [ 'Metric', 'Item', 'Value' ], ',', '"', '' );

		foreach ( $this->get_export_rows( $stats ) as $row ) {
			fputcsv( $stream, $row, ',', '"', '' );
		}

		rewind( $stream );
		$contents = stream_get_contents( $stream );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- This closes an in-memory php://temp stream, not a filesystem path.
		fclose( $stream );

		return is_string( $contents ) ? $contents : "Metric,Item,Value\n";
	}

	/**
	 * Return export rows for a cache activity snapshot.
	 *
	 * @param array<string, mixed>|null $stats Optional activity snapshot.
	 *
	 * @return array<int, array<int, string>>
	 */
	public function get_export_rows( ?array $stats = null ): array {
		if ( null === $stats ) {
			$stored = get_option( self::OPTION_NAME, [] );
			$stats  = is_array( $stored ) ? $stored : [];
		}

		// Keep the primary hit metric visible even before the first cache hit.
		$stats = array_replace( [ 'hits' => 0 ], $stats );

		$rows = [];
		foreach ( $stats as $metric => $value ) {
			if ( count( $rows ) >= self::MAX_EXPORT_ROWS ) {
				break;
			}

			$metric_label = $this->format_label( $metric );
			if ( is_scalar( $value ) || null === $value ) {
				$rows[] = [
					$this->sanitize_csv_cell( $metric_label ),
					'',
					$this->sanitize_csv_cell( $value ),
				];
				continue;
			}

			if ( ! is_array( $value ) ) {
				continue;
			}

			foreach ( $value as $item => $item_value ) {
				if ( count( $rows ) >= self::MAX_EXPORT_ROWS ) {
					break 2;
				}

				if ( ! is_scalar( $item_value ) && null !== $item_value ) {
					continue;
				}

				$rows[] = [
					$this->sanitize_csv_cell( $metric_label ),
					$this->sanitize_csv_cell( $item ),
					$this->sanitize_csv_cell( $item_value ),
				];
			}
		}

		return $rows;
	}

	/**
	 * Clear cache activity without changing cached pages or cache settings.
	 *
	 * @return void
	 */
	public function clear(): void {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Turn an internal metric key into a readable CSV label.
	 *
	 * @param mixed $value Metric key.
	 *
	 * @return string
	 */
	private function format_label( $value ): string {
		$label = str_replace( [ '_', '-' ], ' ', (string) $value );

		return ucwords( $label );
	}

	/**
	 * Protect exported cells from spreadsheet formula execution.
	 *
	 * @param mixed $value Cell value.
	 *
	 * @return string
	 */
	private function sanitize_csv_cell( $value ): string {
		$cell = is_scalar( $value ) || null === $value ? (string) $value : '';
		if ( preg_match( '/^[=+\-@\t\r]/', $cell ) ) {
			return "'" . $cell;
		}

		return $cell;
	}
}
