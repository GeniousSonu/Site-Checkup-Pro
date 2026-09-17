<?php
/**
 * Scanner with Baseline Drift Detection & Server Auditing
 *
 * Checks for rogue admins, wp_options tampering, mu-plugins, exposed backup files,
 * file permissions (640 wp-config), PHP restrictions, DB prefix, TLS depth, and domain email health.
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
 * Class WPSG_Scanner
 */
class WPSG_Scanner {

	/**
	 * Baseline option name.
	 */
	const BASELINE_OPTION = 'wpsg_trusted_baseline';

	/**
	 * Take an initial baseline snapshot upon plugin activation.
	 *
	 * @return bool
	 */
	public static function take_initial_baseline() {
		// Only take baseline if one doesn't exist yet.
		if ( false !== get_option( self::BASELINE_OPTION ) ) {
			return false;
		}

		return self::update_baseline();
	}

	/**
	 * Snapshot current trusted administrators and options as the accepted baseline.
	 *
	 * @return bool
	 */
	public static function update_baseline() {
		$admins = self::get_current_admins();

		$baseline = array(
			'admins'          => $admins,
			'siteurl'         => get_option( 'siteurl' ),
			'home'            => get_option( 'home' ),
			'mu_plugins'      => self::get_mu_plugins_list(),
			'created_at'      => current_time( 'mysql' ),
			'created_by_user' => get_current_user_id(),
		);

		return update_option( self::BASELINE_OPTION, $baseline );
	}

	/**
	 * Get baseline snapshot data.
	 *
	 * @return array
	 */
	public static function get_baseline() {
		$baseline = get_option( self::BASELINE_OPTION );
		return is_array( $baseline ) ? $baseline : array();
	}

	/**
	 * Get list of current administrator user accounts.
	 *
	 * @return array
	 */
	public static function get_current_admins() {
		$users = get_users( array(
			'role'   => 'administrator',
			'fields' => array( 'ID', 'user_login', 'user_email', 'user_registered', 'display_name' ),
		) );

		$admins = array();
		foreach ( $users as $u ) {
			$admins[ (int) $u->ID ] = array(
				'id'         => (int) $u->ID,
				'login'      => $u->user_login,
				'email'      => $u->user_email,
				'registered' => $u->user_registered,
			);
		}

		return $admins;
	}

	/**
	 * Scan for rogue or unapproved administrator users (drift from baseline).
	 *
	 * @return array
	 */
	public static function scan_rogue_admins() {
		$baseline = self::get_baseline();
		$current  = self::get_current_admins();

		if ( empty( $baseline['admins'] ) ) {
			// If no baseline recorded yet, initialize now.
			self::update_baseline();
			return array(
				'status'       => 'done',
				'rogue_admins' => array(),
				'message'      => sprintf(
					/* translators: %d: count of admins */
					__( 'Baseline initialized with %d recognized administrator(s).', 'site-checkup-pro' ),
					count( $current )
				),
			);
		}

		$trusted_admins = $baseline['admins'];
		$rogue_admins   = array();

		foreach ( $current as $user_id => $admin_data ) {
			if ( ! isset( $trusted_admins[ $user_id ] ) ) {
				// Brand new admin account created since baseline.
				$rogue_admins[] = array_merge( $admin_data, array( 'reason' => __( 'Created after baseline snapshot', 'site-checkup-pro' ) ) );
			} elseif ( $trusted_admins[ $user_id ]['email'] !== $admin_data['email'] ) {
				// Admin email was altered.
				$rogue_admins[] = array_merge( $admin_data, array( 'reason' => __( 'Email address was changed', 'site-checkup-pro' ) ) );
			}
		}

		if ( ! empty( $rogue_admins ) ) {
			return array(
				'status'       => 'attention',
				'rogue_admins' => $rogue_admins,
				'message'      => sprintf(
					/* translators: %d: count of untrusted admins */
					__( 'Warning: %d untrusted administrator account(s) detected since last baseline!', 'site-checkup-pro' ),
					count( $rogue_admins )
				),
			);
		}

		return array(
			'status'       => 'done',
			'rogue_admins' => array(),
			'message'      => sprintf(
				/* translators: %d: count of admins */
				__( 'All %d administrator accounts match the verified baseline.', 'site-checkup-pro' ),
				count( $current )
			),
		);
	}

