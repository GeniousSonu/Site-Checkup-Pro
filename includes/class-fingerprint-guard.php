<?php
/**
* Fingerprint & Version Hiding Guard
 *
 * Removes WordPress generator meta tags, provides opt-in script/style version stripping,
 * and manages Content-Security-Policy Report-Only headers and violation captures.
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
 * Class WPSG_Fingerprint_Guard
 */
class WPSG_Fingerprint_Guard {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Fingerprint_Guard|null
	 */
	private static $instance = null;

	/**
	 * Transient key for CSP reports.
	 */
	const CSP_TRANSIENT = 'wpsg_csp_violations';

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Fingerprint_Guard
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
		// 1. Remove WordPress generator version strings.
		if ( get_option( 'wpsg_hide_generator', false ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		// 2. Opt-in: Strip ?ver= from enqueued scripts and stylesheets.
		if ( get_option( 'wpsg_strip_ver', false ) ) {
			add_filter( 'style_loader_src', array( $this, 'strip_version_query_arg' ), 9999 );
			add_filter( 'script_loader_src', array( $this, 'strip_version_query_arg' ), 9999 );
		}

		// 3. CSP Header Dispatch.
		$csp_mode = get_option( 'wpsg_csp_mode', '' );
		if ( in_array( $csp_mode, array( 'report_only', 'enforce' ), true ) ) {
			add_action( 'send_headers', array( $this, 'send_csp_headers' ) );
		}
	}

	/**
	 * Strip ?ver= query arguments from script/style assets.
	 *
	 * @param string $src Asset URL.
	 * @return string Cleaned asset URL.
	 */
	public function strip_version_query_arg( $src ) {
		if ( false !== strpos( $src, 'ver=' ) ) {
			$src = remove_query_arg( 'ver', $src );
		}
		return $src;
	}

	/**
	 * Emit Content-Security-Policy HTTP headers (Report-Only by default).
	 */
	public function send_csp_headers() {
		if ( headers_sent() ) {
			return;
		}

		$csp_mode = get_option( 'wpsg_csp_mode', 'report_only' );
		$report_url = rest_url( 'site-checkup-pro/v1/csp-report' );

		$policy = "default-src 'self'; " .
			"script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; " .
			"style-src 'self' 'unsafe-inline' https:; " .
			"img-src 'self' data: https:; " .
			"font-src 'self' data: https:; " .
			"connect-src 'self' https:; " .
			"report-uri " . esc_url_raw( $report_url );

		if ( 'report_only' === $csp_mode ) {
			header( 'Content-Security-Policy-Report-Only: ' . $policy );
		} elseif ( 'enforce' === $csp_mode ) {
			header( 'Content-Security-Policy: ' . $policy );
		}
	}

	/**
	 * Record a parsed CSP violation report safely in transient memory (FIFO max 50 items).
	 *
	 * @param array $violation Raw violation payload.
	 * @return bool
	 */
	public static function record_csp_violation( array $violation ) {
		$reports = get_transient( self::CSP_TRANSIENT );
		if ( ! is_array( $reports ) ) {
			$reports = array();
		}

		$clean_entry = array(
			'timestamp'          => current_time( 'mysql' ),
			'document_uri'       => ! empty( $violation['document-uri'] ) ? esc_url_raw( $violation['document-uri'] ) : '',
			'blocked_uri'        => ! empty( $violation['blocked-uri'] ) ? esc_url_raw( $violation['blocked-uri'] ) : '',
			'violated_directive' => ! empty( $violation['violated-directive'] ) ? sanitize_text_field( $violation['violated-directive'] ) : '',
			'original_policy'    => ! empty( $violation['original-policy'] ) ? sanitize_text_field( substr( $violation['original-policy'], 0, 300 ) ) : '',
		);

		// Prepend and cap to 50 reports
		array_unshift( $reports, $clean_entry );
		if ( count( $reports ) > 50 ) {
			$reports = array_slice( $reports, 0, 50 );
		}

		return set_transient( self::CSP_TRANSIENT, $reports, 86400 * 7 );
	}

	/**
	 * Get recorded CSP violation reports.
	 *
	 * @return array
	 */
	public static function get_csp_violations() {
		$reports = get_transient( self::CSP_TRANSIENT );
		return is_array( $reports ) ? $reports : array();
	}
}
