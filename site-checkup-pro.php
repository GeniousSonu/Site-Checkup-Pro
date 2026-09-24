<?php
/**
 * Plugin Name:       Site Checkup Pro
 * Plugin URI:        https://www.genioussonu.me/plugin/site-checkup-pro/
 * Description:       Complete security audit, site hardening checklist, and vulnerability scanner for WordPress. One-click safe hardening, login protection, and client reports.
 * Version:           1.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            SK Sahinur Islam
 * Author URI:        https://www.genioussonu.me/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       site-checkup-pro
 * Domain Path:       /languages
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/site-checkup-pro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin Constants.
define( 'WPSG_VERSION', '1.3.0' );
define( 'WPSG_PLUGIN_FILE', __FILE__ );
define( 'WPSG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPSG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPSG_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPSG_PLUGIN_BASENAME', WPSG_BASENAME );

/**
 * Autoload plugin classes.
 *
 * @param string $class Class name.
 * @return void
 */
spl_autoload_register( function ( $class ) {
	// Only autoload classes with WPSG_ prefix.
	if ( 0 !== strpos( $class, 'WPSG_' ) ) {
		return;
	}

	$class_name = strtolower( str_replace( '_', '-', substr( $class, 5 ) ) );
	$file_name  = 'class-' . $class_name . '.php';

	// Check includes directory.
	$paths = array(
		WPSG_PLUGIN_DIR . 'includes/' . $file_name,
		WPSG_PLUGIN_DIR . 'includes/integrations/' . $file_name,
		WPSG_PLUGIN_DIR . 'admin/' . $file_name,
		WPSG_PLUGIN_DIR . 'rest-api/' . $file_name,
	);

	foreach ( $paths as $path ) {
		if ( file_exists( $path ) ) {
			require_once $path;
			return;
		}
	}
} );

// Include Database Schema functions.
require_once WPSG_PLUGIN_DIR . 'db/schema.php';

/**
 * Activation hook callback.
 */
function wpsg_activate() {
	// 1. Create/update custom DB tables.
	wpsg_create_database_tables();

	// 2. Take initial baseline snapshot for rogue-admin and wp_options drift tracking.
	if ( class_exists( 'WPSG_Scanner' ) ) {
		WPSG_Scanner::take_initial_baseline();
	}

	// 3. Register WP-Cron scheduled events.
	if ( class_exists( 'WPSG_Scheduler' ) ) {
		WPSG_Scheduler::register_schedules();
	}

	// Set activation flag for initial welcome notice.
	set_transient( 'wpsg_activation_notice', true, 60 );
}
register_activation_hook( __FILE__, 'wpsg_activate' );

/**
 * Deactivation hook callback.
 */
function wpsg_deactivate() {
	// Clear scheduled cron events.
	if ( class_exists( 'WPSG_Scheduler' ) ) {
		WPSG_Scheduler::clear_schedules();
	}
}
register_deactivation_hook( __FILE__, 'wpsg_deactivate' );

/**
 * Check PHP version compatibility.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Site Checkup Pro requires PHP 7.4 or higher. Please upgrade your PHP version.', 'site-checkup-pro' )
		);
	} );
	return;
}

/**
 * Multisite notice & compatibility handling.
 * Note: v1 is single-site focused. On multisite installations, only Super Admins may manage settings.
 */
if ( is_multisite() ) {
	add_action( 'admin_notices', function () {
		if ( ! current_user_can( 'manage_network' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning is-dismissible"><p><strong>%s:</strong> %s</p></div>',
			esc_html__( 'Site Checkup Pro (Multisite Notice)', 'site-checkup-pro' ),
			esc_html__( 'Version 1.0 of Site Checkup Pro is configured for single-site audits. Network-wide operations are restricted to Super Administrators.', 'site-checkup-pro' )
		);
	} );
}

/**
 * Initialize main plugin singleton.
 */
add_action( 'plugins_loaded', function () {
	if ( class_exists( 'WPSG_Plugin' ) ) {
		WPSG_Plugin::get_instance();
	}
} );
