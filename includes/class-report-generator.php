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

		$status_table = isset( $wpdb->prefix ) ? $wpdb->prefix . 'wpsg_task_status' : 'wp_wpsg_task_status';
		$db_rows      = array();
		if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'get_results' ) ) {
			$output_type = defined( 'OBJECT_K' ) ? OBJECT_K : 'OBJECT_K';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$raw = $wpdb->get_results( "SELECT * FROM {$status_table}", $output_type );
			if ( is_array( $raw ) ) {
				$db_rows = $raw;
			}
		}

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
			$db_record = ( is_array( $db_rows ) && isset( $db_rows[ $id ] ) ) ? $db_rows[ $id ] : null;
			try {
				$serialized = $task->to_array( $db_record );
			} catch ( \Throwable $e ) {
				$serialized = array(
					'id'               => $task->id,
					'section'          => $task->section,
					'title'            => $task->title,
					'description'      => $task->description,
					'automation_level' => $task->automation_level,
					'sub_type'         => $task->sub_type,
					'guide_data'       => $task->guide_data,
					'status'           => 'attention',
					'last_run_at'      => null,
					'note'             => '',
					'next_reminder_at' => null,
					'live_message'     => 'Notice: ' . $e->getMessage(),
					'is_na'            => false,
				);
			}

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

		$site_name = function_exists( 'get_bloginfo' ) ? get_bloginfo( 'name' ) : get_option( 'blogname', 'WordPress Site' );
		$site_url  = function_exists( 'home_url' ) ? home_url() : get_option( 'siteurl', 'https://example.com' );

		return array(
			'site_name'        => $site_name,
			'site_url'         => $site_url,
			'generated_at'     => function_exists( 'current_time' ) ? current_time( 'F j, Y, g:i a' ) : gmdate( 'F j, Y, g:i a' ),
			'agency_name'      => ! empty( $settings['agency_name'] ) ? $settings['agency_name'] : $site_name . ' Security Team',
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
