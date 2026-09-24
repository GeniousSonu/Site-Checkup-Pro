<?php
/**
 * Site Checkup Pro Uninstall Handler
 *
 * Triggered when the plugin is deleted via the WordPress admin plugins screen.
 * Thoroughly purges all custom database tables, options, transients, and cron hooks.
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.0.0
 */

// Exit if accessed directly or not uninstalling.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom database tables.
$table_status      = $wpdb->prefix . 'wpsg_task_status';
$table_audit       = $wpdb->prefix . 'wpsg_audit_log';
$table_rate_limits = $wpdb->prefix . 'wpsg_rate_limits';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$table_status}, {$table_audit}, {$table_rate_limits};" );

// Delete plugin options.
delete_option( 'wpsg_db_version' );
delete_option( 'wpsg_trusted_baseline' );
delete_option( 'wpsg_settings' );
delete_option( 'wpsg_login_slug' );
delete_option( 'wpsg_last_scan_time' );
delete_option( 'wpsg_block_user_enumeration' );
delete_option( 'wpsg_login_hardening' );
delete_option( 'wpsg_trusted_proxies' );
delete_option( 'wpsg_dismissed_notices' );
delete_option( 'wpsg_declutter_dashboard' );
delete_option( 'wpsg_hide_generator' );
delete_option( 'wpsg_strip_ver' );
delete_option( 'wpsg_csp_mode' );
delete_option( 'wpsg_alert_settings' );
delete_option( 'wpsg_last_security_txt_backup' );

// Delete transients.
delete_transient( 'wpsg_plugin_integrity_cache' );
delete_transient( 'wpsg_activation_notice' );
delete_transient( 'wpsg_core_checksums' );
delete_transient( 'wpsg_csp_violations' );
delete_transient( 'wpsg_vulnerability_cache' );
delete_transient( 'wpsg_file_perms_cache' );
delete_transient( 'wpsg_php_restrictions_cache' );
delete_transient( 'wpsg_db_prefix_cache' );
delete_transient( 'wpsg_debug_display_cache' );
delete_transient( 'wpsg_tls_depth_cache' );
delete_transient( 'wpsg_email_auth_cache' );
delete_transient( 'wpsg_due_reminders_count' );

// Clear any remaining scheduled cron events.
wp_clear_scheduled_hook( 'wpsg_scheduled_reminders' );
wp_clear_scheduled_hook( 'wpsg_prune_audit_logs' );
wp_clear_scheduled_hook( 'wpsg_prune_rate_limits' );
wp_clear_scheduled_hook( 'wpsg_daily_integrity_scan' );
