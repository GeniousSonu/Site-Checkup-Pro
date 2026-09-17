<?php
/**
* Two-Factor Authentication (2FA) Bridge
 *
 * Checks for known 2FA implementations and provides setup deep-links.
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
 * Class WPSG_2fa_Bridge
 */
class WPSG_2fa_Bridge {

	/**
	 * Recognized 2FA plugins and detection callbacks.
	 */
	public static function get_active_2fa_plugin() {
		$providers = array(
			'Wordfence Login Security' => array(
				'is_active' => class_exists( 'wordfence' ) || is_plugin_active( 'wordfence-login-security/wordfence-login-security.php' ),
				'link'      => admin_url( 'admin.php?page=WFLS' ),
			),
			'WP 2FA' => array(
				'is_active' => defined( 'WP_2FA_VERSION' ) || is_plugin_active( 'wp-2fa/wp-2fa.php' ),
				'link'      => admin_url( 'admin.php?page=wp-2fa-policies' ),
			),
			'Two-Factor (WordPress Core Team)' => array(
				'is_active' => class_exists( 'Two_Factor_Core' ) || is_plugin_active( 'two-factor/two-factor.php' ),
				'link'      => admin_url( 'profile.php#two-factor' ),
			),
			'miniOrange 2-Factor' => array(
				'is_active' => defined( 'MO_2_FACTOR_VERSION' ) || is_plugin_active( 'miniorange-2-factor-authentication/miniorange_2_factor_settings.php' ),
				'link'      => admin_url( 'admin.php?page=mo_2fa_settings' ),
			),
			'Solid Security 2FA' => array(
				'is_active' => defined( 'ITSEC_CORE_DIR' ),
				'link'      => admin_url( 'admin.php?page=itsec' ),
			),
		);

		foreach ( $providers as $name => $data ) {
			if ( $data['is_active'] ) {
				return array(
					'name' => $name,
					'link' => $data['link'],
				);
			}
		}

		return null;
	}

	/**
	 * Get status of 2FA on the site.
	 *
	 * @return array
	 */
	public static function get_status() {
		$active_provider = self::get_active_2fa_plugin();

		if ( $active_provider ) {
			return array(
				'status'      => 'done',
				'has_2fa'     => true,
				'provider'    => $active_provider['name'],
				'config_url'  => $active_provider['link'],
				'message'     => sprintf(
					/* translators: %s: provider name */
					__( 'Two-Factor Authentication is active via %s.', 'site-checkup-pro' ),
					$active_provider['name']
				),
			);
		}

		return array(
			'status'      => 'attention',
			'has_2fa'     => false,
			'provider'    => '',
			'install_url' => admin_url( 'plugin-install.php?s=two-factor&tab=search&type=term' ),
			'message'     => __( 'No active Two-Factor Authentication plugin detected. Enforcing 2FA for administrators is strongly recommended in the SOP.', 'site-checkup-pro' ),
		);
	}
}
