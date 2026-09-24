<?php
/**
* Safe .htaccess Rule Manager with Central Rule Registry, flock() File Locking,
 * Server Detection & Staging-Safe Health Checks
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
 * Class WPSG_Htaccess_Manager
 */
class WPSG_Htaccess_Manager {

	/**
	 * Prefix for all marker blocks inserted into .htaccess.
	 */
	const MARKER_PREFIX = 'SiteCheckupPro-';

	/**
	 * Central registry of all named .htaccess rules.
	 * Guarantees a single source of truth and prevents overlapping/clobbering blocks.
	 *
	 * @return array
	 */
	public static function get_rule_registry() {
		return array(
			// 1. Hide PHP Version Header
			'HidePHPVersion' => array(
				'title'       => __( 'Hide PHP Version (X-Powered-By)', 'site-checkup-pro' ),
				'marker'      => 'HidePHPVersion',
				'rules'       => array(
					'<IfModule mod_headers.c>',
					'  Header unset X-Powered-By',
					'  Header always unset X-Powered-By',
					'</IfModule>',
				),
				'nginx'       => "proxy_hide_header X-Powered-By;\nfastcgi_hide_header X-Powered-By;",
				'opt_in'      => false,
			),

			// 2. Clickjacking (X-Frame-Options)
			'Clickjacking' => array(
				'title'       => __( 'Clickjacking Protection (X-Frame-Options: SAMEORIGIN)', 'site-checkup-pro' ),
				'marker'      => 'Clickjacking',
				'rules'       => array(
					'<IfModule mod_headers.c>',
					'  Header always set X-Frame-Options "SAMEORIGIN"',
					'</IfModule>',
				),
				'nginx'       => 'add_header X-Frame-Options "SAMEORIGIN" always;',
				'opt_in'      => false,
			),

			// 3. MIME Sniffing (X-Content-Type-Options)
			'MimeSniffing' => array(
				'title'       => __( 'MIME-Type Sniffing Protection (nosniff)', 'site-checkup-pro' ),
				'marker'      => 'MimeSniffing',
				'rules'       => array(
					'<IfModule mod_headers.c>',
					'  Header always set X-Content-Type-Options "nosniff"',
					'</IfModule>',
				),
				'nginx'       => 'add_header X-Content-Type-Options "nosniff" always;',
				'opt_in'      => false,
			),

			// 4. HSTS (Strict-Transport-Security) — Staged Rollout Pre-Flight (300s)
			'HSTS' => array(
				'title'       => __( 'HSTS Header Enforcement (Staged Pre-Flight: 300s)', 'site-checkup-pro' ),
				'marker'      => 'HSTS',
				'rules'       => array(
					'<IfModule mod_headers.c>',
					'  Header always set Strict-Transport-Security "max-age=300; includeSubDomains"',
					'</IfModule>',
				),
				'nginx'       => 'add_header Strict-Transport-Security "max-age=300; includeSubDomains" always;',
				'opt_in'      => false,
			),
			'HSTS_Production' => array(
				'title'       => __( 'HSTS Header Enforcement (Production: 1-Year Preload)', 'site-checkup-pro' ),
				'marker'      => 'HSTS',
				'rules'       => array(
					'<IfModule mod_headers.c>',
					'  Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"',
					'</IfModule>',
				),
				'nginx'       => 'add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;',
				'opt_in'      => false,
			),

			// 5. Disable Directory Browsing
			'DisableIndexes' => array(
				'title'       => __( 'Disable Directory Browsing (Options -Indexes)', 'site-checkup-pro' ),
				'marker'      => 'DisableIndexes',
				'rules'       => array(
					'Options -Indexes',
				),
				'nginx'       => 'autoindex off;',
				'opt_in'      => false,
			),

			// 6. Protect System & Sensitive Files (readme, license, sample, htaccess, wp-config)
			'ProtectSensitiveFiles' => array(
				'title'       => __( 'Protect Sensitive & System Files', 'site-checkup-pro' ),
				'marker'      => 'ProtectSensitiveFiles',
				'rules'       => array(
					'<FilesMatch "^(readme\.html|license\.txt|wp-config-sample\.php|wp-config\.php|\.htaccess|\.env)">',
					'  Order Allow,Deny',
					'  Deny from all',
					'</FilesMatch>',
				),
				'nginx'       => 'location ~* ^/(readme\.html|license\.txt|wp-config-sample\.php|wp-config\.php|\.htaccess|\.env) { deny all; }',
				'opt_in'      => false,
			),

			// 7. Block Direct XML-RPC via .htaccess
			'BlockXMLRPC' => array(
				'title'       => __( 'Block XML-RPC Access via Web Server', 'site-checkup-pro' ),
				'marker'      => 'BlockXMLRPC',
				'rules'       => array(
					'<Files xmlrpc.php>',
					'  Order Allow,Deny',
					'  Deny from all',
					'</Files>',
				),
				'nginx'       => 'location = /xmlrpc.php { deny all; }',
				'opt_in'      => false,
			),

			// 8. Block PHP Execution in Uploads Directory
			'deny_uploads_php' => array(
				'title'       => __( 'Block PHP Execution in /wp-content/uploads/', 'site-checkup-pro' ),
				'marker'      => 'DenyUploadsPHP',
				'rules'       => array(
					'<IfModule mod_rewrite.c>',
					'  RewriteEngine On',
					'  RewriteRule ^wp-content/uploads/.*\.php$ - [F,L]',
					'</IfModule>',
				),
				'nginx'       => 'location ~* ^/wp-content/uploads/.*\.php$ { deny all; }',
				'opt_in'      => true,
				'requires_preflight' => true,
			),

			// 9. Basic Firewall: SQL Injection Pattern
			'basic_firewall_sqli' => array(
				'title'       => __( 'Lightweight Firewall: Block SQL Injection Signatures', 'site-checkup-pro' ),
				'marker'      => 'FirewallSQLi',
				'rules'       => array(
					'<IfModule mod_rewrite.c>',
					'  RewriteEngine On',
					'  RewriteCond %{QUERY_STRING} (union.*select|insert.*into|drop.*table) [NC]',
					'  RewriteRule ^ - [F,L]',
					'</IfModule>',
				),
				'nginx'       => 'if ($query_string ~* "(union.*select|insert.*into|drop.*table)") { return 403; }',
				'opt_in'      => true,
			),

			// 10. Basic Firewall: XSS Pattern
			'basic_firewall_xss' => array(
				'title'       => __( 'Lightweight Firewall: Block Script Injection Signatures', 'site-checkup-pro' ),
				'marker'      => 'FirewallXSS',
				'rules'       => array(
					'<IfModule mod_rewrite.c>',
					'  RewriteEngine On',
					'  RewriteCond %{QUERY_STRING} (<script|%3Cscript|javascript:) [NC]',
					'  RewriteRule ^ - [F,L]',
					'</IfModule>',
				),
				'nginx'       => 'if ($query_string ~* "(<script|%3Cscript|javascript:)") { return 403; }',
				'opt_in'      => true,
			),

			// 11. Basic Firewall: Path Traversal
			'basic_firewall_traversal' => array(
				'title'       => __( 'Lightweight Firewall: Block Path Traversal (../)', 'site-checkup-pro' ),
				'marker'      => 'FirewallTraversal',
				'rules'       => array(
					'<IfModule mod_rewrite.c>',
					'  RewriteEngine On',
					'  RewriteCond %{QUERY_STRING} (\.\./|\.\.\\) [NC]',
					'  RewriteRule ^ - [F,L]',
					'</IfModule>',
				),
				'nginx'       => 'if ($query_string ~* "(\\.\\./|\\.\\.\\\\)") { return 403; }',
				'opt_in'      => true,
			),

			// 12. Basic Firewall: Remote Code Execution / Eval Signatures
			'basic_firewall_rce' => array(
				'title'       => __( 'Lightweight Firewall: Block RCE Signatures', 'site-checkup-pro' ),
				'marker'      => 'FirewallRCE',
				'rules'       => array(
					'<IfModule mod_rewrite.c>',
					'  RewriteEngine On',
					'  RewriteCond %{QUERY_STRING} (base64_decode|eval\(|system\() [NC]',
					'  RewriteRule ^ - [F,L]',
					'</IfModule>',
				),
				'nginx'       => 'if ($query_string ~* "(base64_decode|eval\\(|system\\()") { return 403; }',
				'opt_in'      => true,
			),

			// 13. Bad Bot Scanners (Explicitly labeled as Noise Reduction)
			'bad_bots' => array(
				'title'       => __( 'Noise Reduction: Block Known Automated Scanners User-Agents', 'site-checkup-pro' ),
				'marker'      => 'BadBotsNoiseReduction',
				'rules'       => array(
					'<IfModule mod_rewrite.c>',
					'  RewriteEngine On',
					'  RewriteCond %{HTTP_USER_AGENT} (sqlmap|nikto|wpscan|dirbuster|havij|acunetix) [NC]',
					'  RewriteRule ^ - [F,L]',
					'</IfModule>',
				),
				'nginx'       => 'if ($http_user_agent ~* "(sqlmap|nikto|wpscan|dirbuster|havij|acunetix)") { return 403; }',
				'opt_in'      => true,
			),

			// 14. Content-Security-Policy (Report-Only Mode First)
			'csp_report_only' => array(
				'title'       => __( 'Content-Security-Policy (Report-Only Mode)', 'site-checkup-pro' ),
				'marker'      => 'CSPReportOnly',
				'rules'       => array(
					'<IfModule mod_headers.c>',
					'  Header always set Content-Security-Policy-Report-Only "default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; font-src \'self\' data:; connect-src \'self\'; report-uri /wp-json/site-checkup-pro/v1/csp-report"',
					'</IfModule>',
				),
				'nginx'       => 'add_header Content-Security-Policy-Report-Only "default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; font-src \'self\' data:; connect-src \'self\'; report-uri /wp-json/site-checkup-pro/v1/csp-report" always;',
				'opt_in'      => true,
			),

			// 15. Referrer-Policy
			'referrer_policy' => array(
				'title'       => __( 'Referrer-Policy: strict-origin-when-cross-origin', 'site-checkup-pro' ),
				'marker'      => 'ReferrerPolicy',
				'rules'       => array(
					'<IfModule mod_headers.c>',
					'  Header always set Referrer-Policy "strict-origin-when-cross-origin"',
					'</IfModule>',
				),
				'nginx'       => 'add_header Referrer-Policy "strict-origin-when-cross-origin" always;',
				'opt_in'      => false,
			),

			// 16. Permissions-Policy
			'permissions_policy' => array(
				'title'       => __( 'Permissions-Policy Header', 'site-checkup-pro' ),
				'marker'      => 'PermissionsPolicy',
				'rules'       => array(
					'<IfModule mod_headers.c>',
					'  Header always set Permissions-Policy "geolocation=(), camera=(), microphone=()"',
					'</IfModule>',
				),
				'nginx'       => 'add_header Permissions-Policy "geolocation=(), camera=(), microphone=()" always;',
				'opt_in'      => false,
			),
		);
	}

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
			return 'apache';
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
	 * Check if Tier 1 (Hosting Panel API) is active and configured.
	 *
	 * @return bool
	 */
	public static function has_nginx_tier1() {
		return class_exists( 'WPSG_Hosting_Panel_Bridge' ) && WPSG_Hosting_Panel_Bridge::is_configured();
	}

