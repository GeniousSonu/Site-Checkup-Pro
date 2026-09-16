<?php
/**
 * Safe .htaccess Rule Manager with Server Detection & Staging-Safe Health Checks
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Htaccess_Manager
 */
class WPSG_Htaccess_Manager {

	/**
	 * Prefix for all marker blocks inserted into .htaccess.
	 */
	const MARKER_PREFIX = 'SiteCheckupPro-';

	/**
	 * Detect if web server is Apache or LiteSpeed (supports .htaccess).
	 *
	 * @return string 'apache', 'litespeed', 'nginx', 'iis', or 'unknown'.
	 */
	public static function get_server_type() {
		$software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) ) : '';

		if ( false !== strpos( $software, 'litespeed' ) ) {
			return 'litespeed';
		}
		if ( false !== strpos( $software, 'apache' ) ) {
			return 'apache';
		}
		if ( false !== strpos( $software, 'nginx' ) ) {
			return 'nginx';
		}
		if ( false !== strpos( $software, 'microsoft-iis' ) ) {
			return 'iis';
		}

		// Fallback: check if .htaccess exists and is writable in ABSPATH.
		$htaccess_file = self::get_htaccess_path();
		if ( file_exists( $htaccess_file ) && is_writable( $htaccess_file ) ) {
			return 'apache'; // Assume compatible.
		}

		return 'unknown';
	}

	/**
	 * Check if the current server supports .htaccess rules.
	 *
	 * @return bool
	 */
	public static function supports_htaccess() {
		$server = self::get_server_type();
		return ( 'apache' === $server || 'litespeed' === $server );
	}

	/**
	 * Get absolute path to the webroot .htaccess file.
	 *
	 * @return string
	 */
	public static function get_htaccess_path() {
		return get_home_path() . '.htaccess';
	}

	/**
	 * Get the backup storage directory for .htaccess copies.
	 *
	 * @return string
	 */
	public static function get_backup_dir() {
		$upload_dir = wp_upload_dir();
		$dir        = trailingslashit( $upload_dir['basedir'] ) . 'wpsg-backups/';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
			// Write an index.php and .htaccess to protect backups.
			file_put_contents( $dir . 'index.php', '<?php // Silence is golden.' );
			file_put_contents( $dir . '.htaccess', 'Deny from all' );
		}

		return $dir;
	}

	/**
	 * Create a timestamped backup of the current .htaccess file.
	 *
	 * @return string|false Path to backup file or false on failure.
	 */
	public static function backup_htaccess() {
		$htaccess = self::get_htaccess_path();
		if ( ! file_exists( $htaccess ) ) {
			return false;
		}

		$backup_dir = self::get_backup_dir();
		$backup_file = $backup_dir . 'htaccess-' . gmdate( 'Ymd-His' ) . '.bak';

		if ( copy( $htaccess, $backup_file ) ) {
			return $backup_file;
		}

		return false;
	}

	/**
	 * Insert or update a marker block in .htaccess with automatic backup and health check.
	 *
	 * @param string       $marker Rule name without prefix.
	 * @param string|array $rules  Lines to insert.
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function insert_rule( $marker, $rules ) {
		// 1. Server software compatibility check.
		if ( ! self::supports_htaccess() ) {
			return array(
				'success' => false,
				'is_na'   => true,
				'message' => sprintf(
					/* translators: %s: server software name */
					__( 'Not applicable on this server (%s). Your web server does not process .htaccess rules.', 'site-checkup-pro' ),
					strtoupper( self::get_server_type() )
				),
			);
		}

		$htaccess_file = self::get_htaccess_path();
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		// 2. Backup existing .htaccess.
		$backup_path = self::backup_htaccess();
		if ( ! $backup_path && file_exists( $htaccess_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create safety backup of .htaccess before writing.', 'site-checkup-pro' ),
			);
		}

		// Ensure rules is array of lines.
		if ( is_string( $rules ) ) {
			$rules = explode( "\n", trim( $rules ) );
		}

		$full_marker = self::MARKER_PREFIX . $marker;

		// 3. Write via native insert_with_markers.
		$written = insert_with_markers( $htaccess_file, $full_marker, $rules );
		if ( ! $written ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to write to .htaccess. Please verify file permissions.', 'site-checkup-pro' ),
			);
		}

		// 4. Perform loopback health check.
		$health = self::run_health_check();
		if ( ! $health['healthy'] ) {
			// Rollback immediately!
			if ( $backup_path && file_exists( $backup_path ) ) {
				copy( $backup_path, $htaccess_file );
			}
			return array(
				'success' => false,
				'message' => sprintf(
					/* translators: %s: health check error message */
					__( 'Self-test failed: %s. Rule was automatically rolled back to prevent site downtime.', 'site-checkup-pro' ),
					$health['message']
				),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Rule applied and verified successfully.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Remove a marker block from .htaccess.
	 *
	 * @param string $marker Rule name without prefix.
	 * @return array
	 */
	public static function remove_rule( $marker ) {
		if ( ! self::supports_htaccess() ) {
			return array(
				'success' => true,
				'message' => __( 'Not applicable on this server.', 'site-checkup-pro' ),
			);
		}

		$htaccess_file = self::get_htaccess_path();
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';

		$backup_path = self::backup_htaccess();
		$full_marker = self::MARKER_PREFIX . $marker;

		// Passing empty array removes the marker block.
		$removed = insert_with_markers( $htaccess_file, $full_marker, array() );

		if ( ! $removed ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to remove rule from .htaccess.', 'site-checkup-pro' ),
			);
		}

		// Run health check after removal.
		$health = self::run_health_check();
		if ( ! $health['healthy'] && $backup_path && file_exists( $backup_path ) ) {
			copy( $backup_path, $htaccess_file );
			return array(
				'success' => false,
				'message' => __( 'Self-test failed after rule removal. Rolled back.', 'site-checkup-pro' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Rule removed successfully.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Check if a marker block is currently present in .htaccess.
	 *
	 * @param string $marker Rule name without prefix.
	 * @return bool
	 */
	public static function has_rule( $marker ) {
		$htaccess_file = self::get_htaccess_path();
		if ( ! file_exists( $htaccess_file ) ) {
			return false;
		}

		$content     = file_get_contents( $htaccess_file );
		$full_marker = self::MARKER_PREFIX . $marker;

		return ( false !== strpos( $content, "# BEGIN {$full_marker}" ) );
	}

	/**
	 * Generate a diff preview showing what will change in .htaccess.
	 *
	 * @param string       $marker Rule name without prefix.
	 * @param string|array $rules  Lines to insert.
	 * @return array
	 */
	public static function get_diff( $marker, $rules ) {
		$htaccess_file = self::get_htaccess_path();
		$current       = file_exists( $htaccess_file ) ? file_get_contents( $htaccess_file ) : '';

		if ( is_array( $rules ) ) {
			$rules = implode( "\n", $rules );
		}

		$full_marker = self::MARKER_PREFIX . $marker;
		$block       = "# BEGIN {$full_marker}\n{$rules}\n# END {$full_marker}";

		return array(
			'file'         => '.htaccess',
			'current'      => $current,
			'insert_block' => $block,
		);
	}

	/**
	 * Perform a staging-safe loopback health check.
	 *
	 * Detects HTTP Basic Auth walls (common on staging) and only triggers failure on 5xx or connection errors.
	 *
	 * @return array Array with 'healthy' (bool), 'status_code' (int), 'message' (string).
	 */
	public static function run_health_check() {
		$home_url = home_url( '/' );

		// Set short timeout and don't verify SSL in local/staging environments.
		$args = array(
			'timeout'     => 10,
			'redirection' => 3,
			'sslverify'   => false,
			'user-agent'  => 'SiteCheckupPro-SelfTest/1.0',
		);

		// If server is currently running HTTP Basic Auth, pass credentials if present.
		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$args['headers'] = array(
				'Authorization' => 'Basic ' . base64_encode( sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) ) . ':' . sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) ) ),
			);
		}

		$response = wp_remote_head( $home_url, $args );

		if ( is_wp_error( $response ) ) {
			// Fallback to GET if HEAD was method-not-allowed by host.
			$response = wp_remote_get( $home_url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'healthy'     => false,
				'status_code' => 0,
				'message'     => $response->get_error_message(),
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );

		// 401 Unauthorized or 403 Forbidden is expected on password-protected staging environments.
		// Only 5xx errors (500, 502, 503, 504) represent server/syntax failures caused by .htaccess.
		if ( $status_code >= 500 ) {
			return array(
				'healthy'     => false,
				'status_code' => $status_code,
				'message'     => sprintf( 'HTTP %d Server Error returned by website.', $status_code ),
			);
		}

		return array(
			'healthy'     => true,
			'status_code' => $status_code,
			'message'     => 'Website responded successfully.',
		);
	}
}
