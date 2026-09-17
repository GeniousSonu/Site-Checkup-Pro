<?php
/**
 * Modal: Active Session Management
 *
 * Lists all active WordPress sessions with IP, User-Agent, login time,
 * and allows terminating individual sessions or logging out other devices.
 *
 * @package SiteCheckupPro
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wpsg-modal-overlay" id="wpsg-modal-sessions" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpsg-sessions-modal-title">
	<div class="wpsg-modal wpsg-modal-lg">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title" id="wpsg-sessions-modal-title">
				<span class="dashicons dashicons-desktop" aria-hidden="true"></span>
				<?php esc_html_e( 'Active User Sessions', 'site-checkup-pro' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
				<p style="margin: 0; color: var(--wpsg-text-secondary); font-size: 13px;">
					<?php esc_html_e( 'All devices and browser sessions currently authenticated to your WordPress account.', 'site-checkup-pro' ); ?>
				</p>
				<button type="button" class="wpsg-btn wpsg-btn-danger wpsg-btn-sm" id="wpsg-btn-destroy-other-sessions">
					<span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Log Out All Other Sessions', 'site-checkup-pro' ); ?>
				</button>
			</div>

			<div class="wpsg-table-container" style="max-height: 350px; overflow-y: auto;">
				<table class="wpsg-table" id="wpsg-sessions-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Device / IP', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Browser / Client', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Login Time', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Status', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Action', 'site-checkup-pro' ); ?></th>
						</tr>
					</thead>
					<tbody id="wpsg-sessions-tbody">
						<tr>
							<td colspan="5" style="text-align: center; padding: 20px;">
								<span class="wpsg-spinner"></span> <?php esc_html_e( 'Loading active sessions...', 'site-checkup-pro' ); ?>
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
