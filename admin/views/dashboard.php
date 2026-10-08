<?php
/**
 * Main Check-up Dashboard View — v2.0
 *
 * Professional sidebar + content panel layout modeled after top WordPress plugins
 * (WooCommerce, Yoast SEO, WP Rocket, Solid Security).
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

$wpsg_registry      = WPSG_Task_Registry::get_instance();
$wpsg_sections      = WPSG_Task_Registry::$sections;
$wpsg_server_type   = WPSG_Htaccess_Manager::get_server_type();
$wpsg_has_htaccess  = WPSG_Htaccess_Manager::supports_htaccess();
$wpsg_backup_status = WPSG_Backup_Guard::get_backup_status();
$wpsg_login_slug    = WPSG_Login_Renamer::get_login_slug();
?>

<div class="wrap wpsg-wrap" id="wpsg-app">

	<!-- Top Product Header -->
	<header class="wpsg-page-header">
		<div class="wpsg-header-title-area">
			<img src="<?php echo esc_url( WPSG_PLUGIN_URL . 'media/genioussonu-site-checkup-icon.svg' ); ?>" alt="GeniousSonu Site Checkup" width="38" height="38" class="wpsg-brand-icon-img" />
			<div class="wpsg-header-titles">
				<h1>
					<?php esc_html_e( 'GeniousSonu Site Checkup', 'genioussonu-site-checkup' ); ?>
					<span class="wpsg-version-tag">v<?php echo esc_html( WPSG_VERSION ); ?></span>
				</h1>
				<p><?php esc_html_e( 'SOP Security Hardening & Audit Orchestrator', 'genioussonu-site-checkup' ); ?></p>
			</div>
		</div>

		<div class="wpsg-header-actions">
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-confirm-backup" title="<?php esc_attr_e( 'Confirm that a host/cPanel backup was verified within 48h', 'genioussonu-site-checkup' ); ?>">
				<span class="dashicons dashicons-cloud"></span> <?php esc_html_e( 'Confirm Backup', 'genioussonu-site-checkup' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-update-baseline" title="<?php esc_attr_e( 'Update trusted administrator and database baseline', 'genioussonu-site-checkup' ); ?>">
				<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Trust Baseline', 'genioussonu-site-checkup' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-open-settings" title="<?php esc_attr_e( 'Configure API keys and incident response contacts', 'genioussonu-site-checkup' ); ?>">
				<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Settings', 'genioussonu-site-checkup' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-batch-run">
				<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run All Safe Tasks', 'genioussonu-site-checkup' ); ?>
			</button>
		</div>
	</header>

	<!-- Live Batch Execution Progress Strip (Hidden by default) -->
	<div class="wpsg-batch-banner" id="wpsg-batch-banner" style="display: none;">
		<div class="wpsg-batch-info">
			<span class="wpsg-spinner" aria-hidden="true"></span>
			<span id="wpsg-batch-text"><?php esc_html_e( 'Processing safe verification tasks...', 'genioussonu-site-checkup' ); ?></span>
			<span class="wpsg-batch-count" id="wpsg-batch-count">0 / 0</span>
		</div>
		<div class="wpsg-batch-progress-bar">
			<div class="wpsg-batch-progress-fill" id="wpsg-batch-progress" style="width: 0%;"></div>
		</div>
		<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-subtle" id="wpsg-batch-cancel"><?php esc_html_e( 'Stop', 'genioussonu-site-checkup' ); ?></button>
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
					<?php esc_html_e( 'Loving GeniousSonu Site Checkup? Help us grow with a 5-star review!', 'genioussonu-site-checkup' ); ?>
				</h3>
				<p style="margin: 0; font-size: 13px; color: var(--wpsg-text-secondary);">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of completed security tasks */
							__( 'You have successfully run %d security hardening and audit checks. Leaving a quick review helps us immensely!', 'genioussonu-site-checkup' ),
							$wpsg_tasks_done
						)
					);
					?>
				</p>
			</div>
		</div>
		<div style="display: flex; align-items: center; gap: 10px; flex-shrink: 0;">
			<a href="https://wordpress.org/support/plugin/genioussonu-site-checkup/reviews/#new-post" target="_blank" rel="noopener noreferrer" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-review-now">
				<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Leave a 5-Star Review', 'genioussonu-site-checkup' ); ?>
			</a>
			<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-review-already">
				<?php esc_html_e( 'I Already Did', 'genioussonu-site-checkup' ); ?>
			</button>
			<button type="button" class="wpsg-btn wpsg-btn-subtle" id="wpsg-btn-review-dismiss" title="<?php esc_attr_e( 'Dismiss permanently', 'genioussonu-site-checkup' ); ?>">
				<?php esc_html_e( 'Maybe Later', 'genioussonu-site-checkup' ); ?>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<!-- Main App Layout: Sidebar Navigation + Content Area -->
	<div class="wpsg-app-layout">

		<!-- Left Sidebar Navigation -->
		<aside class="wpsg-sidebar" aria-label="<?php esc_attr_e( 'GeniousSonu Site Checkup Navigation', 'genioussonu-site-checkup' ); ?>">
			
			<div class="wpsg-sidebar-group">
				<div class="wpsg-sidebar-heading"><?php esc_html_e( 'Dashboard', 'genioussonu-site-checkup' ); ?></div>
				<button type="button" class="wpsg-sidebar-item wpsg-tab active" data-tab="overview">
					<span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Overview', 'genioussonu-site-checkup' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-overview-pct">0%</span>
				</button>
			</div>

			<div class="wpsg-sidebar-group">
				<div class="wpsg-sidebar-heading"><?php esc_html_e( 'Checkup Tasks', 'genioussonu-site-checkup' ); ?></div>
				
				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="security_update">
					<span class="dashicons dashicons-shield" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Security Update', 'genioussonu-site-checkup' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-sec-update">5</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="general_check">
					<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Site Audit', 'genioussonu-site-checkup' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-gen-check">13</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="hardening">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Hardening', 'genioussonu-site-checkup' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-hardening">14</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="advanced_protection">
					<span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Advanced', 'genioussonu-site-checkup' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-adv-prot">13</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="regular_checks">
					<span class="dashicons dashicons-calendar-alt" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Maintenance', 'genioussonu-site-checkup' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-reg-checks">7</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="seo_sop">
					<span class="dashicons dashicons-search" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'SEO SOP', 'genioussonu-site-checkup' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-seo">3</span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="all">
					<span class="dashicons dashicons-list-view" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'All Checks', 'genioussonu-site-checkup' ); ?></span>
					<span class="wpsg-sidebar-badge" id="wpsg-badge-all">55</span>
				</button>
			</div>

			<div class="wpsg-sidebar-group">
				<div class="wpsg-sidebar-heading"><?php esc_html_e( 'Tools & Records', 'genioussonu-site-checkup' ); ?></div>
				
				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="features">
					<span class="dashicons dashicons-admin-tools" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Security Features', 'genioussonu-site-checkup' ); ?></span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="audit_trail">
					<span class="dashicons dashicons-format-aside" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Audit Trail', 'genioussonu-site-checkup' ); ?></span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="rest_api">
					<span class="dashicons dashicons-rest-api" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'REST API', 'genioussonu-site-checkup' ); ?></span>
				</button>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="dev_toolkit">
					<span class="dashicons dashicons-code-standards" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Developer Toolkit', 'genioussonu-site-checkup' ); ?></span>
				</button>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=genioussonu-site-checkup-report' ) ); ?>" class="wpsg-sidebar-item wpsg-sidebar-link">
					<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Client Report', 'genioussonu-site-checkup' ); ?></span>
					<span class="dashicons dashicons-external" style="font-size: 13px; margin-left: auto;"></span>
				</a>

				<button type="button" class="wpsg-sidebar-item wpsg-tab" data-tab="settings">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<span class="wpsg-sidebar-label"><?php esc_html_e( 'Settings', 'genioussonu-site-checkup' ); ?></span>
				</button>
			</div>

			<!-- Sidebar Footer Info -->
			<div class="wpsg-sidebar-info">
				<div class="wpsg-sidebar-env-badge">
					<span class="wpsg-status-dot" style="background: var(--wpsg-success);"></span>
					<span><?php echo esc_html( strtoupper( $wpsg_server_type ) ); ?> &bull; PHP <?php echo esc_html( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ); ?></span>
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
								<?php esc_html_e( 'Real-Time Protection Active', 'genioussonu-site-checkup' ); ?>
							</span>
							<span class="wpsg-posture-env-pill">
								<?php echo esc_html( strtoupper( $wpsg_server_type ) ); ?> &bull; PHP <?php echo esc_html( PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ); ?>
							</span>
						</div>
						<h2 class="wpsg-posture-title"><?php esc_html_e( 'Site Security Hardening & Posture', 'genioussonu-site-checkup' ); ?></h2>
						<p class="wpsg-posture-desc" id="wpsg-posture-stats-text">
							<?php esc_html_e( 'Calculating verified standard operating procedures across all 6 hardening domains...', 'genioussonu-site-checkup' ); ?>
						</p>
						<div class="wpsg-posture-actions">
							<button type="button" class="wpsg-btn wpsg-btn-primary wpsg-btn-hero" id="wpsg-btn-hero-run-safe">
								<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run Safe Verification Tasks', 'genioussonu-site-checkup' ); ?>
							</button>
							<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-hero-view-all">
								<span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Inspect All Checks', 'genioussonu-site-checkup' ); ?>
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
								<h3><?php esc_html_e( 'Priority Action Required', 'genioussonu-site-checkup' ); ?></h3>
								<p><?php esc_html_e( 'These items have failed verification or require immediate administrator attention.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<span class="wpsg-attention-badge" id="wpsg-attention-count">0 items</span>
					</div>
					<div class="wpsg-attention-list" id="wpsg-attention-items">
						<!-- Populated via admin.js -->
					</div>
				</div>

				<!-- 4 High-Impact KPI Metric Cards -->
				<div class="wpsg-kpi-grid" aria-label="<?php esc_attr_e( 'System Status Overview', 'genioussonu-site-checkup' ); ?>">
					
					<!-- KPI 1: SOP Coverage -->
					<div class="wpsg-kpi-card">
						<div class="wpsg-kpi-card-header">
							<span class="wpsg-kpi-card-title"><?php esc_html_e( 'SOP Checklist Coverage', 'genioussonu-site-checkup' ); ?></span>
							<div class="wpsg-kpi-icon-wrap wpsg-icon-brand">
								<span class="dashicons dashicons-chart-pie"></span>
							</div>
						</div>
						<div class="wpsg-kpi-card-body">
							<div class="wpsg-kpi-card-metric" id="wpsg-kpi-coverage">0%</div>
							<p class="wpsg-kpi-card-meta" id="wpsg-kpi-fraction"><?php esc_html_e( '0 of 0 tasks active', 'genioussonu-site-checkup' ); ?></p>
							<div class="wpsg-kpi-progress-track">
								<div class="wpsg-kpi-progress-fill" id="wpsg-coverage-bar" style="width: 0%;"></div>
							</div>
						</div>
						<div class="wpsg-kpi-card-footer">
							<span class="wpsg-kpi-footnote"><?php esc_html_e( 'Factual SOP verification', 'genioussonu-site-checkup' ); ?></span>
						</div>
					</div>

					<!-- KPI 2: Server Environment & Directives -->
					<div class="wpsg-kpi-card">
						<div class="wpsg-kpi-card-header">
							<span class="wpsg-kpi-card-title"><?php esc_html_e( 'Web Server Architecture', 'genioussonu-site-checkup' ); ?></span>
							<div class="wpsg-kpi-icon-wrap wpsg-icon-server">
								<span class="dashicons dashicons-networking"></span>
							</div>
						</div>
						<div class="wpsg-kpi-card-body">
							<div class="wpsg-kpi-card-metric" id="wpsg-kpi-server"><?php echo esc_html( strtoupper( $wpsg_server_type ) ); ?></div>
							<p class="wpsg-kpi-card-meta">
								<?php if ( $wpsg_has_htaccess ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done">
										<span class="wpsg-status-dot"></span> <?php esc_html_e( '.htaccess rules supported', 'genioussonu-site-checkup' ); ?>
									</span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention" id="wpsg-kpi-nginx-tier-indicator">
										<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Nginx Directives Mode', 'genioussonu-site-checkup' ); ?>
									</span>
								<?php endif; ?>
							</p>
						</div>
						<div class="wpsg-kpi-card-footer">
							<span class="wpsg-kpi-footnote">
								<?php
								/* translators: %s: PHP version */
								printf( esc_html__( 'PHP %s engine', 'genioussonu-site-checkup' ), esc_html( PHP_VERSION ) );
								?>
							</span>
						</div>
					</div>

					<!-- KPI 3: Backup Safety Gate -->
					<div class="wpsg-kpi-card">
						<div class="wpsg-kpi-card-header">
							<span class="wpsg-kpi-card-title"><?php esc_html_e( 'Backup Safety Gate', 'genioussonu-site-checkup' ); ?></span>
							<div class="wpsg-kpi-icon-wrap wpsg-icon-backup">
								<span class="dashicons dashicons-backup"></span>
							</div>
						</div>
						<div class="wpsg-kpi-card-body">
							<div class="wpsg-kpi-card-metric" id="wpsg-kpi-backup-name">
								<?php echo ! empty( $wpsg_backup_status['plugin_name'] ) ? esc_html( $wpsg_backup_status['plugin_name'] ) : esc_html__( 'Unverified', 'genioussonu-site-checkup' ); ?>
							</div>
							<p class="wpsg-kpi-card-meta">
								<?php if ( ! empty( $wpsg_backup_status['is_recent'] ) ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done">
										<span class="wpsg-status-dot"></span>
										<?php
										/* translators: %s: backup age in hours */
										printf( esc_html__( 'Verified (%s hrs ago)', 'genioussonu-site-checkup' ), esc_html( $wpsg_backup_status['age_hours'] ) );
										?>
									</span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention">
										<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Backup > 48h required', 'genioussonu-site-checkup' ); ?>
									</span>
								<?php endif; ?>
							</p>
						</div>
						<div class="wpsg-kpi-card-footer">
							<span class="wpsg-kpi-footnote"><?php esc_html_e( 'Protects all file-writing tasks', 'genioussonu-site-checkup' ); ?></span>
						</div>
					</div>

					<!-- KPI 4: Active Defenses & Reminders -->
					<div class="wpsg-kpi-card">
						<div class="wpsg-kpi-card-header">
							<span class="wpsg-kpi-card-title"><?php esc_html_e( 'Cadence & Defense', 'genioussonu-site-checkup' ); ?></span>
							<div class="wpsg-kpi-icon-wrap wpsg-icon-clock">
								<span class="dashicons dashicons-shield"></span>
							</div>
						</div>
						<div class="wpsg-kpi-card-body">
							<div class="wpsg-kpi-card-metric" id="wpsg-kpi-reminders">0</div>
							<p class="wpsg-kpi-card-meta">
								<?php if ( ! empty( $wpsg_login_slug ) ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done">
										<span class="wpsg-status-dot"></span>
										<?php
										/* translators: %s: custom login slug */
										printf( esc_html__( 'Custom Login (/%s/)', 'genioussonu-site-checkup' ), esc_html( $wpsg_login_slug ) );
										?>
									</span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention">
										<span class="wpsg-status-dot"></span> <?php esc_html_e( 'Default Login Active', 'genioussonu-site-checkup' ); ?>
									</span>
								<?php endif; ?>
							</p>
						</div>
						<div class="wpsg-kpi-card-footer">
							<span class="wpsg-kpi-footnote"><?php esc_html_e( '15-day reviews & lockouts active', 'genioussonu-site-checkup' ); ?></span>
						</div>
					</div>

				</div>

				<!-- Section Cards Grid: Clear, organized category navigation -->
				<div class="wpsg-section-header-block">
					<div>
						<h2 class="wpsg-section-heading"><?php esc_html_e( 'Checkup Categories', 'genioussonu-site-checkup' ); ?></h2>
						<p class="wpsg-section-subheading"><?php esc_html_e( 'Standard Operating Procedures structured by domain for complete site hardening.', 'genioussonu-site-checkup' ); ?></p>
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
								<h3><?php esc_html_e( 'Security Update', 'genioussonu-site-checkup' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-security_update">5 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Core security patches, automatic update policies, and foundational protection.', 'genioussonu-site-checkup' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-security_update" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-security_update">0 / 5 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="security_update">
								<?php esc_html_e( 'View Checks &rarr;', 'genioussonu-site-checkup' ); ?>
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
								<h3><?php esc_html_e( 'Site Audit', 'genioussonu-site-checkup' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-general_check">13 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Environment audit, rogue admin detection, database integrity, and file scans.', 'genioussonu-site-checkup' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-general_check" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-general_check">0 / 13 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="general_check">
								<?php esc_html_e( 'View Checks &rarr;', 'genioussonu-site-checkup' ); ?>
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
								<h3><?php esc_html_e( 'Hardening', 'genioussonu-site-checkup' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-hardening">14 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Server security headers, .htaccess protection, wp-config restrictions, and login security.', 'genioussonu-site-checkup' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-hardening" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-hardening">0 / 14 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="hardening">
								<?php esc_html_e( 'View Checks &rarr;', 'genioussonu-site-checkup' ); ?>
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
								<h3><?php esc_html_e( 'Advanced Protection', 'genioussonu-site-checkup' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-advanced_protection">13 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Login throttling, user enumeration defense, session security, and runtime hardening.', 'genioussonu-site-checkup' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-advanced_protection" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-advanced_protection">0 / 13 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="advanced_protection">
								<?php esc_html_e( 'View Checks &rarr;', 'genioussonu-site-checkup' ); ?>
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
								<h3><?php esc_html_e( 'Maintenance', 'genioussonu-site-checkup' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-regular_checks">7 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Recurring 15-day credential rotations, vault tracking, and staging site protection.', 'genioussonu-site-checkup' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-regular_checks" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-regular_checks">0 / 7 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="regular_checks">
								<?php esc_html_e( 'View Checks &rarr;', 'genioussonu-site-checkup' ); ?>
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
								<h3><?php esc_html_e( 'SEO SOP', 'genioussonu-site-checkup' ); ?></h3>
								<span class="wpsg-cat-badge" id="wpsg-cat-stat-seo_sop">3 checks</span>
							</div>
						</div>
						<p class="wpsg-cat-desc"><?php esc_html_e( 'Search console indexing integrity, robots.txt audit, and URL removal tracking.', 'genioussonu-site-checkup' ); ?></p>
						<div class="wpsg-cat-progress">
							<div class="wpsg-cat-progress-fill" id="wpsg-cat-bar-seo_sop" style="width: 0%;"></div>
						</div>
						<div class="wpsg-cat-footer">
							<span class="wpsg-cat-status-text" id="wpsg-cat-txt-seo_sop">0 / 3 Completed</span>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-cat-open-btn" data-open-tab="seo_sop">
								<?php esc_html_e( 'View Checks &rarr;', 'genioussonu-site-checkup' ); ?>
							</button>
						</div>
					</div>

				</div>

				<!-- Quick Security Tools Strip -->
				<div class="wpsg-section-header-block" style="margin-top: 32px;">
					<div>
						<h2 class="wpsg-section-heading"><?php esc_html_e( 'Integrated Security Tools', 'genioussonu-site-checkup' ); ?></h2>
						<p class="wpsg-section-subheading"><?php esc_html_e( 'Key defensive mechanisms built directly into GeniousSonu Site Checkup.', 'genioussonu-site-checkup' ); ?></p>
					</div>
				</div>

				<div class="wpsg-tools-preview-grid">
					
					<div class="wpsg-tool-preview-card">
						<div class="wpsg-tool-preview-icon"><span class="dashicons dashicons-admin-network"></span></div>
						<div class="wpsg-tool-preview-body">
							<h4><?php esc_html_e( 'Custom Login URL', 'genioussonu-site-checkup' ); ?></h4>
							<p>
								<?php if ( ! empty( $wpsg_login_slug ) ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done"><span class="wpsg-status-dot"></span> <?php /* translators: %s: custom login slug */ printf( esc_html__( 'Active (/%s/)', 'genioussonu-site-checkup' ), esc_html( $wpsg_login_slug ) ); ?></span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Default /wp-login.php', 'genioussonu-site-checkup' ); ?></span>
								<?php endif; ?>
							</p>
						</div>
						<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-quick-login-url"><?php esc_html_e( 'Configure', 'genioussonu-site-checkup' ); ?></button>
					</div>

					<div class="wpsg-tool-preview-card">
						<div class="wpsg-tool-preview-icon"><span class="dashicons dashicons-groups"></span></div>
						<div class="wpsg-tool-preview-body">
							<h4><?php esc_html_e( 'User Sessions', 'genioussonu-site-checkup' ); ?></h4>
							<p><?php esc_html_e( 'Audit and destroy compromised user sessions across devices.', 'genioussonu-site-checkup' ); ?></p>
						</div>
						<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-quick-sessions"><?php esc_html_e( 'Manage', 'genioussonu-site-checkup' ); ?></button>
					</div>

					<div class="wpsg-tool-preview-card">
						<div class="wpsg-tool-preview-icon"><span class="dashicons dashicons-media-document"></span></div>
						<div class="wpsg-tool-preview-body">
							<h4><?php esc_html_e( 'Client Security Report', 'genioussonu-site-checkup' ); ?></h4>
							<p><?php esc_html_e( 'Generate white-labeled executive PDF reports for clients.', 'genioussonu-site-checkup' ); ?></p>
						</div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=genioussonu-site-checkup-report' ) ); ?>" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary"><?php esc_html_e( 'View Report', 'genioussonu-site-checkup' ); ?></a>
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
								<h2 id="wpsg-current-sec-title"><?php esc_html_e( 'Security Checks', 'genioussonu-site-checkup' ); ?></h2>
								<span class="wpsg-section-banner-badge" id="wpsg-current-sec-badge">0 checks</span>
							</div>
							<p id="wpsg-current-sec-desc"><?php esc_html_e( 'Standard operating procedures for site hardening.', 'genioussonu-site-checkup' ); ?></p>
						</div>
					</div>
					<div class="wpsg-section-banner-actions">
						<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-run-section">
							<span class="dashicons dashicons-controls-play"></span> <?php esc_html_e( 'Run Safe Checks in Section', 'genioussonu-site-checkup' ); ?>
						</button>
					</div>
				</div>

				<!-- Toolbar & Filters -->
				<div class="wpsg-toolbar">
					<div class="wpsg-search-box">
						<span class="dashicons dashicons-search" aria-hidden="true"></span>
						<input type="text" id="wpsg-search-tasks" class="wpsg-search-input" placeholder="<?php esc_attr_e( 'Search checks in this section...', 'genioussonu-site-checkup' ); ?>" />
					</div>

					<div class="wpsg-filter-group">
						<select id="wpsg-filter-level" class="wpsg-select" aria-label="<?php esc_attr_e( 'Filter by automation level', 'genioussonu-site-checkup' ); ?>">
							<option value=""><?php esc_html_e( 'All Levels', 'genioussonu-site-checkup' ); ?></option>
							<option value="A_instant"><?php esc_html_e( 'Level A: Safe / Instant', 'genioussonu-site-checkup' ); ?></option>
							<option value="A_files"><?php esc_html_e( 'Level A: Config / File Writers', 'genioussonu-site-checkup' ); ?></option>
							<option value="B"><?php esc_html_e( 'Level B: Guided Actions', 'genioussonu-site-checkup' ); ?></option>
							<option value="C"><?php esc_html_e( 'Level C: Manual / Reminders', 'genioussonu-site-checkup' ); ?></option>
						</select>

						<select id="wpsg-filter-status" class="wpsg-select" aria-label="<?php esc_attr_e( 'Filter by status', 'genioussonu-site-checkup' ); ?>">
							<option value=""><?php esc_html_e( 'All Statuses', 'genioussonu-site-checkup' ); ?></option>
							<option value="pending"><?php esc_html_e( 'Pending', 'genioussonu-site-checkup' ); ?></option>
							<option value="done"><?php esc_html_e( 'Completed', 'genioussonu-site-checkup' ); ?></option>
							<option value="applied_unverified"><?php esc_html_e( 'Applied (Unverified)', 'genioussonu-site-checkup' ); ?></option>
							<option value="attention"><?php esc_html_e( 'Action Needed', 'genioussonu-site-checkup' ); ?></option>
							<option value="failed"><?php esc_html_e( 'Critical / Failed', 'genioussonu-site-checkup' ); ?></option>
							<option value="not_applicable"><?php esc_html_e( 'Not Applicable', 'genioussonu-site-checkup' ); ?></option>
						</select>
					</div>
				</div>

				<!-- Main Tasks Table Container -->
				<main class="wpsg-table-container" id="wpsg-tasks-table-wrapper">
					<table class="wpsg-table" id="wpsg-tasks-table">
						<thead>
							<tr>
								<th class="wpsg-col-status"><?php esc_html_e( 'Status', 'genioussonu-site-checkup' ); ?></th>
								<th class="wpsg-col-level"><?php esc_html_e( 'Level', 'genioussonu-site-checkup' ); ?></th>
								<th class="wpsg-col-task"><?php esc_html_e( 'Check / Procedure', 'genioussonu-site-checkup' ); ?></th>
								<th class="wpsg-col-checked"><?php esc_html_e( 'Last Verified', 'genioussonu-site-checkup' ); ?></th>
								<th class="wpsg-col-actions"><?php esc_html_e( 'Action', 'genioussonu-site-checkup' ); ?></th>
							</tr>
						</thead>
						<tbody id="wpsg-tasks-tbody">
							<tr>
								<td colspan="5" style="text-align: center; padding: 40px;">
									<span class="wpsg-spinner" aria-hidden="true"></span>
									<p style="margin: 8px 0 0 0; color: var(--wpsg-text-secondary);"><?php esc_html_e( 'Loading checklist tasks and live status...', 'genioussonu-site-checkup' ); ?></p>
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
								<h2><?php esc_html_e( 'Security Features & Controls', 'genioussonu-site-checkup' ); ?></h2>
								<span class="wpsg-section-banner-badge"><?php esc_html_e( 'Active Modules', 'genioussonu-site-checkup' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Manage advanced built-in security features, custom endpoints, sessions, and protections.', 'genioussonu-site-checkup' ); ?></p>
						</div>
					</div>
				</div>

				<div class="wpsg-features-cards-container">
					
					<!-- Feature 1: Rename Login URL -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-admin-network"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Custom Login URL (Hide /wp-admin & /wp-login.php)', 'genioussonu-site-checkup' ); ?></h3>
								<p><?php esc_html_e( 'Protects against brute force attacks by hiding the default login endpoint and blocking /wp-admin probes with an unguessable 404.', 'genioussonu-site-checkup' ); ?></p>
							</div>
							<div class="wpsg-feature-card-status">
								<?php if ( ! empty( $wpsg_login_slug ) ) : ?>
									<span class="wpsg-status-indicator wpsg-status-done"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Active', 'genioussonu-site-checkup' ); ?></span>
								<?php else : ?>
									<span class="wpsg-status-indicator wpsg-status-attention"><span class="wpsg-status-dot"></span> <?php esc_html_e( 'Default', 'genioussonu-site-checkup' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<div class="wpsg-feature-form-row">
								<label for="wpsg-feat-login-slug"><?php esc_html_e( 'Login URL Path:', 'genioussonu-site-checkup' ); ?></label>
								<div class="wpsg-input-prefix-wrap">
									<span class="wpsg-input-prefix"><?php echo esc_html( home_url( '/' ) ); ?></span>
									<input type="text" id="wpsg-feat-login-slug" class="wpsg-input" value="<?php echo esc_attr( $wpsg_login_slug ); ?>" placeholder="secret-login" />
									<span class="wpsg-input-suffix">/</span>
								</div>
								<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-save-feature-login"><?php esc_html_e( 'Save URL', 'genioussonu-site-checkup' ); ?></button>
								<?php if ( ! empty( $wpsg_login_slug ) ) : ?>
									<button type="button" class="wpsg-btn wpsg-btn-subtle" id="wpsg-btn-reset-feature-login"><?php esc_html_e( 'Revert to Default', 'genioussonu-site-checkup' ); ?></button>
									<a href="<?php echo esc_url( home_url( '/' . $wpsg_login_slug . '/' ) ); ?>" target="_blank" class="wpsg-btn wpsg-btn-secondary">
										<span class="dashicons dashicons-external"></span> <?php esc_html_e( 'Test Login URL', 'genioussonu-site-checkup' ); ?>
									</a>
								<?php endif; ?>
							</div>
							<div class="wpsg-feature-notice">
								<span class="dashicons dashicons-info"></span>
								<span>
									<strong><?php esc_html_e( 'Lockout Recovery Guard:', 'genioussonu-site-checkup' ); ?></strong>
									<?php esc_html_e( 'If you ever forget your custom slug, add ', 'genioussonu-site-checkup' ); ?>
									<code>define( 'WPSG_DISABLE_LOGIN_RENAME', true );</code>
									<?php esc_html_e( ' to your wp-config.php to immediately restore the standard /wp-login.php.', 'genioussonu-site-checkup' ); ?>
								</span>
							</div>
						</div>
					</div>

					<!-- Feature 2: Active User Sessions -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-groups"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Active User Sessions Management', 'genioussonu-site-checkup' ); ?></h3>
								<p><?php esc_html_e( 'Track all currently logged-in administrator and user sessions with client IP and browser device fingerprint.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<p style="margin: 0 0 14px; font-size: 13px; color: var(--wpsg-text-secondary);">
								<?php esc_html_e( 'Review active sessions across multiple devices and instantly terminate unauthorized sessions if suspicious activity is detected.', 'genioussonu-site-checkup' ); ?>
							</p>
							<div style="display: flex; gap: 10px; flex-wrap: wrap;">
								<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-feat-view-sessions">
									<span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Inspect Active Sessions', 'genioussonu-site-checkup' ); ?>
								</button>
								<button type="button" class="wpsg-btn wpsg-btn-subtle" id="wpsg-btn-feat-destroy-sessions" style="color: var(--wpsg-critical);">
									<span class="dashicons dashicons-no-alt"></span> <?php esc_html_e( 'Log Out All Other Sessions', 'genioussonu-site-checkup' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Feature 3: Application Passwords Lockdown -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-key"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Application Passwords Lockdown', 'genioussonu-site-checkup' ); ?></h3>
								<p><?php esc_html_e( 'Restrict REST API application passwords to administrators only, preventing lower-privileged accounts from creating bypass keys.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<div style="display: flex; gap: 10px; align-items: center;">
								<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-feat-app-passwords">
									<span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Manage Application Passwords', 'genioussonu-site-checkup' ); ?>
								</button>
							</div>
						</div>
					</div>

					<!-- Feature 4: CSP Violation Reports Stream -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-bell"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Content Security Policy (CSP) Violations', 'genioussonu-site-checkup' ); ?></h3>
								<p><?php esc_html_e( 'Real-time telemetry listening endpoint for browser CSP violation reports to detect XSS and injected scripts.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-feat-csp-reports">
								<span class="dashicons dashicons-analytics"></span> <?php esc_html_e( 'View CSP Reports Feed', 'genioussonu-site-checkup' ); ?>
							</button>
						</div>
					</div>

					<!-- Feature 5: System & Admin Baseline -->
					<div class="wpsg-feature-card">
						<div class="wpsg-feature-card-header">
							<div class="wpsg-feature-card-icon"><span class="dashicons dashicons-saved"></span></div>
							<div class="wpsg-feature-card-titles">
								<h3><?php esc_html_e( 'Administrator & Database Baseline', 'genioussonu-site-checkup' ); ?></h3>
								<p><?php esc_html_e( 'Cryptographic anchor storing authorized administrator user IDs and database configuration to alert on rogue account creation.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-feature-card-body">
							<button type="button" class="wpsg-btn wpsg-btn-secondary" id="wpsg-btn-feat-update-baseline">
								<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Update & Trust Baseline Now', 'genioussonu-site-checkup' ); ?>
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
								<h2><?php esc_html_e( 'Security Audit Trail', 'genioussonu-site-checkup' ); ?></h2>
								<span class="wpsg-section-banner-badge"><?php esc_html_e( 'Immutable Log', 'genioussonu-site-checkup' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Chronological record of automated hardening procedures, configuration changes, and check executions.', 'genioussonu-site-checkup' ); ?></p>
						</div>
					</div>
				</div>

				<div class="wpsg-table-container">
					<table class="wpsg-table" id="wpsg-audit-table">
						<thead>
							<tr>
								<th class="wpsg-audit-col-time"><?php esc_html_e( 'Timestamp', 'genioussonu-site-checkup' ); ?></th>
								<th class="wpsg-audit-col-task"><?php esc_html_e( 'Task ID', 'genioussonu-site-checkup' ); ?></th>
								<th class="wpsg-audit-col-action"><?php esc_html_e( 'Action', 'genioussonu-site-checkup' ); ?></th>
								<th class="wpsg-audit-col-user"><?php esc_html_e( 'User', 'genioussonu-site-checkup' ); ?></th>
								<th class="wpsg-audit-col-result"><?php esc_html_e( 'Result', 'genioussonu-site-checkup' ); ?></th>
								<th><?php esc_html_e( 'Technical Log Message (Redacted)', 'genioussonu-site-checkup' ); ?></th>
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
								<h2><?php esc_html_e( 'REST API Security Auditor', 'genioussonu-site-checkup' ); ?></h2>
								<span class="wpsg-section-banner-badge"><?php esc_html_e( 'Endpoint Inspector', 'genioussonu-site-checkup' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Complete discovery and authorization inspection of all registered REST endpoints across core, active plugins, and themes.', 'genioussonu-site-checkup' ); ?></p>
						</div>
					</div>
					<div class="wpsg-section-banner-actions">
						<button type="button" class="wpsg-btn wpsg-btn-primary" id="wpsg-btn-refresh-rest-audit">
							<span class="dashicons dashicons-update" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
							<?php esc_html_e( 'Re-scan REST Routes', 'genioussonu-site-checkup' ); ?>
						</button>
					</div>
				</div>

				<!-- REST Stats Summary -->
				<div class="wpsg-overview-grid" style="margin-bottom: 20px;">
					<div class="wpsg-category-card">
						<div class="wpsg-category-header">
							<div>
								<div class="wpsg-category-title"><?php esc_html_e( 'Total Endpoints', 'genioussonu-site-checkup' ); ?></div>
								<div class="wpsg-category-subtitle"><?php esc_html_e( 'Across all routes', 'genioussonu-site-checkup' ); ?></div>
							</div>
							<div class="wpsg-category-stat" id="wpsg-rest-stat-total">0</div>
						</div>
					</div>
					<div class="wpsg-category-card">
						<div class="wpsg-category-header">
							<div>
								<div class="wpsg-category-title"><?php esc_html_e( 'Protected Endpoints', 'genioussonu-site-checkup' ); ?></div>
								<div class="wpsg-category-subtitle"><?php esc_html_e( 'Capability gated', 'genioussonu-site-checkup' ); ?></div>
							</div>
							<div class="wpsg-category-stat" style="color: var(--wpsg-success);" id="wpsg-rest-stat-protected">0</div>
						</div>
					</div>
					<div class="wpsg-category-card">
						<div class="wpsg-category-header">
							<div>
								<div class="wpsg-category-title"><?php esc_html_e( 'Publicly Accessible', 'genioussonu-site-checkup' ); ?></div>
								<div class="wpsg-category-subtitle"><?php esc_html_e( 'Open to unauthenticated visitors', 'genioussonu-site-checkup' ); ?></div>
							</div>
							<div class="wpsg-category-stat" style="color: var(--wpsg-warning);" id="wpsg-rest-stat-public">0</div>
						</div>
					</div>
					<div class="wpsg-category-card">
						<div class="wpsg-category-header">
							<div>
								<div class="wpsg-category-title"><?php esc_html_e( 'High Risk Write Endpoints', 'genioussonu-site-checkup' ); ?></div>
								<div class="wpsg-category-subtitle"><?php esc_html_e( 'Public POST/PUT/DELETE', 'genioussonu-site-checkup' ); ?></div>
							</div>
							<div class="wpsg-category-stat" style="color: var(--wpsg-danger);" id="wpsg-rest-stat-high">0</div>
						</div>
					</div>
				</div>

				<!-- REST Filter Controls -->
				<div class="wpsg-filter-bar" style="margin-bottom: 16px; display: flex; gap: 12px; align-items: center; justify-content: space-between;">
					<div class="wpsg-filter-group" style="display: flex; gap: 6px;">
						<button type="button" class="wpsg-filter-btn active" data-rest-filter="all"><?php esc_html_e( 'All', 'genioussonu-site-checkup' ); ?></button>
						<button type="button" class="wpsg-filter-btn" data-rest-filter="public"><?php esc_html_e( 'Public', 'genioussonu-site-checkup' ); ?></button>
						<button type="button" class="wpsg-filter-btn" data-rest-filter="protected"><?php esc_html_e( 'Protected', 'genioussonu-site-checkup' ); ?></button>
						<button type="button" class="wpsg-filter-btn" data-rest-filter="needs_review"><?php esc_html_e( 'Needs Review', 'genioussonu-site-checkup' ); ?></button>
						<button type="button" class="wpsg-filter-btn" data-rest-filter="critical"><?php esc_html_e( 'High Risk', 'genioussonu-site-checkup' ); ?></button>
					</div>
					<div class="wpsg-search-box" style="max-width: 280px; width: 100%;">
						<input type="search" id="wpsg-rest-search" class="wpsg-input" placeholder="<?php esc_attr_e( 'Search routes or plugins...', 'genioussonu-site-checkup' ); ?>">
					</div>
				</div>

				<div class="wpsg-table-container">
					<table class="wpsg-table" id="wpsg-rest-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Route Pattern', 'genioussonu-site-checkup' ); ?></th>
								<th><?php esc_html_e( 'Method(s)', 'genioussonu-site-checkup' ); ?></th>
								<th><?php esc_html_e( 'Origin / Namespace', 'genioussonu-site-checkup' ); ?></th>
								<th><?php esc_html_e( 'Permission Callback', 'genioussonu-site-checkup' ); ?></th>
								<th><?php esc_html_e( 'Risk Level', 'genioussonu-site-checkup' ); ?></th>
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
								<h2><?php esc_html_e( 'Developer Toolkit & Diagnostics', 'genioussonu-site-checkup' ); ?></h2>
								<span class="wpsg-section-banner-badge"><?php esc_html_e( 'v1.2 Tools', 'genioussonu-site-checkup' ); ?></span>
							</div>
							<p><?php esc_html_e( 'Professional tools for WordPress engineers: environment tagging, sanitized system diagnostic snapshots, WP-Cron inspection, database bloat cleanup, and migration verification.', 'genioussonu-site-checkup' ); ?></p>
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
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Environment Badge Tagging', 'genioussonu-site-checkup' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Active top admin bar safety badge indicating server environment.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div style="display: flex; gap: 10px; align-items: center; margin-bottom: 12px;">
								<select id="wpsg-dev-env-select" class="wpsg-select" style="max-width: 200px;">
									<option value="production"><?php esc_html_e( 'Production (Red)', 'genioussonu-site-checkup' ); ?></option>
									<option value="staging"><?php esc_html_e( 'Staging (Amber)', 'genioussonu-site-checkup' ); ?></option>
									<option value="development"><?php esc_html_e( 'Development (Gray)', 'genioussonu-site-checkup' ); ?></option>
								</select>
								<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary" id="wpsg-btn-save-env"><?php esc_html_e( 'Update Badge', 'genioussonu-site-checkup' ); ?></button>
							</div>
							<p class="wpsg-help-text" id="wpsg-dev-env-help"><?php esc_html_e( 'Smart heuristics inspect your hostname and constants to protect production sites.', 'genioussonu-site-checkup' ); ?></p>
						</div>
					</div>

					<!-- Toolkit 2: Diagnostic Snapshot -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--wpsg-primary);">
								<span class="dashicons dashicons-clipboard"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Diagnostic Snapshot Export', 'genioussonu-site-checkup' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Sanitized Markdown report ready for GitHub issues and tickets.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div style="display: flex; gap: 8px; margin-bottom: 10px;">
								<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary" id="wpsg-btn-copy-diagnostic">
									<span class="dashicons dashicons-admin-page" style="font-size: 14px; width: 14px; height: 14px; margin-right: 4px;"></span>
									<?php esc_html_e( 'Copy Markdown to Clipboard', 'genioussonu-site-checkup' ); ?>
								</button>
								<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-view-diagnostic"><?php esc_html_e( 'View Snapshot', 'genioussonu-site-checkup' ); ?></button>
							</div>
							<p class="wpsg-help-text"><?php esc_html_e( 'All database passwords, salts, and secret credentials are strictly redacted.', 'genioussonu-site-checkup' ); ?></p>
						</div>
					</div>

					<!-- Toolkit 3: WP-Cron Scheduled Events -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--wpsg-warning);">
								<span class="dashicons dashicons-calendar-alt"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'WP-Cron Health Auditor', 'genioussonu-site-checkup' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Detects overdue background tasks and duplicate hooks.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div id="wpsg-dev-cron-summary" style="font-size: 13px; color: var(--wpsg-text-secondary); margin-bottom: 10px;">
								<?php esc_html_e( 'Loading scheduled cron events...', 'genioussonu-site-checkup' ); ?>
							</div>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-refresh-cron"><?php esc_html_e( 'Audit Cron Jobs', 'genioussonu-site-checkup' ); ?></button>
						</div>
					</div>

					<!-- Toolkit 4: Database Health & Bloat -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(239, 68, 68, 0.1); color: var(--wpsg-danger);">
								<span class="dashicons dashicons-database"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Database Bloat & Cleanup', 'genioussonu-site-checkup' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Orphaned meta, expired transients, and excess revisions.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div id="wpsg-dev-db-summary" style="font-size: 13px; color: var(--wpsg-text-secondary); margin-bottom: 10px;">
								<?php esc_html_e( 'Loading database health metrics...', 'genioussonu-site-checkup' ); ?>
							</div>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-danger" id="wpsg-btn-clean-db"><?php esc_html_e( 'Clean Up Bloat (Level B)', 'genioussonu-site-checkup' ); ?></button>
						</div>
					</div>

					<!-- Toolkit 5: Migration Readiness -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(139, 92, 246, 0.1); color: #8b5cf6;">
								<span class="dashicons dashicons-migrate"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Migration Serialization Readiness', 'genioussonu-site-checkup' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Detects absolute URLs inside serialized PHP objects.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div id="wpsg-dev-migration-summary" style="font-size: 13px; color: var(--wpsg-text-secondary); margin-bottom: 10px;">
								<?php esc_html_e( 'Scanning for serialized URL hazards...', 'genioussonu-site-checkup' ); ?>
							</div>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-check-migration"><?php esc_html_e( 'Re-scan Migration Risk', 'genioussonu-site-checkup' ); ?></button>
						</div>
					</div>

					<!-- Toolkit 6: Weekly Changelog Digest -->
					<div class="wpsg-settings-card">
						<div class="wpsg-settings-card-header">
							<div class="wpsg-settings-card-icon" style="background: rgba(14, 165, 233, 0.1); color: #0ea5e9;">
								<span class="dashicons dashicons-rss"></span>
							</div>
							<div>
								<h3 class="wpsg-settings-card-title"><?php esc_html_e( 'Weekly Changelog Digest', 'genioussonu-site-checkup' ); ?></h3>
								<p class="wpsg-settings-card-desc"><?php esc_html_e( 'Intelligence summaries for available updates.', 'genioussonu-site-checkup' ); ?></p>
							</div>
						</div>
						<div class="wpsg-settings-card-body" style="padding-top: 14px;">
							<div id="wpsg-dev-changelog-summary" style="font-size: 13px; color: var(--wpsg-text-secondary); margin-bottom: 10px;">
								<?php esc_html_e( 'Aggregating update transients...', 'genioussonu-site-checkup' ); ?>
							</div>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-refresh-digest"><?php esc_html_e( 'Refresh Digest', 'genioussonu-site-checkup' ); ?></button>
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
								<h2><?php esc_html_e( 'Plugin Settings & Alert Channels', 'genioussonu-site-checkup' ); ?></h2>
							</div>
							<p><?php esc_html_e( 'Configure security incident notifications, third-party intelligence API keys, and audit retention.', 'genioussonu-site-checkup' ); ?></p>
						</div>
					</div>
				</div>

				<div class="wpsg-settings-inpage-container">
					<form id="wpsg-inpage-settings-form" onsubmit="return false;">
						
						<!-- Section 1: Patchstack API -->
						<!-- Section 1: Vulnerability Intelligence Sources -->
						<div class="wpsg-settings-card">
							<div class="wpsg-settings-card-header">
								<div class="wpsg-settings-icon wpsg-icon-brand"><span class="dashicons dashicons-shield"></span></div>
								<div>
									<h3><?php esc_html_e( 'Multi-Source Vulnerability Intelligence', 'genioussonu-site-checkup' ); ?></h3>
									<p><?php esc_html_e( 'Continuous cross-referencing against public security advisories with multi-source evidence chains.', 'genioussonu-site-checkup' ); ?></p>
								</div>
							</div>
							<div class="wpsg-settings-card-body">
								<!-- GitHub Security Advisories -->
								<div style="margin-bottom: 16px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
									<label class="wpsg-checkbox-label" style="margin-bottom: 8px;">
										<input type="checkbox" id="wpsg-page-setting-ghsa-optin" />
										<span>
											<strong><?php esc_html_e( 'GitHub Security Advisories (GHSA)', 'genioussonu-site-checkup' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Per WordPress.org Guideline 7, outbound queries require explicit consent. Checks api.github.com for WordPress advisory records.', 'genioussonu-site-checkup' ); ?>
											</span>
										</span>
									</label>
									<div class="wpsg-input-group" style="padding-left: 24px;">
										<label for="wpsg-page-setting-ghsa-key"><?php esc_html_e( 'GitHub Personal Access Token (Optional)', 'genioussonu-site-checkup' ); ?></label>
										<input type="password" id="wpsg-page-setting-ghsa-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste token (stored encrypted with HKDF)', 'genioussonu-site-checkup' ); ?>" autocomplete="off" />
										<p id="wpsg-page-ghsa-masked-status" class="wpsg-input-hint"></p>
									</div>
								</div>

								<!-- OSV -->
								<div style="margin-bottom: 16px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
									<label class="wpsg-checkbox-label" style="margin-bottom: 8px;">
										<input type="checkbox" id="wpsg-page-setting-osv-optin" />
										<span>
											<strong><?php esc_html_e( 'OSV.dev (Open Source Vulnerabilities)', 'genioussonu-site-checkup' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Queries Google OSV database (api.osv.dev) for precise semantic version range correlation.', 'genioussonu-site-checkup' ); ?>
											</span>
										</span>
									</label>
									<div class="wpsg-input-group" style="padding-left: 24px;">
										<label for="wpsg-page-setting-osv-key"><?php esc_html_e( 'OSV API Key / Token (Optional)', 'genioussonu-site-checkup' ); ?></label>
										<input type="password" id="wpsg-page-setting-osv-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste key or leave blank for default query', 'genioussonu-site-checkup' ); ?>" autocomplete="off" />
										<p id="wpsg-page-osv-masked-status" class="wpsg-input-hint"></p>
									</div>
								</div>

								<!-- NVD -->
								<div style="margin-bottom: 16px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
									<label class="wpsg-checkbox-label" style="margin-bottom: 8px;">
										<input type="checkbox" id="wpsg-page-setting-nvd-optin" />
										<span>
											<strong><?php esc_html_e( 'NVD (National Vulnerability Database - NIST)', 'genioussonu-site-checkup' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Queries NIST NVD 2.0 API (services.nvd.nist.gov) for authoritative CVE records.', 'genioussonu-site-checkup' ); ?>
											</span>
										</span>
									</label>
									<div class="wpsg-input-group" style="padding-left: 24px;">
										<label for="wpsg-page-setting-nvd-key"><?php esc_html_e( 'NVD 2.0 API Key (Optional)', 'genioussonu-site-checkup' ); ?></label>
										<input type="password" id="wpsg-page-setting-nvd-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste NIST NVD API key (stored encrypted with HKDF)', 'genioussonu-site-checkup' ); ?>" autocomplete="off" />
										<p id="wpsg-page-nvd-masked-status" class="wpsg-input-hint"></p>
									</div>
								</div>

								<!-- CISA KEV -->
								<div style="margin-bottom: 16px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
									<label class="wpsg-checkbox-label" style="margin-bottom: 8px;">
										<input type="checkbox" id="wpsg-page-setting-cisa-kev-optin" />
										<span>
											<strong><?php esc_html_e( 'CISA KEV Catalog (Actively Exploited)', 'genioussonu-site-checkup' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Cross-references detected CVEs against the CISA Known Exploited Vulnerabilities catalog (cisa.gov).', 'genioussonu-site-checkup' ); ?>
											</span>
										</span>
									</label>
									<div class="wpsg-input-group" style="padding-left: 24px;">
										<label for="wpsg-page-setting-cisa-kev-key"><?php esc_html_e( 'CISA Access / Proxy Token (Optional)', 'genioussonu-site-checkup' ); ?></label>
										<input type="password" id="wpsg-page-setting-cisa-kev-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste token or leave blank for default feed', 'genioussonu-site-checkup' ); ?>" autocomplete="off" />
										<p id="wpsg-page-cisa-kev-masked-status" class="wpsg-input-hint"></p>
									</div>
								</div>

								<!-- WPScan -->
								<div style="margin-bottom: 16px; padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
									<label class="wpsg-checkbox-label" style="margin-bottom: 8px;">
										<input type="checkbox" id="wpsg-page-setting-wpscan-optin" />
										<span>
											<strong><?php esc_html_e( 'WPScan Vulnerability Database API', 'genioussonu-site-checkup' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Queries Automattic WPScan database (wpscan.com/api/v3). Note: Per WPScan terms, vulnerability data from this source is strictly non-cached and never permanently stored.', 'genioussonu-site-checkup' ); ?>
											</span>
										</span>
									</label>
									<div class="wpsg-input-group" style="padding-left: 24px;">
										<label for="wpsg-page-setting-wpscan-token"><?php esc_html_e( 'WPScan API Token', 'genioussonu-site-checkup' ); ?></label>
										<input type="password" id="wpsg-page-setting-wpscan-token" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste WPScan token (stored encrypted with HKDF)', 'genioussonu-site-checkup' ); ?>" autocomplete="off" />
										<p id="wpsg-page-wpscan-masked-status" class="wpsg-input-hint"></p>
									</div>
								</div>

								<!-- Patchstack -->
								<div style="padding: 12px; background: var(--wpsg-bg); border: 1px solid var(--wpsg-border); border-radius: 6px;">
									<label class="wpsg-checkbox-label" style="margin-bottom: 8px;">
										<input type="checkbox" id="wpsg-page-setting-patchstack-optin" />
										<span>
											<strong><?php esc_html_e( 'Patchstack Vulnerability Intelligence API', 'genioussonu-site-checkup' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Cross-reference plugins and themes against Patchstack database (patchstack.com/database/api/v2).', 'genioussonu-site-checkup' ); ?>
											</span>
										</span>
									</label>
									<div class="wpsg-input-group" style="padding-left: 24px;">
										<label for="wpsg-page-setting-patchstack-key"><?php esc_html_e( 'Patchstack API Token', 'genioussonu-site-checkup' ); ?></label>
										<input type="password" id="wpsg-page-setting-patchstack-key" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste API key or leave blank for default local advisories', 'genioussonu-site-checkup' ); ?>" autocomplete="off" />
										<p id="wpsg-page-patchstack-masked-status" class="wpsg-input-hint"></p>
									</div>
								</div>
							</div>
						</div>

						<!-- Section 2: Hosting Control Panel Bridge (Nginx Tier 1) -->
						<div class="wpsg-settings-card">
							<div class="wpsg-settings-card-header">
								<div class="wpsg-settings-icon wpsg-icon-server"><span class="dashicons dashicons-networking"></span></div>
								<div>
									<h3><?php esc_html_e( 'Hosting Control Panel Bridge (Nginx Tier 1 Directives)', 'genioussonu-site-checkup' ); ?></h3>
									<p><?php esc_html_e( 'For sites running on Nginx, route directive applications through your hosting panel official API.', 'genioussonu-site-checkup' ); ?></p>
								</div>
							</div>
							<div class="wpsg-settings-card-body">
								<div id="wpsg-page-panel-detection-info" class="wpsg-detection-pill" style="display: none;"></div>
								<div class="wpsg-form-grid-2">
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-panel-type"><?php esc_html_e( 'Control Panel Type', 'genioussonu-site-checkup' ); ?></label>
										<select id="wpsg-page-setting-panel-type" class="wpsg-select">
											<option value=""><?php esc_html_e( 'None / Not Applicable', 'genioussonu-site-checkup' ); ?></option>
											<option value="cpanel"><?php esc_html_e( 'cPanel (UAPI)', 'genioussonu-site-checkup' ); ?></option>
											<option value="plesk"><?php esc_html_e( 'Plesk (REST API)', 'genioussonu-site-checkup' ); ?></option>
											<option value="cloudpanel"><?php esc_html_e( 'CloudPanel (v2 API)', 'genioussonu-site-checkup' ); ?></option>
											<option value="runcloud"><?php esc_html_e( 'RunCloud (API)', 'genioussonu-site-checkup' ); ?></option>
											<option value="cyberpanel"><?php esc_html_e( 'CyberPanel (REST API)', 'genioussonu-site-checkup' ); ?></option>
										</select>
									</div>
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-panel-url"><?php esc_html_e( 'Panel URL / Port', 'genioussonu-site-checkup' ); ?></label>
										<input type="url" id="wpsg-page-setting-panel-url" class="wpsg-input" placeholder="https://cp.server.com:8443" />
									</div>
								</div>
								<div class="wpsg-input-group">
									<label for="wpsg-page-setting-panel-token"><?php esc_html_e( 'API Token / Secret Key (Stored Encrypted)', 'genioussonu-site-checkup' ); ?></label>
									<input type="password" id="wpsg-page-setting-panel-token" class="wpsg-input" placeholder="<?php esc_attr_e( 'Paste panel API token (stored encrypted with HKDF + AES-256-GCM)', 'genioussonu-site-checkup' ); ?>" autocomplete="off" />
									<p id="wpsg-page-panel-masked-status" class="wpsg-input-hint"></p>
								</div>
								<div class="wpsg-consent-box">
									<label class="wpsg-checkbox-label">
										<input type="checkbox" id="wpsg-page-setting-panel-optin" />
										<span>
											<strong><?php esc_html_e( 'Authorize API Directive Application (Explicit Consent)', 'genioussonu-site-checkup' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Authorizes GeniousSonu Site Checkup to transmit authenticated Nginx directive configurations to the specified control panel API endpoint. Token is never logged or exported.', 'genioussonu-site-checkup' ); ?>
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
									<h3><?php esc_html_e( 'Security Alert Webhooks', 'genioussonu-site-checkup' ); ?></h3>
									<p><?php esc_html_e( 'Forward real-time security alerts (rogue admin creation, login spikes, PHP uploads execution) to Slack or Discord.', 'genioussonu-site-checkup' ); ?></p>
								</div>
							</div>
							<div class="wpsg-settings-card-body">
								<div class="wpsg-input-group">
									<label for="wpsg-page-setting-webhook-url"><?php esc_html_e( 'Webhook Endpoint URL', 'genioussonu-site-checkup' ); ?></label>
									<input type="url" id="wpsg-page-setting-webhook-url" class="wpsg-input" placeholder="https://hooks.slack.com/services/..." />
								</div>
								<div class="wpsg-consent-box">
									<label class="wpsg-checkbox-label">
										<input type="checkbox" id="wpsg-page-setting-webhook-optin" />
										<span>
											<strong><?php esc_html_e( 'Allow outbound alert dispatch to this webhook (Explicit Consent)', 'genioussonu-site-checkup' ); ?></strong><br />
											<span class="wpsg-consent-desc">
												<?php esc_html_e( 'Transmits event summaries, timestamp, and site URL to the specified endpoint. Outbound requests are strictly verified through SSRF guards.', 'genioussonu-site-checkup' ); ?>
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
									<h3><?php esc_html_e( 'Incident Escalation & Agency Information', 'genioussonu-site-checkup' ); ?></h3>
									<p><?php esc_html_e( 'Designate emergency contacts for after-hours breaches and configure agency white-labeling.', 'genioussonu-site-checkup' ); ?></p>
								</div>
							</div>
							<div class="wpsg-settings-card-body">
								<div class="wpsg-form-grid-2">
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-incident-name"><?php esc_html_e( 'Emergency Contact Person / Role', 'genioussonu-site-checkup' ); ?></label>
										<input type="text" id="wpsg-page-setting-incident-name" class="wpsg-input" placeholder="e.g. Lead SecOps Engineer" />
									</div>
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-incident-email"><?php esc_html_e( 'Emergency Email', 'genioussonu-site-checkup' ); ?></label>
										<input type="email" id="wpsg-page-setting-incident-email" class="wpsg-input" placeholder="e.g. security@clientsite.com" />
									</div>
								</div>
								<div class="wpsg-form-grid-2">
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-incident-phone"><?php esc_html_e( 'Emergency Phone / Pager', 'genioussonu-site-checkup' ); ?></label>
										<input type="text" id="wpsg-page-setting-incident-phone" class="wpsg-input" placeholder="e.g. +1 (555) 019-2831" />
									</div>
									<div class="wpsg-input-group">
										<label for="wpsg-page-setting-agency-name"><?php esc_html_e( 'Agency / Preparer Name (Client Reports)', 'genioussonu-site-checkup' ); ?></label>
										<input type="text" id="wpsg-page-setting-agency-name" class="wpsg-input" placeholder="e.g. Acme Security Services" />
									</div>
								</div>
								<div class="wpsg-input-group">
									<label for="wpsg-page-setting-incident-notes"><?php esc_html_e( 'Incident Protocol & Vault Reference', 'genioussonu-site-checkup' ); ?></label>
									<textarea id="wpsg-page-setting-incident-notes" class="wpsg-textarea" rows="2" placeholder="<?php esc_attr_e( 'e.g. Contact 24/7 hosting desk, access 1Password Emergency Vault for root credentials.', 'genioussonu-site-checkup' ); ?>"></textarea>
								</div>
							</div>
						</div>

						<!-- Settings Actions Bar -->
						<div class="wpsg-settings-footer-bar">
							<span id="wpsg-page-settings-save-status" class="wpsg-save-status"></span>
							<button type="button" class="wpsg-btn wpsg-btn-primary wpsg-btn-lg" id="wpsg-btn-page-save-settings">
								<span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Save All Settings', 'genioussonu-site-checkup' ); ?>
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
			<span><?php esc_html_e( 'Developed by', 'genioussonu-site-checkup' ); ?> <strong><a href="https://www.genioussonu.me/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-brand); text-decoration: none;">SK Sahinur Islam</a></strong></span>
			<span style="margin: 0 8px; color: var(--wpsg-border);">&bull;</span>
			<span class="wpsg-footer-version">GeniousSonu Site Checkup v<?php echo esc_html( WPSG_VERSION ); ?></span>
		</div>
		<nav class="wpsg-footer-links" style="display: flex; gap: 16px;" aria-label="<?php esc_attr_e( 'Help and documentation links', 'genioussonu-site-checkup' ); ?>">
			<a href="https://www.genioussonu.me/plugin/genioussonu-site-checkup/docs/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-book" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Docs', 'genioussonu-site-checkup' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/genioussonu-site-checkup/support/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-sos" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Support', 'genioussonu-site-checkup' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/genioussonu-site-checkup/changelog/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-backup" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Changelog', 'genioussonu-site-checkup' ); ?>
			</a>
			<a href="https://www.genioussonu.me/plugin/genioussonu-site-checkup/privacy-policy/" target="_blank" rel="noopener noreferrer" style="color: var(--wpsg-text-secondary); text-decoration: none;">
				<span class="dashicons dashicons-shield" style="font-size: 16px; vertical-align: text-bottom;"></span> <?php esc_html_e( 'Privacy Policy', 'genioussonu-site-checkup' ); ?>
			</a>
		</nav>
	</footer>

</div>
