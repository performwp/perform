<?php
/**
 * Transactional, reversible autoload policy management. Option values never leave the database layer.
 *
 * @package Perform
 */
namespace Perform\Admin\Settings;

use RuntimeException;
use Throwable;

/** Per-site policy changes with a bounded undo journal. */
final class AutoloadOptionsManager {
	const JOURNAL = 'perform_autoload_changes';
	const LIMIT   = 200;

	/** Return a reason when this database cannot provide atomic undo records. */
	public static function unsupported_reason(): string {
		global $wpdb, $wp_version;
		if ( version_compare( (string) $wp_version, '6.4', '<' ) ) {
			return __( 'Autoload management requires WordPress 6.4 or newer.', 'perform' );
		}
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
			return __( 'Autoload management requires an InnoDB options table; this database remains read-only.', 'perform' );
		}
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $wpdb->options ), ARRAY_A );
		return is_array( $status ) && 'innodb' === strtolower( $status['Engine'] ?? '' ) ? '' : __( 'Autoload management requires a verified InnoDB options table.', 'perform' );
	}

	/** Critical bootstrap, security and internal options are never editable. */
	public static function is_protected( string $name ): bool {
		return in_array( $name, [ 'siteurl', 'home', 'active_plugins', 'template', 'stylesheet', 'current_theme', 'cron', 'rewrite_rules', 'db_version', 'initial_db_version', 'site_icon', 'admin_email', 'new_admin_email', 'users_can_register', 'default_role', 'upload_path', 'upload_url_path', 'uninstall_plugins' ], true )
			|| preg_match( '/^(?:perform_|_transient_|_site_transient_|theme_mods_|widget_|sidebars_widgets|.*user_roles$)/', $name );
	}

	/**
	 * Recognized storage policies for the installed core version.
	 * @return array<int,string>
	 */
	private static function policies(): array {
		global $wp_version;
		return version_compare( (string) $wp_version, '6.6', '<' ) ? [ 'yes', 'no' ] : [ 'yes', 'no', 'on', 'off', 'auto', 'auto-on', 'auto-off' ];
	}

	/** Follow the running WordPress version and its autoload filters. */
	private static function enabled( string $policy ): bool {
		$values = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : [ 'yes' ];
		return in_array( $policy, $values, true );
	}

	/**
	 * Sign only metadata with a short-lived site-bound token.
	 * @param array<string,mixed> $row Database metadata.
	 */
	private static function token( array $row, ?int $expires = null ): string {
		$expires = null === $expires ? time() + 1800 : (int) $expires;
		$data    = wp_json_encode( [ get_current_blog_id(), $row['option_id'], $row['option_name'], $row['autoload'], $row['value_hash'], $expires ] );
		return $expires . '.' . hash_hmac( 'sha256', $data, wp_salt( 'nonce' ) );
	}

	/**
	 * Parse journal without exposing raw option values.
	 * @param mixed $raw Stored journal JSON.
	 * @return array<string,array<string,mixed>>
	 */
	private static function journal( $raw ): array {
		$value = json_decode( (string) $raw, true );
		if ( ! is_array( $value ) || count( $value ) > self::LIMIT ) {
			throw new RuntimeException( __( 'The autoload change journal is unavailable. No change was made.', 'perform' ) );
		}
		foreach ( $value as $name => $entry ) {
			if ( ! is_string( $name ) || ! is_array( $entry ) || ! isset( $entry['original'], $entry['applied'], $entry['hash'], $entry['time'], $entry['id'] ) || ! in_array( $entry['original'], self::policies(), true ) || ! in_array( $entry['applied'], [ 'yes', 'no', 'on', 'off' ], true ) || ! is_string( $entry['hash'] ) || ! preg_match( '/^[a-f0-9]{64}$/', $entry['hash'] ) ) {
				throw new RuntimeException( __( 'The autoload change journal is invalid. No change was made.', 'perform' ) );
			}
		}
		return $value;
	}

	/**
	 * Search a bounded page, including options currently not autoloaded.
	 * @return array<string,mixed>
	 */
	public static function listing( string $search = '', int $page = 1, string $view = 'all' ): array {
		global $wpdb;
		$page   = max( 1, (int) $page );
		$reason = self::unsupported_reason();
		// SHA2 is needed for stale tokens; unsupported engines must not receive write tokens.
		$hash        = '' === $reason ? 'SHA2(option_value,256)' : "''";
		$journal_raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", self::JOURNAL ) );
		$journal     = null === $journal_raw ? [] : self::journal( $journal_raw );
		$where       = 'option_name LIKE %s';
		$params      = [ '%' . $wpdb->esc_like( substr( $search, 0, 191 ) ) . '%' ];
		if ( 'changed' === $view ) {
			$where .= empty( $journal ) ? ' AND 1=0' : ' AND option_name IN (' . implode( ',', array_fill( 0, count( $journal ), '%s' ) ) . ')';
			$params = array_merge( $params, array_keys( $journal ) );
		}
		// Table identity is WordPress-owned; hash and WHERE fragments are fixed SQL with bound input.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		$total    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE {$where}", $params ) );
		$params[] = ( $page - 1 ) * 25;
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT option_id,option_name,autoload,OCTET_LENGTH(option_value) AS size_bytes,{$hash} AS value_hash FROM {$wpdb->options} WHERE {$where} ORDER BY option_name LIMIT 25 OFFSET %d", $params ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		if ( ! is_array( $rows ) || $wpdb->last_error ) {
			throw new RuntimeException( __( 'Options could not be listed. Please retry.', 'perform' ) );
		}
		$items = [];
		foreach ( $rows as $row ) {
			$name    = $row['option_name'];
			$entry   = $journal[ $name ] ?? null;
			$items[] = [
				'name'             => $name,
				'sizeBytes'        => (int) $row['size_bytes'],
				'autoload'         => $row['autoload'],
				'enabled'          => self::enabled( $row['autoload'] ),
				'protected'        => (bool) self::is_protected( $name ) || ! in_array( $row['autoload'], self::policies(), true ),
				'protectionReason' => self::is_protected( $name ) ? __( 'Critical WordPress or internal Perform option.', 'perform' ) : ( in_array( $row['autoload'], self::policies(), true ) ? '' : __( 'Unrecognized autoload policy.', 'perform' ) ),
				'changed'          => null !== $entry,
				'canUndo'          => is_array( $entry ) && (int) $entry['id'] === (int) $row['option_id'] && $entry['applied'] === $row['autoload'] && $entry['hash'] === $row['value_hash'],
				'token'            => '' === $reason && ! self::is_protected( $name ) && in_array( $row['autoload'], self::policies(), true ) ? self::token( $row ) : '',
			];
		}
		return [
			'supported' => '' === $reason,
			'reason'    => $reason,
			'page'      => $page,
			'pageSize'  => 25,
			'total'     => $total,
			'items'     => $items,
		];
	}

	/**
	 * Atomically change policy and journal, or roll back both.
	 * @return array{byteDelta:int,message:string}
	 */
	public static function change( string $name, string $token, string $target, bool $undo = false ): array {
		global $wpdb, $wp_version;
		$reason = self::unsupported_reason();
		if ( $reason || self::is_protected( $name ) || ! in_array( $target, [ 'on', 'off' ], true ) ) {
			throw new RuntimeException( '' !== $reason ? $reason : __( 'This option cannot be changed.', 'perform' ) );
		}
		$target_enabled = 'on' === $target;
		if ( version_compare( (string) $wp_version, '6.6', '<' ) ) {
			$target = $target_enabled ? 'yes' : 'no';
		}
		if ( ! $undo && self::enabled( $target ) !== $target_enabled ) {
			throw new RuntimeException( __( 'The site autoload policy does not allow this change.', 'perform' ) );
		}
		$transaction = false;
		$byte_delta  = 0;
		try {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				throw new RuntimeException( __( 'An atomic change could not be started.', 'perform' ) );
			}
			$transaction = true;
			// Acquire the journal write lock directly, including first use. INSERT IGNORE
			// can take a duplicate-key shared lock that deadlocks during a concurrent upgrade.
			if ( false === $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_name=option_name", self::JOURNAL, '{}' ) ) ) {
				throw new RuntimeException( __( 'The undo journal could not be created.', 'perform' ) );
			}
			$raw     = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE", self::JOURNAL ) );
			$journal = self::journal( $raw );
			$row     = $wpdb->get_row( $wpdb->prepare( "SELECT option_id,option_name,autoload,OCTET_LENGTH(option_value) AS size_bytes,SHA2(option_value,256) AS value_hash FROM {$wpdb->options} WHERE option_name=%s FOR UPDATE", $name ), ARRAY_A );
			$expires = (int) strtok( $token, '.' );
			if ( ! is_array( $row ) || $row['option_name'] !== $name || self::is_protected( $row['option_name'] ) || $expires < time() || $expires > time() + 1800 || ! hash_equals( self::token( $row, $expires ), $token ) ) {
				throw new RuntimeException( __( 'This option changed or the review expired. Refresh before trying again.', 'perform' ) );
			}
			if ( ! in_array( $row['autoload'], self::policies(), true ) ) {
				throw new RuntimeException( __( 'Unrecognized autoload policy. No change was made.', 'perform' ) );
			}
			$existing = $journal[ $name ] ?? null;
			if ( $undo ) {
				if ( ! is_array( $existing ) || (int) $existing['id'] !== (int) $row['option_id'] || $existing['applied'] !== $row['autoload'] || $existing['hash'] !== $row['value_hash'] ) {
					throw new RuntimeException( __( 'Undo is stale or unavailable. No change was made.', 'perform' ) );
				}
				$target = $existing['original'];
				if ( ! in_array( $target, self::policies(), true ) ) {
					throw new RuntimeException( __( 'Invalid original policy. No change was made.', 'perform' ) );
				}
				unset( $journal[ $name ] );
			} else {
				if ( self::enabled( $row['autoload'] ) === $target_enabled ) {
					throw new RuntimeException( __( 'This option already has the requested policy. No change was made.', 'perform' ) );
				}
				if ( null === $existing && count( $journal ) >= self::LIMIT ) {
					throw new RuntimeException( __( 'The undo journal is full. Undo an existing change before adding another.', 'perform' ) );
				}
				if ( is_array( $existing ) && ( (int) $existing['id'] !== (int) $row['option_id'] || $existing['applied'] !== $row['autoload'] || $existing['hash'] !== $row['value_hash'] ) ) {
					throw new RuntimeException( __( 'This tracked option changed elsewhere. Its original undo record was preserved.', 'perform' ) );
				}
				$journal[ $name ] = [
					'id'       => (int) $row['option_id'],
					'original' => $existing['original'] ?? $row['autoload'],
					'applied'  => $target,
					'hash'     => $row['value_hash'],
					'time'     => time(),
				];
			}
			$byte_delta = ( (int) self::enabled( $target ) - (int) self::enabled( $row['autoload'] ) ) * (int) $row['size_bytes'];
			$changed    = $target === $row['autoload'] ? 1 : $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET autoload=%s WHERE option_id=%d AND option_name=%s AND BINARY autoload=BINARY %s AND SHA2(option_value,256)=%s", $target, $row['option_id'], $name, $row['autoload'], $row['value_hash'] ) );
			if ( 1 !== $changed || 1 !== $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value=%s,autoload='no' WHERE option_name=%s", wp_json_encode( $journal ), self::JOURNAL ) ) ) {
				throw new RuntimeException( __( 'The option and undo record could not both be saved. No change was made.', 'perform' ) );
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new RuntimeException( __( 'The database did not confirm the change. Refresh to check its state.', 'perform' ) );
			}
			$transaction = false;
		} catch ( Throwable $error ) {
			if ( $transaction ) {
				$wpdb->query( 'ROLLBACK' );
			}
			throw $error;
		} finally {
			// Core caches both positive and negative lookups, plus the autoload aggregate.
			foreach ( [ $name, self::JOURNAL, 'alloptions', 'notoptions' ] as $key ) {
				wp_cache_delete( $key, 'options' );
			}
		}
		return [
			'byteDelta' => $byte_delta,
			'message'   => $undo ? __( 'Original autoload policy restored.', 'perform' ) : __( 'Autoload policy changed. The option value was not modified.', 'perform' ),
		];
	}
}
