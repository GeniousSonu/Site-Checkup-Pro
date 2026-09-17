<?php
/**
 * Admin Notice Inbox & Focus Mode Declutter
 *
 * Buffers admin notices with strict wp_kses_post() sanitization, prevents stored XSS,
 * protects core update/security notices from dismissal, and declutters dashboard widgets.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Notice_Inbox
 */
class WPSG_Notice_Inbox {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Notice_Inbox|null
	 */
	private static $instance = null;

	/**
	 * Captured notices buffer.
	 *
	 * @var array
	 */
	private $captured_notices = array();

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Notice_Inbox
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
		// 1. Dashboard widget declutter at late priority 999
		add_action( 'wp_dashboard_setup', array( $this, 'declutter_dashboard_widgets' ), 999 );

		// 2. Buffer notices if Focus Mode is active
		if ( is_admin() && get_option( 'wpsg_focus_mode', false ) ) {
			add_action( 'admin_notices', array( $this, 'start_notice_buffer' ), -9999 );
			add_action( 'all_admin_notices', array( $this, 'start_notice_buffer' ), -9999 );
			add_action( 'admin_notices', array( $this, 'end_notice_buffer' ), 9999 );
			add_action( 'all_admin_notices', array( $this, 'end_notice_buffer' ), 9999 );
		}
	}

	/**
	 * Declutter standard WordPress dashboard widgets at late priority.
	 */
	public function declutter_dashboard_widgets() {
		if ( ! get_option( 'wpsg_declutter_dashboard', false ) ) {
			return;
		}

		// Cosmetic and promotional widgets
		remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );   // WordPress Events and News
		remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' ); // Quick Draft
		remove_action( 'welcome_panel', 'wp_welcome_panel' );            // Welcome Panel
	}

	/**
	 * Start output buffer for notices.
	 */
	public function start_notice_buffer() {
		ob_start();
	}

	/**
	 * End output buffer, sanitize HTML via wp_kses_post, and filter dismissed notices.
	 */
	public function end_notice_buffer() {
		$raw_html = ob_get_clean();
		if ( empty( trim( $raw_html ) ) ) {
			return;
		}

		// CRITICAL SECURITY FIX: Sanitize all captured notice HTML before storage or display!
		$safe_html = wp_kses_post( $raw_html );

		$dismissed = get_option( 'wpsg_dismissed_notices', array() );
		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		// Hard allowlist: Core updates and Site Checkup Pro alerts must NEVER be dismissed!
		$is_core_update = ( false !== strpos( $safe_html, 'update-nag' ) || false !== strpos( $safe_html, 'WordPress' ) && false !== strpos( $safe_html, 'update' ) );
		$is_site_checkup = ( false !== strpos( $safe_html, 'site-checkup-pro' ) || false !== strpos( $safe_html, 'wpsg-' ) );

		if ( $is_core_update || $is_site_checkup ) {
			// Always echo protected notices directly!
			echo $safe_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$notice_hash = hash( 'sha256', strip_tags( $safe_html ) );

		if ( in_array( $notice_hash, $dismissed, true ) ) {
			// Suppress dismissed notice.
			return;
		}

		// Otherwise, display the sanitized notice.
		echo $safe_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Dismiss a notice by its content hash.
	 *
	 * @param string $notice_hash Hash of the notice text.
	 * @return bool
	 */
	public static function dismiss_notice( $notice_hash ) {
		$dismissed = get_option( 'wpsg_dismissed_notices', array() );
		if ( ! is_array( $dismissed ) ) {
			$dismissed = array();
		}

		if ( ! in_array( $notice_hash, $dismissed, true ) ) {
			$dismissed[] = sanitize_text_field( $notice_hash );
			return update_option( 'wpsg_dismissed_notices', $dismissed );
		}

		return true;
	}

	/**
	 * Reset all dismissed notices.
	 *
	 * @return bool
	 */
	public static function reset_dismissed_notices() {
		return delete_option( 'wpsg_dismissed_notices' );
	}
}
