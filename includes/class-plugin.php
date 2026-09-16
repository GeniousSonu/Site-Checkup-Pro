<?php
/**
 * Main Plugin Orchestrator Class
 *
 * @package SiteCheckupPro
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
	 * Initialize core components.
	 */
	private function init_components() {
		// Initialize Task Registry.
		if ( class_exists( 'WPSG_Task_Registry' ) ) {
			WPSG_Task_Registry::get_instance();
		}

		// Initialize Admin Interface.
		if ( is_admin() && class_exists( 'WPSG_Admin_Menu' ) ) {
			WPSG_Admin_Menu::get_instance();
		}

		// Initialize REST API.
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
		$table_name = $wpdb->prefix . 'wpsg_task_status';

		// Quick cached check or DB lookup.
		$cache_key = 'wpsg_task_status_' . $task_id;
		$status    = wp_cache_get( $cache_key, 'site-checkup-pro' );

		if ( false === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$status = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT status FROM {$table_name} WHERE task_id = %s LIMIT 1",
					$task_id
				)
			);
			wp_cache_set( $cache_key, $status, 'site-checkup-pro', 300 );
		}

		return ( 'done' === $status );
	}
}
