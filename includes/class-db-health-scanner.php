<?php
/**
 * Database Health Scanner
 *
 * Scans for database bloat and orphaned data (orphaned postmeta, orphaned usermeta,
 * expired transients, excess post revisions), calculates storage impact, and provides
 * a protected Level-B cleanup action gated by password re-auth and recent backup verification.
 *
 * @package GeniousSonu_Site_Checkup
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Db_Health_Scanner
 */
class WPSG_Db_Health_Scanner {

	/**
	 * Default threshold for maximum post revisions kept per post.
	 */
	const DEFAULT_REVISION_THRESHOLD = 5;

	/**
	 * Run complete database health analysis.
	 *
	 * @param int $revision_threshold Max revisions per post before flagged.
	 * @return array
	 */
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This scan interpolates only allowlisted, escaped table identifiers; data values are prepared.
	public static function scan( $revision_threshold = self::DEFAULT_REVISION_THRESHOLD ) {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return array(
				'summary' => array( 'total_items' => 0, 'estimated_savings_kb' => 0 ),
				'details' => array(),
			);
		}

		$current_time = time();

		$posts_table    = wpsg_get_table_name( 'posts' );
		$postmeta_table = wpsg_get_table_name( 'postmeta' );
		$usermeta_table = wpsg_get_table_name( 'usermeta' );
		$users_table    = wpsg_get_table_name( 'users' );
		$options_table  = wpsg_get_table_name( 'options' );
		if ( false === $posts_table || false === $postmeta_table || false === $usermeta_table || false === $users_table || false === $options_table ) {
			return array(
				'summary' => array( 'total_items' => 0, 'estimated_savings_kb' => 0 ),
				'details' => array(),
			);
		}
		$posts_table    = esc_sql( $posts_table );
		$postmeta_table = esc_sql( $postmeta_table );
		$usermeta_table = esc_sql( $usermeta_table );
		$users_table    = esc_sql( $users_table );
		$options_table  = esc_sql( $options_table );

		$like_esc = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( '_transient_timeout_' ) : addcslashes( '_transient_timeout_', '_%\\' );

