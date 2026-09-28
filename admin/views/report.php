<?php
/**
 * Client-Facing SOP Coverage Report View
 *
 * Printable, executive-grade client report template with emergency incident response sheet.
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

try {
	$wpsg_data = WPSG_Report_Generator::get_report_data();
} catch ( \Throwable $e ) {
	$wpsg_data = array(
		'site_name'        => get_bloginfo( 'name' ),
		'site_url'         => home_url(),
		'generated_at'     => current_time( 'F j, Y, g:i a' ),
		'agency_name'      => get_bloginfo( 'name' ) . ' Security Team',
		'coverage_pct'     => 0,
		'total_tasks'      => 0,
		'done_tasks'       => 0,
		'completed'        => array(),
		'manual_done'      => array(),
		'attention'        => array(),
		'pending'          => array(),
		'audit_logs'       => array(),
		'incident_contact' => array( 'name' => '', 'email' => '', 'phone' => '', 'notes' => '' ),
	);
}
?>

<div class="wrap wpsg-wrap wpsg-report-container" id="wpsg-report-app">
	<!-- Actions Bar (Hidden on print) -->
	<div class="wpsg-report-toolbar no-print">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=genioussonu-site-checkup' ) ); ?>" class="wpsg-btn wpsg-btn-secondary">
			&larr; <?php esc_html_e( 'Back to Dashboard', 'genioussonu-site-checkup' ); ?>
		</a>
		<div class="wpsg-report-actions">
			<button type="button" class="wpsg-btn wpsg-btn-primary" onclick="window.print();">
				<span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print / Save as PDF', 'genioussonu-site-checkup' ); ?>
			</button>
		</div>
	</div>

	<!-- Printable Report Container -->
	<div class="wpsg-report-doc" id="wpsg-printable-area">
		<!-- Document Header -->
		<header class="wpsg-report-header">
			<div class="wpsg-report-header-left">
				<div class="wpsg-report-brand-lockup">
					<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/logo.svg' ); ?>" alt="GeniousSonu Site Checkup" class="wpsg-report-logo-img" height="42" />
				</div>
				<h1 class="wpsg-report-title"><?php esc_html_e( 'Security Check-up & SOP Hardening Report', 'genioussonu-site-checkup' ); ?></h1>
				<div class="wpsg-report-meta-grid">
					<div><strong><?php esc_html_e( 'Target Website:', 'genioussonu-site-checkup' ); ?></strong> <?php echo esc_html( $wpsg_data['site_name'] ); ?> (<code><?php echo esc_html( $wpsg_data['site_url'] ); ?></code>)</div>
					<div><strong><?php esc_html_e( 'Generated On:', 'genioussonu-site-checkup' ); ?></strong> <?php echo esc_html( $wpsg_data['generated_at'] ); ?> &bull; <strong><?php esc_html_e( 'Prepared By:', 'genioussonu-site-checkup' ); ?></strong> <?php echo esc_html( $wpsg_data['agency_name'] ); ?></div>
				</div>
			</div>

			<div class="wpsg-report-score-panel" style="display: flex; align-items: center; gap: 20px;">
				<?php if ( $wpsg_data['coverage_pct'] >= 80 ) : ?>
					<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/badge-sop-verified.svg' ); ?>" alt="<?php esc_attr_e( 'SOP Verified', 'genioussonu-site-checkup' ); ?>" class="wpsg-report-badge-img" width="68" height="68" />
				<?php endif; ?>
				<div>
					<div class="wpsg-report-score-number"><?php echo esc_html( $wpsg_data['coverage_pct'] ); ?>%</div>
					<div class="wpsg-report-score-label"><?php esc_html_e( 'SOP Coverage', 'genioussonu-site-checkup' ); ?></div>
					<div style="font-size: 12px; color: var(--wpsg-text-secondary); margin-top: 4px;">
						<?php
						/* translators: 1: number of done tasks, 2: total number of tasks */
						printf( esc_html__( '%1$d of %2$d tasks complete', 'genioussonu-site-checkup' ), esc_html( $wpsg_data['done_tasks'] ), esc_html( $wpsg_data['total_tasks'] ) );
						?>
					</div>
				</div>
			</div>
		</header>

		<!-- Disclaimer Box -->
		<div class="wpsg-report-disclaimer-card">
			<strong><?php esc_html_e( 'Notice & Disclaimer:', 'genioussonu-site-checkup' ); ?></strong>
			<?php esc_html_e( 'This document reports checklist adherence to the agency standard operating procedure (SOP) for WordPress security hardening. It reflects configured protections, server rules, and maintenance processes at the time of report generation. It is not an absolute guarantee against zero-day exploits or targeted penetration attempts.', 'genioussonu-site-checkup' ); ?>
		</div>

		<!-- Incident Response & Emergency Escalation Sheet -->
		<div class="wpsg-report-block wpsg-incident-contact-block" style="margin-bottom: 24px; padding: 16px 20px; background: var(--wpsg-surface); border: 1px solid var(--wpsg-border); border-radius: var(--wpsg-radius-md);">
			<h3 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 600; color: var(--wpsg-text-primary); display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-phone" style="color: var(--wpsg-brand); font-size: 18px;"></span>
				<?php esc_html_e( 'Emergency Incident Response Escalation Sheet', 'genioussonu-site-checkup' ); ?>
			</h3>
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; font-size: 13px;">
				<div>
					<span style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Primary Contact:', 'genioussonu-site-checkup' ); ?></span><br />
					<strong><?php echo ! empty( $wpsg_data['incident_contact']['name'] ) ? esc_html( $wpsg_data['incident_contact']['name'] ) : esc_html__( 'Agency Security Desk', 'genioussonu-site-checkup' ); ?></strong>
				</div>
				<div>
					<span style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Emergency Email:', 'genioussonu-site-checkup' ); ?></span><br />
					<strong><?php echo ! empty( $wpsg_data['incident_contact']['email'] ) ? esc_html( $wpsg_data['incident_contact']['email'] ) : esc_html( get_option( 'admin_email' ) ); ?></strong>
				</div>
				<div>
					<span style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Emergency Phone / Slack:', 'genioussonu-site-checkup' ); ?></span><br />
					<strong><?php echo ! empty( $wpsg_data['incident_contact']['phone'] ) ? esc_html( $wpsg_data['incident_contact']['phone'] ) : esc_html__( 'On-call escalation pager', 'genioussonu-site-checkup' ); ?></strong>
				</div>
			</div>
			<?php if ( ! empty( $wpsg_data['incident_contact']['notes'] ) ) : ?>
				<div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--wpsg-border); font-size: 12px; color: var(--wpsg-text-secondary);">
					<strong><?php esc_html_e( 'Incident Protocol:', 'genioussonu-site-checkup' ); ?></strong> <?php echo esc_html( $wpsg_data['incident_contact']['notes'] ); ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Section 1: Hardened & Completed Controls -->
		<div class="wpsg-report-block">
			<h3 class="wpsg-report-block-heading">
				<?php esc_html_e( 'Active Security Controls & Hardening Applied', 'genioussonu-site-checkup' ); ?> (<?php echo count( $wpsg_data['completed'] ); ?>)
			</h3>
			<div class="wpsg-table-card">
				<table class="wpsg-table">
					<thead>
						<tr>
							<th style="width: 260px;"><?php esc_html_e( 'Security Item', 'genioussonu-site-checkup' ); ?></th>
							<th><?php esc_html_e( 'Protection Description', 'genioussonu-site-checkup' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Verification Status', 'genioussonu-site-checkup' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $wpsg_data['completed'] ) ) : ?>
							<tr><td colspan="3" style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'No automated hardening tasks marked as complete.', 'genioussonu-site-checkup' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $wpsg_data['completed'] as $wpsg_task ) : ?>
								<tr>
									<td><strong style="color: var(--wpsg-text-primary);"><?php echo esc_html( $wpsg_task['title'] ); ?></strong></td>
									<td style="color: var(--wpsg-text-secondary);"><?php echo esc_html( $wpsg_task['description'] ); ?></td>
									<td>
										<span class="wpsg-status-indicator wpsg-status-done">
											<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Active & Verified', 'genioussonu-site-checkup' ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Section 2: Manual Audits & Verified Processes -->
		<?php if ( ! empty( $wpsg_data['manual_done'] ) ) : ?>
			<div class="wpsg-report-block">
				<h3 class="wpsg-report-block-heading">
					<?php esc_html_e( 'Manual Audit Cycles & Credential Rotations', 'genioussonu-site-checkup' ); ?> (<?php echo count( $wpsg_data['manual_done'] ); ?>)
				</h3>
				<div class="wpsg-table-card">
					<table class="wpsg-table">
						<thead>
							<tr>
								<th style="width: 260px;"><?php esc_html_e( 'Audit Process', 'genioussonu-site-checkup' ); ?></th>
								<th><?php esc_html_e( 'Notes & Vault Reference', 'genioussonu-site-checkup' ); ?></th>
								<th style="width: 150px;"><?php esc_html_e( 'Status', 'genioussonu-site-checkup' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $wpsg_data['manual_done'] as $wpsg_task ) : ?>
								<tr>
									<td><strong style="color: var(--wpsg-text-primary);"><?php echo esc_html( $wpsg_task['title'] ); ?></strong></td>
									<td style="color: var(--wpsg-text-secondary);"><?php echo ! empty( $wpsg_task['note'] ) ? esc_html( $wpsg_task['note'] ) : esc_html__( 'Verified per agency SOP.', 'genioussonu-site-checkup' ); ?></td>
									<td>
										<span class="wpsg-status-indicator wpsg-status-done">
											<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Verified', 'genioussonu-site-checkup' ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>

		<!-- Section 3: Attention & Outstanding Items -->
		<?php if ( ! empty( $wpsg_data['attention'] ) || ! empty( $wpsg_data['pending'] ) ) : ?>
			<div class="wpsg-report-block">
				<h3 class="wpsg-report-block-heading">
					<?php esc_html_e( 'Pending Checklist Items & Recommendations', 'genioussonu-site-checkup' ); ?>
				</h3>
				<div class="wpsg-table-card">
					<table class="wpsg-table">
						<thead>
							<tr>
								<th style="width: 260px;"><?php esc_html_e( 'Item', 'genioussonu-site-checkup' ); ?></th>
								<th style="width: 150px;"><?php esc_html_e( 'Status', 'genioussonu-site-checkup' ); ?></th>
								<th><?php esc_html_e( 'Recommended Action', 'genioussonu-site-checkup' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_merge( $wpsg_data['attention'], $wpsg_data['pending'] ) as $wpsg_task ) : ?>
								<tr>
									<td><strong style="color: var(--wpsg-text-primary);"><?php echo esc_html( $wpsg_task['title'] ); ?></strong></td>
									<td>
										<?php if ( 'attention' === $wpsg_task['status'] ) : ?>
											<span class="wpsg-status-indicator wpsg-status-attention"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Attention', 'genioussonu-site-checkup' ); ?></span>
										<?php elseif ( 'not_applicable' === $wpsg_task['status'] ) : ?>
											<span class="wpsg-status-indicator wpsg-status-na"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'N/A (Nginx)', 'genioussonu-site-checkup' ); ?></span>
										<?php else : ?>
											<span class="wpsg-status-indicator wpsg-status-pending"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Pending', 'genioussonu-site-checkup' ); ?></span>
										<?php endif; ?>
									</td>
									<td style="color: var(--wpsg-text-secondary);"><?php echo ! empty( $wpsg_task['live_message'] ) ? esc_html( $wpsg_task['live_message'] ) : esc_html( $wpsg_task['description'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		<?php endif; ?>

		<!-- Section 4: Audit Activity Trail -->
		<div class="wpsg-report-block">
			<h3 class="wpsg-report-block-heading">
				<?php esc_html_e( 'Recent Hardening Activity Log', 'genioussonu-site-checkup' ); ?>
			</h3>
			<div class="wpsg-table-card">
				<table class="wpsg-table">
					<thead>
						<tr>
							<th style="width: 170px;"><?php esc_html_e( 'Date/Time', 'genioussonu-site-checkup' ); ?></th>
							<th style="width: 220px;"><?php esc_html_e( 'Task Action', 'genioussonu-site-checkup' ); ?></th>
							<th><?php esc_html_e( 'Administrator', 'genioussonu-site-checkup' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'Result', 'genioussonu-site-checkup' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $wpsg_data['audit_logs'] ) ) : ?>
							<tr><td colspan="4" style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'No audit entries recorded yet.', 'genioussonu-site-checkup' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( array_slice( $wpsg_data['audit_logs'], 0, 15 ) as $wpsg_log ) : ?>
								<tr>
									<td style="font-variant-numeric: tabular-nums;"><?php echo esc_html( $wpsg_log->created_at ); ?></td>
									<td><code><?php echo esc_html( $wpsg_log->task_id ); ?></code> (<?php echo esc_html( $wpsg_log->action ); ?>)</td>
									<td><?php echo esc_html( $wpsg_log->display_name ? $wpsg_log->display_name : $wpsg_log->user_login ); ?></td>
									<td>
										<span class="wpsg-status-indicator wpsg-status-<?php echo 'success' === $wpsg_log->result ? 'done' : 'critical'; ?>">
											<span class="wpsg-status-dot"></span> <?php echo esc_html( ucfirst( $wpsg_log->result ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Footer -->
		<footer style="margin-top: 32px; padding-top: 16px; border-top: 1px solid var(--wpsg-border); font-size: 12px; color: var(--wpsg-text-muted); text-align: center;">
			<p style="margin: 0;">
				<?php
				/* translators: 1: generation date/time, 2: site URL */
				printf( esc_html__( 'Generated by GeniousSonu Site Checkup on %1$s for %2$s.', 'genioussonu-site-checkup' ), esc_html( $wpsg_data['generated_at'] ), esc_html( $wpsg_data['site_url'] ) );
				?>
			</p>
		</footer>
	</div>
</div>
