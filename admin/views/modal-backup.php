<?php
/**
 * Modal: Backup Gating
 *
 * Enforces a verified backup within the last 48 hours before file writes occur.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpsg-modal-overlay" id="wpsg-modal-backup" style="display: none;">
	<div class="wpsg-modal">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title">
				<span class="dashicons dashicons-backup" aria-hidden="true"></span>
				<?php esc_html_e( 'Recent Backup Verification Required', 'site-checkup-pro' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close dialog', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<div class="wpsg-notice wpsg-notice-attention">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Safety Enforcement Gate:', 'site-checkup-pro' ); ?></strong>
					<p><?php esc_html_e( 'The task you selected modifies critical files (.htaccess or wp-config.php). To protect against unexpected server errors, a verified backup within the last 48 hours is required.', 'site-checkup-pro' ); ?></p>
				</div>
			</div>

			<p id="wpsg-backup-modal-msg" style="font-size: 13px; color: var(--wpsg-text-secondary); margin: var(--wpsg-space-3) 0;">
				<?php esc_html_e( 'No verified backup found within the 48-hour recency window.', 'site-checkup-pro' ); ?>
			</p>

			<div class="wpsg-safety-grid">
				<div class="wpsg-safety-card">
					<span class="wpsg-safety-card-title"><?php esc_html_e( 'Backup Plugin', 'site-checkup-pro' ); ?></span>
					<p class="wpsg-safety-card-desc" style="margin-bottom: var(--wpsg-space-3);">
						<?php esc_html_e( 'Open your installed backup plugin and take a snapshot now.', 'site-checkup-pro' ); ?>
					</p>
					<a href="#" id="wpsg-backup-trigger-link" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" target="_blank">
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
						<span id="wpsg-backup-trigger-text"><?php esc_html_e( 'Open Backup Plugin', 'site-checkup-pro' ); ?></span>
					</a>
				</div>

				<div class="wpsg-safety-card">
					<span class="wpsg-safety-card-title"><?php esc_html_e( 'Host Snapshot', 'site-checkup-pro' ); ?></span>
					<p class="wpsg-safety-card-desc" style="margin-bottom: var(--wpsg-space-3);">
						<?php esc_html_e( 'If you have taken a host/cPanel backup in the last 48h, confirm it.', 'site-checkup-pro' ); ?>
					</p>
					<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-confirm-manual-backup-modal">
						<span class="dashicons dashicons-saved" aria-hidden="true"></span>
						<?php esc_html_e( 'Confirm Host Backup', 'site-checkup-pro' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal><?php esc_html_e( 'Cancel', 'site-checkup-pro' ); ?></button>
		</div>
	</div>
</div>
