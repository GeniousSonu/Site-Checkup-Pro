<?php
/**
* Main Plugin Orchestrator Class
 *
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Plugin
 */
class WPSG_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_components();
		$this->init_runtime_hardening();
	}

	/**
	 * Whitelist of options this plugin is permitted to touch.
	 * Blocks arbitrary option update tampering.
	 *
	 * @var array
	 */
	public static $allowed_options = array(
		'wpsg_trusted_baseline',
		'wpsg_settings',
		'wpsg_login_slug',
		'wpsg_last_scan_time',
		'wpsg_db_version',
		'wpsg_manual_backup_confirmed_until',
		'wpsg_block_user_enumeration',
		'wpsg_login_hardening',
		'wpsg_trusted_proxies',
		'wpsg_dismissed_notices',
		'wpsg_declutter_dashboard',
		'wpsg_hide_generator',
		'wpsg_strip_ver',
		'wpsg_csp_mode',
		'wpsg_alert_settings',
		'wpsg_focus_mode',
		'wpsg_environment_type',
	);

	/**
	 * Initialize core components.
	 */
	private function init_components() {
		// Schema upgrade check on version bump: ensures dbDelta runs on update, not just first install.
		$installed_db_ver = get_option( 'wpsg_db_version', '0.0.0' );
		if ( version_compare( $installed_db_ver, WPSG_VERSION, '<' ) ) {
			if ( function_exists( 'wpsg_create_database_tables' ) ) {
				wpsg_create_database_tables();
			}
		}

		// Initialize Task Registry.
		if ( class_exists( 'WPSG_Task_Registry' ) ) {
			WPSG_Task_Registry::get_instance();
		}

		// Initialize Admin Interface.
		if ( is_admin() && class_exists( 'WPSG_Admin_Menu' ) ) {
			WPSG_Admin_Menu::get_instance();
		}

		// Initialize REST API on rest_api_init and during components load.
		add_action( 'rest_api_init', function () {
			if ( ! class_exists( 'WP_REST_Controller' ) ) {
				$rest_path = defined( 'ABSPATH' ) ? ABSPATH . ( defined( 'WPINC' ) ? WPINC : 'wp-includes' ) . '/rest-api/endpoints/class-wp-rest-controller.php' : '';
				if ( $rest_path && file_exists( $rest_path ) ) {
					require_once $rest_path;
				}
			}
			if ( class_exists( 'WPSG_Rest_Controller' ) ) {
				WPSG_Rest_Controller::get_instance();
			}
		} );

		if ( ! class_exists( 'WP_REST_Controller' ) ) {
			$rest_path = defined( 'ABSPATH' ) ? ABSPATH . ( defined( 'WPINC' ) ? WPINC : 'wp-includes' ) . '/rest-api/endpoints/class-wp-rest-controller.php' : '';
			if ( $rest_path && file_exists( $rest_path ) ) {
				require_once $rest_path;
			}
		}
		if ( class_exists( 'WPSG_Rest_Controller' ) ) {
			WPSG_Rest_Controller::get_instance();
		}

		// Initialize Scheduler.
		if ( class_exists( 'WPSG_Scheduler' ) ) {
			WPSG_Scheduler::get_instance();
		}

		// Initialize Login Renamer (if enabled and safe).
		if ( class_exists( 'WPSG_Login_Renamer' ) ) {
			WPSG_Login_Renamer::get_instance();
		}

		// Initialize Login Guard.
		if ( class_exists( 'WPSG_Login_Guard' ) ) {
			WPSG_Login_Guard::get_instance();
		}

		// Initialize Session Manager.
		if ( class_exists( 'WPSG_Session_Manager' ) ) {
			WPSG_Session_Manager::get_instance();
		}

		// Initialize User Enumeration Guard.
		if ( class_exists( 'WPSG_Enumeration_Guard' ) ) {
			WPSG_Enumeration_Guard::get_instance();
		}

		// Initialize Fingerprint Guard.
		if ( class_exists( 'WPSG_Fingerprint_Guard' ) ) {
			WPSG_Fingerprint_Guard::get_instance();
		}

		// Initialize Notice Inbox & Focus Mode.
		if ( class_exists( 'WPSG_Notice_Inbox' ) ) {
			WPSG_Notice_Inbox::get_instance();
		}

		// Initialize Environment Badge.
		if ( class_exists( 'WPSG_Environment_Badge' ) ) {
			WPSG_Environment_Badge::get_instance();
		}

		// Initialize Weekly Changelog Digest.
		if ( class_exists( 'WPSG_Changelog_Digest' ) ) {
			WPSG_Changelog_Digest::get_instance();
		}

		// Ensure Auto-Update support is registered for background updater.
		add_filter( 'auto_update_plugin', array( $this, 'filter_auto_update_plugin' ), 10, 2 );

		// Initialize Self-Hosted Update Checker (Excluded from WordPress.org directory releases per Guideline 8).
		if ( file_exists( WPSG_PLUGIN_DIR . 'includes/class-update-checker.php' ) && defined( 'WPSG_PLUGIN_FILE' ) ) {
			require_once WPSG_PLUGIN_DIR . 'includes/class-update-checker.php';
			if ( class_exists( 'WPSG_Update_Checker' ) ) {
				new WPSG_Update_Checker( WPSG_PLUGIN_FILE, WPSG_VERSION );
			}
		}
	}

	/**
	 * Apply active PHP runtime hardening rules configured via the plugin.
	 */
	private function init_runtime_hardening() {
		// 1. Runtime XML-RPC Disabling.
		if ( $this->is_task_active( 'disable_xmlrpc' ) ) {
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'wp_headers', array( $this, 'filter_xmlrpc_headers' ) );
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		// 2. Runtime REST API User Enumeration Restriction.
		if ( $this->is_task_active( 'restrict_rest_api' ) ) {
			add_filter( 'rest_authentication_errors', array( $this, 'restrict_rest_user_enumeration' ) );
		}

		// 3. Runtime Auto-Update Disabling.
		if ( $this->is_task_active( 'toggle_auto_updates' ) ) {
			add_filter( 'auto_update_plugin', '__return_false' );
			add_filter( 'auto_update_theme', '__return_false' );
			add_filter( 'auto_update_core', '__return_false' );
			add_filter( 'auto_update_translation', '__return_false' );
		}
	}

	/**
	 * Remove pingback/XML-RPC headers.
	 *
	 * @param array $headers HTTP headers.
	 * @return array
	 */
	public function filter_xmlrpc_headers( $headers ) {
		if ( isset( $headers['X-Pingback'] ) ) {
			unset( $headers['X-Pingback'] );
		}
		return $headers;
	}

	/**
	 * Restrict unauthenticated REST API user enumeration requests.
	 *
	 * @param WP_Error|null|bool $result Error from previous filter.
	 * @return WP_Error|null|bool
	 */
	public function restrict_rest_user_enumeration( $result ) {
		if ( true === $result || is_wp_error( $result ) ) {
			return $result;
		}

		// If user is already authenticated, allow request.
		if ( is_user_logged_in() ) {
			return $result;
		}

		// Check if accessing user routes.
		$rest_route = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( false !== strpos( $rest_route, '/wp/v2/users' ) ) {
			return new WP_Error(
				'rest_forbidden_user_enumeration',
				__( 'User enumeration via REST API is disabled for unauthenticated visitors.', 'site-checkup-pro' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return $result;
	}

	/**
	 * Check if a task status is currently 'done'.
	 *
	 * @param string $task_id Task identifier.
	 * @return bool
	 */
	public function is_task_active( $task_id ) {
		global $wpdb;
		$raw_table  = preg_match( '/^[a-zA-Z0-9_]+$/', $wpdb->prefix . 'wpsg_task_status' ) ? $wpdb->prefix . 'wpsg_task_status' : 'wp_wpsg_task_status';
		$table_name = esc_sql( $raw_table );

		// Quick cached check or DB lookup.
		$cache_key = 'wpsg_task_status_' . $task_id;
		$status    = wp_cache_get( $cache_key, 'site-checkup-pro' );

		if ( false === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$status = $wpdb->get_var(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is strictly validated via regex and escaped via esc_sql().
					"SELECT status FROM {$table_name} WHERE task_id = %s LIMIT 1",
					$task_id
				)
			);
			wp_cache_set( $cache_key, $status, 'site-checkup-pro', 300 );
		}

		return ( 'done' === $status );
	}

	/**
	 * Filter update transient (delegates to WPSG_Update_Checker if present).
	 *
	 * @param object|false $transient The update transient.
	 * @return object|false
	 */
	public function filter_update_plugins_transient( $transient ) {
		if ( class_exists( 'WPSG_Update_Checker' ) ) {
			return WPSG_Update_Checker::filter_update_plugins_transient( $transient );
		}
		return $transient;
	}

	/**
	 * Filter whether this plugin should be automatically updated in the background.
	 * Respects WordPress's standard auto_update_plugins site option.
	 *
	 * @param bool|null $update Whether to update.
	 * @param object    $item   The plugin update item.
	 * @return bool|null
	 */
	public function filter_auto_update_plugin( $update, $item ) {
		$basename = defined( 'WPSG_BASENAME' ) ? WPSG_BASENAME : 'site-checkup-pro/site-checkup-pro.php';
		if ( isset( $item->plugin ) && $basename === $item->plugin ) {
			$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
			return in_array( $basename, $auto_updates, true );
		}
		return $update;
	}
}
