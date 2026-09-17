<?php
/**
 * Task Registry
 *
 * Registers all checklist tasks from the agency Security Check-up SOP.
 *
 * @package SiteCheckupPro
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
			'has_undo'         => true,
			'status_callback'  => array( 'WPSG_Plugin_Integrity', 'detect_unwanted_plugins' ),
			'run_callback'     => array( 'WPSG_Plugin_Integrity', 'detect_unwanted_plugins' ),
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
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $php_version_nginx,
			'status_callback'  => function () {
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Running on Nginx. Use Nginx snippet.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_rule( 'HidePHPVersion' );
				return array( 'status' => $has ? 'done' : 'pending', 'message' => $has ? __( 'Header unset rule active in .htaccess.', 'site-checkup-pro' ) : '' );
			},
			'diff_callback'    => function () use ( $php_version_rules ) {
				return WPSG_Htaccess_Manager::get_diff( 'HidePHPVersion', $php_version_rules );
			},
			'run_callback'     => function () use ( $php_version_rules ) {
				return WPSG_Htaccess_Manager::insert_rule( 'HidePHPVersion', $php_version_rules );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::remove_rule( 'HidePHPVersion' );
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
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $clickjack_nginx,
			'status_callback'  => function () {
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Running on Nginx. Use Nginx snippet.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_rule( 'Clickjacking' );
				return array( 'status' => $has ? 'done' : 'pending', 'message' => $has ? __( 'X-Frame-Options SAMEORIGIN active in .htaccess.', 'site-checkup-pro' ) : '' );
			},
			'diff_callback'    => function () use ( $clickjack_rules ) {
				return WPSG_Htaccess_Manager::get_diff( 'Clickjacking', $clickjack_rules );
			},
			'run_callback'     => function () use ( $clickjack_rules ) {
				return WPSG_Htaccess_Manager::insert_rule( 'Clickjacking', $clickjack_rules );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::remove_rule( 'Clickjacking' );
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
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $nosniff_nginx,
			'status_callback'  => function () {
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Running on Nginx. Use Nginx snippet.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_rule( 'MimeSniffing' );
				return array( 'status' => $has ? 'done' : 'pending', 'message' => $has ? __( 'X-Content-Type-Options nosniff active.', 'site-checkup-pro' ) : '' );
			},
			'diff_callback'    => function () use ( $nosniff_rules ) {
				return WPSG_Htaccess_Manager::get_diff( 'MimeSniffing', $nosniff_rules );
			},
			'run_callback'     => function () use ( $nosniff_rules ) {
				return WPSG_Htaccess_Manager::insert_rule( 'MimeSniffing', $nosniff_rules );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::remove_rule( 'MimeSniffing' );
			},
		) ) );

		// 3.6 Strict Transport Security (HSTS)
		$hsts_rules = "<IfModule mod_headers.c>\nHeader always set Strict-Transport-Security \"max-age=31536000; includeSubDomains; preload\"\n</IfModule>";
		$hsts_nginx = 'add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;';

		$this->register( new WPSG_Task( array(
			'id'               => 'hsts_header',
			'section'          => 'hardening',
			'title'            => __( 'HTTP Strict Transport Security (HSTS)', 'site-checkup-pro' ),
			'description'      => __( 'Enforces HTTPS communication with browsers, protecting against man-in-the-middle SSL-strip attacks.', 'site-checkup-pro' ),
			'automation_level' => 'A',
			'sub_type'         => 'writes_files',
			'requires_backup'  => true,
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $hsts_nginx,
			'status_callback'  => function () {
				if ( ! is_ssl() ) {
					return array( 'status' => 'attention', 'message' => __( 'Site is not SSL enabled. HSTS requires HTTPS.', 'site-checkup-pro' ) );
				}
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Running on Nginx. Use Nginx snippet.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_rule( 'HSTS' );
				return array( 'status' => $has ? 'done' : 'pending', 'message' => $has ? __( 'HSTS header active in .htaccess.', 'site-checkup-pro' ) : '' );
			},
			'diff_callback'    => function () use ( $hsts_rules ) {
				return WPSG_Htaccess_Manager::get_diff( 'HSTS', $hsts_rules );
			},
			'run_callback'     => function () use ( $hsts_rules ) {
				return WPSG_Htaccess_Manager::insert_rule( 'HSTS', $hsts_rules );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::remove_rule( 'HSTS' );
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
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $indexes_nginx,
			'status_callback'  => function () {
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Running on Nginx. Use Nginx snippet.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_rule( 'DisableIndexes' );
				return array( 'status' => $has ? 'done' : 'pending', 'message' => $has ? __( 'Options -Indexes active in .htaccess.', 'site-checkup-pro' ) : '' );
			},
			'diff_callback'    => function () use ( $indexes_rules ) {
				return WPSG_Htaccess_Manager::get_diff( 'DisableIndexes', $indexes_rules );
			},
			'run_callback'     => function () use ( $indexes_rules ) {
				return WPSG_Htaccess_Manager::insert_rule( 'DisableIndexes', $indexes_rules );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::remove_rule( 'DisableIndexes' );
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
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $sensitive_nginx,
			'status_callback'  => function () {
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Running on Nginx. Use Nginx snippet.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_rule( 'ProtectSensitiveFiles' );
				return array( 'status' => $has ? 'done' : 'pending', 'message' => $has ? __( 'Sensitive files blocked in .htaccess.', 'site-checkup-pro' ) : '' );
			},
			'diff_callback'    => function () use ( $sensitive_rules ) {
				return WPSG_Htaccess_Manager::get_diff( 'ProtectSensitiveFiles', $sensitive_rules );
			},
			'run_callback'     => function () use ( $sensitive_rules ) {
				return WPSG_Htaccess_Manager::insert_rule( 'ProtectSensitiveFiles', $sensitive_rules );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::remove_rule( 'ProtectSensitiveFiles' );
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
			'has_undo'         => true,
			'has_diff'         => true,
			'nginx_snippet'    => $xmlrpc_nginx,
			'status_callback'  => function () {
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Running on Nginx. Use Nginx snippet.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_rule( 'BlockXMLRPC' );
				return array( 'status' => $has ? 'done' : 'pending', 'message' => $has ? __( 'xmlrpc.php blocked at server level.', 'site-checkup-pro' ) : '' );
			},
			'diff_callback'    => function () use ( $xmlrpc_htaccess ) {
				return WPSG_Htaccess_Manager::get_diff( 'BlockXMLRPC', $xmlrpc_htaccess );
			},
			'run_callback'     => function () use ( $xmlrpc_htaccess ) {
				return WPSG_Htaccess_Manager::insert_rule( 'BlockXMLRPC', $xmlrpc_htaccess );
			},
			'undo_callback'    => function () {
				return WPSG_Htaccess_Manager::remove_rule( 'BlockXMLRPC' );
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
			'has_undo'         => true,
			'has_diff'         => true,
			'status_callback'  => function () {
				$disallow = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;
				return array(
					'status'  => $disallow ? 'done' : 'pending',
					'message' => $disallow ? __( 'DISALLOW_FILE_EDIT is defined as true.', 'site-checkup-pro' ) : __( 'File editing is currently enabled in wp-admin.', 'site-checkup-pro' ),
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
			'status_callback'  => function () {
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
					return array(
						'status'  => 'done',
						'message' => sprintf( __( 'Custom login URL active: /%s/', 'site-checkup-pro' ), $slug ),
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
				return array(
					'status'  => $active ? 'done' : 'pending',
					'message' => $active ? __( 'User & author enumeration blocked for unauthorized visitors.', 'site-checkup-pro' ) : __( 'User enumeration protection is disabled.', 'site-checkup-pro' ),
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
				return array(
					'status'  => $active ? 'done' : 'pending',
					'message' => $active ? __( 'WordPress generator tag is removed.', 'site-checkup-pro' ) : __( 'WordPress generator tag is currently public.', 'site-checkup-pro' ),
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
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Use Nginx configuration directive on Nginx servers.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'deny_uploads_php' );
				return array(
					'status'  => $has ? 'done' : 'pending',
					'message' => $has ? __( 'PHP execution blocked in /uploads/.', 'site-checkup-pro' ) : __( 'PHP execution currently allowed in /uploads/.', 'site-checkup-pro' ),
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
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Use Nginx configuration on Nginx servers.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'basic_firewall_sqli' );
				return array(
					'status'  => $has ? 'done' : 'pending',
					'message' => $has ? __( 'Lightweight query firewall active.', 'site-checkup-pro' ) : __( 'Query string firewall is disabled.', 'site-checkup-pro' ),
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
				if ( ! WPSG_Htaccess_Manager::supports_htaccess() ) {
					return array( 'status' => 'not_applicable', 'is_na' => true, 'message' => __( 'Use Nginx configuration on Nginx servers.', 'site-checkup-pro' ) );
				}
				$has = WPSG_Htaccess_Manager::has_named_rule( 'bad_bots' );
				return array(
					'status'  => $has ? 'done' : 'pending',
					'message' => $has ? __( 'Scanner noise reduction active.', 'site-checkup-pro' ) : __( 'Scanner user-agent filter disabled.', 'site-checkup-pro' ),
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
				return array(
					'status'  => ( 'report_only' === $mode || 'enforce' === $mode ) ? 'done' : 'pending',
					'message' => ( 'report_only' === $mode )
						? __( 'CSP active in Report-Only mode (capturing violations safely).', 'site-checkup-pro' )
						: ( 'enforce' === $mode ? __( 'CSP active in Enforce mode.', 'site-checkup-pro' ) : __( 'Content-Security-Policy is disabled.', 'site-checkup-pro' ) ),
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
