<?php
/**
* Session Manager & Re-Authentication Guard
 *
 * Provides core WP_Session_Tokens governance, cross-user IDOR protection,
 * auto-invalidation on password reset, and session-bound single-use re-auth tokens.
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
 * Class WPSG_Session_Manager
 */
class WPSG_Session_Manager {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Session_Manager|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Session_Manager
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
		// Invalidate all other sessions when user updates password.
		add_action( 'password_reset', array( $this, 'on_password_reset' ), 10, 2 );
		add_action( 'profile_update', array( $this, 'on_profile_update' ), 10, 2 );
	}

	/**
	 * List all active sessions for a user using WordPress core WP_Session_Tokens.
	 *
	 * @param int $user_id User ID.
	 * @return array List of sessions.
	 */
	public static function get_user_sessions( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id || ! class_exists( 'WP_Session_Tokens' ) ) {
			return array();
		}

		$manager  = WP_Session_Tokens::get_instance( $user_id );
		$sessions = $manager->get_all();
		$current  = function_exists( 'wp_get_session_token' ) ? (string) wp_get_session_token() : '';
		$current_hash = ( '' !== $current ) ? hash( 'sha256', $current ) : '';

		$clean_list = array();
		if ( is_array( $sessions ) ) {
			foreach ( $sessions as $verifier => $session ) {
				$verifier_str = (string) $verifier;
				$is_current   = ( '' !== $current_hash && hash_equals( $current_hash, $verifier_str ) );

				$clean_list[] = array(
					'verifier'   => $verifier_str,
					'is_current' => $is_current,
					'ip'         => ( is_array( $session ) && ! empty( $session['ip'] ) ) ? $session['ip'] : 'Unknown',
					'ua'         => ( is_array( $session ) && ! empty( $session['ua'] ) ) ? $session['ua'] : 'Unknown',
					'login_time' => ( is_array( $session ) && ! empty( $session['login'] ) ) ? gmdate( 'Y-m-d H:i:s', absint( $session['login'] ) ) : '',
					'expires'    => ( is_array( $session ) && ! empty( $session['expiration'] ) ) ? gmdate( 'Y-m-d H:i:s', absint( $session['expiration'] ) ) : '',
				);
			}
		}

		return $clean_list;
	}

	/**
	 * Destroy a specific session token with strict IDOR verification.
	 *
	 * @param int    $target_user_id Target user ID.
	 * @param string $verifier       Session verifier token.
	 * @return array Result array.
	 */
	public static function destroy_session( $target_user_id, $verifier ) {
		$current_user_id = get_current_user_id();
		$target_user_id  = absint( $target_user_id );

		// IDOR check: acting user may destroy their own sessions; destroying another user's requires edit_users.
		if ( $current_user_id !== $target_user_id ) {
			if ( ! current_user_can( 'edit_users' ) ) {
				return array(
					'success' => false,
					'message' => __( 'Permission denied. You cannot modify sessions for other users.', 'site-checkup-pro' ),
				);
			}
		}

		if ( ! class_exists( 'WP_Session_Tokens' ) ) {
			return array(
				'success' => false,
				'message' => __( 'WP_Session_Tokens not available.', 'site-checkup-pro' ),
			);
		}

		$manager  = WP_Session_Tokens::get_instance( $target_user_id );
		$sessions = $manager->get_all();

		// Verify that the verifier actually belongs to the target user.
		if ( ! isset( $sessions[ $verifier ] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Specified session not found for this user account.', 'site-checkup-pro' ),
			);
		}

		$manager->destroy( $verifier );

		// If cross-user destruction, dispatch security alert.
		if ( $current_user_id !== $target_user_id && class_exists( 'WPSG_Alert_Dispatcher' ) ) {
			$target_user = get_userdata( $target_user_id );
			WPSG_Alert_Dispatcher::dispatch(
				'session_terminated',
				sprintf(
					/* translators: 1: admin user, 2: target user */
					__( 'Admin user #%1$d terminated a remote session for user "%2$s".', 'site-checkup-pro' ),
					$current_user_id,
					$target_user ? $target_user->user_login : $target_user_id
				),
				array( 'admin_id' => $current_user_id, 'target_user_id' => $target_user_id )
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Session terminated successfully.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Destroy all sessions for a user except the current one.
	 *
	 * @param int $target_user_id Target user ID.
	 * @return array
	 */
	public static function destroy_other_sessions( $target_user_id ) {
		$current_user_id = get_current_user_id();
		$target_user_id  = absint( $target_user_id );

		if ( $current_user_id !== $target_user_id && ! current_user_can( 'edit_users' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Permission denied.', 'site-checkup-pro' ),
			);
		}

		if ( ! class_exists( 'WP_Session_Tokens' ) ) {
			return array( 'success' => false, 'message' => __( 'WP_Session_Tokens not available.', 'site-checkup-pro' ) );
		}

		$manager = WP_Session_Tokens::get_instance( $target_user_id );
		$token   = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';

		if ( $token ) {
			$manager->destroy_others( $token );
		} else {
			$manager->destroy_all();
		}

		return array(
			'success' => true,
			'message' => __( 'All other sessions have been logged out.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Invalidate all sessions upon password reset.
	 *
	 * @param WP_User $user     User object.
	 * @param string  $new_pass New password.
	 */
	public function on_password_reset( $user, $new_pass ) {
		if ( is_object( $user ) && ! empty( $user->ID ) && class_exists( 'WP_Session_Tokens' ) ) {
			$manager = WP_Session_Tokens::get_instance( $user->ID );
			$manager->destroy_all();
		}
	}

	/**
	 * Invalidate all other sessions when password is changed on profile update.
	 *
	 * @param int   $user_id       User ID.
	 * @param array $old_user_data Old user data.
	 */
	public function on_profile_update( $user_id, $old_user_data ) {
		$user = get_userdata( $user_id );
		if ( $user && $old_user_data && $user->user_pass !== $old_user_data->user_pass ) {
			if ( class_exists( 'WP_Session_Tokens' ) ) {
				$manager = WP_Session_Tokens::get_instance( $user_id );
				$token   = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';
				if ( $token ) {
					$manager->destroy_others( $token );
				}
			}
		}
	}

	/**
	 * Verify password and grant a session-bound, single-use re-auth token.
	 * Shares the exact same throttling/lockout pipeline as the login form!
	 *
	 * @param string $password Password provided by current user.
	 * @return array|WP_Error Array with reauth_token or WP_Error.
	 */
	public static function verify_password_and_grant_reauth( $password ) {
		$current_user = wp_get_current_user();
		if ( ! $current_user || ! $current_user->exists() ) {
			return new WP_Error( 'not_authenticated', __( 'User session not found.', 'site-checkup-pro' ), array( 'status' => 401 ) );
		}

		$client_ip = class_exists( 'WPSG_Login_Guard' ) ? WPSG_Login_Guard::get_client_ip() : '127.0.0.1';
		$rate_key  = class_exists( 'WPSG_Login_Guard' ) ? WPSG_Login_Guard::get_rate_key( $client_ip, $current_user->user_login ) : 'rl_' . $client_ip;

		// 1. Check if user or IP is currently locked out!
		if ( class_exists( 'WPSG_Login_Guard' ) ) {
			$lockout = WPSG_Login_Guard::check_lockout( $rate_key );
			if ( $lockout['is_locked'] ) {
				return new WP_Error(
					'wpsg_locked_out',
					sprintf(
						__( 'Too many failed attempts. Please try again in %d minute(s).', 'site-checkup-pro' ),
						max( 1, (int) ceil( $lockout['remaining_seconds'] / 60 ) )
					),
					array( 'status' => 429 )
				);
			}
		}

		// 2. Validate password
		if ( ! wp_check_password( $password, $current_user->user_pass, $current_user->ID ) ) {
			// Record failure in the exact same lockout database
			if ( class_exists( 'WPSG_Login_Guard' ) ) {
				WPSG_Login_Guard::record_failure( $client_ip, $current_user->user_login );
			}

			return new WP_Error(
				'invalid_password',
				__( 'Incorrect administrator password.', 'site-checkup-pro' ),
				array( 'status' => 403 )
			);
		}

		// 3. Password verified! Generate single-use token bound to current session.
		$random_token  = wp_generate_password( 32, false, false );
		$session_token = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : 'default_session';
		$auth_salt     = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'wpsg_salt';
		$token_hash    = hash_hmac( 'sha256', $random_token, $session_token . $auth_salt );

		// Store in transient for 5 minutes.
		$transient_key = 'wpsg_reauth_' . $current_user->ID . '_' . substr( $token_hash, 0, 32 );
		set_transient( $transient_key, $token_hash, 300 );

		return array(
			'success'      => true,
			'reauth_token' => $random_token,
			'expires_in'   => 300,
			'message'      => __( 'Password verified successfully.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Validate a re-auth token and consume it (single-use enforcement).
	 *
	 * @param string $token Raw re-auth token string.
	 * @return bool True if valid, false if invalid, expired, or replayed.
	 */
	public static function validate_and_consume_reauth_token( $token ) {
		if ( empty( $token ) || ! is_string( $token ) ) {
			return false;
		}

		$current_user_id = get_current_user_id();
		if ( ! $current_user_id ) {
			return false;
		}

		$session_token = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : 'default_session';
		$auth_salt     = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'wpsg_salt';
		$token_hash    = hash_hmac( 'sha256', $token, $session_token . $auth_salt );

		$transient_key = 'wpsg_reauth_' . $current_user_id . '_' . substr( $token_hash, 0, 32 );
		$stored_hash   = get_transient( $transient_key );

		if ( empty( $stored_hash ) || ! hash_equals( (string) $stored_hash, (string) $token_hash ) ) {
			return false;
		}

		// Single-use: delete immediately to prevent replay!
		delete_transient( $transient_key );

		return true;
	}
}
