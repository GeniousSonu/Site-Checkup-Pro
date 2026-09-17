<?php
/**
 * RFC 9116 Security.txt Generator & Validator
 *
 * Checks for and safely generates /.well-known/security.txt inside the canonical document root
 * with pre-flight write permission verification and timestamped backups.
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
 * Class WPSG_Security_Txt
 */
class WPSG_Security_Txt {

	/**
	 * Locate the canonical document root for placing /.well-known/ files.
	 *
	 * @return string|false
	 */
	public static function get_document_root() {
		$candidates = array();

		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$candidates[] = realpath( $_SERVER['DOCUMENT_ROOT'] );
		}

		if ( function_exists( 'get_home_path' ) ) {
			$candidates[] = realpath( get_home_path() );
		}

		$candidates[] = realpath( ABSPATH );

		foreach ( $candidates as $path ) {
			if ( $path && is_dir( $path ) && is_readable( $path ) ) {
				return trailingslashit( $path );
			}
		}

		return false;
	}

	/**
	 * Get the canonical destination path for /.well-known/security.txt.
	 *
	 * @return string|false
	 */
	public static function get_target_file_path() {
		$doc_root = self::get_document_root();
		if ( ! $doc_root ) {
			return false;
		}

		return $doc_root . '.well-known/security.txt';
	}

	/**
	 * Check if /.well-known/security.txt exists and is non-empty.
	 * Per RFC 9116, only /.well-known/security.txt is evaluated.
	 *
	 * @return array
	 */
	public static function check_status() {
		$file = self::get_target_file_path();
		if ( ! $file || ! file_exists( $file ) ) {
			return array(
				'status'  => 'attention',
				'exists'  => false,
				'path'    => $file ? $file : '/.well-known/security.txt',
				'message' => __( 'Missing /.well-known/security.txt. Generating one provides a standardized contact channel for responsible vulnerability disclosure.', 'site-checkup-pro' ),
			);
		}

		$content = file_get_contents( $file );
		if ( empty( trim( $content ) ) || false === strpos( $content, 'Contact:' ) ) {
			return array(
				'status'  => 'attention',
				'exists'  => true,
				'path'    => $file,
				'message' => __( 'A /.well-known/security.txt file exists but does not contain a valid Contact: directive.', 'site-checkup-pro' ),
			);
		}

		return array(
			'status'  => 'done',
			'exists'  => true,
			'path'    => $file,
			'message' => __( 'RFC 9116 security disclosure file is present at /.well-known/security.txt and correctly formatted.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Generate or update /.well-known/security.txt safely.
	 *
	 * @param string|null $custom_contact Optional custom contact email or URL.
	 * @return array
	 */
	public static function generate( $custom_contact = null ) {
		$doc_root = self::get_document_root();
		if ( ! $doc_root ) {
			return array(
				'success' => false,
				'message' => __( 'Could not determine the web server document root.', 'site-checkup-pro' ),
			);
		}

		$well_known_dir = $doc_root . '.well-known';
		$target_file    = $well_known_dir . '/security.txt';

		// Pre-flight write permission checks
		if ( file_exists( $well_known_dir ) ) {
			if ( ! is_writable( $well_known_dir ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'The directory %s is not writable by the web server.', 'site-checkup-pro' ), esc_html( $well_known_dir ) ),
				);
			}
		} else {
			if ( ! is_writable( $doc_root ) ) {
				return array(
					'success' => false,
					'message' => sprintf( __( 'Document root %s is not writable to create /.well-known/.', 'site-checkup-pro' ), esc_html( $doc_root ) ),
				);
			}
			wp_mkdir_p( $well_known_dir );
		}

		// Security confinement: verify canonical path stays strictly inside document root
		$real_dir = realpath( $well_known_dir );
		if ( ! $real_dir || 0 !== strpos( $real_dir, $doc_root ) ) {
			return array(
				'success' => false,
				'message' => __( 'Security error: Destination directory resolves outside of the approved document root.', 'site-checkup-pro' ),
			);
		}

		// Backup existing file if present for safe undo
		if ( file_exists( $target_file ) ) {
			$backup_dir = WPSG_Htaccess_Manager::get_backup_dir();
			$backup_dst = $backup_dir . 'security-txt-' . gmdate( 'Ymd-His' ) . '.bak';
			copy( $target_file, $backup_dst );
			update_option( 'wpsg_last_security_txt_backup', $backup_dst );
		}

		// Resolve Contact detail
		$contact = '';
		if ( ! empty( $custom_contact ) ) {
			$contact = sanitize_text_field( $custom_contact );
		} else {
			$settings = get_option( 'wpsg_settings', array() );
			if ( ! empty( $settings['incident_contact_email'] ) ) {
				$contact = 'mailto:' . sanitize_email( $settings['incident_contact_email'] );
			} else {
				$contact = 'mailto:' . sanitize_email( get_option( 'admin_email' ) );
			}
		}

		$canonical = home_url( '/.well-known/security.txt' );
		$expires   = gmdate( 'Y-m-d\TH:i:s\Z', time() + ( 365 * 86400 ) );

		$body  = "# Security Disclosure Policy\n";
		$body .= "# Generated by Site Checkup Pro\n";
		$body .= "Contact: " . $contact . "\n";
		$body .= "Expires: " . $expires . "\n";
		$body .= "Preferred-Languages: en\n";
		$body .= "Canonical: " . esc_url_raw( $canonical ) . "\n";

		$written = @file_put_contents( $target_file, $body );
		if ( false === $written ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to write security.txt to /.well-known/ directory.', 'site-checkup-pro' ),
			);
		}

		// Set proper 0644 permissions on the newly created file
		@chmod( $target_file, 0644 );

		return array(
			'success'  => true,
			'message'  => __( 'RFC 9116 security.txt successfully generated at /.well-known/security.txt.', 'site-checkup-pro' ),
			'file'     => $target_file,
		);
	}

	/**
	 * Remove generated security.txt and restore previous backup if available.
	 *
	 * @return array
	 */
	public static function undo() {
		$target_file = self::get_target_file_path();
		if ( ! $target_file || ! file_exists( $target_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'security.txt file does not exist.', 'site-checkup-pro' ),
			);
		}

		$backup = get_option( 'wpsg_last_security_txt_backup' );
		if ( ! empty( $backup ) && file_exists( $backup ) ) {
			copy( $backup, $target_file );
			delete_option( 'wpsg_last_security_txt_backup' );
			return array(
				'success' => true,
				'message' => __( 'Previous security.txt restored from backup.', 'site-checkup-pro' ),
			);
		}

		@unlink( $target_file );
		return array(
			'success' => true,
			'message' => __( 'security.txt file was removed.', 'site-checkup-pro' ),
		);
	}
}
