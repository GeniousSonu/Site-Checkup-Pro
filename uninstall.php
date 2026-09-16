<?php
/**
 * Site Checkup Pro Uninstall Handler
 *
 * Triggered when the plugin is deleted via the WordPress admin plugins screen.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly or not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom database tables.
$table_status = $wpdb->prefix . 'wpsg_task_status';
$table_audit  = $wpdb->prefix . 'wpsg_audit_log';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$table_status}, {$table_audit};" );

// Delete plugin options.
delete_option( 'wpsg_db_version' );
delete_option( 'wpsg_trusted_baseline' );
delete_option( 'wpsg_settings' );
delete_option( 'wpsg_login_slug' );
delete_option( 'wpsg_last_scan_time' );

// Delete transients.
delete_transient( 'wpsg_plugin_integrity_cache' );
delete_transient( 'wpsg_activation_notice' );

// Clear any remaining scheduled cron events.
wp_clear_scheduled_hook( 'wpsg_scheduled_reminders' );
wp_clear_scheduled_hook( 'wpsg_prune_audit_logs' );
wp_clear_scheduled_hook( 'wpsg_daily_integrity_scan' );
