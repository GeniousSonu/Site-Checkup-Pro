<?php
/**
 * Modal: Generic Destructive / Confirmation Prompt
 *
 * Provides a standardized, accessible confirmation modal dialog across all
 * destructive operations (session termination, login renaming, baseline updates, app password revocations)
 * replacing native browser confirm() dialogs with a unified design-system component.
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
<div class="wpsg-modal-overlay" id="wpsg-modal-confirm" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpsg-confirm-title">
	<div class="wpsg-modal wpsg-modal-sm">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title" id="wpsg-confirm-title" style="display: flex; align-items: center; gap: 8px;">
				<span class="dashicons dashicons-warning" id="wpsg-confirm-icon" aria-hidden="true" style="color: var(--wpsg-warning);"></span>
				<span id="wpsg-confirm-title-text"><?php esc_html_e( 'Confirm Action', 'site-checkup-pro' ); ?></span>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close dialog', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<p id="wpsg-confirm-message" style="margin-top: 0; color: var(--wpsg-text-secondary); font-size: 13px; line-height: 1.5; white-space: pre-line;">
				<?php esc_html_e( 'Are you sure you want to proceed with this action?', 'site-checkup-pro' ); ?>
			</p>
			<div id="wpsg-confirm-notice" class="wpsg-notice wpsg-notice-warning" style="display: none; margin-top: 12px;">
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<div id="wpsg-confirm-notice-text"></div>
			</div>
		</div>

		<div class="wpsg-modal-footer" style="display: flex; justify-content: flex-end; gap: 8px;">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-confirm-cancel" data-close-modal>
				<?php esc_html_e( 'Cancel', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-danger" id="wpsg-btn-confirm-submit">
				<?php esc_html_e( 'Confirm', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>
</div>
