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
					echo esc_html(
						sprintf(
							/* translators: %d: number of completed security tasks */
							__( 'You have successfully run %d security hardening and audit checks. Leaving a quick review helps us immensely!', 'site-checkup-pro' ),
							$wpsg_tasks_done
						)
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

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="rest_api">
					<span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'REST API', 'site-checkup-pro' ); ?></span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="dev_toolkit">
					<span class="dashicons dashicons-code-standards" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Developer Toolkit', 'site-checkup-pro' ); ?></span>
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

			<!-- PANEL 1: OVERVIEW (Executive Command Center) -->
			<section class="wpsg-panel" id="wpsg-panel-overview">

				<!-- Executive Security Posture Hero Banner -->
				<div class="wpsg-posture-hero">
					<div class="wpsg-posture-gauge-col">
						<div class="wpsg-gauge-wrapper">
							<svg class="wpsg-gauge-svg" viewBox="0 0 120 120">
								<circle class="wpsg-gauge-bg" cx="60" cy="60" r="50" />
								<circle class="wpsg-gauge-progress" id="wpsg-gauge-circle" cx="60" cy="60" r="50" stroke-dasharray="314.159" stroke-dashoffset="314.159" />
							</svg>
							<div class="wpsg-gauge-center">
								<span class="wpsg-gauge-score" id="wpsg-posture-pct">0%</span>
								<span class="wpsg-gauge-grade" id="wpsg-posture-grade">Grade --</span>
							</div>
						</div>
					</div>
					<div class="wpsg-posture-info-col">
						<div class="wpsg-posture-badge-row">
							<span class="wpsg-pulse-badge">
								<span class="wpsg-pulse-dot"></span>
								<?php esc_html_e( 'Real-Time Protection Active', 'site-checkup-pro' ); ?>
							</span>
							<span class="wpsg-posture-env-pill">
								<?php echo esc_html( strtoupper( $server_type ) ); ?> &bull; PHP <?php echo esc_html( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ); ?>
							</span>
						</div>
						<h2 class="wpsg-posture-title"><?php esc_html_e( 'Site Security Hardening & Posture', 'site-checkup-pro' ); ?></h2>
						<p class="wpsg-posture-desc" id="wpsg-posture-stats-text">
							<?php esc_html_e( 'Calculating verified standard operating procedures across all 6 hardening domains...', 'site-checkup-pro' ); ?>
						</p>
						<div class="wpsg-posture-actions">
							<button type="button" class="wpsg-btn wpsg-btn-primary wpsg-btn-hero" id="wpsg-btn-hero-run-safe">
								<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run Safe Verification Tasks', 'site-checkup-pro' ); ?>
							</button>
							<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-hero-view-all">
								<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Inspect All Checks', 'site-checkup-pro' ); ?>
							</button>
						</div>
					</div>
				</div>

				<!-- Priority Action Needed Queue (Rendered conditionally via JS) -->
				<div class="wpsg-attention-block" id="wpsg-overview-attention" style="display: none;">
					<div class="wpsg-attention-header">
						<div class="wpsg-attention-title-area">
							<span class="dashicons dashicons-warning wpsg-attention-icon"></span>
							<div>
								<h3><?php esc_html_e( 'Priority Action Required', 'site-checkup-pro' ); ?></h3>
								<p><?php esc_html_e( 'These items have failed verification or require immediate administrator attention.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<span class="wpsg-attention-badge" id="wpsg-attention-count">0 items</span>
					</div>
					<div class="wpsg-attention-list" id="wpsg-attention-items">
						<!-- Populated via admin.js -->
					</div>
				</div>

				<!-- 4 High-Impact KPI Metric Cards -->
				<div class="wpsg-kpi-grid" aria-label="<?php esc_attr_e( 'System Status Overview', 'site-checkup-pro' ); ?>">
					
					<!-- KPI 1: SOP Coverage -->
					<div class="wpsg-kpi-card">
						<div class="wpsg-kpi-card-header">
							<span class="wpsg-kpi-card-title"><?php esc_html_e( 'SOP Checklist Coverage', 'site-checkup-pro' ); ?></span>
							<div class="wpsg-kpi-icon-wrap wpsg-icon-brand">
								<span class="dashicons dashicons-chart-pie"></span>
							</div>
						</div>
						<div class="wpsg-kpi-card-body">
							<div class="wpsg-kpi-card-metric" id="wpsg-kpi-coverage">0%</div>
							<p class="wpsg-kpi-card-meta" id="wpsg-kpi-fraction"><?php esc_html_e( '0 of 0 tasks active', 'site-checkup-pro' ); ?></p>
							<div class="wpsg-kpi-progress-track">
								<div class="wpsg-kpi-progress-fill" id="wpsg-coverage-bar" style="width: 0%;"></div>
							</div>
						</div>
						<div class="wpsg-kpi-card-footer">
							<span class="wpsg-kpi-footnote"><?php esc_html_e( 'Factual SOP verification', 'site-checkup-pro' ); ?></span>
						</div>
					</div>

					<!-- KPI 2: Server Environment & Directives -->
					<div class="wpsg-kpi-card">
						<div class="wpsg-kpi-card-header">
							<span class="wpsg-kpi-card-title"><?php esc_html_e( 'Web Server Architecture', 'site-checkup-pro' ); ?></span>
							<div class="wpsg-kpi-icon-wrap wpsg-icon-server">
								<span class="dashicons dashicons-networking"></span>
							</div>
						</div>
						<div class="wpsg-kpi-card-body">
							<div class="wpsg-kpi-card-metric" id="wpsg-kpi-server"><?php echo esc_html( strtoupper( $server_type ) ); ?></div>
							<p class="wpsg-kpi-card-meta">
								<?php if ( $has_htaccess ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done">
										<span class="wpsg-status-dot"></span> <?php esc_html_e( '.htaccess rules supported', 'site-checkup-pro' ); ?>
									</span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention" id="wpsg-kpi-nginx-tier-indicator">
										<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Nginx Directives Mode', 'site-checkup-pro' ); ?>
									</span>
								<?php endif; ?>
							</p>
						</div>
						<div class="wpsg-kpi-card-footer">
							<span class="wpsg-kpi-footnote">
								<?php
								/* translators: %s: PHP version */
								printf( esc_html__( 'PHP %s engine', 'site-checkup-pro' ), esc_html( PHP_VERSION ) );
								?>
							</span>
						</div>
					</div>

					<!-- KPI 3: Backup Safety Gate -->
					<div class="wpsg-kpi-card">
						<div class="wpsg-kpi-card-header">
							<span class="wpsg-kpi-card-title"><?php esc_html_e( 'Backup Safety Gate', 'site-checkup-pro' ); ?></span>
							<div class="wpsg-kpi-icon-wrap wpsg-icon-backup">
								<span class="dashicons dashicons-backup"></span>
							</div>
						</div>
						<div class="wpsg-kpi-card-body">
							<div class="wpsg-kpi-card-metric" id="wpsg-kpi-backup-name">
								<?php echo ! empty( $backup_status['plugin_name'] ) ? esc_html( $backup_status['plugin_name'] ) : esc_html__( 'Unverified', 'site-checkup-pro' ); ?>
							</div>
							<p class="wpsg-kpi-card-meta">
								<?php if ( ! empty( $backup_status['is_recent'] ) ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done">
										<span class="wpsg-status-dot"></span>
										<?php
										/* translators: %s: backup age in hours */
										printf( esc_html__( 'Verified (%s hrs ago)', 'site-checkup-pro' ), esc_html( $backup_status['age_hours'] ) );
										?>
									</span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention">
										<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Backup > 48h required', 'site-checkup-pro' ); ?>
									</span>
								<?php endif; ?>
							</p>
						</div>
						<div class="wpsg-kpi-card-footer">
							<span class="wpsg-kpi-footnote"><?php esc_html_e( 'Protects all file-writing tasks', 'site-checkup-pro' ); ?></span>
						</div>
					</div>

					<!-- KPI 4: Active Defenses & Reminders -->
					<div class="wpsg-kpi-card">
						<div class="wpsg-kpi-card-header">
							<span class="wpsg-kpi-card-title"><?php esc_html_e( 'Cadence & Defense', 'site-checkup-pro' ); ?></span>
							<div class="wpsg-kpi-icon-wrap wpsg-icon-clock">
								<span class="dashicons dashicons-shield"></span>
							</div>
						</div>
						<div class="wpsg-kpi-card-body">
							<div class="wpsg-kpi-card-metric" id="wpsg-kpi-reminders">0</div>
							<p class="wpsg-kpi-card-meta">
								<?php if ( ! empty( $login_slug ) ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done">
										<span class="wpsg-status-dot"></span>
										<?php
										/* translators: %s: custom login slug */
										printf( esc_html__( 'Custom Login (/%s/)', 'site-checkup-pro' ), esc_html( $login_slug ) );
										?>
									</span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention">
										<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Default Login Active', 'site-checkup-pro' ); ?>
									</span>
								<?php endif; ?>
							</p>
						</div>
						<div class="wpsg-kpi-card-footer">
							<span class="wpsg-kpi-footnote"><?php esc_html_e( '15-day reviews & lockouts active', 'site-checkup-pro' ); ?></span>
						</div>
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
									<span class="wpsg-status-indicator wpsg-status-done"><span class="wpsg-status-dot"></span> <?php /* translators: %s: custom login slug */ printf( esc_html__( 'Active (/%s/)', 'site-checkup-pro' ), esc_html( $login_slug ) ); ?></span>
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
							<option value="applied_unverified"><?php esc_html_e( 'Applied (Unverified)', 'site-checkup-pro' ); ?></option>
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
								<th class="wpsg-audit-col-time"><?php esc_html_e( 'Timestamp', 'site-checkup-pro' ); ?></th>
								<th class="wpsg-audit-col-task"><?php esc_html_e( 'Task ID', 'site-checkup-pro' ); ?></th>
								<th class="wpsg-audit-col-action"><?php esc_html_e( 'Action', 'site-checkup-pro' ); ?></th>
								<th class="wpsg-audit-col-user"><?php esc_html_e( 'User', 'site-checkup-pro' ); ?></th>
								<th class="wpsg-audit-col-result"><?php esc_html_e( 'Result', 'site-checkup-pro' ); ?></th>
								<th><?php esc_html_e( 'Technical Log Message (Redacted)', 'site-checkup-pro' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpsg-audit-tbody">
							<!-- Populated dynamically via admin.js -->
						</tbody>
					</table>
				</div>

			</section>

			<!-- PANEL: REST API SECURITY AUDITOR -->
			<section class="wpsg-panel" id="wpsg-panel-rest-api" style="display: none;">
				<div class="wpsg-section-banner">
					<div class="wpsg-section-banner-content">
						<div class="wpsg-section-banner-icon-wrap">
							<span class="dashicons dashicons-rest-api"></span>
						</div>
						<div>
							<div class="wpsg-section-banner-title-row">
								<h2><?php esc_html_e( 'REST API Security Auditor', 'site-checkup-pro' ); ?></h2>
								<span class="wpsg-section-banner-badge"><?php esc_html_e( 'Endpoint Inspector', 'site-checkup-pro' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Complete discovery and authorization inspection of all registered REST endpoints across core, active plugins, and themes.', 'site-checkup-pro' ); ?></p>
						</div>
					</div>
					<div class="wpsg-section-banner-actions">
						<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-refresh-rest-audit">
							<span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
							<?php esc_html_e( 'Re-scan REST Routes', 'site-checkup-pro' ); ?>
						</button>
					</div>
				</div>

				<!-- REST Stats Summary -->
				<div class="wpsg-overview-grid" style="margin-bottom: 20px;">
					<div class="wpsg-category-card">
						<div class="wpsg-category-header">
							<div>
								<div class="wpsg-category-title"><?php esc_html_e( 'Total Endpoints', 'site-checkup-pro' ); ?></div>
								<div class="wpsg-category-subtitle"><?php esc_html_e( 'Across all routes', 'site-checkup-pro' ); ?></div>
							</div>
							<div class="wpsg-category-stat" id="wpsg-rest-stat-total">0</div>
						</div>
					</div>
					<div class="wpsg-category-card">
						<div class="wpsg-category-header">
							<div>
								<div class="wpsg-category-title"><?php esc_html_e( 'Protected Endpoints', 'site-checkup-pro' ); ?></div>
								<div class="wpsg-category-subtitle"><?php esc_html_e( 'Capability gated', 'site-checkup-pro' ); ?></div>
							</div>
							<div class="wpsg-category-stat" style="color: var(--wpsg-success);" id="wpsg-rest-stat-protected">0</div>
						</div>
					</div>
					<div class="wpsg-category-card">
						<div class="wpsg-category-header">
							<div>
								<div class="wpsg-category-title"><?php esc_html_e( 'Publicly Accessible', 'site-checkup-pro' ); ?></div>
								<div class="wpsg-category-subtitle"><?php esc_html_e( 'Open to unauthenticated visitors', 'site-checkup-pro' ); ?></div>
							</div>
							<div class="wpsg-category-stat" style="color: var(--wpsg-warning);" id="wpsg-rest-stat-public">0</div>
						</div>
					</div>
					<div class="wpsg-category-card">
						<div class="wpsg-category-header">
							<div>
								<div class="wpsg-category-title"><?php esc_html_e( 'High Risk Write Endpoints', 'site-checkup-pro' ); ?></div>
								<div class="wpsg-category-subtitle"><?php esc_html_e( 'Public POST/PUT/DELETE', 'site-checkup-pro' ); ?></div>
							</div>
							<div class="wpsg-category-stat" style="color: var(--wpsg-danger);" id="wpsg-rest-stat-high">0</div>
						</div>
					</div>
				</div>

				<!-- REST Filter Controls -->
				<div class="wpsg-filter-bar" style="margin-bottom: 16px; display: flex; gap: 12px; align-items: center; justify-content: space-between;">
					<div class="wpsg-filter-group" style="display: flex; gap: 6px;">
						<button type="button" class="wpsg-filter-btn active" data-rest-filter="all"><?php esc_html_e( 'All', 'site-checkup-pro' ); ?></button>
						<button type="button" class="wpsg-filter-btn" data-rest-filter="public"><?php esc_html_e( 'Public', 'site-checkup-pro' ); ?></button>
						<button type="button" class="wpsg-filter-btn" data-rest-filter="protected"><?php esc_html_e( 'Protected', 'site-checkup-pro' ); ?></button>
						<button type="button" class="wpsg-filter-btn" data-rest-filter="needs_review"><?php esc_html_e( 'Needs Review', 'site-checkup-pro' ); ?></button>
						<button type="button" class="wpsg-filter-btn" data-rest-filter="critical"><?php esc_html_e( 'High Risk', 'site-checkup-pro' ); ?></button>
					</div>
					<div class="wpsg-search-box" style="max-width: 280px; width: 100%;">
						<input type="search" id="wpsg-rest-search" class="wpsg-input" placeholder="<?php esc_attr_e( 'Search routes or plugins...', 'site-checkup-pro' ); ?>">
					</div>
				</div>

				<div class="wpsg-table-container">
					<table class="wpsg-table" id="wpsg-rest-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Route Pattern', 'site-checkup-pro' ); ?></th>
								<th><?php esc_html_e( 'Method(s)', 'site-checkup-pro' ); ?></th>
								<th><?php esc_html_e( 'Origin / Namespace', 'site-checkup-pro' ); ?></th>
								<th><?php esc_html_e( 'Permission Callback', 'site-checkup-pro' ); ?></th>
								<th><?php esc_html_e( 'Risk Level', 'site-checkup-pro' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpsg-rest-tbody">
							<!-- Populated via admin.js -->
						</tbody>
					</table>
				</div>
			</section>

			<!-- PANEL: DEVELOPER TOOLKIT -->
			<section class="wpsg-panel" id="wpsg-panel-dev-toolkit" style="display: none;">
				<div class="wpsg-section-banner">
					<div class="wpsg-section-banner-content">
						<div class="wpsg-section-banner-icon-wrap">
							<span class="dashicons dashicons-code-standards"></span>
						</div>
						<div>
							<div class="wpsg-section-banner-title-row">
								<h2><?php esc_html_e( 'Developer Toolkit & Diagnostics', 'site-checkup-pro' ); ?></h2>
								<span class="wpsg-section-banner-badge"><?php esc_html_e( 'v1.2 Tools', 'site-checkup-pro' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Professional tools for WordPress engineers: environment tagging, sanitized system diagnostic snapshots, WP-Cron inspection, database bloat cleanup, and migration verification.', 'site-checkup-pro' ); ?></p>
						</div>
					</div>
				</div>

				<div class="wpsg-overview-grid" style="margin-bottom: 24px;">
					
					<!-- Toolkit 1: Environment Tag & Admin Bar -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--wpsg-success);">
								<span class="dashicons dashicons-tag"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Environment Badge Tagging', 'site-checkup-pro' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Active top admin bar safety badge indicating server environment.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 12px;">
								<select id="wpsg-dev-env-select" class="wpsg-select" style="max-width: 200px;">
									<option value="production"><?php esc_html_e( 'Production (Red)', 'site-checkup-pro' ); ?></option>
									<option value="staging"><?php esc_html_e( 'Staging (Amber)', 'site-checkup-pro' ); ?></option>
									<option value="development"><?php esc_html_e( 'Development (Gray)', 'site-checkup-pro' ); ?></option>
								</select>
								<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary" id="wpsg-btn-save-env"><?php esc_html_e( 'Update Badge', 'site-checkup-pro' ); ?></button>
							</div>
							<p class="wpsg-help-text" id="wpsg-dev-env-help"><?php esc_html_e( 'Smart heuristics inspect your hostname and constants to protect production sites.', 'site-checkup-pro' ); ?></p>
						</div>
					</div>

					<!-- Toolkit 2: Diagnostic Snapshot -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--wpsg-primary);">
								<span class="dashicons dashicons-clipboard"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Diagnostic Snapshot Export', 'site-checkup-pro' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Sanitized Markdown report ready for GitHub issues and tickets.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div style="display: flex; gap: 8px; margin-bottom: 10px;">
								<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary" id="wpsg-btn-copy-diagnostic">
									<span class="dashicons dashicons-admin-page" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
									<?php esc_html_e( 'Copy Markdown to Clipboard', 'site-checkup-pro' ); ?>
								</button>
								<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-view-diagnostic"><?php esc_html_e( 'View Snapshot', 'site-checkup-pro' ); ?></button>
							</div>
							<p class="wpsg-help-text"><?php esc_html_e( 'All database passwords, salts, and secret credentials are strictly redacted.', 'site-checkup-pro' ); ?></p>
						</div>
					</div>

					<!-- Toolkit 3: WP-Cron Scheduled Events -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--wpsg-warning);">
								<span class="dashicons dashicons-calendar-alt"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'WP-Cron Health Auditor', 'site-checkup-pro' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Detects overdue background tasks and duplicate hooks.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div id="wpsg-dev-cron-summary" style="font-size: 13px; color: var(--wpsg-text-secondary); margin-bottom: 10px;">
								<?php esc_html_e( 'Loading scheduled cron events...', 'site-checkup-pro' ); ?>
							</div>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-refresh-cron"><?php esc_html_e( 'Audit Cron Jobs', 'site-checkup-pro' ); ?></button>
						</div>
					</div>

					<!-- Toolkit 4: Database Health & Bloat -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--wpsg-danger);">
								<span class="dashicons dashicons-database"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Database Bloat & Cleanup', 'site-checkup-pro' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Orphaned meta, expired transients, and excess revisions.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div id="wpsg-dev-db-summary" style="font-size: 13px; color: var(--wpsg-text-secondary); margin-bottom: 10px;">
								<?php esc_html_e( 'Loading database health metrics...', 'site-checkup-pro' ); ?>
							</div>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-danger" id="wpsg-btn-clean-db"><?php esc_html_e( 'Clean Up Bloat (Level B)', 'site-checkup-pro' ); ?></button>
						</div>
					</div>

					<!-- Toolkit 5: Migration Readiness -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
								<span class="dashicons dashicons-migrate"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Migration Serialization Readiness', 'site-checkup-pro' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Detects absolute URLs inside serialized PHP objects.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div id="wpsg-dev-migration-summary" style="font-size: 13px; color: var(--wpsg-text-secondary); margin-bottom: 10px;">
								<?php esc_html_e( 'Scanning for serialized URL hazards...', 'site-checkup-pro' ); ?>
							</div>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-check-migration"><?php esc_html_e( 'Re-scan Migration Risk', 'site-checkup-pro' ); ?></button>
						</div>
					</div>

					<!-- Toolkit 6: Weekly Changelog Digest -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
								<span class="dashicons dashicons-rss"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Weekly Changelog Digest', 'site-checkup-pro' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Intelligence summaries for available updates.', 'site-checkup-pro' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div id="wpsg-dev-changelog-summary" style="font-size: 13px; color: var(--wpsg-text-secondary); margin-bottom: 10px;">
								<?php esc_html_e( 'Aggregating update transients...', 'site-checkup-pro' ); ?>
							</div>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-refresh-digest"><?php esc_html_e( 'Refresh Digest', 'site-checkup-pro' ); ?></button>
						</div>
					</div>

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

				<div class="wpsg-settings-inpage-container">
					<form id="wpsg-inpage-settings-form" onsubmit="return false;">
						
						<!-- Section 1: Patchstack API -->
						<div class="wpsg-settings-card">
							<div class="wpsg-settings-card-header">
								<div class="wpsg-settings-icon wpsg-icon-brand"><span class="dashicons dashicons-shield"></span></div>
								<div>
									<h3><?php esc_html_e( 'Patchstack Vulnerability Intelligence API', 'site-checkup-pro' ); ?></h3>
									<p><?php esc_html_e( 'Live cross-referencing of installed plugins & themes against active CVE vulnerabilities.', 'site-checkup-pro' ); ?></p>
								</div>
							</div>
							<div class="wpsg-settings-card-body">
								<div class="wpsg-input-group">
									<label for="wpsg-page-setting-patchstack-key"><?php esc_html_e( 'Patchstack API Token', 'site-checkup-pro' ); ?></label>
									<input type="password" id="wpsg-page-setting-patchstack-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste API key or leave blank for default local advisories', 'site-checkup-pro' ); ?>" autocomplete="off" />
									<p id="wpsg-page-patchstack-masked-status" class="wpsg-input-hint"></p>
								</div>
								<div class="wpsg-consent-box">
									<label class="wpsg-checkbox-label">
										<input type="checkbox" id="wpsg-page-setting-patchstack-optin" />
										<span>
											<strong><?php esc_html_e( 'Allow outbound queries to Patchstack API (Explicit Consent)', 'site-checkup-pro' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Per WordPress.org Guideline 7, outbound network calls require explicit consent. Transmits installed plugin/theme slugs and versions to Patchstack to check public advisories. No personal data or database records are ever transmitted.', 'site-checkup-pro' ); ?>
											</span>
										</span>
									</label>
								</div>
							</div>
						</div>

						<!-- Section 2: Hosting Control Panel Bridge (Nginx Tier 1) -->
						<div class="wpsg-settings-card">
							<div class="wpsg-settings-card-header">
								<div class="wpsg-settings-icon wpsg-icon-server"><span class="dashicons dashicons-networking"></span></div>
								<div>
									<h3><?php esc_html_e( 'Hosting Control Panel Bridge (Nginx Tier 1 Directives)', 'site-checkup-pro' ); ?></h3>
									<p><?php esc_html_e( 'For sites running on Nginx, route directive applications through your hosting panel official API.', 'site-checkup-pro' ); ?></p>
								</div>
							</div>
							<div class="wpsg-settings-card-body">
								<div id="wpsg-page-panel-detection-info" class="wpsg-detection-pill" style="display: none;"></div>
								<div class="wpsg-form-grid-2">
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-panel-type"><?php esc_html_e( 'Control Panel Type', 'site-checkup-pro' ); ?></label>
										<select id="wpsg-page-setting-panel-type" class="wpsg-select">
											<option value=""><?php esc_html_e( 'None / Not Applicable', 'site-checkup-pro' ); ?></option>
											<option value="cpanel"><?php esc_html_e( 'cPanel (UAPI)', 'site-checkup-pro' ); ?></option>
											<option value="plesk"><?php esc_html_e( 'Plesk (REST API)', 'site-checkup-pro' ); ?></option>
											<option value="cloudpanel"><?php esc_html_e( 'CloudPanel (v2 API)', 'site-checkup-pro' ); ?></option>
											<option value="runcloud"><?php esc_html_e( 'RunCloud (API)', 'site-checkup-pro' ); ?></option>
											<option value="cyberpanel"><?php esc_html_e( 'CyberPanel (REST API)', 'site-checkup-pro' ); ?></option>
										</select>
									</div>
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-panel-url"><?php esc_html_e( 'Panel URL / Port', 'site-checkup-pro' ); ?></label>
										<input type="url" id="wpsg-page-setting-panel-url" class="wpsg-input" placeholder="https://cp.server.com:8443" />
									</div>
								</div>
								<div class="wpsg-input-group">
									<label for="wpsg-page-setting-panel-token"><?php esc_html_e( 'API Token / Secret Key (Stored Encrypted)', 'site-checkup-pro' ); ?></label>
									<input type="password" id="wpsg-page-setting-panel-token" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste panel API token (stored encrypted with HKDF + AES-256-GCM)', 'site-checkup-pro' ); ?>" autocomplete="off" />
									<p id="wpsg-page-panel-masked-status" class="wpsg-input-hint"></p>
								</div>
								<div class="wpsg-consent-box">
									<label class="wpsg-checkbox-label">
										<input type="checkbox" id="wpsg-page-setting-panel-optin" />
										<span>
											<strong><?php esc_html_e( 'Authorize API Directive Application (Explicit Consent)', 'site-checkup-pro' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Authorizes Site Checkup Pro to transmit authenticated Nginx directive configurations to the specified control panel API endpoint. Token is never logged or exported.', 'site-checkup-pro' ); ?>
											</span>
										</span>
									</label>
								</div>
							</div>
						</div>

						<!-- Section 3: Security Alert Webhooks -->
						<div class="wpsg-settings-card">
							<div class="wpsg-settings-card-header">
								<div class="wpsg-settings-icon wpsg-icon-clock"><span class="dashicons dashicons-bell"></span></div>
								<div>
									<h3><?php esc_html_e( 'Security Alert Webhooks', 'site-checkup-pro' ); ?></h3>
									<p><?php esc_html_e( 'Forward real-time security alerts (rogue admin creation, login spikes, PHP uploads execution) to Slack or Discord.', 'site-checkup-pro' ); ?></p>
								</div>
							</div>
							<div class="wpsg-settings-card-body">
								<div class="wpsg-input-group">
									<label for="wpsg-page-setting-webhook-url"><?php esc_html_e( 'Webhook Endpoint URL', 'site-checkup-pro' ); ?></label>
									<input type="url" id="wpsg-page-setting-webhook-url" class="wpsg-input" placeholder="https://hooks.slack.com/services/..." />
								</div>
								<div class="wpsg-consent-box">
									<label class="wpsg-checkbox-label">
										<input type="checkbox" id="wpsg-page-setting-webhook-optin" />
										<span>
											<strong><?php esc_html_e( 'Allow outbound alert dispatch to this webhook (Explicit Consent)', 'site-checkup-pro' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Transmits event summaries, timestamp, and site URL to the specified endpoint. Outbound requests are strictly verified through SSRF guards.', 'site-checkup-pro' ); ?>
											</span>
										</span>
									</label>
								</div>
							</div>
						</div>

						<!-- Section 4: Emergency Incident Contacts & Agency -->
						<div class="wpsg-settings-card">
							<div class="wpsg-settings-card-header">
								<div class="wpsg-settings-icon wpsg-icon-backup"><span class="dashicons dashicons-groups"></span></div>
								<div>
									<h3><?php esc_html_e( 'Incident Escalation & Agency Information', 'site-checkup-pro' ); ?></h3>
									<p><?php esc_html_e( 'Designate emergency contacts for after-hours breaches and configure agency white-labeling.', 'site-checkup-pro' ); ?></p>
								</div>
							</div>
							<div class="wpsg-settings-card-body">
								<div class="wpsg-form-grid-2">
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-incident-name"><?php esc_html_e( 'Emergency Contact Person / Role', 'site-checkup-pro' ); ?></label>
										<input type="text" id="wpsg-page-setting-incident-name" class="wpsg-input" placeholder="e.g. Lead SecOps Engineer" />
									</div>
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-incident-email"><?php esc_html_e( 'Emergency Email', 'site-checkup-pro' ); ?></label>
										<input type="email" id="wpsg-page-setting-incident-email" class="wpsg-input" placeholder="e.g. security@clientsite.com" />
									</div>
								</div>
								<div class="wpsg-form-grid-2">
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-incident-phone"><?php esc_html_e( 'Emergency Phone / Pager', 'site-checkup-pro' ); ?></label>
										<input type="text" id="wpsg-page-setting-incident-phone" class="wpsg-input" placeholder="e.g. +1 (555) 019-2831" />
									</div>
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-agency-name"><?php esc_html_e( 'Agency / Preparer Name (Client Reports)', 'site-checkup-pro' ); ?></label>
										<input type="text" id="wpsg-page-setting-agency-name" class="wpsg-input" placeholder="e.g. Acme Security Services" />
									</div>
								</div>
								<div class="wpsg-input-group">
									<label for="wpsg-page-setting-incident-notes"><?php esc_html_e( 'Incident Protocol & Vault Reference', 'site-checkup-pro' ); ?></label>
									<textarea id="wpsg-page-setting-incident-notes" class="wpsg-textarea" rows="2" placeholder="<?php esc_attr_e( 'e.g. Contact 24/7 hosting desk, access 1Password Emergency Vault for root credentials.', 'site-checkup-pro' ); ?>"></textarea>
								</div>
							</div>
						</div>

						<!-- Settings Actions Bar -->
						<div class="wpsg-settings-footer-bar">
							<span id="wpsg-page-settings-save-status" class="wpsg-save-status"></span>
							<button type="button" class="wpsg-btn wpsg-btn-primary wpsg-btn-lg" id="wpsg-btn-page-save-settings">
								<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save All Settings', 'site-checkup-pro' ); ?>
							</button>
						</div>

					</form>
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
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-confirm.php'; ?>
	<?php include WPSG_PLUGIN_DIR . 'admin/views/modal-diagnostic.php'; ?>

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
