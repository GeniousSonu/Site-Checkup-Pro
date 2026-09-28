<?php
/**
 * Database table identifier validation.
 *
 * @package GeniousSonu_Site_Checkup
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve an allowlisted WordPress or plugin table name for SQL use.
 *
 * @param string $table_key Logical table key.
 * @return string|false Escaped table identifier, or false for an invalid key/prefix.
 */
function wpsg_get_table_name( $table_key ) {
	global $wpdb;

	if ( ! isset( $wpdb->prefix ) || ! is_string( $wpdb->prefix ) || ! preg_match( '/^[a-zA-Z0-9_]+$/', $wpdb->prefix ) ) {
		return false;
	}

	$core_tables = array( 'options', 'posts', 'postmeta', 'usermeta', 'users' );
	if ( in_array( $table_key, $core_tables, true ) ) {
		$table_name = ! empty( $wpdb->{$table_key} ) ? $wpdb->{$table_key} : $wpdb->prefix . $table_key;
	} else {
		$plugin_tables = array(
			'task_status' => 'wpsg_task_status',
			'audit_log'   => 'wpsg_audit_log',
			'rate_limits' => 'wpsg_rate_limits',
		);
		if ( ! isset( $plugin_tables[ $table_key ] ) ) {
			return false;
		}
		$table_name = $wpdb->prefix . $plugin_tables[ $table_key ];
	}

	if ( ! is_string( $table_name ) || ! preg_match( '/^[a-zA-Z0-9_]+$/', $table_name ) ) {
		return false;
	}

	return esc_sql( $table_name );
}
