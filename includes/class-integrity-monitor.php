<?php
/**
* Core & Uploads Integrity Monitor
 *
 * Scans WordPress core files against official WordPress.org checksums (strictly excluding wp-content)
 * and detects dangerous executable PHP files inside /wp-content/uploads/.
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
 * Class WPSG_Integrity_Monitor
 */
class WPSG_Integrity_Monitor {

	/**
	 * Transient key for core checksum results.
	 */
	const CHECKSUM_CACHE = 'wpsg_core_checksums';

	/**
	 * Check core files against WordPress.org checksums API.
	 * Explicitly excludes wp-content/ to prevent false positives.
	 *
	 * @param bool $force_refresh Bypass cache.
	 * @return array Checksum results.
	 */
	public static function check_core_checksums( $force_refresh = false ) {
		global $wp_version, $wp_local_package;

		if ( ! $force_refresh ) {
			$cached = get_transient( self::CHECKSUM_CACHE );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$locale = ! empty( $wp_local_package ) ? $wp_local_package : get_locale();
		$api_url = sprintf( 'https://api.wordpress.org/core/checksums/1.0/?version=%s&locale=%s', $wp_version, $locale );

		$response = wp_remote_get( $api_url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Fallback without locale if localized checksums not found
			$api_url = sprintf( 'https://api.wordpress.org/core/checksums/1.0/?version=%s', $wp_version );
			$response = wp_remote_get( $api_url, array( 'timeout' => 15 ) );
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'status'  => 'attention',
				'message' => __( 'Could not connect to WordPress.org checksums API.', 'site-checkup-pro' ),
				'details' => array(),
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['checksums'] ) || ! is_array( $body['checksums'] ) ) {
			return array(
				'status'  => 'attention',
				'message' => __( 'Invalid checksum data received from WordPress.org.', 'site-checkup-pro' ),
				'details' => array(),
			);
		}

		$checksums = $body['checksums'];
		$modified  = array();
		$missing   = array();
		$total     = 0;

		foreach ( $checksums as $file => $expected_hash ) {
			// STRICT REQUIREMENT: Explicitly exclude wp-content/ entirely!
			if ( 0 === strpos( $file, 'wp-content/' ) ) {
				continue;
			}

			$total++;
			$local_path = ABSPATH . $file;

			if ( ! file_exists( $local_path ) ) {
				$missing[] = $file;
				continue;
			}

			$local_hash = md5_file( $local_path );
			if ( $local_hash !== $expected_hash ) {
				$modified[] = $file;
			}
		}

		$is_clean = empty( $modified ) && empty( $missing );
		$result   = array(
			'status'         => $is_clean ? 'done' : 'failed',
			'total_scanned'  => $total,
			'modified_count' => count( $modified ),
			'missing_count'  => count( $missing ),
			'modified_files' => $modified,
			'missing_files'  => $missing,
			'last_checked'   => current_time( 'mysql' ),
			'message'        => $is_clean
				? sprintf( __( 'All %d WordPress core files verified and matched official WordPress.org checksums.', 'site-checkup-pro' ), $total )
				: sprintf( __( 'Integrity Alert: %1$d modified core file(s) and %2$d missing file(s) detected!', 'site-checkup-pro' ), count( $modified ), count( $missing ) ),
		);

		// Cache for 24 hours
		set_transient( self::CHECKSUM_CACHE, $result, 86400 );

		if ( ! $is_clean && class_exists( 'WPSG_Alert_Dispatcher' ) ) {
			WPSG_Alert_Dispatcher::dispatch(
				'core_file_tampering',
				$result['message'],
				array( 'modified' => $modified, 'missing' => $missing )
			);
		}

		return $result;
	}

	/**
	 * Scan /wp-content/uploads/ for unexpected executable PHP scripts.
	 *
	 * @return array
	 */
	public static function scan_uploads_for_executables() {
		$uploads     = wp_upload_dir();
		$uploads_dir = $uploads['basedir'];
		if ( ! is_dir( $uploads_dir ) ) {
			return array(
				'status'  => 'done',
				'files'   => array(),
				'message' => __( 'Uploads directory does not exist.', 'site-checkup-pro' ),
			);
		}

		$found = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			$count = 0;
			foreach ( $iterator as $file ) {
				if ( ++$count > 2000 ) {
					break; // Bounded scan limit.
				}
				if ( ! $file->isDir() ) {
					$ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
					if ( in_array( $ext, array( 'php', 'phtml', 'php5', 'phar', 'shtml' ), true ) ) {
						$rel_path = substr( $file->getPathname(), strlen( $uploads_dir ) + 1 );
						// Whitelist benign directory index guards (standard WordPress silence or HTTP 403 exit guards).
						if ( 'index.php' === $file->getFilename() ) {
							$content = @file_get_contents( $file->getPathname() );
							if ( false !== $content && ( false !== strpos( $content, 'Silence is golden' ) || false !== strpos( $content, 'http_response_code( 403 )' ) || trim( $content ) === '<?php' || trim( $content ) === '<?php exit;' ) ) {
								continue;
							}
						}
						$found[] = $rel_path;
					}
				}
			}
		} catch ( Exception $e ) {
			// Directory traversal catch
		}

		$is_clean = empty( $found );
		if ( ! $is_clean && class_exists( 'WPSG_Alert_Dispatcher' ) ) {
			WPSG_Alert_Dispatcher::dispatch(
				'uploads_php_detected',
				sprintf( __( 'High-Severity Alert: %d executable PHP script(s) discovered inside /wp-content/uploads/!', 'site-checkup-pro' ), count( $found ) ),
				array( 'files' => $found )
			);
		}

		return array(
			'status'  => $is_clean ? 'done' : 'failed',
			'files'   => $found,
			'message' => $is_clean
				? __( 'Zero executable PHP/phar scripts discovered in /wp-content/uploads/.', 'site-checkup-pro' )
				: sprintf( __( 'Critical Alert: %1$d unauthorized executable PHP script(s) found in /wp-content/uploads/: %2$s', 'site-checkup-pro' ), count( $found ), implode( ', ', $found ) ),
		);
	}
}
