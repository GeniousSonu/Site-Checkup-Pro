<?php
/**
 * Server-Side Request Forgery (SSRF) & DNS Rebinding Guard
 *
 * Provides strict multi-IP IPv4/IPv6 validation, cloud-metadata protection,
 * CURLOPT_RESOLVE IP pinning, and redirect blocking.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_SSRF_Guard
 */
class WPSG_SSRF_Guard {

	/**
	 * Validate a URL against SSRF and DNS rebinding attacks.
	 *
	 * Performs a single combined A + AAAA DNS query and validates all resolved IPs.
	 *
	 * @param string $url URL to check.
	 * @return array|WP_Error Array with host, port, pinned_ip if safe, or WP_Error on failure.
	 */
	public static function validate_url( $url ) {
		if ( ! is_string( $url ) || empty( $url ) ) {
			return new WP_Error( 'invalid_url', __( 'Empty or non-string URL specified.', 'site-checkup-pro' ) );
		}

		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return new WP_Error( 'invalid_url', __( 'Malformed URL or missing host/scheme.', 'site-checkup-pro' ) );
		}

		$scheme = strtolower( $parsed['scheme'] );
		if ( 'http' !== $scheme && 'https' !== $scheme ) {
			return new WP_Error( 'forbidden_scheme', __( 'Only HTTP and HTTPS protocols are permitted.', 'site-checkup-pro' ) );
		}

		$host = strtolower( trim( $parsed['host'] ) );
		$port = isset( $parsed['port'] ) ? (int) $parsed['port'] : ( 'https' === $scheme ? 443 : 80 );

		// Reject obvious loopback and literal IP patterns directly.
		if ( 'localhost' === $host || '127.0.0.1' === $host || '::1' === $host || '169.254.169.254' === $host ) {
			return new WP_Error( 'forbidden_host', __( 'Target host is a reserved or internal address.', 'site-checkup-pro' ) );
		}

		// Single combined DNS query for both A (IPv4) and AAAA (IPv6) records.
		$ips = array();

