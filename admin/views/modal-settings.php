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
			<!-- Multi-Source Vulnerability Intelligence Settings -->
			<div class="wpsg-form-section" style="margin-bottom: 20px;">
				<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: var(--wpsg-text-primary);">
					<?php esc_html_e( 'Vulnerability Intelligence Sources', 'site-checkup-pro' ); ?>
				</h4>
				<p style="margin: 0 0 14px 0; font-size: 12px; color: var(--wpsg-text-secondary);">
					<?php esc_html_e( 'Configure opt-in external intelligence sources to cross-reference installed plugins and themes against active CVE advisories. All API keys and tokens are encrypted at rest using HKDF-derived keys and never logged. Outbound queries require explicit opt-in per WordPress.org Guideline 7.', 'site-checkup-pro' ); ?>
				</p>

				<!-- GitHub Security Advisories -->
				<div style="margin-bottom: 14px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
					<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; font-weight: 500; cursor: pointer; margin-bottom: 8px;">
						<input type="checkbox" id="wpsg-setting-ghsa-optin" style="margin-top: 2px;" />
						<span>
							<strong><?php esc_html_e( 'GitHub Security Advisories (GHSA)', 'site-checkup-pro' ); ?></strong><br />
							<span style="font-weight: 400; color: var(--wpsg-text-secondary); font-size: 11px;">
								<?php esc_html_e( 'Query GitHub GraphQL/REST Advisories API (api.github.com) for WordPress package advisories and version ranges.', 'site-checkup-pro' ); ?>
							</span>
						</span>
					</label>
					<div class="wpsg-input-group" style="margin-top: 8px; padding-left: 24px;">
						<label for="wpsg-setting-ghsa-key" style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 4px; color: var(--wpsg-text-secondary);">
							<?php esc_html_e( 'GitHub Personal Access Token (Optional, for higher rate limits)', 'site-checkup-pro' ); ?>
						</label>
						<input type="password" id="wpsg-setting-ghsa-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste GitHub token (stored encrypted with HKDF)', 'site-checkup-pro' ); ?>" style="width: 100%; font-size: 12px;" autocomplete="off" />
						<p id="wpsg-ghsa-masked-status" style="margin: 4px 0 0 0; font-size: 11px; color: var(--wpsg-text-muted);"></p>
					</div>
				</div>

				<!-- OSV (Open Source Vulnerabilities) -->
				<div style="margin-bottom: 14px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
					<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; font-weight: 500; cursor: pointer; margin-bottom: 8px;">
						<input type="checkbox" id="wpsg-setting-osv-optin" style="margin-top: 2px;" />
						<span>
							<strong><?php esc_html_e( 'OSV.dev (Open Source Vulnerabilities)', 'site-checkup-pro' ); ?></strong><br />
							<span style="font-weight: 400; color: var(--wpsg-text-secondary); font-size: 11px;">
								<?php esc_html_e( 'Query Google OSV database (api.osv.dev) for precise semantic version range correlation.', 'site-checkup-pro' ); ?>
							</span>
						</span>
					</label>
					<div class="wpsg-input-group" style="margin-top: 8px; padding-left: 24px;">
						<label for="wpsg-setting-osv-key" style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 4px; color: var(--wpsg-text-secondary);">
							<?php esc_html_e( 'OSV API Key / Token (Optional, stored encrypted)', 'site-checkup-pro' ); ?>
						</label>
						<input type="password" id="wpsg-setting-osv-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste OSV API key or leave blank for public queries', 'site-checkup-pro' ); ?>" style="width: 100%; font-size: 12px;" autocomplete="off" />
						<p id="wpsg-osv-masked-status" style="margin: 4px 0 0 0; font-size: 11px; color: var(--wpsg-text-muted);"></p>
					</div>
				</div>

				<!-- NVD (National Vulnerability Database) -->
				<div style="margin-bottom: 14px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
					<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; font-weight: 500; cursor: pointer; margin-bottom: 8px;">
						<input type="checkbox" id="wpsg-setting-nvd-optin" style="margin-top: 2px;" />
						<span>
							<strong><?php esc_html_e( 'NVD (National Vulnerability Database - NIST)', 'site-checkup-pro' ); ?></strong><br />
							<span style="font-weight: 400; color: var(--wpsg-text-secondary); font-size: 11px;">
								<?php esc_html_e( 'Query NIST NVD 2.0 API (services.nvd.nist.gov) for authoritative CVE records and CVSS severity metrics.', 'site-checkup-pro' ); ?>
							</span>
						</span>
					</label>
					<div class="wpsg-input-group" style="margin-top: 8px; padding-left: 24px;">
						<label for="wpsg-setting-nvd-key" style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 4px; color: var(--wpsg-text-secondary);">
							<?php esc_html_e( 'NVD API Key (Recommended for higher rate limits)', 'site-checkup-pro' ); ?>
						</label>
						<input type="password" id="wpsg-setting-nvd-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste NIST NVD 2.0 API key (stored encrypted with HKDF)', 'site-checkup-pro' ); ?>" style="width: 100%; font-size: 12px;" autocomplete="off" />
						<p id="wpsg-nvd-masked-status" style="margin: 4px 0 0 0; font-size: 11px; color: var(--wpsg-text-muted);"></p>
					</div>
				</div>

				<!-- CISA KEV (Known Exploited Vulnerabilities) -->
				<div style="margin-bottom: 14px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
					<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; font-weight: 500; cursor: pointer; margin-bottom: 8px;">
						<input type="checkbox" id="wpsg-setting-cisa-kev-optin" style="margin-top: 2px;" />
						<span>
							<strong><?php esc_html_e( 'CISA KEV Catalog (Actively Exploited in the Wild)', 'site-checkup-pro' ); ?></strong><br />
							<span style="font-weight: 400; color: var(--wpsg-text-secondary); font-size: 11px;">
								<?php esc_html_e( 'Cross-reference detected CVEs against the CISA Known Exploited Vulnerabilities catalog (cisa.gov) to highlight critical weaponized threats.', 'site-checkup-pro' ); ?>
							</span>
						</span>
					</label>
					<div class="wpsg-input-group" style="margin-top: 8px; padding-left: 24px;">
						<label for="wpsg-setting-cisa-kev-key" style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 4px; color: var(--wpsg-text-secondary);">
							<?php esc_html_e( 'CISA Access / Proxy Token (Optional, stored encrypted)', 'site-checkup-pro' ); ?>
						</label>
						<input type="password" id="wpsg-setting-cisa-kev-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste token or leave blank for default feed', 'site-checkup-pro' ); ?>" style="width: 100%; font-size: 12px;" autocomplete="off" />
						<p id="wpsg-cisa-kev-masked-status" style="margin: 4px 0 0 0; font-size: 11px; color: var(--wpsg-text-muted);"></p>
					</div>
				</div>

				<!-- WPScan Vulnerability Database -->
				<div style="margin-bottom: 14px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
					<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; font-weight: 500; cursor: pointer; margin-bottom: 8px;">
						<input type="checkbox" id="wpsg-setting-wpscan-optin" style="margin-top: 2px;" />
						<span>
							<strong><?php esc_html_e( 'WPScan Vulnerability Database API', 'site-checkup-pro' ); ?></strong><br />
							<span style="font-weight: 400; color: var(--wpsg-text-secondary); font-size: 11px;">
								<?php esc_html_e( 'Query Automattic WPScan database (wpscan.com/api/v3). Note: Per WPScan terms, vulnerability data from this source is strictly non-cached and never permanently stored.', 'site-checkup-pro' ); ?>
							</span>
						</span>
					</label>
					<div class="wpsg-input-group" style="margin-top: 8px; padding-left: 24px;">
						<label for="wpsg-setting-wpscan-token" style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 4px; color: var(--wpsg-text-secondary);">
							<?php esc_html_e( 'WPScan API Token', 'site-checkup-pro' ); ?>
						</label>
						<input type="password" id="wpsg-setting-wpscan-token" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste WPScan user API token (stored encrypted with HKDF)', 'site-checkup-pro' ); ?>" style="width: 100%; font-size: 12px;" autocomplete="off" />
						<p id="wpsg-wpscan-masked-status" style="margin: 4px 0 0 0; font-size: 11px; color: var(--wpsg-text-muted);"></p>
					</div>
				</div>

				<!-- Patchstack API -->
				<div style="margin-bottom: 0; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
					<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; font-weight: 500; cursor: pointer; margin-bottom: 8px;">
						<input type="checkbox" id="wpsg-setting-patchstack-optin" style="margin-top: 2px;" />
						<span>
							<strong><?php esc_html_e( 'Patchstack Vulnerability Intelligence API', 'site-checkup-pro' ); ?></strong><br />
							<span style="font-weight: 400; color: var(--wpsg-text-secondary); font-size: 11px;">
								<?php esc_html_e( 'Cross-reference plugins and themes against Patchstack database (patchstack.com/database/api/v2).', 'site-checkup-pro' ); ?>
							</span>
						</span>
					</label>
					<div class="wpsg-input-group" style="margin-top: 8px; padding-left: 24px;">
						<label for="wpsg-setting-patchstack-key" style="display: block; font-size: 11px; font-weight: 500; margin-bottom: 4px; color: var(--wpsg-text-secondary);">
							<?php esc_html_e( 'Patchstack API Token', 'site-checkup-pro' ); ?>
						</label>
						<input type="password" id="wpsg-setting-patchstack-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste API key or leave blank for default check', 'site-checkup-pro' ); ?>" style="width: 100%; font-size: 12px;" autocomplete="off" />
						<p id="wpsg-patchstack-masked-status" style="margin: 4px 0 0 0; font-size: 11px; color: var(--wpsg-text-muted);"></p>
					</div>
				</div>
			</div>

			<!-- Hosting Control Panel Bridge (Tier 1 Directives) -->
			<div class="wpsg-form-section" style="margin-bottom: 20px; padding-top: 16px; border-top: 1px solid var(--wpsg-border);">
				<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: var(--wpsg-text-primary);">
					<?php esc_html_e( 'Hosting Control Panel Bridge (Nginx Tier 1)', 'site-checkup-pro' ); ?>
				</h4>
				<p style="margin: 0 0 10px 0; font-size: 12px; color: var(--wpsg-text-secondary);">
					<?php esc_html_e( 'For sites hosted on Nginx, connect your control panel API to apply security directives automatically. Credentials are encrypted at rest with HKDF + AES-256-GCM / libsodium.', 'site-checkup-pro' ); ?>
				</p>
				<div id="wpsg-panel-detection-info" style="margin-bottom: 10px; font-size: 12px; color: var(--wpsg-text-secondary); padding: 8px 12px; background: var(--wpsg-bg); border-radius: 6px; border: 1px solid var(--wpsg-border); display: none;"></div>
				<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px;">
					<div>
						<label for="wpsg-setting-panel-type" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
							<?php esc_html_e( 'Control Panel Type', 'site-checkup-pro' ); ?>
						</label>
						<select id="wpsg-setting-panel-type" class="wpsg-select" style="width: 100%;">
							<option value=""><?php esc_html_e( 'None / Not Applicable', 'site-checkup-pro' ); ?></option>
							<option value="cpanel"><?php esc_html_e( 'cPanel (UAPI)', 'site-checkup-pro' ); ?></option>
							<option value="plesk"><?php esc_html_e( 'Plesk (REST API)', 'site-checkup-pro' ); ?></option>
							<option value="cloudpanel"><?php esc_html_e( 'CloudPanel (v2 API)', 'site-checkup-pro' ); ?></option>
							<option value="runcloud"><?php esc_html_e( 'RunCloud (API)', 'site-checkup-pro' ); ?></option>
							<option value="cyberpanel"><?php esc_html_e( 'CyberPanel (REST API)', 'site-checkup-pro' ); ?></option>
						</select>
					</div>
					<div>
						<label for="wpsg-setting-panel-url" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
							<?php esc_html_e( 'Panel URL / Port', 'site-checkup-pro' ); ?>
						</label>
						<input type="url" id="wpsg-setting-panel-url" class="wpsg-input" placeholder="https://cp.server.com:8443" style="width: 100%;" />
					</div>
				</div>
				<div class="wpsg-input-group" style="margin-bottom: 10px;">
					<label for="wpsg-setting-panel-token" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
						<?php esc_html_e( 'API Token / Secret Key', 'site-checkup-pro' ); ?>
					</label>
					<input type="password" id="wpsg-setting-panel-token" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste panel API token (stored encrypted)', 'site-checkup-pro' ); ?>" style="width: 100%;" autocomplete="off" />
					<p id="wpsg-panel-masked-status" style="margin: 4px 0 0 0; font-size: 11px; color: var(--wpsg-text-muted);"></p>
				</div>
				<div style="padding: 10px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
					<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; font-weight: 500; cursor: pointer;">
						<input type="checkbox" id="wpsg-setting-panel-optin" style="margin-top: 2px;" />
						<span>
							<strong><?php esc_html_e( 'Authorize API Directive Application (Explicit Consent)', 'site-checkup-pro' ); ?></strong><br />
							<span style="font-weight: 400; color: var(--wpsg-text-secondary); font-size: 11px;">
								<?php esc_html_e( 'Per WordPress.org Guideline 7, authorizing this panel bridge allows Site Checkup Pro to transmit authenticated Nginx directive configurations to the specified control panel API endpoint. Token is never logged or exported.', 'site-checkup-pro' ); ?>
							</span>
						</span>
					</label>
				</div>
			</div>

			<!-- Webhook Notification Settings -->
			<div class="wpsg-form-section" style="margin-bottom: 20px; padding-top: 16px; border-top: 1px solid var(--wpsg-border);">
				<h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: var(--wpsg-text-primary);">
					<?php esc_html_e( 'Security Alert Webhooks (Optional)', 'site-checkup-pro' ); ?>
				</h4>
				<p style="margin: 0 0 10px 0; font-size: 12px; color: var(--wpsg-text-secondary);">
					<?php esc_html_e( 'Forward real-time security alerts (rogue admin creation, login spikes, PHP uploads execution) to an external webhook endpoint (e.g. Slack, Discord, OpsGenie).', 'site-checkup-pro' ); ?>
				</p>
				<div class="wpsg-input-group" style="margin-bottom: 8px;">
					<label for="wpsg-setting-webhook-url" style="display: block; font-size: 12px; font-weight: 500; margin-bottom: 4px;">
						<?php esc_html_e( 'Webhook Endpoint URL', 'site-checkup-pro' ); ?>
					</label>
					<input type="url" id="wpsg-setting-webhook-url" class="wpsg-input" placeholder="https://hooks.slack.com/services/..." style="width: 100%;" />
				</div>
				<div style="padding: 10px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
					<label style="display: flex; align-items: flex-start; gap: 8px; font-size: 12px; font-weight: 500; cursor: pointer;">
						<input type="checkbox" id="wpsg-setting-webhook-optin" style="margin-top: 2px;" />
						<span>
							<strong><?php esc_html_e( 'Allow outbound alert dispatch to this webhook (Explicit Consent)', 'site-checkup-pro' ); ?></strong><br />
							<span style="font-weight: 400; color: var(--wpsg-text-secondary); font-size: 11px;">
								<?php esc_html_e( 'Per WordPress.org Guideline 7, outbound notifications require explicit consent. When enabled, alerts send event summaries, timestamp, and site URL to the specified endpoint. Outbound requests are strictly filtered through SSRF guards to block internal/loopback addresses.', 'site-checkup-pro' ); ?>
							</span>
						</span>
					</label>
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
