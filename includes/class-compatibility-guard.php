<?php
/**
 * Defensive Compatibility & Conflict Guard
 *
 * Detects overlapping security plugins (Wordfence, Solid Security, Sucuri, AIOS)
 * to prevent hook contention or duplicate .htaccess rules, and provides graceful
 * degradation on read-only managed hosting environments.
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
 * Class WPSG_Compatibility_Guard
 */
class WPSG_Compatibility_Guard {

	/**
	 * Detect all active third-party security plugins.
	 *
	 * @return array List of detected active security plugin identifiers and names.
	 */
	public static function get_active_security_plugins() {
		$active = array();

		// Wordfence Security
		if ( class_exists( 'wordfence' ) || is_plugin_active( 'wordfence/wordfence.php' ) ) {
			$active['wordfence'] = 'Wordfence Security';
		}

		// Solid Security (formerly iThemes Security)
		if ( defined( 'ITSEC_CORE_DIR' ) || is_plugin_active( 'better-wp-security/better-wp-security.php' ) ) {
			$active['solid_security'] = 'Solid Security (iThemes)';
		}

		// Sucuri Security
		if ( defined( 'SUCURISCAN' ) || is_plugin_active( 'sucuri-scanner/sucuri.php' ) ) {
			$active['sucuri'] = 'Sucuri Security';
		}

		// All In One WP Security & Firewall (AIOS)
		if ( defined( 'AIO_WP_SECURITY_VERSION' ) || is_plugin_active( 'all-in-one-wp-security-and-firewall/wp-security.php' ) ) {
			$active['aios'] = 'All In One WP Security (AIOS)';
		}

		return $active;
	}

	/**
	 * Detect if an active security plugin provides an overlapping feature.
	 * Allows Site Checkup Pro to offer deferral rather than fighting over hooks or rules.
	 *
	 * @param string $feature_key Feature identifier (e.g. 'xmlrpc', 'login_protection', 'security_headers').
	 * @return array Status indicating whether an overlapping provider was detected.
	 */
	public static function detect_feature_overlap( $feature_key ) {
		$active_plugins = self::get_active_security_plugins();

		if ( empty( $active_plugins ) ) {
			return array(
				'has_overlap' => false,
				'provider'    => '',
				'advice'      => '',
			);
		}

		switch ( $feature_key ) {
			case 'xmlrpc':
				if ( isset( $active_plugins['aios'] ) || isset( $active_plugins['wordfence'] ) || isset( $active_plugins['solid_security'] ) ) {
					$names = implode( ', ', $active_plugins );
					return array(
						'has_overlap' => true,
						'provider'    => $names,
						'advice'      => sprintf(
							/* translators: %s: active plugin names */
							__( 'Active security plugin(s) (%s) may already manage XML-RPC restrictions. Deferring to external rules prevents rule conflicts.', 'site-checkup-pro' ),
							$names
						),
					);
				}
				break;

			case 'login_protection':
				if ( isset( $active_plugins['wordfence'] ) || isset( $active_plugins['solid_security'] ) || isset( $active_plugins['aios'] ) ) {
					$names = implode( ', ', $active_plugins );
					return array(
						'has_overlap' => true,
						'provider'    => $names,
						'advice'      => sprintf(
							/* translators: %s: active plugin names */
							__( 'Active security plugin(s) (%s) already enforce login rate limiting. Site Checkup Pro rate limits will operate as a secondary defense layer.', 'site-checkup-pro' ),
							$names
						),
					);
				}
				break;

			case 'security_headers':
				if ( isset( $active_plugins['aios'] ) || isset( $active_plugins['solid_security'] ) ) {
					$names = implode( ', ', $active_plugins );
					return array(
						'has_overlap' => true,
						'provider'    => $names,
						'advice'      => sprintf(
							/* translators: %s: active plugin names */
							__( 'Active plugin(s) (%s) may already write security headers. Verify headers via browser devtools to avoid duplicate HTTP headers.', 'site-checkup-pro' ),
							$names
						),
					);
				}
				break;
		}

		return array(
			'has_overlap' => false,
			'provider'    => '',
			'advice'      => '',
		);
	}

	/**
	 * Check if a file target is writable; if not, return graceful degradation instructions.
	 * Handles managed hosts (WP Engine, Kinsta, Pressable) with read-only root/config.
	 *
	 * @param string $file_path Target file path.
	 * @param string $rule_type Rule or snippet identifier.
	 * @return array
	 */
	public static function check_writable_or_fallback( $file_path, $rule_type = '' ) {
		$exists   = file_exists( $file_path );
		$writable = $exists ? is_writable( $file_path ) : is_writable( dirname( $file_path ) );

		if ( $writable ) {
			return array(
				'is_writable'           => true,
				'graceful_degradation'  => false,
				'manual_snippet_needed' => false,
				'message'               => '',
			);
		}

		// Read-only filesystem detected!
		$filename = basename( $file_path );
		return array(
			'is_writable'           => false,
			'graceful_degradation'  => true,
			'manual_snippet_needed' => true,
			'file'                  => $filename,
			'message'               => sprintf(
				/* translators: %s: file name */
				__( 'Managed Hosting Notice: %s is read-only in this hosting environment. Automatic file modification is disabled to protect server stability. Please provide this configuration snippet to your hosting support or add it via your control panel.', 'site-checkup-pro' ),
				$filename
			),
		);
	}
}
