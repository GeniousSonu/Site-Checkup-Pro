<?php
/**
 * REST API Controller
 *
 * Exposes endpoints for task listing, running, undoing, diff previews,
 * baseline updates, reports, re-authentication, session control, scoped settings, and CSP reporting.
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

if ( ! class_exists( 'WP_REST_Controller' ) ) {
	$wpsg_rest_controller_file = defined( 'ABSPATH' )
		? ABSPATH . ( defined( 'WPINC' ) ? WPINC : 'wp-includes' ) . '/rest-api/endpoints/class-wp-rest-controller.php'
		: '';
	if ( $wpsg_rest_controller_file && file_exists( $wpsg_rest_controller_file ) ) {
		require_once $wpsg_rest_controller_file;
	}
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
		if ( function_exists( 'did_action' ) && did_action( 'rest_api_init' ) ) {
			$this->register_routes();
		} else {
			add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		}
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
				'id'           => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
				'reauth_token' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// 3. Undo Single Task
		register_rest_route( $this->namespace, '/tasks/(?P<id>[a-zA-Z0-9_-]+)/undo', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'undo_task' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'id'           => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
				'reauth_token' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// 3b. Verify Single Task (Standing "Check Now" Action)
		register_rest_route( $this->namespace, '/tasks/(?P<id>[a-zA-Z0-9_-]+)/verify', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'verify_task' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'id' => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
			),
		) );

		// 3c. Diagnostic Probe for Fresh Runtime Evaluation
		register_rest_route( $this->namespace, '/diagnostic-probe', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_diagnostic_probe' ),
			'permission_callback' => array( $this, 'check_probe_permissions' ),
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

		// 5. Update Task Status / Notes / Reminder (Level C only)
		register_rest_route( $this->namespace, '/tasks/(?P<id>[a-zA-Z0-9_-]+)/status', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'update_task_status' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'id'               => array( 'sanitize_callback' => 'sanitize_key', 'required' => true ),
				'status'           => array( 'sanitize_callback' => 'sanitize_key' ),
				'note'             => array( 'sanitize_callback' => 'sanitize_textarea_field' ),
				'next_reminder_at' => array( 'sanitize_callback' => 'sanitize_text_field' ),
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
			'args'                => array(
				'slug'    => array( 'sanitize_callback' => 'sanitize_title', 'required' => true ),
				'confirm' => array( 'sanitize_callback' => 'sanitize_text_field', 'required' => true ),
			),
		) );

		// 9. Quarantine & Delete Unwanted Plugin with Zip Backup
		register_rest_route( $this->namespace, '/tasks/delete-plugin', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'delete_plugin' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'slug'        => array( 'sanitize_callback' => 'sanitize_file_name', 'required' => true ),
				'plugin_path' => array( 'sanitize_callback' => 'sanitize_text_field', 'required' => false ),
			),
		) );

		// 10. Restore Plugin from Zip Backup
		register_rest_route( $this->namespace, '/tasks/restore-plugin', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'restore_plugin' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'slug' => array( 'sanitize_callback' => 'sanitize_file_name', 'required' => true ),
			),
		) );

		// 10. Get Audit Log
		register_rest_route( $this->namespace, '/audit-log', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_audit_log' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'limit'  => array( 'sanitize_callback' => 'absint', 'default' => 50 ),
				'offset' => array( 'sanitize_callback' => 'absint', 'default' => 0 ),
			),
		) );

		// 11. Get Report Data
		register_rest_route( $this->namespace, '/report', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_report' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 12. Session Re-Authentication Endpoint
		register_rest_route( $this->namespace, '/reauth', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'reauth' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'password' => array( 'required' => true, 'type' => 'string' ),
			),
		) );

		// 13. Active Sessions List
		register_rest_route( $this->namespace, '/sessions', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_sessions' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'user_id' => array( 'sanitize_callback' => 'absint' ),
			),
		) );

		// 14. Destroy Specific Session
		register_rest_route( $this->namespace, '/sessions/destroy', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'destroy_session' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'verifier' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'user_id'  => array( 'sanitize_callback' => 'absint' ),
			),
		) );

		// 15. Destroy All Other Sessions
		register_rest_route( $this->namespace, '/sessions/destroy-others', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'destroy_other_sessions' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'user_id' => array( 'sanitize_callback' => 'absint' ),
			),
		) );

		// 16. Application Passwords List
		register_rest_route( $this->namespace, '/app-passwords', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_app_passwords' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 17. Revoke Application Password
		register_rest_route( $this->namespace, '/app-passwords/revoke', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'revoke_app_password' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'uuid'         => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
				'user_id'      => array( 'required' => true, 'sanitize_callback' => 'absint' ),
				'reauth_token' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// 18. Public CSP Violation Report Collector (Rate-Limited, Size-Capped)
		register_rest_route( $this->namespace, '/csp-report', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'receive_csp_report' ),
			'permission_callback' => '__return_true',
		) );

		// 19. Get CSP Violation Reports
		register_rest_route( $this->namespace, '/csp-reports', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_csp_reports' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 20. Dismiss Notice from Notice Inbox
		register_rest_route( $this->namespace, '/notices/dismiss', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'dismiss_notice' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'hash' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// 21. Reset Dismissed Notices
		register_rest_route( $this->namespace, '/notices/reset', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'reset_notices' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 22. Get Core & Uploads Integrity Status
		register_rest_route( $this->namespace, '/integrity/status', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_integrity_status' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 23. Run Full Integrity Scan
		register_rest_route( $this->namespace, '/integrity/scan', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'run_integrity_scan' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 24. Scoped Plugin Settings (Strict Key Allowlist)
		register_rest_route( $this->namespace, '/settings', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'save_settings' ),
				'permission_callback' => array( $this, 'check_permissions' ),
			),
		) );

		// 25. Vulnerability Intelligence Scan
		register_rest_route( $this->namespace, '/vulnerabilities', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_vulnerabilities' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( $this->namespace, '/vulnerabilities/scan', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'scan_vulnerabilities' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		register_rest_route( $this->namespace, '/vulnerabilities/verify-fix', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'verify_vulnerability_fix' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'slug' => array(
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_key',
				),
				'type' => array(
					'required'          => false,
					'type'              => 'string',
					'default'           => 'plugin',
					'sanitize_callback' => 'sanitize_key',
				),
			),
		) );

		// 26. Generate RFC 9116 security.txt
		register_rest_route( $this->namespace, '/security-txt/generate', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'generate_security_txt' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 27. Dismiss Review Prompt
		register_rest_route( $this->namespace, '/review-prompt/dismiss', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'dismiss_review_prompt' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 28. REST API Security Auditor
		register_rest_route( $this->namespace, '/developer/rest-audit', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_rest_audit' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 29. Developer Diagnostic Snapshot
		register_rest_route( $this->namespace, '/developer/diagnostic-snapshot', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_diagnostic_snapshot' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 30. WP-Cron Scheduled Events Audit
		register_rest_route( $this->namespace, '/developer/cron-audit', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_cron_audit' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 31. Database Health Scanner
		register_rest_route( $this->namespace, '/developer/db-health', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_db_health' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 32. Database Health Protected Cleanup (Level B)
		register_rest_route( $this->namespace, '/developer/db-health/clean', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'clean_db_health' ),
			'permission_callback' => array( $this, 'check_permissions' ),
			'args'                => array(
				'type'         => array( 'sanitize_callback' => 'sanitize_key', 'default' => 'all' ),
				'reauth_token' => array( 'sanitize_callback' => 'sanitize_text_field' ),
			),
		) );

		// 33. Migration Readiness Check
		register_rest_route( $this->namespace, '/developer/migration-readiness', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_migration_readiness' ),
			'permission_callback' => array( $this, 'check_permissions' ),
		) );

		// 34. Weekly Changelog Digest
		register_rest_route( $this->namespace, '/developer/changelog-digest', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_changelog_digest' ),
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
	 * Verify an optional action-specific nonce for defense-in-depth.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @param string          $action  Action name.
	 * @return bool|WP_Error
	 */
	private function verify_action_nonce( $request, $action ) {
		$nonce = $request->get_header( 'X-WPSG-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpsg_nonce' );
		}

		if ( $nonce && ! wp_verify_nonce( $nonce, 'wpsg_' . $action ) ) {
			return new WP_Error(
				'invalid_action_nonce',
				__( 'Invalid or expired security token for this action.', 'site-checkup-pro' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get tasks catalog array with live status and section groupings.
	 *
	 * @return array
	 */
	public function get_tasks_catalog() {
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
			try {
				$task_data = $task->to_array( $db_record );
			} catch ( \Throwable $e ) {
				error_log( sprintf( '[Site Checkup Pro] Error serializing task "%s": %s in %s:%d', $task->id, $e->getMessage(), $e->getFile(), $e->getLine() ) );
				$task_data = array(
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
					'live_message'     => 'Evaluation notice: ' . $e->getMessage(),
					'is_na'            => false,
				);
			}

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

		return array(
			'tasks'             => $serialized_tasks,
			'sections'          => WPSG_Task_Registry::$sections,
			'safe_instant_ids'  => $safe_instant_ids,
			'total_count'       => $total_count,
			'done_count'        => $done_count,
			'sop_coverage_pct'  => ( $total_count > 0 ) ? round( ( $done_count / $total_count ) * 100 ) : 0,
			'server_type'       => $server_type,
			'supports_htaccess' => $supports_htaccess,
			'backup_status'     => $backup_status,
		);
	}

	/**
	 * Get tasks catalog with live status and section groupings (REST callback).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_tasks( $request ) {
		return rest_ensure_response( $this->get_tasks_catalog() );
	}

	/**
	 * Run single task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_task( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'run_task' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		if ( class_exists( 'WPSG_Login_Guard' ) ) {
			$allowed = WPSG_Login_Guard::check_rate_limit( 'task_run_' . get_current_user_id(), 60, 60 );
			if ( ! $allowed ) {
				return new WP_Error(
					'rate_limited',
					__( 'Too many task execution requests. Please pause a moment.', 'site-checkup-pro' ),
					array( 'status' => 429 )
				);
			}
		}

		$task_id       = $request->get_param( 'id' );
		$reauth_token  = $request->get_header( 'X-WPSG-Reauth' );
		if ( ! $reauth_token ) {
			$reauth_token = $request->get_param( 'reauth_token' );
		}
		$force_refresh = true; // An explicit run/re-scan action should always evaluate live
		if ( null !== $request->get_param( 'force' ) ) {
			$force_refresh = (bool) $request->get_param( 'force' );
		} elseif ( null !== $request->get_param( 'force_refresh' ) ) {
			$force_refresh = (bool) $request->get_param( 'force_refresh' );
		}

		$result = WPSG_Task_Runner::run( $task_id, $reauth_token, $force_refresh );

		return rest_ensure_response( $result );
	}

	/**
	 * Undo single task.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function undo_task( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'undo_task' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$task_id      = $request->get_param( 'id' );
		$reauth_token = $request->get_header( 'X-WPSG-Reauth' );
		if ( ! $reauth_token ) {
			$reauth_token = $request->get_param( 'reauth_token' );
		}

		$result = WPSG_Task_Runner::undo( $task_id, $reauth_token );

		return rest_ensure_response( $result );
	}

	/**
	 * Verify an individual task on-demand (Standing "Check Now" action).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_task( $request ) {
		$task_id = $request->get_param( 'id' );
		$task    = WPSG_Task_Registry::get_instance()->get( $task_id );

		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Task not found.', 'site-checkup-pro' ), array( 'status' => 404 ) );
		}

		$verification = class_exists( 'WPSG_HTTP_Verifier' )
			? WPSG_HTTP_Verifier::verify_task( $task_id, true )
			: array( 'verified' => false, 'status' => 'pending', 'message' => '' );

		$new_status = ! empty( $verification['verified'] ) ? 'done' : ( ! empty( $verification['status'] ) ? $verification['status'] : 'applied_unverified' );

		// Update database status
		WPSG_Task_Runner::update_db_status(
			$task_id,
			$new_status,
			$task->automation_level,
			null,
			null,
			array( 'verification' => $verification )
		);

		return rest_ensure_response( array(
			'success'      => ! empty( $verification['verified'] ),
			'task_id'      => $task_id,
			'status'       => $new_status,
			'message'      => ! empty( $verification['message'] ) ? $verification['message'] : '',
			'live_message' => ! empty( $verification['message'] ) ? $verification['message'] : '',
			'verified'     => ! empty( $verification['verified'] ),
		) );
	}

	/**
	 * Permission check for diagnostic probe endpoint.
	 *
	 * Allows administrator users OR requests carrying a valid internal self-verification token.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function check_probe_permissions( $request ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( class_exists( 'WPSG_HTTP_Verifier' ) && WPSG_HTTP_Verifier::is_self_verification_request() ) {
			return true;
		}

		return new WP_Error( 'rest_forbidden', __( 'Access denied.', 'site-checkup-pro' ), array( 'status' => 403 ) );
	}

	/**
	 * Fresh-process runtime diagnostic probe for PHP constants and login URL renames.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_diagnostic_probe( $request ) {
		return rest_ensure_response( array(
			'success'   => true,
			'constants' => array(
				'DISALLOW_FILE_EDIT' => defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT,
				'WP_DEBUG_DISPLAY'   => defined( 'WP_DEBUG_DISPLAY' ) && ! WP_DEBUG_DISPLAY,
			),
			'login_slug' => get_option( 'wpsg_login_slug', '' ),
			'timestamp'  => time(),
		) );
	}

	/**
	 * Get diff preview for file tasks.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
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
	 * Update task status, note, or reminder (Level C ONLY).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_task_status( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'update_status' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$task_id          = $request->get_param( 'id' );
		$status           = $request->get_param( 'status' );
		$note             = $request->get_param( 'note' );
		$next_reminder_at = $request->get_param( 'next_reminder_at' );

		$task = WPSG_Task_Registry::get_instance()->get( $task_id );
		if ( ! $task ) {
			return new WP_Error( 'not_found', __( 'Task not found.', 'site-checkup-pro' ), array( 'status' => 404 ) );
		}

		// Security constraint: Only Level C manual checklist tasks may have their status updated directly!
		if ( 'C' !== $task->automation_level ) {
			return new WP_Error(
				'invalid_level',
				__( 'Automated Level A and guided Level B tasks cannot be manually marked done. They must be executed or verified through their respective procedures.', 'site-checkup-pro' ),
				array( 'status' => 403 )
			);
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
	 * @return WP_REST_Response|WP_Error
	 */
	public function confirm_backup( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'confirm_backup' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

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
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_baseline( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'update_baseline' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

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
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_login_slug( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'set_login_slug' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		// Re-authentication check for state-changing login URL rename
		$reauth_token = $request->get_header( 'X-WPSG-Reauth' );
		if ( ! $reauth_token ) {
			$reauth_token = $request->get_param( 'reauth_token' );
		}
		if ( class_exists( 'WPSG_Session_Manager' ) && ! WPSG_Session_Manager::validate_reauth_token( $reauth_token ) ) {
			return rest_ensure_response( array(
				'success'         => false,
				'reauth_required' => true,
				'message'         => __( 'Administrator password confirmation is required before changing the login URL.', 'site-checkup-pro' ),
			) );
		}

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
				/* translators: %s: custom login slug */
				sprintf( __( 'Custom login URL activated: /%s/', 'site-checkup-pro' ), $result['slug'] )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Quarantine and delete an unwanted plugin with zip backup.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_plugin( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'delete_plugin' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		// Re-authentication check for destructive plugin removal
		$reauth_token = $request->get_header( 'X-WPSG-Reauth' );
		if ( ! $reauth_token ) {
			$reauth_token = $request->get_param( 'reauth_token' );
		}
		if ( class_exists( 'WPSG_Session_Manager' ) && ! WPSG_Session_Manager::validate_reauth_token( $reauth_token ) ) {
			return rest_ensure_response( array(
				'success'         => false,
				'reauth_required' => true,
				'message'         => __( 'Administrator password confirmation is required before deleting plugins.', 'site-checkup-pro' ),
			) );
		}

		$slug        = $request->get_param( 'slug' );
		$plugin_path = $request->get_param( 'plugin_path' );

		if ( empty( $plugin_path ) ) {
			if ( ! function_exists( 'get_plugins' ) ) {
				if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
			}
			$all_plugins = function_exists( 'get_plugins' ) ? get_plugins() : array();
			foreach ( array_keys( $all_plugins ) as $path ) {
				if ( dirname( $path ) === $slug || basename( $path, '.php' ) === $slug ) {
					$plugin_path = $path;
					break;
				}
			}
			if ( empty( $plugin_path ) ) {
				$plugin_path = $slug . '/' . $slug . '.php';
			}
		}

		$result = WPSG_Plugin_Integrity::safe_delete_plugin( $plugin_path );

		if ( ! empty( $result['success'] ) ) {
			WPSG_Audit_Log::log(
				'detect_unwanted_plugins',
				'delete_plugin',
				'attention',
				'done',
				'success',
				sprintf( 'Safely archived and deleted plugin: %s', $slug )
			);
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Restore deleted plugin from zip backup.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore_plugin( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'restore_plugin' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

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

	/**
	 * Session Re-Authentication.
	 *
	 * Shares the exact same lockout pipeline and counter as wp-login!
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reauth( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'reauth' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$password = $request->get_param( 'password' );
		if ( empty( $password ) || ! is_string( $password ) ) {
			return new WP_Error( 'missing_password', __( 'Password is required.', 'site-checkup-pro' ), array( 'status' => 400 ) );
		}

		if ( ! class_exists( 'WPSG_Session_Manager' ) ) {
			return new WP_Error( 'unavailable', __( 'Session manager is not available.', 'site-checkup-pro' ), array( 'status' => 500 ) );
		}

		$result = WPSG_Session_Manager::verify_password_and_grant_reauth( $password );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get active sessions for a user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_sessions( $request ) {
		$current_user_id = get_current_user_id();
		$target_user_id  = $request->get_param( 'user_id' ) ? absint( $request->get_param( 'user_id' ) ) : $current_user_id;

		// IDOR guard: viewing another user's sessions requires edit_users capability
		if ( $target_user_id !== $current_user_id && ! current_user_can( 'edit_users' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You cannot inspect sessions for other users.', 'site-checkup-pro' ), array( 'status' => 403 ) );
		}

		$sessions = WPSG_Session_Manager::get_user_sessions( $target_user_id );

		return rest_ensure_response( array(
			'user_id'  => $target_user_id,
			'sessions' => $sessions,
		) );
	}

	/**
	 * Destroy a specific session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy_session( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'destroy_session' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$current_user_id = get_current_user_id();
		$target_user_id  = $request->get_param( 'user_id' ) ? absint( $request->get_param( 'user_id' ) ) : $current_user_id;
		$verifier        = sanitize_text_field( $request->get_param( 'verifier' ) );

		if ( empty( $verifier ) ) {
			return new WP_Error( 'missing_verifier', __( 'Session verifier required.', 'site-checkup-pro' ), array( 'status' => 400 ) );
		}

		$result = WPSG_Session_Manager::destroy_session( $target_user_id, $verifier );

		return rest_ensure_response( $result );
	}

	/**
	 * Destroy all other sessions for a user.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function destroy_other_sessions( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'destroy_session' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$current_user_id = get_current_user_id();
		$target_user_id  = $request->get_param( 'user_id' ) ? absint( $request->get_param( 'user_id' ) ) : $current_user_id;

		$result = WPSG_Session_Manager::destroy_other_sessions( $target_user_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Get application passwords list.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_app_passwords( $request ) {
		$passwords = WPSG_App_Password_Manager::get_all_application_passwords();

		return rest_ensure_response( array(
			'passwords' => $passwords,
		) );
	}

	/**
	 * Revoke an application password.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function revoke_app_password( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'revoke_app_pass' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$user_id      = absint( $request->get_param( 'user_id' ) );
		$uuid         = sanitize_text_field( $request->get_param( 'uuid' ) );
		$reauth_token = $request->get_header( 'X-WPSG-Reauth' );
		if ( ! $reauth_token ) {
			$reauth_token = $request->get_param( 'reauth_token' );
		}

		$result = WPSG_App_Password_Manager::revoke_password( $user_id, $uuid, $reauth_token );

		return rest_ensure_response( $result );
	}

	/**
	 * Public collector for CSP Report-Only violation reports.
	 *
	 * Hardened: rate-limited by IP, size-capped to 10KB, strict JSON parsing.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function receive_csp_report( $request ) {
		$client_ip = class_exists( 'WPSG_Login_Guard' ) ? WPSG_Login_Guard::get_client_ip() : '127.0.0.1';

		// Rate limit: max 60 reports per minute per IP
		if ( class_exists( 'WPSG_Login_Guard' ) ) {
			$allowed = WPSG_Login_Guard::check_rate_limit( 'csp_' . $client_ip, 60, 60 );
			if ( ! $allowed ) {
				return new WP_Error(
					'rate_limited',
					__( 'Too many reports from this IP.', 'site-checkup-pro' ),
					array( 'status' => 429 )
				);
			}
		}

		$body = $request->get_body();

		// Hard payload size cap: 10KB
		if ( strlen( $body ) > 10240 ) {
			return new WP_Error(
				'payload_too_large',
				__( 'CSP report payload exceeds 10KB size limit.', 'site-checkup-pro' ),
				array( 'status' => 413 )
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_json',
				__( 'Invalid JSON payload.', 'site-checkup-pro' ),
				array( 'status' => 400 )
			);
		}

		$violation = isset( $data['csp-report'] ) && is_array( $data['csp-report'] ) ? $data['csp-report'] : $data;

		if ( class_exists( 'WPSG_Fingerprint_Guard' ) ) {
			WPSG_Fingerprint_Guard::record_csp_violation( $violation );
		}

		return rest_ensure_response( array( 'received' => true ) );
	}

	/**
	 * Get recorded CSP violation reports.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_csp_reports( $request ) {
		$reports = class_exists( 'WPSG_Fingerprint_Guard' ) ? WPSG_Fingerprint_Guard::get_csp_violations() : array();

		return rest_ensure_response( array(
			'reports' => $reports,
		) );
	}

	/**
	 * Dismiss a notice from the Notice Inbox.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function dismiss_notice( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'dismiss_notice' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$hash    = sanitize_text_field( $request->get_param( 'hash' ) );
		$success = class_exists( 'WPSG_Notice_Inbox' ) ? WPSG_Notice_Inbox::dismiss_notice( $hash ) : false;

		return rest_ensure_response( array( 'success' => $success ) );
	}

	/**
	 * Reset all dismissed notices.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function reset_notices( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'dismiss_notice' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$success = class_exists( 'WPSG_Notice_Inbox' ) ? WPSG_Notice_Inbox::reset_dismissed_notices() : false;

		return rest_ensure_response( array( 'success' => $success ) );
	}

	/**
	 * Get core checksums and uploads executable scan status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_integrity_status( $request ) {
		$checksums = class_exists( 'WPSG_Integrity_Monitor' ) ? WPSG_Integrity_Monitor::check_core_checksums( false ) : array();
		$execs     = class_exists( 'WPSG_Integrity_Monitor' ) ? WPSG_Integrity_Monitor::scan_uploads_for_executables() : array();

		return rest_ensure_response( array(
			'checksums'   => $checksums,
			'executables' => $execs,
		) );
	}

	/**
	 * Trigger fresh integrity scans.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_integrity_scan( $request ) {
		$nonce_check = $this->verify_action_nonce( $request, 'integrity_scan' );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$checksums = class_exists( 'WPSG_Integrity_Monitor' ) ? WPSG_Integrity_Monitor::check_core_checksums( true ) : array();
		$execs     = class_exists( 'WPSG_Integrity_Monitor' ) ? WPSG_Integrity_Monitor::scan_uploads_for_executables() : array();

		return rest_ensure_response( array(
			'checksums'   => $checksums,
			'executables' => $execs,
		) );
	}

	/**
	 * Retrieve saved plugin settings.
	 * Redacts sensitive API keys for safe display.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings( $request ) {
		$settings = get_option( 'wpsg_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$redacted_key = '';
		if ( ! empty( $settings['patchstack_api_key'] ) ) {
			$len = strlen( $settings['patchstack_api_key'] );
			$redacted_key = ( $len > 8 )
				? substr( $settings['patchstack_api_key'], 0, 4 ) . str_repeat( '•', $len - 8 ) . substr( $settings['patchstack_api_key'], -4 )
				: '••••••••';
		}

		return rest_ensure_response( array(
			'success'  => true,
			'settings' => array(
				'patchstack_api_key_masked' => $redacted_key,
				'has_patchstack_key'        => ! empty( $settings['patchstack_api_key'] ),
				'patchstack_optin'          => ! empty( $settings['patchstack_optin'] ),
				'webhook_url'               => isset( $settings['webhook_url'] ) ? $settings['webhook_url'] : '',
				'webhook_optin'             => ! empty( $settings['webhook_optin'] ),
				'incident_contact_name'     => isset( $settings['incident_contact_name'] ) ? $settings['incident_contact_name'] : '',
				'incident_contact_email'    => isset( $settings['incident_contact_email'] ) ? $settings['incident_contact_email'] : '',
				'incident_contact_phone'    => isset( $settings['incident_contact_phone'] ) ? $settings['incident_contact_phone'] : '',
				'incident_contact_notes'    => isset( $settings['incident_contact_notes'] ) ? $settings['incident_contact_notes'] : '',
				'agency_name'               => isset( $settings['agency_name'] ) ? $settings['agency_name'] : '',
				// Hosting Panel Integration (Tier 1)
				'hosting_panel_detected'    => class_exists( 'WPSG_Hosting_Panel_Bridge' ) ? WPSG_Hosting_Panel_Bridge::detect_panel() : array(),
				'hosting_panel_type'        => isset( $settings['hosting_panel_type'] ) ? $settings['hosting_panel_type'] : 'auto',
				'hosting_panel_url'         => isset( $settings['hosting_panel_url'] ) ? $settings['hosting_panel_url'] : '',
				'has_hosting_panel_key'     => ! empty( $settings['hosting_panel_key_enc'] ),
				'hosting_panel_has_token'   => ! empty( $settings['hosting_panel_key_enc'] ),
				'hosting_panel_key_masked'  => ! empty( $settings['hosting_panel_key_enc'] ) ? '••••••••' : '',
				'hosting_panel_optin'       => ! empty( $settings['hosting_panel_optin'] ),
				'supports_htaccess'         => class_exists( 'WPSG_Htaccess_Manager' ) ? WPSG_Htaccess_Manager::supports_htaccess() : false,
				'has_nginx_tier1'           => class_exists( 'WPSG_Htaccess_Manager' ) ? WPSG_Htaccess_Manager::has_nginx_tier1() : false,
				'has_nginx_tier2'           => class_exists( 'WPSG_Htaccess_Manager' ) ? WPSG_Htaccess_Manager::has_nginx_tier2() : false,
			),
		) );
	}

	/**
	 * Save plugin settings with a hardcoded allowlist and per-key sanitization.
	 * Strictly rejects mass-assignment and arbitrary keys.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_settings( $request ) {
		$allowlist = array(
			'patchstack_api_key'     => function( $v ) { return sanitize_text_field( trim( (string) $v ) ); },
			'patchstack_optin'       => function( $v ) { return ! empty( $v ) ? 1 : 0; },
			'webhook_url'            => function( $v ) { return esc_url_raw( trim( (string) $v ) ); },
			'webhook_optin'          => function( $v ) { return ! empty( $v ) ? 1 : 0; },
			'incident_contact_name'  => function( $v ) { return sanitize_text_field( trim( (string) $v ) ); },
			'incident_contact_email' => function( $v ) { return sanitize_email( trim( (string) $v ) ); },
			'incident_contact_phone' => function( $v ) {
				$clean = preg_replace( '/[^\+0-9\-\(\)\s]/', '', (string) $v );
				return substr( trim( $clean ), 0, 25 );
			},
			'incident_contact_notes' => function( $v ) { return sanitize_textarea_field( trim( (string) $v ) ); },
			'agency_name'            => function( $v ) { return sanitize_text_field( trim( (string) $v ) ); },
			'hosting_panel_type'     => function( $v ) { return sanitize_key( trim( (string) $v ) ); },
			'hosting_panel_url'      => function( $v ) { return esc_url_raw( trim( (string) $v ) ); },
			'hosting_panel_optin'    => function( $v ) { return ! empty( $v ) ? 1 : 0; },
		);

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$current_settings = get_option( 'wpsg_settings', array() );
		if ( ! is_array( $current_settings ) ) {
			$current_settings = array();
		}

		foreach ( $allowlist as $key => $sanitizer ) {
			if ( array_key_exists( $key, $params ) ) {
				// Avoid wiping existing API key if masked string was submitted back
				if ( 'patchstack_api_key' === $key && false !== strpos( (string) $params[ $key ], '•' ) ) {
					continue;
				}
				$current_settings[ $key ] = call_user_func( $sanitizer, $params[ $key ] );
			}
		}

		// Handle hosting panel API key encrypted storage
		$token_input = null;
		if ( array_key_exists( 'hosting_panel_token', $params ) ) {
			$token_input = (string) $params['hosting_panel_token'];
		} elseif ( array_key_exists( 'hosting_panel_api_key', $params ) ) {
			$token_input = (string) $params['hosting_panel_api_key'];
		}

		if ( null !== $token_input ) {
			$raw_panel_key = trim( $token_input );
			if ( '' !== $raw_panel_key && false === strpos( $raw_panel_key, '•' ) ) {
				$current_settings['hosting_panel_key_enc'] = WPSG_Encryption::encrypt( $raw_panel_key );
			} elseif ( '' === $raw_panel_key ) {
				unset( $current_settings['hosting_panel_key_enc'] );
			}
		}

		update_option( 'wpsg_settings', $current_settings );

		// Invalidate vulnerability cache if API key was updated
		if ( array_key_exists( 'patchstack_api_key', $params ) ) {
			delete_transient( 'wpsg_vulnerability_cache' );
		}

		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Plugin settings updated successfully.', 'site-checkup-pro' ),
		) );
	}

	/**
	 * Retrieve cached vulnerability scan status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_vulnerabilities( $request ) {
		$data = class_exists( 'WPSG_Vulnerability_Checker' )
			? WPSG_Vulnerability_Checker::get_vulnerability_status( false )
			: array( 'status' => 'pending', 'message' => __( 'Vulnerability module unavailable.', 'site-checkup-pro' ) );

		return rest_ensure_response( $data );
	}

	/**
	 * Force fresh vulnerability scan.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function scan_vulnerabilities( $request ) {
		$data = class_exists( 'WPSG_Vulnerability_Checker' )
			? WPSG_Vulnerability_Checker::get_vulnerability_status( true )
			: array( 'status' => 'pending', 'message' => __( 'Vulnerability module unavailable.', 'site-checkup-pro' ) );

		return rest_ensure_response( $data );
	}

	/**
	 * Verify fix/remediation for a specific vulnerability finding.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function verify_vulnerability_fix( $request ) {
		$slug = $request->get_param( 'slug' );
		$type = $request->get_param( 'type' );

		if ( empty( $slug ) ) {
			return new WP_Error(
				'wpsg_missing_param',
				__( 'Component slug is required.', 'site-checkup-pro' ),
				array( 'status' => 400 )
			);
		}

		if ( ! class_exists( 'WPSG_Vulnerability_Checker' ) ) {
			return new WP_Error(
				'wpsg_module_missing',
				__( 'Vulnerability checker module unavailable.', 'site-checkup-pro' ),
				array( 'status' => 500 )
			);
		}

		$result = WPSG_Vulnerability_Checker::verify_fix( $slug, $type ? $type : 'plugin' );
		return rest_ensure_response( $result );
	}

	/**
	 * Generate RFC 9116 security.txt at /.well-known/security.txt.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function generate_security_txt( $request ) {
		$contact = $request->get_param( 'contact' );
		$result  = class_exists( 'WPSG_Security_Txt' )
			? WPSG_Security_Txt::generate( $contact )
			: array( 'success' => false, 'message' => __( 'Security.txt module unavailable.', 'site-checkup-pro' ) );

		return rest_ensure_response( $result );
	}

	/**
	 * Permanently dismiss the in-plugin review prompt.
	 *
	 * @return WP_REST_Response
	 */
	public function dismiss_review_prompt() {
		update_option( 'wpsg_review_prompt_dismissed', true );
		return rest_ensure_response( array(
			'success' => true,
			'message' => __( 'Review prompt dismissed.', 'site-checkup-pro' ),
		) );
	}

	/**
	 * Run REST API security audit.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_rest_audit( $request ) {
		if ( ! class_exists( 'WPSG_Rest_Auditor' ) ) {
			return new WP_Error( 'wpsg_class_missing', __( 'REST API Auditor component missing.', 'site-checkup-pro' ), array( 'status' => 500 ) );
		}

		$results = WPSG_Rest_Auditor::audit_routes();
		return rest_ensure_response( array(
			'success' => true,
			'data'    => $results,
		) );
	}

	/**
	 * Compile developer diagnostic snapshot.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_diagnostic_snapshot( $request ) {
		if ( ! class_exists( 'WPSG_Diagnostic_Snapshot' ) ) {
			return new WP_Error( 'wpsg_class_missing', __( 'Diagnostic Snapshot component missing.', 'site-checkup-pro' ), array( 'status' => 500 ) );
		}

		$snapshot = WPSG_Diagnostic_Snapshot::compile();
		return rest_ensure_response( array(
			'success' => true,
			'data'    => $snapshot,
		) );
	}

	/**
	 * Run WP-Cron scheduled events audit.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_cron_audit( $request ) {
		if ( ! class_exists( 'WPSG_Cron_Auditor' ) ) {
			return new WP_Error( 'wpsg_class_missing', __( 'Cron Auditor component missing.', 'site-checkup-pro' ), array( 'status' => 500 ) );
		}

		$audit = WPSG_Cron_Auditor::audit_cron_jobs();
		return rest_ensure_response( array(
			'success' => true,
			'data'    => $audit,
		) );
	}

	/**
	 * Run database health scan.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_db_health( $request ) {
		if ( ! class_exists( 'WPSG_Db_Health_Scanner' ) ) {
			return new WP_Error( 'wpsg_class_missing', __( 'Database Health Scanner component missing.', 'site-checkup-pro' ), array( 'status' => 500 ) );
		}

		$scan = WPSG_Db_Health_Scanner::scan();
		return rest_ensure_response( array(
			'success' => true,
			'data'    => $scan,
		) );
	}

	/**
	 * Execute protected database cleanup (Level B).
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function clean_db_health( $request ) {
		if ( ! class_exists( 'WPSG_Db_Health_Scanner' ) ) {
			return new WP_Error( 'wpsg_class_missing', __( 'Database Health Scanner component missing.', 'site-checkup-pro' ), array( 'status' => 500 ) );
		}

		$type = $request->get_param( 'type' );
		if ( empty( $type ) ) {
			$type = 'all';
		}

		$reauth_token = $request->get_header( 'X-WPSG-Reauth' );
		if ( empty( $reauth_token ) ) {
			$reauth_token = $request->get_param( 'reauth_token' );
		}

		$result = WPSG_Db_Health_Scanner::cleanup( $type, $reauth_token );
		if ( ! empty( $result['needs_reauth'] ) ) {
			return new WP_Error( 'wpsg_reauth_required', $result['message'], array( 'status' => 403, 'needs_reauth' => true ) );
		}
		if ( ! empty( $result['needs_backup'] ) ) {
			return new WP_Error( 'wpsg_backup_required', $result['message'], array( 'status' => 412, 'needs_backup' => true ) );
		}
		if ( empty( $result['success'] ) ) {
			return new WP_Error( 'wpsg_cleanup_failed', $result['message'], array( 'status' => 400 ) );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Run migration readiness scan.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_migration_readiness( $request ) {
		if ( ! class_exists( 'WPSG_Migration_Readiness' ) ) {
			return new WP_Error( 'wpsg_class_missing', __( 'Migration Readiness component missing.', 'site-checkup-pro' ), array( 'status' => 500 ) );
		}

		$scan = WPSG_Migration_Readiness::scan();
		return rest_ensure_response( array(
			'success' => true,
			'data'    => $scan,
		) );
	}

	/**
	 * Compile weekly changelog digest.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_changelog_digest( $request ) {
		if ( ! class_exists( 'WPSG_Changelog_Digest' ) ) {
			return new WP_Error( 'wpsg_class_missing', __( 'Changelog Digest component missing.', 'site-checkup-pro' ), array( 'status' => 500 ) );
		}

		$digest = WPSG_Changelog_Digest::compile_digest();
		return rest_ensure_response( array(
			'success' => true,
			'data'    => $digest,
		) );
	}
}
