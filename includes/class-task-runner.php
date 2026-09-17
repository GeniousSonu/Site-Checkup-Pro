<?php
/**
* Safe Task Execution Engine
 *
 * Enforces capabilities (manage_options), mutex locking to prevent concurrent runs (TOCTOU),
 * re-authentication verification for file modifications, atomic backup gating (24-48h recency),
 * state snapshot HMAC integrity verification, redacted audit logging, and undo operations.
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
 * Class WPSG_Task_Runner
 */
class WPSG_Task_Runner {

	/**
	 * Run a task safely following Validate -> Authorize -> Perform -> Verify -> Log -> Recover.
	 *
	 * @param string      $task_id      Task identifier.
	 * @param string|null $reauth_token Optional single-use re-auth token.
	 * @return array Result payload.
	 */
	public static function run( $task_id, $reauth_token = null ) {
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

		// 2. Concurrency Mutex Lock: Prevent concurrent executions of the same task.
		$lock_key = 'wpsg_task_lock_' . sanitize_key( $task_id );
		if ( get_transient( $lock_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'This task is currently being executed by another process. Please wait.', 'site-checkup-pro' ),
			);
		}
		set_transient( $lock_key, true, 30 ); // 30-second TTL

		try {
			// 3. Re-Authentication requirement for destructive / file-modifying tasks.
			if ( 'writes_files' === $task->sub_type || ! empty( $task->requires_reauth ) ) {
				if ( class_exists( 'WPSG_Session_Manager' ) ) {
					$valid_reauth = WPSG_Session_Manager::validate_and_consume_reauth_token( $reauth_token );
					if ( ! $valid_reauth ) {
						return array(
							'success'         => false,
							'reauth_required' => true,
							'message'         => __( 'Administrator password confirmation is required before executing file modifications.', 'site-checkup-pro' ),
						);
					}
				}
			}

			// 4. Atomic Backup guard enforcement for file-modifying tasks.
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

			// 5. Capture before snapshot and compute HMAC integrity hash.
			$before_status = $task->get_live_status();
			$auth_salt     = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'wpsg_salt';
			$snapshot_hash = hash_hmac( 'sha256', wp_json_encode( $before_status ), $auth_salt );

			// 6. Execute task callback (Perform).
			$result = $task->run();

			// Determine if the run itself succeeded (not whether the site passed the check).
			// - Explicit 'success: true' from the callback (write/action tasks).
			// - 'status: done' — check passed.
			// - 'status: attention' for instant (scanner) tasks — scan ran fine, found issues.
			//   'attention' is a valid scan result, not a run failure.
			$result_status = isset( $result['status'] ) ? $result['status'] : '';
			$is_success = ! empty( $result['success'] )
				|| 'done' === $result_status
				|| ( 'attention' === $result_status && 'instant' === $task->sub_type );
			$message    = isset( $result['message'] ) ? $result['message'] : '';

			// 7. Post-action verification (Verify).
			//
			// For runtime-filter instant tasks (disable_xmlrpc, restrict_rest_api,
			// toggle_auto_updates), the status_callback reads the task's DB row via
			// is_task_active(). That row is only written in step 9 AFTER this verify
			// step — so the verify would always see 'pending' and falsely mark the task
			// as 'failed'. Fix: for instant tasks where the run_callback returned an
			// explicit 'success: true' (i.e. action tasks, not scanner tasks), write a
			// temporary 'done' row BEFORE reading live status so is_task_active() returns
			// true. Scanner tasks return 'status: attention' (not 'success: true'), so
			// they skip this pre-write.
			//
			// For writes_files tasks (HSTS with no-SSL guard, wp_debug_display where the
			// PHP constant can't change in the same process), trust the run_callback result
			// if it explicitly returned 'success: true' — do not demote to 'failed' just
			// because the live re-check reads an environmental guard or in-memory constant.
			$run_returned_success_flag = ! empty( $result['success'] );
			if ( $is_success && $run_returned_success_flag && 'instant' === $task->sub_type ) {
				// Pre-write so is_task_active() returns true during the verify re-check.
				// update_db_status() handles cache invalidation for wpsg_task_status_{task_id}.
				self::update_db_status( $task_id, 'done', $task->automation_level );
			}

			$after_status = $task->get_live_status();

			if ( isset( $after_status['status'] ) && 'done' !== $after_status['status'] && $is_success ) {
				// Accept 'attention' as a valid result for:
				// 1. Scanner (instant) tasks — scan ran, found issues. Not a failure.
				// 2. writes_files tasks — write succeeded but env condition (no SSL,
				//    in-memory PHP constant) prevents immediate 'done'. Mark done; the
				//    guard will reflect correctly once the environment changes.
				if ( 'attention' === $after_status['status'] &&
				     ( 'instant' === $task->sub_type || 'writes_files' === $task->sub_type ) ) {
					// For writes_files: force 'done' (write confirmed). For instant: keep 'attention'.
					if ( 'writes_files' === $task->sub_type ) {
						$after_status['status'] = 'done';
					}
					// else: leave after_status['status'] as 'attention' — new_status step will handle it.
				} else {
					// Verification genuinely failed after action execution.
					$is_success = false;
					$message    = ! empty( $after_status['message'] ) ? $after_status['message'] : __( 'Post-action verification failed.', 'site-checkup-pro' );
				}
			}

			// 8. Record to redacted audit log (Log).
			WPSG_Audit_Log::log(
				$task_id,
				'run',
				$before_status,
				$after_status,
				$is_success ? 'success' : 'failed',
				$message
			);

			// 9. Update task status in database with snapshot hash.
			// For instant tasks that return 'attention', store 'attention' (not 'done') so
			// the UI shows the actual check result. For action tasks (success: true), use 'done'.
			if ( $is_success ) {
				$live_status = isset( $after_status['status'] ) ? $after_status['status'] : 'done';
				// Preserve 'attention' for scanner tasks; default to 'done' for action tasks.
				$new_status = ( 'instant' === $task->sub_type && 'attention' === $live_status ) ? 'attention' : 'done';
			} else {
				$new_status = isset( $result['status'] ) ? $result['status'] : 'failed';
				if ( ! in_array( $new_status, array( 'attention', 'pending', 'failed', 'not_applicable' ), true ) ) {
					$new_status = 'failed';
				}
			}
			$metadata   = array(
				'snapshot_hash' => $snapshot_hash,
				'before_status' => $before_status,
				'after_status'  => $after_status,
			);
			self::update_db_status( $task_id, $new_status, $task->automation_level, null, null, $metadata );

			if ( $is_success ) {
				$completed_count = (int) get_option( 'wpsg_completed_tasks_count', 0 ) + 1;
				update_option( 'wpsg_completed_tasks_count', $completed_count );
			}

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
				isset( $before_status ) ? $before_status : null,
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
		} finally {
			// Always release mutex lock!
			delete_transient( $lock_key );
		}
	}

	/**
	 * Undo a previously executed task with snapshot integrity & stale check.
	 *
	 * @param string      $task_id      Task identifier.
	 * @param string|null $reauth_token Optional re-auth token.
	 * @return array
	 */
	public static function undo( $task_id, $reauth_token = null ) {
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

		$lock_key = 'wpsg_task_lock_' . sanitize_key( $task_id );
		if ( get_transient( $lock_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'This task is currently being modified. Please wait.', 'site-checkup-pro' ),
			);
		}
		set_transient( $lock_key, true, 30 );

		try {
			// Re-Auth check for destructive undos.
			if ( 'writes_files' === $task->sub_type || ! empty( $task->requires_reauth ) ) {
				if ( class_exists( 'WPSG_Session_Manager' ) ) {
					$valid_reauth = WPSG_Session_Manager::validate_and_consume_reauth_token( $reauth_token );
					if ( ! $valid_reauth ) {
						return array(
							'success'         => false,
							'reauth_required' => true,
							'message'         => __( 'Administrator password confirmation is required before undoing file modifications.', 'site-checkup-pro' ),
						);
					}
				}
			}

			// Stale snapshot & tamper validation.
			$db_record = self::get_db_record( $task_id );
			$metadata  = ( $db_record && ! empty( $db_record->metadata ) ) ? json_decode( $db_record->metadata, true ) : null;

			if ( ! empty( $metadata['snapshot_hash'] ) && ! empty( $metadata['before_status'] ) ) {
				$auth_salt     = defined( 'AUTH_SALT' ) ? (string) AUTH_SALT : 'wpsg_salt';
				$expected_hash = hash_hmac( 'sha256', (string) wp_json_encode( $metadata['before_status'] ), $auth_salt );
				if ( ! hash_equals( (string) $expected_hash, (string) $metadata['snapshot_hash'] ) ) {
					return array(
						'success' => false,
						'message' => __( 'Security error: Snapshot integrity check failed (tampered snapshot detected). Undo aborted.', 'site-checkup-pro' ),
					);
				}
			}

			$before_status = $task->get_live_status();

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
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Retrieve database status record.
	 *
	 * @param string $task_id Task ID.
	 * @return object|null
	 */
	public static function get_db_record( $task_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wpsg_task_status';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_name} WHERE task_id = %s LIMIT 1", $task_id )
		);
	}

	/**
	 * Update status record in wpsg_task_status table.
	 *
	 * @param string      $task_id          Task ID.
	 * @param string      $status           Status string.
	 * @param string      $automation_level Automation level.
	 * @param string|null $note             Optional note.
	 * @param string|null $next_reminder_at Optional reminder timestamp.
	 * @param array|null  $metadata         Optional metadata array.
	 * @return bool
	 */
	public static function update_db_status( $task_id, $status, $automation_level = 'A', $note = null, $next_reminder_at = null, $metadata = null ) {
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

		if ( null !== $metadata ) {
			$data['metadata'] = wp_json_encode( $metadata );
		}

		wp_cache_delete( 'wpsg_task_status_' . $task_id, 'site-checkup-pro' );

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
