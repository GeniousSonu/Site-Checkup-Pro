<?php
/**
 * Developer Diagnostic Snapshot
 *
 * Compiles a comprehensive, strictly sanitized environment snapshot (PHP, server,
 * database, active theme, plugins, and boolean wp-config constants) formatted for
 * on-screen inspection and copyable Markdown for GitHub issues / support tickets.
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
 * Class WPSG_Diagnostic_Snapshot
 */
class WPSG_Diagnostic_Snapshot {

	/**
	 * Compile full diagnostic snapshot array.
	 *
	 * @return array
	 */
	public static function compile() {
		global $wpdb;

		// 1. WordPress details
		$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme() : null;
		$theme_name = $theme ? $theme->get( 'Name' ) : 'Unknown';
		$theme_ver  = $theme ? $theme->get( 'Version' ) : '';
		$parent_theme = ( $theme && $theme->parent() ) ? $theme->parent()->get( 'Name' ) : null;

		$wp_data = array(
			'version'      => function_exists( 'get_bloginfo' ) ? get_bloginfo( 'version' ) : 'Unknown',
			'multisite'    => function_exists( 'is_multisite' ) && is_multisite() ? 'Yes' : 'No',
			'site_url'     => self::sanitize_url( function_exists( 'get_site_url' ) ? get_site_url() : '' ),
			'home_url'     => self::sanitize_url( function_exists( 'home_url' ) ? home_url() : '' ),
			'active_theme' => $theme_name . ( $theme_ver ? ' (' . $theme_ver . ')' : '' ),
			'parent_theme' => $parent_theme,
			'locale'       => function_exists( 'get_locale' ) ? get_locale() : 'en_US',
		);

		// 2. Server & PHP environment
		$db_version = 'Unknown';
		if ( isset( $wpdb ) && is_object( $wpdb ) ) {
			if ( method_exists( $wpdb, 'db_version' ) ) {
				$db_version = $wpdb->db_version();
			}
		}

		$key_extensions = array( 'curl', 'openssl', 'mbstring', 'json', 'mysqli', 'gd', 'imagick', 'zip', 'xml' );
		$loaded_ext_status = array();
		foreach ( $key_extensions as $ext ) {
			$loaded_ext_status[ $ext ] = extension_loaded( $ext );
		}

		$server_data = array(
			'php_version'         => PHP_VERSION,
			'server_software'     => isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown',
			'database_version'    => $db_version,
			'memory_limit'        => ini_get( 'memory_limit' ) ? ini_get( 'memory_limit' ) : 'N/A',
			'max_execution_time'  => ini_get( 'max_execution_time' ) ? ini_get( 'max_execution_time' ) . 's' : 'N/A',
			'upload_max_filesize' => ini_get( 'upload_max_filesize' ) ? ini_get( 'upload_max_filesize' ) : 'N/A',
			'post_max_size'       => ini_get( 'post_max_size' ) ? ini_get( 'post_max_size' ) : 'N/A',
			'extensions'          => $loaded_ext_status,
		);

		// 3. Active Plugins
		$active_plugins = array();
		if ( function_exists( 'get_plugins' ) ) {
			$all_plugins = get_plugins();
			$active_slugs = (array) get_option( 'active_plugins', array() );
			foreach ( $active_slugs as $slug ) {
				if ( isset( $all_plugins[ $slug ] ) ) {
					$active_plugins[] = array(
						'name'    => sanitize_text_field( $all_plugins[ $slug ]['Name'] ),
						'version' => sanitize_text_field( $all_plugins[ $slug ]['Version'] ),
						'author'  => sanitize_text_field( strip_tags( $all_plugins[ $slug ]['Author'] ) ),
					);
				}
			}
		}

		// 4. Key wp-config.php Constants (Booleans / Names only — reuse audit log secret redaction)
		$constants_tracked = array(
			'WP_DEBUG',
			'WP_DEBUG_LOG',
			'WP_DEBUG_DISPLAY',
			'SCRIPT_DEBUG',
			'SAVEQUERIES',
			'DISALLOW_FILE_EDIT',
			'DISALLOW_FILE_MODS',
			'FORCE_SSL_ADMIN',
			'AUTOMATIC_UPDATER_DISABLED',
			'WP_CACHE',
			'WP_ENVIRONMENT_TYPE',
		);

		$constants_status = array();
		foreach ( $constants_tracked as $c ) {
			if ( defined( $c ) ) {
				$val = constant( $c );
				if ( is_bool( $val ) ) {
					$constants_status[ $c ] = $val ? 'true' : 'false';
				} elseif ( 'WP_ENVIRONMENT_TYPE' === $c ) {
					$constants_status[ $c ] = sanitize_text_field( (string) $val );
				} else {
					// Always redact any arbitrary or string values to prevent secret leakage.
					$constants_status[ $c ] = 'defined';
				}
			} else {
				$constants_status[ $c ] = 'undefined';
			}
		}

		// Apply secret redaction filter rule from WPSG_Audit_Log
		$sensitive_pattern = '/(pass|pwd|secret|key|salt|token|auth|cookie|hash)/i';
		foreach ( $constants_status as $k => $v ) {
			if ( preg_match( $sensitive_pattern, $k ) ) {
				$constants_status[ $k ] = '[REDACTED]';
			}
		}

		$snapshot = array(
			'generated_at' => current_time( 'mysql' ),
			'wordpress'    => $wp_data,
			'server'       => $server_data,
			'plugins'      => $active_plugins,
			'constants'    => $constants_status,
		);

		$snapshot['markdown'] = self::format_markdown( $snapshot );

		return $snapshot;
	}

