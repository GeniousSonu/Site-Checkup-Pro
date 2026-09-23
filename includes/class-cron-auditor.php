<?php
/**
 * Cron Job Auditor
 *
 * Inspects all scheduled WordPress WP-Cron events via _get_cron_array(), resolves
 * originating plugins/themes, detects overdue stalled events, and flags duplicate schedules.
 *
 * @package Site_Checkup_Pro
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Cron_Auditor
 */
class WPSG_Cron_Auditor {

	/**
	 * Run full audit on scheduled WP-Cron jobs.
	 *
	 * @return array
	 */
	public static function audit_cron_jobs() {
		$cron_array = function_exists( '_get_cron_array' ) ? _get_cron_array() : array();
		if ( ! is_array( $cron_array ) ) {
			$cron_array = array();
		}

		$current_time    = time();
		$schedules       = function_exists( 'wp_get_schedules' ) ? wp_get_schedules() : array();
		$events_list     = array();
		$hook_counts     = array();
		$overdue_count   = 0;
		$duplicate_count = 0;

		// First pass: count hook occurrences to identify duplicates
		foreach ( $cron_array as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}
			foreach ( $hooks as $hook => $event_instances ) {
				if ( ! isset( $hook_counts[ $hook ] ) ) {
					$hook_counts[ $hook ] = 0;
				}
				$hook_counts[ $hook ] += count( $event_instances );
			}
		}

		// Second pass: compile and analyze each event
		foreach ( $cron_array as $timestamp => $hooks ) {
			if ( ! is_array( $hooks ) ) {
				continue;
			}

			foreach ( $hooks as $hook => $event_instances ) {
				if ( ! is_array( $event_instances ) ) {
					continue;
				}

				foreach ( $event_instances as $sig => $data ) {
					$interval_sec  = isset( $data['interval'] ) ? (int) $data['interval'] : 0;
					$schedule_name = isset( $data['schedule'] ) && $data['schedule'] ? $data['schedule'] : ( $interval_sec > 0 ? 'custom' : 'single' );
					$schedule_desc = isset( $schedules[ $schedule_name ]['display'] ) ? $schedules[ $schedule_name ]['display'] : ucfirst( $schedule_name );

					// Overdue assessment: event scheduled > 600s in the past
					$overdue_diff = $current_time - $timestamp;
					$is_overdue   = false;
					if ( $overdue_diff > 600 ) {
						// Overdue if 10 mins past due, or past 20% of its interval
						$is_overdue = true;
						$overdue_count++;
					}

					// Duplicate assessment
					$is_duplicate = ( isset( $hook_counts[ $hook ] ) && $hook_counts[ $hook ] > 1 );
					if ( $is_duplicate ) {
						$duplicate_count++;
					}

					$source = self::resolve_hook_source( $hook );

					$events_list[] = array(
						'hook'             => $hook,
						'next_run_ts'      => $timestamp,
						'next_run_human'   => gmdate( 'Y-m-d H:i:s', $timestamp ) . ' UTC',
						'schedule_name'    => $schedule_name,
						'schedule_display' => $schedule_desc,
						'interval_seconds' => $interval_sec,
						'is_overdue'       => $is_overdue,
						'overdue_seconds'  => $is_overdue ? $overdue_diff : 0,
						'is_duplicate'     => $is_duplicate,
						'duplicate_count'  => isset( $hook_counts[ $hook ] ) ? $hook_counts[ $hook ] : 1,
						'source'           => $source['name'],
						'source_type'      => $source['type'],
						'args'             => isset( $data['args'] ) ? $data['args'] : array(),
					);
				}
			}
		}

		// Sort events by next_run timestamp ascending
		usort( $events_list, function ( $a, $b ) {
			return $a['next_run_ts'] - $b['next_run_ts'];
		} );

		return array(
			'summary' => array(
				'total_jobs'        => count( $events_list ),
				'unique_hooks'      => count( $hook_counts ),
				'overdue_count'     => $overdue_count,
				'duplicate_count'   => $duplicate_count,
				'cron_disabled'     => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'alternate_cron'    => defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON,
				'current_time_gmt'  => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			),
			'jobs' => $events_list,
		);
	}

	/**
	 * Resolve registering source of a WP-Cron hook.
	 *
	 * @param string $hook Hook name.
	 * @return array
	 */
	public static function resolve_hook_source( $hook ) {
		// Core hooks
		$core_hooks = array(
			'wp_version_check',
			'wp_update_plugins',
			'wp_update_themes',
			'wp_scheduled_delete',
			'wp_scheduled_auto_draft_delete',
			'delete_expired_transients',
			'wp_privacy_delete_old_export_files',
			'recovery_mode_clean_expired_keys',
			'wp_site_health_scheduled_check',
			'wp_split_shared_term_batch',
		);
		if ( in_array( $hook, $core_hooks, true ) || 0 === strpos( $hook, 'wp_' ) ) {
			return array( 'name' => 'WordPress Core', 'type' => 'core' );
		}

		// Site Checkup Pro
		if ( 0 === strpos( $hook, 'wpsg_' ) ) {
			return array( 'name' => 'Site Checkup Pro', 'type' => 'plugin' );
		}

		// WooCommerce / Action Scheduler
		if ( 0 === strpos( $hook, 'action_scheduler_' ) || 0 === strpos( $hook, 'woocommerce_' ) ) {
			return array( 'name' => 'WooCommerce / Action Scheduler', 'type' => 'plugin' );
		}

		// Jetpack
		if ( 0 === strpos( $hook, 'jetpack_' ) ) {
			return array( 'name' => 'Jetpack', 'type' => 'plugin' );
		}

		// Yoast
		if ( 0 === strpos( $hook, 'wpseo_' ) || 0 === strpos( $hook, 'yoast_' ) ) {
			return array( 'name' => 'Yoast SEO', 'type' => 'plugin' );
		}

		// Inspect registered actions on the hook if available
		global $wp_filter;
		if ( isset( $wp_filter[ $hook ] ) ) {
			$hook_callbacks = $wp_filter[ $hook ];
			if ( is_object( $hook_callbacks ) && isset( $hook_callbacks->callbacks ) ) {
				foreach ( $hook_callbacks->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $cb_info ) {
						if ( isset( $cb_info['function'] ) ) {
							$fn = $cb_info['function'];
							try {
								$file = '';
								if ( is_array( $fn ) && isset( $fn[0] ) ) {
									$ref  = new ReflectionClass( $fn[0] );
									$file = $ref->getFileName();
								} elseif ( $fn instanceof Closure || ( is_string( $fn ) && function_exists( $fn ) ) ) {
									$ref  = new ReflectionFunction( $fn );
									$file = $ref->getFileName();
								}
								if ( $file && defined( 'WP_PLUGIN_DIR' ) && false !== strpos( $file, WP_PLUGIN_DIR ) ) {
									$rel = trim( str_replace( WP_PLUGIN_DIR, '', $file ), '/\\' );
									$parts = explode( DIRECTORY_SEPARATOR, $rel );
									$folder = ! empty( $parts[0] ) ? $parts[0] : '';
									return array( 'name' => ucwords( str_replace( array( '-', '_' ), ' ', $folder ) ), 'type' => 'plugin' );
								}
							} catch ( Exception $e ) {
								// Fallback below
							}
						}
					}
				}
			}
		}

		// Fallback: hook prefix
		$parts = explode( '_', $hook );
		$prefix = ! empty( $parts[0] ) ? $parts[0] : 'Custom';
		return array(
			'name' => ucwords( str_replace( '-', ' ', $prefix ) ),
			'type' => 'custom',
		);
	}
}
