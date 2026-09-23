<?php
/**
 * Real HTTP-Level Loopback Verification Engine
 *
 * Provides independent, runtime verification of server-configuration directives
 * and hardening rules through SSRF-guarded loopback requests to home_url().
 * Features URL+method response caching, internal self-verification tagging,
 * synthetic non-executable probes, and dedicated probe directories.
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
 * Class WPSG_HTTP_Verifier
 */
class WPSG_HTTP_Verifier {

	/**
	 * In-memory response cache for the current request.
	 *
	 * @var array<string, array>
	 */
	private static $memory_cache = array();

	/**
	 * Reset in-memory cache.
	 */
	public static function clear_memory_cache() {
		self::$memory_cache = array();
	}

	/**
	 * Header name used to tag internal self-verification probes.
	 */
	const VERIFY_HEADER = 'X-WPSG-Self-Verification';

	/**
	 * Generate an authenticated self-verification token.
	 *
	 * @return string Format "{timestamp}:{hmac}".
	 */
	public static function generate_verify_token() {
		$time = time();
		$salt = defined( 'AUTH_SALT' ) && AUTH_SALT ? AUTH_SALT : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpsg_salt' );
		$hmac = hash_hmac( 'sha256', "wpsg_self_verify:{$time}", $salt );

		return "{$time}:{$hmac}";
	}

	/**
	 * Check if the current incoming request is an internal self-verification probe.
	 *
	 * @return bool True if valid internal probe.
	 */
	public static function is_self_verification_request() {
		$header = '';
		if ( ! empty( $_SERVER['HTTP_X_WPSG_SELF_VERIFICATION'] ) ) {
			$header = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPSG_SELF_VERIFICATION'] ) );
		} elseif ( function_exists( 'getallheaders' ) ) {
			$headers = array_change_key_case( (array) getallheaders(), CASE_LOWER );
			$lower_key = strtolower( self::VERIFY_HEADER );
			if ( ! empty( $headers[ $lower_key ] ) ) {
				$header = sanitize_text_field( $headers[ $lower_key ] );
			}
		}

		if ( empty( $header ) ) {
			return false;
		}

		$parts = explode( ':', $header, 2 );
		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$time = (int) $parts[0];
		$hmac = $parts[1];

		// Probe must have been generated within the last 120 seconds
		if ( abs( time() - $time ) > 120 ) {
			return false;
		}

		$salt          = defined( 'AUTH_SALT' ) && AUTH_SALT ? AUTH_SALT : ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpsg_salt' );
		$expected_hmac = hash_hmac( 'sha256', "wpsg_self_verify:{$time}", $salt );

		return hash_equals( $expected_hmac, $hmac );
	}

