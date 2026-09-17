<?php
/**
 * Login Guard, Progressive Throttling & Honeypot Protection
 *
 * Implements atomic DB-level lockout counting, trusted-proxy header validation,
 * case-normalized SHA-256 rate keys, generic login error masking, honeypots,
 * and secondary global per-username brute-force defense.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Login_Guard
 */
class WPSG_Login_Guard {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Login_Guard|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Login_Guard
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// 1. Pre-flight lockout enforcement: priority 5 (before password validation runs).
		add_filter( 'authenticate', array( $this, 'preflight_lockout_check' ), 5, 3 );

		// 2. Failure counter: strictly on wp_login_failed hook.
		add_action( 'wp_login_failed', array( $this, 'on_login_failed' ), 10, 2 );

		// 3. Generic login error override.
		add_filter( 'login_errors', array( $this, 'mask_login_errors' ) );

		// 4. Honeypot injection.
		add_action( 'login_form', array( $this, 'inject_honeypot' ) );
		add_action( 'comment_form', array( $this, 'inject_honeypot' ) );

		// 5. Honeypot silent validation.
		add_filter( 'authenticate', array( $this, 'validate_login_honeypot' ), 1, 3 );
		add_filter( 'preprocess_comment', array( $this, 'validate_comment_honeypot' ) );
	}

	/**
	 * Get the client IP address safely, respecting trusted proxy configurations.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '127.0.0.1';
		$trusted_proxies = get_option( 'wpsg_trusted_proxies', array() );

		// If no trusted proxies configured, strictly use REMOTE_ADDR.
		if ( empty( $trusted_proxies ) || ! is_array( $trusted_proxies ) || ! in_array( $remote_addr, $trusted_proxies, true ) ) {
			return $remote_addr;
		}

		// Inspect X-Forwarded-For if REMOTE_ADDR is a verified trusted proxy.
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$forwarded = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
			// Traverse right-to-left for the first untrusted IP.
			for ( $i = count( $forwarded ) - 1; $i >= 0; $i-- ) {
				$ip = trim( $forwarded[ $i ] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) && ! in_array( $ip, $trusted_proxies, true ) ) {
					return $ip;
				}
			}
		}

		return $remote_addr;
	}

	/**
	 * Generate case-normalized SHA-256 rate limit key.
	 *
	 * @param string $ip       Client IP.
	 * @param string $username Submitted username.
	 * @return string 64-character SHA-256 hex string.
	 */
	public static function get_rate_key( $ip, $username ) {
		$clean_user = strtolower( sanitize_user( $username ) );
		return hash( 'sha256', $ip . '|' . $clean_user );
	}

	/**
	 * Pre-flight lockout enforcement on authenticate filter.
	 * Runs at priority 5 to abort before WordPress does password hashing/auth work.
	 *
	 * @param WP_User|WP_Error|null $user     User object.
	 * @param string                $username Username.
	 * @param string                $password Password.
	 * @return WP_User|WP_Error
	 */
	public function preflight_lockout_check( $user, $username, $password ) {
		if ( empty( $username ) ) {
			return $user;
		}

		$client_ip = self::get_client_ip();
		$rate_key  = self::get_rate_key( $client_ip, $username );

		$lockout = self::check_lockout( $rate_key );
		if ( $lockout['is_locked'] ) {
			$remaining_minutes = max( 1, (int) ceil( $lockout['remaining_seconds'] / 60 ) );
			return new WP_Error(
				'wpsg_locked_out',
				sprintf(
					/* translators: %d: remaining minutes */
					__( 'Too many failed login attempts. Please wait %d minute(s) before trying again.', 'site-checkup-pro' ),
					$remaining_minutes
				)
			);
		}

		// Check secondary global per-username lockout (distributed credential stuffing defense).
		$clean_user = strtolower( sanitize_user( $username ) );
		$user_key   = hash( 'sha256', 'global_user|' . $clean_user );
		$global_lock = self::check_lockout( $user_key );
		if ( $global_lock['is_locked'] ) {
			$remaining_minutes = max( 1, (int) ceil( $global_lock['remaining_seconds'] / 60 ) );
			return new WP_Error(
				'wpsg_global_locked_out',
				sprintf(
					/* translators: %d: remaining minutes */
					__( 'Account temporarily protected due to suspicious activity. Please wait %d minute(s).', 'site-checkup-pro' ),
					$remaining_minutes
				)
			);
		}

		return $user;
	}

	/**
	 * Record failed login attempt atomically in the database.
	 *
	 * @param string   $username Username.
	 * @param WP_Error $error    Failure error object.
	 */
	public function on_login_failed( $username, $error = null ) {
		if ( empty( $username ) ) {
			$username = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : 'unknown';
		}

		$client_ip = self::get_client_ip();
		self::record_failure( $client_ip, $username );
	}

	/**
	 * Atomic database record of failure with explicit lockout threshold setting.
	 *
	 * @param string $client_ip Client IP.
	 * @param string $username  Target username.
	 * @return array Lockout status.
	 */
	public static function record_failure( $client_ip, $username ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpsg_rate_limits';
		$clean_user = strtolower( sanitize_user( $username ) );
		$rate_key   = self::get_rate_key( $client_ip, $clean_user );
		$now        = current_time( 'mysql' );

		// 1. Atomic insertion or increment
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (rate_key, attempts, first_attempt, last_attempt) 
				VALUES (%s, 1, %s, %s) 
				ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = %s",
				$rate_key,
				$now,
				$now,
				$now
			)
		);

		// 2. Fetch updated attempts
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$attempts = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT attempts FROM {$table_name} WHERE rate_key = %s LIMIT 1", $rate_key )
		);

		// 3. Evaluate threshold and set locked_until explicitly
		$lock_seconds = 0;
		if ( $attempts >= 10 ) {
			$lock_seconds = 3600; // 60 minutes
		} elseif ( $attempts >= 5 ) {
			$lock_seconds = 900;  // 15 minutes
		} elseif ( $attempts >= 3 ) {
			$lock_seconds = 60;   // 1 minute
		}

		if ( $lock_seconds > 0 ) {
			$locked_until = gmdate( 'Y-m-d H:i:s', time() + $lock_seconds );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET locked_until = %s WHERE rate_key = %s",
					$locked_until,
					$rate_key
				)
			);
		}

		// 4. Secondary global per-username failure counter (distributed brute force guard)
		$user_key = hash( 'sha256', 'global_user|' . $clean_user );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (rate_key, attempts, first_attempt, last_attempt) 
				VALUES (%s, 1, %s, %s) 
				ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = %s",
				$user_key,
				$now,
				$now,
				$now
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$user_attempts = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT attempts FROM {$table_name} WHERE rate_key = %s LIMIT 1", $user_key )
		);

		if ( $user_attempts >= 50 ) {
			$locked_until = gmdate( 'Y-m-d H:i:s', time() + 1800 ); // 30 minutes
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table_name} SET locked_until = %s WHERE rate_key = %s",
					$locked_until,
					$user_key
				)
			);

			// Trigger security alert for distributed credential-stuffing attack
			if ( class_exists( 'WPSG_Alert_Dispatcher' ) ) {
				WPSG_Alert_Dispatcher::dispatch(
					'distributed_attack',
					sprintf(
						__( 'Distributed brute-force attack detected on username "%s" (50+ failed attempts). Account temporarily protected.', 'site-checkup-pro' ),
						$clean_user
					),
					array( 'target_user' => $clean_user, 'total_attempts' => $user_attempts )
				);
			}
		}

		// 5. Redacted audit log entry (STRICTLY NEVER LOG PASSWORDS)
		if ( class_exists( 'WPSG_Audit_Log' ) ) {
			WPSG_Audit_Log::log(
				'login_guard',
				'failed_attempt',
				null,
				array( 'ip' => $client_ip, 'user' => $clean_user, 'attempts' => $attempts ),
				$lock_seconds > 0 ? 'failed' : 'attention',
				$lock_seconds > 0
					? sprintf( __( 'IP %1$s locked out for %2$d seconds after %3$d failed login attempts.', 'site-checkup-pro' ), $client_ip, $lock_seconds, $attempts )
					: sprintf( __( 'Failed login recorded for user "%1$s" from %2$s.', 'site-checkup-pro' ), $clean_user, $client_ip )
			);
		}

		return array(
			'attempts'     => $attempts,
			'locked'       => $lock_seconds > 0,
			'lock_seconds' => $lock_seconds,
		);
	}

	/**
	 * Check if a rate key is currently locked out.
	 *
	 * @param string $rate_key 64-char SHA-256 hash.
	 * @return array
	 */
	public static function check_lockout( $rate_key ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpsg_rate_limits';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT attempts, locked_until FROM {$table_name} WHERE rate_key = %s LIMIT 1",
				$rate_key
			)
		);

		if ( ! $row || empty( $row->locked_until ) ) {
			return array( 'is_locked' => false, 'remaining_seconds' => 0 );
		}

		$locked_until_ts = strtotime( $row->locked_until );
		$now_ts          = time();

		if ( $locked_until_ts > $now_ts ) {
			return array(
				'is_locked'         => true,
				'remaining_seconds' => ( $locked_until_ts - $now_ts ),
				'attempts'          => (int) $row->attempts,
			);
		}

		return array( 'is_locked' => false, 'remaining_seconds' => 0 );
	}

	/**
	 * Generic rate limiter helper (used for REST /reauth and /csp-report).
	 *
	 * @param string $action_key Unique key.
	 * @param int    $max_limit  Max attempts allowed.
	 * @param int    $window_sec Time window in seconds.
	 * @return bool True if permitted, false if rate limited.
	 */
	public static function check_rate_limit( $action_key, $max_limit = 10, $window_sec = 60 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpsg_rate_limits';
		$rate_key   = hash( 'sha256', 'rl_' . $action_key );
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT attempts, first_attempt FROM {$table_name} WHERE rate_key = %s LIMIT 1",
				$rate_key
			)
		);

		if ( ! $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table_name,
				array(
					'rate_key'      => $rate_key,
					'attempts'      => 1,
					'first_attempt' => $now,
					'last_attempt'  => $now,
				),
				array( '%s', '%d', '%s', '%s' )
			);
			return true;
		}

		$window_start = strtotime( $row->first_attempt );
		if ( ( time() - $window_start ) > $window_sec ) {
			// Window elapsed: reset counter
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->update(
				$table_name,
				array(
					'attempts'      => 1,
					'first_attempt' => $now,
					'last_attempt'  => $now,
					'locked_until'  => null,
				),
				array( 'rate_key' => $rate_key )
			);
			return true;
		}

		if ( (int) $row->attempts >= $max_limit ) {
			return false; // Exceeded
		}

		// Increment
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} SET attempts = attempts + 1, last_attempt = %s WHERE rate_key = %s",
				$now,
				$rate_key
			)
		);

		return true;
	}

	/**
	 * Prune old expired rate limits. Scheduled daily via WP-Cron.
	 *
	 * @return int Number of pruned rows.
	 */
	public static function prune_old_rate_limits() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpsg_rate_limits';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = $wpdb->query(
			"DELETE FROM {$table_name} 
			WHERE last_attempt < DATE_SUB(NOW(), INTERVAL 1 DAY) 
			AND (locked_until IS NULL OR locked_until < NOW())"
		);

		return (int) $deleted;
	}

	/**
	 * Override WordPress default login error messages with a single generic string.
	 *
	 * @param string $errors Error HTML string.
	 * @return string
	 */
	public function mask_login_errors( $errors ) {
		if ( ! empty( $errors ) && false === strpos( $errors, 'wpsg_locked_out' ) ) {
			return '<strong>' . esc_html__( 'Error:', 'site-checkup-pro' ) . '</strong> ' . esc_html__( 'Invalid username or password.', 'site-checkup-pro' );
		}
		return $errors;
	}

	/**
	 * Inject hidden honeypot field into login and comment forms.
	 */
	public function inject_honeypot() {
		echo '<input type="text" name="wpsg_hp_field" value="" style="display:none !important; position:absolute !important; left:-9999px !important;" tabindex="-1" autocomplete="off" aria-hidden="true" />';
	}

	/**
	 * Validate login honeypot field silently.
	 *
	 * @param WP_User|WP_Error|null $user User.
	 * @return WP_User|WP_Error
	 */
	public function validate_login_honeypot( $user ) {
		if ( ! empty( $_POST['wpsg_hp_field'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Bot filled honeypot! Terminate silently without error message.
			status_header( 200 );
			exit;
		}
		return $user;
	}

	/**
	 * Validate comment honeypot field silently.
	 *
	 * @param array $commentdata Comment data.
	 * @return array
	 */
	public function validate_comment_honeypot( $commentdata ) {
		if ( ! empty( $_POST['wpsg_hp_field'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			// Silently drop spam submission.
			status_header( 200 );
			exit;
		}
		return $commentdata;
	}
}