		// 1. Orphaned postmeta (no matching post in wp_posts)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers come from the fixed table allowlist and are escaped.
		$orphaned_postmeta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$postmeta_table} pm LEFT JOIN {$posts_table} p ON pm.post_id = p.ID WHERE p.ID IS NULL" );

		// 2. Orphaned usermeta (no matching user in wp_users)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers come from the fixed table allowlist and are escaped.
		$orphaned_usermeta = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$usermeta_table} um LEFT JOIN {$users_table} u ON um.user_id = u.ID WHERE u.ID IS NULL" );

		// 3. Expired transients in wp_options
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; all values are prepared.
		$expired_transients = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; values use placeholders.
				"SELECT COUNT(*) FROM {$options_table} WHERE option_name LIKE %s AND option_value < %d",
				$like_esc . '%',
				$current_time
			)
		);

		// 4. Excess post revisions beyond threshold
		$threshold = max( 1, (int) $revision_threshold );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; post type is prepared.
		$total_revisions = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; post type uses a placeholder.
				"SELECT COUNT(*) FROM {$posts_table} WHERE post_type = %s",
				'revision'
			)
		);

		// Count posts having revisions
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; post type is prepared.
		$posts_with_revisions = (int) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; post type uses a placeholder.
				"SELECT COUNT(DISTINCT post_parent) FROM {$posts_table} WHERE post_type = %s AND post_parent > 0",
				'revision'
			)
		);

		$allowed_revisions = $posts_with_revisions * $threshold;
		$excess_revisions  = max( 0, $total_revisions - $allowed_revisions );

		// Estimate storage size
		// Approx: postmeta: 350B, usermeta: 300B, transient: 800B, revision: 3.5KB
		$est_postmeta_kb   = round( ( $orphaned_postmeta * 350 ) / 1024, 2 );
		$est_usermeta_kb   = round( ( $orphaned_usermeta * 300 ) / 1024, 2 );
		$est_transients_kb = round( ( $expired_transients * 2 * 800 ) / 1024, 2 ); // timeout + transient option
		$est_revisions_kb  = round( ( $excess_revisions * 3500 ) / 1024, 2 );

		$total_items      = $orphaned_postmeta + $orphaned_usermeta + $expired_transients + $excess_revisions;
		$total_savings_kb = round( $est_postmeta_kb + $est_usermeta_kb + $est_transients_kb + $est_revisions_kb, 2 );

		return array(
			'summary' => array(
				'total_bloat_items'    => $total_items,
				'estimated_savings_kb' => $total_savings_kb,
				'estimated_savings_mb' => round( $total_savings_kb / 1024, 2 ),
				'has_bloat'            => $total_items > 0,
			),
			'details' => array(
				'orphaned_postmeta'  => array(
					'label'        => __( 'Orphaned Post Metadata', 'genioussonu-security-hardening-audit' ),
					'count'        => $orphaned_postmeta,
					'estimated_kb' => $est_postmeta_kb,
					'description'  => __( 'Meta keys referencing posts that no longer exist.', 'genioussonu-security-hardening-audit' ),
				),
				'orphaned_usermeta'  => array(
					'label'        => __( 'Orphaned User Metadata', 'genioussonu-security-hardening-audit' ),
					'count'        => $orphaned_usermeta,
					'estimated_kb' => $est_usermeta_kb,
					'description'  => __( 'Meta keys referencing deleted user accounts.', 'genioussonu-security-hardening-audit' ),
				),
				'expired_transients' => array(
					'label'        => __( 'Expired Transients', 'genioussonu-security-hardening-audit' ),
					'count'        => $expired_transients,
					'estimated_kb' => $est_transients_kb,
					'description'  => __( 'Stale transient cache rows remaining in the options table.', 'genioussonu-security-hardening-audit' ),
				),
				'excess_revisions'   => array(
					'label'        => __( 'Excess Post Revisions', 'genioussonu-security-hardening-audit' ),
					'count'        => $excess_revisions,
					'total_count'  => $total_revisions,
					'threshold'    => $threshold,
					'estimated_kb' => $est_revisions_kb,
					/* translators: %d: revision threshold */
					'description'  => sprintf( __( 'Post revisions beyond %d per post.', 'genioussonu-security-hardening-audit' ), $threshold ),
				),
			),
			'scanned_at' => current_time( 'mysql' ),
		);
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/**
	 * Perform protected cleanup (Level B).
	 * Requires validated re-authentication token and active backup guard check.
	 *
	 * @param string $type Target type: 'all', 'orphaned_postmeta', 'orphaned_usermeta', 'expired_transients', 'excess_revisions'.
	 * @param string $reauth_token Re-authentication token.
	 * @return array
	 */
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Cleanup interpolates only allowlisted, escaped table identifiers; data values and revision IDs are prepared.
	public static function cleanup( $type = 'all', $reauth_token = '' ) {
		global $wpdb;

		// 1. Authorization check
		if ( ! current_user_can( 'manage_options' ) ) {
			return array( 'success' => false, 'message' => __( 'Insufficient permissions to perform database cleanup.', 'genioussonu-security-hardening-audit' ) );
		}

		// 2. Re-authentication verification
		if ( class_exists( 'WPSG_Session_Manager' ) ) {
			if ( ! WPSG_Session_Manager::validate_reauth_token( $reauth_token ) ) {
				return array(
					'success'      => false,
					'needs_reauth' => true,
					'message'      => __( 'Database cleanup requires fresh administrator password verification.', 'genioussonu-security-hardening-audit' ),
				);
			}
		}

		// 3. Backup guard verification
		if ( class_exists( 'WPSG_Backup_Guard' ) ) {
			$backup_status = WPSG_Backup_Guard::get_backup_status();
			if ( empty( $backup_status['is_recent'] ) ) {
				return array(
					'success'      => false,
					'needs_backup' => true,
					'message'      => __( 'Database cleanup blocked: No verified backup within 48 hours. Please verify or take a backup first.', 'genioussonu-security-hardening-audit' ),
				);
			}
		}

		$deleted_counts = array();
		$posts_table    = wpsg_get_table_name( 'posts' );
		$postmeta_table = wpsg_get_table_name( 'postmeta' );
		$usermeta_table = wpsg_get_table_name( 'usermeta' );
		$users_table    = wpsg_get_table_name( 'users' );
		$options_table  = wpsg_get_table_name( 'options' );
		if ( false === $posts_table || false === $postmeta_table || false === $usermeta_table || false === $users_table || false === $options_table ) {
			return array(
				'success' => false,
				'message' => __( 'Database cleanup stopped because a database table name failed validation.', 'genioussonu-security-hardening-audit' ),
			);
		}
		$posts_table    = esc_sql( $posts_table );
		$postmeta_table = esc_sql( $postmeta_table );
		$usermeta_table = esc_sql( $usermeta_table );
		$users_table    = esc_sql( $users_table );
		$options_table  = esc_sql( $options_table );

		$like_esc = method_exists( $wpdb, 'esc_like' ) ? $wpdb->esc_like( '_transient_timeout_' ) : addcslashes( '_transient_timeout_', '_%\\' );

		// Cleanup orphaned postmeta
		if ( 'all' === $type || 'orphaned_postmeta' === $type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers come from the fixed table allowlist and are escaped.
			$deleted = $wpdb->query(
				"DELETE pm FROM {$postmeta_table} pm LEFT JOIN {$posts_table} p ON pm.post_id = p.ID WHERE p.ID IS NULL"
			);
			$deleted_counts['orphaned_postmeta'] = false !== $deleted ? (int) $deleted : 0;
		}

		// Cleanup orphaned usermeta
		if ( 'all' === $type || 'orphaned_usermeta' === $type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers come from the fixed table allowlist and are escaped.
			$deleted = $wpdb->query(
				"DELETE um FROM {$usermeta_table} um LEFT JOIN {$users_table} u ON um.user_id = u.ID WHERE u.ID IS NULL"
			);
			$deleted_counts['orphaned_usermeta'] = false !== $deleted ? (int) $deleted : 0;
		}

		// Cleanup expired transients
		if ( 'all' === $type || 'expired_transients' === $type ) {
			$current_time = time();
			// Get expired timeout keys
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; values are prepared.
			$expired_keys = $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; values use placeholders.
					"SELECT option_name FROM {$options_table} WHERE option_name LIKE %s AND option_value < %d LIMIT 500",
					$like_esc . '%',
					$current_time
				)
			);

			$transient_deleted = 0;
			if ( ! empty( $expired_keys ) ) {
				foreach ( $expired_keys as $timeout_key ) {
					$transient_key = str_replace( '_transient_timeout_', '_transient_', $timeout_key );
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; option names are prepared.
					$wpdb->query(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; option names use placeholders.
							"DELETE FROM {$options_table} WHERE option_name IN (%s, %s)",
							$timeout_key,
							$transient_key
						)
					);
					$transient_deleted++;
				}
			}
			$deleted_counts['expired_transients'] = $transient_deleted;
		}

		// Cleanup excess revisions
		if ( 'all' === $type || 'excess_revisions' === $type ) {
			// Delete revisions keeping latest 5
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; query contains fixed SQL only.
			$old_revisions = $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; this query has no runtime values.
					"SELECT ID FROM {$posts_table} WHERE post_type = 'revision' AND ID NOT IN (
						SELECT ID FROM (
							SELECT ID FROM {$posts_table} WHERE post_type = 'revision' ORDER BY ID DESC LIMIT 500
						) as recent
					) LIMIT 200"
				)
			);
			$rev_deleted = 0;
			if ( ! empty( $old_revisions ) ) {
				$revision_ids = array_values( array_map( 'absint', $old_revisions ) );
				$id_placeholders = implode( ', ', array_fill( 0, count( $revision_ids ), '%d' ) );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table is allowlisted; placeholder count matches the absint ID array passed variadically.
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table is allowlisted; placeholders exactly match the absint ID array.
						"DELETE FROM {$posts_table} WHERE ID IN ({$id_placeholders})",
						...$revision_ids
					)
				);
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table is allowlisted; placeholder count matches the absint ID array passed variadically.
				$wpdb->query(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table is allowlisted; placeholders exactly match the absint ID array.
						"DELETE FROM {$postmeta_table} WHERE post_id IN ({$id_placeholders})",
						...$revision_ids
					)
				);
				$rev_deleted = count( $old_revisions );
			}
			$deleted_counts['excess_revisions'] = $rev_deleted;
		}

		$total_deleted = array_sum( $deleted_counts );

		// 4. Log to audit trail
		if ( class_exists( 'WPSG_Audit_Logger' ) ) {
			WPSG_Audit_Logger::log(
				'db_health_cleanup',
				sprintf( 'Database cleanup executed (%1$s): %2$d bloat items removed.', $type, $total_deleted ),
				'admin',
				'warning'
			);
		}

		return array(
			'success'        => true,
			'total_deleted'  => $total_deleted,
			'deleted_counts' => $deleted_counts,
			/* translators: %d: number of deleted items */
			'message'        => sprintf( __( 'Database cleanup complete: %d orphaned items removed safely.', 'genioussonu-security-hardening-audit' ), $total_deleted ),
		);
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
