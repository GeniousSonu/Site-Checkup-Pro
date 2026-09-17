<?php
/**
 * Main Check-up Dashboard View — v2.0
 *
 * Professional sidebar + content panel layout modeled after top WordPress plugins
 * (WooCommerce, Yoast SEO, WP Rocket, Solid Security).
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

$registry      = WPSG_Task_Registry::get_instance();
$sections      = WPSG_Task_Registry::$sections;
$server_type   = WPSG_Htaccess_Manager::get_server_type();
$has_htaccess  = WPSG_Htaccess_Manager::supports_htaccess();
$backup_status = WPSG_Backup_Guard::get_backup_status();
$login_slug    = WPSG_Login_Renamer::get_login_slug();
?>

<div class="wrap wpsg-wrap" id="wpsg-app">

	<!-- Top Product Header -->
	<header class="wpsg-page-header">
		<div class="wpsg-header-title-area">
			<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/site-checkup-pro-icon.svg' ); ?>" alt="Site Checkup Pro" width="38" height="38" class="wpsg-brand-icon-img" />
			<div class="wpsg-header-titles">
				<h1>
					<?php esc_html_e( 'Site Checkup Pro', 'site-checkup-pro' ); ?>
					<span class="wpsg-version-tag">v<?php echo esc_html( WPSG_VERSION ); ?></span>
				</h1>
				<p><?php esc_html_e( 'SOP Security Hardening & Audit Orchestrator', 'site-checkup-pro' ); ?></p>
			</div>
		</div>

		<div class="wpsg-header-actions">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-confirm-backup" title="<?php esc_attr_e( 'Confirm that a host/cPanel backup was verified within 48h', 'site-checkup-pro' ); ?>">
				<span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Confirm Backup', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-update-baseline" title="<?php esc_attr_e( 'Update trusted administrator and database baseline', 'site-checkup-pro' ); ?>">
				<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Trust Baseline', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-open-settings" title="<?php esc_attr_e( 'Configure API keys and incident response contacts', 'site-checkup-pro' ); ?>">
				<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-batch-run">
				<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run All Safe Tasks', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</header>

	<!-- Live Batch Execution Progress Strip (Hidden by default) -->
	<div class="wpsg-batch-banner" id="wpsg-batch-banner" style="display: none;">
		<div class="wpsg-batch-info">
			<span class="wpsg-spinner" aria-hidden="true"></span>
			<span id="wpsg-batch-text"><?php esc_html_e( 'Processing safe verification tasks...', 'site-checkup-pro' ); ?></span>
			<span class="wpsg-batch-count" id="wpsg-batch-count">0 / 0</span>
		</div>
		<div class="wpsg-batch-progress-bar">
			<div class="wpsg-batch-progress-fill" id="wpsg-batch-progress" style="width: 0%;"></div>
		</div>
		<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-subtle" id="wpsg-batch-cancel"><?php esc_html_e( 'Stop', 'site-checkup-pro' ); ?></button>
	</div>

	<!-- In-Plugin Milestone Review Prompt -->
	<?php
	$wpsg_review_dismissed = get_option( 'wpsg_review_prompt_dismissed', false );
	$wpsg_tasks_done       = (int) get_option( 'wpsg_completed_tasks_count', 0 );
	if ( ! $wpsg_review_dismissed && $wpsg_tasks_done >= 3 ) :
	?>
	<div class="wpsg-review-prompt" id="wpsg-review-prompt" style="margin: 0 0 20px 0; padding: 16px 20px; background: var(--wpsg-surface); border: 1px solid var(--wpsg-brand); border-left: 4px solid var(--wpsg-brand); border-radius: var(--wpsg-radius-md); display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;">
		<div style="display: flex; align-items: center; gap: 14px;">
			<span class="dashicons dashicons-star-filled" style="font-size: 26px; width: 26px; height: 26px; color: #f59e0b;" aria-hidden="true"></span>
			<div>
				<h3 style="margin: 0 0 4px; font-size: 14px; font-weight: 600; color: var(--wpsg-text-primary);">
					<?php esc_html_e( 'Loving Site Checkup Pro? Help us grow with a 5-star review!', 'site-checkup-pro' ); ?>
				</h3>
				<p style="margin: 0; font-size: 13px; color: var(--wpsg-text-secondary);">
					<?php
					printf(
						/* translators: %d: number of completed security tasks */
						esc_html__( 'You have successfully run %d security hardening and audit checks. Leaving a quick review helps us immensely!', 'site-checkup-pro' ),
						$wpsg_tasks_done
					);
					?>
				</p>
			</div>
		</div>
		<div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
			<a href="https://wordpress.org/support/plugin/site-checkup-pro/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-review-now">
				<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Leave a 5-Star Review', 'site-checkup-pro' ); ?>
			</a>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-review-already">
				<?php esc_html_e( 'I Already Did', 'site-checkup-pro' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-subtle" id="wpsg-btn-review-dismiss" title="<?php esc_attr_e( 'Dismiss permanently', 'site-checkup-pro' ); ?>">
				<?php esc_html_e( 'Maybe Later', 'site-checkup-pro' ); ?>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<!-- Main App Layout: Sidebar Navigation + Content Area -->
	<div class="wpsg-app-layout">

		<!-- Left Sidebar Navigation -->
		<aside class="wpsg-sidebar" aria-label="<?php esc_attr_e( 'Site Checkup Pro Navigation', 'site-checkup-pro' ); ?>">
			
			<div class="wpsg-sidebar-group">
				<div class="wpsg-sidebar-heading"><?php esc_html_e( 'Dashboard', 'site-checkup-pro' ); ?></div>
				<button type="button" class="wpsg-sidebar-item wpsg-tab active" data-tab="overview">
					<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Overview', 'site-checkup-pro' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-overview-pct">0%</span>
				</button>
			</div>

			<div class="wpsg-sidebar-group">
				<div class="wpsg-sidebar-heading"><?php esc_html_e( 'Checkup Tasks', 'site-checkup-pro' ); ?></div>
				
				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="security_update">
					<span class="dashicons dashicons-shield" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Security Update', 'site-checkup-pro' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-sec-update">5</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="general_check">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Site Audit', 'site-checkup-pro' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-gen-check">13</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="hardening">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Hardening', 'site-checkup-pro' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-hardening">14</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="advanced_protection">
					<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Advanced', 'site-checkup-pro' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-adv-prot">13</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="regular_checks">
					<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Maintenance', 'site-checkup-pro' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-reg-checks">7</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="seo_sop">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'SEO SOP', 'site-checkup-pro' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-seo">3</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="all">
					<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'All Checks', 'site-checkup-pro' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-all">55</span>
				</button>
			</div>

			<div class="wpsg-sidebar-group">
				<div class="wpsg-sidebar-heading"><?php esc_html_e( 'Tools & Records', 'site-checkup-pro' ); ?></div>
				
				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="features">
					<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Security Features', 'site-checkup-pro' ); ?></span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="audit_trail">
					<span class="dashicons dashicons-format-aside" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Audit Trail', 'site-checkup-pro' ); ?></span>
				</button>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=site-checkup-pro-report' ) ); ?>" class="wpsg-sidebar-item wpsg-sidebar-link">
					<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Client Report', 'site-checkup-pro' ); ?></span>
					<span class="dashicons dashicons-external" style="font-size: 13px; margin-left: auto;"></span>
				</a>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="settings">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Settings', 'site-checkup-pro' ); ?></span>
				</button>
			</div>

			<!-- Sidebar Footer Info -->
			<div class="wpsg-sidebar-info">
				<div class="wpsg-sidebar-env-badge">
					<span class="wpsg-status-dot" style="background: var(--wpsg-success);"></span>
					<span><?php echo esc_html( strtoupper( $server_type ) ); ?> &bull; PHP <?php echo esc_html( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ); ?></span>
				</div>
			</div>

		</aside>

		<!-- Right Content Panels Area -->
		<div class="wpsg-main-content">

			<!-- PANEL 1: OVERVIEW (Default Landing) -->
			<section class="wpsg-panel" id="wpsg-panel-overview">
				
				<!-- Factual KPI Metric Strip -->
				<div class="wpsg-kpi-strip" aria-label="<?php esc_attr_e( 'System Status Overview', 'site-checkup-pro' ); ?>">
					
					<!-- KPI 1: SOP Coverage -->
					<div class="wpsg-kpi-cell">
						<div class="wpsg-kpi-header-row">
							<span class="wpsg-kpi-title"><?php esc_html_e( 'SOP Coverage', 'site-checkup-pro' ); ?></span>
							<span class="dashicons dashicons-chart-pie" aria-hidden="true"></span>
						</div>
						<div class="wpsg-kpi-metric" id="wpsg-kpi-coverage">0%</div>
						<p class="wpsg-kpi-meta-line" id="wpsg-kpi-fraction">0 / 0 tasks active</p>
						<div class="wpsg-kpi-progress-track">
							<div class="wpsg-kpi-progress-fill" id="wpsg-coverage-bar" style="width: 0%;"></div>
						</div>
						<p class="wpsg-kpi-disclaimer-note"><?php esc_html_e( 'Factual checklist coverage; not a penetration test.', 'site-checkup-pro' ); ?></p>
					</div>

					<!-- KPI 2: Server Environment -->
					<div class="wpsg-kpi-cell">
						<div class="wpsg-kpi-header-row">
							<span class="wpsg-kpi-title"><?php esc_html_e( 'Environment', 'site-checkup-pro' ); ?></span>
							<span class="dashicons dashicons-networking" aria-hidden="true"></span>
						</div>
						<div class="wpsg-kpi-metric" id="wpsg-kpi-server"><?php echo esc_html( strtoupper( $server_type ) ); ?></div>
						<p class="wpsg-kpi-meta-line">
							<?php if ( $has_htaccess ) : ?>
								<span class="wpsg-status-indicator wpsg-status-done">
									<span class="wpsg-status-dot"></span> <?php esc_html_e( '.htaccess active', 'site-checkup-pro' ); ?>
								</span>
							<?php else : ?>
								<span class="wpsg-status-indicator wpsg-status-na">
									<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Nginx Directives Mode', 'site-checkup-pro' ); ?>
								</span>
							<?php endif; ?>
						</p>
						<p class="wpsg-kpi-disclaimer-note"><?php printf( esc_html__( 'PHP %s runtime.', 'site-checkup-pro' ), esc_html( PHP_VERSION ) ); ?></p>
					</div>

					<!-- KPI 3: Backup Gate -->
					<div class="wpsg-kpi-cell">
						<div class="wpsg-kpi-header-row">
							<span class="wpsg-kpi-title"><?php esc_html_e( 'Backup Protection', 'site-checkup-pro' ); ?></span>
							<span class="dashicons dashicons-backup" aria-hidden="true"></span>
						</div>
						<div class="wpsg-kpi-metric" id="wpsg-kpi-backup-name">
							<?php echo ! empty( $backup_status['plugin_name'] ) ? esc_html( $backup_status['plugin_name'] ) : esc_html__( 'Unverified', 'site-checkup-pro' ); ?>
						</div>
						<p class="wpsg-kpi-meta-line">
							<?php if ( ! empty( $backup_status['is_recent'] ) ) : ?>
								<span class="wpsg-status-indicator wpsg-status-done">
									<span class="wpsg-status-dot"></span> <?php printf( esc_html__( 'Verified (%s hrs ago)', 'site-checkup-pro' ), esc_html( $backup_status['age_hours'] ) ); ?>
								</span>
							<?php else : ?>
								<span class="wpsg-status-indicator wpsg-status-attention">
									<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Backup > 48h required', 'site-checkup-pro' ); ?>
								</span>
							<?php endif; ?>
						</p>
						<p class="wpsg-kpi-disclaimer-note"><?php esc_html_e( 'Protects all file-modifying tasks.', 'site-checkup-pro' ); ?></p>
					</div>

					<!-- KPI 4: Reminders -->
					<div class="wpsg-kpi-cell">
						<div class="wpsg-kpi-header-row">
							<span class="wpsg-kpi-title"><?php esc_html_e( 'Scheduled Checks', 'site-checkup-pro' ); ?></span>
							<span class="dashicons dashicons-clock" aria-hidden="true"></span>
						</div>
						<div class="wpsg-kpi-metric" id="wpsg-kpi-reminders">0</div>
						<p class="wpsg-kpi-meta-line"><?php esc_html_e( 'Active cadence reminders', 'site-checkup-pro' ); ?></p>
						<p class="wpsg-kpi-disclaimer-note"><?php esc_html_e( '15-day credentials & 6-month reviews.', 'site-checkup-pro' ); ?></p>
					</div>
				</div>

				<!-- Section Cards Grid: Clear, organized category navigation -->
				<div class="wpsg-section-header-block">
					<div>
						<h2 class="wpsg-section-heading"><?php esc_html_e( 'Checkup Categories', 'site-checkup-pro' ); ?></h2>
						<p class="wpsg-section-subheading"><?php esc_html_e( 'Standard Operating Procedures structured by domain for complete site hardening.', 'site-checkup-pro' ); ?></p>
					</div>
				</div>

				<div class="wpsg-category-grid">
					
					<!-- Card 1: Security Update -->
					<div class="wpsg-category-card" data-section-target="security_update">
						<div class="wpsg-cat-card-header">
							<div class="wpsg-cat-icon wpsg-cat-icon-shield">
								<span class="dashicons dashicons-shield"></span>
							</div>
							<div class="wpsg-cat-titles">
								<h3><?php esc_html_e( 'Security Update', 'site-checkup-pro' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-security_update">5 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Core security patches, automatic update policies, and foundational protection.', 'site-checkup-pro' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-security_update" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-security_update">0 / 5 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="security_update">
								<?php esc_html_e( 'View Checks &rarr;', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>

					<!-- Card 2: General Check / Site Audit -->
					<div class="wpsg-category-card" data-section-target="general_check">
						<div class="wpsg-cat-card-header">
							<div class="wpsg-cat-icon wpsg-cat-icon-audit">
								<span class="dashicons dashicons-visibility"></span>
							</div>
							<div class="wpsg-cat-titles">
								<h3><?php esc_html_e( 'Site Audit', 'site-checkup-pro' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-general_check">13 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Environment audit, rogue admin detection, database integrity, and file scans.', 'site-checkup-pro' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-general_check" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-general_check">0 / 13 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="general_check">
								<?php esc_html_e( 'View Checks &rarr;', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>

					<!-- Card 3: Hardening -->
					<div class="wpsg-category-card" data-section-target="hardening">
						<div class="wpsg-cat-card-header">
							<div class="wpsg-cat-icon wpsg-cat-icon-lock">
								<span class="dashicons dashicons-lock"></span>
							</div>
							<div class="wpsg-cat-titles">
								<h3><?php esc_html_e( 'Hardening', 'site-checkup-pro' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-hardening">14 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Server security headers, .htaccess protection, wp-config restrictions, and login security.', 'site-checkup-pro' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-hardening" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-hardening">0 / 14 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="hardening">
								<?php esc_html_e( 'View Checks &rarr;', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>

					<!-- Card 4: Advanced Protection -->
					<div class="wpsg-category-card" data-section-target="advanced_protection">
						<div class="wpsg-cat-card-header">
							<div class="wpsg-cat-icon wpsg-cat-icon-adv">
								<span class="dashicons dashicons-shield-alt"></span>
							</div>
							<div class="wpsg-cat-titles">
								<h3><?php esc_html_e( 'Advanced Protection', 'site-checkup-pro' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-advanced_protection">13 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Login throttling, user enumeration defense, session security, and runtime hardening.', 'site-checkup-pro' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-advanced_protection" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-advanced_protection">0 / 13 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="advanced_protection">
								<?php esc_html_e( 'View Checks &rarr;', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>

					<!-- Card 5: Maintenance / Regular Checks -->
					<div class="wpsg-category-card" data-section-target="regular_checks">
						<div class="wpsg-cat-card-header">
							<div class="wpsg-cat-icon wpsg-cat-icon-cal">
								<span class="dashicons dashicons-calendar-alt"></span>
							</div>
							<div class="wpsg-cat-titles">
								<h3><?php esc_html_e( 'Maintenance', 'site-checkup-pro' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-regular_checks">7 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Recurring 15-day credential rotations, vault tracking, and staging site protection.', 'site-checkup-pro' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-regular_checks" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-regular_checks">0 / 7 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="regular_checks">
								<?php esc_html_e( 'View Checks &rarr;', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>

					<!-- Card 6: SEO SOP -->
					<div class="wpsg-category-card" data-section-target="seo_sop">
						<div class="wpsg-cat-card-header">
							<div class="wpsg-cat-icon wpsg-cat-icon-seo">
								<span class="dashicons dashicons-search"></span>
							</div>
							<div class="wpsg-cat-titles">
								<h3><?php esc_html_e( 'SEO SOP', 'site-checkup-pro' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-seo_sop">3 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Search console indexing integrity, robots.txt audit, and URL removal tracking.', 'site-checkup-pro' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-seo_sop" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-seo_sop">0 / 3 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="seo_sop">
								<?php esc_html_e( 'View Checks &rarr;', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>

				</div>

				<!-- Quick Security Tools Strip -->
				<div class="wpsg-section-header-block" style="margin-top: 32px;">
					<div>
						<h2 class="wpsg-section-heading"><?php esc_html_e( 'Integrated Security Tools', 'site-checkup-pro' ); ?></h2>
						<p class="wpsg-section-subheading"><?php esc_html_e( 'Key defensive mechanisms built directly into Site Checkup Pro.', 'site-checkup-pro' ); ?></p>
					</div>
				</div>

				<div class="wpsg-tools-preview-grid">
					
					<div class="wpsg-tool-preview-card">
						<div class="wpsg-tool-preview-icon"><span class="dashicons dashicons-admin-network"></span></div>
						<div class="wpsg-tool-preview-body">
							<h4><?php esc_html_e( 'Custom Login URL', 'site-checkup-pro' ); ?></h4>
							<p>
								<?php if ( ! empty( $login_slug ) ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done"><span class="wpsg-status-dot"></span> <?php printf( esc_html__( 'Active (/%s/)', 'site-checkup-pro' ), esc_html( $login_slug ) ); ?></span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Default /wp-login.php', 'site-checkup-pro' ); ?></span>
								<?php endif; ?>
							</p>
						</div>
						<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-quick-login-url"><?php esc_html_e( 'Configure', 'site-checkup-pro' ); ?></button>
					</div>

					<div class="wpsg-tool-preview-card">
						<div class="wpsg-tool-preview-icon"><span class="dashicons dashicons-groups"></span></div>
						<div class="wpsg-tool-preview-body">
							<h4><?php esc_html_e( 'User Sessions', 'site-checkup-pro' ); ?></h4>
							<p><?php esc_html_e( 'Audit and destroy compromised user sessions across devices.', 'site-checkup-pro' ); ?></p>
						</div>
						<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-quick-sessions"><?php esc_html_e( 'Manage', 'site-checkup-pro' ); ?></button>
					</div>

					<div class="wpsg-tool-preview-card">
						<div class="wpsg-tool-preview-icon"><span class="dashicons dashicons-media-document"></span></div>
						<div class="wpsg-tool-preview-body">
							<h4><?php esc_html_e( 'Client Security Report', 'site-checkup-pro' ); ?></h4>
							<p><?php esc_html_e( 'Generate white-labeled executive PDF reports for clients.', 'site-checkup-pro' ); ?></p>
						</div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=site-checkup-pro-report' ) ); ?>" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary"><?php esc_html_e( 'View Report', 'site-checkup-pro' ); ?></a>
					</div>

				</div>

			</section>

			<!-- PANEL 2: TASK CHECKS (Shared by security_update, general_check, hardening, advanced_protection, regular_checks, seo_sop, and all) -->
			<section class="wpsg-panel" id="wpsg-panel-tasks" style="display: none;">
				
				<!-- Section Hero Banner -->
				<div class="wpsg-section-banner" id="wpsg-section-banner">
					<div class="wpsg-section-banner-content">
						<div class="wpsg-section-banner-icon-wrap">
							<span class="dashicons dashicons-shield" id="wpsg-current-sec-icon"></span>
						</div>
						<div>
							<div class="wpsg-section-banner-title-row">
								<h2 id="wpsg-current-sec-title"><?php esc_html_e( 'Security Checks', 'site-checkup-pro' ); ?></h2>
								<span class="wpsg-section-banner-badge" id="wpsg-current-sec-badge">0 checks</span>
							</div>
							<p id="wpsg-current-sec-desc"><?php esc_html_e( 'Standard operating procedures for site hardening.', 'site-checkup-pro' ); ?></p>
						</div>
					</div>
					<div class="wpsg-section-banner-actions">
						<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-run-section">
							<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run Safe Checks in Section', 'site-checkup-pro' ); ?>
						</button>
					</div>
				</div>

				<!-- Toolbar & Filters -->
				<div class="wpsg-toolbar">
					<div class="wpsg-search-box">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<input type="text" id="wpsg-search-tasks" class="wpsg-search-input" placeholder="<?php esc_attr_e( 'Search checks in this section...', 'site-checkup-pro' ); ?>" />
					</div>

					<div class="wpsg-filter-group">
						<select id="wpsg-filter-level" class="wpsg-select" aria-label="<?php esc_attr_e( 'Filter by automation level', 'site-checkup-pro' ); ?>">
							<option value=""><?php esc_html_e( 'All Levels', 'site-checkup-pro' ); ?></option>
							<option value="A_instant"><?php esc_html_e( 'Level A: Safe / Instant', 'site-checkup-pro' ); ?></option>
							<option value="A_files"><?php esc_html_e( 'Level A: Config / File Writers', 'site-checkup-pro' ); ?></option>
							<option value="B"><?php esc_html_e( 'Level B: Guided Actions', 'site-checkup-pro' ); ?></option>
							<option value="C"><?php esc_html_e( 'Level C: Manual / Reminders', 'site-checkup-pro' ); ?></option>
						</select>

						<select id="wpsg-filter-status" class="wpsg-select" aria-label="<?php esc_attr_e( 'Filter by status', 'site-checkup-pro' ); ?>">
							<option value=""><?php esc_html_e( 'All Statuses', 'site-checkup-pro' ); ?></option>
							<option value="pending"><?php esc_html_e( 'Pending', 'site-checkup-pro' ); ?></option>
							<option value="done"><?php esc_html_e( 'Completed', 'site-checkup-pro' ); ?></option>
							<option value="attention"><?php esc_html_e( 'Action Needed', 'site-checkup-pro' ); ?></option>
							<option value="failed"><?php esc_html_e( 'Critical / Failed', 'site-checkup-pro' ); ?></option>
							<option value="not_applicable"><?php esc_html_e( 'Not Applicable', 'site-checkup-pro' ); ?></option>
						</select>
					</div>
				</div>

				<!-- Main Tasks Table Container -->
				<main class="wpsg-table-container" id="wpsg-tasks-table-wrapper">
					<table class="wpsg-table" id="wpsg-tasks-table">
						<thead>
							<tr>
								<th class="wpsg-col-status"><?php esc_html_e( 'Status', 'site-checkup-pro' ); ?></th>
								<th class="wpsg-col-level"><?php esc_html_e( 'Level', 'site-checkup-pro' ); ?></th>
								<th class="wpsg-col-task"><?php esc_html_e( 'Check / Procedure', 'site-checkup-pro' ); ?></th>
								<th class="wpsg-col-checked"><?php esc_html_e( 'Last Verified', 'site-checkup-pro' ); ?></th>
								<th class="wpsg-col-actions"><?php esc_html_e( 'Action', 'site-checkup-pro' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpsg-tasks-tbody">
							<tr>
								<td colspan="5" style="text-align: center; padding: 40px;">
									<span class="wpsg-spinner" aria-hidden="true"></span>
									<p style="margin: 8px 0 0 0; color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Loading checklist tasks and live status...', 'site-checkup-pro' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</main>

			</section>

			<!-- PANEL 3: FEATURES & TOOLS -->
			<section class="wpsg-panel" id="wpsg-panel-features" style="display: none;">
				
				<div class="wpsg-section-banner">
					<div class="wpsg-section-banner-content">
						<div class="wpsg-section-banner-icon-wrap">
							<span class="dashicons dashicons-admin-tools"></span>
						</div>
						<div>
							<div class="wpsg-section-banner-title-row">
								<h2><?php esc_html_e( 'Security Features & Controls', 'site-checkup-pro' ); ?></h2>
								<span class="wpsg-section-banner-badge"><?php esc_html_e( 'Active Modules', 'site-checkup-pro' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Manage advanced built-in security features, custom endpoints, sessions, and protections.', 'site-checkup-pro' ); ?></p>
						</div>
					</div>
				</div>

				<div class="wpsg-features-cards-container">
					
					<!-- Feature 1: Rename Login URL -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-admin-network"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Custom Login URL (Hide /wp-admin & /wp-login.php)', 'site-checkup-pro' ); ?></h3>
								<p><?php esc_html_e( 'Protects against brute force attacks by hiding the default login endpoint and blocking /wp-admin probes with an unguessable 404.', 'site-checkup-pro' ); ?></p>
							</div>
							<div class="wpsg-feature-card-status">
								<?php if ( ! empty( $login_slug ) ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Active', 'site-checkup-pro' ); ?></span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Default', 'site-checkup-pro' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<div class="wpsg-feature-form-row">
								<label for="wpsg-feat-login-slug"><?php esc_html_e( 'Login URL Path:', 'site-checkup-pro' ); ?></label>
								<div class="wpsg-input-prefix-wrap">
									<span class="wpsg-input-prefix"><?php echo esc_html( home_url( '/' ) ); ?></span>
									<input type="text" id="wpsg-feat-login-slug" class="wpsg-input" value="<?php echo esc_attr( $login_slug ); ?>" placeholder="secret-login" />
									<span class="wpsg-input-suffix">/</span>
								</div>
								<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-save-feature-login"><?php esc_html_e( 'Save URL', 'site-checkup-pro' ); ?></button>
								<?php if ( ! empty( $login_slug ) ) : ?>
									<button type="button" class="wpsg-btn wpsg-btn-subtle" id="wpsg-btn-reset-feature-login"><?php esc_html_e( 'Revert to Default', 'site-checkup-pro' ); ?></button>
									<a href="<?php echo esc_url( home_url( '/' . $login_slug . '/' ) ); ?>" target="_blank" class="wpsg-btn wpsg-btn-secondary">
										<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Test Login URL', 'site-checkup-pro' ); ?>
									</a>
								<?php endif; ?>
							</div>
							<div class="wpsg-feature-notice">
								<span class="dashicons dashicons-info"></span>
								<span>
									<strong><?php esc_html_e( 'Lockout Recovery Guard:', 'site-checkup-pro' ); ?></strong>
									<?php esc_html_e( 'If you ever forget your custom slug, add ', 'site-checkup-pro' ); ?>
									<code>define( 'WPSG_DISABLE_LOGIN_RENAME', true );</code>
									<?php esc_html_e( ' to your wp-config.php to immediately restore the standard /wp-login.php.', 'site-checkup-pro' ); ?>
								</span>
							</div>
						</div>
					</div>

					<!-- Feature 2: Active User Sessions -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-groups"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Active User Sessions Management', 'site-checkup-pro' ); ?></h3>
								<p><?php esc_html_e( 'Track all currently logged-in administrator and user sessions with client IP and browser device fingerprint.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<p style="margin: 0 0 14px; font-size: 13px; color: var(--wpsg-text-secondary);">
								<?php esc_html_e( 'Review active sessions across multiple devices and instantly terminate unauthorized sessions if suspicious activity is detected.', 'site-checkup-pro' ); ?>
							</p>
							<div style="display: flex; gap: 10px; flex-wrap: wrap;">
								<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-feat-view-sessions">
									<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Inspect Active Sessions', 'site-checkup-pro' ); ?>
								</button>
								<button type="button" class="wpsg-btn wpsg-btn-subtle" id="wpsg-btn-feat-destroy-sessions" style="color: var(--wpsg-critical);">
									<span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Log Out All Other Sessions', 'site-checkup-pro' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Feature 3: Application Passwords Lockdown -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-key"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Application Passwords Lockdown', 'site-checkup-pro' ); ?></h3>
								<p><?php esc_html_e( 'Restrict REST API application passwords to administrators only, preventing lower-privileged accounts from creating bypass keys.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<div style="display: flex; gap: 10px; align-items: center;">
								<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-feat-app-passwords">
									<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Manage Application Passwords', 'site-checkup-pro' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Feature 4: CSP Violation Reports Stream -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-bell"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Content Security Policy (CSP) Violations', 'site-checkup-pro' ); ?></h3>
								<p><?php esc_html_e( 'Real-time telemetry listening endpoint for browser CSP violation reports to detect XSS and injected scripts.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-feat-csp-reports">
								<span class="dashicons dashicons-analytics"></span> <?php esc_html_e( 'View CSP Reports Feed', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>

					<!-- Feature 5: System & Admin Baseline -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-saved"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Administrator & Database Baseline', 'site-checkup-pro' ); ?></h3>
								<p><?php esc_html_e( 'Cryptographic anchor storing authorized administrator user IDs and database configuration to alert on rogue account creation.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-feat-update-baseline">
								<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Update & Trust Baseline Now', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>

				</div>

			</section>

			<!-- PANEL 4: AUDIT TRAIL -->
			<section class="wpsg-panel" id="wpsg-panel-audit" style="display: none;">
				
				<div class="wpsg-section-banner">
					<div class="wpsg-section-banner-content">
						<div class="wpsg-section-banner-icon-wrap">
							<span class="dashicons dashicons-format-aside"></span>
						</div>
						<div>
							<div class="wpsg-section-banner-title-row">
								<h2><?php esc_html_e( 'Security Audit Trail', 'site-checkup-pro' ); ?></h2>
								<span class="wpsg-section-banner-badge"><?php esc_html_e( 'Immutable Log', 'site-checkup-pro' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Chronological record of automated hardening procedures, configuration changes, and check executions.', 'site-checkup-pro' ); ?></p>
						</div>
					</div>
				</div>

				<div class="wpsg-table-container">
					<table class="wpsg-table" id="wpsg-audit-table">
						<thead>
							<tr>
								<th style="width: 170px;"><?php esc_html_e( 'Timestamp', 'site-checkup-pro' ); ?></th>
								<th style="width: 160px;"><?php esc_html_e( 'Task ID', 'site-checkup-pro' ); ?></th>
								<th style="width: 110px;"><?php esc_html_e( 'Action', 'site-checkup-pro' ); ?></th>
								<th style="width: 140px;"><?php esc_html_e( 'User', 'site-checkup-pro' ); ?></th>
								<th style="width: 110px;"><?php esc_html_e( 'Result', 'site-checkup-pro' ); ?></th>
								<th><?php esc_html_e( 'Technical Log Message (Redacted)', 'site-checkup-pro' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpsg-audit-tbody">
							<!-- Populated dynamically via admin.js -->
						</tbody>
					</table>
				</div>

			</section>

			<!-- PANEL 5: SETTINGS -->
			<section class="wpsg-panel" id="wpsg-panel-settings" style="display: none;">
				
				<div class="wpsg-section-banner">
					<div class="wpsg-section-banner-content">
						<div class="wpsg-section-banner-icon-wrap">
							<span class="dashicons dashicons-admin-generic"></span>
						</div>
						<div>
							<div class="wpsg-section-banner-title-row">
								<h2><?php esc_html_e( 'Plugin Settings & Alert Channels', 'site-checkup-pro' ); ?></h2>
							</div>
							<p><?php esc_html_e( 'Configure security incident notifications, third-party intelligence API keys, and audit retention.', 'site-checkup-pro' ); ?></p>
						</div>
					</div>
				</div>

				<div class="wpsg-settings-page-card">
					<p style="color: var(--wpsg-text-secondary); margin-bottom: 20px;">
						<?php esc_html_e( 'Click the button below to configure API integrations, emergency alert emails, Slack webhook endpoints, and automated cadence schedules.', 'site-checkup-pro' ); ?>
					</p>
					<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-trigger-settings-modal">
						<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Open Settings Modal', 'site-checkup-pro' ); ?>
					</button>
				</div>

			</section>

		</div><!-- /.wpsg-main-content -->

	</div><!-- /.wpsg-app-layout -->

	<!-- Modal Dialogs (Preserved exactly for full backward compatibility) -->
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-diff.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-nginx.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-backup.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-note.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-login-rename.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-delete-plugin.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-reauth.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-sessions.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-app-passwords.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-csp-reports.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-settings.php'; ?>

	<!-- Settings Screen Footer / Resource Block -->
	<footer class="wpsg-dashboard-footer" style="margin-top: 32px; padding: 18px 24px; background: var(--wpsg-surface); border: 1px solid var(--wpsg-border); border-radius: var(--wpsg-radius-md); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; font-size: 13px;">
		<div class="wpsg-footer-attribution" style="color: var(--wpsg-text-secondary);">
			<span><?php esc_html_e( 'Developed by', 'site-checkup-pro' ); ?> <strong><a href="https://www.genioussonu.me/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-brand); text-decoration: none;">SK Sahinur Islam</a></strong></span>
			<span style="margin: 0 8px; color: var(--wpsg-border);">&bull;</span>
			<span class="wpsg-footer-version">Site Checkup Pro v<?php echo esc_html( WPSG_VERSION ); ?></span>
		</div>
		<nav class="wpsg-footer-links" style="display: flex; gap: 16px;" aria-label="<?php esc_attr_e( 'Help and documentation links', 'site-checkup-pro' ); ?>">
			<a href="https://www.genioussonu.me/plugin/site-checkup-pro/docs/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-book" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Docs', 'site-checkup-pro' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/site-checkup-pro/support/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-sos" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Support', 'site-checkup-pro' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/site-checkup-pro/changelog/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-backup" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Changelog', 'site-checkup-pro' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/site-checkup-pro/privacy-policy/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-shield" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Privacy Policy', 'site-checkup-pro' ); ?>
			</a>
		</nav>
	</footer>

</div>
