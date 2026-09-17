<?php
/**
* Admin Menu & Assets Enqueuer
 *
 * Registers the top-level Site Checkup dashboard menu and loads CSS/JS assets.
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
		add_action( 'admin_head', array( $this, 'enqueue_admin_menu_icon_styles' ) );

		// Plugin action links and row meta on plugins.php.
		$basename = defined( 'WPSG_BASENAME' ) ? WPSG_BASENAME : 'site-checkup-pro/site-checkup-pro.php';
		add_filter( 'plugin_action_links_' . $basename, array( $this, 'add_action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );

		// Auto-updates column fallback rendering on plugins.php.
		add_filter( 'plugin_auto_update_setting_html', array( $this, 'filter_auto_update_setting_html' ), 10, 3 );
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
			__( 'Site Checkup Pro', 'site-checkup-pro' ),
			__( 'Site Checkup', 'site-checkup-pro' ),
			'manage_options',
			'site-checkup-pro',
			array( $this, 'render_dashboard' ),
			$icon,
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
		// Strict screen check: Only enqueue on Site Checkup Pro admin screens!
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( $screen->id, 'site-checkup-pro' ) ) {
			if ( false === strpos( $hook, 'site-checkup-pro' ) ) {
				return;
			}
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
			'restUrl'          => esc_url_raw( rest_url( 'site-checkup-pro/v1' ) ),
			'nonce'            => wp_create_nonce( 'wp_rest' ),
			'homeUrl'          => home_url(),
			'adminUrl'         => admin_url(),
			'mediaUrl'         => esc_url_raw( WPSG_PLUGIN_URL . 'media/' ),
			'serverType'       => WPSG_Htaccess_Manager::get_server_type(),
			'supportsHtaccess' => WPSG_Htaccess_Manager::supports_htaccess(),
			'nonces'           => array(
				'run_task'        => wp_create_nonce( 'wpsg_run_task' ),
				'undo_task'       => wp_create_nonce( 'wpsg_undo_task' ),
				'update_status'   => wp_create_nonce( 'wpsg_update_status' ),
				'confirm_backup'  => wp_create_nonce( 'wpsg_confirm_backup' ),
				'update_baseline' => wp_create_nonce( 'wpsg_update_baseline' ),
				'set_login_slug'  => wp_create_nonce( 'wpsg_set_login_slug' ),
				'restore_plugin'  => wp_create_nonce( 'wpsg_restore_plugin' ),
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
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'site-checkup-pro' ) );
		}
		try {
			include WPSG_PLUGIN_DIR . 'admin/views/dashboard.php';
		} catch ( \Throwable $e ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Site Checkup Pro encountered an unexpected error: %s', 'site-checkup-pro' ), $e->getMessage() ) ) . '</p></div>';
		}
	}

	/**
	 * Render audit log view.
	 */
	public function render_audit_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'site-checkup-pro' ) );
		}
		try {
			include WPSG_PLUGIN_DIR . 'admin/views/dashboard.php';
		} catch ( \Throwable $e ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Site Checkup Pro encountered an unexpected error: %s', 'site-checkup-pro' ), $e->getMessage() ) ) . '</p></div>';
		}
	}

	/**
	 * Render report view.
	 */
	public function render_report() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'site-checkup-pro' ) );
		}
		try {
			include WPSG_PLUGIN_DIR . 'admin/views/report.php';
		} catch ( \Throwable $e ) {
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Site Checkup Pro encountered an unexpected error generating the report: %s', 'site-checkup-pro' ), $e->getMessage() ) ) . '</p></div>';
		}
	}

	/**
	 * Add custom action links (Settings) under the plugin name on plugins.php.
	 *
	 * @param array $actions Existing action links.
	 * @return array
	 */
	public function add_action_links( $actions ) {
		$settings_url = admin_url( 'admin.php?page=site-checkup-pro' );
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( $settings_url ),
			esc_html__( 'Settings', 'site-checkup-pro' )
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
		$basename = defined( 'WPSG_BASENAME' ) ? WPSG_BASENAME : 'site-checkup-pro/site-checkup-pro.php';
		if ( $basename !== $file ) {
			return $meta;
		}

		$docs_url      = apply_filters( 'wpsg_docs_url', 'https://www.genioussonu.me/plugin/site-checkup-pro/docs/' );
		$tutorials_url = apply_filters( 'wpsg_tutorials_url', 'https://www.genioussonu.me/plugin/site-checkup-pro/tutorials/' );

		$links = array(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=site-checkup-pro' ) ),
				esc_html__( 'Settings', 'site-checkup-pro' )
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $docs_url ),
				esc_html__( 'Docs & FAQs', 'site-checkup-pro' )
			),
			sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
				esc_url( $tutorials_url ),
				esc_html__( 'Video Tutorials', 'site-checkup-pro' )
			),
		);

		return array_merge( $meta, $links );
	}

	/**
	 * Filters the HTML of the auto-updates column for Site Checkup Pro.
	 * Ensures the Enable / Disable auto-updates link is always displayed and functional.
	 *
	 * @param string $html        The HTML of the auto-update column content.
	 * @param string $plugin_file Path to the plugin file relative to the plugins directory.
	 * @param array  $plugin_data Array of plugin data.
	 * @return string
	 */
	public function filter_auto_update_setting_html( $html, $plugin_file, $plugin_data ) {
		$basename = defined( 'WPSG_BASENAME' ) ? WPSG_BASENAME : 'site-checkup-pro/site-checkup-pro.php';
		if ( $basename !== $plugin_file ) {
			return $html;
		}

		// If core already produced a valid toggle link, preserve it.
		if ( ! empty( $html ) && false !== strpos( $html, 'toggle-auto-update' ) ) {
			return $html;
		}

		$auto_updates = (array) get_site_option( 'auto_update_plugins', array() );
		$enabled      = in_array( $plugin_file, $auto_updates, true );
		$action       = $enabled ? 'disable' : 'enable';
		$text         = $enabled ? __( 'Disable auto-updates', 'site-checkup-pro' ) : __( 'Enable auto-updates', 'site-checkup-pro' );

		$query_args = array(
			'action'        => "{$action}-auto-update",
			'plugin'        => $plugin_file,
			'paged'         => isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1,
			'plugin_status' => isset( $_REQUEST['plugin_status'] ) ? sanitize_key( $_REQUEST['plugin_status'] ) : 'all',
		);

		$url = wp_nonce_url( add_query_arg( $query_args, 'plugins.php' ), 'updates' );

		return sprintf(
			'<a href="%s" class="toggle-auto-update aria-button-if-js" data-wp-action="%s"><span class="dashicons dashicons-update spin hidden" aria-hidden="true"></span><span class="label">%s</span></a>',
			esc_url( $url ),
			esc_attr( $action ),
			esc_html( $text )
		);
	}

	/**
	 * Output crisp menu icon styles across all admin pages.
	 * Ensures the custom brand mark is cleanly sized, centered, and smooth on hover.
	 */
	public function enqueue_admin_menu_icon_styles() {
		?>
		<style id="wpsg-menu-icon-styles">
			#adminmenu .toplevel_page_site-checkup-pro .wp-menu-image {
				display: flex;
				align-items: center;
				justify-content: center;
			}
			#adminmenu .toplevel_page_site-checkup-pro .wp-menu-image img {
				width: 19px !important;
				height: 19px !important;
				padding: 0 !important;
				opacity: 0.88;
				transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.15s ease;
			}
			#adminmenu .toplevel_page_site-checkup-pro:hover .wp-menu-image img,
			#adminmenu .toplevel_page_site-checkup-pro.wp-has-current-submenu .wp-menu-image img {
				opacity: 1;
				transform: scale(1.12);
			}
		</style>
		<?php
	}
}
