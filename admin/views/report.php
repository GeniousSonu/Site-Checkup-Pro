<?php
/**
 * Client-Facing SOP Coverage Report View
 *
 * Printable, executive-grade client report template with emergency incident response sheet.
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

try {
	$data = WPSG_Report_Generator::get_report_data();
} catch ( \Throwable $e ) {
	$data = array(
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
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=site-checkup-pro' ) ); ?>" class="wpsg-btn wpsg-btn-secondary">
			&larr; <?php esc_html_e( 'Back to Dashboard', 'site-checkup-pro' ); ?>
		</a>
		<div class="wpsg-report-actions">
			<button type="button" class="wpsg-btn wpsg-btn-primary" onclick="window.print();">
				<span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print / Save as PDF', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>

	<!-- Printable Report Container -->
	<div class="wpsg-report-doc" id="wpsg-printable-area">
		<!-- Document Header -->
		<header class="wpsg-report-header">
			<div class="wpsg-report-header-left">
				<div class="wpsg-report-brand-lockup">
					<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/logo.svg' ); ?>" alt="Site Checkup Pro" class="wpsg-report-logo-img" height="42" />
				</div>
				<h1 class="wpsg-report-title"><?php esc_html_e( 'Security Check-up & SOP Hardening Report', 'site-checkup-pro' ); ?></h1>
				<div class="wpsg-report-meta-grid">
					<div><strong><?php esc_html_e( 'Target Website:', 'site-checkup-pro' ); ?></strong> <?php echo esc_html( $data['site_name'] ); ?> (<code><?php echo esc_html( $data['site_url'] ); ?></code>)</div>
					<div><strong><?php esc_html_e( 'Generated On:', 'site-checkup-pro' ); ?></strong> <?php echo esc_html( $data['generated_at'] ); ?> &bull; <strong><?php esc_html_e( 'Prepared By:', 'site-checkup-pro' ); ?></strong> <?php echo esc_html( $data['agency_name'] ); ?></div>
				</div>
			</div>

			<div class="wpsg-report-score-panel" style="display: flex; align-items: center; gap: 20px;">
				<?php if ( $data['coverage_pct'] >= 80 ) : ?>
					<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/badge-sop-verified.svg' ); ?>" alt="<?php esc_attr_e( 'SOP Verified', 'site-checkup-pro' ); ?>" class="wpsg-report-badge-img" width="68" height="68" />
				<?php endif; ?>
				<div>
					<div class="wpsg-report-score-number"><?php echo esc_html( $data['coverage_pct'] ); ?>%</div>
					<div class="wpsg-report-score-label"><?php esc_html_e( 'SOP Coverage', 'site-checkup-pro' ); ?></div>
					<div style="font-size: 12px; color: var(--wpsg-text-secondary); margin-top: 4px;">
						<?php
						/* translators: 1: number of done tasks, 2: total number of tasks */
						printf( esc_html__( '%1$d of %2$d tasks complete', 'site-checkup-pro' ), esc_html( $data['done_tasks'] ), esc_html( $data['total_tasks'] ) );
						?>
					</div>
				</div>
			</div>
		</header>

		<!-- Disclaimer Box -->
		<div class="wpsg-report-disclaimer-card">
			<strong><?php esc_html_e( 'Notice & Disclaimer:', 'site-checkup-pro' ); ?></strong>
			<?php esc_html_e( 'This document reports checklist adherence to the agency standard operating procedure (SOP) for WordPress security hardening. It reflects configured protections, server rules, and maintenance processes at the time of report generation. It is not an absolute guarantee against zero-day exploits or targeted penetration attempts.', 'site-checkup-pro' ); ?>
		</div>

		<!-- Incident Response & Emergency Escalation Sheet -->
		<div class="wpsg-report-block wpsg-incident-contact-block" style="margin-bottom: 24px; padding: 16px 20px; background: var(--wpsg-surface); border: 1px solid var(--wpsg-border); border-radius: var(--wpsg-radius-md);">
			<h3 style="margin: 0 0 10px 0; font-size: 15px; font-weight: 600; color: var(--wpsg-text-primary); display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-phone" style="color: var(--wpsg-brand); font-size: 18px;"></span>
				<?php esc_html_e( 'Emergency Incident Response Escalation Sheet', 'site-checkup-pro' ); ?>
			</h3>
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; font-size: 13px;">
				<div>
					<span style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Primary Contact:', 'site-checkup-pro' ); ?></span><br />
					<strong><?php echo ! empty( $data['incident_contact']['name'] ) ? esc_html( $data['incident_contact']['name'] ) : esc_html__( 'Agency Security Desk', 'site-checkup-pro' ); ?></strong>
				</div>
				<div>
					<span style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Emergency Email:', 'site-checkup-pro' ); ?></span><br />
					<strong><?php echo ! empty( $data['incident_contact']['email'] ) ? esc_html( $data['incident_contact']['email'] ) : esc_html( get_option( 'admin_email' ) ); ?></strong>
				</div>
				<div>
					<span style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Emergency Phone / Slack:', 'site-checkup-pro' ); ?></span><br />
					<strong><?php echo ! empty( $data['incident_contact']['phone'] ) ? esc_html( $data['incident_contact']['phone'] ) : esc_html__( 'On-call escalation pager', 'site-checkup-pro' ); ?></strong>
				</div>
			</div>
			<?php if ( ! empty( $data['incident_contact']['notes'] ) ) : ?>
				<div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed var(--wpsg-border); font-size: 12px; color: var(--wpsg-text-secondary);">
					<strong><?php esc_html_e( 'Incident Protocol:', 'site-checkup-pro' ); ?></strong> <?php echo esc_html( $data['incident_contact']['notes'] ); ?>
				</div>
			<?php endif; ?>
		</div>

		<!-- Section 1: Hardened & Completed Controls -->
		<div class="wpsg-report-block">
			<h3 class="wpsg-report-block-heading">
				<?php esc_html_e( 'Active Security Controls & Hardening Applied', 'site-checkup-pro' ); ?> (<?php echo count( $data['completed'] ); ?>)
			</h3>
			<div class="wpsg-table-card">
				<table class="wpsg-table">
					<thead>
						<tr>
							<th style="width: 260px;"><?php esc_html_e( 'Security Item', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Protection Description', 'site-checkup-pro' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Verification Status', 'site-checkup-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $data['completed'] ) ) : ?>
							<tr><td colspan="3" style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'No automated hardening tasks marked as complete.', 'site-checkup-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $data['completed'] as $task ) : ?>
								<tr>
									<td><strong style="color: var(--wpsg-text-primary);"><?php echo esc_html( $task['title'] ); ?></strong></td>
									<td style="color: var(--wpsg-text-secondary);"><?php echo esc_html( $task['description'] ); ?></td>
									<td>
										<span class="wpsg-status-indicator wpsg-status-done">
											<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Active & Verified', 'site-checkup-pro' ); ?>
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
		<?php if ( ! empty( $data['manual_done'] ) ) : ?>
			<div class="wpsg-report-block">
				<h3 class="wpsg-report-block-heading">
					<?php esc_html_e( 'Manual Audit Cycles & Credential Rotations', 'site-checkup-pro' ); ?> (<?php echo count( $data['manual_done'] ); ?>)
				</h3>
				<div class="wpsg-table-card">
					<table class="wpsg-table">
						<thead>
							<tr>
								<th style="width: 260px;"><?php esc_html_e( 'Audit Process', 'site-checkup-pro' ); ?></th>
								<th><?php esc_html_e( 'Notes & Vault Reference', 'site-checkup-pro' ); ?></th>
								<th style="width: 150px;"><?php esc_html_e( 'Status', 'site-checkup-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $data['manual_done'] as $task ) : ?>
								<tr>
									<td><strong style="color: var(--wpsg-text-primary);"><?php echo esc_html( $task['title'] ); ?></strong></td>
									<td style="color: var(--wpsg-text-secondary);"><?php echo ! empty( $task['note'] ) ? esc_html( $task['note'] ) : esc_html__( 'Verified per agency SOP.', 'site-checkup-pro' ); ?></td>
									<td>
										<span class="wpsg-status-indicator wpsg-status-done">
											<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Verified', 'site-checkup-pro' ); ?>
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
		<?php if ( ! empty( $data['attention'] ) || ! empty( $data['pending'] ) ) : ?>
			<div class="wpsg-report-block">
				<h3 class="wpsg-report-block-heading">
					<?php esc_html_e( 'Pending Checklist Items & Recommendations', 'site-checkup-pro' ); ?>
				</h3>
				<div class="wpsg-table-card">
					<table class="wpsg-table">
						<thead>
							<tr>
								<th style="width: 260px;"><?php esc_html_e( 'Item', 'site-checkup-pro' ); ?></th>
								<th style="width: 150px;"><?php esc_html_e( 'Status', 'site-checkup-pro' ); ?></th>
								<th><?php esc_html_e( 'Recommended Action', 'site-checkup-pro' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_merge( $data['attention'], $data['pending'] ) as $task ) : ?>
								<tr>
									<td><strong style="color: var(--wpsg-text-primary);"><?php echo esc_html( $task['title'] ); ?></strong></td>
									<td>
										<?php if ( 'attention' === $task['status'] ) : ?>
											<span class="wpsg-status-indicator wpsg-status-attention"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Attention', 'site-checkup-pro' ); ?></span>
										<?php elseif ( 'not_applicable' === $task['status'] ) : ?>
											<span class="wpsg-status-indicator wpsg-status-na"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'N/A (Nginx)', 'site-checkup-pro' ); ?></span>
										<?php else : ?>
											<span class="wpsg-status-indicator wpsg-status-pending"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Pending', 'site-checkup-pro' ); ?></span>
										<?php endif; ?>
									</td>
									<td style="color: var(--wpsg-text-secondary);"><?php echo ! empty( $task['live_message'] ) ? esc_html( $task['live_message'] ) : esc_html( $task['description'] ); ?></td>
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
				<?php esc_html_e( 'Recent Hardening Activity Log', 'site-checkup-pro' ); ?>
			</h3>
			<div class="wpsg-table-card">
				<table class="wpsg-table">
					<thead>
						<tr>
							<th style="width: 170px;"><?php esc_html_e( 'Date/Time', 'site-checkup-pro' ); ?></th>
							<th style="width: 220px;"><?php esc_html_e( 'Task Action', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Administrator', 'site-checkup-pro' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'Result', 'site-checkup-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $data['audit_logs'] ) ) : ?>
							<tr><td colspan="4" style="color: var(--wpsg-text-secondary);"><?php esc_html_e( 'No audit entries recorded yet.', 'site-checkup-pro' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( array_slice( $data['audit_logs'], 0, 15 ) as $log ) : ?>
								<tr>
									<td style="font-variant-numeric: tabular-nums;"><?php echo esc_html( $log->created_at ); ?></td>
									<td><code><?php echo esc_html( $log->task_id ); ?></code> (<?php echo esc_html( $log->action ); ?>)</td>
									<td><?php echo esc_html( $log->display_name ? $log->display_name : $log->user_login ); ?></td>
									<td>
										<span class="wpsg-status-indicator wpsg-status-<?php echo 'success' === $log->result ? 'done' : 'critical'; ?>">
											<span class="wpsg-status-dot"></span> <?php echo esc_html( ucfirst( $log->result ) ); ?>
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
				printf( esc_html__( 'Generated by Site Checkup Pro on %1$s for %2$s.', 'site-checkup-pro' ), esc_html( $data['generated_at'] ), esc_html( $data['site_url'] ) );
				?>
			</p>
		</footer>
	</div>
</div>
