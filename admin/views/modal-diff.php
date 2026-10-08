<?php
/**
* Modal: Pre-Execution Diff Preview
 *
 * Transparent 4-part safety breakdown with syntax-highlighted diff.
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
<div class="wpsg-modal-overlay" id="wpsg-modal-diff" style="display: none;">
	<div class="wpsg-modal wpsg-modal-lg">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title">
				<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/icon-diff.svg' ); ?>" width="20" height="20" alt="" />
				<?php esc_html_e( 'Pre-Execution Diff Preview', 'genioussonu-security-hardening-audit' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'genioussonu-security-hardening-audit' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<!-- 4-Part Safety Transparency Grid -->
			<div class="wpsg-safety-grid">
				<div class="wpsg-safety-card">
					<span class="wpsg-safety-card-title"><?php esc_html_e( '1. Target Configuration', 'genioussonu-security-hardening-audit' ); ?></span>
					<p class="wpsg-safety-card-desc" id="wpsg-diff-file-target"><?php esc_html_e( 'Target: .htaccess', 'genioussonu-security-hardening-audit' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<span class="wpsg-safety-card-title"><?php esc_html_e( '2. Modification Method', 'genioussonu-security-hardening-audit' ); ?></span>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Standard WordPress insert_with_markers delimiters', 'genioussonu-security-hardening-audit' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<span class="wpsg-safety-card-title"><?php esc_html_e( '3. Safety Net', 'genioussonu-security-hardening-audit' ); ?></span>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Pre-write timestamped backup + loopback self-test', 'genioussonu-security-hardening-audit' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<span class="wpsg-safety-card-title"><?php esc_html_e( '4. Reversibility', 'genioussonu-security-hardening-audit' ); ?></span>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( '100% reversible via One-Click Undo or .bak restore', 'genioussonu-security-hardening-audit' ); ?></p>
				</div>
			</div>

			<label class="wpsg-label"><?php esc_html_e( 'Proposed Configuration Block:', 'genioussonu-security-hardening-audit' ); ?></label>
			<div class="wpsg-diff-container">
				<pre><code id="wpsg-diff-content"></code></pre>
			</div>

			<div class="wpsg-notice wpsg-notice-info">
				<span class="dashicons dashicons-shield" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Automatic Rollback Active:', 'genioussonu-security-hardening-audit' ); ?></strong>
					<?php esc_html_e( 'If this rule triggers a 5xx server error, the plugin will immediately roll back to the safety backup.', 'genioussonu-security-hardening-audit' ); ?>
				</div>
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal><?php esc_html_e( 'Cancel', 'genioussonu-security-hardening-audit' ); ?></button>
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-diff-confirm-run">
				<span class="dashicons dashicons-saved" aria-hidden="true"></span> <?php esc_html_e( 'Apply Hardening Rule', 'genioussonu-security-hardening-audit' ); ?>
			</button>
		</div>
	</div>
</div>
