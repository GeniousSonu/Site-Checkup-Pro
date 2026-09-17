/**
 * Site Checkup Pro Admin Application
 *
 * Handles client-driven sequential batch execution, optimistic task updates,
 * accessible status badges, diff preview modals, and Nginx snippet exports.
 *
 * @package SiteCheckupPro
 * @version 1.0.0
 */

(function () {
	'use strict';

	// Main App State
	const state = {
		tasks: [],
		sections: {},
		safeInstantIds: [],
		totalCount: 0,
		doneCount: 0,
		sopCoveragePct: 0,
		serverType: 'apache',
		supportsHtaccess: true,
		backupStatus: {},
		activeTab: 'overview',
		filterLevel: '',
		filterStatus: '',
		searchQuery: '',
		batchRunning: false,
		batchQueue: [],
		batchTotal: 0,
		batchIndex: 0,
		pendingActionTask: null,
	};

	// DOM Elements Cache
	const dom = {};

	/**
	 * Initialize Application
	 */
	function init() {
		cacheDom();
		bindEvents();
		loadTasks();
		initReviewPrompt();
	}

	/**
	 * Cache DOM elements
	 */
	function cacheDom() {
		dom.app = document.getElementById('wpsg-app');
		if (!dom.app) return;

		// Panels
		dom.panelOverview = document.getElementById('wpsg-panel-overview');
		dom.panelTasks = document.getElementById('wpsg-panel-tasks');
		dom.panelFeatures = document.getElementById('wpsg-panel-features');
		dom.panelAudit = document.getElementById('wpsg-panel-audit');
		dom.panelSettings = document.getElementById('wpsg-panel-settings');

		// Section Banner
		dom.currentSecIcon = document.getElementById('wpsg-current-sec-icon');
		dom.currentSecTitle = document.getElementById('wpsg-current-sec-title');
		dom.currentSecDesc = document.getElementById('wpsg-current-sec-desc');
		dom.currentSecBadge = document.getElementById('wpsg-current-sec-badge');
		dom.btnRunSection = document.getElementById('wpsg-btn-run-section');
		dom.searchTasks = document.getElementById('wpsg-search-tasks');

		// Task Table & Audit
		dom.tbody = document.getElementById('wpsg-tasks-tbody');
		dom.tableWrapper = document.getElementById('wpsg-tasks-table-wrapper');
		dom.auditView = document.getElementById('wpsg-audit-view');
		dom.auditTbody = document.getElementById('wpsg-audit-tbody');

		// KPIs
		dom.kpiCoverage = document.getElementById('wpsg-kpi-coverage');
		dom.kpiFraction = document.getElementById('wpsg-kpi-fraction');
		dom.coverageBar = document.getElementById('wpsg-coverage-bar');
		dom.kpiServer = document.getElementById('wpsg-kpi-server');
		dom.kpiBackupName = document.getElementById('wpsg-kpi-backup-name');
		dom.kpiReminders = document.getElementById('wpsg-kpi-reminders');

		// Batch Banner
		dom.batchBanner = document.getElementById('wpsg-batch-banner');
		dom.batchText = document.getElementById('wpsg-batch-text');
		dom.batchCount = document.getElementById('wpsg-batch-count');
		dom.batchProgress = document.getElementById('wpsg-batch-progress');
		dom.batchCancel = document.getElementById('wpsg-batch-cancel');

		// Actions & Filters
		dom.btnBatchRun = document.getElementById('wpsg-btn-batch-run');
		dom.btnConfirmBackup = document.getElementById('wpsg-btn-confirm-backup');
		dom.btnUpdateBaseline = document.getElementById('wpsg-btn-update-baseline');
		dom.filterLevel = document.getElementById('wpsg-filter-level');
		dom.filterStatus = document.getElementById('wpsg-filter-status');
		dom.tabs = document.querySelectorAll('.wpsg-tab');

		// Quick Action buttons on Overview
		dom.btnQuickLoginUrl = document.getElementById('wpsg-btn-quick-login-url');
		dom.btnQuickSessions = document.getElementById('wpsg-btn-quick-sessions');

		// Features Panel Controls
		dom.featLoginSlug = document.getElementById('wpsg-feat-login-slug');
		dom.btnSaveFeatLogin = document.getElementById('wpsg-btn-save-feature-login');
		dom.btnResetFeatLogin = document.getElementById('wpsg-btn-reset-feature-login');
		dom.btnFeatViewSessions = document.getElementById('wpsg-btn-feat-view-sessions');
		dom.btnFeatDestroySessions = document.getElementById('wpsg-btn-feat-destroy-sessions');
		dom.btnFeatAppPasswords = document.getElementById('wpsg-btn-feat-app-passwords');
		dom.btnFeatCspReports = document.getElementById('wpsg-btn-feat-csp-reports');
		dom.btnFeatUpdateBaseline = document.getElementById('wpsg-btn-feat-update-baseline');
		dom.btnTriggerSettingsModal = document.getElementById('wpsg-btn-trigger-settings-modal');

		// Modals
		dom.modalDiff = document.getElementById('wpsg-modal-diff');
		dom.diffFileTarget = document.getElementById('wpsg-diff-file-target');
		dom.diffContent = document.getElementById('wpsg-diff-content');
		dom.btnDiffConfirm = document.getElementById('wpsg-btn-diff-confirm-run');

		dom.modalNginx = document.getElementById('wpsg-modal-nginx');
		dom.nginxCode = document.getElementById('wpsg-nginx-code');
		dom.btnCopyNginx = document.getElementById('wpsg-btn-copy-nginx');

		dom.modalBackup = document.getElementById('wpsg-modal-backup');
		dom.backupTriggerLink = document.getElementById('wpsg-backup-trigger-link');
		dom.backupTriggerText = document.getElementById('wpsg-backup-trigger-text');
		dom.btnConfirmManualBackupModal = document.getElementById('wpsg-btn-confirm-manual-backup-modal');

		dom.modalNote = document.getElementById('wpsg-modal-note');
		dom.noteModalTitle = document.getElementById('wpsg-note-modal-title');
		dom.noteTaskDesc = document.getElementById('wpsg-note-task-desc');
		dom.taskNote = document.getElementById('wpsg-task-note');
		dom.taskReminder = document.getElementById('wpsg-task-reminder');
		dom.btnSaveNote = document.getElementById('wpsg-btn-save-note');

		// Passgen in Note Modal
		dom.passgenBox = document.getElementById('wpsg-passgen-box');
		dom.generatedPass = document.getElementById('wpsg-generated-pass');
		dom.btnGenPass = document.getElementById('wpsg-btn-gen-pass');
		dom.btnCopyPass = document.getElementById('wpsg-btn-copy-pass');

		dom.modalLoginRename = document.getElementById('wpsg-modal-login-rename');
		dom.inputLoginSlug = document.getElementById('wpsg-input-login-slug');
		dom.inputLoginConfirm = document.getElementById('wpsg-input-login-confirm');
		dom.btnConfirmLoginRename = document.getElementById('wpsg-btn-confirm-login-rename');

		dom.modalDeletePlugin = document.getElementById('wpsg-modal-delete-plugin');
		dom.deletePluginName = document.getElementById('wpsg-delete-plugin-name');
		dom.deletePluginSlug = document.getElementById('wpsg-delete-plugin-slug');
		dom.btnConfirmDeletePlugin = document.getElementById('wpsg-btn-confirm-delete-plugin');

		// Advanced Protection & Re-auth Modals
		dom.modalReauth = document.getElementById('wpsg-modal-reauth');
		dom.reauthPassword = document.getElementById('wpsg-reauth-password');
		dom.reauthErrorBox = document.getElementById('wpsg-reauth-error-box');
		dom.reauthErrorMessage = document.getElementById('wpsg-reauth-error-message');
		dom.btnReauthSubmit = document.getElementById('wpsg-btn-reauth-submit');

		dom.modalSessions = document.getElementById('wpsg-modal-sessions');
		dom.sessionsTbody = document.getElementById('wpsg-sessions-tbody');
		dom.btnDestroyOtherSessions = document.getElementById('wpsg-btn-destroy-other-sessions');

		dom.modalAppPasswords = document.getElementById('wpsg-modal-app-passwords');
		dom.appPasswordsTbody = document.getElementById('wpsg-app-passwords-tbody');

		dom.modalCspReports = document.getElementById('wpsg-modal-csp-reports');
		dom.cspTbody = document.getElementById('wpsg-csp-tbody');

		// Settings Modal
		dom.btnOpenSettings = document.getElementById('wpsg-btn-open-settings');
		dom.modalSettings = document.getElementById('wpsg-modal-settings');
		dom.btnCloseSettings = document.getElementById('wpsg-btn-close-settings');
		dom.btnCancelSettings = document.getElementById('wpsg-btn-cancel-settings');
		dom.btnSaveSettings = document.getElementById('wpsg-btn-save-settings');
		dom.settingPatchstackKey = document.getElementById('wpsg-setting-patchstack-key');
		dom.settingPatchstackOptin = document.getElementById('wpsg-setting-patchstack-optin');
		dom.settingWebhookUrl = document.getElementById('wpsg-setting-webhook-url');
		dom.settingWebhookOptin = document.getElementById('wpsg-setting-webhook-optin');
		dom.patchstackMaskedStatus = document.getElementById('wpsg-patchstack-masked-status');
		dom.settingIncidentName = document.getElementById('wpsg-setting-incident-name');
		dom.settingIncidentEmail = document.getElementById('wpsg-setting-incident-email');
		dom.settingIncidentPhone = document.getElementById('wpsg-setting-incident-phone');
		dom.settingIncidentNotes = document.getElementById('wpsg-setting-incident-notes');
		dom.settingAgencyName = document.getElementById('wpsg-setting-agency-name');
		dom.settingsSaveStatus = document.getElementById('wpsg-settings-save-status');
	}

	/**
	 * Bind UI event listeners
	 */
	function bindEvents() {
		if (!dom.app) return;

		// Tab Switching (Sidebar and top navigation)
		document.querySelectorAll('.wpsg-tab').forEach(tab => {
			tab.addEventListener('click', (e) => {
				e.preventDefault();
				const targetTab = tab.getAttribute('data-tab');
				if (targetTab) {
					switchTab(targetTab);
				}
			});
		});

		// Overview Category Card Clicks
		document.querySelectorAll('[data-open-tab]').forEach(btn => {
			btn.addEventListener('click', (e) => {
				e.preventDefault();
				e.stopPropagation();
				const targetTab = btn.getAttribute('data-open-tab');
				if (targetTab) {
					switchTab(targetTab);
				}
			});
		});

		document.querySelectorAll('.wpsg-category-card').forEach(card => {
			card.addEventListener('click', (e) => {
				if (e.target.closest('button') || e.target.closest('a')) return;
				const targetSection = card.getAttribute('data-section-target');
				if (targetSection) {
					switchTab(targetSection);
				}
			});
		});

		// Task Search
		if (dom.searchTasks) {
			dom.searchTasks.addEventListener('input', (e) => {
				state.searchQuery = e.target.value.trim();
				renderTasksTable();
			});
		}

		// Run Section Batch Button
		if (dom.btnRunSection) {
			dom.btnRunSection.addEventListener('click', runCurrentSectionBatch);
		}

		// Quick Buttons in Overview
		if (dom.btnQuickLoginUrl) {
			dom.btnQuickLoginUrl.addEventListener('click', () => {
				switchTab('features');
				if (dom.featLoginSlug) dom.featLoginSlug.focus();
			});
		}
		if (dom.btnQuickSessions) {
			dom.btnQuickSessions.addEventListener('click', openSessionsModal);
		}

		// Features Panel Event Listeners
		if (dom.btnSaveFeatLogin) {
			dom.btnSaveFeatLogin.addEventListener('click', saveFeatureLoginSlug);
		}
		if (dom.btnResetFeatLogin) {
			dom.btnResetFeatLogin.addEventListener('click', resetFeatureLoginSlug);
		}
		if (dom.btnFeatViewSessions) {
			dom.btnFeatViewSessions.addEventListener('click', openSessionsModal);
		}
		if (dom.btnFeatDestroySessions) {
			dom.btnFeatDestroySessions.addEventListener('click', destroyOtherSessions);
		}
		if (dom.btnFeatAppPasswords) {
			dom.btnFeatAppPasswords.addEventListener('click', openAppPasswordsModal);
		}
		if (dom.btnFeatCspReports) {
			dom.btnFeatCspReports.addEventListener('click', openCspReportsModal);
		}
		if (dom.btnFeatUpdateBaseline) {
			dom.btnFeatUpdateBaseline.addEventListener('click', updateBaseline);
		}
		if (dom.btnTriggerSettingsModal) {
			dom.btnTriggerSettingsModal.addEventListener('click', openSettingsModal);
		}

		// Filters
		if (dom.filterLevel) {
			dom.filterLevel.addEventListener('change', e => {
				state.filterLevel = e.target.value;
				renderTasksTable();
			});
		}

		if (dom.filterStatus) {
			dom.filterStatus.addEventListener('change', e => {
				state.filterStatus = e.target.value;
				renderTasksTable();
			});
		}

		// Header Buttons
		if (dom.btnBatchRun) {
			dom.btnBatchRun.addEventListener('click', startBatchRunner);
		}

		if (dom.batchCancel) {
			dom.batchCancel.addEventListener('click', stopBatchRunner);
		}

		if (dom.btnConfirmBackup) {
			dom.btnConfirmBackup.addEventListener('click', confirmManualBackup);
		}

		if (dom.btnUpdateBaseline) {
			dom.btnUpdateBaseline.addEventListener('click', updateBaseline);
		}

		// Modal Close Buttons
		document.querySelectorAll('[data-close-modal]').forEach(btn => {
			btn.addEventListener('click', closeAllModals);
		});

		// Close modal on escape key
		window.addEventListener('keydown', e => {
			if (e.key === 'Escape') closeAllModals();
		});

		// Diff Modal Confirm Run
		if (dom.btnDiffConfirm) {
			dom.btnDiffConfirm.addEventListener('click', () => {
				if (state.pendingActionTask) {
					const taskId = state.pendingActionTask;
					closeAllModals();
					runTask(taskId);
				}
			});
		}

		// Nginx Snippet Copy
		if (dom.btnCopyNginx) {
			dom.btnCopyNginx.addEventListener('click', () => {
				const code = dom.nginxCode.textContent;
				navigator.clipboard.writeText(code).then(() => {
					const originalText = dom.btnCopyNginx.innerHTML;
					dom.btnCopyNginx.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
					setTimeout(() => {
						dom.btnCopyNginx.innerHTML = originalText;
					}, 2000);
				});
			});
		}

		// Modal Backup Manual Confirm
		if (dom.btnConfirmManualBackupModal) {
			dom.btnConfirmManualBackupModal.addEventListener('click', () => {
				confirmManualBackup(() => {
					closeAllModals();
					if (state.pendingActionTask) {
						runTask(state.pendingActionTask);
					}
				});
			});
		}

		// Save Note (Level C)
		if (dom.btnSaveNote) {
			dom.btnSaveNote.addEventListener('click', saveManualNote);
		}

		// Password Generator Events
		if (dom.btnGenPass) {
			dom.btnGenPass.addEventListener('click', () => {
				const chars = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789!@#$%^&*()-_=+';
				let pass = '';
				const array = new Uint32Array(24);
				window.crypto.getRandomValues(array);
				for (let i = 0; i < 24; i++) {
					pass += chars[array[i] % chars.length];
				}
				if (dom.generatedPass) dom.generatedPass.value = pass;
			});
		}
		if (dom.btnCopyPass) {
			dom.btnCopyPass.addEventListener('click', () => {
				if (!dom.generatedPass || !dom.generatedPass.value) return;
				navigator.clipboard.writeText(dom.generatedPass.value).then(() => {
					const orig = dom.btnCopyPass.innerHTML;
					dom.btnCopyPass.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
					setTimeout(() => { dom.btnCopyPass.innerHTML = orig; }, 2000);
				});
			});
		}

		// Login Renamer Input Validation (Typing 'CHANGE' required)
		if (dom.inputLoginConfirm) {
			dom.inputLoginConfirm.addEventListener('input', e => {
				const isConfirmed = e.target.value.trim() === 'CHANGE';
				const hasSlug = dom.inputLoginSlug.value.trim().length > 0;
				dom.btnConfirmLoginRename.disabled = !(isConfirmed && hasSlug);
			});
		}
		if (dom.inputLoginSlug) {
			dom.inputLoginSlug.addEventListener('input', () => {
				const isConfirmed = dom.inputLoginConfirm.value.trim() === 'CHANGE';
				const hasSlug = dom.inputLoginSlug.value.trim().length > 0;
				dom.btnConfirmLoginRename.disabled = !(isConfirmed && hasSlug);
			});
		}
		if (dom.btnConfirmLoginRename) {
			dom.btnConfirmLoginRename.addEventListener('click', submitLoginRename);
		}

		// Confirm Delete Plugin
		if (dom.btnConfirmDeletePlugin) {
			dom.btnConfirmDeletePlugin.addEventListener('click', submitDeletePlugin);
		}

		// Re-Auth Modal Submit
		if (dom.btnReauthSubmit) {
			dom.btnReauthSubmit.addEventListener('click', handleReauthSubmit);
		}

		// Destroy Other Sessions Button
		if (dom.btnDestroyOtherSessions) {
			dom.btnDestroyOtherSessions.addEventListener('click', destroyOtherSessions);
		}

		// Settings Modal Events
		if (dom.btnOpenSettings) {
			dom.btnOpenSettings.addEventListener('click', openSettingsModal);
		}
		if (dom.btnCloseSettings) {
			dom.btnCloseSettings.addEventListener('click', closeAllModals);
		}
		if (dom.btnCancelSettings) {
			dom.btnCancelSettings.addEventListener('click', closeAllModals);
		}
		if (dom.btnSaveSettings) {
			dom.btnSaveSettings.addEventListener('click', saveSettings);
		}
	}

	/**
	 * Fetch tasks catalog and summary from REST API
	 */
	async function loadTasks() {
		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/tasks',
			});

			state.tasks = res.tasks || [];
			state.sections = res.sections || {};
			state.safeInstantIds = res.safe_instant_ids || [];
			state.totalCount = res.total_count || 0;
			state.doneCount = res.done_count || 0;
			state.sopCoveragePct = res.sop_coverage_pct || 0;
			state.serverType = res.server_type || 'apache';
			state.supportsHtaccess = res.supports_htaccess;
			state.backupStatus = res.backup_status || {};

			updateKpis();
			renderViews();
		} catch (err) {
			console.error('Failed to load Site Checkup Pro tasks:', err);
			if (dom.tbody) {
				dom.tbody.innerHTML = `<tr><td colspan="5" class="wpsg-error-state"><span class="dashicons dashicons-warning"></span> ${escapeHtml(err.message || 'Error communicating with REST API.')}</td></tr>`;
			}
		}
	}

	/**
	 * Switch active tab and render appropriate panel
	 */
	function switchTab(tabId) {
		state.activeTab = tabId;

		// Update active class on all tab buttons
		document.querySelectorAll('.wpsg-tab').forEach(t => {
			if (t.getAttribute('data-tab') === tabId) {
				t.classList.add('active');
			} else {
				t.classList.remove('active');
			}
		});

		renderViews();
	}

	/**
	 * Update overview category card stats & sidebar counts
	 */
	function updateOverviewStats() {
		// Update sidebar Overview badge
		const overviewBadge = document.getElementById('wpsg-badge-overview-pct');
		if (overviewBadge) {
			overviewBadge.textContent = `${state.sopCoveragePct}%`;
		}

		// Update each section card and sidebar badge
		const sectionKeys = ['security_update', 'general_check', 'hardening', 'advanced_protection', 'regular_checks', 'seo_sop'];
		sectionKeys.forEach(key => {
			const tasks = state.tasks.filter(t => t.section === key);
			const total = tasks.length;
			const done = tasks.filter(t => t.status === 'done').length;
			const pct = total > 0 ? Math.round((done / total) * 100) : 0;

			// Card elements
			const bar = document.getElementById(`wpsg-cat-bar-${key}`);
			if (bar) bar.style.width = `${pct}%`;

			const txt = document.getElementById(`wpsg-cat-txt-${key}`);
			if (txt) txt.textContent = `${done} / ${total} Completed`;

			const stat = document.getElementById(`wpsg-cat-stat-${key}`);
			if (stat) stat.textContent = `${total} checks`;

			// Sidebar badge
			const sidebarBadgeMap = {
				'security_update': 'wpsg-badge-sec-update',
				'general_check': 'wpsg-badge-gen-check',
				'hardening': 'wpsg-badge-hardening',
				'advanced_protection': 'wpsg-badge-adv-prot',
				'regular_checks': 'wpsg-badge-reg-checks',
				'seo_sop': 'wpsg-badge-seo',
			};
			const sbBadge = document.getElementById(sidebarBadgeMap[key]);
			if (sbBadge) {
				sbBadge.textContent = `${done}/${total}`;
			}
		});

		const allBadge = document.getElementById('wpsg-badge-all');
		if (allBadge) {
			allBadge.textContent = `${state.doneCount}/${state.totalCount}`;
		}
	}

	/**
	 * Update task section hero banner
	 */
	function updateSectionBanner() {
		const sec = state.sections[state.activeTab];
		let title = 'All Checks';
		let iconClass = 'dashicons-list-view';
		let desc = 'Complete system standard operating procedures and hardening checks.';

		if (sec) {
			title = sec.label;
			iconClass = sec.icon || 'dashicons-shield';
			desc = sec.desc || '';
		}

		if (dom.currentSecTitle) dom.currentSecTitle.textContent = title;
		if (dom.currentSecDesc) dom.currentSecDesc.textContent = desc;
		if (dom.currentSecIcon) {
			dom.currentSecIcon.className = `dashicons ${iconClass}`;
		}

		const secTasks = state.activeTab === 'all' ? state.tasks : state.tasks.filter(t => t.section === state.activeTab);
		const secTotal = secTasks.length;
		const secDone = secTasks.filter(t => t.status === 'done').length;
		const secPct = secTotal > 0 ? Math.round((secDone / secTotal) * 100) : 0;

		if (dom.currentSecBadge) {
			dom.currentSecBadge.textContent = `${secDone} / ${secTotal} Passed (${secPct}%)`;
		}

		if (dom.btnRunSection) {
			dom.btnRunSection.innerHTML = `<span class="dashicons dashicons-controls-play"></span> Run Safe Checks in ${escapeHtml(title)}`;
		}
	}

	/**
	 * Run batch on safe tasks within the active section
	 */
	function runCurrentSectionBatch() {
		if (state.batchRunning) return;

		let targetTasks = [];
		if (state.activeTab === 'all') {
			targetTasks = state.tasks.filter(t => t.automation_level === 'A' && t.status !== 'done');
		} else {
			targetTasks = state.tasks.filter(t => t.section === state.activeTab && t.automation_level === 'A' && t.status !== 'done');
		}

		if (targetTasks.length === 0) {
			alert('All automated safe checks in this section are already completed!');
			return;
		}

		state.batchQueue = targetTasks.map(t => t.id);
		state.batchTotal = state.batchQueue.length;
		state.batchIndex = 0;
		state.batchRunning = true;

		if (dom.batchBanner) dom.batchBanner.style.display = 'flex';
		if (dom.batchProgress) dom.batchProgress.style.backgroundColor = 'var(--wpsg-brand)';
		if (dom.btnBatchRun) dom.btnBatchRun.disabled = true;
		if (dom.btnRunSection) dom.btnRunSection.disabled = true;

		processNextBatchTask();
	}

	/**
	 * Save custom login URL from Features panel
	 */
	async function saveFeatureLoginSlug() {
		if (!dom.featLoginSlug) return;
		const slug = dom.featLoginSlug.value.trim();
		if (!slug) {
			alert('Please enter a login URL slug.');
			return;
		}

		if (!confirm(`Are you sure you want to change your WordPress login URL to:\n${window.wpsgData?.homeUrl || ''}/${slug}/\n\nMake sure to remember this URL before proceeding!`)) {
			return;
		}

		try {
			dom.btnSaveFeatLogin.disabled = true;
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/tasks/set-login-slug',
				method: 'POST',
				headers: window.wpsgData?.nonces?.set_login_slug ? { 'X-WPSG-Nonce': window.wpsgData.nonces.set_login_slug } : {},
				data: { slug, confirm: 'CHANGE' },
			});

			if (res.success) {
				alert(res.message);
				window.location.reload();
			} else {
				alert(`Failed: ${res.message}`);
				dom.btnSaveFeatLogin.disabled = false;
			}
		} catch (err) {
			alert(`Error: ${err.message || 'Could not change login URL'}`);
			dom.btnSaveFeatLogin.disabled = false;
		}
	}

	/**
	 * Reset custom login URL back to default
	 */
	async function resetFeatureLoginSlug() {
		if (!confirm('Revert back to standard WordPress /wp-login.php?')) {
			return;
		}

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/tasks/set-login-slug',
				method: 'POST',
				headers: window.wpsgData?.nonces?.set_login_slug ? { 'X-WPSG-Nonce': window.wpsgData.nonces.set_login_slug } : {},
				data: { slug: '', confirm: 'CHANGE' },
			});

			if (res.success) {
				alert(res.message);
				window.location.reload();
			} else {
				alert(`Failed: ${res.message}`);
			}
		} catch (err) {
			alert(`Error: ${err.message || 'Could not reset login URL'}`);
		}
	}

	/**
	 * Update KPI summary cards
	 */
	function updateKpis() {
		if (dom.kpiCoverage) dom.kpiCoverage.textContent = `${state.sopCoveragePct}%`;
		if (dom.kpiFraction) dom.kpiFraction.textContent = `${state.doneCount} / ${state.totalCount} tasks`;
		if (dom.coverageBar) dom.coverageBar.style.width = `${state.sopCoveragePct}%`;
		if (dom.kpiServer) dom.kpiServer.textContent = state.serverType.toUpperCase();

		// Count scheduled reminders
		const reminderCount = state.tasks.filter(t => t.next_reminder_at).length;
		if (dom.kpiReminders) dom.kpiReminders.textContent = reminderCount;

		updateOverviewStats();
	}

	/**
	 * Render views depending on active tab
	 */
	function renderViews() {
		// Hide all panels first
		if (dom.panelOverview) dom.panelOverview.style.display = 'none';
		if (dom.panelTasks) dom.panelTasks.style.display = 'none';
		if (dom.panelFeatures) dom.panelFeatures.style.display = 'none';
		if (dom.panelAudit) dom.panelAudit.style.display = 'none';
		if (dom.panelSettings) dom.panelSettings.style.display = 'none';

		if (state.activeTab === 'overview') {
			if (dom.panelOverview) dom.panelOverview.style.display = 'block';
			updateOverviewStats();
		} else if (state.activeTab === 'features') {
			if (dom.panelFeatures) dom.panelFeatures.style.display = 'block';
		} else if (state.activeTab === 'audit_trail') {
			if (dom.panelAudit) dom.panelAudit.style.display = 'block';
			loadAuditLogs();
		} else if (state.activeTab === 'settings') {
			if (dom.panelSettings) dom.panelSettings.style.display = 'block';
		} else {
			// Specific task section or 'all'
			if (dom.panelTasks) dom.panelTasks.style.display = 'block';
			updateSectionBanner();
			renderTasksTable();
		}
	}

	/**
	 * Render checklist table rows with filters
	 */
	function renderTasksTable() {
		if (!dom.tbody) return;

		let filtered = state.tasks;

		// 0. Filter by Search Query
		if (state.searchQuery) {
			const q = state.searchQuery.toLowerCase();
			filtered = filtered.filter(t =>
				(t.title && t.title.toLowerCase().includes(q)) ||
				(t.description && t.description.toLowerCase().includes(q)) ||
				(t.id && t.id.toLowerCase().includes(q))
			);
		}

		// 1. Filter by Section tab
		if (state.activeTab !== 'all' && state.activeTab !== 'audit_trail' && state.activeTab !== 'overview' && state.activeTab !== 'features') {
			filtered = filtered.filter(t => t.section === state.activeTab);
		}

		// 2. Filter by Level
		if (state.filterLevel) {
			if (state.filterLevel === 'A_instant') {
				filtered = filtered.filter(t => t.automation_level === 'A' && t.sub_type === 'instant');
			} else if (state.filterLevel === 'A_files') {
				filtered = filtered.filter(t => t.automation_level === 'A' && t.sub_type === 'writes_files');
			} else {
				filtered = filtered.filter(t => t.automation_level === state.filterLevel);
			}
		}

		// 3. Filter by Status
		if (state.filterStatus) {
			filtered = filtered.filter(t => t.status === state.filterStatus);
		}

		if (filtered.length === 0) {
			const emptyImg = (window.wpsgData && window.wpsgData.mediaUrl) ? `${window.wpsgData.mediaUrl}empty-state.svg` : '';
			dom.tbody.innerHTML = `
				<tr>
					<td colspan="5">
						<div class="wpsg-empty-state">
							${emptyImg ? `<img src="${emptyImg}" width="120" height="85" alt="" />` : '<span class="dashicons dashicons-search"></span>'}
							<h4>No checklist tasks found</h4>
							<p>No tasks match the active filters or search query. Try switching categories or clearing search.</p>
						</div>
					</td>
				</tr>
			`;
			return;
		}

		dom.tbody.innerHTML = filtered.map(t => renderTaskRow(t)).join('');
		bindRowEvents();
	}

	/**
	 * Render a single task row with accessible badges (Dot + Label + Accessible text)
	 */
	function renderTaskRow(task) {
		const statusBadge = getStatusBadgeHtml(task.status);
		const levelBadge = getLevelBadgeHtml(task.automation_level, task.sub_type);
		const actionButtons = getActionButtonsHtml(task);
		const lastRunText = task.last_run_at ? formatDate(task.last_run_at) : '<span class="wpsg-text-muted">Not checked yet</span>';
		const rowClass = `wpsg-task-row ${task.status === 'done' ? 'wpsg-row-completed' : ''}`;

		return `
			<tr data-task-id="${escapeHtml(task.id)}" class="${rowClass}">
				<td class="wpsg-col-status">${statusBadge}</td>
				<td class="wpsg-col-level">${levelBadge}</td>
				<td class="wpsg-col-task">
					<div class="wpsg-task-cell">
						<span class="wpsg-task-title">${escapeHtml(task.title)}</span>
						<span class="wpsg-task-desc">${escapeHtml(task.description)}</span>
						${task.live_message ? `<div class="wpsg-task-evidence"><span class="dashicons dashicons-info" style="font-size:12px;width:12px;height:12px;margin-top:1px;"></span> <span>${escapeHtml(task.live_message)}</span></div>` : ''}
						${task.note ? `<div class="wpsg-task-evidence"><span class="dashicons dashicons-edit" style="font-size:12px;width:12px;height:12px;margin-top:1px;"></span> <span>Note: ${escapeHtml(task.note)}</span></div>` : ''}
					</div>
				</td>
				<td class="wpsg-col-checked">${lastRunText}</td>
				<td class="wpsg-col-actions">${actionButtons}</td>
			</tr>
		`;
	}

	/**
	 * Colorblind Accessible Status Badges (Dot + Label)
	 */
	function getStatusBadgeHtml(status) {
		switch (status) {
			case 'done':
				return '<span class="wpsg-status-indicator wpsg-status-done"><span class="wpsg-status-dot"></span> Completed</span>';
			case 'attention':
				return '<span class="wpsg-status-indicator wpsg-status-attention"><span class="wpsg-status-dot"></span> Action Needed</span>';
			case 'failed':
				return '<span class="wpsg-status-indicator wpsg-status-critical"><span class="wpsg-status-dot"></span> Failed</span>';
			case 'not_applicable':
				return '<span class="wpsg-status-indicator wpsg-status-na"><span class="wpsg-status-dot"></span> Not Applicable</span>';
			case 'pending':
			default:
				return '<span class="wpsg-status-indicator wpsg-status-pending"><span class="wpsg-status-dot"></span> Pending</span>';
		}
	}

	/**
	 * Level Badge HTML (Crisp Chips)
	 */
	function getLevelBadgeHtml(level, subType) {
		if (level === 'A') {
			if (subType === 'writes_files') {
				return '<span class="wpsg-chip" title="Automated: File/Config Write">Level A (Config)</span>';
			}
			return '<span class="wpsg-chip wpsg-chip-instant" title="Automated: Instant Safe Runtime">Level A (Instant)</span>';
		}
		if (level === 'B') {
			return '<span class="wpsg-chip" title="Guided Procedure">Level B (Guided)</span>';
		}
		if (level === 'C') {
			return '<span class="wpsg-chip" title="Manual SOP Verification">Level C (Manual)</span>';
		}
		return `<span class="wpsg-chip">Level ${escapeHtml(level)}</span>`;
	}

	/**
	 * Action Buttons HTML based on Task Classification
	 */
	function getActionButtonsHtml(task) {
		let html = '<div class="wpsg-action-group">';

		// If server is Nginx and task is .htaccess-only rule
		if (!state.supportsHtaccess && task.nginx_snippet && task.is_na) {
			html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-view-nginx" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-networking"></span> Nginx Snippet</button>`;
			html += '</div>';
			return html;
		}

		// Level A: Automated
		if (task.automation_level === 'A') {
			if (task.has_diff && task.status !== 'done') {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-diff" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-visibility"></span> Diff</button> `;
			}

			if (task.status === 'done' && task.has_undo) {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-undo" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-undo"></span> Undo</button>`;
			} else {
				const runLabel = task.status === 'done' ? 'Re-run' : 'Run';
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-run" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-controls-play"></span> ${runLabel}</button>`;
			}
		}

		// Level B: Guided
		else if (task.automation_level === 'B') {
			if (task.id === 'inspect_sessions') {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-open-sessions"><span class="dashicons dashicons-desktop"></span> Inspect Sessions</button>`;
			} else if (task.id === 'app_passwords') {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-open-app-passwords"><span class="dashicons dashicons-admin-network"></span> Review Credentials</button>`;
			} else if (task.id === 'csp_report_collector') {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-open-csp-reports"><span class="dashicons dashicons-shield-alt"></span> View Reports</button> `;
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-run" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-controls-play"></span> Check Header</button>`;
			} else if (task.guide_data && task.guide_data.action === 'modal_login_rename') {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-open-login-rename"><span class="dashicons dashicons-admin-network"></span> Change Login URL</button>`;
			} else if (task.id === 'scaffold_child_theme') {
				if (task.status === 'done') {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-undo" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-undo"></span> Undo</button>`;
				} else {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-run" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-admin-appearance"></span> Create Child Theme</button>`;
				}
			} else if (task.guide_data && task.guide_data.link) {
				html += `<a href="${escapeHtml(task.guide_data.link)}" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" target="_blank"><span class="dashicons dashicons-external"></span> ${escapeHtml(task.guide_data.button_label || 'Configure')}</a>`;
			} else if (task.id === 'gsc_bing_audit') {
				html += `<a href="${escapeHtml(task.guide_data.gsc_url)}" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" target="_blank"><span class="dashicons dashicons-search"></span> GSC</a> `;
				html += `<a href="${escapeHtml(task.guide_data.bing_url)}" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" target="_blank"><span class="dashicons dashicons-admin-site"></span> Bing</a>`;
			}
		}

		// Level C: Manual SOP Tracking
		else if (task.automation_level === 'C') {
			html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-open-note" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-edit"></span> ${task.status === 'done' ? 'Edit Notes' : 'Mark Done + Note'}</button>`;
		}

		html += '</div>';
		return html;
	}

	/**
	 * Bind click listeners to table row action buttons
	 */
	function bindRowEvents() {
		// Run Button
		document.querySelectorAll('.wpsg-btn-run').forEach(btn => {
			btn.addEventListener('click', () => {
				const id = btn.getAttribute('data-id');
				runTask(id, btn);
			});
		});

		// Undo Button
		document.querySelectorAll('.wpsg-btn-undo').forEach(btn => {
			btn.addEventListener('click', () => {
				const id = btn.getAttribute('data-id');
				undoTask(id, btn);
			});
		});

		// Diff Preview Button
		document.querySelectorAll('.wpsg-btn-diff').forEach(btn => {
			btn.addEventListener('click', () => {
				const id = btn.getAttribute('data-id');
				openDiffModal(id);
			});
		});

		// Nginx Snippet Button
		document.querySelectorAll('.wpsg-btn-view-nginx').forEach(btn => {
			btn.addEventListener('click', () => {
				const id = btn.getAttribute('data-id');
				openNginxModal(id);
			});
		});

		// Manual Note / Mark Done Button
		document.querySelectorAll('.wpsg-btn-open-note').forEach(btn => {
			btn.addEventListener('click', () => {
				const id = btn.getAttribute('data-id');
				openNoteModal(id);
			});
		});

		// Login Rename Modal Button
		document.querySelectorAll('.wpsg-btn-open-login-rename').forEach(btn => {
			btn.addEventListener('click', () => {
				openModal(dom.modalLoginRename);
			});
		});

		// Open Sessions Modal Button
		document.querySelectorAll('.wpsg-btn-open-sessions').forEach(btn => {
			btn.addEventListener('click', loadSessions);
		});

		// Open App Passwords Modal Button
		document.querySelectorAll('.wpsg-btn-open-app-passwords').forEach(btn => {
			btn.addEventListener('click', loadAppPasswords);
		});

		// Open CSP Reports Modal Button
		document.querySelectorAll('.wpsg-btn-open-csp-reports').forEach(btn => {
			btn.addEventListener('click', loadCspReports);
		});
	}

	/**
	 * Run a single task via REST API
	 */
	async function runTask(taskId, btnElement = null, reauthToken = null) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		// Set button loading state
		if (btnElement) {
			btnElement.disabled = true;
			btnElement.innerHTML = '<span class="wpsg-spinner"></span> Running...';
		}

		try {
			const headers = {};
			if (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.run_task) {
				headers['X-WPSG-Nonce'] = window.wpsgData.nonces.run_task;
			}
			if (reauthToken) {
				headers['X-WPSG-Reauth'] = reauthToken;
			}

			const res = await wp.apiFetch({
				path: `/site-checkup-pro/v1/tasks/${taskId}/run`,
				method: 'POST',
				headers: headers,
				data: reauthToken ? { reauth_token: reauthToken } : {},
			});

			// Re-authentication check
			if (res && res.reauth_required) {
				if (btnElement) {
					btnElement.disabled = false;
					btnElement.innerHTML = '<span class="dashicons dashicons-lock"></span> Password Required';
				}
				requireReauth((token) => {
					runTask(taskId, btnElement, token);
				});
				return;
			}

			if (res.backup_required) {
				// Backup gate triggered! Show modal
				state.pendingActionTask = taskId;
				if (dom.backupTriggerLink && res.backup_info && res.backup_info.trigger_url) {
					dom.backupTriggerLink.href = res.backup_info.trigger_url;
					dom.backupTriggerText.textContent = `Open ${res.backup_info.plugin_name || 'Backup Plugin'}`;
				}
				openModal(dom.modalBackup);
				if (btnElement) {
					btnElement.disabled = false;
					btnElement.innerHTML = '<span class="dashicons dashicons-controls-play"></span> Run';
				}
				return;
			}

			// Update task state
			task.status = res.status || (res.success ? 'done' : 'failed');
			task.live_message = res.live_message || res.message;
			task.last_run_at = new Date().toISOString();

			// Recalculate summary metrics
			state.doneCount = state.tasks.filter(t => t.status === 'done').length;
			state.sopCoveragePct = Math.round((state.doneCount / state.totalCount) * 100);

			updateKpis();
			renderTasksTable();

		} catch (err) {
			console.error(`Task ${taskId} failed:`, err);
			task.status = 'failed';
			task.live_message = err.message || 'Execution error.';
			renderTasksTable();
		}
	}

	/**
	 * Undo a task via REST API
	 */
	async function undoTask(taskId, btnElement = null, reauthToken = null) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		if (btnElement) {
			btnElement.disabled = true;
			btnElement.innerHTML = '<span class="wpsg-spinner"></span> Undoing...';
		}

		try {
			const headers = {};
			if (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.undo_task) {
				headers['X-WPSG-Nonce'] = window.wpsgData.nonces.undo_task;
			}
			if (reauthToken) {
				headers['X-WPSG-Reauth'] = reauthToken;
			}

			const res = await wp.apiFetch({
				path: `/site-checkup-pro/v1/tasks/${taskId}/undo`,
				method: 'POST',
				headers: headers,
				data: reauthToken ? { reauth_token: reauthToken } : {},
			});

			if (res && res.reauth_required) {
				if (btnElement) {
					btnElement.disabled = false;
					btnElement.innerHTML = '<span class="dashicons dashicons-lock"></span> Password Required';
				}
				requireReauth((token) => {
					undoTask(taskId, btnElement, token);
				});
				return;
			}

			task.status = res.status || 'pending';
			task.live_message = res.live_message || res.message;

			state.doneCount = state.tasks.filter(t => t.status === 'done').length;
			state.sopCoveragePct = Math.round((state.doneCount / state.totalCount) * 100);

			updateKpis();
			renderTasksTable();
		} catch (err) {
			console.error(`Undo failed for ${taskId}:`, err);
			alert(`Undo failed: ${err.message || 'Unknown error'}`);
			if (btnElement) btnElement.disabled = false;
		}
	}

	/**
	 * Client-Driven Sequential Batch Runner ("Run All Safe Tasks")
	 * Executes one task per REST call, advances on success, pauses on error.
	 */
	async function startBatchRunner() {
		// Only select non-file-writing, instant, uncompleted Level A tasks
		const queue = state.tasks
			.filter(t => t.automation_level === 'A' && t.sub_type === 'instant' && t.status !== 'done')
			.map(t => t.id);

		if (queue.length === 0) {
			alert('All safe instant tasks are already completed!');
			return;
		}

		state.batchRunning = true;
		state.batchQueue = queue;
		state.batchTotal = queue.length;
		state.batchIndex = 0;

		dom.batchBanner.style.display = 'flex';
		dom.btnBatchRun.disabled = true;

		processNextBatchTask();
	}

	/**
	 * Process next item in sequential batch queue
	 */
	async function processNextBatchTask() {
		if (!state.batchRunning) return;

		if (state.batchIndex >= state.batchTotal) {
			// Finished all tasks!
			finishBatchRunner();
			return;
		}

		const taskId = state.batchQueue[state.batchIndex];
		const task = state.tasks.find(t => t.id === taskId);

		dom.batchCount.textContent = `${state.batchIndex + 1} / ${state.batchTotal}`;
		dom.batchText.textContent = `Running: ${task ? task.title : taskId}...`;
		const progressPct = Math.round((state.batchIndex / state.batchTotal) * 100);
		dom.batchProgress.style.width = `${progressPct}%`;

		try {
			const res = await wp.apiFetch({
				path: `/site-checkup-pro/v1/tasks/${taskId}/run`,
				method: 'POST',
			});

			if (task) {
				task.status = res.status || (res.success ? 'done' : 'failed');
				task.live_message = res.live_message || res.message;
				task.last_run_at = new Date().toISOString();
			}

			// If task failed, pause sequence and let user inspect
			if (!res.success && res.status !== 'done') {
				dom.batchText.textContent = `Paused: Task "${task.title}" reported an issue.`;
				dom.batchProgress.style.backgroundColor = '#ef4444';
				state.batchRunning = false;
				dom.btnBatchRun.disabled = false;
				renderTasksTable();
				return;
			}

			state.batchIndex++;
			renderTasksTable();

			// Short pause before next request to keep browser responsive
			setTimeout(processNextBatchTask, 400);

		} catch (err) {
			dom.batchText.textContent = `Paused on error: ${err.message || 'Execution failed'}`;
			dom.batchProgress.style.backgroundColor = '#ef4444';
			state.batchRunning = false;
			dom.btnBatchRun.disabled = false;
			renderTasksTable();
		}
	}

	function finishBatchRunner() {
		state.batchRunning = false;
		dom.batchProgress.style.width = '100%';
		dom.batchText.textContent = 'All safe tasks executed successfully!';
		dom.btnBatchRun.disabled = false;

		state.doneCount = state.tasks.filter(t => t.status === 'done').length;
		state.sopCoveragePct = Math.round((state.doneCount / state.totalCount) * 100);
		updateKpis();

		setTimeout(() => {
			dom.batchBanner.style.display = 'none';
			dom.batchProgress.style.width = '0%';
			dom.batchProgress.style.backgroundColor = '';
		}, 4000);
	}

	function stopBatchRunner() {
		state.batchRunning = false;
		dom.batchBanner.style.display = 'none';
		dom.btnBatchRun.disabled = false;
	}

	/**
	 * Open Diff Preview Modal
	 */
	async function openDiffModal(taskId) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		state.pendingActionTask = taskId;
		dom.diffFileTarget.textContent = `Target: ${task.sub_type === 'writes_files' && taskId.includes('file_edit') ? 'wp-config.php' : '.htaccess'}`;
		dom.diffContent.textContent = 'Loading diff preview...';
		openModal(dom.modalDiff);

		try {
			const res = await wp.apiFetch({
				path: `/site-checkup-pro/v1/tasks/${taskId}/diff`,
			});

			if (res.diff && res.diff.insert_block) {
				dom.diffContent.textContent = res.diff.insert_block;
			} else {
				dom.diffContent.textContent = JSON.stringify(res.diff, null, 2);
			}
		} catch (err) {
			dom.diffContent.textContent = `Error loading diff: ${err.message || 'Failed'}`;
		}
	}

	/**
	 * Open Nginx Snippet Modal
	 */
	function openNginxModal(taskId) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task || !task.nginx_snippet) return;

		dom.nginxCode.textContent = task.nginx_snippet;
		openModal(dom.modalNginx);
	}

	/**
	 * Open Manual Note Modal (Level C)
	 */
	function openNoteModal(taskId) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		state.pendingActionTask = taskId;
		dom.noteModalTitle.textContent = `Audit: ${task.title}`;
		dom.noteTaskDesc.textContent = task.description;
		dom.taskNote.value = task.note || '';
		dom.taskReminder.value = 'none';

		// Toggle password generator visibility for credential rotation tasks
		if (dom.passgenBox) {
			if (taskId.includes('password') || taskId.includes('credential')) {
				dom.passgenBox.style.display = 'flex';
				if (dom.generatedPass) dom.generatedPass.value = '';
			} else {
				dom.passgenBox.style.display = 'none';
			}
		}

		openModal(dom.modalNote);
	}

	/**
	 * Save Manual Note & Reminder via REST API
	 */
	async function saveManualNote() {
		const taskId = state.pendingActionTask;
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		const note = dom.taskNote.value.trim();
		const reminderChoice = dom.taskReminder.value;

		let nextReminderAt = null;
		const now = new Date();
		if (reminderChoice === '15_days') {
			now.setDate(now.getDate() + 15);
			nextReminderAt = now.toISOString().slice(0, 19).replace('T', ' ');
		} else if (reminderChoice === '30_days') {
			now.setDate(now.getDate() + 30);
			nextReminderAt = now.toISOString().slice(0, 19).replace('T', ' ');
		} else if (reminderChoice === '6_months') {
			now.setMonth(now.getMonth() + 6);
			nextReminderAt = now.toISOString().slice(0, 19).replace('T', ' ');
		}

		dom.btnSaveNote.disabled = true;

		try {
			await wp.apiFetch({
				path: `/site-checkup-pro/v1/tasks/${taskId}/status`,
				method: 'POST',
				headers: window.wpsgData?.nonces?.update_status ? { 'X-WPSG-Nonce': window.wpsgData.nonces.update_status } : {},
				data: {
					status: 'done',
					note: note,
					next_reminder_at: nextReminderAt,
				},
			});

			task.status = 'done';
			task.note = note;
			task.next_reminder_at = nextReminderAt;

			state.doneCount = state.tasks.filter(t => t.status === 'done').length;
			state.sopCoveragePct = Math.round((state.doneCount / state.totalCount) * 100);

			closeAllModals();
			updateKpis();
			renderTasksTable();
		} catch (err) {
			alert(`Failed to save: ${err.message || 'Error'}`);
		} finally {
			dom.btnSaveNote.disabled = false;
		}
	}

	/**
	 * Submit Custom Login Rename
	 */
	async function submitLoginRename() {
		const slug = dom.inputLoginSlug.value.trim();
		const confirm = dom.inputLoginConfirm.value.trim();

		dom.btnConfirmLoginRename.disabled = true;

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/tasks/set-login-slug',
				method: 'POST',
				headers: window.wpsgData?.nonces?.set_login_slug ? { 'X-WPSG-Nonce': window.wpsgData.nonces.set_login_slug } : {},
				data: { slug, confirm },
			});

			if (res.success) {
				alert(res.message);
				closeAllModals();
				loadTasks();
			} else {
				alert(`Failed: ${res.message}`);
				dom.btnConfirmLoginRename.disabled = false;
			}
		} catch (err) {
			alert(`Error: ${err.message || 'Could not change login URL'}`);
			dom.btnConfirmLoginRename.disabled = false;
		}
	}

	/**
	 * Submit Plugin Deletion with ZIP Backup
	 */
	async function submitDeletePlugin() {
		// Handled via task run callback
	}

	/**
	 * Confirm manual backup
	 */
	async function confirmManualBackup(callback = null) {
		try {
			await wp.apiFetch({
				path: '/site-checkup-pro/v1/tasks/confirm-backup',
				method: 'POST',
				headers: window.wpsgData?.nonces?.confirm_backup ? { 'X-WPSG-Nonce': window.wpsgData.nonces.confirm_backup } : {},
			});

			alert('Manual backup confirmation recorded. Safe file operations are now unblocked for the next 48 hours.');
			loadTasks();
			if (typeof callback === 'function') callback();
		} catch (err) {
			alert(`Failed: ${err.message || 'Error'}`);
		}
	}

	/**
	 * Update trusted baseline
	 */
	async function updateBaseline() {
		if (!confirm('Update the baseline snapshot to accept all current administrators and configuration settings as trusted?')) {
			return;
		}

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/tasks/update-baseline',
				method: 'POST',
				headers: window.wpsgData?.nonces?.update_baseline ? { 'X-WPSG-Nonce': window.wpsgData.nonces.update_baseline } : {},
			});

			alert(res.message || 'Baseline updated.');
			loadTasks();
		} catch (err) {
			alert(`Failed to update baseline: ${err.message || 'Error'}`);
		}
	}

	/**
	 * Re-authentication Modal Prompt
	 */
	function requireReauth(callback) {
		state.pendingReauthCallback = callback;
		if (dom.reauthPassword) dom.reauthPassword.value = '';
		if (dom.reauthErrorBox) dom.reauthErrorBox.style.display = 'none';
		openModal(dom.modalReauth);
		if (dom.reauthPassword) dom.reauthPassword.focus();
	}

	/**
	 * Handle Re-authentication Submit
	 */
	async function handleReauthSubmit() {
		const password = dom.reauthPassword ? dom.reauthPassword.value : '';
		if (!password) {
			if (dom.reauthErrorBox && dom.reauthErrorMessage) {
				dom.reauthErrorMessage.textContent = 'Please enter your administrator password.';
				dom.reauthErrorBox.style.display = 'flex';
			}
			return;
		}

		dom.btnReauthSubmit.disabled = true;
		dom.btnReauthSubmit.innerHTML = '<span class="wpsg-spinner"></span> Verifying...';
		if (dom.reauthErrorBox) dom.reauthErrorBox.style.display = 'none';

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/reauth',
				method: 'POST',
				headers: window.wpsgData?.nonces?.reauth ? { 'X-WPSG-Nonce': window.wpsgData.nonces.reauth } : {},
				data: { password },
			});

			if (res && res.reauth_token) {
				closeAllModals();
				const cb = state.pendingReauthCallback;
				state.pendingReauthCallback = null;
				if (typeof cb === 'function') {
					cb(res.reauth_token);
				}
			} else {
				throw new Error(res.message || 'Verification failed.');
			}
		} catch (err) {
			if (dom.reauthErrorBox && dom.reauthErrorMessage) {
				dom.reauthErrorMessage.textContent = err.message || 'Incorrect password.';
				dom.reauthErrorBox.style.display = 'flex';
			}
		} finally {
			dom.btnReauthSubmit.disabled = false;
			dom.btnReauthSubmit.innerHTML = '<span class="dashicons dashicons-unlock"></span> Verify & Proceed';
		}
	}

	/**
	 * Active User Sessions Management
	 */
	async function loadSessions() {
		if (!dom.sessionsTbody) return;
		openModal(dom.modalSessions);
		dom.sessionsTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;"><span class="wpsg-spinner"></span> Loading active sessions...</td></tr>';

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/sessions',
			});

			const sessions = (res && Array.isArray(res.sessions)) ? res.sessions : [];
			if (sessions.length === 0) {
				dom.sessionsTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No active sessions found.</td></tr>';
				return;
			}

			dom.sessionsTbody.innerHTML = sessions.map(s => `
				<tr>
					<td><code>${escapeHtml(s.ip || 'Unknown')}</code></td>
					<td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${escapeHtml(s.ua || '')}">${escapeHtml(s.ua || 'Unknown')}</td>
					<td>${escapeHtml(s.login_time || '')}</td>
					<td>
						${s.is_current ? '<span class="wpsg-chip wpsg-chip-instant">Current Device</span>' : '<span class="wpsg-chip">Remote Device</span>'}
					</td>
					<td>
						${s.is_current ? '<span class="wpsg-text-muted">Active</span>' : `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-danger wpsg-btn-destroy-session" data-verifier="${escapeHtml(s.verifier)}"><span class="dashicons dashicons-no"></span> Terminate</button>`}
					</td>
				</tr>
			`).join('');

			// Bind row terminate buttons
			dom.sessionsTbody.querySelectorAll('.wpsg-btn-destroy-session').forEach(btn => {
				btn.addEventListener('click', async () => {
					const verifier = btn.getAttribute('data-verifier');
					if (!confirm('Are you sure you want to terminate this remote session?')) return;
					btn.disabled = true;
					try {
						await wp.apiFetch({
							path: '/site-checkup-pro/v1/sessions/destroy',
							method: 'POST',
							headers: window.wpsgData?.nonces?.destroy_session ? { 'X-WPSG-Nonce': window.wpsgData.nonces.destroy_session } : {},
							data: { verifier },
						});
						loadSessions();
					} catch (err) {
						alert(`Failed to terminate session: ${err.message || 'Error'}`);
						btn.disabled = false;
					}
				});
			});
		} catch (err) {
			dom.sessionsTbody.innerHTML = `<tr><td colspan="5" class="wpsg-error-state">Failed to load sessions: ${escapeHtml(err.message)}</td></tr>`;
		}
	}

	async function destroyOtherSessions() {
		if (!confirm('Log out all other browser sessions across all devices?')) return;
		if (dom.btnDestroyOtherSessions) dom.btnDestroyOtherSessions.disabled = true;

		try {
			await wp.apiFetch({
				path: '/site-checkup-pro/v1/sessions/destroy-others',
				method: 'POST',
				headers: window.wpsgData?.nonces?.destroy_session ? { 'X-WPSG-Nonce': window.wpsgData.nonces.destroy_session } : {},
			});
			alert('All other sessions have been logged out.');
			loadSessions();
		} catch (err) {
			alert(`Failed: ${err.message || 'Error'}`);
		} finally {
			if (dom.btnDestroyOtherSessions) dom.btnDestroyOtherSessions.disabled = false;
		}
	}

	/**
	 * Application Passwords Governance
	 */
	async function loadAppPasswords() {
		if (!dom.appPasswordsTbody) return;
		openModal(dom.modalAppPasswords);
		dom.appPasswordsTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;"><span class="wpsg-spinner"></span> Loading application passwords...</td></tr>';

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/app-passwords',
			});

			const passwords = (res && Array.isArray(res.passwords)) ? res.passwords : [];
			if (passwords.length === 0) {
				dom.appPasswordsTbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No application passwords currently registered.</td></tr>';
				return;
			}

			dom.appPasswordsTbody.innerHTML = passwords.map(p => `
				<tr>
					<td><strong>${escapeHtml(p.name)}</strong></td>
					<td><code>${escapeHtml(p.user_login)}</code></td>
					<td>${escapeHtml(p.created || '')}</td>
					<td>${escapeHtml(p.last_used || 'Never')} ${p.last_ip !== 'N/A' ? `(${escapeHtml(p.last_ip)})` : ''}</td>
					<td>
						<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-danger wpsg-btn-revoke-app-pass" data-uuid="${escapeHtml(p.uuid)}" data-user-id="${escapeHtml(p.user_id)}">
							<span class="dashicons dashicons-trash"></span> Revoke
						</button>
					</td>
				</tr>
			`).join('');

			// Bind revoke buttons with confirmation and reauth
			dom.appPasswordsTbody.querySelectorAll('.wpsg-btn-revoke-app-pass').forEach(btn => {
				btn.addEventListener('click', () => {
					const uuid = btn.getAttribute('data-uuid');
					const userId = btn.getAttribute('data-user-id');
					if (!confirm('WARNING: Revoking this application password will permanently break external REST API clients, third-party integrations, or mobile apps using it. Proceed?')) {
						return;
					}

					requireReauth(async (token) => {
						btn.disabled = true;
						try {
							const revRes = await wp.apiFetch({
								path: '/site-checkup-pro/v1/app-passwords/revoke',
								method: 'POST',
								headers: {
									'X-WPSG-Nonce': window.wpsgData?.nonces?.revoke_app_pass || '',
									'X-WPSG-Reauth': token,
								},
								data: {
									uuid: uuid,
									user_id: userId,
									reauth_token: token,
								},
							});

							if (revRes && revRes.success) {
								alert('Application password revoked successfully.');
								loadAppPasswords();
							} else {
								alert(`Failed: ${revRes.message || 'Error'}`);
								btn.disabled = false;
							}
						} catch (err) {
							alert(`Failed to revoke: ${err.message || 'Error'}`);
							btn.disabled = false;
						}
					});
				});
			});
		} catch (err) {
			dom.appPasswordsTbody.innerHTML = `<tr><td colspan="5" class="wpsg-error-state">Failed to load application passwords: ${escapeHtml(err.message)}</td></tr>`;
		}
	}

	/**
	 * CSP Reports Viewer
	 */
	async function loadCspReports() {
		if (!dom.cspTbody) return;
		openModal(dom.modalCspReports);
		dom.cspTbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;"><span class="wpsg-spinner"></span> Loading CSP violation records...</td></tr>';

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/csp-reports',
			});

			const reports = (res && Array.isArray(res.reports)) ? res.reports : [];
			if (reports.length === 0) {
				dom.cspTbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No CSP violation reports recorded yet. (Policy running clean).</td></tr>';
				return;
			}

			dom.cspTbody.innerHTML = reports.map(r => `
				<tr>
					<td style="font-variant-numeric: tabular-nums;">${escapeHtml(r.timestamp)}</td>
					<td><code>${escapeHtml(r.violated_directive || 'N/A')}</code></td>
					<td style="word-break: break-all;"><code>${escapeHtml(r.blocked_uri || 'self')}</code></td>
					<td style="word-break: break-all;">${escapeHtml(r.document_uri || '')}</td>
				</tr>
			`).join('');
		} catch (err) {
			dom.cspTbody.innerHTML = `<tr><td colspan="4" class="wpsg-error-state">Failed to load CSP reports: ${escapeHtml(err.message)}</td></tr>`;
		}
	}

	/**
	 * Load audit logs into Audit View
	 */
	async function loadAuditLogs() {
		if (!dom.auditTbody) return;

		dom.auditTbody.innerHTML = '<tr><td colspan="6"><span class="wpsg-spinner"></span> Loading audit trail...</td></tr>';

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/audit-log',
			});

			const logs = (res && Array.isArray(res.logs)) ? res.logs : [];

			if (logs.length === 0) {
				const emptyImg = (window.wpsgData && window.wpsgData.mediaUrl) ? `${window.wpsgData.mediaUrl}empty-state.svg` : '';
				dom.auditTbody.innerHTML = `
					<tr>
						<td colspan="6">
							<div class="wpsg-empty-state">
								${emptyImg ? `<img src="${emptyImg}" width="120" height="85" alt="" />` : '<span class="dashicons dashicons-portfolio"></span>'}
								<h4>No audit log records found</h4>
								<p>Security actions, file modifications, and baseline checks will appear here once executed.</p>
							</div>
						</td>
					</tr>
				`;
				return;
			}

			dom.auditTbody.innerHTML = logs.map(l => `
				<tr>
					<td style="font-variant-numeric: tabular-nums;">${escapeHtml(l.created_at)}</td>
					<td><code>${escapeHtml(l.task_id)}</code></td>
					<td>${escapeHtml(l.action)}</td>
					<td>${escapeHtml(l.display_name || l.user_login || 'System')}</td>
					<td>
						<span class="wpsg-status-indicator wpsg-status-${l.result === 'success' ? 'done' : 'critical'}">
							<span class="wpsg-status-dot"></span> ${escapeHtml(l.result === 'success' ? 'Success' : 'Failed')}
						</span>
					</td>
					<td>${escapeHtml(l.message || '')}</td>
				</tr>
			`).join('');
		} catch (err) {
			dom.auditTbody.innerHTML = `<tr><td colspan="6" class="wpsg-error-state">Error loading audit log: ${escapeHtml(err.message)}</td></tr>`;
		}
	}

	/**
	 * Modal Helpers
	 */
	function openModal(modal) {
		if (modal) modal.style.display = 'flex';
	}

	function closeAllModals() {
		document.querySelectorAll('.wpsg-modal-overlay, .wpsg-modal-backdrop').forEach(m => {
			m.style.display = 'none';
		});
		state.pendingActionTask = null;
		if (dom.inputLoginConfirm) dom.inputLoginConfirm.value = '';
		if (dom.btnConfirmLoginRename) dom.btnConfirmLoginRename.disabled = true;
	}

	/**
	 * Settings Modal Operations
	 */
	async function openSettingsModal() {
		if (!dom.modalSettings) return;
		openModal(dom.modalSettings);
		if (dom.settingsSaveStatus) dom.settingsSaveStatus.textContent = 'Loading settings...';
		try {
			const res = await wp.apiFetch({ path: '/site-checkup-pro/v1/settings' });
			if (res && res.settings) {
				if (dom.settingPatchstackKey) dom.settingPatchstackKey.value = '';
				if (dom.patchstackMaskedStatus) {
					dom.patchstackMaskedStatus.textContent = res.settings.has_patchstack_key
						? `Current Key: ${res.settings.patchstack_api_key_masked}`
						: 'No API key set (default checks active).';
				}
				if (dom.settingPatchstackOptin) dom.settingPatchstackOptin.checked = !!res.settings.patchstack_optin;
				if (dom.settingWebhookUrl) dom.settingWebhookUrl.value = res.settings.webhook_url || '';
				if (dom.settingWebhookOptin) dom.settingWebhookOptin.checked = !!res.settings.webhook_optin;
				if (dom.settingIncidentName) dom.settingIncidentName.value = res.settings.incident_contact_name || '';
				if (dom.settingIncidentEmail) dom.settingIncidentEmail.value = res.settings.incident_contact_email || '';
				if (dom.settingIncidentPhone) dom.settingIncidentPhone.value = res.settings.incident_contact_phone || '';
				if (dom.settingIncidentNotes) dom.settingIncidentNotes.value = res.settings.incident_contact_notes || '';
				if (dom.settingAgencyName) dom.settingAgencyName.value = res.settings.agency_name || '';
				if (dom.settingsSaveStatus) dom.settingsSaveStatus.textContent = '';
			}
		} catch (e) {
			if (dom.settingsSaveStatus) dom.settingsSaveStatus.textContent = 'Error loading settings.';
		}
	}

	async function saveSettings() {
		if (!dom.btnSaveSettings) return;
		const orig = dom.btnSaveSettings.innerHTML;
		dom.btnSaveSettings.disabled = true;
		dom.btnSaveSettings.innerHTML = '<span class="wpsg-spinner" aria-hidden="true"></span> Saving...';
		if (dom.settingsSaveStatus) dom.settingsSaveStatus.textContent = '';

		const payload = {};
		if (dom.settingPatchstackKey && dom.settingPatchstackKey.value.trim()) {
			payload.patchstack_api_key = dom.settingPatchstackKey.value.trim();
		}
		if (dom.settingPatchstackOptin) payload.patchstack_optin = dom.settingPatchstackOptin.checked ? 1 : 0;
		if (dom.settingWebhookUrl) payload.webhook_url = dom.settingWebhookUrl.value.trim();
		if (dom.settingWebhookOptin) payload.webhook_optin = dom.settingWebhookOptin.checked ? 1 : 0;
		if (dom.settingIncidentName) payload.incident_contact_name = dom.settingIncidentName.value.trim();
		if (dom.settingIncidentEmail) payload.incident_contact_email = dom.settingIncidentEmail.value.trim();
		if (dom.settingIncidentPhone) payload.incident_contact_phone = dom.settingIncidentPhone.value.trim();
		if (dom.settingIncidentNotes) payload.incident_contact_notes = dom.settingIncidentNotes.value.trim();
		if (dom.settingAgencyName) payload.agency_name = dom.settingAgencyName.value.trim();

		try {
			const res = await wp.apiFetch({
				path: '/site-checkup-pro/v1/settings',
				method: 'POST',
				data: payload,
			});
			if (res && res.success) {
				if (dom.settingsSaveStatus) dom.settingsSaveStatus.textContent = 'Settings saved!';
				setTimeout(() => {
					closeAllModals();
					loadTasks();
				}, 600);
			}
		} catch (e) {
			if (dom.settingsSaveStatus) dom.settingsSaveStatus.textContent = e.message || 'Failed to save settings.';
		} finally {
			dom.btnSaveSettings.disabled = false;
			dom.btnSaveSettings.innerHTML = orig;
		}
	}

	/**
	 * Utilities
	 */
	function escapeHtml(str) {
		if (!str) return '';
		return String(str)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function formatDate(dateStr) {
		try {
			const d = new Date(dateStr);
			return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
		} catch (e) {
			return dateStr;
		}
	}

	function initReviewPrompt() {
		const promptEl = document.getElementById('wpsg-review-prompt');
		if (!promptEl) return;

		const dismissHandler = async () => {
			promptEl.style.display = 'none';
			try {
				await fetch(`${apiBase}/review-prompt/dismiss`, {
					method: 'POST',
					headers: { 'X-WP-Nonce': nonce },
				});
			} catch (e) {
				console.error('Failed to dismiss review prompt:', e);
			}
		};

		const btnNow = document.getElementById('wpsg-btn-review-now');
		const btnAlready = document.getElementById('wpsg-btn-review-already');
		const btnDismiss = document.getElementById('wpsg-btn-review-dismiss');

		if (btnNow) {
			btnNow.addEventListener('click', () => {
				setTimeout(dismissHandler, 500);
			});
		}
		if (btnAlready) {
			btnAlready.addEventListener('click', dismissHandler);
		}
		if (btnDismiss) {
			btnDismiss.addEventListener('click', dismissHandler);
		}
	}

	// Boot on DOM Ready
	document.addEventListener('DOMContentLoaded', init);

})();
