<?php
/**
 * Audit Log Handler with Sensitive Data Redaction
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Audit_Log
 */
class WPSG_Audit_Log {

	/**
	 * Record an entry into the audit log table.
	 *
	 * @param string $task_id         Task ID.
	 * @param string $action          Action performed (run, undo, mark_done, note_update).
	 * @param mixed  $before_snapshot State before action.
	 * @param mixed  $after_snapshot  State after action.
	 * @param string $result          Result ('success' or 'failed').
	 * @param string $message         Descriptive message.
	 * @return int|false Insert ID or false on failure.
	 */
	public static function log( $task_id, $action, $before_snapshot = null, $after_snapshot = null, $result = 'success', $message = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpsg_audit_log';
		$user_id    = get_current_user_id();

		// Sanitize snapshots to guarantee zero sensitive keys or passwords are saved.
		$sanitized_before = self::sanitize_snapshot( $task_id, $before_snapshot );
		$sanitized_after  = self::sanitize_snapshot( $task_id, $after_snapshot );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(
			$table_name,
			array(
				'task_id'         => sanitize_key( $task_id ),
				'action'          => sanitize_text_field( $action ),
				'user_id'         => absint( $user_id ),
				'before_snapshot' => $sanitized_before ? wp_json_encode( $sanitized_before ) : null,
				'after_snapshot'  => $sanitized_after ? wp_json_encode( $sanitized_after ) : null,
				'result'          => 'failed' === $result ? 'failed' : 'success',
				'message'         => sanitize_text_field( $message ),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Sanitizes snapshot data to protect database credentials, salts, and secret keys.
	 *
	 * @param string $task_id Task ID.
	 * @param mixed  $data    Raw snapshot data.
	 * @return array|null Sanitized data array.
	 */
	public static function sanitize_snapshot( $task_id, $data ) {
		if ( empty( $data ) ) {
			return null;
		}

		// 1. Salt rotation: NEVER log old or new key material.
		if ( 'rotate_salts' === $task_id ) {
			return array(
				'salts_rotated' => true,
				'timestamp'     => current_time( 'mysql' ),
				'keys_count'    => 8,
			);
		}

		// 2. Config changes: Store only changed constant names + boolean states.
		if ( 'disable_file_edit' === $task_id || false !== strpos( $task_id, 'wp_config' ) ) {
			if ( is_array( $data ) ) {
				$clean = array();
				foreach ( $data as $k => $v ) {
					// Only allow specific recognized boolean flags.
					if ( in_array( $k, array( 'DISALLOW_FILE_EDIT', 'DISALLOW_FILE_MODS' ), true ) ) {
						$clean[ $k ] = (bool) $v;
					}
				}
				return ! empty( $clean ) ? $clean : array( 'constants_updated' => true );
			}
			return array( 'file_modified' => true );
		}

		// 3. Recursive scrub for any general snapshot arrays.
		if ( is_array( $data ) ) {
			return self::recursive_scrub( $data );
		}

		if ( is_string( $data ) ) {
			// If snapshot is raw string, never store if it looks like config/code.
			if ( false !== strpos( $data, 'DB_PASSWORD' ) || false !== strpos( $data, 'AUTH_KEY' ) ) {
				return array( 'raw_content' => '[REDACTED_FOR_SECURITY]' );
			}
			return array( 'summary' => sanitize_text_field( substr( $data, 0, 500 ) ) );
		}

		return array( 'value' => '[FILTERED]' );
	}

	/**
	 * Recursively scrub sensitive keys from an array.
	 *
	 * @param array $array Array to scrub.
	 * @return array Scrubbed array.
	 */
	private static function recursive_scrub( array $array ) {
		$sensitive_pattern = '/(pass|pwd|secret|key|salt|token|auth|cookie|hash)/i';
		$scrubbed          = array();

		foreach ( $array as $k => $v ) {
			if ( is_string( $k ) && preg_match( $sensitive_pattern, $k ) ) {
				$scrubbed[ $k ] = '[REDACTED]';
			} elseif ( is_array( $v ) ) {
				$scrubbed[ $k ] = self::recursive_scrub( $v );
			} else {
				$scrubbed[ $k ] = $v;
			}
		}

		return $scrubbed;
	}

	/**
	 * Get recent audit logs.
	 *
	 * @param int $limit  Number of records.
	 * @param int $offset Offset.
	 * @return array
	 */
	public static function get_logs( $limit = 50, $offset = 0 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpsg_audit_log';
		$limit      = absint( $limit );
		$offset     = absint( $offset );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, u.user_login, u.display_name 
				FROM {$table_name} a 
				LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
				ORDER BY a.created_at DESC 
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Prune old audit logs older than a specified number of days (default: 365 days / 12 months).
	 *
	 * @param int $days Retention days.
	 * @return int Number of deleted rows.
	 */
	public static function prune_old_logs( $days = 365 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpsg_audit_log';
		$days       = absint( $days );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return (int) $deleted;
	}
}
