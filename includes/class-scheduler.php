<?php
/**
* WP-Cron Scheduler & Reminder Manager
 *
 * Handles 15-day credential rotations, 6-month GSC reminders, 12-month audit log pruning,
 * and dashboard notification notices.
 *
 *
 * @package GeniousSonu_Site_Checkup
 * @author  SK Sahinur Islam <https://www.genioussonu.me/>
 * @link    https://github.com/GeniousSonu/
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Scheduler
 */
class WPSG_Scheduler {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Scheduler|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Scheduler
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
		add_action( 'wpsg_scheduled_reminders', array( $this, 'run_daily_reminder_check' ) );
		add_action( 'wpsg_prune_audit_logs', array( $this, 'run_audit_log_pruning' ) );
		add_action( 'wpsg_prune_rate_limits', array( $this, 'run_rate_limits_pruning' ) );
		add_action( 'wpsg_daily_integrity_scan', array( $this, 'run_daily_integrity_scan' ) );
		add_action( 'admin_notices', array( $this, 'render_due_reminder_notices' ) );
	}

	/**
	 * Register recurring cron events.
	 */
	public static function register_schedules() {
		if ( ! wp_next_scheduled( 'wpsg_scheduled_reminders' ) ) {
			wp_schedule_event( time() + 3600, 'daily', 'wpsg_scheduled_reminders' );
		}

		if ( ! wp_next_scheduled( 'wpsg_prune_audit_logs' ) ) {
			wp_schedule_event( time() + 7200, 'daily', 'wpsg_prune_audit_logs' );
		}

		if ( ! wp_next_scheduled( 'wpsg_prune_rate_limits' ) ) {
			wp_schedule_event( time() + 8400, 'daily', 'wpsg_prune_rate_limits' );
		}

		if ( ! wp_next_scheduled( 'wpsg_daily_integrity_scan' ) ) {
			wp_schedule_event( time() + 10800, 'daily', 'wpsg_daily_integrity_scan' );
		}
	}

	/**
	 * Clear scheduled cron events.
	 */
	public static function clear_schedules() {
		wp_clear_scheduled_hook( 'wpsg_scheduled_reminders' );
		wp_clear_scheduled_hook( 'wpsg_prune_audit_logs' );
		wp_clear_scheduled_hook( 'wpsg_prune_rate_limits' );
		wp_clear_scheduled_hook( 'wpsg_daily_integrity_scan' );
	}

	/**
	 * Daily check for due reminders.
	 */
	public function run_daily_reminder_check() {
		$due_tasks = self::get_due_reminders();
		if ( ! empty( $due_tasks ) ) {
			// Update transient flag to trigger admin notice.
			set_transient( 'wpsg_due_reminders_count', count( $due_tasks ), 86400 );
		} else {
			delete_transient( 'wpsg_due_reminders_count' );
		}
	}

	/**
	 * Daily pruning of audit logs older than 12 months (365 days).
	 */
	public function run_audit_log_pruning() {
		if ( class_exists( 'WPSG_Audit_Log' ) ) {
			WPSG_Audit_Log::prune_old_logs( 365 );
		}
	}

	/**
	 * Daily pruning of expired rate limit and lockout rows.
	 */
	public function run_rate_limits_pruning() {
		if ( class_exists( 'WPSG_Login_Guard' ) ) {
			WPSG_Login_Guard::prune_old_rate_limits();
		}
	}

	/**
	 * Daily automated integrity check.
	 */
	public function run_daily_integrity_scan() {
		if ( class_exists( 'WPSG_Plugin_Integrity' ) ) {
			WPSG_Plugin_Integrity::check_plugin_integrity( true );
		}
	}

	/**
	 * Get tasks with reminders currently due or overdue.
	 *
	 * @return array
	 */
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This query interpolates only the allowlisted, escaped table identifier; timestamp value is prepared.
	public static function get_due_reminders() {
		global $wpdb;

		$table_name = wpsg_get_table_name( 'task_status' );
		if ( false === $table_name ) {
			return array();
		}
		$table_name = esc_sql( $table_name );
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; timestamp is prepared.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table identifier is allowlisted and escaped; timestamp is prepared.
				"SELECT task_id, status, note, next_reminder_at 
				FROM {$table_name} 
				WHERE next_reminder_at IS NOT NULL AND next_reminder_at <= %s",
				$now
			)
		);

		return is_array( $results ) ? $results : array();
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	/**
	 * Render admin notification banners if reminders are due.
	 */
	public function render_due_reminder_notices() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$due_count = (int) get_transient( 'wpsg_due_reminders_count' );
		if ( 0 === $due_count ) {
			$due_tasks = self::get_due_reminders();
			$due_count = count( $due_tasks );
		}

		if ( $due_count > 0 ) {
			$url = admin_url( 'admin.php?page=genioussonu-site-checkup' );
			printf(
				'<div class="notice notice-warning is-dismissible"><p><strong>%1$s:</strong> %2$s <a href="%3$s">%4$s &rarr;</a></p></div>',
				esc_html__( 'GeniousSonu Site Checkup Alert', 'genioussonu-security-hardening-audit' ),
				esc_html(
					sprintf(
						/* translators: %d: count of due tasks */
						__( 'You have %d recurring security task(s) or password rotations due today.', 'genioussonu-security-hardening-audit' ),
						$due_count
					)
				),
				esc_url( $url ),
				esc_html__( 'View Check-up Dashboard', 'genioussonu-security-hardening-audit' )
			);
		}
	}
}