	/**
	 * Check wp_options integrity for siteurl/home tampering and oversized autoloaded options.
	 *
	 * @return array
	 */
	public static function scan_options_integrity() {
		global $wpdb;

		$baseline = self::get_baseline();
		$issues   = array();

		$current_siteurl = get_option( 'siteurl' );
		$current_home    = get_option( 'home' );

		// 1. Check URL drift against baseline.
		if ( ! empty( $baseline['siteurl'] ) && $baseline['siteurl'] !== $current_siteurl ) {
			$issues[] = sprintf(
				/* translators: 1: original URL, 2: current URL */
				__( 'siteurl changed from %1$s to %2$s', 'site-checkup-pro' ),
				esc_url( $baseline['siteurl'] ),
				esc_url( $current_siteurl )
			);
		}

		if ( ! empty( $baseline['home'] ) && $baseline['home'] !== $current_home ) {
			$issues[] = sprintf(
				/* translators: 1: original URL, 2: current URL */
				__( 'home URL changed from %1$s to %2$s', 'site-checkup-pro' ),
				esc_url( $baseline['home'] ),
				esc_url( $current_home )
			);
		}

		// 2. Check for suspicious oversized autoloaded options (> 100 KB).
		$large_options = array();
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
			$options_table = isset( $wpdb->options ) ? $wpdb->options : ( isset( $wpdb->prefix ) ? $wpdb->prefix . 'options' : 'wp_options' );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$large_autoload = $wpdb->get_results(
				"SELECT option_name, LENGTH(option_value) AS size_bytes 
				FROM {$options_table} 
				WHERE autoload = 'yes' AND LENGTH(option_value) > 102400 
				ORDER BY size_bytes DESC LIMIT 10"
			);

			if ( ! empty( $large_autoload ) && is_array( $large_autoload ) ) {
				foreach ( $large_autoload as $opt ) {
					if ( is_object( $opt ) && isset( $opt->option_name, $opt->size_bytes ) ) {
						$large_options[] = sprintf( '%s (%s KB)', $opt->option_name, round( $opt->size_bytes / 1024 ) );
					}
				}
			}
		}

		if ( ! empty( $issues ) ) {
			return array(
				'status'  => 'failed',
				'issues'  => $issues,
				'message' => implode( '; ', $issues ),
			);
		}

		if ( ! empty( $large_options ) ) {
			return array(
				'status'  => 'attention',
				'message' => sprintf(
					/* translators: %s: list of options */
					__( 'Core URLs match baseline. Note: %d oversized autoloaded options found (>100KB): %s', 'site-checkup-pro' ),
					count( $large_options ),
					implode( ', ', array_slice( $large_options, 0, 3 ) )
				),
			);
		}

		return array(
			'status'  => 'done',
			'message' => __( 'siteurl and home match baseline. No suspicious autoload bloat detected.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Get list of files in wp-content/mu-plugins.
	 *
	 * @return array
	 */
	public static function get_mu_plugins_list() {
		$mu_dir = defined( 'WPMU_PLUGIN_DIR' ) ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
		if ( ! is_dir( $mu_dir ) ) {
			return array();
		}

		$files = scandir( $mu_dir );
		$list  = array();

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file || is_dir( $mu_dir . '/' . $file ) ) {
				continue;
			}
			if ( 'php' === pathinfo( $file, PATHINFO_EXTENSION ) ) {
				$list[ $file ] = md5_file( $mu_dir . '/' . $file );
			}
		}

		return $list;
	}

