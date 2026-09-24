<?php
/**
* Application Password Governance & Revocation
 *
 * Surfaces active application passwords across all users via core WP_Application_Passwords,
 * and gates revocations behind manage_options and session-bound re-authentication.
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
 * Class WPSG_App_Password_Manager
 */
class WPSG_App_Password_Manager {

	/**
	 * Get all application passwords across all users.
	 *
	 * @return array
	 */
	public static function get_all_application_passwords() {
		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return array();
		}

		$users = get_users( array(
			'role__in' => array( 'administrator', 'editor', 'author' ),
			'fields'   => array( 'ID', 'user_login', 'display_name' ),
		) );

		$all_passwords = array();

		foreach ( $users as $u ) {
			$passwords = WP_Application_Passwords::get_user_application_passwords( $u->ID );
			if ( ! empty( $passwords ) && is_array( $passwords ) ) {
				foreach ( $passwords as $p ) {
					$all_passwords[] = array(
						'uuid'         => isset( $p['uuid'] ) ? sanitize_text_field( $p['uuid'] ) : '',
						'name'         => isset( $p['name'] ) ? sanitize_text_field( $p['name'] ) : '',
						'user_id'      => (int) $u->ID,
						'user_login'   => $u->user_login,
						'display_name' => $u->display_name,
						'created'      => ! empty( $p['created'] ) ? gmdate( 'Y-m-d H:i:s', $p['created'] ) : '',
						'last_used'    => ! empty( $p['last_used'] ) ? gmdate( 'Y-m-d H:i:s', $p['last_used'] ) : __( 'Never', 'site-checkup-pro' ),
						'last_ip'      => ! empty( $p['last_ip'] ) ? $p['last_ip'] : __( 'N/A', 'site-checkup-pro' ),
					);
				}
			}
		}

		return $all_passwords;
	}

	/**
	 * Revoke an application password safely, gated by manage_options and re-authentication.
	 *
	 * @param int    $user_id      Target user ID.
	 * @param string $uuid         Application password UUID.
	 * @param string $reauth_token Single-use re-auth token.
	 * @return array Result.
	 */
	public static function revoke_password( $user_id, $uuid, $reauth_token ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Permission denied. Administrator capabilities required.', 'site-checkup-pro' ),
			);
		}

		// Re-authentication check
		if ( class_exists( 'WPSG_Session_Manager' ) ) {
			if ( ! WPSG_Session_Manager::validate_and_consume_reauth_token( $reauth_token ) ) {
				return array(
					'success'         => false,
					'reauth_required' => true,
					'message'         => __( 'Re-authentication required: please confirm your password to revoke credentials.', 'site-checkup-pro' ),
				);
			}
		}

		if ( ! class_exists( 'WP_Application_Passwords' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Application Passwords not supported on this WordPress installation.', 'site-checkup-pro' ),
			);
		}

		$deleted = WP_Application_Passwords::delete_application_password( $user_id, $uuid );

		if ( $deleted ) {
			if ( class_exists( 'WPSG_Audit_Log' ) ) {
				WPSG_Audit_Log::log(
					'app_password_revoke',
					'revoke',
					null,
					array( 'target_user_id' => $user_id, 'uuid' => $uuid ),
					'success',
					/* translators: %s: application password UUID */
					sprintf( __( 'Application password %s revoked.', 'site-checkup-pro' ), $uuid )
				);
			}

			return array(
				'success' => true,
				'message' => __( 'Application password revoked successfully.', 'site-checkup-pro' ),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'Could not revoke specified application password.', 'site-checkup-pro' ),
		);
	}
}
