<?php
/**
 * Backup Guard & Recency Verifier
 *
 * Enforces a 24-48 hour recency check before executing high-risk or file-modifying tasks.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Backup_Guard
 */
class WPSG_Backup_Guard {

	/**
	 * Default maximum allowed age of backup in hours (48h).
	 */
	const MAX_BACKUP_AGE_HOURS = 48;

	/**
	 * Check if a verified backup exists within the recency window.
	 *
	 * @param int $max_age_hours Maximum age in hours.
	 * @return bool
	 */
	public static function has_recent_backup( $max_age_hours = self::MAX_BACKUP_AGE_HOURS ) {
		$status = self::get_backup_status( $max_age_hours );
		return ! empty( $status['is_recent'] );
	}

	/**
	 * Get detailed backup plugin and recency status.
	 *
	 * @param int $max_age_hours Maximum age in hours.
	 * @return array
	 */
	public static function get_backup_status( $max_age_hours = self::MAX_BACKUP_AGE_HOURS ) {
		$now = current_time( 'timestamp' );

		// 1. Check UpdraftPlus
		if ( class_exists( 'UpdraftPlus' ) || defined( 'UPDRAFTPLUS_DIR' ) ) {
			$history = get_option( 'updraft_backup_history' );
			if ( is_array( $history ) && ! empty( $history ) ) {
				$timestamps = array_keys( $history );
				rsort( $timestamps );
				$latest_ts = (int) $timestamps[0];
				$age_hours = round( ( $now - $latest_ts ) / 3600, 1 );

				return array(
					'has_backup_plugin' => true,
					'plugin_name'       => 'UpdraftPlus',
					'last_backup_time'  => $latest_ts,
					'last_backup_date'  => gmdate( 'Y-m-d H:i:s', $latest_ts ),
					'age_hours'         => $age_hours,
					'is_recent'         => ( $age_hours <= $max_age_hours ),
					'trigger_url'       => admin_url( 'options-general.php?page=updraftplus' ),
				);
			}

			return array(
				'has_backup_plugin' => true,
				'plugin_name'       => 'UpdraftPlus',
				'last_backup_time'  => null,
				'is_recent'         => false,
				'trigger_url'       => admin_url( 'options-general.php?page=updraftplus' ),
				'message'           => __( 'UpdraftPlus is active, but no completed backups were found.', 'site-checkup-pro' ),
			);
		}

		// 2. Check WPvivid
		if ( defined( 'WPVIVID_PLUGIN_DIR' ) || class_exists( 'WPvivid' ) ) {
			$list = get_option( 'wpvivid_backup_list' );
			if ( is_array( $list ) && ! empty( $list ) ) {
				$latest_ts = 0;
				foreach ( $list as $item ) {
					if ( isset( $item['create_time'] ) && $item['create_time'] > $latest_ts ) {
						$latest_ts = (int) $item['create_time'];
					}
				}

				if ( $latest_ts > 0 ) {
					$age_hours = round( ( $now - $latest_ts ) / 3600, 1 );
					return array(
						'has_backup_plugin' => true,
						'plugin_name'       => 'WPvivid Backup',
						'last_backup_time'  => $latest_ts,
						'last_backup_date'  => gmdate( 'Y-m-d H:i:s', $latest_ts ),
						'age_hours'         => $age_hours,
						'is_recent'         => ( $age_hours <= $max_age_hours ),
						'trigger_url'       => admin_url( 'admin.php?page=WPvivid' ),
					);
				}
			}

			return array(
				'has_backup_plugin' => true,
				'plugin_name'       => 'WPvivid Backup',
				'last_backup_time'  => null,
				'is_recent'         => false,
				'trigger_url'       => admin_url( 'admin.php?page=WPvivid' ),
				'message'           => __( 'WPvivid is active, but no completed backups were found.', 'site-checkup-pro' ),
			);
		}

		// 3. Check BackWPup
		if ( class_exists( 'BackWPup' ) ) {
			return array(
				'has_backup_plugin' => true,
				'plugin_name'       => 'BackWPup',
				'last_backup_time'  => null,
				'is_recent'         => false,
				'trigger_url'       => admin_url( 'admin.php?page=backwpupjobs' ),
				'message'           => __( 'BackWPup is active. Please verify a recent backup has run.', 'site-checkup-pro' ),
			);
		}

		// 4. Check Manual Confirmation fallback (e.g. cPanel / hosting snapshot confirmed by dev)
		$manual_ts = (int) get_option( 'wpsg_manual_backup_confirmed_at', 0 );
		if ( $manual_ts > 0 ) {
			$age_hours = round( ( $now - $manual_ts ) / 3600, 1 );
			if ( $age_hours <= $max_age_hours ) {
				return array(
					'has_backup_plugin' => false,
					'plugin_name'       => __( 'Manual Host/cPanel Backup', 'site-checkup-pro' ),
					'last_backup_time'  => $manual_ts,
					'last_backup_date'  => gmdate( 'Y-m-d H:i:s', $manual_ts ),
					'age_hours'         => $age_hours,
					'is_recent'         => true,
					'trigger_url'       => '',
				);
			}
		}

		// No backup plugin detected and no recent manual confirmation
		return array(
			'has_backup_plugin' => false,
			'plugin_name'       => '',
			'last_backup_time'  => null,
			'is_recent'         => false,
			'trigger_url'       => admin_url( 'plugin-install.php?s=updraftplus&tab=search&type=term' ),
			'message'           => __( 'No active backup plugin or recent host backup detected within the last 48 hours.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Record a manual backup confirmation timestamp.
	 *
	 * @return bool
	 */
	public static function confirm_manual_backup() {
		return update_option( 'wpsg_manual_backup_confirmed_at', current_time( 'timestamp' ) );
	}
}
