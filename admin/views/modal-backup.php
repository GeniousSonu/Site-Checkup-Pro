<?php
/**
* Modal: Backup Gating
 *
 * Enforces a verified backup within the last 48 hours before file writes occur.
 *
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
?>
<div class="wpsg-modal-overlay" id="wpsg-modal-backup" style="display: none;">
	<div class="wpsg-modal">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title">
				<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/icon-backup.svg' ); ?>" width="20" height="20" alt="" />
				<?php esc_html_e( 'Recent Backup Verification Required', 'genioussonu-site-checkup' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close dialog', 'genioussonu-site-checkup' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<div class="wpsg-notice wpsg-notice-attention">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Safety Enforcement Gate:', 'genioussonu-site-checkup' ); ?></strong>
					<p><?php esc_html_e( 'The task you selected modifies critical files (.htaccess or wp-config.php). To protect against unexpected server errors, a verified backup within the last 48 hours is required.', 'genioussonu-site-checkup' ); ?></p>
				</div>
			</div>

			<p id="wpsg-backup-modal-msg" style="font-size: 13px; color: var(--wpsg-text-secondary); margin: var(--wpsg-space-3) 0;">
				<?php esc_html_e( 'No verified backup found within the 48-hour recency window.', 'genioussonu-site-checkup' ); ?>
			</p>

			<div class="wpsg-safety-grid">
				<div class="wpsg-safety-card">
					<span class="wpsg-safety-card-title"><?php esc_html_e( 'Backup Plugin', 'genioussonu-site-checkup' ); ?></span>
					<p class="wpsg-safety-card-desc" style="margin-bottom: var(--wpsg-space-3);">
						<?php esc_html_e( 'Open your installed backup plugin and take a snapshot now.', 'genioussonu-site-checkup' ); ?>
					</p>
					<a href="#" id="wpsg-backup-trigger-link" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" target="_blank">
						<span class="dashicons dashicons-external" aria-hidden="true"></span>
						<span id="wpsg-backup-trigger-text"><?php esc_html_e( 'Open Backup Plugin', 'genioussonu-site-checkup' ); ?></span>
					</a>
				</div>

				<div class="wpsg-safety-card">
					<span class="wpsg-safety-card-title"><?php esc_html_e( 'Host Snapshot', 'genioussonu-site-checkup' ); ?></span>
					<p class="wpsg-safety-card-desc" style="margin-bottom: var(--wpsg-space-3);">
						<?php esc_html_e( 'If you have taken a host/cPanel backup in the last 48h, confirm it.', 'genioussonu-site-checkup' ); ?>
					</p>
					<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-confirm-manual-backup-modal">
						<span class="dashicons dashicons-saved" aria-hidden="true"></span>
						<?php esc_html_e( 'Confirm Host Backup', 'genioussonu-site-checkup' ); ?>
					</button>
				</div>
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal><?php esc_html_e( 'Cancel', 'genioussonu-site-checkup' ); ?></button>
		</div>
	</div>
</div>
