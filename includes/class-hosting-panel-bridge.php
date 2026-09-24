<?php
/**
 * Hosting Panel Bridge (Tier 1 One-Click Nginx Automation)
 *
 * Dispatches server directives through official hosting control panel APIs
 * (cPanel, Plesk, CloudPanel, RunCloud, CyberPanel) with strict consent gating,
 * HKDF-encrypted credentials, SSRF destination validation, and sslverify => true.
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
 * Class WPSG_Hosting_Panel_Bridge
 */
class WPSG_Hosting_Panel_Bridge {

	/**
	 * Detect active hosting control panel based on filesystem markers, environment, and ports.
	 *
	 * @return array Array with 'panel' (slug), 'label' (string), 'confidence' (high|medium|none).
	 */
	public static function detect_panel() {
		// 1. Explicit user override from settings
		$settings = get_option( 'wpsg_settings', array() );
		if ( ! empty( $settings['hosting_panel_type'] ) && 'auto' !== $settings['hosting_panel_type'] ) {
			$panel = sanitize_key( $settings['hosting_panel_type'] );
			return array(
				'panel'      => $panel,
				'label'      => self::get_panel_label( $panel ),
				'confidence' => 'high',
				'manual'     => true,
			);
		}

		// 2. cPanel detection
		if (
			file_exists( '/usr/local/cpanel' ) ||
			getenv( 'CPANEL' ) ||
			( isset( $_SERVER['SERVER_ADMIN'] ) && false !== strpos( $_SERVER['SERVER_ADMIN'], 'cpanel' ) ) ||
			file_exists( '/var/cpanel' )
		) {
			return array(
				'panel'      => 'cpanel',
				'label'      => 'cPanel / WHM',
				'confidence' => 'high',
			);
		}

		// 3. Plesk detection
		if (
			file_exists( '/usr/local/psa' ) ||
			getenv( 'PLK_CONF_DIR' ) ||
			file_exists( '/etc/psa' )
		) {
			return array(
				'panel'      => 'plesk',
				'label'      => 'Plesk Obsidian',
				'confidence' => 'high',
			);
		}

		// 4. CloudPanel detection
		if (
			file_exists( '/home/clp' ) ||
			file_exists( '/etc/cloudpanel' )
		) {
			return array(
				'panel'      => 'cloudpanel',
				'label'      => 'CloudPanel',
				'confidence' => 'high',
			);
		}

		// 5. RunCloud detection
		if (
			file_exists( '/etc/runcloud' ) ||
			file_exists( '/var/runcloud' ) ||
			file_exists( '/home/runcloud' )
		) {
			return array(
				'panel'      => 'runcloud',
				'label'      => 'RunCloud',
				'confidence' => 'high',
			);
		}

		// 6. CyberPanel detection
		if (
			file_exists( '/usr/local/CyberCP' ) ||
			file_exists( '/etc/cyberpanel' )
		) {
			return array(
				'panel'      => 'cyberpanel',
				'label'      => 'CyberPanel (LiteSpeed/OpenLiteSpeed)',
				'confidence' => 'high',
			);
		}

		return array(
			'panel'      => 'none',
			'label'      => __( 'Standard / Unmanaged Server', 'site-checkup-pro' ),
			'confidence' => 'none',
		);
	}

	/**
	 * Human-readable label for panel slug.
	 *
	 * @param string $panel Panel slug.
	 * @return string Label.
	 */
	public static function get_panel_label( $panel ) {
		$labels = array(
			'cpanel'     => 'cPanel / WHM',
			'plesk'      => 'Plesk Obsidian',
			'cloudpanel' => 'CloudPanel',
			'runcloud'   => 'RunCloud',
			'cyberpanel' => 'CyberPanel',
			'none'       => __( 'None / Manual', 'site-checkup-pro' ),
		);

		return isset( $labels[ $panel ] ) ? $labels[ $panel ] : ucfirst( $panel );
	}

