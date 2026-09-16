<?php
/**
 * Safe Task Execution Engine
 *
 * Enforces capabilities (manage_options), backup gating (24-48h recency),
 * state snapshots, redacted audit logging, and undo operations.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Task_Runner
 */
class WPSG_Task_Runner {

	/**
	 * Run a task safely.
	 *
	 * @param string $task_id Task identifier.
	 * @return array
	 */
	public static function run( $task_id ) {
		// 1. Strict capability enforcement: Hard-require manage_options.
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Permission denied. Administrator capabilities required.', 'site-checkup-pro' ),
			);
		}

		$task = WPSG_Task_Registry::get_instance()->get( $task_id );
		if ( ! $task ) {
			return array(
				'success' => false,
				'message' => __( 'Task not found in registry.', 'site-checkup-pro' ),
			);
		}

		// 2. Backup guard enforcement for file-modifying tasks.
		if ( $task->requires_backup ) {
			if ( ! WPSG_Backup_Guard::has_recent_backup() ) {
				$backup_info = WPSG_Backup_Guard::get_backup_status();
				return array(
					'success'         => false,
					'backup_required' => true,
					'backup_info'     => $backup_info,
					'message'         => __( 'A verified backup taken within the last 48 hours is required before running this file-modifying task.', 'site-checkup-pro' ),
				);
			}
		}

		// 3. Capture before snapshot.
		$before_status = $task->get_live_status();

		try {
			// 4. Execute the task run callback.
			$result = $task->run();

			$is_success = ! empty( $result['success'] ) || ( isset( $result['status'] ) && 'done' === $result['status'] );
			$message    = isset( $result['message'] ) ? $result['message'] : '';

			// 5. Capture after snapshot.
			$after_status = $task->get_live_status();

			// 6. Record to redacted audit log.
			WPSG_Audit_Log::log(
				$task_id,
				'run',
				$before_status,
				$after_status,
				$is_success ? 'success' : 'failed',
				$message
			);

			// 7. Update task status in database.
			$new_status = $is_success ? 'done' : ( isset( $result['status'] ) ? $result['status'] : 'failed' );
			self::update_db_status( $task_id, $new_status, $task->automation_level );

			return array(
				'success'      => $is_success,
				'task_id'      => $task_id,
				'status'       => $new_status,
				'message'      => $message,
				'live_message' => isset( $after_status['message'] ) ? $after_status['message'] : $message,
				'has_undo'     => $task->has_undo,
			);

		} catch ( Exception $e ) {
			WPSG_Audit_Log::log(
				$task_id,
				'run',
				$before_status,
				array( 'error' => $e->getMessage() ),
				'failed',
				$e->getMessage()
			);

			self::update_db_status( $task_id, 'failed', $task->automation_level );

			return array(
				'success' => false,
				'status'  => 'failed',
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Undo a previously executed task.
	 *
	 * @param string $task_id Task identifier.
	 * @return array
	 */
	public static function undo( $task_id ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success' => false,
				'message' => __( 'Permission denied. Administrator capabilities required.', 'site-checkup-pro' ),
			);
		}

		$task = WPSG_Task_Registry::get_instance()->get( $task_id );
		if ( ! $task || ! $task->has_undo ) {
			return array(
				'success' => false,
				'message' => __( 'This task does not support undo operations.', 'site-checkup-pro' ),
			);
		}

		$before_status = $task->get_live_status();

		try {
			$result     = $task->undo();
			$is_success = ! empty( $result['success'] );
			$message    = isset( $result['message'] ) ? $result['message'] : '';

			$after_status = $task->get_live_status();

			WPSG_Audit_Log::log(
				$task_id,
				'undo',
				$before_status,
				$after_status,
				$is_success ? 'success' : 'failed',
				$message
			);

			if ( $is_success ) {
				self::update_db_status( $task_id, 'pending', $task->automation_level );
			}

			return array(
				'success'      => $is_success,
				'task_id'      => $task_id,
				'status'       => 'pending',
				'message'      => $message,
				'live_message' => isset( $after_status['message'] ) ? $after_status['message'] : $message,
			);

		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Update status record in wpsg_task_status table.
	 *
	 * @param string $task_id          Task ID.
	 * @param string $status           Status string (pending, done, failed, skipped, attention).
	 * @param string $automation_level Automation level (A, B, C, D).
	 * @param string $note             Optional note.
	 * @param string $next_reminder_at Optional reminder timestamp.
	 * @return bool
	 */
	public static function update_db_status( $task_id, $status, $automation_level = 'A', $note = null, $next_reminder_at = null ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wpsg_task_status';
		$user_id    = get_current_user_id();
		$now        = current_time( 'mysql' );

		$data = array(
			'task_id'          => sanitize_key( $task_id ),
			'status'           => sanitize_key( $status ),
			'automation_level' => sanitize_text_field( $automation_level ),
			'last_run_at'      => $now,
			'last_run_by'      => absint( $user_id ),
		);

		if ( null !== $note ) {
			$data['note'] = wp_kses_post( $note );
		}

		if ( null !== $next_reminder_at ) {
			$data['next_reminder_at'] = $next_reminder_at ? sanitize_text_field( $next_reminder_at ) : null;
		}

		// Clear cache
		wp_cache_delete( 'wpsg_task_status_' . $task_id, 'site-checkup-pro' );

		// Check if record exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table_name} WHERE task_id = %s LIMIT 1", $task_id )
		);

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return false !== $wpdb->update( $table_name, $data, array( 'task_id' => $task_id ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			return false !== $wpdb->insert( $table_name, $data );
		}
	}
}
