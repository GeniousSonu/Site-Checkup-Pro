<?php
/**
 * Modal: Rename Login URL
 *
 * Configures custom login URL with conflict checks, emergency recovery instructions,
 * and mandatory typed 'CHANGE' confirmation.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpsg-modal-overlay" id="wpsg-modal-login-rename" style="display: none;">
	<div class="wpsg-modal wpsg-modal-lg">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title">
				<span class="dashicons dashicons-admin-network"></span>
				<?php esc_html_e( 'Change WordPress Login URL', 'site-checkup-pro' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<!-- WPS Hide Login Recommendation Box -->
			<div class="wpsg-notice wpsg-notice-info">
				<span class="dashicons dashicons-info"></span>
				<div>
					<strong><?php esc_html_e( 'Recommended Industry Standard:', 'site-checkup-pro' ); ?></strong>
					<p style="margin: 2px 0 8px 0;"><?php esc_html_e( 'For maximum compatibility with third-party SSO, WooCommerce, and membership plugins, we recommend using the audited WPS Hide Login plugin.', 'site-checkup-pro' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'plugin-install.php?s=wps-hide-login&tab=search&type=term' ) ); ?>" class="wpsg-btn wpsg-btn-secondary wpsg-btn-sm" target="_blank">
						<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Install WPS Hide Login', 'site-checkup-pro' ); ?>
					</a>
				</div>
			</div>

			<!-- Emergency Lockout Recovery Notice -->
			<div class="wpsg-notice wpsg-notice-critical">
				<span class="dashicons dashicons-shield"></span>
				<div>
					<strong><?php esc_html_e( 'Emergency Lockout Recovery Safeguard:', 'site-checkup-pro' ); ?></strong>
					<p style="margin: 2px 0 6px 0;"><?php esc_html_e( 'Zero query-string backdoors are permitted. If you ever lose access to your custom slug, add this recovery constant to wp-config.php via SSH or FTP:', 'site-checkup-pro' ); ?></p>
					<code>define( 'WPSG_DISABLE_LOGIN_RENAME', true );</code>
				</div>
			</div>

			<!-- 4-Part Safety Transparency Grid -->
			<div class="wpsg-safety-grid">
				<div class="wpsg-safety-card">
					<div class="wpsg-safety-card-title"><?php esc_html_e( 'What Will Change', 'site-checkup-pro' ); ?></div>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Default /wp-login.php and /wp-admin requests will be intercepted and redirected away.', 'site-checkup-pro' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<div class="wpsg-safety-card-title"><?php esc_html_e( 'Why It Matters', 'site-checkup-pro' ); ?></div>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Stops 99% of automated credential stuffing bots targeting the standard WordPress endpoint.', 'site-checkup-pro' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<div class="wpsg-safety-card-title"><?php esc_html_e( 'Backup Requirement', 'site-checkup-pro' ); ?></div>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Ensure FTP/SSH credentials are noted in your password vault prior to updating.', 'site-checkup-pro' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<div class="wpsg-safety-card-title"><?php esc_html_e( 'Reversibility', 'site-checkup-pro' ); ?></div>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Instant reversal in settings or via wp-config.php emergency constant override.', 'site-checkup-pro' ); ?></p>
				</div>
			</div>

			<div class="wpsg-form-row">
				<label class="wpsg-label" for="wpsg-input-login-slug"><?php esc_html_e( 'New Secret Login Slug:', 'site-checkup-pro' ); ?></label>
				<div class="wpsg-input-prefix-group">
					<span class="wpsg-input-prefix"><?php echo esc_html( home_url( '/' ) ); ?></span>
					<input type="text" id="wpsg-input-login-slug" class="wpsg-input" placeholder="my-agency-login" autocomplete="off" />
				</div>
				<p class="wpsg-text-secondary" style="font-size: 12px; margin: 4px 0 0 0;"><?php esc_html_e( 'Do not use predictable names such as admin, wp-admin, login, or dashboard.', 'site-checkup-pro' ); ?></p>
			</div>

			<div class="wpsg-form-row" style="margin-bottom: 0;">
				<label class="wpsg-label" for="wpsg-input-login-confirm">
					<?php esc_html_e( 'Type "CHANGE" to confirm you have noted the recovery constant:', 'site-checkup-pro' ); ?>
				</label>
				<input type="text" id="wpsg-input-login-confirm" class="wpsg-input" placeholder="CHANGE" autocomplete="off" />
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal><?php esc_html_e( 'Cancel', 'site-checkup-pro' ); ?></button>
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-confirm-login-rename" disabled>
				<span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'Activate Custom Login URL', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>
</div>