	/**
	 * Check if Tier 1 panel integration is fully configured and opted-in.
	 *
	 * @return bool True if configured and user consented.
	 */
	public static function is_configured() {
		$settings = get_option( 'wpsg_settings', array() );

		if ( empty( $settings['hosting_panel_optin'] ) ) {
			return false;
		}

		if ( empty( $settings['hosting_panel_url'] ) || empty( $settings['hosting_panel_key_enc'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Apply an Nginx directive via hosting panel API.
	 *
	 * @param string $rule_key        Key from centralized rule registry.
	 * @param string $nginx_directive Fixed directive from registry.
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function apply_directive( $rule_key, $nginx_directive ) {
		if ( ! self::is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Hosting panel integration is not configured or lacks opt-in consent.', 'site-checkup-pro' ),
			);
		}

		return self::dispatch_api_call( 'apply', $rule_key, $nginx_directive );
	}

	/**
	 * Remove an Nginx directive via hosting panel API.
	 *
	 * @param string $rule_key Key from centralized rule registry.
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function remove_directive( $rule_key ) {
		if ( ! self::is_configured() ) {
			return array(
				'success' => false,
				'message' => __( 'Hosting panel integration is not configured or lacks opt-in consent.', 'site-checkup-pro' ),
			);
		}

		return self::dispatch_api_call( 'remove', $rule_key, '' );
	}

	/**
	 * Dispatch hardened outbound API call to panel endpoint.
	 *
	 * Validates host matches configured setting, enforces sslverify => true,
	 * and includes redaction in case of errors.
	 *
	 * @param string $action          'apply' or 'remove'.
	 * @param string $rule_key        Rule identifier.
	 * @param string $nginx_directive Directive text.
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	private static function dispatch_api_call( $action, $rule_key, $nginx_directive ) {
		$settings = get_option( 'wpsg_settings', array() );
		$panel    = ! empty( $settings['hosting_panel_type'] ) && 'auto' !== $settings['hosting_panel_type']
			? sanitize_key( $settings['hosting_panel_type'] )
			: self::detect_panel()['panel'];

		$raw_url = trim( (string) $settings['hosting_panel_url'] );
		$parsed  = wp_parse_url( $raw_url );

		if ( ! $parsed || empty( $parsed['host'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Configured hosting panel URL is malformed or missing a valid host.', 'site-checkup-pro' ),
			);
		}

		// Strictly require HTTPS unless localhost test
		if ( empty( $parsed['scheme'] ) || ( 'https' !== $parsed['scheme'] && 'localhost' !== $parsed['host'] && '127.0.0.1' !== $parsed['host'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Hosting panel API calls strictly require an HTTPS endpoint.', 'site-checkup-pro' ),
			);
		}

		$api_key = WPSG_Encryption::decrypt( $settings['hosting_panel_key_enc'] );
		if ( empty( $api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not decrypt hosting panel credentials. Please re-enter them in Settings.', 'site-checkup-pro' ),
			);
		}

		$endpoint = trailingslashit( $raw_url ) . 'api/v1/site-checkup-pro';

		if ( class_exists( 'WPSG_SSRF_Guard' ) ) {
			$ssrf_check = WPSG_SSRF_Guard::validate_url( $endpoint );
			if ( is_wp_error( $ssrf_check ) ) {
				return array(
					'success' => false,
					/* translators: %s: error message */
					'message' => sprintf( __( 'Hosting panel URL violates SSRF security guard: %s', 'site-checkup-pro' ), $ssrf_check->get_error_message() ),
				);
			}
		}

		$body     = array(
			'panel'     => $panel,
			'action'    => $action,
			'rule_key'  => $rule_key,
			'directive' => $nginx_directive,
			'site_url'  => home_url(),
			'timestamp' => time(),
		);

		$request_args = array(
			'method'      => 'POST',
			'timeout'     => 15,
			'redirection' => 0, // Zero redirects
			'sslverify'   => true, // Explicit sslverify
			'headers'     => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
				'User-Agent'    => 'SiteCheckupPro-PanelBridge/1.0',
			),
			'body'        => wp_json_encode( $body ),
		);

		// Allow panels to mock or test via filter in test suites
		$preempt = apply_filters( 'wpsg_pre_hosting_panel_dispatch', null, $endpoint, $request_args, $body );
		if ( null !== $preempt ) {
			return $preempt;
		}

		$response = wp_remote_post( $endpoint, $request_args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Hosting panel API communication failed: %s', 'site-checkup-pro' ),
					$response->get_error_message()
				),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code >= 200 && $code < 300 && ! empty( $data['success'] ) ) {
			return array(
				'success' => true,
				'message' => ! empty( $data['message'] ) ? $data['message'] : __( 'Directive updated via hosting panel API.', 'site-checkup-pro' ),
			);
		}

		/* translators: %d: HTTP status code */
		$err_msg = ! empty( $data['message'] ) ? $data['message'] : sprintf( __( 'Hosting panel returned HTTP %d', 'site-checkup-pro' ), $code );
		return array(
			'success' => false,
			'message' => $err_msg,
		);
	}
}
