<?php
/**
* Wordfence Security Integration Bridge
 *
 * Checks Wordfence installation and configuration status and provides deep links.
 * Complies with WordPress.org guidelines: no remote code execution or proprietary config writes.
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
 * Class WPSG_Wordfence_Bridge
 */
class WPSG_Wordfence_Bridge {

	/**
	 * Check if Wordfence is installed.
	 *
	 * @return bool
	 */
	public static function is_installed() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$plugins = get_plugins();
		return isset( $plugins['wordfence/wordfence.php'] );
	}

	/**
	 * Check if Wordfence is currently active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return is_plugin_active( 'wordfence/wordfence.php' ) || class_exists( 'wordfence' );
	}

	/**
	 * Get comprehensive Wordfence status and recommended settings checklist.
	 *
	 * @return array
	 */
	public static function get_status() {
		if ( ! self::is_active() ) {
			return array(
				'status'      => 'pending',
				'is_active'   => false,
				'waf_status'  => 'inactive',
				'install_url' => admin_url( 'plugin-install.php?s=wordfence&tab=search&type=term' ),
				'message'     => __( 'Wordfence is not active. Install and activate it to enable web application firewall and malware scanning.', 'site-checkup-pro' ),
			);
		}

		$waf_status = 'Standard Protection';
		$waf_active = false;

		if ( class_exists( 'wfConfig' ) ) {
			$waf_status_val = wfConfig::get( 'wafStatus' );
			if ( 'enabled' === $waf_status_val ) {
				$waf_active = true;
				$waf_status = 'Firewall Enabled (Active Protection)';
			} elseif ( 'learning-mode' === $waf_status_val ) {
				$waf_active = true;
				$waf_status = 'Learning Mode';
			} else {
				$waf_status = 'Firewall Disabled';
			}
		}

		// Recommended settings guidelines from Agency SOP
		$recommendations = array(
			array(
				'title' => __( 'Web Application Firewall (WAF)', 'site-checkup-pro' ),
				'desc'  => __( 'Optimize WAF to Extended Protection (user.ini/php.ini setup).', 'site-checkup-pro' ),
				'link'  => admin_url( 'admin.php?page=WordfenceWAF' ),
			),
			array(
				'title' => __( 'Brute Force Protection', 'site-checkup-pro' ),
				'desc'  => __( 'Enforce max 5 login failures and lock out immediately.', 'site-checkup-pro' ),
				'link'  => admin_url( 'admin.php?page=WordfenceWAF#waf-options-login-security' ),
			),
			array(
				'title' => __( 'Email Alert Monitoring', 'site-checkup-pro' ),
				'desc'  => __( 'Ensure alert emails route to designated agency monitoring inbox.', 'site-checkup-pro' ),
				'link'  => admin_url( 'admin.php?page=WordfenceGlobalOptions#global-options-email-preferences' ),
			),
		);

		return array(
			'status'          => $waf_active ? 'done' : 'attention',
			'is_active'       => true,
			'waf_status'      => $waf_status,
			'waf_active'      => $waf_active,
			'settings_url'    => admin_url( 'admin.php?page=Wordfence' ),
			'waf_url'         => admin_url( 'admin.php?page=WordfenceWAF' ),
			'scan_url'        => admin_url( 'admin.php?page=WordfenceScan' ),
			'recommendations' => $recommendations,
			'message'         => sprintf(
				/* translators: %s: WAF status */
				__( 'Wordfence is active (%s). Review agency recommended settings.', 'site-checkup-pro' ),
				$waf_status
			),
		);
	}
}