	/**
	 * Check if Tier 2 (Companion include directory) is active.
	 *
	 * Requires that the companion module is present and configured.
	 *
	 * @return bool
	 */
	public static function has_nginx_tier2() {
		if ( class_exists( 'WPSG_Nginx_Tier2' ) ) {
			return WPSG_Nginx_Tier2::is_active();
		}
		return false;
	}

	/**
	 * Get absolute path to the Nginx include directory.
	 *
	 * @return string
	 */
	public static function get_nginx_conf_dir() {
		if ( class_exists( 'WPSG_Nginx_Tier2' ) ) {
			return WPSG_Nginx_Tier2::get_conf_dir();
		}
		return '';
	}

	/**
	 * Get absolute path to the Nginx staging directory.
	 *
	 * @return string
	 */
	public static function get_nginx_staging_dir() {
		if ( class_exists( 'WPSG_Nginx_Tier2' ) ) {
			return WPSG_Nginx_Tier2::get_staging_dir();
		}
		return '';
	}

	/**
	 * Execute a strictly hardcoded system command via Tier 2 companion.
	 *
	 * @param array $cmd Literal command array.
	 * @return array Array with 'success' (bool), 'exit_code' (int), 'output' (string).
	 */
	public static function execute_fixed_system_command( array $cmd ) {
		if ( class_exists( 'WPSG_Nginx_Tier2' ) ) {
			return WPSG_Nginx_Tier2::execute_fixed_system_command( $cmd );
		}
		return array(
			'success'   => false,
			'exit_code' => -1,
			'output'    => 'Tier 2 execution unavailable.',
		);
	}

