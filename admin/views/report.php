<?php
/**
 * Client-Facing SOP Coverage Report View
 *
 * Printable, high-fidelity client report template.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$data = WPSG_Report_Generator::get_report_data();
?>

<div class="wrap wpsg-report-wrap" id="wpsg-report-app">
	<!-- Actions Bar (Hidden on print) -->
	<div class="wpsg-report-toolbar no-print">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=site-checkup-pro' ) ); ?>" class="wpsg-btn wpsg-btn-outline">
			&larr; <?php esc_html_e( 'Back to Dashboard', 'site-checkup-pro' ); ?>
		</a>
		<div class="wpsg-report-actions">
			<button type="button" class="wpsg-btn wpsg-btn-primary" onclick="window.print();">
				<span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print / Save as PDF', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>

	<!-- Printable Report Container -->
	<div class="wpsg-report-document" id="wpsg-printable-area">
		<!-- Document Header -->
		<header class="wpsg-report-header">
			<div class="wpsg-report-header-left">
				<div class="wpsg-report-logo">
					<span class="dashicons dashicons-shield"></span>
					<h2><?php esc_html_e( 'Site Checkup Pro', 'site-checkup-pro' ); ?></h2>
				</div>
				<h1 class="wpsg-report-title"><?php esc_html_e( 'Security Check-up & SOP Hardening Report', 'site-checkup-pro' ); ?></h1>
				<p class="wpsg-report-meta">
					<strong><?php esc_html_e( 'Target Website:', 'site-checkup-pro' ); ?></strong> <?php echo esc_html( $data['site_name'] ); ?> (<code><?php echo esc_html( $data['site_url'] ); ?></code>)<br />
					<strong><?php esc_html_e( 'Generated On:', 'site-checkup-pro' ); ?></strong> <?php echo esc_html( $data['generated_at'] ); ?> &bull; 
					<strong><?php esc_html_e( 'Prepared By:', 'site-checkup-pro' ); ?></strong> <?php echo esc_html( $data['agency_name'] ); ?>
				</p>
			</div>

			<div class="wpsg-report-score-box">
				<div class="wpsg-score-circle">
					<span class="wpsg-score-number"><?php echo esc_html( $data['coverage_pct'] ); ?>%</span>
					<span class="wpsg-score-label"><?php esc_html_e( 'SOP Coverage', 'site-checkup-pro' ); ?></span>
				</div>
				<p class="wpsg-score-sub"><?php printf( esc_html__( '%1$d of %2$d tasks complete', 'site-checkup-pro' ), esc_html( $data['done_tasks'] ), esc_html( $data['total_tasks'] ) ); ?></p>
			</div>
		</header>

		<!-- Disclaimer Box -->
		<div class="wpsg-report-disclaimer">
			<p>
				<strong><?php esc_html_e( 'Notice & Disclaimer:', 'site-checkup-pro' ); ?></strong>
				<?php esc_html_e( 'This document reports checklist adherence to the agency standard operating procedure (SOP) for WordPress security hardening. It reflects configured protections, server rules, and maintenance processes at the time of report generation. It is not an absolute guarantee against zero-day exploits or targeted penetration attempts.', 'site-checkup-pro' ); ?>
			</p>
		</div>

		<!-- Section 1: Hardened & Completed Controls -->
		<section class="wpsg-report-section">
			<h3 class="wpsg-section-heading">
				<span class="dashicons dashicons-yes-alt"></span>
				<?php esc_html_e( 'Active Security Controls & Hardening Applied', 'site-checkup-pro' ); ?> (<?php echo count( $data['completed'] ); ?>)
			</h3>
			<table class="wpsg-report-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Security Item', 'site-checkup-pro' ); ?></th>
						<th><?php esc_html_e( 'Protection Description', 'site-checkup-pro' ); ?></th>
						<th><?php esc_html_e( 'Verification Status', 'site-checkup-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $data['completed'] ) ) : ?>
						<tr><td colspan="3"><?php esc_html_e( 'No automated hardening tasks marked as complete.', 'site-checkup-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $data['completed'] as $task ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $task['title'] ); ?></strong></td>
								<td><?php echo esc_html( $task['description'] ); ?></td>
								<td>
									<span class="wpsg-badge wpsg-badge-done">
										<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Active & Verified', 'site-checkup-pro' ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<!-- Section 2: Manual Audits & Verified Processes -->
		<?php if ( ! empty( $data['manual_done'] ) ) : ?>
			<section class="wpsg-report-section">
				<h3 class="wpsg-section-heading">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Manual Audit Cycles & Credential Rotations', 'site-checkup-pro' ); ?> (<?php echo count( $data['manual_done'] ); ?>)
				</h3>
				<table class="wpsg-report-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Audit Process', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Notes & Vault Reference', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'site-checkup-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['manual_done'] as $task ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $task['title'] ); ?></strong></td>
								<td><?php echo ! empty( $task['note'] ) ? esc_html( $task['note'] ) : esc_html__( 'Verified per agency SOP.', 'site-checkup-pro' ); ?></td>
								<td>
									<span class="wpsg-badge wpsg-badge-done">
										<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Verified', 'site-checkup-pro' ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<!-- Section 3: Attention & Outstanding Items -->
		<?php if ( ! empty( $data['attention'] ) || ! empty( $data['pending'] ) ) : ?>
			<section class="wpsg-report-section">
				<h3 class="wpsg-section-heading">
					<span class="dashicons dashicons-flag"></span>
					<?php esc_html_e( 'Pending Checklist Items & Recommendations', 'site-checkup-pro' ); ?>
				</h3>
				<table class="wpsg-report-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Item', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Recommended Action', 'site-checkup-pro' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( array_merge( $data['attention'], $data['pending'] ) as $task ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $task['title'] ); ?></strong></td>
								<td>
									<?php if ( 'attention' === $task['status'] ) : ?>
										<span class="wpsg-badge wpsg-badge-attention"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Attention', 'site-checkup-pro' ); ?></span>
									<?php elseif ( 'not_applicable' === $task['status'] ) : ?>
										<span class="wpsg-badge wpsg-badge-subtle"><span class="dashicons dashicons-minus"></span> <?php esc_html_e( 'Nginx Environment', 'site-checkup-pro' ); ?></span>
									<?php else : ?>
										<span class="wpsg-badge wpsg-badge-pending"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Pending', 'site-checkup-pro' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo ! empty( $task['live_message'] ) ? esc_html( $task['live_message'] ) : esc_html( $task['description'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>

		<!-- Section 4: Audit Activity Trail -->
		<section class="wpsg-report-section">
			<h3 class="wpsg-section-heading">
				<span class="dashicons dashicons-portfolio"></span>
				<?php esc_html_e( 'Hardening Activity Log', 'site-checkup-pro' ); ?>
			</h3>
			<table class="wpsg-report-table wpsg-table-sm">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date/Time', 'site-checkup-pro' ); ?></th>
						<th><?php esc_html_e( 'Action', 'site-checkup-pro' ); ?></th>
						<th><?php esc_html_e( 'Administrator', 'site-checkup-pro' ); ?></th>
						<th><?php esc_html_e( 'Result', 'site-checkup-pro' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $data['audit_logs'] ) ) : ?>
						<tr><td colspan="4"><?php esc_html_e( 'No audit entries recorded yet.', 'site-checkup-pro' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( array_slice( $data['audit_logs'], 0, 15 ) as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->created_at ); ?></td>
								<td><code><?php echo esc_html( $log->task_id ); ?></code> (<?php echo esc_html( $log->action ); ?>)</td>
								<td><?php echo esc_html( $log->display_name ? $log->display_name : $log->user_login ); ?></td>
								<td>
									<span class="wpsg-badge wpsg-badge-<?php echo 'success' === $log->result ? 'done' : 'failed'; ?>">
										<?php echo esc_html( ucfirst( $log->result ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</section>

		<!-- Footer -->
		<footer class="wpsg-report-footer">
			<p><?php printf( esc_html__( 'Generated by Site Checkup Pro on %1$s for %2$s.', 'site-checkup-pro' ), esc_html( $data['generated_at'] ), esc_html( $data['site_url'] ) ); ?></p>
		</footer>
	</div>
</div>
