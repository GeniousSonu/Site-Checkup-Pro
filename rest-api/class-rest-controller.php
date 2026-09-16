<?php
/**
 * REST API Controller
 *
 * Exposes endpoints for task listing, running, undoing, diff previews,
 * baseline updates, and report generation.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPSG_Rest_Controller
 */
class WPSG_Rest_Controller extends WP_REST_Controller {

	/**
	 * Namespace for REST routes.
	 *
	 * @var string
	 */
	protected $namespace = 'site-checkup-pro/v1';

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Rest_Controller|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Rest_Controller
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
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// 1. Get All Tasks + Summary
		register_rest_route( $this->namespace, '/tasks', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_tasks' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 2. Run Single Task
		register_rest_route( $this->namespace, '/tasks/(?P<id>[a-zA-Z0-9_-]+)/run', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'run_task' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'id' => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
			),
		) );

		// 3. Undo Single Task
		register_rest_route( $this->namespace, '/tasks/(?P<id>[a-zA-Z0-9_-]+)/undo', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'undo_task' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'id' => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
			),
		) );

		// 4. Get Diff Preview for Task
		register_rest_route( $this->namespace, '/tasks/(?P<id>[a-zA-Z0-9_-]+)/diff', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_diff' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'id' => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
			),
		) );

		// 5. Update Task Status / Notes / Reminder (Level C)
		register_rest_route( $this->namespace, '/tasks/(?P<id>[a-zA-Z0-9_-]+)/status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_task_status' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'id' => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
			),
		) );

		// 6. Confirm Manual Backup
		register_rest_route( $this->namespace, '/tasks/confirm-backup', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'confirm_backup' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 7. Update Baseline Snapshot
		register_rest_route( $this->namespace, '/tasks/update-baseline', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_baseline' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 8. Change Login Slug
		register_rest_route( $this->namespace, '/tasks/set-login-slug', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'set_login_slug' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 9. Restore Plugin from Zip Backup
		register_rest_route( $this->namespace, '/tasks/restore-plugin', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'restore_plugin' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 10. Get Audit Log
		register_rest_route( $this->namespace, '/audit-log', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_audit_log' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 11. Get Report Data
		register_rest_route( $this->namespace, '/report', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_report' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );
	}

	/**
	 * Permission check callback: Hard-require manage_options.
	 *
	 * @return bool|WP_Error
	 */
	public function check_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have administrative permissions to access Site Checkup Pro.', 'site-checkup-pro' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Get tasks catalog with live status and section groupings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_tasks( $request ) {
		global $wpdb;

		$registry = WPSG_Task_Registry::get_instance();
		$tasks    = $registry->get_all();

		$status_table = $wpdb->prefix . 'wpsg_task_status';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$db_rows = $wpdb->get_results( "SELECT * FROM {$status_table}", OBJECT_K );

		$serialized_tasks = array();
		$safe_instant_ids = array();
		$done_count       = 0;
		$total_count      = 0;

		foreach ( $tasks as $id => $task ) {
			if ( 'report' === $task->section ) {
				continue;
			}

			$total_count++;
			$db_record = isset( $db_rows[ $id ] ) ? $db_rows[ $id ] : null;
			$task_data = $task->to_array( $db_record );

			$serialized_tasks[] = $task_data;

			if ( 'done' === $task_data['status'] ) {
				$done_count++;
			}

			// Collect IDs for client-driven "Run All Safe" batch
			if ( 'A' === $task->automation_level && 'instant' === $task->sub_type && 'done' !== $task_data['status'] ) {
				$safe_instant_ids[] = $task->id;
			}
		}

		$server_type       = WPSG_Htaccess_Manager::get_server_type();
		$supports_htaccess = WPSG_Htaccess_Manager::supports_htaccess();
		$backup_status     = WPSG_Backup_Guard::get_backup_status();

		return rest_ensure_response( array(
			'tasks'             => $serialized_tasks,
			'sections'          => WPSG_Task_Registry::$sections,
			'safe_instant_ids'  => $safe_instant_ids,
			'total_count'       => $total_count,
			'done_count'        => $done_count,
			'sop_coverage_pct'  => ( $total_count > 0 ) ? round( ( $done_count / $total_count ) * 100 ) : 0,
			'server_type'       => $server_type,
			'supports_htaccess' => $supports_htaccess,
			'backup_status'     => $backup_status,
		) );
	}

	/**
	 * Run single task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function run_task( $request ) {
		$task_id = $request->get_param( 'id' );
		$result  = WPSG_Task_Runner::run( $task_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Undo single task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function undo_task( $request ) {
		$task_id = $request->get_param( 'id' );
		$result  = WPSG_Task_Runner::undo( $task_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Get diff preview for file tasks.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_diff( $request ) {
		$task_id = $request->get_param( 'id' );
		$task    = WPSG_Task_Registry::get_instance()->get( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Task not found.', 'site-checkup-pro' ), array( 'status' => 404 ) );
		}

		$diff = $task->get_diff();

		return rest_ensure_response( array(
			'task_id' => $task_id,
			'diff'    => $diff,
		) );
	}

	/**
	 * Update task status, note, or reminder (Level C).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_task_status( $request ) {
		$task_id          = $request->get_param( 'id' );
		$status           = $request->get_param( 'status' );
		$note             = $request->get_param( 'note' );
		$next_reminder_at = $request->get_param( 'next_reminder_at' );

		$task = WPSG_Task_Registry::get_instance()->get( $task_id );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Task not found.', 'site-checkup-pro' ), array( 'status' => 404 ) );
		}

		$updated = WPSG_Task_Runner::update_db_status(
			$task_id,
			$status ? $status : 'done',
			$task->automation_level,
			$note,
			$next_reminder_at
		);

		WPSG_Audit_Log::log(
			$task_id,
			'mark_done',
			null,
			array( 'status' => $status, 'note' => $note ),
			'success',
			__( 'Manual SOP checklist item updated.', 'site-checkup-pro' )
		);

		return rest_ensure_response( array(
			'success' => $updated,
			'task_id' => $task_id,
			'status'  => $status,
		) );
	}

	/**
	 * Confirm manual backup.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function confirm_backup( $request ) {
		WPSG_Backup_Guard::confirm_manual_backup();

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Manual backup confirmation recorded.', 'site-checkup-pro' ),
		) );
	}

	/**
	 * Update baseline snapshot.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function update_baseline( $request ) {
		$updated = WPSG_Scanner::update_baseline();

		WPSG_Audit_Log::log(
			'baseline_update',
			'update_baseline',
			null,
			null,
			'success',
			__( 'Security baseline snapshot updated with current administrators and options.', 'site-checkup-pro' )
		);

		return rest_ensure_response( array(
			'success' => $updated,
			'message' => __( 'Baseline updated successfully.', 'site-checkup-pro' ),
		) );
	}

	/**
	 * Set login slug with typed 'CHANGE' verification.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function set_login_slug( $request ) {
		$slug    = $request->get_param( 'slug' );
		$confirm = $request->get_param( 'confirm' );

		$result = WPSG_Login_Renamer::set_login_slug( $slug, $confirm );

		if ( ! empty( $result['success'] ) ) {
			WPSG_Audit_Log::log(
				'login_url_rename',
				'rename_login',
				null,
				array( 'slug_configured' => true ),
				'success',
				sprintf( __( 'Custom login URL activated: /%s/', 'site-checkup-pro' ), $result['slug'] )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Restore deleted plugin from zip backup.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function restore_plugin( $request ) {
		$slug   = $request->get_param( 'slug' );
		$result = WPSG_Plugin_Integrity::restore_plugin( $slug );

		return rest_ensure_response( $result );
	}

	/**
	 * Get audit log entries.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_audit_log( $request ) {
		$limit  = $request->get_param( 'limit' ) ? absint( $request->get_param( 'limit' ) ) : 50;
		$offset = $request->get_param( 'offset' ) ? absint( $request->get_param( 'offset' ) ) : 0;

		$logs = WPSG_Audit_Log::get_logs( $limit, $offset );

		return rest_ensure_response( array(
			'logs' => $logs,
		) );
	}

	/**
	 * Get full report data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_report( $request ) {
		$data = WPSG_Report_Generator::get_report_data();
		return rest_ensure_response( $data );
	}
}