	/**
	 * Scan mu-plugins directory.
	 *
	 * @return array
	 */
	public static function scan_mu_plugins() {
		$current  = self::get_mu_plugins_list();
		$baseline = self::get_baseline();

		if ( empty( $current ) ) {
			return array(
				'status'  => 'done',
				'files'   => array(),
				'message' => __( 'No mu-plugins installed in wp-content/mu-plugins.', 'site-checkup-pro' ),
			);
		}

		$unrecognized = array();
		$trusted_mu   = isset( $baseline['mu_plugins'] ) ? $baseline['mu_plugins'] : array();

		foreach ( $current as $filename => $hash ) {
			if ( ! isset( $trusted_mu[ $filename ] ) ) {
				$unrecognized[] = $filename;
			}
		}

		if ( ! empty( $unrecognized ) ) {
			return array(
				'status'  => 'attention',
				'files'   => array_keys( $current ),
				'message' => sprintf(
					/* translators: 1: count of total files, 2: list of new files */
					__( '%1$d mu-plugin(s) active. New/unrecognized files found since baseline: %2$s', 'site-checkup-pro' ),
					count( $current ),
					implode( ', ', $unrecognized )
				),
			);
		}

		return array(
			'status'  => 'done',
			'files'   => array_keys( $current ),
			'message' => sprintf(
				/* translators: %d: count of files */
				__( '%d mu-plugin(s) active and verified against baseline.', 'site-checkup-pro' ),
				count( $current )
			),
		);
	}

	/**
	 * Scan webroot for exposed backup and sensitive dump files.
	 *
	 * @return array
	 */
	public static function scan_exposed_files() {
		$webroot = ABSPATH;
		$targets = array(
			'.env',
			'.git',
			'.gitignore',
			'wp-config.php.bak',
			'wp-config.old',
			'wp-config.txt',
			'wp-config.php~',
			'backup.sql',
			'dump.sql',
			'database.sql',
			'db.sql',
			'data.sql',
			'backup.zip',
			'site.tar.gz',
		);

		$found = array();
		foreach ( $targets as $target ) {
			$path = $webroot . $target;
			if ( file_exists( $path ) ) {
				$found[] = $target;
			}
		}

		// Also check for any *.sql files in webroot.
		$sql_files = glob( $webroot . '*.sql' );
		if ( is_array( $sql_files ) ) {
			foreach ( $sql_files as $sql_path ) {
				$base = basename( $sql_path );
				if ( ! in_array( $base, $found, true ) ) {
					$found[] = $base;
				}
			}
		}

		if ( ! empty( $found ) ) {
			return array(
				'status' => 'failed',
				'found'  => $found,
				'message' => sprintf(
					/* translators: %s: list of exposed files */
					__( 'Critical: %d exposed backup/sensitive file(s) found in webroot: %s', 'site-checkup-pro' ),
					count( $found ),
					implode( ', ', $found )
				),
			);
		}

		return array(
			'status'  => 'done',
			'found'   => array(),
			'message' => __( 'No exposed backup files, database dumps, or .env files found in webroot.', 'site-checkup-pro' ),
		);
	}

