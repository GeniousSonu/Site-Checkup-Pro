<?php
/**
* Admin Menu & Assets Enqueuer
 *
 * Registers the top-level Site Checkup dashboard menu and loads CSS/JS assets.
 *
 *
 * @package GeniousSonu_Site_Checkup
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_menu_icon_styles' ) );

		// Plugin action links and row meta on plugins.php.
		$basename = defined( 'WPSG_BASENAME' ) ? WPSG_BASENAME : 'genioussonu-site-checkup/genioussonu-site-checkup.php';
		add_filter( 'plugin_action_links_' . $basename, array( $this, 'add_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );

		// Clean, isolated workspace: Suppress external admin notices on GeniousSonu Site Checkup screens.
		add_action( 'in_admin_header', array( $this, 'suppress_foreign_admin_notices' ), 100 );
	}

	/**
	 * Suppress external WordPress admin notices on GeniousSonu Site Checkup screens
	 * to prevent layout disruption and maintain an uncluttered, industry-standard UI.
	 */
	public function suppress_foreign_admin_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( $screen->id, 'genioussonu-security-hardening-audit' ) ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Register admin menu and submenus.
	 */
	public function register_menu() {
		$icon_svg_path = WPSG_PLUGIN_DIR . 'media/menu-icon.svg';
		$icon = 'dashicons-shield';
		if ( file_exists( $icon_svg_path ) ) {
			$svg_content = file_get_contents( $icon_svg_path );
			if ( ! empty( $svg_content ) ) {
				$icon = 'data:image/svg+xml;base64,' . base64_encode( $svg_content );
			}
		}

		$hook = add_menu_page(
			__( 'GeniousSonu Site Checkup', 'genioussonu-security-hardening-audit' ),
			__( 'Site Checkup', 'genioussonu-security-hardening-audit' ),
			'manage_options',
			'genioussonu-security-hardening-audit',
			array( $this, 'render_dashboard' ),
			$icon,
			75
		);

		add_submenu_page(
			'genioussonu-security-hardening-audit',
			__( 'Check-up Dashboard', 'genioussonu-security-hardening-audit' ),
			__( 'Dashboard', 'genioussonu-security-hardening-audit' ),
			'manage_options',
			'genioussonu-security-hardening-audit',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'genioussonu-security-hardening-audit',
			__( 'Security Audit Log', 'genioussonu-security-hardening-audit' ),
			__( 'Audit Log', 'genioussonu-security-hardening-audit' ),
			'manage_options',
			'genioussonu-site-checkup-audit',
			array( $this, 'render_audit_log' )
		);

		add_submenu_page(
			'genioussonu-security-hardening-audit',
			__( 'SOP Coverage Report', 'genioussonu-security-hardening-audit' ),
			__( 'Client Report', 'genioussonu-security-hardening-audit' ),
			'manage_options',
			'genioussonu-site-checkup-report',
			array( $this, 'render_report' )
		);
	}

	/**
	 * Enqueue admin scripts and stylesheets on plugin pages.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Strict screen check: Only enqueue on GeniousSonu Site Checkup admin screens!
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( $screen->id, 'genioussonu-security-hardening-audit' ) ) {
			if ( false === strpos( $hook, 'genioussonu-security-hardening-audit' ) ) {
				return;
			}
		}

		$ver = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? time() : WPSG_VERSION;

		wp_enqueue_style(
			'wpsg-design-tokens',
			WPSG_PLUGIN_URL . 'admin/assets/css/design-tokens.css',
			array(),
			$ver
		);

		wp_enqueue_style(
			'wpsg-admin-css',
			WPSG_PLUGIN_URL . 'admin/assets/css/admin.css',
			array( 'dashicons', 'wpsg-design-tokens' ),
			$ver
		);

		wp_enqueue_script(
			'wpsg-admin-js',
			WPSG_PLUGIN_URL . 'admin/assets/js/admin.js',
			array( 'wp-api-fetch' ),
			$ver,
			true
		);

		wp_enqueue_script(
			'wpsg-report-js',
			WPSG_PLUGIN_URL . 'admin/assets/js/report.js',
			array(),
			$ver,
			true
		);

		$initial_catalog = null;
		if ( ! class_exists( 'WP_REST_Controller' ) ) {
			$rest_path = defined( 'ABSPATH' ) ? ABSPATH . ( defined( 'WPINC' ) ? WPINC : 'wp-includes' ) . '/rest-api/endpoints/class-wp-rest-controller.php' : '';
			if ( $rest_path && file_exists( $rest_path ) ) {
				require_once $rest_path;
			}
		}
		if ( class_exists( 'WPSG_Rest_Controller' ) ) {
			try {
				$initial_catalog = WPSG_Rest_Controller::get_instance()->get_tasks_catalog();
			} catch ( \Throwable $e ) {
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- This call is reachable only when WP_DEBUG is enabled.
					error_log( sprintf( '[GeniousSonu Site Checkup] Failed to generate initial tasks catalog: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );
				}
				$initial_catalog = null;
			}
		}

		wp_localize_script( 'wpsg-admin-js', 'wpsgData', array(
			'restUrl'          => esc_url_raw( rest_url( 'genioussonu-site-checkup/v1' ) ),
			'nonce'            => wp_create_nonce( 'wp_rest' ),
			'homeUrl'          => home_url(),
			'adminUrl'         => admin_url(),
			'mediaUrl'         => esc_url_raw( WPSG_PLUGIN_URL . 'media/' ),
			'serverType'       => WPSG_Htaccess_Manager::get_server_type(),
			'supportsHtaccess' => WPSG_Htaccess_Manager::supports_htaccess(),
			'environmentType'  => class_exists( 'WPSG_Environment_Badge' ) ? WPSG_Environment_Badge::get_environment_type() : 'production',
			'initialData'      => $initial_catalog,
			'nonces'           => array(
				'run_task'        => wp_create_nonce( 'wpsg_run_task' ),
				'undo_task'       => wp_create_nonce( 'wpsg_undo_task' ),
				'update_status'   => wp_create_nonce( 'wpsg_update_status' ),
				'confirm_backup'  => wp_create_nonce( 'wpsg_confirm_backup' ),
				'update_baseline' => wp_create_nonce( 'wpsg_update_baseline' ),
				'set_login_slug'  => wp_create_nonce( 'wpsg_set_login_slug' ),
				'restore_plugin'  => wp_create_nonce( 'wpsg_restore_plugin' ),
				'delete_plugin'   => wp_create_nonce( 'wpsg_delete_plugin' ),
				'reauth'          => wp_create_nonce( 'wpsg_reauth' ),
				'destroy_session' => wp_create_nonce( 'wpsg_destroy_session' ),
				'revoke_app_pass' => wp_create_nonce( 'wpsg_revoke_app_pass' ),
				'dismiss_notice'  => wp_create_nonce( 'wpsg_dismiss_notice' ),
				'integrity_scan'  => wp_create_nonce( 'wpsg_integrity_scan' ),
			),
		) );
	}

	/**
	 * Render main checklist dashboard.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'genioussonu-security-hardening-audit' ) );
		}
		try {
			include WPSG_PLUGIN_DIR . 'admin/views/dashboard.php';
		} catch ( \Throwable $e ) {
			/* translators: %s: error message */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'GeniousSonu Site Checkup encountered an unexpected error: %s', 'genioussonu-security-hardening-audit' ), $e->getMessage() ) ) . '</p></div>';
		}
	}

	/**
	 * Render audit log view.
	 */
	public function render_audit_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'genioussonu-security-hardening-audit' ) );
		}
		try {
			include WPSG_PLUGIN_DIR . 'admin/views/dashboard.php';
		} catch ( \Throwable $e ) {
			/* translators: %s: error message */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'GeniousSonu Site Checkup encountered an unexpected error: %s', 'genioussonu-security-hardening-audit' ), $e->getMessage() ) ) . '</p></div>';
		}
	}

	/**
	 * Render report view.
	 */
	public function render_report() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'genioussonu-security-hardening-audit' ) );
		}
		try {
			include WPSG_PLUGIN_DIR . 'admin/views/report.php';
		} catch ( \Throwable $e ) {
			/* translators: %s: error message */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'GeniousSonu Site Checkup encountered an unexpected error generating the report: %s', 'genioussonu-security-hardening-audit' ), $e->getMessage() ) ) . '</p></div>';
		}
	}

	/**
	 * Add custom action links (Settings) under the plugin name on plugins.php.
	 *
	 * @param array $actions Existing action links.
	 * @return array
	 */
	public function add_action_links( $actions ) {
		$settings_url = admin_url( 'admin.php?page=genioussonu-site-checkup' );
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'genioussonu-security-hardening-audit' )
		);
		array_unshift( $actions, $settings_link );
		return $actions;
	}

	/**
	 * Add row meta links below the plugin description on plugins.php.
	 * Appends Settings, Docs & FAQs, and Video Tutorials to:
	 * "Version 1.0.0 | By SK Sahinur Islam | Visit plugin site | Settings | Docs & FAQs | Video Tutorials"
	 *
	 * @param array  $meta Existing row meta items.
	 * @param string $file Plugin file basename.
	 * @return array
	 */
	public function add_row_meta( $meta, $file ) {
		$basename = defined( 'WPSG_BASENAME' ) ? WPSG_BASENAME : 'genioussonu-site-checkup/genioussonu-site-checkup.php';
		if ( $basename !== $file ) {
			return $meta;
		}

		$docs_url      = apply_filters( 'wpsg_docs_url', 'https://www.genioussonu.me/plugin/genioussonu-site-checkup/docs/' );
		$tutorials_url = apply_filters( 'wpsg_tutorials_url', 'https://www.genioussonu.me/plugin/genioussonu-site-checkup/tutorials/' );

		$links = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=genioussonu-site-checkup' ) ),
				esc_html__( 'Settings', 'genioussonu-security-hardening-audit' )
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $docs_url ),
				esc_html__( 'Docs & FAQs', 'genioussonu-security-hardening-audit' )
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $tutorials_url ),
				esc_html__( 'Video Tutorials', 'genioussonu-security-hardening-audit' )
			),
		);

		return array_merge( $meta, $links );
	}


	/**
	 * Output crisp menu icon styles across all admin pages.
	 * Ensures the custom brand mark is cleanly sized, centered, and smooth on hover.
	 */
	public function enqueue_admin_menu_icon_styles() {
		$handle = 'wpsg-admin-menu-icon-styles';
		wp_register_style( $handle, false, array(), WPSG_VERSION );
		wp_enqueue_style( $handle );
		wp_add_inline_style(
			$handle,
			'#adminmenu .toplevel_page_genioussonu-site-checkup .wp-menu-image { display: flex; align-items: center; justify-content: center; }' .
			'#adminmenu .toplevel_page_genioussonu-site-checkup .wp-menu-image img { width: 19px !important; height: 19px !important; padding: 0 !important; opacity: 0.88; transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.15s ease; }' .
			'#adminmenu .toplevel_page_genioussonu-site-checkup:hover .wp-menu-image img, #adminmenu .toplevel_page_genioussonu-site-checkup.wp-has-current-submenu .wp-menu-image img { opacity: 1; transform: scale(1.12); }'
		);
	}
}
