<?php
/**
* Modal: Nginx Directive View
 *
 * Displays equivalent Nginx configuration for hosts running Nginx web servers.
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
<div class="wpsg-modal-overlay" id="wpsg-modal-nginx" style="display: none;">
	<div class="wpsg-modal">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title">
				<span class="dashicons dashicons-networking" aria-hidden="true"></span>
				<?php esc_html_e( 'Nginx Directive Equivalent', 'site-checkup-pro' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close dialog', 'site-checkup-pro' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<div class="wpsg-notice wpsg-notice-info">
				<span class="dashicons dashicons-info" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Web Server: Nginx', 'site-checkup-pro' ); ?></strong>
					<p><?php esc_html_e( 'Nginx ignores .htaccess files. To apply this rule, paste the directive below into your server block or send it to your hosting provider.', 'site-checkup-pro' ); ?></p>
				</div>
			</div>

			<div class="wpsg-form-row">
				<label class="wpsg-label" for="wpsg-nginx-code"><?php esc_html_e( 'Server Block Directive:', 'site-checkup-pro' ); ?></label>
				<div class="wpsg-diff-container">
					<pre><code id="wpsg-nginx-code"></code></pre>
				</div>
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal><?php esc_html_e( 'Close', 'site-checkup-pro' ); ?></button>
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-copy-nginx">
				<span class="dashicons dashicons-clipboard" aria-hidden="true"></span> <?php esc_html_e( 'Copy Directive', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>
</div>