	/**
	 * System environment checks: PHP version, WordPress version, and SSL certificate.
	 *
	 * @return array
	 */
	public static function check_system_environment() {
		global $wp_version;

		$php_version = PHP_VERSION;
		$ssl_expiry  = self::get_ssl_expiry_days();
		$issues      = array();

		// Check PHP.
		if ( version_compare( $php_version, '7.4', '<' ) ) {
			$issues[] = sprintf( __( 'PHP %s is critically outdated and unsupported.', 'site-checkup-pro' ), $php_version );
		} elseif ( version_compare( $php_version, '8.1', '<' ) ) {
			$issues[] = sprintf( __( 'PHP %s has reached end-of-life. Upgrade to PHP 8.1+ recommended.', 'site-checkup-pro' ), $php_version );
		}

		// Check SSL.
		$is_ssl = is_ssl();
		if ( ! $is_ssl ) {
			$issues[] = __( 'Site is not running over HTTPS/SSL.', 'site-checkup-pro' );
		} elseif ( false !== $ssl_expiry && $ssl_expiry <= 14 ) {
			$issues[] = sprintf( __( 'SSL certificate expires in %d day(s)!', 'site-checkup-pro' ), $ssl_expiry );
		}

		$status = empty( $issues ) ? 'done' : ( count( $issues ) > 1 ? 'failed' : 'attention' );
		$msg    = sprintf(
			/* translators: 1: WP version, 2: PHP version, 3: SSL info */
			__( 'WordPress %1$s, PHP %2$s. SSL: %3$s.', 'site-checkup-pro' ),
			$wp_version,
			$php_version,
			$is_ssl ? ( false !== $ssl_expiry ? sprintf( __( 'Valid (%d days remaining)', 'site-checkup-pro' ), $ssl_expiry ) : __( 'Active', 'site-checkup-pro' ) ) : __( 'Inactive', 'site-checkup-pro' )
		);

		if ( ! empty( $issues ) ) {
			$msg .= ' ' . implode( ' ', $issues );
		}

		return array(
			'status'      => $status,
			'wp_version'  => $wp_version,
			'php_version' => $php_version,
			'ssl_expiry'  => $ssl_expiry,
			'message'     => $msg,
		);
	}

