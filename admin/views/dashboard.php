<?php
/**
 * Main Check-up Dashboard View
 *
 * Built with an architectural, calm layout inspired by Linear, Stripe, and Cloudflare.
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

$registry      = WPSG_Task_Registry::get_instance();
$sections      = WPSG_Task_Registry::$sections;
$server_type   = WPSG_Htaccess_Manager::get_server_type();
$has_htaccess  = WPSG_Htaccess_Manager::supports_htaccess();
$backup_status = WPSG_Backup_Guard::get_backup_status();
?>

<div class="wrap wpsg-wrap" id="wpsg-app">

	<!-- Top Product Header -->
	<header class="wpsg-page-header">
		<div class="wpsg-header-title-area">
			<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/icon.svg' ); ?>" alt="Site Checkup Pro" width="36" height="36" class="wpsg-brand-icon-img" />
			<div class="wpsg-header-titles">
				<h1>
					<?php esc_html_e( 'Site Checkup Pro', 'site-checkup-pro' ); ?>
					<span class="wpsg-version-tag">v<?php echo esc_html( WPSG_VERSION ); ?></span>
				</h1>
				<p><?php esc_html_e( 'SOP Security Hardening & Audit Orchestrator', 'site-checkup-pro' ); ?></p>
			</div>
		</div>

		<div class="wpsg-header-actions">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-confirm-backup" title="<?php esc_attr_e( 'Confirm that a host/cPanel backup was verified within 48h', 'site-checkup-pro' ); ?>">
				<span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Confirm Host Backup', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-update-baseline" title="<?php esc_attr_e( 'Update trusted administrator and database baseline', 'site-checkup-pro' ); ?>">
				<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Trust Baseline', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-open-settings" title="<?php esc_attr_e( 'Configure API keys and incident response contacts', 'site-checkup-pro' ); ?>">
				<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-batch-run">
				<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run All Safe Tasks', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</header>

	<!-- Live Batch Execution Progress Strip (Hidden by default) -->
	<div class="wpsg-batch-banner" id="wpsg-batch-banner" style="display: none;">
		<div class="wpsg-batch-info">
			<span class="wpsg-spinner" aria-hidden="true"></span>
			<span id="wpsg-batch-text"><?php esc_html_e( 'Processing safe verification tasks...', 'site-checkup-pro' ); ?></span>
			<span class="wpsg-batch-count" id="wpsg-batch-count">0 / 0</span>
		</div>
		<div class="wpsg-batch-progress-bar">
			<div class="wpsg-batch-progress-fill" id="wpsg-batch-progress" style="width: 0%;"></div>
		</div>
		<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-subtle" id="wpsg-batch-cancel"><?php esc_html_e( 'Stop', 'site-checkup-pro' ); ?></button>
	</div>

	<!-- In-Plugin Milestone Review Prompt (Shown once after milestone, dismissible) -->
	<?php
	$wpsg_review_dismissed = get_option( 'wpsg_review_prompt_dismissed', false );
	$wpsg_tasks_done       = (int) get_option( 'wpsg_completed_tasks_count', 0 );
	if ( ! $wpsg_review_dismissed && $wpsg_tasks_done >= 3 ) :
	?>
	<div class="wpsg-review-prompt" id="wpsg-review-prompt" style="margin: 20px 0; padding: 18px 24px; background: var(--wpsg-surface); border: 1px solid var(--wpsg-brand); border-left: 4px solid var(--wpsg-brand); border-radius: var(--wpsg-radius-md); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
		<div style="display: flex; align-items: center; gap: 14px;">
			<span class="dashicons dashicons-star-filled" style="font-size: 28px; width: 28px; height: 28px; color: #f59e0b;" aria-hidden="true"></span>
			<div>
				<h3 style="margin: 0 0 4px; font-size: 14px; font-weight: 600; color: var(--wpsg-text);">
					<?php esc_html_e( 'Loving Site Checkup Pro? Help us grow with a 5-star review!', 'site-checkup-pro' ); ?>
				</h3>
				<p style="margin: 0; font-size: 13px; color: var(--wpsg-text-secondary);">
					<?php
					printf(
						/* translators: %d: number of completed security tasks */
						esc_html__( 'You have successfully run %d security hardening and audit checks. If this plugin saves you time and secures your sites, leaving a quick review on WordPress.org helps us immensely!', 'site-checkup-pro' ),
						$wpsg_tasks_done
					);
					?>
				</p>
			</div>
		</div>
		<div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
			<a href="https://wordpress.org/support/plugin/site-checkup-pro/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-review-now">
				<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Leave a 5-Star Review', 'site-checkup-pro' ); ?>
			</a>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-review-already">
				<?php esc_html_e( 'I Already Did', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-subtle" id="wpsg-btn-review-dismiss" title="<?php esc_attr_e( 'Dismiss permanently', 'site-checkup-pro' ); ?>">
				<?php esc_html_e( 'Maybe Later', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<!-- Factual KPI Metric Strip -->
	<section class="wpsg-kpi-strip" aria-label="<?php esc_attr_e( 'System Status Overview', 'site-checkup-pro' ); ?>">
		<!-- KPI 1: SOP Coverage -->
		<div class="wpsg-kpi-cell">
			<div class="wpsg-kpi-header-row">
				<span class="wpsg-kpi-title"><?php esc_html_e( 'SOP Coverage', 'site-checkup-pro' ); ?></span>
				<span class="dashicons dashicons-chart-pie" aria-hidden="true"></span>
			</div>
			<div class="wpsg-kpi-metric" id="wpsg-kpi-coverage">0%</div>
			<p class="wpsg-kpi-meta-line" id="wpsg-kpi-fraction">0 / 0 tasks active</p>
			<div class="wpsg-kpi-progress-track">
				<div class="wpsg-kpi-progress-fill" id="wpsg-coverage-bar" style="width: 0%;"></div>
			</div>
			<p class="wpsg-kpi-disclaimer-note"><?php esc_html_e( 'Factual checklist coverage; not a penetration test.', 'site-checkup-pro' ); ?></p>
		</div>

		<!-- KPI 2: Server Environment -->
		<div class="wpsg-kpi-cell">
			<div class="wpsg-kpi-header-row">
				<span class="wpsg-kpi-title"><?php esc_html_e( 'Environment', 'site-checkup-pro' ); ?></span>
				<span class="dashicons dashicons-networking" aria-hidden="true"></span>
			</div>
			<div class="wpsg-kpi-metric" id="wpsg-kpi-server"><?php echo esc_html( strtoupper( $server_type ) ); ?></div>
			<p class="wpsg-kpi-meta-line">
				<?php if ( $has_htaccess ) : ?>
					<span class="wpsg-status-indicator wpsg-status-done">
						<span class="wpsg-status-dot"></span> <?php esc_html_e( '.htaccess active', 'site-checkup-pro' ); ?>
					</span>
				<?php else : ?>
					<span class="wpsg-status-indicator wpsg-status-na">
						<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Nginx Directives Mode', 'site-checkup-pro' ); ?>
					</span>
				<?php endif; ?>
			</p>
			<p class="wpsg-kpi-disclaimer-note"><?php printf( esc_html__( 'PHP %s runtime.', 'site-checkup-pro' ), esc_html( PHP_VERSION ) ); ?></p>
		</div>

		<!-- KPI 3: Backup Gate -->
		<div class="wpsg-kpi-cell">
			<div class="wpsg-kpi-header-row">
				<span class="wpsg-kpi-title"><?php esc_html_e( 'Backup Protection', 'site-checkup-pro' ); ?></span>
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
			</div>
			<div class="wpsg-kpi-metric" id="wpsg-kpi-backup-name">
				<?php echo ! empty( $backup_status['plugin_name'] ) ? esc_html( $backup_status['plugin_name'] ) : esc_html__( 'Unverified', 'site-checkup-pro' ); ?>
			</div>
			<p class="wpsg-kpi-meta-line">
				<?php if ( ! empty( $backup_status['is_recent'] ) ) : ?>
					<span class="wpsg-status-indicator wpsg-status-done">
						<span class="wpsg-status-dot"></span> <?php printf( esc_html__( 'Verified (%s hrs ago)', 'site-checkup-pro' ), esc_html( $backup_status['age_hours'] ) ); ?>
					</span>
				<?php else : ?>
					<span class="wpsg-status-indicator wpsg-status-attention">
						<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Backup > 48h required', 'site-checkup-pro' ); ?>
					</span>
				<?php endif; ?>
			</p>
			<p class="wpsg-kpi-disclaimer-note"><?php esc_html_e( 'Protects all file-modifying tasks.', 'site-checkup-pro' ); ?></p>
		</div>

		<!-- KPI 4: Reminders -->
		<div class="wpsg-kpi-cell">
			<div class="wpsg-kpi-header-row">
				<span class="wpsg-kpi-title"><?php esc_html_e( 'Scheduled Checks', 'site-checkup-pro' ); ?></span>
				<span class="dashicons dashicons-clock" aria-hidden="true"></span>
			</div>
			<div class="wpsg-kpi-metric" id="wpsg-kpi-reminders">0</div>
			<p class="wpsg-kpi-meta-line"><?php esc_html_e( 'Active cadence reminders', 'site-checkup-pro' ); ?></p>
			<p class="wpsg-kpi-disclaimer-note"><?php esc_html_e( '15-day credentials & 6-month reviews.', 'site-checkup-pro' ); ?></p>
		</div>
	</section>

	<!-- Navigation Toolbar & Filters -->
	<div class="wpsg-toolbar">
		<nav class="wpsg-nav-tabs" role="tablist">
			<button type="button" class="wpsg-tab active" data-tab="all">
				<?php esc_html_e( 'All Checks', 'site-checkup-pro' ); ?>
			</button>
			<?php foreach ( $sections as $s_key => $s_meta ) : ?>
				<button type="button" class="wpsg-tab" data-tab="<?php echo esc_attr( $s_key ); ?>">
					<?php echo esc_html( $s_meta['label'] ); ?>
				</button>
			<?php endforeach; ?>
			<button type="button" class="wpsg-tab" data-tab="audit_trail">
				<?php esc_html_e( 'Audit Trail', 'site-checkup-pro' ); ?>
			</button>
		</nav>

		<div class="wpsg-filter-group">
			<select id="wpsg-filter-level" class="wpsg-select" aria-label="<?php esc_attr_e( 'Filter by automation level', 'site-checkup-pro' ); ?>">
				<option value=""><?php esc_html_e( 'All Levels', 'site-checkup-pro' ); ?></option>
				<option value="A_instant"><?php esc_html_e( 'Level A: Safe / Instant', 'site-checkup-pro' ); ?></option>
				<option value="A_files"><?php esc_html_e( 'Level A: Config / File Writers', 'site-checkup-pro' ); ?></option>
				<option value="B"><?php esc_html_e( 'Level B: Guided Actions', 'site-checkup-pro' ); ?></option>
				<option value="C"><?php esc_html_e( 'Level C: Manual / Reminders', 'site-checkup-pro' ); ?></option>
			</select>

			<select id="wpsg-filter-status" class="wpsg-select" aria-label="<?php esc_attr_e( 'Filter by status', 'site-checkup-pro' ); ?>">
				<option value=""><?php esc_html_e( 'All Statuses', 'site-checkup-pro' ); ?></option>
				<option value="pending"><?php esc_html_e( 'Pending', 'site-checkup-pro' ); ?></option>
				<option value="done"><?php esc_html_e( 'Completed', 'site-checkup-pro' ); ?></option>
				<option value="attention"><?php esc_html_e( 'Action Needed', 'site-checkup-pro' ); ?></option>
				<option value="failed"><?php esc_html_e( 'Critical / Failed', 'site-checkup-pro' ); ?></option>
				<option value="not_applicable"><?php esc_html_e( 'Not Applicable', 'site-checkup-pro' ); ?></option>
			</select>
		</div>
	</div>

	<!-- Main Operational Table Container -->
	<main class="wpsg-table-container" id="wpsg-tasks-table-wrapper">
		<table class="wpsg-table" id="wpsg-tasks-table">
			<thead>
				<tr>
					<th class="wpsg-col-status"><?php esc_html_e( 'Status', 'site-checkup-pro' ); ?></th>
					<th class="wpsg-col-level"><?php esc_html_e( 'Level', 'site-checkup-pro' ); ?></th>
					<th class="wpsg-col-task"><?php esc_html_e( 'Check / Procedure', 'site-checkup-pro' ); ?></th>
					<th class="wpsg-col-checked"><?php esc_html_e( 'Last Verified', 'site-checkup-pro' ); ?></th>
					<th class="wpsg-col-actions"><?php esc_html_e( 'Action', 'site-checkup-pro' ); ?></th>
				</tr>
			</thead>
			<tbody id="wpsg-tasks-tbody">
				<tr>
					<td colspan="5" style="text-align: center; padding: 40px;">
						<span class="wpsg-spinner" aria-hidden="true"></span>
						<p style="margin: 8px 0 0 0; color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Loading checklist tasks and live status...', 'site-checkup-pro' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
	</main>

	<!-- Historical Audit Log View (Shown when Audit Trail tab is active) -->
	<section class="wpsg-table-container" id="wpsg-audit-view" style="display: none;">
		<table class="wpsg-table" id="wpsg-audit-table">
			<thead>
				<tr>
					<th style="width: 170px;"><?php esc_html_e( 'Timestamp', 'site-checkup-pro' ); ?></th>
					<th style="width: 160px;"><?php esc_html_e( 'Task ID', 'site-checkup-pro' ); ?></th>
					<th style="width: 110px;"><?php esc_html_e( 'Action', 'site-checkup-pro' ); ?></th>
					<th style="width: 140px;"><?php esc_html_e( 'User', 'site-checkup-pro' ); ?></th>
					<th style="width: 110px;"><?php esc_html_e( 'Result', 'site-checkup-pro' ); ?></th>
					<th><?php esc_html_e( 'Technical Log Message (Redacted)', 'site-checkup-pro' ); ?></th>
				</tr>
			</thead>
			<tbody id="wpsg-audit-tbody">
				<!-- Injected dynamically via admin.js -->
			</tbody>
		</table>
	</section>

	<!-- Modal Dialogs -->
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-diff.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-nginx.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-backup.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-note.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-login-rename.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-delete-plugin.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-reauth.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-sessions.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-app-passwords.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-csp-reports.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-settings.php'; ?>

	<!-- Settings Screen Footer / Resource Block -->
	<footer class="wpsg-dashboard-footer" style="margin-top: 32px; padding: 18px 24px; background: var(--wpsg-surface); border: 1px solid var(--wpsg-border); border-radius: var(--wpsg-radius-md); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; font-size: 13px;">
		<div class="wpsg-footer-attribution" style="color: var(--wpsg-text-secondary);">
			<span><?php esc_html_e( 'Developed by', 'site-checkup-pro' ); ?> <strong><a href="https://www.genioussonu.me/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-brand); text-decoration: none;">SK Sahinur Islam</a></strong></span>
			<span style="margin: 0 8px; color: var(--wpsg-border);">&bull;</span>
			<span class="wpsg-footer-version">Site Checkup Pro v<?php echo esc_html( WPSG_VERSION ); ?></span>
		</div>
		<nav class="wpsg-footer-links" style="display: flex; gap: 16px;" aria-label="<?php esc_attr_e( 'Help and documentation links', 'site-checkup-pro' ); ?>">
			<a href="https://www.genioussonu.me/plugin/site-checkup-pro/docs/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-book" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Docs', 'site-checkup-pro' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/site-checkup-pro/support/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-sos" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Support', 'site-checkup-pro' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/site-checkup-pro/changelog/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-backup" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Changelog', 'site-checkup-pro' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/site-checkup-pro/privacy-policy/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-shield" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Privacy Policy', 'site-checkup-pro' ); ?>
			</a>
		</nav>
	</footer>

</div>


