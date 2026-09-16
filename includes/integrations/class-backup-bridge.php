<?php
/**
 * Backup Plugin Integration Bridge
 *
 * Provides status and safe triggers for established backup solutions.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Backup_Bridge
 */
class WPSG_Backup_Bridge {

	/**
	 * Get detailed backup integration info.
	 *
	 * @return array
	 */
	public static function get_info() {
		return WPSG_Backup_Guard::get_backup_status();
	}

	/**
	 * Attempt to trigger a backup or provide deep-link if direct trigger is not safely available via public API.
	 *
	 * @return array
	 */
	public static function trigger_backup() {
		$status = WPSG_Backup_Guard::get_backup_status();

		if ( ! empty( $status['trigger_url'] ) ) {
			return array(
				'success'     => true,
				'redirect_url'=> $status['trigger_url'],
				'message'     => sprintf(
					/* translators: %s: plugin name */
					__( 'Please trigger a full backup via %s before continuing.', 'site-checkup-pro' ),
					$status['plugin_name'] ? $status['plugin_name'] : __( 'your backup plugin', 'site-checkup-pro' )
				),
			);
		}

		return array(
			'success' => false,
			'message' => __( 'No supported backup plugin detected to trigger automatic backup.', 'site-checkup-pro' ),
		);
	}
}
