<?php
/**
 * Settings & Incident Contact Modal
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
?>

<div class="wpsg-modal-overlay" id="wpsg-modal-settings" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpsg-settings-modal-title">
	<div class="wpsg-modal" style="max-width: 580px;">
		<div class="wpsg-modal-header">
			<div class="wpsg-modal-title" style="display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-admin-generic" style="color: var(--wpsg-brand); font-size: 20px;"></span>
				<h3 id="wpsg-settings-modal-title" style="margin: 0; font-size: 15px;"><?php esc_html_e( 'Site Checkup Pro Settings', 'site-checkup-pro' ); ?></h3>
			</div>
			<button type="button" class="wpsg-modal-close" data-close-modal id="wpsg-btn-close-settings" aria-label="<?php esc_attr_e( 'Close settings dialog', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<!-- Vulnerability API Settings -->
			<div class="wpsg-form-section" style="margin-bottom: 20px;">
				<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: var(--wpsg-text-primary);">
					<?php esc_html_e( 'Patchstack Vulnerability Intelligence API', 'site-checkup-pro' ); ?>
				</h4>
				<p style="margin: 0 0 10px 0; font-size: 12px; color: var(--wpsg-text-secondary);">
					<?php esc_html_e( 'Enter your Patchstack API key for continuous live cross-referencing of installed plugins against active security advisories. An optional free-tier key can be obtained from Patchstack.', 'site-checkup-pro' ); ?>
				</p>
				<div class="wpsg-input-group">
					<label for="wpsg-setting-patchstack-key" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
						<?php esc_html_e( 'Patchstack API Token', 'site-checkup-pro' ); ?>
					</label>
					<input type="password" id="wpsg-setting-patchstack-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste API key or leave blank for default check', 'site-checkup-pro' ); ?>" style="width: 100%;" autocomplete="off" />
					<p id="wpsg-patchstack-masked-status" style="margin: 4px 0 0 0; font-size: 11px; color: var(--wpsg-text-muted);"></p>
				</div>
			</div>

			<!-- Emergency Incident Response Contact -->
			<div class="wpsg-form-section" style="margin-bottom: 20px; padding-top: 16px; border-top: 1px solid var(--wpsg-border);">
				<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: var(--wpsg-text-primary);">
					<?php esc_html_e( 'Incident Response Emergency Contacts', 'site-checkup-pro' ); ?>
				</h4>
				<p style="margin: 0 0 12px 0; font-size: 12px; color: var(--wpsg-text-secondary);">
					<?php esc_html_e( 'Designate escalation contacts and protocols in the event of an after-hours breach or emergency lockout. Surfaced on client audit reports.', 'site-checkup-pro' ); ?>
				</p>

				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
					<div>
						<label for="wpsg-setting-incident-name" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
							<?php esc_html_e( 'Contact Person / Role', 'site-checkup-pro' ); ?>
						</label>
						<input type="text" id="wpsg-setting-incident-name" class="wpsg-input" placeholder="e.g. Lead SecOps Engineer" style="width: 100%;" />
					</div>
					<div>
						<label for="wpsg-setting-incident-email" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
							<?php esc_html_e( 'Emergency Email', 'site-checkup-pro' ); ?>
						</label>
						<input type="email" id="wpsg-setting-incident-email" class="wpsg-input" placeholder="e.g. security@clientsite.com" style="width: 100%;" />
					</div>
				</div>

				<div style="margin-bottom: 12px;">
					<label for="wpsg-setting-incident-phone" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
						<?php esc_html_e( 'Emergency Phone / Pager', 'site-checkup-pro' ); ?>
					</label>
					<input type="text" id="wpsg-setting-incident-phone" class="wpsg-input" placeholder="e.g. +1 (555) 019-2831" style="width: 100%;" />
				</div>

				<div>
					<label for="wpsg-setting-incident-notes" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
						<?php esc_html_e( 'Incident Protocol & Vault Reference', 'site-checkup-pro' ); ?>
					</label>
					<textarea id="wpsg-setting-incident-notes" class="wpsg-textarea" rows="2" placeholder="<?php esc_attr_e( 'e.g. Contact 24/7 hosting desk, access 1Password Emergency Vault for root credentials.', 'site-checkup-pro' ); ?>" style="width: 100%;"></textarea>
				</div>
			</div>

			<!-- Agency Customization -->
			<div class="wpsg-form-section" style="padding-top: 16px; border-top: 1px solid var(--wpsg-border);">
				<div style="margin-bottom: 8px;">
					<label for="wpsg-setting-agency-name" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
						<?php esc_html_e( 'Agency / Preparer Name (Client Reports)', 'site-checkup-pro' ); ?>
					</label>
					<input type="text" id="wpsg-setting-agency-name" class="wpsg-input" placeholder="e.g. Acme Security Services" style="width: 100%;" />
				</div>
			</div>
		</div>

		<div class="wpsg-modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
			<span id="wpsg-settings-save-status" style="font-size: 12px; color: var(--wpsg-text-secondary);"></span>
			<div style="display: flex; gap: 8px;">
				<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-cancel-settings">
					<?php esc_html_e( 'Cancel', 'site-checkup-pro' ); ?>
				</button>
				<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-save-settings">
					<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save Settings', 'site-checkup-pro' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
