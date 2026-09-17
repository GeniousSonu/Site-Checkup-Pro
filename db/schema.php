<?php
/**
 * Database Schema for Site Checkup Pro
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates or updates custom database tables using dbDelta.
 *
 * @return void
 */
function wpsg_create_database_tables() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();

	// Table: Task Status
	// Note dbDelta requirements: 2 spaces after PRIMARY KEY, uppercase types, column per line.
	$table_status = $wpdb->prefix . 'wpsg_task_status';
	$sql_status   = "CREATE TABLE {$table_status} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id varchar(100) NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'pending',
		automation_level varchar(5) NOT NULL DEFAULT 'A',
		last_run_at datetime DEFAULT NULL,
		last_run_by bigint(20) unsigned DEFAULT NULL,
		note text DEFAULT NULL,
		next_reminder_at datetime DEFAULT NULL,
		metadata longtext DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY task_id (task_id),
		KEY status (status),
		KEY automation_level (automation_level)
	) {$charset_collate};";

	dbDelta( $sql_status );

	// Table: Audit Log (Redacted, no plain secrets)
	$table_audit = $wpdb->prefix . 'wpsg_audit_log';
	$sql_audit   = "CREATE TABLE {$table_audit} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		task_id varchar(100) NOT NULL,
		action varchar(50) NOT NULL,
		user_id bigint(20) unsigned NOT NULL,
		before_snapshot longtext DEFAULT NULL,
		after_snapshot longtext DEFAULT NULL,
		result varchar(20) NOT NULL DEFAULT 'success',
		message text DEFAULT NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY task_id (task_id),
		KEY created_at (created_at),
		KEY result (result)
	) {$charset_collate};";

	dbDelta( $sql_audit );

	// Table: Rate Limits (Atomic login/reauth throttling and lockout)
	$table_rate_limits = $wpdb->prefix . 'wpsg_rate_limits';
	$sql_rate_limits   = "CREATE TABLE {$table_rate_limits} (
		rate_key varchar(64) NOT NULL,
		attempts int unsigned NOT NULL DEFAULT 1,
		first_attempt datetime NOT NULL,
		last_attempt datetime NOT NULL,
		locked_until datetime DEFAULT NULL,
		PRIMARY KEY  (rate_key),
		KEY locked_until (locked_until),
		KEY last_attempt (last_attempt)
	) {$charset_collate};";

	dbDelta( $sql_rate_limits );

	// Store current schema version in options
	update_option( 'wpsg_db_version', WPSG_VERSION );
}

/**
 * Drops custom database tables upon uninstall if requested.
 *
 * @return void
 */
function wpsg_drop_database_tables() {
	global $wpdb;

	$table_status      = $wpdb->prefix . 'wpsg_task_status';
	$table_audit       = $wpdb->prefix . 'wpsg_audit_log';
	$table_rate_limits = $wpdb->prefix . 'wpsg_rate_limits';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query( "DROP TABLE IF EXISTS {$table_status}, {$table_audit}, {$table_rate_limits};" );

	delete_option( 'wpsg_db_version' );
	delete_option( 'wpsg_trusted_baseline' );
	delete_option( 'wpsg_settings' );
}
