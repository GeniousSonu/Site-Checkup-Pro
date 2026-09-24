<?php
/**
 * Task Registry
 *
 * Registers all checklist tasks from the agency Security Check-up SOP.
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
 * Class WPSG_Task_Registry
 */
class WPSG_Task_Registry {

	/**
	 * Singleton instance.
	 *
	 * @var WPSG_Task_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registered tasks catalog.
	 *
	 * @var array<string, WPSG_Task>
	 */
	private $tasks = array();

	/**
	 * SOP Sections definition.
	 *
	 * @var array
	 */
	public static $sections = array(
		'security_update' => array(
			'label' => 'Security Update',
			'icon'  => 'dashicons-shield',
			'desc'  => 'Core security patches, automatic update policies, and foundational protection.',
		),
		'general_check'   => array(
			'label' => 'General Check',
			'icon'  => 'dashicons-visibility',
			'desc'  => 'Environment audit, rogue admin detection, database integrity, and file scans.',
		),
		'hardening'       => array(
			'label' => 'Hardening',
			'icon'  => 'dashicons-lock',
			'desc'  => 'Server security headers, .htaccess protection, wp-config restrictions, and login security.',
		),
		'seo_sop'         => array(
			'label' => 'SEO SOP',
			'icon'  => 'dashicons-search',
			'desc'  => 'Search console indexing integrity, robots.txt audit, and URL removal tracking.',
		),
		'regular_checks'      => array(
			'label' => 'Regular Checks',
			'icon'  => 'dashicons-calendar-alt',
			'desc'  => 'Recurring 15-day credential rotations, vault tracking, and staging site protection.',
		),
		'advanced_protection' => array(
			'label' => 'Advanced Protection',
			'icon'  => 'dashicons-shield-alt',
			'desc'  => 'Login throttling, user enumeration defense, session security, and runtime hardening.',
		),
		'report'              => array(
			'label' => 'SOP Report',
			'icon'  => 'dashicons-clipboard',
			'desc'  => 'Client-ready SOP coverage summary report and export.',
		),
	);

