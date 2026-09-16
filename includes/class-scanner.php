<?php
/**
 * Scanner with Baseline Drift Detection
 *
 * Checks for rogue admins, wp_options tampering, mu-plugins, exposed backup files, and system health.
 * Alerts only on drift from an accepted baseline.
 *
 * @package SiteCheckupPro
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$large_autoload = $wpdb->get_results(
			"SELECT option_name, LENGTH(option_value) AS size_bytes 
			FROM {$wpdb->options} 
			WHERE autoload = 'yes' AND LENGTH(option_value) > 102400 
			ORDER BY size_bytes DESC LIMIT 10"
		);

		$large_options = array();
		if ( ! empty( $large_autoload ) ) {
			foreach ( $large_autoload as $opt ) {
				$large_options[] = sprintf( '%s (%s KB)', $opt->option_name, round( $opt->size_bytes / 1024 ) );
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
}