		// If host is already an IP address.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ips[] = $host;
		} else {
			if ( function_exists( 'dns_get_record' ) ) {
				$records = @dns_get_record( $host, DNS_A | DNS_AAAA );
				if ( is_array( $records ) ) {
					foreach ( $records as $rec ) {
						if ( 'A' === $rec['type'] && ! empty( $rec['ip'] ) ) {
							$ips[] = $rec['ip'];
						} elseif ( 'AAAA' === $rec['type'] && ! empty( $rec['ipv6'] ) ) {
							$ips[] = $rec['ipv6'];
						}
					}
				}
			}

			// Fallback to gethostbynamel if dns_get_record returned nothing.
			if ( empty( $ips ) && function_exists( 'gethostbynamel' ) ) {
				$v4_records = @gethostbynamel( $host );
				if ( is_array( $v4_records ) ) {
					$ips = array_merge( $ips, $v4_records );
				}
			}
		}

		if ( empty( $ips ) ) {
			return new WP_Error( 'dns_resolution_failed', __( 'Could not resolve domain name to any valid IP address.', 'site-checkup-pro' ) );
		}

		// Validate EVERY resolved IP address. If even ONE IP is internal/private, reject the host!
		foreach ( $ips as $ip ) {
			if ( ! self::is_ip_safe( $ip ) ) {
				return new WP_Error(
					'forbidden_ip',
					sprintf(
						/* translators: %s: forbidden IP */
						__( 'Destination resolved to forbidden private, loopback, or metadata IP: %s', 'site-checkup-pro' ),
						esc_html( $ip )
					)
				);
			}
		}

		return array(
			'safe'      => true,
			'host'      => $host,
			'port'      => $port,
			'scheme'    => $scheme,
			'pinned_ip' => $ips[0],
		);
	}

	/**
	 * Verify if an IP is public and safe (rejects private, loopback, link-local, multicast, cloud metadata).
	 *
	 * @param string $ip IPv4 or IPv6 address.
	 * @return bool True if safe public IP, false if forbidden.
	 */
	public static function is_ip_safe( $ip ) {
		// General filter check.
		$is_valid = filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);

		if ( false === $is_valid ) {
			return false;
		}

		// IPv4 specific range checks.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			$long = ip2long( $ip );
			if ( false === $long ) {
				return false;
			}

			// Explicitly check forbidden IPv4 ranges.
			$forbidden_ranges = array(
				array( '127.0.0.0', '127.255.255.255' ),
				array( '10.0.0.0', '10.255.255.255' ),
				array( '172.16.0.0', '172.31.255.255' ),
				array( '192.168.0.0', '192.168.255.255' ),
				array( '169.254.0.0', '169.254.255.255' ),
				array( '0.0.0.0', '0.255.255.255' ),
				array( '100.64.0.0', '100.127.255.255' ),
				array( '192.0.0.0', '192.0.0.255' ),
				array( '192.0.2.0', '192.0.2.255' ),
				array( '198.51.100.0', '198.51.100.255' ),
				array( '203.0.113.0', '203.0.113.255' ),
				array( '224.0.0.0', '239.255.255.255' ),
				array( '240.0.0.0', '255.255.255.255' ),
			);

			foreach ( $forbidden_ranges as $range ) {
				$min = ip2long( $range[0] );
				$max = ip2long( $range[1] );
				if ( $long >= $min && $long <= $max ) {
					return false;
				}
			}

			return true;
		}

		// IPv6 specific range checks.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$hex = bin2hex( inet_pton( $ip ) );

			// ::1 (Loopback)
			if ( '00000000000000000000000000000001' === $hex ) {
				return false;
			}

			// fc00::/7 (Unique local)
			$first_byte = hexdec( substr( $hex, 0, 2 ) );
			if ( ( $first_byte & 0xfe ) === 0xfc ) {
				return false;
			}

			// fe80::/10 (Link-local)
			$first_word = hexdec( substr( $hex, 0, 4 ) );
			if ( ( $first_word & 0xffc0 ) === 0xfe80 ) {
				return false;
			}

			// ::ffff:0:0/96 (IPv4-mapped)
			if ( 0 === strpos( $hex, '00000000000000000000ffff' ) ) {
				$ipv4_hex = substr( $hex, 24 );
				$ipv4     = long2ip( hexdec( $ipv4_hex ) );
				return self::is_ip_safe( $ipv4 );
			}

			// fd00:ec2::254 (AWS IMDSv6)
			if ( 0 === strpos( strtolower( $ip ), 'fd00:ec2::254' ) ) {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Perform a hardened outbound HTTP POST request with pinned IP and disabled redirects.
	 *
	 * @param string $url  Target URL.
	 * @param array  $args wp_remote_post arguments.
	 * @return array|WP_Error Response array or WP_Error.
	 */
	public static function safe_remote_post( $url, array $args = array() ) {
		$validation = self::validate_url( $url );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$host      = $validation['host'];
		$port      = $validation['port'];
		$pinned_ip = $validation['pinned_ip'];

		// Enforce safety options.
		$args['redirection'] = 0; // Disable redirect-following entirely!
		$args['timeout']     = isset( $args['timeout'] ) ? min( (int) $args['timeout'], 15 ) : 10;

		// Pin IP and lock down protocols in cURL via filter.
		$curl_hook = function ( $handle ) use ( $host, $port, $pinned_ip ) {
			// Zero redirects.
			curl_setopt( $handle, CURLOPT_FOLLOWLOCATION, false );

			// Strict protocol restrictions: only HTTP and HTTPS.
			if ( defined( 'CURLPROTO_HTTP' ) && defined( 'CURLPROTO_HTTPS' ) ) {
				curl_setopt( $handle, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );
				curl_setopt( $handle, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS );
			}

			// Pin connection to the pre-validated IP to eliminate DNS rebinding.
			if ( defined( 'CURLOPT_RESOLVE' ) ) {
				curl_setopt( $handle, CURLOPT_RESOLVE, array( "{$host}:{$port}:{$pinned_ip}" ) );
			}
		};

		add_action( 'http_api_curl', $curl_hook, 10, 1 );

		try {
			$response = wp_remote_post( $url, $args );
		} finally {
			remove_action( 'http_api_curl', $curl_hook, 10 );
		}

		return $response;
	}
}
