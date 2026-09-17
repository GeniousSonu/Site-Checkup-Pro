<?php
/**
 * Client-Facing SOP Coverage Report Generator
 *
 * Generates an executive audit report of SOP checklist completion,
 * hardened security controls, pending maintenance tasks, and incident response contacts.
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
 * Class WPSG_Report_Generator
 */
class WPSG_Report_Generator {

	/**
	 * Get all data needed to build the client report.
	 *
	 * @return array
	 */
	public static function get_report_data() {
		global $wpdb;

		$registry = WPSG_Task_Registry::get_instance();
		$tasks    = $registry->get_all();

		$status_table = $wpdb->prefix . 'wpsg_task_status';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$db_rows = $wpdb->get_results( "SELECT * FROM {$status_table}", OBJECT_K );

		$completed   = array();
		$attention   = array();
		$pending     = array();
		$manual_done = array();
		$total_count = 0;
		$done_count  = 0;

		foreach ( $tasks as $id => $task ) {
			if ( 'report' === $task->section ) {
				continue;
			}

			$total_count++;
			$db_record = isset( $db_rows[ $id ] ) ? $db_rows[ $id ] : null;
			$serialized = $task->to_array( $db_record );

			if ( 'done' === $serialized['status'] ) {
				$done_count++;
				if ( 'C' === $task->automation_level ) {
					$manual_done[] = $serialized;
				} else {
					$completed[] = $serialized;
				}
			} elseif ( 'attention' === $serialized['status'] || 'failed' === $serialized['status'] ) {
				$attention[] = $serialized;
			} else {
				$pending[] = $serialized;
			}
		}

		$coverage_pct = ( $total_count > 0 ) ? round( ( $done_count / $total_count ) * 100 ) : 0;
		$recent_logs  = WPSG_Audit_Log::get_logs( 25 );
		$settings     = get_option( 'wpsg_settings', array() );

		$incident_contact = array(
			'name'  => ! empty( $settings['incident_contact_name'] ) ? $settings['incident_contact_name'] : '',
			'email' => ! empty( $settings['incident_contact_email'] ) ? $settings['incident_contact_email'] : '',
			'phone' => ! empty( $settings['incident_contact_phone'] ) ? $settings['incident_contact_phone'] : '',
			'notes' => ! empty( $settings['incident_contact_notes'] ) ? $settings['incident_contact_notes'] : '',
		);

		return array(
			'site_name'        => get_bloginfo( 'name' ),
			'site_url'         => home_url(),
			'generated_at'     => current_time( 'F j, Y, g:i a' ),
			'agency_name'      => ! empty( $settings['agency_name'] ) ? $settings['agency_name'] : get_bloginfo( 'name' ) . ' Security Team',
			'coverage_pct'     => $coverage_pct,
			'total_tasks'      => $total_count,
			'done_tasks'       => $done_count,
			'completed'        => $completed,
			'manual_done'      => $manual_done,
			'attention'        => $attention,
			'pending'          => $pending,
			'audit_logs'       => $recent_logs,
			'incident_contact' => $incident_contact,
		);
	}
}