	/**
	 * Convert compiled snapshot array to clean Markdown suitable for GitHub issues.
	 *
	 * @param array $data Snapshot data.
	 * @return string
	 */
	public static function format_markdown( array $data ) {
		$md  = "### Site Checkup Pro — Developer Diagnostic Snapshot\n";
		$md .= '**Generated:** `' . esc_html( $data['generated_at'] ) . "`\n\n";

		// WordPress
		$md .= "#### WordPress Environment\n";
		$md .= "- **WordPress Version:** `{$data['wordpress']['version']}`\n";
		$md .= "- **Multisite:** `{$data['wordpress']['multisite']}`\n";
		$md .= "- **Site URL:** `{$data['wordpress']['site_url']}`\n";
		$md .= "- **Active Theme:** `{$data['wordpress']['active_theme']}`" . ( ! empty( $data['wordpress']['parent_theme'] ) ? " (Parent: {$data['wordpress']['parent_theme']})" : '' ) . "\n";
		$md .= "- **Locale:** `{$data['wordpress']['locale']}`\n\n";

		// Server
		$md .= "#### Server & Database\n";
		$md .= "- **PHP Version:** `{$data['server']['php_version']}`\n";
		$md .= "- **Web Server:** `{$data['server']['server_software']}`\n";
		$md .= "- **Database:** `{$data['server']['database_version']}`\n";
		$md .= "- **Memory Limit:** `{$data['server']['memory_limit']}`\n";
		$md .= "- **Max Execution Time:** `{$data['server']['max_execution_time']}`\n";
		$md .= "- **Upload Max Filesize:** `{$data['server']['upload_max_filesize']}`\n\n";

		// Constants
		$md .= "#### Key wp-config.php Constants\n";
		$md .= "```\n";
		foreach ( $data['constants'] as $k => $v ) {
			$md .= sprintf( "%-28s = %s\n", $k, $v );
		}
		$md .= "```\n\n";

		// Plugins
		$count = count( $data['plugins'] );
		$md .= "#### Active Plugins ({$count})\n";
		if ( empty( $data['plugins'] ) ) {
			$md .= "_No active third-party plugins detected._\n";
		} else {
			foreach ( $data['plugins'] as $p ) {
				$md .= "- **{$p['name']}** `v{$p['version']}` by {$p['author']}\n";
			}
		}

		return $md;
	}

	/**
	 * Sanitize URL to strip any potential query parameters or basic auth.
	 *
	 * @param string $url URL to sanitize.
	 * @return string
	 */
	private static function sanitize_url( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( ! $parts || ! isset( $parts['host'] ) ) {
			return sanitize_text_field( $url );
		}
		$scheme = isset( $parts['scheme'] ) ? $parts['scheme'] . '://' : 'https://';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path   = isset( $parts['path'] ) ? $parts['path'] : '';
		return $scheme . $parts['host'] . $port . $path;
	}
}
