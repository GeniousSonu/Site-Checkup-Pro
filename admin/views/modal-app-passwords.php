<?php
/**
* Modal: Application Passwords Governance
 *
 * Surfaces all active application passwords and safely gates revocations
 * behind explicit confirmation and re-authentication.
 *
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
<div class="wpsg-modal-overlay" id="wpsg-modal-app-passwords" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="wpsg-app-passwords-modal-title">
	<div class="wpsg-modal wpsg-modal-lg">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title" id="wpsg-app-passwords-modal-title">
				<span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
				<?php esc_html_e( 'Application Passwords Governance', 'site-checkup-pro' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<div class="wpsg-notice wpsg-notice-info" style="margin-bottom: 14px;">
				<span class="dashicons dashicons-info"></span>
				<div>
					<strong><?php esc_html_e( 'Application Credentials Review:', 'site-checkup-pro' ); ?></strong>
					<p style="margin: 2px 0 0 0;"><?php esc_html_e( 'Application passwords allow external systems, scripts, and mobile apps to authenticate via the WordPress REST API without exposing your master account password. Stale or unused credentials should be revoked immediately.', 'site-checkup-pro' ); ?></p>
				</div>
			</div>

			<div class="wpsg-table-container" style="max-height: 350px; overflow-y: auto;">
				<table class="wpsg-table" id="wpsg-app-passwords-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Application / Label', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Owner User', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Created', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Last Used', 'site-checkup-pro' ); ?></th>
							<th><?php esc_html_e( 'Action', 'site-checkup-pro' ); ?></th>
						</tr>
					</thead>
					<tbody id="wpsg-app-passwords-tbody">
						<tr>
							<td colspan="5" style="text-align: center; padding: 20px;">
								<span class="wpsg-spinner"></span> <?php esc_html_e( 'Loading application passwords...', 'site-checkup-pro' ); ?>
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