	/**
	 * Get singleton instance.
	 *
	 * @return WPSG_Task_Registry
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
		$this->register_all_tasks();
	}

	/**
	 * Register all SOP checklist items as Task objects.
	 */
	private function register_all_tasks() {
		// ==========================================
		// SECTION 1: SECURITY UPDATE
		// ==========================================

		// 1.1 Force/Verify Fresh Backup
		$this->register( new WPSG_Task( array(
			'id'               => 'trigger_backup',
			'section'          => 'security_update',
			'title'            => __( 'Verify Recent Site Backup', 'site-checkup-pro' ),
			'description'      => __( 'Verifies that a full database & file backup exists within the last 24–48 hours (UpdraftPlus, WPvivid, or host snapshot).', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				$b = WPSG_Backup_Guard::get_backup_status();
				return array(
					'status'  => $b['is_recent'] ? 'done' : 'attention',
					'message' => $b['is_recent']
						? sprintf( __( 'Verified recent backup (%1$s, %2$s hours ago).', 'site-checkup-pro' ), $b['plugin_name'], $b['age_hours'] )
						: ( isset( $b['message'] ) ? $b['message'] : __( 'No recent backup found within the last 48 hours.', 'site-checkup-pro' ) ),
				);
			},
			'run_callback'     => function () {
				$b = WPSG_Backup_Guard::get_backup_status();
				if ( $b['is_recent'] ) {
					return array( 'success' => true, 'message' => sprintf( __( 'Recent backup verified (%s).', 'site-checkup-pro' ), $b['plugin_name'] ) );
				}
				// If manual confirmation was just recorded
				WPSG_Backup_Guard::confirm_manual_backup();
				return array( 'success' => true, 'message' => __( 'Manual host/cPanel backup confirmed for the next 48 hours.', 'site-checkup-pro' ) );
			},
		) ) );

		// 1.2 Disable Auto-Updates for Plugins, Themes, Core
		$this->register( new WPSG_Task( array(
			'id'               => 'toggle_auto_updates',
			'section'          => 'security_update',
			'title'            => __( 'Disable Automatic Updates', 'site-checkup-pro' ),
			'description'      => __( 'Prevents unverified background core, plugin, and theme updates from breaking client customizations. Agency manages updates manually.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$active = WPSG_Plugin::get_instance()->is_task_active( 'toggle_auto_updates' );
				return array(
					'status'  => $active ? 'done' : 'pending',
					'message' => $active ? __( 'Automatic updates are blocked via runtime filters.', 'site-checkup-pro' ) : __( 'Automatic updates are currently allowed.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				return array( 'success' => true, 'message' => __( 'Automatic updates disabled.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				return array( 'success' => true, 'message' => __( 'Automatic updates enabled.', 'site-checkup-pro' ) );
			},
		) ) );

		// 1.3 Wordfence Setup & Recommended Settings
		$this->register( new WPSG_Task( array(
			'id'               => 'wordfence_config',
			'section'          => 'security_update',
			'title'            => __( 'Wordfence Firewall & Scanner', 'site-checkup-pro' ),
			'description'      => __( 'Verifies Wordfence WAF status, brute-force lockout rules, and alert preferences against the agency SOP.', 'site-checkup-pro' ),
			'automation_level' => 'B',
			'sub_type'         => 'guided',
			'status_callback'  => array( 'WPSG_Wordfence_Bridge', 'get_status' ),
			'guide_data'       => array(
				'button_label' => __( 'Configure Wordfence', 'site-checkup-pro' ),
				'link'         => admin_url( 'admin.php?page=Wordfence' ),
			),
		) ) );

		// 1.4 Two-Factor Authentication (2FA)
		$this->register( new WPSG_Task( array(
			'id'               => 'two_factor_auth',
			'section'          => 'security_update',
			'title'            => __( 'Enforce Two-Factor Authentication (2FA)', 'site-checkup-pro' ),
			'description'      => __( 'Ensures an active 2FA plugin is installed and mandatory for all administrator accounts.', 'site-checkup-pro' ),
			'automation_level' => 'B',
			'sub_type'         => 'guided',
			'status_callback'  => array( 'WPSG_2fa_Bridge', 'get_status' ),
			'guide_data'       => array(
				'button_label' => __( 'Configure 2FA', 'site-checkup-pro' ),
				'link'         => admin_url( 'plugins.php' ),
			),
		) ) );

		// 1.5 Vulnerability Intelligence (Patchstack CVE Database)
		$this->register( new WPSG_Task( array(
			'id'               => 'vulnerability_database_check',
			'section'          => 'security_update',
			'title'            => __( 'Scan Vulnerability Database (Patchstack CVE)', 'site-checkup-pro' ),
			'description'      => __( 'Cross-references installed plugins and themes against the Patchstack vulnerability database with match-confidence scoring. Cached 24h.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Vulnerability_Checker', 'get_vulnerability_status' ),
			'run_callback'     => function () {
				return WPSG_Vulnerability_Checker::get_vulnerability_status( true );
			},
		) ) );

		// ==========================================
		// SECTION 2: GENERAL CHECK
		// ==========================================

		// 2.1 System Environment Check (PHP, WP, SSL)
		$this->register( new WPSG_Task( array(
			'id'               => 'system_environment_check',
			'section'          => 'general_check',
			'title'            => __( 'Environment Audit (PHP, WP, SSL)', 'site-checkup-pro' ),
			'description'      => __( 'Audits PHP version (>= 8.1), WordPress core release, and SSL certificate expiration window.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'check_system_environment' ),
			'run_callback'     => array( 'WPSG_Scanner', 'check_system_environment' ),
		) ) );

		// 2.2 Detect Rogue Administrators (Baseline Drift)
		$this->register( new WPSG_Task( array(
			'id'               => 'scan_rogue_admins',
			'section'          => 'general_check',
			'title'            => __( 'Detect Rogue / Unrecognized Admins', 'site-checkup-pro' ),
			'description'      => __( 'Compares administrator users against the accepted baseline snapshot taken at activation. Alerts on any newly created or altered admin.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'scan_rogue_admins' ),
			'run_callback'     => array( 'WPSG_Scanner', 'scan_rogue_admins' ),
		) ) );

		// 2.3 wp_options Integrity & Autoload Bloat
		$this->register( new WPSG_Task( array(
			'id'               => 'scan_options_integrity',
			'section'          => 'general_check',
			'title'            => __( 'wp_options Integrity & Autoload Scan', 'site-checkup-pro' ),
			'description'      => __( 'Checks for siteurl/home URL hijacking and flags oversized autoloaded options (>100KB) that cause database slowdowns or malware persistence.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'scan_options_integrity' ),
			'run_callback'     => array( 'WPSG_Scanner', 'scan_options_integrity' ),
		) ) );

		// 2.4 Audit mu-plugins Directory
		$this->register( new WPSG_Task( array(
			'id'               => 'scan_mu_plugins',
			'section'          => 'general_check',
			'title'            => __( 'Audit Must-Use (mu-plugins) Directory', 'site-checkup-pro' ),
			'description'      => __( 'Inspects wp-content/mu-plugins for unauthorized PHP scripts that execute automatically outside standard plugin controls.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'scan_mu_plugins' ),
			'run_callback'     => array( 'WPSG_Scanner', 'scan_mu_plugins' ),
		) ) );

		// 2.5 Scan Exposed Backup / Sensitive Files
		$this->register( new WPSG_Task( array(
			'id'               => 'scan_exposed_files',
			'section'          => 'general_check',
			'title'            => __( 'Scan Exposed Backups & Dumps in Webroot', 'site-checkup-pro' ),
			'description'      => __( 'Scans for public database dumps (*.sql), .env files, and wp-config backups accidentally left in the public webroot.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'scan_exposed_files' ),
			'run_callback'     => array( 'WPSG_Scanner', 'scan_exposed_files' ),
		) ) );

		// 2.6 Detect Unwanted / Leftover Plugins
		$this->register( new WPSG_Task( array(
			'id'               => 'detect_unwanted_plugins',
			'section'          => 'general_check',
			'title'            => __( 'Detect & Quarantine Risky Plugins', 'site-checkup-pro' ),
			'description'      => __( 'Detects leftover migration tools (Better Search Replace, File Manager, Duplicate Page). Deleting zips the plugin first to enable real Undo.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => false,
			'status_callback'  => array( 'WPSG_Plugin_Integrity', 'detect_unwanted_plugins' ),
			'run_callback'     => array( 'WPSG_Plugin_Integrity', 'detect_unwanted_plugins' ),
			'undo_callback'    => array( 'WPSG_Plugin_Integrity', 'undo_last_deletion' ),
		) ) );

		// 2.7 Plugin Integrity (WP.org Closed/Removed Scan)
		$this->register( new WPSG_Task( array(
			'id'               => 'plugin_integrity_check',
			'section'          => 'general_check',
			'title'            => __( 'Scan Closed / Abandoned Plugins (WP.org)', 'site-checkup-pro' ),
			'description'      => __( 'Checks installed public plugins against the WordPress.org API to detect plugins removed for security vulnerabilities. Cached 24h.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Plugin_Integrity', 'check_plugin_integrity' ),
			'run_callback'     => function () {
				return WPSG_Plugin_Integrity::check_plugin_integrity( true );
			},
		) ) );

		// 2.8 Scaffold Custom Child Theme
		$this->register( new WPSG_Task( array(
			'id'               => 'scaffold_child_theme',
			'section'          => 'general_check',
			'title'            => __( 'Custom Child Theme Setup', 'site-checkup-pro' ),
			'description'      => __( 'Verifies if a child theme is active. Generates a clean child theme without third-party online generators.', 'site-checkup-pro' ),
			'automation_level' => 'B',
			'sub_type'         => 'guided',
			'has_undo'         => true,
			'status_callback'  => array( 'WPSG_Child_Theme', 'get_status' ),
			'run_callback'     => array( 'WPSG_Child_Theme', 'scaffold' ),
			'undo_callback'    => array( 'WPSG_Child_Theme', 'undo' ),
		) ) );

		// 2.9 File Permissions Audit (wp-config <= 0640, 0644/0755)
		$this->register( new WPSG_Task( array(
			'id'               => 'file_permissions_audit',
			'section'          => 'general_check',
			'title'            => __( 'File Permissions Audit (wp-config 640, 644/755)', 'site-checkup-pro' ),
			'description'      => __( 'Audits key files and directories against strict permission baselines (wp-config.php <= 0640, root files <= 0644, directories <= 0755) and flags world-writable bits.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'audit_file_permissions' ),
			'run_callback'     => function () {
				return WPSG_Scanner::audit_file_permissions( true );
			},
		) ) );

		// 2.10 PHP Security Restrictions (disable_functions & open_basedir)
		$this->register( new WPSG_Task( array(
			'id'               => 'php_server_restrictions',
			'section'          => 'general_check',
			'title'            => __( 'PHP Security Restrictions (disable_functions & open_basedir)', 'site-checkup-pro' ),
			'description'      => __( 'Audits php.ini to detect if dangerous execution functions (exec, shell_exec, system, passthru) are disabled and whether open_basedir is active.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'check_php_server_restrictions' ),
			'run_callback'     => function () {
				return WPSG_Scanner::check_php_server_restrictions( true );
			},
		) ) );

		// 2.11 Database Table Prefix Detection
		$this->register( new WPSG_Task( array(
			'id'               => 'db_prefix_check',
			'section'          => 'general_check',
			'title'            => __( 'Database Table Prefix Detection', 'site-checkup-pro' ),
			'description'      => __( 'Checks whether database tables use the default "wp_" prefix or a custom prefix to resist automated SQL injection scripts.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'check_db_prefix' ),
			'run_callback'     => function () {
				return WPSG_Scanner::check_db_prefix( true );
			},
		) ) );

		// 2.12 TLS Protocol & Certificate Chain Depth Probe
		$this->register( new WPSG_Task( array(
			'id'               => 'tls_cert_depth_check',
			'section'          => 'general_check',
			'title'            => __( 'TLS Protocol & Certificate Chain Depth Probe', 'site-checkup-pro' ),
			'description'      => __( 'Actively probes server support for legacy TLS 1.0 and 1.1 protocols and verifies intermediate certificate chain completeness.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'check_tls_and_cert_depth' ),
			'run_callback'     => function () {
				return WPSG_Scanner::check_tls_and_cert_depth( true );
			},
		) ) );

		// 2.13 Domain Email Authentication (SPF & DMARC)
		$this->register( new WPSG_Task( array(
			'id'               => 'email_domain_auth_check',
			'section'          => 'general_check',
			'title'            => __( 'Domain Email Authentication (SPF & DMARC)', 'site-checkup-pro' ),
			'description'      => __( 'Informational DNS query verifying SPF and DMARC records for the sending domain to prevent email spoofing and spam folder placement.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => array( 'WPSG_Scanner', 'check_domain_email_auth' ),
			'run_callback'     => function () {
				return WPSG_Scanner::check_domain_email_auth( true );
			},
		) ) );

		// 2.14 REST API Security Auditor
		$this->register( new WPSG_Task( array(
			'id'               => 'rest_api_security_audit',
			'section'          => 'general_check',
			'title'            => __( 'REST API Security Audit', 'site-checkup-pro' ),
			'description'      => __( 'Enumerates all registered WordPress REST API endpoints, inspects permission callbacks, maps source plugins/themes, and identifies unauthenticated exposure risks.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				if ( ! class_exists( 'WPSG_Rest_Auditor' ) ) {
					return array( 'status' => 'pending', 'message' => __( 'REST API Auditor not loaded.', 'site-checkup-pro' ) );
				}
				$audit = WPSG_Rest_Auditor::audit_routes();
				$high  = isset( $audit['summary']['high_risk'] ) ? $audit['summary']['high_risk'] : 0;
				$total = isset( $audit['summary']['total_endpoints'] ) ? $audit['summary']['total_endpoints'] : 0;
				if ( $high > 0 ) {
					return array(
						'status'  => 'attention',
						'message' => sprintf( __( '%1$d high-risk or publicly exposed REST endpoint(s) detected across %2$d endpoints.', 'site-checkup-pro' ), $high, $total ),
						'data'    => $audit['summary'],
					);
				}
				return array(
					'status'  => 'done',
					'message' => sprintf( __( 'All %d registered REST endpoints audited. No unauthorized public write routes detected.', 'site-checkup-pro' ), $total ),
					'data'    => $audit['summary'],
				);
			},
			'run_callback'     => function () {
				if ( ! class_exists( 'WPSG_Rest_Auditor' ) ) {
					return array( 'success' => false, 'message' => __( 'REST API Auditor not loaded.', 'site-checkup-pro' ) );
				}
				$audit = WPSG_Rest_Auditor::audit_routes();
				$high  = isset( $audit['summary']['high_risk'] ) ? $audit['summary']['high_risk'] : 0;
				$total = isset( $audit['summary']['total_endpoints'] ) ? $audit['summary']['total_endpoints'] : 0;
				if ( class_exists( 'WPSG_Audit_Logger' ) ) {
					WPSG_Audit_Logger::log(
						'rest_api_audit_performed',
						sprintf( 'Audited %1$d REST endpoints: %2$d public, %3$d protected, %4$d high-risk.', $total, $audit['summary']['public'], $audit['summary']['protected'], $high ),
						'system',
						$high > 0 ? 'warning' : 'info'
					);
				}
				return array(
					'success' => true,
					'status'  => $high > 0 ? 'attention' : 'done',
					'message' => $high > 0
						? sprintf( __( 'Audit complete: %1$d high-risk endpoint(s) identified across %2$d endpoints.', 'site-checkup-pro' ), $high, $total )
						: sprintf( __( 'Audit complete: all %d endpoints verified with proper authorization.', 'site-checkup-pro' ), $total ),
					'summary' => $audit['summary'],
				);
			},
		) ) );

		// 2.15 Environment Tagging & Admin Bar Badge
		$this->register( new WPSG_Task( array(
			'id'               => 'environment_badge_check',
			'section'          => 'general_check',
			'title'            => __( 'Environment Tagging & Admin Bar Badge', 'site-checkup-pro' ),
			'description'      => __( 'Tags the environment (Production, Staging, or Development) and displays a persistent color-coded safety badge in the WordPress top admin bar to prevent accidental changes on production.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				if ( ! class_exists( 'WPSG_Environment_Badge' ) ) {
					return array( 'status' => 'pending', 'message' => __( 'Environment Badge component not loaded.', 'site-checkup-pro' ) );
				}
				$confirmed = WPSG_Environment_Badge::is_confirmed();
				$env       = WPSG_Environment_Badge::get_environment();
				if ( $confirmed ) {
					return array(
						'status'  => 'done',
						'message' => sprintf( __( 'Active environment confirmed: %s (Admin bar badge active).', 'site-checkup-pro' ), ucfirst( $env ) ),
						'data'    => array( 'environment' => $env, 'confirmed' => true ),
					);
				}
				return array(
					'status'  => 'pending',
					'message' => sprintf( __( 'Unconfirmed environment. Domain heuristics suggest: %s. Click Run to confirm.', 'site-checkup-pro' ), ucfirst( $env ) ),
					'data'    => array( 'environment' => $env, 'confirmed' => false ),
				);
			},
			'run_callback'     => function () {
				if ( ! class_exists( 'WPSG_Environment_Badge' ) ) {
					return array( 'success' => false, 'message' => __( 'Environment Badge component not loaded.', 'site-checkup-pro' ) );
				}
				$suggested = WPSG_Environment_Badge::suggest_environment();
				update_option( WPSG_Environment_Badge::OPTION_KEY, $suggested );
				if ( class_exists( 'WPSG_Audit_Logger' ) ) {
					WPSG_Audit_Logger::log(
						'environment_tagged',
						sprintf( 'Environment manually tagged as %s via Site Checkup Pro.', ucfirst( $suggested ) ),
						'admin',
						'info'
					);
				}
				return array(
					'success' => true,
					'status'  => 'done',
					'message' => sprintf( __( 'Environment confirmed as %s. Admin bar badge updated.', 'site-checkup-pro' ), ucfirst( $suggested ) ),
					'data'    => array( 'environment' => $suggested, 'confirmed' => true ),
				);
			},
		) ) );

		// 2.16 Developer Diagnostic Snapshot
		$this->register( new WPSG_Task( array(
			'id'               => 'diagnostic_snapshot_check',
			'section'          => 'general_check',
			'title'            => __( 'Developer Diagnostic Snapshot', 'site-checkup-pro' ),
			'description'      => __( 'Generates a sanitized one-click diagnostic snapshot of PHP, server, database, theme, active plugins, and non-sensitive wp-config flags formatted for developer debugging and support tickets.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				if ( ! class_exists( 'WPSG_Diagnostic_Snapshot' ) ) {
					return array( 'status' => 'pending', 'message' => __( 'Diagnostic Snapshot component not loaded.', 'site-checkup-pro' ) );
				}
				return array(
					'status'  => 'done',
					'message' => __( 'Diagnostic snapshot generator ready. Click Re-run to generate a fresh export.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				if ( ! class_exists( 'WPSG_Diagnostic_Snapshot' ) ) {
					return array( 'success' => false, 'message' => __( 'Diagnostic Snapshot component not loaded.', 'site-checkup-pro' ) );
				}
				$snapshot = WPSG_Diagnostic_Snapshot::compile();
				if ( class_exists( 'WPSG_Audit_Logger' ) ) {
					WPSG_Audit_Logger::log(
						'diagnostic_snapshot_generated',
						sprintf( 'Generated developer diagnostic snapshot (PHP %s, WP %s).', $snapshot['server']['php_version'], $snapshot['wordpress']['version'] ),
						'admin',
						'info'
					);
				}
				return array(
					'success'  => true,
					'status'   => 'done',
					'message'  => __( 'Developer diagnostic snapshot generated successfully.', 'site-checkup-pro' ),
					'markdown' => $snapshot['markdown'],
					'data'     => $snapshot,
				);
			},
		) ) );

		// 2.17 WP-Cron Scheduled Events Audit
		$this->register( new WPSG_Task( array(
			'id'               => 'cron_job_audit',
			'section'          => 'general_check',
			'title'            => __( 'WP-Cron Scheduled Events Audit', 'site-checkup-pro' ),
			'description'      => __( 'Lists all scheduled WP-Cron background jobs, detects overdue stalled tasks indicating cron failure, and identifies duplicate conflicting hook registrations.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				if ( ! class_exists( 'WPSG_Cron_Auditor' ) ) {
					return array( 'status' => 'pending', 'message' => __( 'Cron Auditor component not loaded.', 'site-checkup-pro' ) );
				}
				$audit = WPSG_Cron_Auditor::audit_cron_jobs();
				$overdue = $audit['summary']['overdue_count'];
				$dupes   = $audit['summary']['duplicate_count'];
				$total   = $audit['summary']['total_jobs'];
				if ( $overdue > 0 || $dupes > 0 ) {
					return array(
						'status'  => 'attention',
						'message' => sprintf( __( 'Cron anomalies detected: %1$d overdue task(s), %2$d duplicate registration(s) across %3$d scheduled events.', 'site-checkup-pro' ), $overdue, $dupes, $total ),
						'data'    => $audit['summary'],
					);
				}
				return array(
					'status'  => 'done',
					'message' => sprintf( __( 'All %d scheduled WP-Cron jobs running normally. No overdue or duplicate tasks found.', 'site-checkup-pro' ), $total ),
					'data'    => $audit['summary'],
				);
			},
			'run_callback'     => function () {
				if ( ! class_exists( 'WPSG_Cron_Auditor' ) ) {
					return array( 'success' => false, 'message' => __( 'Cron Auditor component not loaded.', 'site-checkup-pro' ) );
				}
				$audit   = WPSG_Cron_Auditor::audit_cron_jobs();
				$overdue = $audit['summary']['overdue_count'];
				$dupes   = $audit['summary']['duplicate_count'];
				$total   = $audit['summary']['total_jobs'];
				if ( class_exists( 'WPSG_Audit_Logger' ) ) {
					WPSG_Audit_Logger::log(
						'cron_audit_performed',
						sprintf( 'Audited WP-Cron: %1$d jobs, %2$d overdue, %3$d duplicates.', $total, $overdue, $dupes ),
						'system',
						( $overdue > 0 || $dupes > 0 ) ? 'warning' : 'info'
					);
				}
				return array(
					'success' => true,
					'status'  => ( $overdue > 0 || $dupes > 0 ) ? 'attention' : 'done',
					'message' => ( $overdue > 0 || $dupes > 0 )
						? sprintf( __( 'Cron scan complete: %1$d overdue task(s), %2$d duplicate(s) found.', 'site-checkup-pro' ), $overdue, $dupes )
						: sprintf( __( 'Cron scan complete: all %d events verified and scheduled properly.', 'site-checkup-pro' ), $total ),
					'summary' => $audit['summary'],
				);
			},
		) ) );

		// 2.18 Database Health Scanner
		$this->register( new WPSG_Task( array(
			'id'               => 'db_health_scanner',
			'section'          => 'general_check',
			'title'            => __( 'Database Overhead & Orphaned Data Scanner', 'site-checkup-pro' ),
			'description'      => __( 'Detects orphaned postmeta, orphaned usermeta, expired transients, and excess post revisions with storage impact metrics and protected Level-B cleanup.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				if ( ! class_exists( 'WPSG_Db_Health_Scanner' ) ) {
					return array( 'status' => 'pending', 'message' => __( 'Database Health Scanner component not loaded.', 'site-checkup-pro' ) );
				}
				$scan = WPSG_Db_Health_Scanner::scan();
				$bloat = $scan['summary']['total_bloat_items'];
				$mb    = $scan['summary']['estimated_savings_mb'];
				if ( $bloat > 0 ) {
					return array(
						'status'  => 'attention',
						'message' => sprintf( __( '%1$d orphaned/stale database item(s) found (~%2$s MB overhead). Click Run to inspect.', 'site-checkup-pro' ), $bloat, $mb ),
						'data'    => $scan['summary'],
					);
				}
				return array(
					'status'  => 'done',
					'message' => __( 'Database is optimized. Zero orphaned metadata or expired transients detected.', 'site-checkup-pro' ),
					'data'    => $scan['summary'],
				);
			},
			'run_callback'     => function () {
				if ( ! class_exists( 'WPSG_Db_Health_Scanner' ) ) {
					return array( 'success' => false, 'message' => __( 'Database Health Scanner component not loaded.', 'site-checkup-pro' ) );
				}
				$scan  = WPSG_Db_Health_Scanner::scan();
				$bloat = $scan['summary']['total_bloat_items'];
				$mb    = $scan['summary']['estimated_savings_mb'];
				if ( class_exists( 'WPSG_Audit_Logger' ) ) {
					WPSG_Audit_Logger::log(
						'db_health_scanned',
						sprintf( 'Database bloat scanned: %1$d items (~%2$s MB overhead).', $bloat, $mb ),
						'system',
						$bloat > 0 ? 'warning' : 'info'
					);
				}
				return array(
					'success' => true,
					'status'  => $bloat > 0 ? 'attention' : 'done',
					'message' => $bloat > 0
						? sprintf( __( 'Scan complete: %1$d orphaned/stale item(s) identified (~%2$s MB overhead).', 'site-checkup-pro' ), $bloat, $mb )
						: __( 'Scan complete: database is clean and optimized.', 'site-checkup-pro' ),
					'summary' => $scan['summary'],
					'details' => $scan['details'],
				);
			},
		) ) );

		// 2.19 Migration Readiness Check
		$this->register( new WPSG_Task( array(
			'id'               => 'migration_readiness_check',
			'section'          => 'general_check',
			'title'            => __( 'Migration URL & Serialization Risk Check', 'site-checkup-pro' ),
			'description'      => __( 'Scans the database for hardcoded absolute URLs embedded inside PHP serialized strings that break during domain migrations, providing safe WP-CLI guidance.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				if ( ! class_exists( 'WPSG_Migration_Readiness' ) ) {
					return array( 'status' => 'pending', 'message' => __( 'Migration Readiness component not loaded.', 'site-checkup-pro' ) );
				}
				$scan  = WPSG_Migration_Readiness::scan();
				$count = $scan['summary']['total_findings'];
				if ( $count > 0 ) {
					return array(
						'status'  => 'attention',
						'message' => sprintf( __( '%d serialized database row(s) contain hardcoded domain URLs. Standard SQL export/import will corrupt these strings.', 'site-checkup-pro' ), $count ),
						'data'    => $scan['summary'],
					);
				}
				return array(
					'status'  => 'done',
					'message' => __( 'Zero serialized URL risks detected. Database is ready for safe migration.', 'site-checkup-pro' ),
					'data'    => $scan['summary'],
				);
			},
			'run_callback'     => function () {
				if ( ! class_exists( 'WPSG_Migration_Readiness' ) ) {
					return array( 'success' => false, 'message' => __( 'Migration Readiness component not loaded.', 'site-checkup-pro' ) );
				}
				$scan  = WPSG_Migration_Readiness::scan();
				$count = $scan['summary']['total_findings'];
				if ( class_exists( 'WPSG_Audit_Logger' ) ) {
					WPSG_Audit_Logger::log(
						'migration_readiness_scanned',
						sprintf( 'Migration readiness scanned: %d serialized URL risks detected.', $count ),
						'system',
						$count > 0 ? 'warning' : 'info'
					);
				}
				return array(
					'success'  => true,
					'status'   => $count > 0 ? 'attention' : 'done',
					'message'  => $count > 0
						? sprintf( __( 'Scan complete: %d serialized row(s) contain absolute URLs. Review WP-CLI guidance.', 'site-checkup-pro' ), $count )
						: __( 'Scan complete: no serialized absolute URL risks detected.', 'site-checkup-pro' ),
					'summary'  => $scan['summary'],
					'guidance' => $scan['guidance'],
				);
			},
		) ) );

		// 2.20 Update Changelog Intelligence Digest
		$this->register( new WPSG_Task( array(
			'id'               => 'changelog_digest_check',
			'section'          => 'general_check',
			'title'            => __( 'Update Changelog Intelligence Digest', 'site-checkup-pro' ),
			'description'      => __( 'Aggregates changelogs and release notes for available plugin/theme updates and delivers a weekly intelligence digest via alerts and dashboard.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				if ( ! class_exists( 'WPSG_Changelog_Digest' ) ) {
					return array( 'status' => 'pending', 'message' => __( 'Changelog Digest component not loaded.', 'site-checkup-pro' ) );
				}
				$digest = WPSG_Changelog_Digest::compile_digest();
				$total  = $digest['summary']['total_updates'];
				if ( $total > 0 ) {
					return array(
						'status'  => 'attention',
						'message' => sprintf( __( '%d plugin/theme update(s) available with changelog summaries ready for review.', 'site-checkup-pro' ), $total ),
						'data'    => $digest['summary'],
					);
				}
				return array(
					'status'  => 'done',
					'message' => __( 'All plugins and themes are up to date. Weekly changelog digest scheduled.', 'site-checkup-pro' ),
					'data'    => $digest['summary'],
				);
			},
			'run_callback'     => function () {
				if ( ! class_exists( 'WPSG_Changelog_Digest' ) ) {
					return array( 'success' => false, 'message' => __( 'Changelog Digest component not loaded.', 'site-checkup-pro' ) );
				}
				$digest = WPSG_Changelog_Digest::compile_digest();
				$total  = $digest['summary']['total_updates'];
				if ( class_exists( 'WPSG_Audit_Logger' ) ) {
					WPSG_Audit_Logger::log(
						'changelog_digest_compiled',
						sprintf( 'Compiled changelog digest: %d update(s) available.', $total ),
						'system',
						'info'
					);
				}
				return array(
					'success' => true,
					'status'  => $total > 0 ? 'attention' : 'done',
					'message' => $total > 0
						? sprintf( __( 'Digest compiled: %d update(s) available with release notes.', 'site-checkup-pro' ), $total )
						: __( 'Digest compiled: all plugins and themes are current.', 'site-checkup-pro' ),
					'summary' => $digest['summary'],
					'items'   => $digest['items'],
				);
			},
		) ) );

		// ==========================================
		// SECTION 3: HARDENING (.htaccess & wp-config)
		// ==========================================

		// 3.1 Disable XML-RPC (PHP Runtime Filter)
		$this->register( new WPSG_Task( array(
			'id'               => 'disable_xmlrpc',
			'section'          => 'hardening',
			'title'            => __( 'Disable XML-RPC (Runtime Filter)', 'site-checkup-pro' ),
			'description'      => __( 'Disables XML-RPC pingbacks and brute-force vectors via WordPress core filters and removes discovery link headers.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$active = WPSG_Plugin::get_instance()->is_task_active( 'disable_xmlrpc' );
				return array(
					'status'  => $active ? 'done' : 'pending',
					'message' => $active ? __( 'XML-RPC is disabled via core filters.', 'site-checkup-pro' ) : __( 'XML-RPC is currently enabled.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				return array( 'success' => true, 'message' => __( 'XML-RPC runtime filter activated.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				return array( 'success' => true, 'message' => __( 'XML-RPC filter deactivated.', 'site-checkup-pro' ) );
			},
		) ) );

		// 3.2 Restrict REST API User Enumeration
		$this->register( new WPSG_Task( array(
			'id'               => 'restrict_rest_api',
			'section'          => 'hardening',
			'title'            => __( 'Restrict REST API User Enumeration', 'site-checkup-pro' ),
			'description'      => __( 'Prevents unauthenticated visitors and bots from enumerating usernames via the /wp/v2/users REST route.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$active = WPSG_Plugin::get_instance()->is_task_active( 'restrict_rest_api' );
				return array(
					'status'  => $active ? 'done' : 'pending',
					'message' => $active ? __( 'REST user enumeration restricted for visitors.', 'site-checkup-pro' ) : __( 'REST user enumeration is publicly accessible.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				return array( 'success' => true, 'message' => __( 'REST user route restriction activated.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				return array( 'success' => true, 'message' => __( 'REST user route restriction deactivated.', 'site-checkup-pro' ) );
			},
		) ) );

		// 3.3 Hide PHP Version Header (PHP-FPM header unset first + Apache mod_php fallback)
		$php_version_rules = "<IfModule mod_headers.c>\nHeader unset X-Powered-By\n</IfModule>\n<IfModule mod_php7.c>\nphp_flag expose_php off\n</IfModule>\n<IfModule mod_php8.c>\nphp_flag expose_php off\n</IfModule>";
		$php_version_nginx = 'fastcgi_hide_header X-Powered-By;';

		$this->register( new WPSG_Task( array(
			'id'               => 'hide_php_version',
			'section'          => 'hardening',
			'title'            => __( 'Hide PHP Version (X-Powered-By)', 'site-checkup-pro' ),
			'description'      => __( 'Strips the X-Powered-By server response header. Prioritizes Header unset for modern PHP-FPM servers.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $php_version_nginx,
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'hide_php_version' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'HidePHPVersion' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array( 'status' => 'pending', 'message' => '' );
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'HidePHPVersion' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'HidePHPVersion' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'HidePHPVersion' );
			},
		) ) );

		// 3.4 Clickjacking Protection (X-Frame-Options)
		$clickjack_rules = "<IfModule mod_headers.c>\nHeader always append X-Frame-Options SAMEORIGIN\n</IfModule>";
		$clickjack_nginx = 'add_header X-Frame-Options "SAMEORIGIN" always;';

		$this->register( new WPSG_Task( array(
			'id'               => 'clickjacking_protection',
			'section'          => 'hardening',
			'title'            => __( 'Clickjacking Protection (X-Frame-Options)', 'site-checkup-pro' ),
			'description'      => __( 'Prevents the site from being loaded inside unauthorized iframes to protect against clickjacking attacks.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $clickjack_nginx,
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'clickjacking_protection' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'Clickjacking' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array( 'status' => 'pending', 'message' => '' );
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'Clickjacking' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'Clickjacking' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'Clickjacking' );
			},
		) ) );

		// 3.5 MIME Sniffing Protection (X-Content-Type-Options)
		$nosniff_rules = "<IfModule mod_headers.c>\nHeader set X-Content-Type-Options nosniff\n</IfModule>";
		$nosniff_nginx = 'add_header X-Content-Type-Options "nosniff" always;';

		$this->register( new WPSG_Task( array(
			'id'               => 'nosniff_header',
			'section'          => 'hardening',
			'title'            => __( 'MIME Sniffing (X-Content-Type-Options)', 'site-checkup-pro' ),
			'description'      => __( 'Prevents browsers from MIME-sniffing a response away from the declared content-type.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $nosniff_nginx,
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'nosniff_header' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'MimeSniffing' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array( 'status' => 'pending', 'message' => '' );
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'MimeSniffing' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'MimeSniffing' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'MimeSniffing' );
			},
		) ) );

		// 3.6 Strict Transport Security (HSTS)
		$hsts_rules = "<IfModule mod_headers.c>\nHeader always set Strict-Transport-Security \"max-age=300; includeSubDomains\"\n</IfModule>";
		$hsts_nginx = 'add_header Strict-Transport-Security "max-age=300; includeSubDomains" always;';

		$this->register( new WPSG_Task( array(
			'id'               => 'hsts_header',
			'section'          => 'hardening',
			'title'            => __( 'HTTP Strict Transport Security (HSTS)', 'site-checkup-pro' ),
			'description'      => __( 'Enforces HTTPS communication with browsers, protecting against man-in-the-middle SSL-strip attacks.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $hsts_nginx,
			'status_callback'  => function () {
				if ( ! is_ssl() ) {
					return array( 'status' => 'attention', 'message' => __( 'HSTS requires active SSL/HTTPS. Enable HTTPS to activate HSTS enforcement.', 'site-checkup-pro' ) );
				}
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'hsts_header' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'HSTS' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array( 'status' => 'pending', 'message' => '' );
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'HSTS' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'HSTS' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'HSTS' );
			},
		) ) );

		// 3.7 Disable Directory Browsing
		$indexes_rules = "Options -Indexes";
		$indexes_nginx = 'autoindex off;';

		$this->register( new WPSG_Task( array(
			'id'               => 'disable_directory_listing',
			'section'          => 'hardening',
			'title'            => __( 'Disable Directory Browsing (Indexes)', 'site-checkup-pro' ),
			'description'      => __( 'Prevents visitors from viewing directory file listings in folders without an index file (e.g., /wp-content/uploads/).', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $indexes_nginx,
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'disable_directory_listing' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'DisableIndexes' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array( 'status' => 'pending', 'message' => '' );
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'DisableIndexes' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'DisableIndexes' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'DisableIndexes' );
			},
		) ) );

		// 3.8 Block Access to Sensitive Files via .htaccess
		$sensitive_rules = "<FilesMatch \"^(\\.git|\\.env|\\.bak|wp-config\\.php|readme\\.html|license\\.txt|composer\\.(json|lock))\">\nOrder allow,deny\nDeny from all\n</FilesMatch>";
		$sensitive_nginx = "location ~* /\\.(git|env|bak) {\n    deny all;\n}\nlocation = /wp-config.php {\n    deny all;\n}";

		$this->register( new WPSG_Task( array(
			'id'               => 'protect_sensitive_files',
			'section'          => 'hardening',
			'title'            => __( 'Protect Sensitive System Files', 'site-checkup-pro' ),
			'description'      => __( 'Blocks web access to .env, .git, .bak, readme.html, and wp-config.php directly at the web server layer.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $sensitive_nginx,
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'protect_sensitive_files' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'ProtectSensitiveFiles' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array( 'status' => 'pending', 'message' => '' );
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'ProtectSensitiveFiles' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'ProtectSensitiveFiles' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'ProtectSensitiveFiles' );
			},
		) ) );

		// 3.9 Block xmlrpc.php via Web Server
		$xmlrpc_htaccess = "<Files xmlrpc.php>\nOrder Deny,Allow\nDeny from all\n</Files>";
		$xmlrpc_nginx    = "location = /xmlrpc.php {\n    deny all;\n}";

		$this->register( new WPSG_Task( array(
			'id'               => 'block_xmlrpc_htaccess',
			'section'          => 'hardening',
			'title'            => __( 'Block xmlrpc.php at Server Level', 'site-checkup-pro' ),
			'description'      => __( 'Drops requests to xmlrpc.php before WordPress PHP boots, preventing DDoS amplification attacks.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $xmlrpc_nginx,
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'block_xmlrpc_htaccess' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'BlockXMLRPC' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array( 'status' => 'pending', 'message' => '' );
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'BlockXMLRPC' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'BlockXMLRPC' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'BlockXMLRPC' );
			},
		) ) );

		// 3.10 Disable wp-admin File Editing (DISALLOW_FILE_EDIT)
		$this->register( new WPSG_Task( array(
			'id'               => 'disable_file_edit',
			'section'          => 'hardening',
			'title'            => __( 'Disable Theme & Plugin Editor (wp-config.php)', 'site-checkup-pro' ),
			'description'      => __( 'Adds DISALLOW_FILE_EDIT to wp-config.php so compromised admin accounts cannot inject PHP via the dashboard editor.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'disable_file_edit' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$disallow = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
				if ( $disallow ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Constant defined, awaiting fresh process verification.', 'site-checkup-pro' ) );
				}
				if ( class_exists( 'WPSG_Wp_Config_Manager' ) && WPSG_Wp_Config_Manager::has_constant_in_file( 'DISALLOW_FILE_EDIT' ) ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Constant configured in wp-config.php, awaiting fresh process verification.', 'site-checkup-pro' ) );
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'File editing is currently enabled in wp-admin.', 'site-checkup-pro' ),
				);
			},
			'diff_callback'    => function () {
				return WPSG_Wp_Config_Manager::get_diff_preview( array( 'DISALLOW_FILE_EDIT' => true ) );
			},
			'run_callback'     => function () {
				return WPSG_Wp_Config_Manager::update_constants( array( 'DISALLOW_FILE_EDIT' => true ) );
			},
			'undo_callback'    => function () {
				return WPSG_Wp_Config_Manager::update_constants( array( 'DISALLOW_FILE_EDIT' => false ) );
			},
		) ) );

		// 3.11 Rotate WordPress Security Salts (wp-config.php)
		$this->register( new WPSG_Task( array(
			'id'               => 'rotate_salts',
			'section'          => 'hardening',
			'title'            => __( 'Rotate WordPress Security Salts & Keys', 'site-checkup-pro' ),
			'description'      => __( 'Generates 8 fresh 64-char crypto salts in wp-config.php, immediately invalidating all active browser cookies and sessions.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'status_callback'  => function () {
				$last_rotated = get_option( 'wpsg_salts_last_rotated' );
				if ( ! empty( $last_rotated ) ) {
					$days = round( ( time() - $last_rotated ) / 86400, 1 );
					return array(
						'status'  => 'done',
						'message' => sprintf( __( 'Salts rotated successfully (%s day(s) ago).', 'site-checkup-pro' ), $days ),
					);
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'Salts can be rotated on demand. Invalidates all active login sessions.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				return WPSG_Wp_Config_Manager::rotate_salts();
			},
		) ) );

		// 3.12 Change wp-admin Login URL (Guided + Conflict Checks + Emergency wp-config Recovery)
		$this->register( new WPSG_Task( array(
			'id'               => 'login_url_rename',
			'section'          => 'hardening',
			'title'            => __( 'Rename wp-admin Login URL', 'site-checkup-pro' ),
			'description'      => __( 'Hides wp-login.php behind a custom slug. Bridges to WPS Hide Login if present. Recovery is exclusively via WPSG_DISABLE_LOGIN_RENAME in wp-config.php.', 'site-checkup-pro' ),
			'automation_level' => 'B',
			'sub_type'         => 'guided',
			'status_callback'  => function () {
				if ( WPSG_Login_Renamer::is_wps_hide_login_active() ) {
					return array(
						'status'  => 'done',
						'message' => __( 'Managed via WPS Hide Login.', 'site-checkup-pro' ),
					);
				}
				$slug = WPSG_Login_Renamer::get_login_slug();
				if ( ! empty( $slug ) ) {
					$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'login_url_rename' ) : array( 'verified' => false );
					if ( ! empty( $http['verified'] ) ) {
						return array( 'status' => 'done', 'message' => $http['message'] );
					}
					return array(
						'status'  => 'applied_unverified',
						'message' => sprintf( __( 'Custom login URL set (/%s/), but live probe could not verify redirection yet.', 'site-checkup-pro' ), $slug ),
					);
				}
				$conflict = WPSG_Login_Renamer::get_conflicting_plugin();
				if ( false !== $conflict ) {
					return array(
						'status'  => 'attention',
						'message' => sprintf( __( 'Managed via %s.', 'site-checkup-pro' ), $conflict ),
					);
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'Login URL is currently default (/wp-login.php).', 'site-checkup-pro' ),
				);
			},
			'guide_data'       => array(
				'button_label' => __( 'Change Login URL', 'site-checkup-pro' ),
				'action'       => 'modal_login_rename',
			),
		) ) );

		// 3.13 Disable Front-End Debug Output (WP_DEBUG_DISPLAY)
		$this->register( new WPSG_Task( array(
			'id'               => 'wp_debug_display_check',
			'section'          => 'hardening',
			'title'            => __( 'Disable Front-End Debug Output (WP_DEBUG_DISPLAY)', 'site-checkup-pro' ),
			'description'      => __( 'Prevents database errors and PHP warnings from displaying on the front-end to site visitors by setting WP_DEBUG_DISPLAY to false in wp-config.php.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'wp_debug_display_check' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$raw = WPSG_Scanner::check_wp_debug_display();
				if ( isset( $raw['status'] ) && 'done' === $raw['status'] ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'WP_DEBUG_DISPLAY set to false in wp-config.php, awaiting fresh process verification.', 'site-checkup-pro' ) );
				}
				return $raw;
			},
			'diff_callback'    => function () {
				return WPSG_Wp_Config_Manager::get_diff_preview( array( 'WP_DEBUG_DISPLAY' => false ) );
			},
			'run_callback'     => function () {
				$result = WPSG_Wp_Config_Manager::update_constants( array( 'WP_DEBUG_DISPLAY' => false ) );
				// Flush the cached result so the next status check reads wp-config.php directly.
				delete_transient( 'wpsg_debug_display_cache' );
				return $result;
			},
			'undo_callback'    => function () {
				return WPSG_Wp_Config_Manager::update_constants( array( 'WP_DEBUG_DISPLAY' => true ) );
			},
		) ) );

		// 3.14 Security Disclosure Policy (security.txt)
		$this->register( new WPSG_Task( array(
			'id'               => 'security_txt_check',
			'section'          => 'hardening',
			'title'            => __( 'Security Disclosure Policy (security.txt)', 'site-checkup-pro' ),
			'description'      => __( 'Verifies and generates RFC 9116 responsible disclosure contact information at /.well-known/security.txt inside the document root.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$raw = WPSG_Security_Txt::check_status();
				if ( isset( $raw['status'] ) && 'done' === $raw['status'] ) {
					$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'security_txt_check' ) : array( 'verified' => false );
					if ( ! empty( $http['verified'] ) ) {
						return array( 'status' => 'done', 'message' => $http['message'] );
					}
					return array( 'status' => 'applied_unverified', 'message' => __( 'security.txt exists on disk, but live HTTP request did not confirm 200 OK at /.well-known/security.txt.', 'site-checkup-pro' ) );
				}
				return $raw;
			},
			'run_callback'     => array( 'WPSG_Security_Txt', 'generate' ),
			'undo_callback'    => array( 'WPSG_Security_Txt', 'undo' ),
		) ) );

		// ==========================================
		// SECTION 4: SEO SOP
		// ==========================================

		// 4.1 Robots.txt Review
		$this->register( new WPSG_Task( array(
			'id'               => 'robots_txt_review',
			'section'          => 'seo_sop',
			'title'            => __( 'Audit robots.txt Directives', 'site-checkup-pro' ),
			'description'      => __( 'Ensures search engines are not accidentally disallowed from crawling the site and that sensitive admin endpoints are disallowed.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
			'status_callback'  => function () {
				$blog_public = (int) get_option( 'blog_public', 1 );
				if ( 0 === $blog_public ) {
					return array(
						'status'  => 'attention',
						'message' => __( 'Search engine visibility is disabled in Settings &rarr; Reading (Disallow: /).', 'site-checkup-pro' ),
					);
				}
				$file = get_home_path() . 'robots.txt';
				if ( file_exists( $file ) ) {
					$content = file_get_contents( $file );
					if ( preg_match( '/Disallow:\s*\/\s*$/m', $content ) ) {
						return array(
							'status'  => 'attention',
							'message' => __( 'Physical robots.txt is blocking crawlers with "Disallow: /".', 'site-checkup-pro' ),
						);
					}
				}
				return array(
					'status'  => 'done',
					'message' => __( 'Search engine indexing permitted. No crawl-blocking directives found.', 'site-checkup-pro' ),
				);
			},
			'guide_data'       => array(
				'link' => home_url( '/robots.txt' ),
			),
		) ) );

		// 4.2 GSC & Bing Webmaster Review
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		$this->register( new WPSG_Task( array(
			'id'               => 'gsc_bing_audit',
			'section'          => 'seo_sop',
			'title'            => __( 'Google Search Console & Bing Review', 'site-checkup-pro' ),
			'description'      => __( 'Audit indexing coverage, security actions, and sitemaps directly in Google Search Console and Bing Webmaster Tools.', 'site-checkup-pro' ),
			'automation_level' => 'B',
			'sub_type'         => 'guided',
			'guide_data'       => array(
				'gsc_url'  => 'https://search.google.com/search-console?resource_id=' . rawurlencode( home_url() ),
				'bing_url' => 'https://www.bing.com/webmasters/',
			),
		) ) );

		// 4.3 GSC 6-Month URL Removal Recheck
		$this->register( new WPSG_Task( array(
			'id'               => 'gsc_removal_reminder',
			'section'          => 'seo_sop',
			'title'            => __( '6-Month GSC URL Removal Review', 'site-checkup-pro' ),
			'description'      => __( 'Google Search Console temporary URL removals expire after 6 months. Track submitted removals and schedule rechecks.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
		) ) );

		// ==========================================
		// SECTION 5: REGULAR CHECKS (15-Day Cadence)
		// ==========================================

		// 5.1 cPanel Password Rotation
		$this->register( new WPSG_Task( array(
			'id'               => 'cpanel_password_rotation',
			'section'          => 'regular_checks',
			'title'            => __( 'Rotate cPanel / Hosting Credentials (15d)', 'site-checkup-pro' ),
			'description'      => __( 'Rotate cPanel, FTP, and hosting passwords every 15 days in adherence to the agency security policy. Update vault.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
		) ) );

		// 5.2 WordPress Admin Credential Rotation
		$this->register( new WPSG_Task( array(
			'id'               => 'admin_password_rotation',
			'section'          => 'regular_checks',
			'title'            => __( 'Rotate WordPress Admin Password (15d)', 'site-checkup-pro' ),
			'description'      => __( 'Rotate main administrator credentials every 15 days, notify client if necessary, and store in secure team vault.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
		) ) );

		// 5.3 Staging Site HTTP Password Protection
		$this->register( new WPSG_Task( array(
			'id'               => 'staging_site_protection',
			'section'          => 'regular_checks',
			'title'            => __( 'Verify Staging Site Auth Protection', 'site-checkup-pro' ),
			'description'      => __( 'Ensure staging and development environments are shielded by HTTP Basic Auth (Directory Privacy) to prevent indexing and bot probing.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
			'status_callback'  => function () {
				$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
				$is_staging = (bool) preg_match( '/(staging|dev|test|local|temp)/i', $host );
				$has_auth   = isset( $_SERVER['PHP_AUTH_USER'] ) || isset( $_SERVER['REMOTE_USER'] );
				if ( $is_staging && ! $has_auth ) {
					return array(
						'status'  => 'attention',
						'message' => __( 'Staging domain detected without HTTP Basic Auth wall.', 'site-checkup-pro' ),
					);
				} elseif ( $has_auth ) {
					return array(
						'status'  => 'done',
						'message' => __( 'HTTP Basic Auth protection is active.', 'site-checkup-pro' ),
					);
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'Manual audit required: verify Directory Privacy in cPanel.', 'site-checkup-pro' ),
				);
			},
		) ) );

		// 5.4 Wordfence Alert Monitoring Inbox
		$this->register( new WPSG_Task( array(
			'id'               => 'wordfence_email_alert',
			'section'          => 'regular_checks',
			'title'            => __( 'Wordfence Email Alert Routing', 'site-checkup-pro' ),
			'description'      => __( 'Verify security alert notifications from Wordfence route to designated agency monitoring inbox.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
		) ) );

		// 5.5 Quarterly Backup Restore Test
		$this->register( new WPSG_Task( array(
			'id'               => 'backup_restore_test',
			'section'          => 'regular_checks',
			'title'            => __( 'Quarterly Backup Restore Test (90d)', 'site-checkup-pro' ),
			'description'      => __( 'Untested backups are worthless. Conduct a quarterly rehearsal restoring a database and file backup to a staging environment.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
		) ) );

		// 5.6 Domain, SSL & Hosting Expiry Audit
		$this->register( new WPSG_Task( array(
			'id'               => 'domain_ssl_hosting_expiry',
			'section'          => 'regular_checks',
			'title'            => __( 'Domain, SSL & Hosting Expiry Audit (90d)', 'site-checkup-pro' ),
			'description'      => __( 'Quarterly review of domain registration, auto-renewal status, SSL certificate validity, and hosting plan limits.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
		) ) );

		// 5.7 Incident Response Contact Sheet
		$this->register( new WPSG_Task( array(
			'id'               => 'incident_response_contact',
			'section'          => 'regular_checks',
			'title'            => __( 'Incident Response Emergency Contact Sheet', 'site-checkup-pro' ),
			'description'      => __( 'Record emergency contact details and escalation protocols in the event of a security incident. Surfaced on client reports.', 'site-checkup-pro' ),
			'automation_level' => 'C',
			'sub_type'         => 'manual',
			'status_callback'  => function () {
				$s = get_option( 'wpsg_settings', array() );
				$has_contact = ! empty( $s['incident_contact_email'] ) || ! empty( $s['incident_contact_phone'] );
				return array(
					'status'  => $has_contact ? 'done' : 'attention',
					'message' => $has_contact
						? sprintf( __( 'Incident contact configured: %s', 'site-checkup-pro' ), esc_html( ! empty( $s['incident_contact_name'] ) ? $s['incident_contact_name'] : $s['incident_contact_email'] ) )
						: __( 'No emergency incident contact details recorded. Update in Settings.', 'site-checkup-pro' ),
				);
			},
		) ) );

		// ==========================================
		// SECTION 6: ADVANCED PROTECTION
		// ==========================================

		// 6.1 Block User & Author Enumeration
		$this->register( new WPSG_Task( array(
			'id'               => 'block_user_enumeration',
			'section'          => 'advanced_protection',
			'title'            => __( 'Block User & Author Enumeration', 'site-checkup-pro' ),
			'description'      => __( 'Restricts unauthenticated access to /wp/v2/users and intercepts ?author= numeric queries to prevent attacker username discovery.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$active = get_option( 'wpsg_block_user_enumeration', false );
				if ( $active ) {
					$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'block_user_enumeration' ) : array( 'verified' => false );
					if ( ! empty( $http['verified'] ) ) {
						return array( 'status' => 'done', 'message' => $http['message'] );
					}
					return array( 'status' => 'applied_unverified', 'message' => __( 'User enumeration protection enabled in settings, but live probe was not blocked.', 'site-checkup-pro' ) );
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'User enumeration protection is disabled.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				update_option( 'wpsg_block_user_enumeration', true );
				return array( 'success' => true, 'message' => __( 'User & author enumeration blocked.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				delete_option( 'wpsg_block_user_enumeration' );
				return array( 'success' => true, 'message' => __( 'User enumeration protection disabled.', 'site-checkup-pro' ) );
			},
		) ) );

		// 6.2 Progressive Login Throttling & Honeypot
		$this->register( new WPSG_Task( array(
			'id'               => 'login_hardening',
			'section'          => 'advanced_protection',
			'title'            => __( 'Login Throttling & Honeypot Protection', 'site-checkup-pro' ),
			'description'      => __( 'Enforces atomic DB-level progressive lockouts (1m, 15m, 60m), silent honeypots, and generic error masking.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$active = get_option( 'wpsg_login_hardening', true );
				return array(
					'status'  => $active ? 'done' : 'pending',
					'message' => $active ? __( 'Progressive login throttling and honeypot active.', 'site-checkup-pro' ) : __( 'Login throttling is disabled.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				update_option( 'wpsg_login_hardening', true );
				return array( 'success' => true, 'message' => __( 'Login throttling & honeypot enabled.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				delete_option( 'wpsg_login_hardening' );
				return array( 'success' => true, 'message' => __( 'Login throttling disabled.', 'site-checkup-pro' ) );
			},
		) ) );

		// 6.3 Active Session Governance
		$this->register( new WPSG_Task( array(
			'id'               => 'session_governance',
			'section'          => 'advanced_protection',
			'title'            => __( 'Active Session Management', 'site-checkup-pro' ),
			'description'      => __( 'Review concurrent logged-in sessions across devices, terminate stale logins, and enforce automatic session invalidation on password updates.', 'site-checkup-pro' ),
			'automation_level' => 'B',
			'sub_type'         => 'guided',
			'guide_data'       => array( 'action' => 'open_sessions_modal' ),
			'status_callback'  => function () {
				$sessions = class_exists( 'WPSG_Session_Manager' ) ? WPSG_Session_Manager::get_user_sessions( get_current_user_id() ) : array();
				$count    = count( $sessions );
				return array(
					'status'  => ( $count <= 2 ) ? 'done' : 'attention',
					'message' => sprintf( __( '%d active session(s) recorded for current administrator.', 'site-checkup-pro' ), $count ),
				);
			},
		) ) );

		// 6.4 Hide WordPress Version & Generator
		$this->register( new WPSG_Task( array(
			'id'               => 'hide_wordpress_fingerprint',
			'section'          => 'advanced_protection',
			'title'            => __( 'Hide WordPress Version Meta Tag', 'site-checkup-pro' ),
			'description'      => __( 'Removes the WordPress generator tag from HTML headers and RSS feeds to reduce version fingerprinting.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$active = get_option( 'wpsg_hide_generator', false );
				if ( $active ) {
					$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'hide_wordpress_fingerprint' ) : array( 'verified' => false );
					if ( ! empty( $http['verified'] ) ) {
						return array( 'status' => 'done', 'message' => $http['message'] );
					}
					return array( 'status' => 'applied_unverified', 'message' => __( 'Generator tag suppression enabled, but live HTML probe still detected WordPress version string.', 'site-checkup-pro' ) );
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'WordPress generator tag is currently public.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				update_option( 'wpsg_hide_generator', true );
				return array( 'success' => true, 'message' => __( 'WordPress generator version hidden.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				delete_option( 'wpsg_hide_generator' );
				return array( 'success' => true, 'message' => __( 'WordPress generator tag restored.', 'site-checkup-pro' ) );
			},
		) ) );

		// 6.5 Strip Script/Style Version Queries (Opt-In)
		$this->register( new WPSG_Task( array(
			'id'               => 'strip_script_versions',
			'section'          => 'advanced_protection',
			'title'            => __( 'Remove ?ver= from Enqueued Scripts & Styles', 'site-checkup-pro' ),
			'description'      => __( 'Strips version query strings from script and stylesheet URLs (Opt-in: may impact browser caching on file updates).', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$active = get_option( 'wpsg_strip_ver', false );
				return array(
					'status'  => $active ? 'done' : 'pending',
					'message' => $active ? __( 'Version strings stripped from enqueued assets.', 'site-checkup-pro' ) : __( 'Version query strings remain enabled.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				update_option( 'wpsg_strip_ver', true );
				return array( 'success' => true, 'message' => __( '?ver= parameters removed from scripts and styles.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				delete_option( 'wpsg_strip_ver' );
				return array( 'success' => true, 'message' => __( 'Asset version parameters restored.', 'site-checkup-pro' ) );
			},
		) ) );

		// 6.6 Block PHP Execution in Uploads Directory
		$this->register( new WPSG_Task( array(
			'id'               => 'deny_uploads_php',
			'section'          => 'advanced_protection',
			'title'            => __( 'Block PHP Execution in /wp-content/uploads/', 'site-checkup-pro' ),
			'description'      => __( 'Prevents direct execution of PHP scripts in the uploads folder, shutting down web shells uploaded via plugin vulnerabilities.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => "location ~* ^/wp-content/uploads/.*\\.php$ { deny all; }",
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'deny_uploads_php' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'deny_uploads_php' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'PHP execution currently allowed in /uploads/.', 'site-checkup-pro' ),
				);
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'deny_uploads_php' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'deny_uploads_php' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'deny_uploads_php' );
			},
		) ) );

		// 6.7 Core Checksums Integrity Scan
		$this->register( new WPSG_Task( array(
			'id'               => 'core_checksum_integrity',
			'section'          => 'advanced_protection',
			'title'            => __( 'WordPress Core Files Checksum Scan', 'site-checkup-pro' ),
			'description'      => __( 'Compares local WordPress core files against official WordPress.org release checksums (strictly excluding wp-content).', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				return class_exists( 'WPSG_Integrity_Monitor' ) ? WPSG_Integrity_Monitor::check_core_checksums() : array( 'status' => 'pending' );
			},
			'run_callback'     => function () {
				return class_exists( 'WPSG_Integrity_Monitor' ) ? WPSG_Integrity_Monitor::check_core_checksums( true ) : array( 'success' => false );
			},
		) ) );

		// 6.8 Scan Uploads for Executable PHP Files
		$this->register( new WPSG_Task( array(
			'id'               => 'scan_uploads_executables',
			'section'          => 'advanced_protection',
			'title'            => __( 'Scan Uploads for Executable Scripts', 'site-checkup-pro' ),
			'description'      => __( 'Audits /wp-content/uploads/ for suspicious .php, .phtml, or .phar scripts.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'status_callback'  => function () {
				return class_exists( 'WPSG_Integrity_Monitor' ) ? WPSG_Integrity_Monitor::scan_uploads_for_executables() : array( 'status' => 'pending' );
			},
			'run_callback'     => function () {
				return class_exists( 'WPSG_Integrity_Monitor' ) ? WPSG_Integrity_Monitor::scan_uploads_for_executables() : array( 'success' => false );
			},
		) ) );

		// 6.9 Lightweight Basic Firewall Rules (Opt-In, Individually Toggleable)
		$this->register( new WPSG_Task( array(
			'id'               => 'basic_firewall_rules',
			'section'          => 'advanced_protection',
			'title'            => __( 'Lightweight Query String Firewall', 'site-checkup-pro' ),
			'description'      => __( 'Hardcoded rule set blocking SQL injection, XSS, and traversal signatures in URL query strings (Opt-in; does not replace a network WAF).', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => "if (\$query_string ~* \"(union.*select|<script|\\.\\./|base64_decode)\") { return 403; }",
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'basic_firewall_rules' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'basic_firewall_sqli' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'Query string firewall is disabled.', 'site-checkup-pro' ),
				);
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'basic_firewall_sqli' );
			},
			'run_callback'     => function () {
				WPSG_Htaccess_Manager::enable_named_rule( 'basic_firewall_sqli' );
				WPSG_Htaccess_Manager::enable_named_rule( 'basic_firewall_xss' );
				WPSG_Htaccess_Manager::enable_named_rule( 'basic_firewall_traversal' );
				return WPSG_Htaccess_Manager::enable_named_rule( 'basic_firewall_rce' );
			},
			'undo_callback'    => function () {
				WPSG_Htaccess_Manager::disable_named_rule( 'basic_firewall_sqli' );
				WPSG_Htaccess_Manager::disable_named_rule( 'basic_firewall_xss' );
				WPSG_Htaccess_Manager::disable_named_rule( 'basic_firewall_traversal' );
				return WPSG_Htaccess_Manager::disable_named_rule( 'basic_firewall_rce' );
			},
		) ) );

		// 6.10 Bad Bots Noise Reduction
		$this->register( new WPSG_Task( array(
			'id'               => 'bad_bots_noise_reduction',
			'section'          => 'advanced_protection',
			'title'            => __( 'Noise Reduction: Block Known Vulnerability Scanners', 'site-checkup-pro' ),
			'description'      => __( 'Blocks requests matching known scanner user agents (sqlmap, nikto, wpscan). Accurately labeled: reduces automated scan log noise.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'requires_reauth'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => "if (\$http_user_agent ~* \"(sqlmap|nikto|wpscan|dirbuster)\") { return 403; }",
			'status_callback'  => function () {
				$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'bad_bots_noise_reduction' ) : array( 'verified' => false );
				if ( ! empty( $http['verified'] ) ) {
					return array( 'status' => 'done', 'message' => $http['message'] );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'bad_bots' );
				if ( $has ) {
					return array( 'status' => 'applied_unverified', 'message' => __( 'Directive applied, but live HTTP verification could not confirm enforcement yet.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() && ! WPSG_Htaccess_Manager::has_nginx_tier1() && ! WPSG_Htaccess_Manager::has_nginx_tier2() ) {
					return array( 'status' => 'pending', 'is_na' => false, 'message' => __( 'Cannot be applied automatically on this hosting setup — manual step required.', 'site-checkup-pro' ) );
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'Scanner user-agent filter disabled.', 'site-checkup-pro' ),
				);
			},
			'diff_callback'    => function () {
				return WPSG_Htaccess_Manager::get_named_rule_diff( 'bad_bots' );
			},
			'run_callback'     => function () {
				return WPSG_Htaccess_Manager::enable_named_rule( 'bad_bots' );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::disable_named_rule( 'bad_bots' );
			},
		) ) );

		// 6.11 Content-Security-Policy (Report-Only Mode)
		$this->register( new WPSG_Task( array(
			'id'               => 'security_headers_csp',
			'section'          => 'advanced_protection',
			'title'            => __( 'Content-Security-Policy (Report-Only Mode)', 'site-checkup-pro' ),
			'description'      => __( 'Deploys CSP in safe Report-Only mode to log potential violations without breaking page builders or analytics.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$mode = get_option( 'wpsg_csp_mode', '' );
				if ( 'report_only' === $mode || 'enforce' === $mode ) {
					$http = class_exists( 'WPSG_HTTP_Verifier' ) ? WPSG_HTTP_Verifier::verify_task( 'security_headers_csp' ) : array( 'verified' => false );
					if ( ! empty( $http['verified'] ) ) {
						return array( 'status' => 'done', 'message' => $http['message'] );
					}
					return array( 'status' => 'applied_unverified', 'message' => __( 'CSP enabled in settings, but live HTTP header could not be verified on front-end.', 'site-checkup-pro' ) );
				}
				return array(
					'status'  => 'pending',
					'message' => __( 'Content-Security-Policy is disabled.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				update_option( 'wpsg_csp_mode', 'report_only' );
				return array( 'success' => true, 'message' => __( 'CSP enabled in Report-Only mode.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				delete_option( 'wpsg_csp_mode' );
				return array( 'success' => true, 'message' => __( 'Content-Security-Policy disabled.', 'site-checkup-pro' ) );
			},
		) ) );

		// 6.12 Admin Notice Focus Mode & Dashboard Declutter
		$this->register( new WPSG_Task( array(
			'id'               => 'admin_notice_focus_mode',
			'section'          => 'advanced_protection',
			'title'            => __( 'Admin Notice Focus Mode & Dashboard Declutter', 'site-checkup-pro' ),
			'description'      => __( 'Buffers and sanitizes promotional plugin notices while never suppressing WordPress core updates or security warnings.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'instant',
			'has_undo'         => true,
			'status_callback'  => function () {
				$active = get_option( 'wpsg_focus_mode', false );
				return array(
					'status'  => $active ? 'done' : 'pending',
					'message' => $active ? __( 'Notice Focus Mode and dashboard declutter active.', 'site-checkup-pro' ) : __( 'Standard WordPress admin notices visible.', 'site-checkup-pro' ),
				);
			},
			'run_callback'     => function () {
				update_option( 'wpsg_focus_mode', true );
				update_option( 'wpsg_declutter_dashboard', true );
				return array( 'success' => true, 'message' => __( 'Focus Mode enabled.', 'site-checkup-pro' ) );
			},
			'undo_callback'    => function () {
				delete_option( 'wpsg_focus_mode' );
				delete_option( 'wpsg_declutter_dashboard' );
				return array( 'success' => true, 'message' => __( 'Standard admin notices restored.', 'site-checkup-pro' ) );
			},
		) ) );

		// 6.13 Application Password Audit & Governance
		$this->register( new WPSG_Task( array(
			'id'               => 'audit_app_passwords',
			'section'          => 'advanced_protection',
			'title'            => __( 'Audit & Govern Application Passwords', 'site-checkup-pro' ),
			'description'      => __( 'Audit all active application passwords across users, surface unused credentials, and revoke with re-authentication.', 'site-checkup-pro' ),
			'automation_level' => 'B',
			'sub_type'         => 'guided',
			'guide_data'       => array( 'action' => 'open_app_passwords_modal' ),
			'status_callback'  => function () {
				$passwords = class_exists( 'WPSG_App_Password_Manager' ) ? WPSG_App_Password_Manager::get_all_application_passwords() : array();
				$count     = count( $passwords );
				return array(
					'status'  => ( 0 === $count ) ? 'done' : 'attention',
					'message' => sprintf( __( '%d application password(s) active on this site.', 'site-checkup-pro' ), $count ),
				);
			},
		) ) );

		// ==========================================
		// SECTION 7: CLIENT REPORT
		// ==========================================

		$this->register( new WPSG_Task( array(
			'id'               => 'client_security_report',
			'section'          => 'report',
			'title'            => __( 'Client SOP Coverage Report', 'site-checkup-pro' ),
			'description'      => __( 'Generate a print-ready client audit report summarizing completed hardening, active protections, and outstanding items.', 'site-checkup-pro' ),
			'automation_level' => 'D',
			'sub_type'         => 'report',
		) ) );

		/**
		 * Fires after all built-in tasks are registered.
		 * Allows a future Pro add-on plugin (or custom agency integration)
		 * to register additional tasks dynamically via $registry->register().
		 *
		 * @param WPSG_Task_Registry $this Task registry instance.
		 */
		do_action( 'wpsg_register_tasks', $this );
	}

	/**
	 * Register a single task.
	 *
	 * @param WPSG_Task $task Task instance.
	 */
	public function register( WPSG_Task $task ) {
		$this->tasks[ $task->id ] = $task;
	}

	/**
	 * Get all registered tasks.
	 *
	 * @return array<string, WPSG_Task>
	 */
	public function get_all() {
		return $this->tasks;
	}

	/**
	 * Get task by ID.
	 *
	 * @param string $task_id Task identifier.
	 * @return WPSG_Task|null
	 */
	public function get( $task_id ) {
		return isset( $this->tasks[ $task_id ] ) ? $this->tasks[ $task_id ] : null;
	}

	/**
	 * Get tasks by SOP section.
	 *
	 * @param string $section Section key.
	 * @return array<string, WPSG_Task>
	 */
	public function get_by_section( $section ) {
		$filtered = array();
		foreach ( $this->tasks as $id => $task ) {
			if ( $task->section === $section ) {
				$filtered[ $id ] = $task;
			}
		}
		return $filtered;
	}
}
