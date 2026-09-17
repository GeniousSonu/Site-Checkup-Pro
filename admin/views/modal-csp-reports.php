<?php
/**
 * Modal: CSP Violation Reports
 *
 * Displays recorded Content-Security-Policy Report-Only violation events.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpsg-modal-overlay" id="wpsg-modal-csp-reports" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpsg-csp-modal-title">
	<div class="wpsg-modal wpsg-modal-lg">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title" id="wpsg-csp-modal-title">
				<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
				<?php esc_html_e( 'Content-Security-Policy Reports (Report-Only Mode)', 'site-checkup-pro' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<p style="margin-top: 0; color: var(--wpsg-text-secondary); font-size: 13px;">
				<?php esc_html_e( 'The policy is currently running in Report-Only mode. No resources are blocked. Violations triggered by scripts, styles, or plugins are captured below for review before enforcement.', 'site-checkup-pro' ); ?>
			</p>

			<div class="wpsg-table-container" style="max-height: 350px; overflow-y: auto;">
				<table class="wpsg-table" id="wpsg-csp-table">
					<thead>
						<tr>
							<th style="width: 140px;"><?php esc_html_e( 'Time', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Directive', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Blocked URI', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Document URI', 'site-checkup-pro' ); ?></th>
						</tr>
					</thead>
					<tbody id="wpsg-csp-tbody">
						<tr>
							<td colspan="4" style="text-align: center; padding: 20px;">
								<span class="wpsg-spinner"></span> <?php esc_html_e( 'Loading CSP violation records...', 'site-checkup-pro' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal>
				<?php esc_html_e( 'Close', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>
</div>
