<?php
/**
 * Admin Menu & Assets Enqueuer
 *
 * Registers the top-level Site Checkup dashboard menu and loads CSS/JS assets.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Admin_Menu
 */
class WPSG_Admin_Menu {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Admin_Menu|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Admin_Menu
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin menu and submenus.
	 */
	public function register_menu() {
		$hook = add_menu_page(
			__( 'Site Checkup Pro', 'site-checkup-pro' ),
			__( 'Site Checkup', 'site-checkup-pro' ),
			'manage_options',
			'site-checkup-pro',
			array( $this, 'render_dashboard' ),
			'dashicons-shield',
			75
		);

		add_submenu_page(
			'site-checkup-pro',
			__( 'Check-up Dashboard', 'site-checkup-pro' ),
			__( 'Dashboard', 'site-checkup-pro' ),
			'manage_options',
			'site-checkup-pro',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'site-checkup-pro',
			__( 'Security Audit Log', 'site-checkup-pro' ),
			__( 'Audit Log', 'site-checkup-pro' ),
			'manage_options',
			'site-checkup-pro-audit',
			array( $this, 'render_audit_log' )
		);

		add_submenu_page(
			'site-checkup-pro',
			__( 'SOP Coverage Report', 'site-checkup-pro' ),
			__( 'Client Report', 'site-checkup-pro' ),
			'manage_options',
			'site-checkup-pro-report',
			array( $this, 'render_report' )
		);
	}

	/**
	 * Enqueue admin scripts and stylesheets on plugin pages.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'site-checkup-pro' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpsg-admin-css',
			WPSG_PLUGIN_URL . 'admin/assets/css/admin.css',
			array( 'dashicons' ),
			WPSG_VERSION
		);

		wp_enqueue_script(
			'wpsg-admin-js',
			WPSG_PLUGIN_URL . 'admin/assets/js/admin.js',
			array( 'wp-api-fetch' ),
			WPSG_VERSION,
			true
		);

		wp_enqueue_script(
			'wpsg-report-js',
			WPSG_PLUGIN_URL . 'admin/assets/js/report.js',
			array(),
			WPSG_VERSION,
			true
		);

		wp_localize_script( 'wpsg-admin-js', 'wpsgData', array(
			'restUrl'         => esc_url_raw( rest_url( 'site-checkup-pro/v1' ) ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'homeUrl'         => home_url(),
			'adminUrl'        => admin_url(),
			'serverType'      => WPSG_Htaccess_Manager::get_server_type(),
			'supportsHtaccess'=> WPSG_Htaccess_Manager::supports_htaccess(),
		) );
	}

	/**
	 * Render main checklist dashboard.
	 */
	public function render_dashboard() {
		include WPSG_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render audit log view.
	 */
	public function render_audit_log() {
		include WPSG_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render report view.
	 */
	public function render_report() {
		include WPSG_PLUGIN_DIR . 'admin/views/report.php';
	}
}
