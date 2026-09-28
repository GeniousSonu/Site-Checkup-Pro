<?php
/**
* Modal: Quarantine & Delete Plugin
 *
 * Confirms plugin deletion after creating a full zip archive in wpsg-backups/plugins/.
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
<div class="wpsg-modal-overlay" id="wpsg-modal-delete-plugin" style="display: none;">
	<div class="wpsg-modal">
		<div class="wpsg-modal-header">
			<h3 class="wpsg-modal-title">
				<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/icon-backup.svg' ); ?>" width="20" height="20" alt="" />
				<?php esc_html_e( 'Confirm Safe Plugin Removal', 'genioussonu-site-checkup' ); ?>
			</h3>
			<button type="button" class="wpsg-modal-close" data-close-modal aria-label="<?php esc_attr_e( 'Close', 'genioussonu-site-checkup' ); ?>">&times;</button>
		</div>

		<div class="wpsg-modal-body">
			<p style="margin: 0 0 16px 0; color: var(--wpsg-text-secondary); font-size: 13px;">
				<?php esc_html_e( 'You are about to remove an obsolete development, migration, or unused plugin from this installation.', 'genioussonu-site-checkup' ); ?>
			</p>

			<div style="background: var(--wpsg-surface-subtle); border: 1px solid var(--wpsg-border); border-radius: var(--wpsg-radius-sm); padding: 12px 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center;">
				<strong id="wpsg-delete-plugin-name" style="font-size: 13px; font-weight: 600; color: var(--wpsg-text-primary);">Plugin Name</strong>
				<code id="wpsg-delete-plugin-slug" style="font-size: 12px; color: var(--wpsg-text-secondary); background: var(--wpsg-surface); border: 1px solid var(--wpsg-border); padding: 2px 6px; border-radius: 4px;">plugin-slug</code>
			</div>

			<!-- 4-Part Safety Transparency Grid -->
			<div class="wpsg-safety-grid">
				<div class="wpsg-safety-card">
					<div class="wpsg-safety-card-title"><?php esc_html_e( 'What Will Change', 'genioussonu-site-checkup' ); ?></div>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Plugin directory removed from wp-content/plugins/.', 'genioussonu-site-checkup' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<div class="wpsg-safety-card-title"><?php esc_html_e( 'Why It Matters', 'genioussonu-site-checkup' ); ?></div>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Eliminates attack surface from unmaintained code.', 'genioussonu-site-checkup' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<div class="wpsg-safety-card-title"><?php esc_html_e( 'Backup Protection', 'genioussonu-site-checkup' ); ?></div>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Full ZIP archive automatically saved before deletion.', 'genioussonu-site-checkup' ); ?></p>
				</div>
				<div class="wpsg-safety-card">
					<div class="wpsg-safety-card-title"><?php esc_html_e( 'Reversibility', 'genioussonu-site-checkup' ); ?></div>
					<p class="wpsg-safety-card-desc"><?php esc_html_e( 'Full 1-click restoration from archive available anytime.', 'genioussonu-site-checkup' ); ?></p>
				</div>
			</div>

			<div class="wpsg-notice wpsg-notice-info">
				<span class="dashicons dashicons-archive"></span>
				<div>
					<strong><?php esc_html_e( 'Automated ZIP Protection Active:', 'genioussonu-site-checkup' ); ?></strong>
					<p style="margin: 2px 0 0 0;"><?php esc_html_e( 'Before deletion, the entire plugin directory will be archived into a compressed ZIP file under wp-content/uploads/wpsg-backups/plugins/. You can undo and restore the plugin anytime.', 'genioussonu-site-checkup' ); ?></p>
				</div>
			</div>
		</div>

		<div class="wpsg-modal-footer">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" data-close-modal><?php esc_html_e( 'Cancel', 'genioussonu-site-checkup' ); ?></button>
			<button type="button" class="wpsg-btn wpsg-btn-danger" id="wpsg-btn-confirm-delete-plugin">
				<span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Archive to ZIP & Delete Plugin', 'genioussonu-site-checkup' ); ?>
			</button>
		</div>
	</div>
</div>