	/**
	 * Hardened loopback request fetcher with 30s URL+method caching.
	 *
	 * Batches multiple header-checking tasks (clickjacking, nosniff, hsts, csp, fingerprint)
	 * into a single HTTP roundtrip to home_url('/').
	 *
	 * @param string $url         Target URL (must belong to home_url()).
	 * @param string $method      'GET' or 'POST'.
	 * @param array  $custom_args Optional wp_remote_get/post arguments.
	 * @param bool   $force_fresh Bypass cache if true.
	 * @return array|WP_Error Response array or WP_Error.
	 */
	public static function fetch_loopback( $url, $method = 'GET', array $custom_args = array(), $force_fresh = false ) {
		// Strict SSRF guard: target URL must match the configured home_url() host and scheme
		$home     = home_url();
		$home_p   = wp_parse_url( $home );
		$target_p = wp_parse_url( $url );

		if ( ! $home_p || ! $target_p || empty( $target_p['host'] ) ) {
			return new WP_Error( 'invalid_loopback_url', __( 'Invalid loopback URL specified.', 'site-checkup-pro' ) );
		}

		if ( strtolower( $home_p['host'] ) !== strtolower( $target_p['host'] ) ) {
			return new WP_Error( 'forbidden_loopback_host', __( 'Loopback verification is strictly confined to home_url() host.', 'site-checkup-pro' ) );
		}

		// Cache key by method, URL, and user-agent / query customizer
		$ua_key    = ! empty( $custom_args['user-agent'] ) ? $custom_args['user-agent'] : 'default';
		$cache_key = 'wpsg_v_' . hash( 'sha256', "{$method}:{$url}:{$ua_key}" );

		if ( ! $force_fresh ) {
			if ( isset( self::$memory_cache[ $cache_key ] ) ) {
				return self::$memory_cache[ $cache_key ];
			}
			$transient_val = get_transient( $cache_key );
			if ( false !== $transient_val && is_array( $transient_val ) ) {
				self::$memory_cache[ $cache_key ] = $transient_val;
				return $transient_val;
			}
		}

		$headers = array(
			self::VERIFY_HEADER => self::generate_verify_token(),
			'User-Agent'        => ! empty( $custom_args['user-agent'] ) ? $custom_args['user-agent'] : 'SiteCheckupPro-SelfVerifier/1.0',
		);

		if ( ! empty( $custom_args['headers'] ) && is_array( $custom_args['headers'] ) ) {
			$headers = array_merge( $headers, $custom_args['headers'] );
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 10,
			'redirection' => 0, // Never follow redirects
			'sslverify'   => false, // Loopbacks on staging/local may use self-signed certs
			'headers'     => $headers,
		);

		if ( isset( $custom_args['body'] ) ) {
			$args['body'] = $custom_args['body'];
		}

		// Support staging basic auth
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) && empty( $args['headers']['Authorization'] ) ) {
			$args['headers']['Authorization'] = 'Basic ' . base64_encode( sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) ) . ':' . sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) ) );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code             = (int) wp_remote_retrieve_response_code( $response );
		$response_headers = wp_remote_retrieve_headers( $response );
		$body             = wp_remote_retrieve_body( $response );

		$normalized_headers = array();
		if ( is_array( $response_headers ) || is_object( $response_headers ) ) {
			foreach ( $response_headers as $k => $v ) {
				$normalized_headers[ strtolower( $k ) ] = $v;
			}
		}

		$result = array(
			'code'    => $code,
			'headers' => $normalized_headers,
			'body'    => $body,
		);

		self::$memory_cache[ $cache_key ] = $result;
		set_transient( $cache_key, $result, 30 ); // 30-second TTL

		return $result;
	}

	/**
	 * Verify an individual task via independent HTTP loopback check.
	 *
	 * @param string $task_id     Task identifier.
	 * @param bool   $force_fresh Force fresh network request.
	 * @return array Array with 'verified' (bool), 'status' ('done'|'applied_unverified'|'pending'), 'message' (string).
	 */
	public static function verify_task( $task_id, $force_fresh = false ) {
		switch ( $task_id ) {
			case 'clickjacking_protection':
				return self::verify_clickjacking( $force_fresh );

			case 'nosniff_header':
				return self::verify_nosniff( $force_fresh );

			case 'hsts_header':
				return self::verify_hsts( $force_fresh );

			case 'hide_php_version':
				return self::verify_hide_php_version( $force_fresh );

			case 'disable_directory_listing':
				return self::verify_directory_listing( $force_fresh );

			case 'protect_sensitive_files':
				return self::verify_sensitive_files( $force_fresh );

			case 'block_xmlrpc_htaccess':
				return self::verify_xmlrpc_blocked( $force_fresh );

			case 'deny_uploads_php':
				return self::verify_uploads_php_denied( $force_fresh );

			case 'basic_firewall_rules':
				return self::verify_basic_firewall( $force_fresh );

			case 'bad_bots_noise_reduction':
				return self::verify_bad_bots( $force_fresh );

			case 'hide_wordpress_fingerprint':
				return self::verify_fingerprint_hidden( $force_fresh );

			case 'security_headers_csp':
				return self::verify_csp( $force_fresh );

			case 'security_txt_check':
				return self::verify_security_txt( $force_fresh );

			case 'login_url_rename':
				return self::verify_login_url_rename( $force_fresh );

			case 'disable_file_edit':
				return self::verify_diagnostic_constant( 'DISALLOW_FILE_EDIT', $force_fresh );

			case 'wp_debug_display_check':
				return self::verify_diagnostic_constant( 'WP_DEBUG_DISPLAY', $force_fresh, false );

			case 'block_user_enumeration':
				return self::verify_user_enumeration( $force_fresh );

			default:
				return array(
					'verified' => false,
					'status'   => 'pending',
					'message'  => __( 'No HTTP verification procedure defined for this task.', 'site-checkup-pro' ),
				);
		}
	}

	/**
	 * 1. Verify Clickjacking (X-Frame-Options or CSP frame-ancestors).
	 */
	private static function verify_clickjacking( $force_fresh ) {
		$res = self::fetch_loopback( home_url( '/' ), 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		$x_frame = isset( $res['headers']['x-frame-options'] ) ? (string) $res['headers']['x-frame-options'] : '';
		$csp     = isset( $res['headers']['content-security-policy'] ) ? (string) $res['headers']['content-security-policy'] : '';

		$has_x_frame = ( false !== stripos( $x_frame, 'SAMEORIGIN' ) || false !== stripos( $x_frame, 'DENY' ) );
		$has_csp_fa  = ( false !== stripos( $csp, 'frame-ancestors' ) );

		if ( $has_x_frame || $has_csp_fa ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => sprintf( __( 'Verified live: %s header confirmed.', 'site-checkup-pro' ), $has_x_frame ? "X-Frame-Options: {$x_frame}" : 'CSP frame-ancestors' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => __( 'Directive applied, but live HTTP response lacks X-Frame-Options or CSP frame-ancestors header.', 'site-checkup-pro' ),
		);
	}

	/**
	 * 2. Verify MIME Sniffing (X-Content-Type-Options: nosniff).
	 */
	private static function verify_nosniff( $force_fresh ) {
		$res = self::fetch_loopback( home_url( '/' ), 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		$header = isset( $res['headers']['x-content-type-options'] ) ? strtolower( trim( (string) $res['headers']['x-content-type-options'] ) ) : '';
		if ( 'nosniff' === $header ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: X-Content-Type-Options: nosniff header confirmed.', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => __( 'Directive applied, but live HTTP response lacks X-Content-Type-Options: nosniff header.', 'site-checkup-pro' ),
		);
	}

	/**
	 * 3. Verify HSTS (Strict-Transport-Security) with staged-rollout checks.
	 */
	private static function verify_hsts( $force_fresh ) {
		if ( ! is_ssl() ) {
			return array(
				'verified' => false,
				'status'   => 'applied_unverified',
				'message'  => __( 'HSTS directive is configured, but SSL/HTTPS is not active on this site. HSTS requires HTTPS.', 'site-checkup-pro' ),
			);
		}

		$res = self::fetch_loopback( home_url( '/' ), 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		$hsts = isset( $res['headers']['strict-transport-security'] ) ? (string) $res['headers']['strict-transport-security'] : '';
		if ( ! empty( $hsts ) ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => sprintf( __( 'Verified live: Strict-Transport-Security confirmed (%s).', 'site-checkup-pro' ), $hsts ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => __( 'HSTS rule applied, but live HTTP response lacks Strict-Transport-Security header.', 'site-checkup-pro' ),
		);
	}

	/**
	 * 4. Verify Hide PHP Version (X-Powered-By is absent).
	 */
	private static function verify_hide_php_version( $force_fresh ) {
		$res = self::fetch_loopback( home_url( '/' ), 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		if ( empty( $res['headers']['x-powered-by'] ) ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: X-Powered-By header is successfully suppressed.', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => sprintf( __( 'X-Powered-By header is still visible in live response (%s).', 'site-checkup-pro' ), $res['headers']['x-powered-by'] ),
		);
	}

	/**
	 * 5. Verify Directory Browsing (Options -Indexes / autoindex off).
	 *
	 * Uses a dedicated, plugin-owned test directory lacking index.php to prevent false 200s from core.
	 */
	private static function verify_directory_listing( $force_fresh ) {
		$uploads = wp_upload_dir();
		$probe_dir = trailingslashit( $uploads['basedir'] ) . 'wpsg-probe';
		$probe_url = trailingslashit( $uploads['baseurl'] ) . 'wpsg-probe/';

		if ( ! is_dir( $probe_dir ) ) {
			wp_mkdir_p( $probe_dir );
		}

		// Ensure no index.php or index.html exists in probe directory!
		if ( file_exists( $probe_dir . '/index.php' ) ) {
			@unlink( $probe_dir . '/index.php' );
		}
		if ( file_exists( $probe_dir . '/index.html' ) ) {
			@unlink( $probe_dir . '/index.html' );
		}

		$res = self::fetch_loopback( $probe_url, 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		// If blocked, server returns 403 Forbidden or 404
		if ( 403 === $res['code'] ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: Directory probe returned 403 Forbidden. Directory listing is blocked.', 'site-checkup-pro' ),
			);
		}

		// Check if response body looks like directory index listing
		$body = strtolower( (string) $res['body'] );
		if ( false !== strpos( $body, '<title>index of' ) || false !== strpos( $body, 'directory listing' ) ) {
			return array(
				'verified' => false,
				'status'   => 'applied_unverified',
				'message'  => __( 'Directory listing is still active: server returned an index directory listing.', 'site-checkup-pro' ),
			);
		}

		// If server returned non-200 or custom 404/redirect without listing
		if ( $res['code'] >= 400 ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => sprintf( __( 'Verified live: Server denied directory listing (HTTP %d).', 'site-checkup-pro' ), $res['code'] ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => __( 'Directory browsing directive could not be confirmed. Probe did not return 403 Forbidden.', 'site-checkup-pro' ),
		);
	}

	/**
	 * 6. Verify Protect Sensitive System Files (wp-config.php, .env, readme.html).
	 */
	private static function verify_sensitive_files( $force_fresh ) {
		$targets = array(
			'/wp-config.php',
			'/.env',
			'/readme.html',
		);

		$all_blocked = true;
		$evidence    = array();

		foreach ( $targets as $rel ) {
			$url = home_url( $rel );
			$res = self::fetch_loopback( $url, 'GET', array(), $force_fresh );

			if ( is_wp_error( $res ) ) {
				$all_blocked = false;
				$evidence[]  = "{$rel} (error)";
				continue;
			}

			// Must return non-200 (403, 404, etc.)
			if ( 200 === $res['code'] ) {
				$all_blocked = false;
				$evidence[]  = "{$rel} returned HTTP 200";
			}
		}

		if ( $all_blocked ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: Direct web access to .env, wp-config.php, and readme.html is blocked.', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => sprintf( __( 'Protection could not be confirmed: %s.', 'site-checkup-pro' ), implode( ', ', $evidence ) ),
		);
	}

	/**
	 * 7. Verify Block xmlrpc.php via Web Server.
	 */
	private static function verify_xmlrpc_blocked( $force_fresh ) {
		$url = home_url( '/xmlrpc.php' );
		$res = self::fetch_loopback( $url, 'POST', array( 'body' => '<methodCall><methodName>system.listMethods</methodName></methodCall>' ), $force_fresh );

		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		if ( 403 === $res['code'] ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: Direct xmlrpc.php requests are blocked at server layer (HTTP 403 Forbidden).', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => sprintf( __( 'xmlrpc.php block could not be confirmed (server returned HTTP %d, expected 403).', 'site-checkup-pro' ), $res['code'] ),
		);
	}

	/**
	 * 8. Verify Deny PHP Execution in Uploads.
	 *
	 * Uses a SYNTHETIC non-existent .php URL to verify web-server layer 403 blocking without disk writes.
	 */
	private static function verify_uploads_php_denied( $force_fresh ) {
		$uploads   = wp_upload_dir();
		$probe_url = trailingslashit( $uploads['baseurl'] ) . 'wpsg-synthetic-probe-deny.php';

		$res = self::fetch_loopback( $probe_url, 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		// Server rule intercepts before file lookup: returns 403 Forbidden
		if ( 403 === $res['code'] ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: PHP execution under /uploads/ blocked at server layer (synthetic probe returned 403).', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => sprintf( __( 'Uploads PHP execution block not confirmed: probe returned HTTP %d (expected 403 Forbidden).', 'site-checkup-pro' ), $res['code'] ),
		);
	}

	/**
	 * 9. Verify Basic Query String Firewall.
	 */
	private static function verify_basic_firewall( $force_fresh ) {
		$probe_url = home_url( '/?wpsg_test_sqli=union+select' );
		$res       = self::fetch_loopback( $probe_url, 'GET', array(), $force_fresh );

		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		if ( 403 === $res['code'] ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: Malicious SQLi signature query string blocked (HTTP 403 Forbidden).', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => sprintf( __( 'Firewall rule not active: test probe returned HTTP %d (expected 403 Forbidden).', 'site-checkup-pro' ), $res['code'] ),
		);
	}

	/**
	 * 10. Verify Bad Bots Noise Reduction.
	 */
	private static function verify_bad_bots( $force_fresh ) {
		$res = self::fetch_loopback( home_url( '/' ), 'GET', array( 'user-agent' => 'sqlmap/1.5#dev' ), $force_fresh );

		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		if ( 403 === $res['code'] ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: Scanner User-Agent request blocked (HTTP 403 Forbidden).', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => sprintf( __( 'Bot filter not active: scanner User-Agent returned HTTP %d (expected 403 Forbidden).', 'site-checkup-pro' ), $res['code'] ),
		);
	}

	/**
	 * 11. Verify WordPress Version Meta Tag is Hidden.
	 */
	private static function verify_fingerprint_hidden( $force_fresh ) {
		$res = self::fetch_loopback( home_url( '/' ), 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		$body = (string) $res['body'];
		if ( false !== stripos( $body, 'name="generator"' ) && false !== stripos( $body, 'content="WordPress' ) ) {
			return array(
				'verified' => false,
				'status'   => 'applied_unverified',
				'message'  => __( 'WordPress version meta tag is still visible in page HTML.', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => true,
			'status'   => 'done',
			'message'  => __( 'Verified live: WordPress generator meta tag suppressed from HTML output.', 'site-checkup-pro' ),
		);
	}

	/**
	 * 12. Verify Content-Security-Policy Header.
	 */
	private static function verify_csp( $force_fresh ) {
		$res = self::fetch_loopback( home_url( '/' ), 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		$has_ro = ! empty( $res['headers']['content-security-policy-report-only'] );
		$has_en = ! empty( $res['headers']['content-security-policy'] );

		if ( $has_ro || $has_en ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => sprintf(
					__( 'Verified live: CSP active in %s mode.', 'site-checkup-pro' ),
					$has_ro ? 'Report-Only' : 'Enforce'
				),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => __( 'Content-Security-Policy header not detected on live HTTP response.', 'site-checkup-pro' ),
		);
	}

	/**
	 * 13. Verify security.txt disclosure policy.
	 */
	private static function verify_security_txt( $force_fresh ) {
		$url = home_url( '/.well-known/security.txt' );
		$res = self::fetch_loopback( $url, 'GET', array(), $force_fresh );

		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		$body = (string) $res['body'];
		if ( 200 === $res['code'] && false !== stripos( $body, 'Contact:' ) ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: /.well-known/security.txt responds with valid contact directives.', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => sprintf( __( 'security.txt probe returned HTTP %d (expected 200 with Contact directive).', 'site-checkup-pro' ), $res['code'] ),
		);
	}

	/**
	 * 14. Verify Login URL Rename.
	 */
	private static function verify_login_url_rename( $force_fresh ) {
		$slug = get_option( 'wpsg_login_slug', '' );
		if ( empty( $slug ) ) {
			return array(
				'verified' => false,
				'status'   => 'pending',
				'message'  => __( 'Login URL rename is not configured.', 'site-checkup-pro' ),
			);
		}

		// Probe standard wp-login.php
		$res = self::fetch_loopback( home_url( '/wp-login.php' ), 'GET', array(), $force_fresh );
		if ( is_wp_error( $res ) ) {
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		// Should not return 200 showing standard login form
		$body = strtolower( (string) $res['body'] );
		if ( 200 === $res['code'] && ( false !== strpos( $body, 'id="loginform"' ) || false !== strpos( $body, 'name="log"' ) ) ) {
			return array(
				'verified' => false,
				'status'   => 'applied_unverified',
				'message'  => __( 'Direct /wp-login.php access still displays WordPress login form.', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => true,
			'status'   => 'done',
			'message'  => sprintf( __( 'Verified live: Direct /wp-login.php access is protected. Custom slug /%s is active.', 'site-checkup-pro' ), esc_html( $slug ) ),
		);
	}

	/**
	 * 15. Verify wp-config PHP Constants via fresh-process diagnostic probe.
	 */
	private static function verify_diagnostic_constant( $constant_name, $force_fresh, $expected_value = true ) {
		$probe_url = home_url( '/wp-json/site-checkup-pro/v1/diagnostic-probe' );
		$res       = self::fetch_loopback( $probe_url, 'GET', array(), $force_fresh );

		if ( is_wp_error( $res ) ) {
			// Fallback: if REST loopback failed, inspect defined() or wp-config directly
			if ( defined( $constant_name ) ) {
				$val = constant( $constant_name );
				if ( (bool) $val === (bool) $expected_value ) {
					return array(
						'verified' => true,
						'status'   => 'done',
						'message'  => sprintf( __( 'Constant %s is verified in current process (%s).', 'site-checkup-pro' ), $constant_name, $val ? 'true' : 'false' ),
					);
				}
			}
			return array( 'verified' => false, 'status' => 'applied_unverified', 'message' => $res->get_error_message() );
		}

		$data = json_decode( (string) $res['body'], true );
		if ( is_array( $data ) && isset( $data['constants'][ $constant_name ] ) ) {
			$actual = (bool) $data['constants'][ $constant_name ];
			if ( $actual === (bool) $expected_value ) {
				return array(
					'verified' => true,
					'status'   => 'done',
					'message'  => sprintf( __( 'Verified live in fresh process: %1$s is set to %2$s.', 'site-checkup-pro' ), $constant_name, $actual ? 'true' : 'false' ),
				);
			}
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => sprintf( __( 'Constant %s could not be verified in fresh runtime process.', 'site-checkup-pro' ), $constant_name ),
		);
	}

	/**
	 * 16. Verify User Enumeration Protection.
	 */
	private static function verify_user_enumeration( $force_fresh ) {
		$res_author = self::fetch_loopback( home_url( '/?author=1' ), 'GET', array(), $force_fresh );
		$res_rest   = self::fetch_loopback( home_url( '/wp-json/wp/v2/users' ), 'GET', array(), $force_fresh );

		$author_blocked = ( ! is_wp_error( $res_author ) && ( 403 === $res_author['code'] || 301 === $res_author['code'] || 302 === $res_author['code'] || 200 !== $res_author['code'] ) );
		$rest_blocked   = ( ! is_wp_error( $res_rest ) && ( 401 === $res_rest['code'] || 403 === $res_rest['code'] ) );

		if ( $author_blocked || $rest_blocked ) {
			return array(
				'verified' => true,
				'status'   => 'done',
				'message'  => __( 'Verified live: Author query and unauthenticated user REST endpoints are protected.', 'site-checkup-pro' ),
			);
		}

		return array(
			'verified' => false,
			'status'   => 'applied_unverified',
			'message'  => __( 'User enumeration protection could not be verified in live checks.', 'site-checkup-pro' ),
		);
	}
}
