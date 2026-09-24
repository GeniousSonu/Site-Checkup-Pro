<?php
/**
 * External Fingerprint & Information Disclosure Scanner
 *
 * Conducts safe, non-destructive loopback HTTP probes against the site's origin
 * to detect exposed sensitive files, version disclosures, backup artifacts, and debug logs.
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_External_Fingerprint
 */
class WPSG_External_Fingerprint {

	/**
	 * Cache key for probe results.
	 */
	const CACHE_KEY = 'wpsg_external_fingerprint_results';

	/**
	 * Cache TTL: 1 hour.
	 */
	const CACHE_TTL = 3600;

	/**
	 * Run non-destructive external loopback scan.
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array Scan results.
	 */
	public static function scan( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$base_url = function_exists( 'home_url' ) ? home_url( '/' ) : 'https://example.com/';
		$exposed  = array();
		$probes   = self::get_probe_definitions();
		$scanned  = 0;

		$req_args = array(
			'timeout'     => 10,
			'redirection' => 0, // Do not follow redirects to login/homepage
			'sslverify'   => true,
			'headers'     => array(
				'User-Agent'                  => 'SiteCheckupPro-FingerprintScanner/' . ( defined( 'WPSG_VERSION' ) ? WPSG_VERSION : '1.2.0' ),
				'X-WPSG-Self-Verification'    => '1',
				'Accept'                      => '*/*',
			),
		);

		foreach ( $probes as $id => $probe ) {
			$scanned++;
			$target_url = rtrim( $base_url, '/' ) . '/' . ltrim( $probe['path'], '/' );

			// SSRF protection: strictly confine to home_url() host
			$home_p   = wp_parse_url( $base_url );
			$target_p = wp_parse_url( $target_url );
			if ( ! $home_p || ! $target_p || empty( $target_p['host'] ) || strtolower( $home_p['host'] ) !== strtolower( $target_p['host'] ) ) {
				continue;
			}

			$response = wp_remote_get( $target_url, $req_args );
			if ( is_wp_error( $response ) ) {
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );

			if ( 200 === (int) $code ) {
				$matched = false;
				if ( ! empty( $probe['pattern'] ) ) {
					if ( preg_match( $probe['pattern'], $body ) ) {
						$matched = true;
					}
				} else {
					// Empty pattern means any 200 OK exposes the file
					$matched = true;
				}

				if ( $matched ) {
					$exposed[] = array(
						'id'          => $id,
						'path'        => $probe['path'],
						'url'         => $target_url,
						'title'       => $probe['title'],
						'severity'    => $probe['severity'],
						'description' => $probe['description'],
						'remediation' => $probe['remediation'],
					);
				}
			}
		}

		$status = empty( $exposed ) ? 'clean' : 'attention';
		$message = empty( $exposed )
			? sprintf(
				/* translators: %d: count */
				__( 'External fingerprint probe clean: %d sensitive paths checked with zero exposures.', 'site-checkup-pro' ),
				$scanned
			)
			: sprintf(
				/* translators: 1: exposed count, 2: total count */
				__( 'Alert: %1$d external exposure(s) detected out of %2$d paths probed.', 'site-checkup-pro' ),
				count( $exposed ),
				$scanned
			);

		$result = array(
			'success'       => true,
			'status'        => $status,
			'total_probed'  => $scanned,
			'exposed_count' => count( $exposed ),
			'exposed_items' => $exposed,
			'message'       => $message,
			'scanned_at'    => current_time( 'mysql' ),
		);

		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );
		return $result;
	}

	/**
	 * Probe definitions.
	 *
	 * @return array
	 */
	public static function get_probe_definitions() {
		return array(
			'git_head' => array(
				'path'        => '.git/HEAD',
				'title'       => __( 'Exposed Git Repository Metadata', 'site-checkup-pro' ),
				'severity'    => 'critical',
				'pattern'     => '/ref:\s*refs\//i',
				'description' => __( 'The .git repository directory is publicly accessible, allowing complete source code and history extraction.', 'site-checkup-pro' ),
				'remediation' => __( 'Block access to .git directories via server rules or remove .git from public document root.', 'site-checkup-pro' ),
			),
			'env_file' => array(
				'path'        => '.env',
				'title'       => __( 'Exposed Environment File (.env)', 'site-checkup-pro' ),
				'severity'    => 'critical',
				'pattern'     => '/(?:DB_|APP_|API_KEY|SECRET|PASSWORD)/i',
				'description' => __( 'Environment configuration file containing plain secrets is publicly readable.', 'site-checkup-pro' ),
				'remediation' => __( 'Block web access to .env files or move outside the document root immediately.', 'site-checkup-pro' ),
			),
			'wp_config_backup' => array(
				'path'        => 'wp-config.php~',
				'title'       => __( 'Exposed wp-config Backup File', 'site-checkup-pro' ),
				'severity'    => 'critical',
				'pattern'     => '/DB_PASSWORD/i',
				'description' => __( 'A text editor backup of wp-config.php is publicly served without PHP interpretation.', 'site-checkup-pro' ),
				'remediation' => __( 'Delete editor backup files (e.g. wp-config.php~, wp-config.old, wp-config.php.bak) from the server.', 'site-checkup-pro' ),
			),
			'debug_log' => array(
				'path'        => 'wp-content/debug.log',
				'title'       => __( 'Publicly Accessible WordPress Debug Log', 'site-checkup-pro' ),
				'severity'    => 'high',
				'pattern'     => '/(?:PHP Notice|PHP Fatal|Stack trace|\[\d{2}-[A-Za-z]{3}-\d{4})/i',
				'description' => __( 'The WordPress debug.log file is publicly readable, exposing internal paths, queries, or error states.', 'site-checkup-pro' ),
				'remediation' => __( 'Disable WP_DEBUG_LOG or block direct web requests to *.log files.', 'site-checkup-pro' ),
			),
			'readme_version' => array(
				'path'        => 'readme.html',
				'title'       => __( 'WordPress Version Leak via readme.html', 'site-checkup-pro' ),
				'severity'    => 'medium',
				'pattern'     => '/Version\s+[0-9]+/i',
				'description' => __( 'Default WordPress readme.html file reveals the exact core version installed.', 'site-checkup-pro' ),
				'remediation' => __( 'Delete readme.html from the WordPress root directory.', 'site-checkup-pro' ),
			),
			'license_txt' => array(
				'path'        => 'license.txt',
				'title'       => __( 'Exposed license.txt File', 'site-checkup-pro' ),
				'severity'    => 'low',
				'pattern'     => '/GNU GENERAL PUBLIC LICENSE/i',
				'description' => __( 'Default license.txt file helps automated bots fingerprint WordPress installation.', 'site-checkup-pro' ),
				'remediation' => __( 'Remove or restrict access to license.txt.', 'site-checkup-pro' ),
			),
		);
	}

	/**
	 * Clear cached scan results.
	 *
	 * @return bool
	 */
	public static function clear_cache() {
		return delete_transient( self::CACHE_KEY );
	}
}
