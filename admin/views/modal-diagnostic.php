<?php
/**
 * Modal: System Diagnostic Snapshot
 *
 * Sanitized Markdown report viewer and exporter for developer troubleshooting.
 *
 * @package GeniousSonu_Site_Checkup
 * @since   1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpsg-modal-overlay" id="wpsg-modal-diagnostic" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpsg-diagnostic-modal-title">
	<div class="wpsg-modal wpsg-modal-lg" style="max-width: 800px;">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title" id="wpsg-diagnostic-modal-title" style="display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-clipboard"></span>
				<?php esc_html_e( 'System Diagnostic Snapshot', 'genioussonu-security-hardening-audit' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'genioussonu-security-hardening-audit' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<div class="wpsg-notice wpsg-notice-info" style="margin-bottom: 14px;">
				<span class="dashicons dashicons-shield" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Security Sanitized:', 'genioussonu-security-hardening-audit' ); ?></strong>
					<?php esc_html_e( 'Database passwords, salts, and secret credentials are automatically redacted. This snapshot is safe for GitHub issues and support tickets.', 'genioussonu-security-hardening-audit' ); ?>
				</div>
			</div>

			<div class="wpsg-diff-container" style="max-height: 420px; overflow-y: auto;">
				<pre style="margin: 0; padding: 12px; font-size: 12px; line-height: 1.5; font-family: monospace; white-space: pre-wrap; background: var(--wpsg-surface-subtle); color: var(--wpsg-text-primary); border-radius: 4px;"><code id="wpsg-diagnostic-content"><?php esc_html_e( 'Loading diagnostic snapshot...', 'genioussonu-security-hardening-audit' ); ?></code></pre>
			</div>
		</div>

		<div class="wpsg-modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-diagnostic-modal-copy">
				<span class="dashicons dashicons-admin-page" style="margin-right: 4px;"></span>
				<?php esc_html_e( 'Copy Markdown to Clipboard', 'genioussonu-security-hardening-audit' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal>
				<?php esc_html_e( 'Close', 'genioussonu-security-hardening-audit' ); ?>
			</button>
		</div>
	</div>
</div>
