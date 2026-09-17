<?php
/**
* Task Value Object
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
 * Class WPSG_Task
 */
class WPSG_Task {

	/**
	 * Task ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * SOP Section key.
	 *
	 * @var string
	 */
	public $section;

	/**
	 * Human-readable title.
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Detailed description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Automation level: 'A' (Automated), 'B' (Guided), 'C' (Manual), 'D' (Report).
	 *
	 * @var string
	 */
	public $automation_level;

	/**
	 * Sub-type for batch runner gating:
	 * 'instant' (safe, non-file writing) vs 'writes_files' (edits config/.htaccess) vs 'guided' vs 'manual'.
	 *
	 * @var string
	 */
	public $sub_type;

	/**
	 * Does this task require a recent (24-48h) backup before running?
	 *
	 * @var bool
	 */
	public $requires_backup = false;

	/**
	 * Does this task require password re-authentication before executing?
	 *
	 * @var bool
	 */
	public $requires_reauth = false;

	/**
	 * Can this task be undone?
	 *
	 * @var bool
	 */
	public $has_undo = false;

	/**
	 * Does this task support a pre-execution diff preview?
	 *
	 * @var bool
	 */
	public $has_diff = false;

	/**
	 * Run callback.
	 *
	 * @var callable
	 */
	protected $run_callback;

	/**
	 * Undo callback.
	 *
	 * @var callable
	 */
	protected $undo_callback;

	/**
	 * Status callback.
	 *
	 * @var callable
	 */
	protected $status_callback;

	/**
	 * Diff preview callback.
	 *
	 * @var callable
	 */
	protected $diff_callback;

	/**
	 * Deep link / guide URL or action for Level B tasks.
	 *
	 * @var string|array
	 */
	public $guide_data = array();

	/**
	 * Equivalent Nginx configuration snippet (for .htaccess tasks).
	 *
	 * @var string
	 */
	public $nginx_snippet = '';

	/**
	 * Constructor.
	 *
	 * @param array $args Task arguments.
	 */
	public function __construct( array $args ) {
		$this->id               = isset( $args['id'] ) ? sanitize_key( $args['id'] ) : '';
		$this->section          = isset( $args['section'] ) ? sanitize_key( $args['section'] ) : 'general_check';
		$this->title            = isset( $args['title'] ) ? $args['title'] : '';
		$this->description      = isset( $args['description'] ) ? $args['description'] : '';
		$this->automation_level = isset( $args['automation_level'] ) ? strtoupper( $args['automation_level'] ) : 'A';
		$this->sub_type         = isset( $args['sub_type'] ) ? $args['sub_type'] : 'instant';
		$this->requires_backup  = ! empty( $args['requires_backup'] );
		$this->requires_reauth  = ! empty( $args['requires_reauth'] );
		$this->has_undo         = ! empty( $args['has_undo'] );
		$this->has_diff         = ! empty( $args['has_diff'] );
		$this->run_callback     = isset( $args['run_callback'] ) ? $args['run_callback'] : null;
		$this->undo_callback    = isset( $args['undo_callback'] ) ? $args['undo_callback'] : null;
		$this->status_callback  = isset( $args['status_callback'] ) ? $args['status_callback'] : null;
		$this->diff_callback    = isset( $args['diff_callback'] ) ? $args['diff_callback'] : null;
		$this->guide_data       = isset( $args['guide_data'] ) ? $args['guide_data'] : array();
		$this->nginx_snippet    = isset( $args['nginx_snippet'] ) ? $args['nginx_snippet'] : '';
	}

	/**
	 * Execute the task.
	 *
	 * @return array Array with 'success' (bool), 'message' (string), 'data' (array).
	 */
	public function run() {
		if ( is_callable( $this->run_callback ) ) {
			return call_user_func( $this->run_callback );
		}

		return array(
			'success' => false,
			'message' => __( 'No run callback registered for this task.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Undo the task action.
	 *
	 * @return array Array with 'success' (bool), 'message' (string).
	 */
	public function undo() {
		if ( is_callable( $this->undo_callback ) ) {
			return call_user_func( $this->undo_callback );
		}

		return array(
			'success' => false,
			'message' => __( 'No undo callback registered for this task.', 'site-checkup-pro' ),
		);
	}

	/**
	 * Get current live status of this task.
	 *
	 * @return array Status payload.
	 */
	public function get_live_status() {
		if ( is_callable( $this->status_callback ) ) {
			try {
				$res = call_user_func( $this->status_callback );
				return is_array( $res ) ? $res : array( 'status' => 'pending', 'message' => '' );
			} catch ( \Throwable $e ) {
				return array(
					'status'  => 'attention',
					'message' => sprintf( __( 'Check encountered an environmental notice: %s', 'site-checkup-pro' ), $e->getMessage() ),
				);
			}
		}

		return array(
			'status'  => 'pending',
			'message' => '',
		);
	}

	/**
	 * Get diff preview for file-modifying tasks.
	 *
	 * @return array Diff preview payload.
	 */
	public function get_diff() {
		if ( is_callable( $this->diff_callback ) ) {
			return call_user_func( $this->diff_callback );
		}

		return array(
			'file'    => '',
			'current' => '',
			'diff'    => '',
		);
	}

	/**
	 * Serialize task into array representation for UI/REST.
	 *
	 * @param array $db_status Current database status record if available.
	 * @return array
	 */
	public function to_array( $db_status = null ) {
		$live = $this->get_live_status();

		$status           = isset( $live['status'] ) ? $live['status'] : ( $db_status && isset( $db_status->status ) ? $db_status->status : 'pending' );
		$last_run_at      = $db_status && isset( $db_status->last_run_at ) ? $db_status->last_run_at : null;
		$note             = $db_status && isset( $db_status->note ) ? $db_status->note : '';
		$next_reminder_at = $db_status && isset( $db_status->next_reminder_at ) ? $db_status->next_reminder_at : null;
		$live_message     = isset( $live['message'] ) ? $live['message'] : '';
		$is_na            = isset( $live['is_na'] ) ? (bool) $live['is_na'] : false;

		// If manual task was marked done in database
		if ( $db_status && 'done' === $db_status->status && ( ! isset( $live['status'] ) || 'pending' === $live['status'] ) ) {
			$status = 'done';
		}

		// Overdue reminder check (e.g. 15-day credential rotation or 6-month GSC review)
		if ( $next_reminder_at && strtotime( $next_reminder_at ) <= time() ) {
			$status       = 'attention';
			$live_message = sprintf( __( 'Overdue reminder: scheduled review was due on %s.', 'site-checkup-pro' ), $next_reminder_at );
		}

		return array(
			'id'               => $this->id,
			'section'          => $this->section,
			'title'            => $this->title,
			'description'      => $this->description,
			'automation_level' => $this->automation_level,
			'sub_type'         => $this->sub_type,
			'requires_backup'  => $this->requires_backup,
			'requires_reauth'  => $this->requires_reauth,
			'has_undo'         => $this->has_undo,
			'has_diff'         => $this->has_diff,
			'guide_data'       => $this->guide_data,
			'nginx_snippet'    => $this->nginx_snippet,
			'status'           => $is_na ? 'not_applicable' : $status,
			'live_message'     => $live_message,
			'is_na'            => $is_na,
			'last_run_at'      => $last_run_at,
			'note'             => $note,
			'next_reminder_at' => $next_reminder_at,
		);
	}
}
