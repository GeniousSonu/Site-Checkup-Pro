<?php
/**
 * Modal: Session Re-Authentication Prompt
 *
 * Prompts administrator for their account password before executing
 * sensitive operations (file modifications, credential revocations).
 * Bound to current user session token and strictly single-use.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpsg-modal-overlay" id="wpsg-modal-reauth" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpsg-reauth-modal-title">
	<div class="wpsg-modal wpsg-modal-sm">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title" id="wpsg-reauth-modal-title">
				<span class="dashicons dashicons-lock" aria-hidden="true" style="color: var(--wpsg-accent-blue);"></span>
				<?php esc_html_e( 'Confirm Administrator Password', 'site-checkup-pro' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<p style="margin-top: 0; color: var(--wpsg-text-secondary); font-size: 13px;">
				<?php esc_html_e( 'This operation modifies core server configurations or active credentials. Please confirm your administrator password to proceed.', 'site-checkup-pro' ); ?>
			</p>

			<div class="wpsg-notice wpsg-notice-critical" id="wpsg-reauth-error-box" style="display: none; margin-bottom: 14px;">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<div id="wpsg-reauth-error-message"></div>
			</div>

			<div class="wpsg-form-row">
				<label for="wpsg-reauth-password" class="wpsg-form-label">
					<?php esc_html_e( 'Current Password', 'site-checkup-pro' ); ?>
				</label>
				<input
					type="password"
					id="wpsg-reauth-password"
					class="wpsg-input"
					autocomplete="current-password"
					placeholder="<?php esc_attr_e( 'Enter your password...', 'site-checkup-pro' ); ?>"
				/>
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal>
				<?php esc_html_e( 'Cancel', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-reauth-submit">
				<span class="dashicons dashicons-unlock" aria-hidden="true"></span>
				<?php esc_html_e( 'Verify & Proceed', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>
</div>