	/**
	 * Estimate SSL certificate days remaining.
	 *
	 * @return int|false
	 */
	public static function get_ssl_expiry_days() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host ) {
			return false;
		}

		$context = stream_context_create( array(
			'ssl' => array(
				'capture_peer_cert' => true,
				'verify_peer'       => false,
				'verify_peer_name'  => false,
			),
		) );

		$client = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context );
		if ( ! $client ) {
			return false;
		}

		$params = stream_context_get_params( $client );
		fclose( $client );

		if ( empty( $params['options']['ssl']['peer_certificate'] ) ) {
			return false;
		}

		$cert = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
		if ( ! isset( $cert['validTo_time_t'] ) ) {
			return false;
		}

		$days = (int) round( ( $cert['validTo_time_t'] - time() ) / 86400 );
		return max( 0, $days );
	}

	/**
	 * Audit file and directory permissions against strict security baselines.
	 * Strict baseline: wp-config.php <= 0640 (or 0600), other root files <= 0644, directories <= 0755.
	 * Strictly flags any world-writable bit (& 0002).
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array
	 */
	public static function audit_file_permissions( $force_refresh = false ) {
		$cache_key = 'wpsg_file_perms_cache';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$webroot = ABSPATH;
		$issues  = array();

		// 1. Audit wp-config.php (Strict baseline: 0640 or 0600)
		$config_path = WPSG_Wp_Config_Manager::get_config_path();
		if ( $config_path && file_exists( $config_path ) ) {
			$perms       = fileperms( $config_path ) & 0777;
			$perms_octal = sprintf( '%04o', $perms );

			if ( ( $perms & 0002 ) !== 0 ) {
				$issues[] = array(
					'path'     => 'wp-config.php',
					'perms'    => $perms_octal,
					'severity' => 'critical',
					'message'  => sprintf( __( 'Critical: wp-config.php is world-writable (%s). Permissions must be 0640 or 0600.', 'site-checkup-pro' ), $perms_octal ),
				);
			} elseif ( $perms > 0640 ) {
				$issues[] = array(
					'path'     => 'wp-config.php',
					'perms'    => $perms_octal,
					'severity' => 'attention',
					'message'  => sprintf( __( 'Warning: wp-config.php permissions (%s) exceed recommended baseline (0640 or 0600).', 'site-checkup-pro' ), $perms_octal ),
				);
			}
		}

		// 2. Audit Key Root Files (Baseline: <= 0644; flag 0755/0777 or world-writable)
		$root_files = array(
			'.htaccess'       => WPSG_Htaccess_Manager::get_htaccess_path(),
			'index.php'       => $webroot . 'index.php',
			'wp-login.php'    => $webroot . 'wp-login.php',
			'wp-cron.php'     => $webroot . 'wp-cron.php',
			'wp-settings.php' => $webroot . 'wp-settings.php',
		);

		foreach ( $root_files as $name => $path ) {
			if ( file_exists( $path ) ) {
				$perms       = fileperms( $path ) & 0777;
				$perms_octal = sprintf( '%04o', $perms );

				if ( ( $perms & 0002 ) !== 0 ) {
					$issues[] = array(
						'path'     => $name,
						'perms'    => $perms_octal,
						'severity' => 'critical',
						'message'  => sprintf( __( 'Critical: %1$s is world-writable (%2$s). Target baseline is 0644.', 'site-checkup-pro' ), $name, $perms_octal ),
					);
				} elseif ( ( $perms & 0111 ) !== 0 || $perms > 0644 ) {
					// Flag execution bit on root files or permissions above 0644 (e.g. 0755 on files)
					$issues[] = array(
						'path'     => $name,
						'perms'    => $perms_octal,
						'severity' => 'attention',
						'message'  => sprintf( __( 'Warning: File %1$s has executable/relaxed permissions (%2$s). Target baseline is 0644.', 'site-checkup-pro' ), $name, $perms_octal ),
					);
				}
			}
		}

		// 3. Audit Key Directories (Baseline: <= 0755; flag 0777 or world-writable)
		$uploads   = wp_upload_dir();
		$dirs_to_check = array(
			'wp-content'         => WP_CONTENT_DIR,
			'wp-content/plugins' => WP_PLUGIN_DIR,
			'wp-content/themes'  => get_theme_root(),
			'wp-content/uploads' => $uploads['basedir'],
			'wp-admin'           => $webroot . 'wp-admin',
			'wp-includes'        => $webroot . WPINC,
		);

		foreach ( $dirs_to_check as $label => $dir_path ) {
			if ( is_dir( $dir_path ) ) {
				$perms       = fileperms( $dir_path ) & 0777;
				$perms_octal = sprintf( '%04o', $perms );

				if ( ( $perms & 0002 ) !== 0 || $perms > 0755 ) {
					$issues[] = array(
						'path'     => $label,
						'perms'    => $perms_octal,
						'severity' => ( ( $perms & 0002 ) !== 0 ) ? 'critical' : 'attention',
						'message'  => sprintf( __( 'Directory %1$s has relaxed/world-writable permissions (%2$s). Baseline must be 0755 or stricter.', 'site-checkup-pro' ), $label, $perms_octal ),
					);
				}
			}
		}

		$has_critical = false;
		foreach ( $issues as $issue ) {
			if ( 'critical' === $issue['severity'] ) {
				$has_critical = true;
				break;
			}
		}

		$status = empty( $issues ) ? 'done' : ( $has_critical ? 'failed' : 'attention' );
		$msg    = empty( $issues )
			? __( 'File permissions verified against strict baselines (wp-config <= 0640, files 0644, directories 0755).', 'site-checkup-pro' )
			: sprintf(
				/* translators: %d: issue count */
				__( '%d permission anomaly(s) detected across monitored files and directories.', 'site-checkup-pro' ),
				count( $issues )
			);

		$result = array(
			'status'       => $status,
			'issues'       => $issues,
			'message'      => $msg,
			'last_checked' => current_time( 'mysql' ),
		);

		set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Detect hosting PHP restrictions (disable_functions and open_basedir).
	 * Report-only Level A check.
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array
	 */
	public static function check_php_server_restrictions( $force_refresh = false ) {
		$cache_key = 'wpsg_php_restrictions_cache';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$disabled_str = (string) ini_get( 'disable_functions' );
		$disabled_arr = array_filter( array_map( 'trim', explode( ',', $disabled_str ) ) );

		$dangerous_functions = array( 'exec', 'shell_exec', 'passthru', 'system', 'proc_open', 'popen' );
		$unprotected         = array();

		foreach ( $dangerous_functions as $func ) {
			if ( ! in_array( $func, $disabled_arr, true ) ) {
				$unprotected[] = $func;
			}
		}

		$open_basedir = ini_get( 'open_basedir' );
		$is_obd_set   = ! empty( $open_basedir );

		$warnings = array();
		if ( ! empty( $unprotected ) ) {
			$warnings[] = sprintf(
				/* translators: %s: list of dangerous functions */
				__( 'Dangerous shell execution functions are active in php.ini: %s.', 'site-checkup-pro' ),
				implode( ', ', $unprotected )
			);
		}

		if ( ! $is_obd_set ) {
			$warnings[] = __( 'open_basedir is not configured in php.ini, allowing filesystem traversal outside site root if a breach occurs.', 'site-checkup-pro' );
		}

		$status = empty( $warnings ) ? 'done' : 'attention';
		$msg    = empty( $warnings )
			? __( 'Server php.ini restrictions verified: critical execution functions disabled and open_basedir active.', 'site-checkup-pro' )
			: implode( ' ', $warnings );

		$result = array(
			'status'             => $status,
			'disabled_functions' => $disabled_arr,
			'unprotected'        => $unprotected,
			'open_basedir_set'   => $is_obd_set,
			'open_basedir_value' => $open_basedir,
			'message'            => $msg,
			'last_checked'       => current_time( 'mysql' ),
		);

		set_transient( $cache_key, $result, 24 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Check database table prefix (flag if still using default wp_).
	 * Report-only Level A check.
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array
	 */
	public static function check_db_prefix( $force_refresh = false ) {
		$cache_key = 'wpsg_db_prefix_cache';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		global $wpdb;
		$prefix     = $wpdb->prefix;
		$is_default = ( 'wp_' === $prefix );

		$status  = $is_default ? 'attention' : 'done';
		$message = $is_default
			? sprintf( __( 'Database tables use the default prefix "%s". A customized prefix reduces automated SQLi payload targeting.', 'site-checkup-pro' ), $prefix )
			: sprintf( __( 'Database prefix is customized ("%s"), offering resistance to automated generic table targeting.', 'site-checkup-pro' ), $prefix );

		$result = array(
			'status'       => $status,
			'prefix'       => $prefix,
			'is_default'   => $is_default,
			'message'      => $message,
			'last_checked' => current_time( 'mysql' ),
		);

		set_transient( $cache_key, $result, 24 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Check front-end database and PHP error display (WP_DEBUG_DISPLAY).
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array
	 */
	public static function check_wp_debug_display( $force_refresh = false ) {
		$cache_key = 'wpsg_debug_display_cache';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$debug_display_const = defined( 'WP_DEBUG_DISPLAY' ) ? WP_DEBUG_DISPLAY : null;
		$display_errors_ini  = ini_get( 'display_errors' );
		$is_display_on       = ( true === $debug_display_const || '1' === $display_errors_ini || 'on' === strtolower( (string) $display_errors_ini ) );

		$status  = $is_display_on ? 'attention' : 'done';
		$message = $is_display_on
			? __( 'WP_DEBUG_DISPLAY or display_errors is enabled, exposing database errors and stack traces to visitors.', 'site-checkup-pro' )
			: __( 'Front-end debug display is disabled (WP_DEBUG_DISPLAY off), preventing sensitive database leakage.', 'site-checkup-pro' );

		$result = array(
			'status'         => $status,
			'is_display_on'  => $is_display_on,
			'debug_display'  => $debug_display_const,
			'display_errors' => $display_errors_ini,
			'message'        => $message,
			'last_checked'   => current_time( 'mysql' ),
		);

		set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Deep TLS Protocol & Certificate Chain Depth Probe.
	 * Attempts separate connections forcing each protocol to detect if weak TLS 1.0 or 1.1 are still accepted.
	 * Also validates intermediate certificate presence in the peer cert chain.
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array
	 */
	public static function check_tls_and_cert_depth( $force_refresh = false ) {
		$cache_key = 'wpsg_tls_depth_cache';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host || ! is_ssl() ) {
			$result = array(
				'status'       => 'failed',
				'message'      => __( 'Site is not running over HTTPS. SSL/TLS depth inspection requires an active SSL connection.', 'site-checkup-pro' ),
				'last_checked' => current_time( 'mysql' ),
			);
			set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );
			return $result;
		}

		// 1. Inspect Certificate Chain Completeness
		$context = stream_context_create( array(
			'ssl' => array(
				'capture_peer_cert'       => true,
				'capture_peer_cert_chain' => true,
				'verify_peer'             => false,
				'verify_peer_name'        => false,
			),
		) );

		$client = @stream_socket_client( 'ssl://' . $host . ':443', $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $context );
		$chain_count = 0;
		$cert_days   = false;

		if ( $client ) {
			$params = stream_context_get_params( $client );
			fclose( $client );

			if ( ! empty( $params['options']['ssl']['peer_certificate_chain'] ) && is_array( $params['options']['ssl']['peer_certificate_chain'] ) ) {
				$chain_count = count( $params['options']['ssl']['peer_certificate_chain'] );
			}

			if ( ! empty( $params['options']['ssl']['peer_certificate'] ) ) {
				$cert = openssl_x509_parse( $params['options']['ssl']['peer_certificate'] );
				if ( isset( $cert['validTo_time_t'] ) ) {
					$cert_days = max( 0, (int) round( ( $cert['validTo_time_t'] - time() ) / 86400 ) );
				}
			}
		}

		// 2. Multi-Protocol Separate Connection Probes
		$protocol_methods = array();
		if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT' ) ) {
			$protocol_methods['TLSv1.0'] = STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
		}
		if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT' ) ) {
			$protocol_methods['TLSv1.1'] = STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
		}
		if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT' ) ) {
			$protocol_methods['TLSv1.2'] = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
		}
		if ( defined( 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT' ) ) {
			$protocol_methods['TLSv1.3'] = STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
		}

		$accepted_protocols = array();
		foreach ( $protocol_methods as $label => $crypto_flag ) {
			$tcp_client = @stream_socket_client( 'tcp://' . $host . ':443', $err_no, $err_str, 3, STREAM_CLIENT_CONNECT );
			if ( $tcp_client ) {
				stream_set_timeout( $tcp_client, 3 );
				$crypto_ok = @stream_socket_enable_crypto( $tcp_client, true, $crypto_flag );
				if ( true === $crypto_ok ) {
					$accepted_protocols[] = $label;
				}
				fclose( $tcp_client );
			}
		}

		$issues = array();
		if ( in_array( 'TLSv1.0', $accepted_protocols, true ) || in_array( 'TLSv1.1', $accepted_protocols, true ) ) {
			$issues[] = sprintf(
				/* translators: %s: accepted weak protocols */
				__( 'Insecure legacy protocols (%s) are still accepted by the server. Disable TLS 1.0 and 1.1 at the server/CDN level.', 'site-checkup-pro' ),
				implode( ', ', array_intersect( $accepted_protocols, array( 'TLSv1.0', 'TLSv1.1' ) ) )
			);
		}

		if ( $chain_count === 1 ) {
			$issues[] = __( 'Incomplete certificate chain detected (missing intermediate CA certificate). May cause trust errors on mobile/older clients.', 'site-checkup-pro' );
		}

		if ( false !== $cert_days && $cert_days <= 14 ) {
			$issues[] = sprintf( __( 'SSL certificate expires in %d day(s).', 'site-checkup-pro' ), $cert_days );
		}

		$status = empty( $issues ) ? 'done' : 'attention';
		$msg    = empty( $issues )
			? sprintf(
				/* translators: 1: days remaining, 2: accepted protocols */
				__( 'TLS configuration verified: Strong protocols active (%2$s), complete certificate chain (%1$d certs).', 'site-checkup-pro' ),
				$chain_count,
				implode( ', ', $accepted_protocols )
			)
			: implode( ' ', $issues );

		$result = array(
			'status'             => $status,
			'accepted_protocols' => $accepted_protocols,
			'chain_count'        => $chain_count,
			'cert_days'          => $cert_days,
			'message'            => $msg,
			'last_checked'       => current_time( 'mysql' ),
		);

		set_transient( $cache_key, $result, 12 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Informational domain email authentication check (SPF, DKIM, DMARC).
	 * Label: Informational / best-effort (never false red failure).
	 *
	 * @param bool $force_refresh Whether to bypass transient cache.
	 * @return array
	 */
	public static function check_domain_email_auth( $force_refresh = false ) {
		$cache_key = 'wpsg_email_auth_cache';
		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! $host ) {
			$admin_email = get_option( 'admin_email' );
			$parts       = explode( '@', (string) $admin_email );
			$host        = isset( $parts[1] ) ? $parts[1] : '';
		}

		// Strip www. prefix for root domain DNS inspection
		$domain = preg_replace( '/^www\./i', '', strtolower( (string) $host ) );

		$has_spf   = false;
		$has_dmarc = false;
		$spf_rec   = '';
		$dmarc_rec = '';

		if ( ! empty( $domain ) && function_exists( 'dns_get_record' ) ) {
			// Query domain TXT records for SPF
			$txt_records = @dns_get_record( $domain, DNS_TXT );
			if ( is_array( $txt_records ) ) {
				foreach ( $txt_records as $rec ) {
					if ( ! empty( $rec['txt'] ) && 0 === strpos( strtolower( trim( $rec['txt'] ) ), 'v=spf1' ) ) {
						$has_spf = true;
						$spf_rec = $rec['txt'];
						break;
					}
				}
			}

			// Query _dmarc.{domain} for DMARC
			$dmarc_records = @dns_get_record( '_dmarc.' . $domain, DNS_TXT );
			if ( is_array( $dmarc_records ) ) {
				foreach ( $dmarc_records as $rec ) {
					if ( ! empty( $rec['txt'] ) && 0 === strpos( strtolower( trim( $rec['txt'] ) ), 'v=dmarc1' ) ) {
						$has_dmarc = true;
						$dmarc_rec = $rec['txt'];
						break;
					}
				}
			}
		}

		// Informational status determination
		$notes = array();
		if ( $has_spf ) {
			$notes[] = __( 'SPF record found.', 'site-checkup-pro' );
		} else {
			$notes[] = __( 'No SPF record detected on root domain.', 'site-checkup-pro' );
		}

		if ( $has_dmarc ) {
			$notes[] = __( 'DMARC policy active.', 'site-checkup-pro' );
		} else {
			$notes[] = __( 'No DMARC policy found.', 'site-checkup-pro' );
		}

		$msg = sprintf(
			/* translators: 1: domain, 2: notes */
			__( 'Domain email health for %1$s: %2$s (Note: Custom DKIM selectors cannot be discovered via domain scanning. If mail is handled via third-party relays like SendGrid or Google Workspace, confirm their DNS records are configured.)', 'site-checkup-pro' ),
			$domain,
			implode( ' ', $notes )
		);

		$result = array(
			'status'       => 'attention', // Informational advisory state, not a failed badge
			'is_info_only' => true,
			'domain'       => $domain,
			'has_spf'      => $has_spf,
			'spf_record'   => $spf_rec,
			'has_dmarc'    => $has_dmarc,
			'dmarc_record' => $dmarc_rec,
			'message'      => $msg,
			'last_checked' => current_time( 'mysql' ),
		);

		set_transient( $cache_key, $result, 24 * HOUR_IN_SECONDS );
		return $result;
	}
}
