<?php
/**
* Modal: Note & Schedule Reminder (Level C)
 *
 * Captures manual checklist notes, configures 15-day / 6-month reminders,
 * and includes a cryptographic password generator.
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
<div class="wpsg-modal-overlay" id="wpsg-modal-note" style="display: none;">
	<div class="wpsg-modal">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<span id="wpsg-note-modal-title"><?php esc_html_e( 'Manual Audit & Notes', 'genioussonu-security-hardening-audit' ); ?></span>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close dialog', 'genioussonu-security-hardening-audit' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<p id="wpsg-note-task-desc" style="font-size: 13px; color: var(--wpsg-text-secondary); margin: 0 0 var(--wpsg-space-4) 0;"></p>

			<!-- Integrated 24-Char Password Generator (Shown for credential rotation tasks) -->
			<div class="wpsg-notice wpsg-notice-info" id="wpsg-passgen-box" style="display: none; flex-direction: column;">
				<div>
					<strong><?php esc_html_e( 'Credential Generator:', 'genioussonu-security-hardening-audit' ); ?></strong>
					<p style="margin: 2px 0 var(--wpsg-space-2) 0; font-size: 12px;">
						<?php esc_html_e( 'Generate a secure 24-character password to update in cPanel and store in your vault:', 'genioussonu-security-hardening-audit' ); ?>
					</p>
				</div>
				<div class="wpsg-input-prefix-group">
					<input type="text" id="wpsg-generated-pass" class="wpsg-input" readonly style="font-family: var(--wpsg-font-mono); font-weight: 600;" placeholder="<?php esc_attr_e( 'Click Generate below...', 'genioussonu-security-hardening-audit' ); ?>" />
					<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary" id="wpsg-btn-gen-pass" style="border-radius: 0;">
						<span class="dashicons dashicons-update" aria-hidden="true"></span> <?php esc_html_e( 'Generate', 'genioussonu-security-hardening-audit' ); ?>
					</button>
					<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-copy-pass" style="border-radius: 0;">
						<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <?php esc_html_e( 'Copy', 'genioussonu-security-hardening-audit' ); ?>
					</button>
				</div>
			</div>

			<div class="wpsg-form-row">
				<label class="wpsg-label" for="wpsg-task-note"><?php esc_html_e( 'Audit Notes / Vault Reference:', 'genioussonu-security-hardening-audit' ); ?></label>
				<textarea id="wpsg-task-note" class="wpsg-textarea" rows="3" placeholder="<?php esc_attr_e( 'E.g., "Updated in 1Password vault by Alex on Sep 16".', 'genioussonu-security-hardening-audit' ); ?>"></textarea>
			</div>

			<div class="wpsg-form-row" style="margin-bottom: 0;">
				<label class="wpsg-label" for="wpsg-task-reminder"><?php esc_html_e( 'Schedule Next Review / Rotation:', 'genioussonu-security-hardening-audit' ); ?></label>
				<select id="wpsg-task-reminder" class="wpsg-select" style="width: 100%;">
					<option value="none"><?php esc_html_e( 'No reminder', 'genioussonu-security-hardening-audit' ); ?></option>
					<option value="15_days"><?php esc_html_e( 'Remind in 15 days (SOP standard for credentials)', 'genioussonu-security-hardening-audit' ); ?></option>
					<option value="30_days"><?php esc_html_e( 'Remind in 30 days', 'genioussonu-security-hardening-audit' ); ?></option>
					<option value="6_months"><?php esc_html_e( 'Remind in 6 months (GSC URL removal review)', 'genioussonu-security-hardening-audit' ); ?></option>
				</select>
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal><?php esc_html_e( 'Cancel', 'genioussonu-security-hardening-audit' ); ?></button>
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-save-note">
				<span class="dashicons dashicons-saved" aria-hidden="true"></span> <?php esc_html_e( 'Mark Done & Save', 'genioussonu-security-hardening-audit' ); ?>
			</button>
		</div>
	</div>
</div>