	/**
	 * Apply an Nginx directive using the hardcoded registry with atomic staging and test-before-live.
	 *
	 * Selects strictly by rule key from get_rule_registry(). Never accepts freeform directive strings.
	 *
	 * @param string $rule_key Key from get_rule_registry().
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function apply_nginx_named_rule( $rule_key ) {
		$registry = self::get_rule_registry();
		if ( ! isset( $registry[ $rule_key ] ) || empty( $registry[ $rule_key ]['nginx'] ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unknown or unsupported Nginx rule: %s', 'site-checkup-pro' ), esc_html( $rule_key ) ),
			);
		}

		$directive = $registry[ $rule_key ]['nginx'];

		// Tier 1: Hosting Panel API
		if ( self::has_nginx_tier1() ) {
			return WPSG_Hosting_Panel_Bridge::apply_directive( $rule_key, $directive );
		}

		// Tier 2: Companion Directory with atomic staging + test-before-live
		if ( self::has_nginx_tier2() && class_exists( 'WPSG_Nginx_Tier2' ) ) {
			return WPSG_Nginx_Tier2::apply_rule( $rule_key, $directive );
		}

		return array(
			'success' => false,
			'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Remove an Nginx named rule with atomic rollback on test failure.
	 *
	 * @param string $rule_key Rule key.
	 * @return array
	 */
	public static function remove_nginx_named_rule( $rule_key ) {
		if ( self::has_nginx_tier1() ) {
			return WPSG_Hosting_Panel_Bridge::remove_directive( $rule_key );
		}

		if ( self::has_nginx_tier2() && class_exists( 'WPSG_Nginx_Tier2' ) ) {
			return WPSG_Nginx_Tier2::remove_rule( $rule_key );
		}

		return array(
			'success' => true,
			'message' => __( 'Manual removal required on this hosting setup.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Check if an Nginx named rule is active.
	 *
	 * @param string $rule_key Rule key.
	 * @return bool
	 */
	public static function has_nginx_named_rule( $rule_key ) {
		if ( self::has_nginx_tier2() && class_exists( 'WPSG_Nginx_Tier2' ) ) {
			return WPSG_Nginx_Tier2::has_rule( $rule_key );
		}
		return false;
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
		}

		$htaccess_content = "<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n";
		if ( ! file_exists( $dir . '.htaccess' ) ) {
			file_put_contents( $dir . '.htaccess', $htaccess_content );
		}

		if ( ! file_exists( $dir . 'index.php' ) ) {
			file_put_contents( $dir . 'index.php', "<?php\nhttp_response_code( 403 );\nexit;\n" );
		}

		if ( ! file_exists( $dir . 'web.config' ) ) {
			file_put_contents( $dir . 'web.config', '<configuration><system.webServer><authorization><clear /><deny users="*" /></authorization></system.webServer></configuration>' );
		}

		return $dir;
	}

	/**
	 * Create a timestamped backup of the current .htaccess file with an unguessable token.
	 *
	 * @return string|false Path to backup file or false on failure.
	 */
	public static function backup_htaccess() {
		$htaccess = self::get_htaccess_path();
		if ( ! file_exists( $htaccess ) ) {
			return false;
		}

		$backup_dir  = self::get_backup_dir();
		$token       = wp_generate_password( 32, false, false );
		$backup_file = $backup_dir . 'htaccess-' . gmdate( 'Ymd-His' ) . '-' . $token . '.bak';

		if ( copy( $htaccess, $backup_file ) ) {
			return $backup_file;
		}

		return false;
	}

	/**
	 * Enable a named rule from the centralized registry.
	 *
	 * @param string $rule_key Key from get_rule_registry().
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function enable_named_rule( $rule_key ) {
		$registry = self::get_rule_registry();
		if ( ! isset( $registry[ $rule_key ] ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unknown named rule: %s', 'site-checkup-pro' ), esc_html( $rule_key ) ),
			);
		}

		$rule = $registry[ $rule_key ];

		// Pre-flight check for upload PHP execution block
		if ( ! empty( $rule['requires_preflight'] ) && 'deny_uploads_php' === $rule_key ) {
			$preflight = self::check_uploads_php_preflight();
			if ( ! $preflight['safe'] ) {
				return array(
					'success'           => false,
					'requires_warning'  => true,
					'warning_message'   => $preflight['message'],
					'message'           => $preflight['message'],
				);
			}
		}

		if ( ! self::supports_htaccess() ) {
			if ( self::has_nginx_tier1() || self::has_nginx_tier2() ) {
				return self::apply_nginx_named_rule( $rule_key );
			}
			return array(
				'success' => false,
				'is_na'   => true,
				'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ),
			);
		}

		return self::insert_rule( $rule['marker'], $rule['rules'] );
	}

	/**
	 * Disable a named rule from the centralized registry.
	 *
	 * @param string $rule_key Key from get_rule_registry().
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public static function disable_named_rule( $rule_key ) {
		$registry = self::get_rule_registry();
		if ( ! isset( $registry[ $rule_key ] ) ) {
			return array(
				'success' => false,
				'message' => sprintf( __( 'Unknown named rule: %s', 'site-checkup-pro' ), esc_html( $rule_key ) ),
			);
		}

		if ( ! self::supports_htaccess() ) {
			if ( self::has_nginx_tier1() || self::has_nginx_tier2() ) {
				return self::remove_nginx_named_rule( $rule_key );
			}
			return array(
				'success' => true,
				'message' => __( 'Manual removal required on this hosting setup.', 'site-checkup-pro' ),
			);
		}

		return self::remove_rule( $registry[ $rule_key ]['marker'] );
	}

	/**
	 * Check if a named rule is currently active.
	 *
	 * @param string $rule_key Key from get_rule_registry().
	 * @return bool
	 */
	public static function has_named_rule( $rule_key ) {
		$registry = self::get_rule_registry();
		if ( ! isset( $registry[ $rule_key ] ) ) {
			return false;
		}

		if ( ! self::supports_htaccess() ) {
			return self::has_nginx_named_rule( $rule_key );
		}

		return self::has_rule( $registry[ $rule_key ]['marker'] );
	}

	/**
	 * Get diff preview for a named rule.
	 *
	 * @param string $rule_key Key from get_rule_registry().
	 * @return array
	 */
	public static function get_named_rule_diff( $rule_key ) {
		$registry = self::get_rule_registry();
		if ( ! isset( $registry[ $rule_key ] ) ) {
			return array( 'file' => '.htaccess', 'current' => '', 'insert_block' => '' );
		}

		return self::get_diff( $registry[ $rule_key ]['marker'], $registry[ $rule_key ]['rules'] );
	}

	/**
	 * Pre-flight compatibility check for blocking PHP execution in uploads.
	 *
	 * @return array
	 */
	public static function check_uploads_php_preflight() {
		$uploads     = wp_upload_dir();
		$uploads_dir = $uploads['basedir'];
		if ( ! is_dir( $uploads_dir ) ) {
			return array( 'safe' => true, 'found' => array(), 'message' => '' );
		}

		$found = array();
		try {
			$iterator = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::LEAVES_ONLY
			);

			$count = 0;
			foreach ( $iterator as $file ) {
				if ( ++$count > 500 ) {
					break; // Bounded inspection.
				}
				if ( ! $file->isDir() ) {
					$ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
					if ( in_array( $ext, array( 'php', 'phtml', 'php5', 'phar' ), true ) ) {
						$rel = substr( $file->getPathname(), strlen( $uploads_dir ) + 1 );
						if ( 0 === strpos( $rel, 'wpsg-backups' ) ) {
							continue;
						}
						if ( 'index.php' === $file->getFilename() && $file->getSize() <= 60 ) {
							continue;
						}
						$found[] = $rel;
					}
				}
			}
		} catch ( Exception $e ) {
			// Ignore directory read errors gracefully.
		}

		return array(
			'safe'    => empty( $found ),
			'found'   => $found,
			'message' => ! empty( $found )
				? sprintf(
					/* translators: 1: count, 2: sample files */
					__( 'Compatibility Warning: %1$d existing PHP file(s) found in /uploads/ (%2$s). Blocking execution may break plugins relying on these scripts.', 'site-checkup-pro' ),
					count( $found ),
					implode( ', ', array_slice( $found, 0, 3 ) )
				)
				: __( 'No PHP files detected in /wp-content/uploads/. Safe to apply rule.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Insert or update a marker block in .htaccess with automatic backup, flock() file lock, and health check.
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
		if ( ! function_exists( 'insert_with_markers' ) ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( file_exists( ABSPATH . 'wp-admin/includes/misc.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/misc.php';
			}
		}

		// 2. Backup existing .htaccess.
		$backup_path = self::backup_htaccess();
		if ( ! $backup_path && file_exists( $htaccess_file ) ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create safety backup of .htaccess before writing.', 'site-checkup-pro' ),
			);
		}

		if ( is_string( $rules ) ) {
			$rules = explode( "\n", trim( $rules ) );
		}

		$full_marker = self::MARKER_PREFIX . $marker;

		// 3. Write via native insert_with_markers wrapped in flock() file lock.
		$lock_file = $htaccess_file . '.lock';
		$lock_fp   = @fopen( $lock_file, 'w+' );
		if ( $lock_fp ) {
			flock( $lock_fp, LOCK_EX );
		}

		try {
			$written = insert_with_markers( $htaccess_file, $full_marker, $rules );
		} finally {
			if ( $lock_fp ) {
				flock( $lock_fp, LOCK_UN );
				fclose( $lock_fp );
				@unlink( $lock_file );
			}
		}

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
	 * Remove a marker block from .htaccess with flock() file lock.
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
		if ( ! function_exists( 'insert_with_markers' ) ) {
			if ( file_exists( ABSPATH . 'wp-admin/includes/file.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}
			if ( file_exists( ABSPATH . 'wp-admin/includes/misc.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/misc.php';
			}
		}

		$backup_path = self::backup_htaccess();
		$full_marker = self::MARKER_PREFIX . $marker;

		// 3. Write via native insert_with_markers wrapped in flock() file lock.
		$lock_file = $htaccess_file . '.lock';
		$lock_fp   = @fopen( $lock_file, 'w+' );
		if ( $lock_fp ) {
			flock( $lock_fp, LOCK_EX );
		}

		try {
			$removed = insert_with_markers( $htaccess_file, $full_marker, array() );
			// Also excise any remaining empty marker block completely to keep .htaccess clean.
			if ( $removed && file_exists( $htaccess_file ) ) {
				$c       = file_get_contents( $htaccess_file );
				$pattern = '/\s*#\s*BEGIN\s+' . preg_quote( $full_marker, '/' ) . '.*?#\s*END\s+' . preg_quote( $full_marker, '/' ) . '\s*/s';
				$cleaned = preg_replace( $pattern, "\n", $c );
				if ( null !== $cleaned && $cleaned !== $c ) {
					file_put_contents( $htaccess_file, $cleaned );
				}
			}
		} finally {
			if ( $lock_fp ) {
				flock( $lock_fp, LOCK_UN );
				fclose( $lock_fp );
				@unlink( $lock_file );
			}
		}

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
	 * Check if a marker block is currently present and contains active directives in .htaccess.
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

		$pattern = '/#\s*BEGIN\s+' . preg_quote( $full_marker, '/' ) . '(.*?)#\s*END\s+' . preg_quote( $full_marker, '/' ) . '/s';
		if ( ! preg_match( $pattern, $content, $matches ) ) {
			return false;
		}

		// Check if there is actual directive content between markers (ignoring WP auto-comments & whitespace)
		$lines = explode( "\n", $matches[1] );
		foreach ( $lines as $line ) {
			$trimmed = trim( $line );
			if ( '' !== $trimmed && '#' !== $trimmed[0] ) {
				return true;
			}
		}

		return false;
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
	 * Targets only the site's configured home_url( '/' ) and verifies against 5xx errors.
	 *
	 * @return array Array with 'healthy' (bool), 'status_code' (int), 'message' (string).
	 */
	public static function run_health_check() {
		$home_url = home_url( '/' );

		$args = array(
			'timeout'     => 10,
			'redirection' => 0,
			'sslverify'   => false,
			'user-agent'  => 'SiteCheckupPro-SelfTest/1.0',
		);

		if ( isset( $_SERVER['PHP_AUTH_USER'] ) && isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			$args['headers'] = array(
				'Authorization' => 'Basic ' . base64_encode( sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) ) . ':' . sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) ) ),
			);
		}

		$response = wp_remote_head( $home_url, $args );

		if ( is_wp_error( $response ) ) {
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
