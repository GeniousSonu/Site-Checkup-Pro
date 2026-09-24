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

	// Boot Guard
	let initialized = false;

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
		loading: true,
		loadError: null,
		batchRunning: false,
		batchQueue: [],
		batchTotal: 0,
		batchIndex: 0,
		pendingActionTask: null,
		pendingDeletePlugin: null,
		reauthToken: null,
		reauthExpiresAt: 0,
		justUpdatedTaskId: null,
		restData: null,
		restLoading: false,
		restFilter: 'all',
		restSearch: '',
		diagnosticMarkdown: '',
		diagnosticLoading: false,
	};

	// DOM Elements Cache
	const dom = {};

	/**
	 * Retrieve active session-bound re-auth token if still within trusted window
	 */
	function getActiveReauthToken() {
		if (state.reauthToken && state.reauthExpiresAt > Date.now()) {
			return state.reauthToken;
		}
		try {
			const stored = sessionStorage.getItem('wpsg_reauth_token');
			const expires = parseInt(sessionStorage.getItem('wpsg_reauth_expires') || '0', 10);
			if (stored && expires > Date.now()) {
				state.reauthToken = stored;
				state.reauthExpiresAt = expires;
				return stored;
			}
		} catch (_) {}
		return null;
	}

	/**
	 * Set re-auth token with 20-30 minute trusted window
	 */
	function setReauthToken(token, expiresInSeconds = 1800) {
		const expiresAt = Date.now() + (expiresInSeconds * 1000);
		state.reauthToken = token;
		state.reauthExpiresAt = expiresAt;
		try {
			sessionStorage.setItem('wpsg_reauth_token', token);
			sessionStorage.setItem('wpsg_reauth_expires', expiresAt.toString());
		} catch (_) {}
	}

	/**
	 * Clear re-auth token on expiration or explicit rejection
	 */
	function clearReauthToken() {
		state.reauthToken = null;
		state.reauthExpiresAt = 0;
		try {
			sessionStorage.removeItem('wpsg_reauth_token');
			sessionStorage.removeItem('wpsg_reauth_expires');
		} catch (_) {}
	}

	/**
	 * Helper: Identify read-only instant scanner/audit tasks
	 */
	function isScannerTask(task) {
		if (!task || task.automation_level !== 'A' || task.sub_type !== 'instant') {
			return false;
		}
		const runtimeActionIds = [
			'trigger_backup',
			'toggle_auto_updates',
			'disable_xmlrpc',
			'restrict_rest_api',
			'block_user_enumeration',
			'login_hardening',
			'hide_wordpress_fingerprint',
			'strip_script_versions',
			'security_headers_csp',
			'admin_notice_focus_mode'
		];
		return runtimeActionIds.indexOf(task.id) === -1;
	}

	/**
	 * Resilient REST API caller with automatic nonce injection & native fetch fallback
	 */
	async function apiCall(options) {
		const path = options.path;
		const method = options.method || 'GET';
		const data = options.data || null;
		const headers = Object.assign({}, options.headers || {});

		// Ensure standard WP REST nonce is present for cookie authentication
		if (window.wpsgData && window.wpsgData.nonce && !headers['X-WP-Nonce']) {
			headers['X-WP-Nonce'] = window.wpsgData.nonce;
		}

		// Automatically attach active trusted reauth token if present
		const activeReauth = getActiveReauthToken();
		if (activeReauth && !headers['X-WPSG-Reauth']) {
			headers['X-WPSG-Reauth'] = activeReauth;
		}

		// Try window.wp.apiFetch first if available
		if (window.wp && typeof window.wp.apiFetch === 'function') {
			try {
				const apiFetchOpts = {
					path: path,
					method: method,
					headers: headers,
				};
				if (data && method !== 'GET') {
					apiFetchOpts.data = data;
				}
				return await window.wp.apiFetch(apiFetchOpts);
			} catch (fetchErr) {
				// If server returned structured WP_Error with code and message, re-throw it
				if (fetchErr && fetchErr.code && fetchErr.message) {
					throw fetchErr;
				}
				// Otherwise fall through to native fetch fallback
				console.warn('wp.apiFetch encountered an error, attempting native fetch fallback:', fetchErr);
			}
		}

		// Native fetch fallback
		const baseUrl = (window.wpsgData && window.wpsgData.restUrl)
			? window.wpsgData.restUrl.replace(/\/$/, '')
			: '/wp-json/site-checkup-pro/v1';
		const relativePath = path.replace(/^\/site-checkup-pro\/v1/, '');
		const fullUrl = baseUrl + relativePath;

		const fetchOptions = {
			method: method,
			headers: Object.assign({
				'Accept': 'application/json',
				'Content-Type': 'application/json',
			}, headers),
			credentials: 'same-origin',
		};

		if (data && method !== 'GET') {
			fetchOptions.body = JSON.stringify(data);
		}

		const response = await fetch(fullUrl, fetchOptions);
		let responseData = {};
		try {
			responseData = await response.json();
		} catch (_) {
			responseData = {};
		}

		if (!response.ok) {
			const msg = responseData.message || ('Server error (' + response.status + ')');
			const err = new Error(msg);
			err.status = response.status;
			err.code = responseData.code;
			throw err;
		}

		return responseData;
	}

	/**
	 * Apply tasks catalog payload into local state and refresh views
	 */
	function applyTasksPayload(res) {
		if (!res) return;
		state.tasks = Array.isArray(res.tasks) ? res.tasks : Object.values(res.tasks || {});
		state.sections = res.sections || {};
		state.safeInstantIds = res.safe_instant_ids || [];
		state.totalCount = res.total_count || state.tasks.length || 0;
		state.doneCount = res.done_count || 0;
		state.sopCoveragePct = res.sop_coverage_pct || 0;
		if (res.server_type) state.serverType = res.server_type;
		if (typeof res.supports_htaccess !== 'undefined') state.supportsHtaccess = res.supports_htaccess;
		state.backupStatus = res.backup_status || {};
		state.loading = false;
		state.loadError = null;

		updateKpis();
		renderViews();
	}

	/**
	 * Initialize Application
	 */
	function init() {
		if (initialized) return;

		cacheDom();
		if (!dom.app) return;
		initialized = true;

		// Setup nonce middleware on wp.apiFetch if available
		if (window.wp && window.wp.apiFetch && window.wp.apiFetch.createNonceMiddleware && window.wpsgData && window.wpsgData.nonce) {
			try {
				window.wp.apiFetch.use(window.wp.apiFetch.createNonceMiddleware(window.wpsgData.nonce));
			} catch (_) {}
		}

		bindEvents();

		// Hydrate immediately from server-rendered initial data (Zero-latency UI render)
		if (window.wpsgData && window.wpsgData.initialData && window.wpsgData.initialData.tasks) {
			applyTasksPayload(window.wpsgData.initialData);
		} else {
			state.loading = true;
		}

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
		dom.panelRestApi = document.getElementById('wpsg-panel-rest-api');
		dom.panelDevToolkit = document.getElementById('wpsg-panel-dev-toolkit');

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

		// Standardized Confirmation Modal
		dom.modalConfirm = document.getElementById('wpsg-modal-confirm');
		dom.confirmTitleText = document.getElementById('wpsg-confirm-title-text');
		dom.confirmIcon = document.getElementById('wpsg-confirm-icon');
		dom.confirmMessage = document.getElementById('wpsg-confirm-message');
		dom.confirmNotice = document.getElementById('wpsg-confirm-notice');
		dom.confirmNoticeText = document.getElementById('wpsg-confirm-notice-text');
		dom.btnConfirmCancel = document.getElementById('wpsg-btn-confirm-cancel');
		dom.btnConfirmSubmit = document.getElementById('wpsg-btn-confirm-submit');

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
		dom.patchstackMaskedStatus = document.getElementById('wpsg-patchstack-masked-status');

		dom.settingGhsaKey = document.getElementById('wpsg-setting-ghsa-key');
		dom.settingGhsaOptin = document.getElementById('wpsg-setting-ghsa-optin');
		dom.ghsaMaskedStatus = document.getElementById('wpsg-ghsa-masked-status');

		dom.settingOsvKey = document.getElementById('wpsg-setting-osv-key');
		dom.settingOsvOptin = document.getElementById('wpsg-setting-osv-optin');
		dom.osvMaskedStatus = document.getElementById('wpsg-osv-masked-status');

		dom.settingNvdKey = document.getElementById('wpsg-setting-nvd-key');
		dom.settingNvdOptin = document.getElementById('wpsg-setting-nvd-optin');
		dom.nvdMaskedStatus = document.getElementById('wpsg-nvd-masked-status');

		dom.settingCisaKevKey = document.getElementById('wpsg-setting-cisa-kev-key');
		dom.settingCisaKevOptin = document.getElementById('wpsg-setting-cisa-kev-optin');
		dom.cisaKevMaskedStatus = document.getElementById('wpsg-cisa-kev-masked-status');

		dom.settingWpscanToken = document.getElementById('wpsg-setting-wpscan-token');
		dom.settingWpscanOptin = document.getElementById('wpsg-setting-wpscan-optin');
		dom.wpscanMaskedStatus = document.getElementById('wpsg-wpscan-masked-status');

		dom.panelDetectionInfo = document.getElementById('wpsg-panel-detection-info');
		dom.settingPanelType = document.getElementById('wpsg-setting-panel-type');
		dom.settingPanelUrl = document.getElementById('wpsg-setting-panel-url');
		dom.settingPanelToken = document.getElementById('wpsg-setting-panel-token');
		dom.panelMaskedStatus = document.getElementById('wpsg-panel-masked-status');
		dom.settingPanelOptin = document.getElementById('wpsg-setting-panel-optin');
		dom.settingWebhookUrl = document.getElementById('wpsg-setting-webhook-url');
		dom.settingWebhookOptin = document.getElementById('wpsg-setting-webhook-optin');
		dom.settingIncidentName = document.getElementById('wpsg-setting-incident-name');
		dom.settingIncidentEmail = document.getElementById('wpsg-setting-incident-email');
		dom.settingIncidentPhone = document.getElementById('wpsg-setting-incident-phone');
		dom.settingIncidentNotes = document.getElementById('wpsg-setting-incident-notes');
		dom.settingAgencyName = document.getElementById('wpsg-setting-agency-name');
		dom.settingsSaveStatus = document.getElementById('wpsg-settings-save-status');

		// Posture Hero & Gauge
		dom.posturePct = document.getElementById('wpsg-posture-pct');
		dom.postureGrade = document.getElementById('wpsg-posture-grade');
		dom.postureStatsText = document.getElementById('wpsg-posture-stats-text');
		dom.gaugeCircle = document.getElementById('wpsg-gauge-circle');
		dom.btnHeroRunSafe = document.getElementById('wpsg-btn-hero-run-safe');
		dom.btnHeroViewAll = document.getElementById('wpsg-btn-hero-view-all');

		// Attention queue on Overview
		dom.overviewAttention = document.getElementById('wpsg-overview-attention');
		dom.attentionCount = document.getElementById('wpsg-attention-count');
		dom.attentionItems = document.getElementById('wpsg-attention-items');

		// In-page Settings
		dom.pageSettingPatchstackKey = document.getElementById('wpsg-page-setting-patchstack-key');
		dom.pagePatchstackMaskedStatus = document.getElementById('wpsg-page-patchstack-masked-status');
		dom.pageSettingPatchstackOptin = document.getElementById('wpsg-page-setting-patchstack-optin');

		dom.pageSettingGhsaKey = document.getElementById('wpsg-page-setting-ghsa-key');
		dom.pageSettingGhsaOptin = document.getElementById('wpsg-page-setting-ghsa-optin');
		dom.pageGhsaMaskedStatus = document.getElementById('wpsg-page-ghsa-masked-status');

		dom.pageSettingOsvKey = document.getElementById('wpsg-page-setting-osv-key');
		dom.pageSettingOsvOptin = document.getElementById('wpsg-page-setting-osv-optin');
		dom.pageOsvMaskedStatus = document.getElementById('wpsg-page-osv-masked-status');

		dom.pageSettingNvdKey = document.getElementById('wpsg-page-setting-nvd-key');
		dom.pageSettingNvdOptin = document.getElementById('wpsg-page-setting-nvd-optin');
		dom.pageNvdMaskedStatus = document.getElementById('wpsg-page-nvd-masked-status');

		dom.pageSettingCisaKevKey = document.getElementById('wpsg-page-setting-cisa-kev-key');
		dom.pageSettingCisaKevOptin = document.getElementById('wpsg-page-setting-cisa-kev-optin');
		dom.pageCisaKevMaskedStatus = document.getElementById('wpsg-page-cisa-kev-masked-status');

		dom.pageSettingWpscanToken = document.getElementById('wpsg-page-setting-wpscan-token');
		dom.pageSettingWpscanOptin = document.getElementById('wpsg-page-setting-wpscan-optin');
		dom.pageWpscanMaskedStatus = document.getElementById('wpsg-page-wpscan-masked-status');

		dom.pagePanelDetectionInfo = document.getElementById('wpsg-page-panel-detection-info');
		dom.pageSettingPanelType = document.getElementById('wpsg-page-setting-panel-type');
		dom.pageSettingPanelUrl = document.getElementById('wpsg-page-setting-panel-url');
		dom.pageSettingPanelToken = document.getElementById('wpsg-page-setting-panel-token');
		dom.pagePanelMaskedStatus = document.getElementById('wpsg-page-panel-masked-status');
		dom.pageSettingPanelOptin = document.getElementById('wpsg-page-setting-panel-optin');
		dom.pageSettingWebhookUrl = document.getElementById('wpsg-page-setting-webhook-url');
		dom.pageSettingWebhookOptin = document.getElementById('wpsg-page-setting-webhook-optin');
		dom.pageSettingIncidentName = document.getElementById('wpsg-page-setting-incident-name');
		dom.pageSettingIncidentEmail = document.getElementById('wpsg-page-setting-incident-email');
		dom.pageSettingIncidentPhone = document.getElementById('wpsg-page-setting-incident-phone');
		dom.pageSettingAgencyName = document.getElementById('wpsg-page-setting-agency-name');
		dom.pageSettingIncidentNotes = document.getElementById('wpsg-page-setting-incident-notes');
		dom.pageSettingsSaveStatus = document.getElementById('wpsg-page-settings-save-status');
		dom.btnPageSaveSettings = document.getElementById('wpsg-btn-page-save-settings');

		// REST API Security Auditor Controls
		dom.btnRefreshRestAudit = document.getElementById('wpsg-btn-refresh-rest-audit');
		dom.restStatTotal = document.getElementById('wpsg-rest-stat-total');
		dom.restStatProtected = document.getElementById('wpsg-rest-stat-protected');
		dom.restStatPublic = document.getElementById('wpsg-rest-stat-public');
		dom.restStatHigh = document.getElementById('wpsg-rest-stat-high');
		dom.restSearch = document.getElementById('wpsg-rest-search');
		dom.restTbody = document.getElementById('wpsg-rest-tbody');
		dom.restFilterBtns = document.querySelectorAll('[data-rest-filter]');

		// Developer Toolkit Controls
		dom.devEnvSelect = document.getElementById('wpsg-dev-env-select');
		dom.btnSaveEnv = document.getElementById('wpsg-btn-save-env');
		dom.devEnvHelp = document.getElementById('wpsg-dev-env-help');
		dom.btnCopyDiagnostic = document.getElementById('wpsg-btn-copy-diagnostic');
		dom.btnViewDiagnostic = document.getElementById('wpsg-btn-view-diagnostic');
		dom.modalDiagnostic = document.getElementById('wpsg-modal-diagnostic');
		dom.diagnosticContent = document.getElementById('wpsg-diagnostic-content');
		dom.btnDiagnosticModalCopy = document.getElementById('wpsg-btn-diagnostic-modal-copy');
		dom.devCronSummary = document.getElementById('wpsg-dev-cron-summary');
		dom.btnRefreshCron = document.getElementById('wpsg-btn-refresh-cron');
		dom.devDbSummary = document.getElementById('wpsg-dev-db-summary');
		dom.btnCleanDb = document.getElementById('wpsg-btn-clean-db');
		dom.devMigrationSummary = document.getElementById('wpsg-dev-migration-summary');
		dom.btnCheckMigration = document.getElementById('wpsg-btn-check-migration');
		dom.devChangelogSummary = document.getElementById('wpsg-dev-changelog-summary');
		dom.btnRefreshDigest = document.getElementById('wpsg-btn-refresh-digest');
	}

	/**
	 * Bind UI event listeners
	 */
	function bindEvents() {
		if (!dom.app) return;

		// 1. Delegated Click Listener on dom.app for 100% reliable interaction across re-renders
		dom.app.addEventListener('click', (e) => {
			// Tab buttons (.wpsg-tab)
			const tabBtn = e.target.closest('.wpsg-tab');
			if (tabBtn) {
				e.preventDefault();
				const targetTab = tabBtn.getAttribute('data-tab');
				if (targetTab) switchTab(targetTab);
				return;
			}

			// Overview category card buttons [data-open-tab]
			const openTabBtn = e.target.closest('[data-open-tab]');
			if (openTabBtn) {
				e.preventDefault();
				e.stopPropagation();
				const targetTab = openTabBtn.getAttribute('data-open-tab');
				if (targetTab) switchTab(targetTab);
				return;
			}

			// Overview category cards (.wpsg-category-card)
			const catCard = e.target.closest('.wpsg-category-card');
			if (catCard && !e.target.closest('button, a')) {
				const targetSec = catCard.getAttribute('data-section-target');
				if (targetSec) switchTab(targetSec);
				return;
			}

			// Attention item Go / Inspect button (.wpsg-attention-go-btn)
			const attGoBtn = e.target.closest('.wpsg-attention-go-btn');
			if (attGoBtn) {
				e.preventDefault();
				const targetSec = attGoBtn.getAttribute('data-go-section') || 'all';
				const targetTaskId = attGoBtn.getAttribute('data-go-task') || '';
				switchTab(targetSec, true);
				if (targetTaskId && dom.searchTasks) {
					dom.searchTasks.value = targetTaskId;
					state.searchQuery = targetTaskId;
					renderTasksTable();
				}
				return;
			}

			// Modal close buttons [data-close-modal]
			const closeBtn = e.target.closest('[data-close-modal]');
			if (closeBtn) {
				e.preventDefault();
				closeAllModals();
				return;
			}

			// Table Row Actions: Verify Button ("Check Now")
			const verifyBtn = e.target.closest('.wpsg-btn-verify');
			if (verifyBtn) {
				e.preventDefault();
				const id = verifyBtn.getAttribute('data-id');
				if (id) verifyTask(id, verifyBtn);
				return;
			}

			// Table Row Actions: Run Button
			const runBtn = e.target.closest('.wpsg-btn-run');
			if (runBtn) {
				e.preventDefault();
				const id = runBtn.getAttribute('data-id');
				if (id) runTask(id, runBtn);
				return;
			}

			// Table Row Actions: Undo Button
			const undoBtn = e.target.closest('.wpsg-btn-undo');
			if (undoBtn) {
				e.preventDefault();
				const id = undoBtn.getAttribute('data-id');
				if (id) undoTask(id, undoBtn);
				return;
			}

			// Table Row Actions: Diff Button
			const diffBtn = e.target.closest('.wpsg-btn-diff');
			if (diffBtn) {
				e.preventDefault();
				const id = diffBtn.getAttribute('data-id');
				if (id) openDiffModal(id);
				return;
			}

			// Table Row Actions: Nginx Snippet Button
			const nginxBtn = e.target.closest('.wpsg-btn-view-nginx');
			if (nginxBtn) {
				e.preventDefault();
				const id = nginxBtn.getAttribute('data-id');
				if (id) openNginxModal(id);
				return;
			}

			// Table Row Actions: Note / Mark Done Button
			const noteBtn = e.target.closest('.wpsg-btn-open-note');
			if (noteBtn) {
				e.preventDefault();
				const id = noteBtn.getAttribute('data-id');
				if (id) openNoteModal(id);
				return;
			}

			// Table Row Actions: Login Rename Button
			const openLoginRenameBtn = e.target.closest('.wpsg-btn-open-login-rename');
			if (openLoginRenameBtn) {
				e.preventDefault();
				openModal(dom.modalLoginRename);
				return;
			}

			// Table Row Actions: Sessions Modal Button
			const openSessionsBtn = e.target.closest('.wpsg-btn-open-sessions');
			if (openSessionsBtn) {
				e.preventDefault();
				loadSessions();
				return;
			}

			// Table Row Actions: App Passwords Modal Button
			const openAppPassBtn = e.target.closest('.wpsg-btn-open-app-passwords');
			if (openAppPassBtn) {
				e.preventDefault();
				loadAppPasswords();
				return;
			}

			// Table Row Actions: CSP Reports Modal Button
			const openCspBtn = e.target.closest('.wpsg-btn-open-csp-reports');
			if (openCspBtn) {
				e.preventDefault();
				loadCspReports();
				return;
			}

			// Table Row Actions: Open Delete Plugin Modal
			const openDelPluginBtn = e.target.closest('.wpsg-btn-open-delete-plugin');
			if (openDelPluginBtn) {
				e.preventDefault();
				const slug = openDelPluginBtn.getAttribute('data-slug');
				const name = openDelPluginBtn.getAttribute('data-name');
				const path = openDelPluginBtn.getAttribute('data-path');
				openDeletePluginModal(slug, name, path);
				return;
			}

			// Table Row Actions: Verify Fix on Vulnerability Finding
			const verifyVulnBtn = e.target.closest('.wpsg-btn-verify-vuln-fix');
			if (verifyVulnBtn) {
				e.preventDefault();
				const slug = verifyVulnBtn.getAttribute('data-slug');
				const type = verifyVulnBtn.getAttribute('data-type') || 'plugin';
				if (slug) verifyVulnerabilityFix(slug, type, verifyVulnBtn);
				return;
			}
		});

		// Close modals on overlay backdrop click
		document.querySelectorAll('.wpsg-modal-overlay').forEach(overlay => {
			overlay.addEventListener('click', (e) => {
				if (e.target === overlay) {
					closeAllModals();
				}
			});
		});

		// Close modal on escape key
		window.addEventListener('keydown', e => {
			if (e.key === 'Escape') closeAllModals();
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

		// Hero Posture Banner Buttons
		if (dom.btnHeroRunSafe) {
			dom.btnHeroRunSafe.addEventListener('click', runBatchSafeTasks);
		}
		if (dom.btnHeroViewAll) {
			dom.btnHeroViewAll.addEventListener('click', () => switchTab('all'));
		}

		// In-page Settings Save Button
		if (dom.btnPageSaveSettings) {
			dom.btnPageSaveSettings.addEventListener('click', () => saveSettings(true));
		}

		// Quick Buttons in Overview
		if (dom.btnQuickLoginUrl) {
			dom.btnQuickLoginUrl.addEventListener('click', () => {
				switchTab('features');
				if (dom.featLoginSlug) dom.featLoginSlug.focus();
			});
		}
		if (dom.btnQuickSessions) {
			dom.btnQuickSessions.addEventListener('click', loadSessions);
		}

		// Features Panel Event Listeners
		if (dom.btnSaveFeatLogin) {
			dom.btnSaveFeatLogin.addEventListener('click', saveFeatureLoginSlug);
		}
		if (dom.btnResetFeatLogin) {
			dom.btnResetFeatLogin.addEventListener('click', resetFeatureLoginSlug);
		}
		if (dom.btnFeatViewSessions) {
			dom.btnFeatViewSessions.addEventListener('click', loadSessions);
		}
		if (dom.btnFeatDestroySessions) {
			dom.btnFeatDestroySessions.addEventListener('click', destroyOtherSessions);
		}
		if (dom.btnFeatAppPasswords) {
			dom.btnFeatAppPasswords.addEventListener('click', loadAppPasswords);
		}
		if (dom.btnFeatCspReports) {
			dom.btnFeatCspReports.addEventListener('click', loadCspReports);
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
				const code = dom.nginxCode ? dom.nginxCode.textContent : '';
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(code).then(() => {
						const originalText = dom.btnCopyNginx.innerHTML;
						dom.btnCopyNginx.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
						setTimeout(() => {
							dom.btnCopyNginx.innerHTML = originalText;
						}, 2000);
					});
				}
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
				if (window.crypto && window.crypto.getRandomValues) {
					window.crypto.getRandomValues(array);
					for (let i = 0; i < 24; i++) {
						pass += chars[array[i] % chars.length];
					}
				} else {
					for (let i = 0; i < 24; i++) {
						pass += chars.charAt(Math.floor(Math.random() * chars.length));
					}
				}
				if (dom.generatedPass) dom.generatedPass.value = pass;
			});
		}
		if (dom.btnCopyPass) {
			dom.btnCopyPass.addEventListener('click', () => {
				if (!dom.generatedPass || !dom.generatedPass.value) return;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(dom.generatedPass.value).then(() => {
						const orig = dom.btnCopyPass.innerHTML;
						dom.btnCopyPass.innerHTML = '<span class="dashicons dashicons-yes"></span> Copied!';
						setTimeout(() => { dom.btnCopyPass.innerHTML = orig; }, 2000);
					});
				}
			});
		}

		// Login Renamer Input Validation (Typing 'CHANGE' required)
		function updateLoginRenameButton() {
			if (!dom.btnConfirmLoginRename) return;
			const isConfirmed = dom.inputLoginConfirm ? dom.inputLoginConfirm.value.trim() === 'CHANGE' : false;
			const hasSlug = dom.inputLoginSlug ? dom.inputLoginSlug.value.trim().length > 0 : false;
			dom.btnConfirmLoginRename.disabled = !(isConfirmed && hasSlug);
		}
		if (dom.inputLoginConfirm) {
			dom.inputLoginConfirm.addEventListener('input', updateLoginRenameButton);
		}
		if (dom.inputLoginSlug) {
			dom.inputLoginSlug.addEventListener('input', updateLoginRenameButton);
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

		// REST API Security Auditor Events
		if (dom.btnRefreshRestAudit) {
			dom.btnRefreshRestAudit.addEventListener('click', () => loadRestAudit(true));
		}
		if (dom.restSearch) {
			dom.restSearch.addEventListener('input', (e) => {
				state.restSearch = e.target.value.trim().toLowerCase();
				renderRestTable();
			});
		}
		if (dom.restFilterBtns) {
			dom.restFilterBtns.forEach(btn => {
				btn.addEventListener('click', (e) => {
					e.preventDefault();
					dom.restFilterBtns.forEach(b => b.classList.remove('active'));
					btn.classList.add('active');
					state.restFilter = btn.getAttribute('data-rest-filter') || 'all';
					renderRestTable();
				});
			});
		}

		// Developer Toolkit Events
		if (dom.btnSaveEnv) {
			dom.btnSaveEnv.addEventListener('click', saveEnvironmentType);
		}
		if (dom.btnCopyDiagnostic) {
			dom.btnCopyDiagnostic.addEventListener('click', copyDiagnosticSnapshot);
		}
		if (dom.btnViewDiagnostic) {
			dom.btnViewDiagnostic.addEventListener('click', viewDiagnosticSnapshot);
		}
		if (dom.btnDiagnosticModalCopy) {
			dom.btnDiagnosticModalCopy.addEventListener('click', copyDiagnosticSnapshot);
		}
		if (dom.btnRefreshCron) {
			dom.btnRefreshCron.addEventListener('click', () => loadCronAudit(true));
		}
		if (dom.btnCleanDb) {
			dom.btnCleanDb.addEventListener('click', cleanDbBloat);
		}
		if (dom.btnCheckMigration) {
			dom.btnCheckMigration.addEventListener('click', () => loadMigrationReadiness(true));
		}
		if (dom.btnRefreshDigest) {
			dom.btnRefreshDigest.addEventListener('click', () => loadChangelogDigest(true));
		}
	}

	/**
	 * Fetch tasks catalog and summary from REST API
	 */
	async function loadTasks() {
		try {
			state.loading = true;
			state.loadError = null;
			const res = await apiCall({
				path: '/site-checkup-pro/v1/tasks',
			});
			applyTasksPayload(res);
		} catch (err) {
			console.error('Failed to load Site Checkup Pro tasks:', err);
			state.loading = false;
			state.loadError = err.message || 'Error communicating with REST API.';
			if (dom.tbody && (!state.tasks || state.tasks.length === 0)) {
				dom.tbody.innerHTML = `
					<tr>
						<td colspan="5" class="wpsg-error-state" style="text-align: center; padding: 40px;">
							<span class="dashicons dashicons-warning" style="font-size: 32px; color: var(--wpsg-danger);"></span>
							<h4 style="margin: 8px 0 4px;">Unable to load checklist tasks</h4>
							<p style="color: var(--wpsg-text-secondary); margin-bottom: 12px;">${escapeHtml(state.loadError)}</p>
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-retry-tasks">Retry Loading Tasks</button>
						</td>
					</tr>
				`;
				const retryBtn = document.getElementById('wpsg-btn-retry-tasks');
				if (retryBtn) retryBtn.addEventListener('click', () => loadTasks());
			}
		}
	}

	/**
	 * Switch active tab and render appropriate panel
	 *
	 * @param {string} tabId Target tab slug
	 * @param {boolean} preserveSearch Whether to retain active search query
	 */
	function switchTab(tabId, preserveSearch = false) {
		state.activeTab = tabId;

		if (!preserveSearch) {
			state.searchQuery = '';
			if (dom.searchTasks) {
				dom.searchTasks.value = '';
			}
		}

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
		if (dom.btnHeroRunSafe) dom.btnHeroRunSafe.disabled = true;
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

		const confirmed = await showConfirmModal({
			title: 'Confirm Login URL Change',
			message: `Are you sure you want to change your WordPress login URL to:\n${window.wpsgData?.homeUrl || ''}/${slug}/\n\nMake sure to remember this URL before proceeding!`,
			notice: 'Losing this URL may require manual reset via database or WP-CLI if forgotten.',
			confirmText: 'Change Login URL',
			confirmClass: 'wpsg-btn-danger',
			iconClass: 'dashicons-admin-network'
		});
		if (!confirmed) {
			return;
		}

		try {
			dom.btnSaveFeatLogin.disabled = true;
			const res = await apiCall({
				path: '/site-checkup-pro/v1/tasks/set-login-slug',
				method: 'POST',
				headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.set_login_slug) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.set_login_slug } : {},
				data: { slug, confirm: 'CHANGE' },
			});

			if (res && res.reauth_required) {
				clearReauthToken();
				dom.btnSaveFeatLogin.disabled = false;
				requireReauth(() => {
					saveFeatureLoginSlug();
				});
				return;
			}

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
		const confirmed = await showConfirmModal({
			title: 'Revert Login URL',
			message: 'Revert back to standard WordPress /wp-login.php?',
			notice: 'This will re-enable the standard WordPress login URL.',
			confirmText: 'Revert to Default',
			confirmClass: 'wpsg-btn-primary',
			iconClass: 'dashicons-undo'
		});
		if (!confirmed) {
			return;
		}

		try {
			const res = await apiCall({
				path: '/site-checkup-pro/v1/tasks/set-login-slug',
				method: 'POST',
				headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.set_login_slug) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.set_login_slug } : {},
				data: { slug: '', confirm: 'CHANGE' },
			});

			if (res && res.reauth_required) {
				clearReauthToken();
				requireReauth(() => {
					resetFeatureLoginSlug();
				});
				return;
			}

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
	 * Update KPI summary cards & Executive Posture Gauge
	 */
	function updateKpis() {
		if (dom.kpiCoverage) dom.kpiCoverage.textContent = `${state.sopCoveragePct}%`;
		if (dom.kpiFraction) dom.kpiFraction.textContent = `${state.doneCount} of ${state.totalCount} tasks active`;
		if (dom.coverageBar) dom.coverageBar.style.width = `${state.sopCoveragePct}%`;
		if (dom.kpiServer) dom.kpiServer.textContent = state.serverType.toUpperCase();

		// Count scheduled reminders
		const reminderCount = state.tasks.filter(t => t.next_reminder_at).length;
		if (dom.kpiReminders) dom.kpiReminders.textContent = reminderCount;

		renderPostureGauge();
		renderAttentionQueue();
		updateOverviewStats();
	}

	/**
	 * Render Executive Security Posture SVG Gauge & Grade
	 */
	function renderPostureGauge() {
		const total = state.totalCount || state.tasks.length || 1;
		const done = state.doneCount || state.tasks.filter(t => t.status === 'done' || t.status === 'applied_unverified').length;
		const pct = Math.min(100, Math.round((done / total) * 100));

		if (dom.posturePct) dom.posturePct.textContent = `${pct}%`;

		// Grade calculation
		let grade = 'Grade D';
		let strokeColor = '#DC2626'; // var(--wpsg-color-status-critical)
		if (pct >= 90) {
			grade = 'Grade A+';
			strokeColor = '#16A34A'; // var(--wpsg-color-status-success)
		} else if (pct >= 75) {
			grade = 'Grade A';
			strokeColor = '#16A34A'; // var(--wpsg-color-status-success)
		} else if (pct >= 60) {
			grade = 'Grade B';
			strokeColor = '#2563EB'; // var(--wpsg-color-brand)
		} else if (pct >= 40) {
			grade = 'Grade C';
			strokeColor = '#D97706'; // var(--wpsg-color-status-attention)
		}

		if (dom.postureGrade) {
			dom.postureGrade.textContent = grade;
			dom.postureGrade.style.color = strokeColor;
		}

		// Animate circular progress SVG
		if (dom.gaugeCircle) {
			const circumference = 2 * Math.PI * 50; // ~314.159
			const offset = circumference - (pct / 100) * circumference;
			dom.gaugeCircle.style.strokeDasharray = `${circumference}`;
			dom.gaugeCircle.style.strokeDashoffset = `${offset}`;
			dom.gaugeCircle.style.stroke = strokeColor;
		}

		if (dom.postureStatsText) {
			dom.postureStatsText.textContent = `${done} of ${total} verified standard operating procedures active across all 6 hardening domains.`;
		}
	}

	/**
	 * Render Priority Action Needed Queue on Overview
	 */
	function renderAttentionQueue() {
		if (!dom.overviewAttention || !dom.attentionItems) return;

		const attentionTasks = state.tasks.filter(t => t.status === 'attention' || t.status === 'failed');

		if (attentionTasks.length === 0) {
			dom.overviewAttention.style.display = 'none';
			return;
		}

		dom.overviewAttention.style.display = 'block';
		if (dom.attentionCount) {
			dom.attentionCount.textContent = `${attentionTasks.length} ${attentionTasks.length === 1 ? 'item requires attention' : 'items require attention'}`;
		}

		dom.attentionItems.innerHTML = attentionTasks.slice(0, 5).map(task => {
			const isFailed = task.status === 'failed';
			const badgeClass = isFailed ? 'wpsg-status-critical' : 'wpsg-status-attention';
			const badgeLabel = isFailed ? 'Failed' : 'Action Needed';
			const reason = task.live_message || task.description || 'Verification requires administrator review.';

			return `
				<div class="wpsg-attention-item">
					<div class="wpsg-attention-item-info">
						<span class="wpsg-status-indicator ${badgeClass}"><span class="wpsg-status-dot"></span> ${badgeLabel}</span>
						<div>
							<div class="wpsg-attention-item-title">${escapeHtml(task.title)}</div>
							<div class="wpsg-attention-item-reason">${escapeHtml(reason)}</div>
						</div>
					</div>
					<div class="wpsg-action-group">
						<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-attention-go-btn" data-go-task="${escapeHtml(task.id)}" data-go-section="${escapeHtml(task.section)}">
							<span class="dashicons dashicons-arrow-right-alt"></span> Inspect & Resolve
						</button>
					</div>
				</div>
			`;
		}).join('');
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
		if (dom.panelRestApi) dom.panelRestApi.style.display = 'none';
		if (dom.panelDevToolkit) dom.panelDevToolkit.style.display = 'none';

		if (state.activeTab === 'overview') {
			if (dom.panelOverview) dom.panelOverview.style.display = 'block';
			if (dom.panelTasks) dom.panelTasks.style.display = 'none'; // Clean separation: overview dashboard only!
			updateOverviewStats();
			renderPostureGauge();
			renderAttentionQueue();
		} else if (state.activeTab === 'features') {
			if (dom.panelFeatures) dom.panelFeatures.style.display = 'block';
		} else if (state.activeTab === 'audit_trail') {
			if (dom.panelAudit) dom.panelAudit.style.display = 'block';
			loadAuditLogs();
		} else if (state.activeTab === 'rest_api') {
			if (dom.panelRestApi) dom.panelRestApi.style.display = 'block';
			loadRestAudit(false);
		} else if (state.activeTab === 'dev_toolkit') {
			if (dom.panelDevToolkit) dom.panelDevToolkit.style.display = 'block';
			loadDevToolkit();
		} else if (state.activeTab === 'settings') {
			if (dom.panelSettings) dom.panelSettings.style.display = 'block';
			loadSettings(true);
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

		// 0. If tasks are currently loading and local tasks list is empty, show loading state
		if (state.loading && (!state.tasks || state.tasks.length === 0)) {
			dom.tbody.innerHTML = `
				<tr>
					<td colspan="5" style="text-align: center; padding: 40px;">
						<span class="wpsg-spinner" aria-hidden="true"></span>
						<p style="margin: 8px 0 0 0; color: var(--wpsg-text-secondary); font-size: 13px;">Loading checklist tasks and live verification status...</p>
					</td>
				</tr>
			`;
			return;
		}

		// 0b. If load failed and tasks list is empty, show explicit REST error with retry
		if (state.loadError && (!state.tasks || state.tasks.length === 0)) {
			dom.tbody.innerHTML = `
				<tr>
					<td colspan="5" class="wpsg-error-state" style="text-align: center; padding: 40px;">
						<span class="dashicons dashicons-warning" style="font-size: 32px; color: var(--wpsg-danger);"></span>
						<h4 style="margin: 8px 0 4px;">Unable to load checklist tasks</h4>
						<p style="color: var(--wpsg-text-secondary); margin-bottom: 12px;">${escapeHtml(state.loadError)}</p>
						<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-retry-tasks-view">Retry Loading Tasks</button>
					</td>
				</tr>
			`;
			const retryBtn = document.getElementById('wpsg-btn-retry-tasks-view');
			if (retryBtn) retryBtn.addEventListener('click', () => loadTasks());
			return;
		}

		let filtered = state.tasks;

		// 1. Filter by Search Query
		if (state.searchQuery) {
			const q = state.searchQuery.toLowerCase();
			filtered = filtered.filter(t =>
				(t.title && t.title.toLowerCase().includes(q)) ||
				(t.description && t.description.toLowerCase().includes(q)) ||
				(t.id && t.id.toLowerCase().includes(q))
			);
		}

		// 2. Filter by Section tab
		if (state.activeTab !== 'all' && state.activeTab !== 'audit_trail' && state.activeTab !== 'overview' && state.activeTab !== 'features') {
			filtered = filtered.filter(t => t.section === state.activeTab);
		}

		// 3. Filter by Level
		if (state.filterLevel) {
			if (state.filterLevel === 'A_instant') {
				filtered = filtered.filter(t => t.automation_level === 'A' && t.sub_type === 'instant');
			} else if (state.filterLevel === 'A_files') {
				filtered = filtered.filter(t => t.automation_level === 'A' && t.sub_type === 'writes_files');
			} else {
				filtered = filtered.filter(t => t.automation_level === state.filterLevel);
			}
		}

		// 4. Filter by Status
		if (state.filterStatus) {
			filtered = filtered.filter(t => t.status === state.filterStatus || t.id === state.justUpdatedTaskId);
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
							<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-clear-task-filters" style="margin-top: 14px;">
								<span class="dashicons dashicons-dismiss"></span> Clear Filters & Search
							</button>
						</div>
					</td>
				</tr>
			`;
			const clearBtn = document.getElementById('wpsg-btn-clear-task-filters');
			if (clearBtn) {
				clearBtn.addEventListener('click', () => {
					state.searchQuery = '';
					state.filterLevel = '';
					state.filterStatus = '';
					if (dom.searchTasks) dom.searchTasks.value = '';
					if (dom.filterLevel) dom.filterLevel.value = '';
					if (dom.filterStatus) dom.filterStatus.value = '';
					renderTasksTable();
				});
			}
			return;
		}

		dom.tbody.innerHTML = filtered.map(t => renderTaskRow(t)).join('');
		bindRowEvents();
	}

	/**
	 * Render Vulnerability Intelligence findings with version-range correlation,
	 * distinct source badges (GitHub Advisories vs Patchstack/OSV/NVD), remediation guidance,
	 * and inline "Verify Fix" actions.
	 */
	function renderVulnerabilityFindingsHtml(task) {
		if (task.id !== 'vulnerability_database_check' || !task.live_data) {
			return '';
		}

		const vulnerable = Array.isArray(task.live_data.vulnerable) ? task.live_data.vulnerable : [];
		if (vulnerable.length === 0) {
			return '';
		}

		return `
			<div class="wpsg-vuln-findings-container">
				${vulnerable.map(v => {
					const slug = v.component || v.slug || '';
					const name = v.component_name || v.name || slug;
					const ver = v.installed_version || v.version || '';
					const type = v.component_type || v.type || 'plugin';
					const advId = v.advisory_id || v.cve || 'Advisory';
					const advTitle = v.advisory_title || v.title || 'Known Security Vulnerability';
					const range = v.affected_range || '';
					const fixed = v.fixed_version || '';
					const remediation = v.remediation || (fixed ? `Update immediately to version ${fixed} or higher.` : 'Deactivate and replace component.');
					const sources = Array.isArray(v.sources_matched) ? v.sources_matched : (v.source ? [v.source] : ['Vulnerability Database']);
					const isCorroborated = v.is_corroborated || sources.length >= 2;
					const status = v.verification_status || 'not_applied';

					let statusBadge = '<span class="wpsg-vuln-badge wpsg-vuln-badge-danger"><span class="wpsg-status-dot"></span> Not Applied</span>';
					if (status === 'done') {
						statusBadge = '<span class="wpsg-vuln-badge wpsg-vuln-badge-success"><span class="wpsg-status-dot"></span> Applied &amp; Verified</span>';
					} else if (status === 'applied_unverified') {
						statusBadge = '<span class="wpsg-vuln-badge wpsg-vuln-badge-warning"><span class="wpsg-status-dot"></span> Applied (Unverified)</span>';
					}

					const sourceBadges = sources.map(src => {
						let cls = 'wpsg-source-tag';
						if (src === 'GitHub Security Advisories') cls += ' wpsg-source-ghsa';
						else if (src === 'Patchstack') cls += ' wpsg-source-patchstack';
						else if (src === 'OSV') cls += ' wpsg-source-osv';
						else if (src === 'NVD') cls += ' wpsg-source-nvd';
						else if (src === 'WPScan') cls += ' wpsg-source-wpscan';
						return `<span class="${cls}">${escapeHtml(src)}</span>`;
					}).join('');

					return `
						<div class="wpsg-vuln-card" data-slug="${escapeHtml(slug)}">
							<div class="wpsg-vuln-card-header">
								<div class="wpsg-vuln-comp-meta">
									<strong class="wpsg-vuln-name">${escapeHtml(name)}</strong>
									<span class="wpsg-vuln-ver">v${escapeHtml(ver)}</span>
									<span class="wpsg-chip wpsg-chip-sm">${escapeHtml(type)}</span>
									${isCorroborated ? '<span class="wpsg-badge-corroborated" title="Corroborated across multiple independent advisory sources">2+ Sources Corroborated</span>' : ''}
								</div>
								<div class="wpsg-vuln-status-group">
									${sourceBadges}
									${statusBadge}
								</div>
							</div>
							<div class="wpsg-vuln-advisory-block">
								<div class="wpsg-vuln-adv-line">
									<span class="wpsg-adv-id">${escapeHtml(advId)}</span>
									<span class="wpsg-adv-title">${escapeHtml(advTitle)}</span>
								</div>
								${range ? `<div class="wpsg-vuln-range-line"><span class="wpsg-label-muted">Affected Version Range:</span> <code class="wpsg-range-code">${escapeHtml(range)}</code></div>` : ''}
								<div class="wpsg-vuln-remediation-line"><span class="wpsg-label-muted">Remediation:</span> <span class="wpsg-remediation-text">${escapeHtml(remediation)}</span></div>
							</div>
							<div class="wpsg-vuln-actions">
								<button type="button" class="wpsg-btn wpsg-btn-xs wpsg-btn-secondary wpsg-btn-verify-vuln-fix" data-slug="${escapeHtml(slug)}" data-type="${escapeHtml(type)}">
									<span class="dashicons dashicons-yes-alt"></span> Verify Fix
								</button>
							</div>
						</div>
					`;
				}).join('')}
			</div>
		`;
	}

	/**
	 * Render a single task row with accessible badges (Dot + Label + Accessible text)
	 */
	function renderTaskRow(task) {
		const statusBadge = getStatusBadgeHtml(task.status);
		const levelBadge = getLevelBadgeHtml(task.automation_level, task.sub_type);
		const actionButtons = getActionButtonsHtml(task);
		const lastRunText = task.last_run_at ? formatDate(task.last_run_at) : '<span class="wpsg-text-muted">Not checked yet</span>';
		const isJustUpdated = state.justUpdatedTaskId === task.id;
		const rowClass = `wpsg-task-row ${task.status === 'done' ? 'wpsg-row-completed' : ''} ${isJustUpdated ? 'wpsg-row-highlight' : ''}`;
		const isManualNginx = !state.supportsHtaccess && task.nginx_snippet && !task.has_nginx_tier1 && !task.has_nginx_tier2 && task.status !== 'done';

		let evidenceClass = '';
		let evidenceIcon = 'dashicons-info';
		if (task.status === 'attention') {
			evidenceClass = 'wpsg-evidence-attention';
			evidenceIcon = 'dashicons-warning';
		} else if (task.status === 'failed') {
			evidenceClass = 'wpsg-evidence-critical';
			evidenceIcon = 'dashicons-dismiss';
		} else if (task.status === 'applied_unverified') {
			evidenceClass = 'wpsg-evidence-unverified';
			evidenceIcon = 'dashicons-info';
		} else if (task.status === 'done') {
			evidenceClass = 'wpsg-evidence-success';
			evidenceIcon = 'dashicons-yes-alt';
		}

		return `
			<tr data-task-id="${escapeHtml(task.id)}" class="${rowClass}">
				<td class="wpsg-col-status">${statusBadge}</td>
				<td class="wpsg-col-level">${levelBadge}</td>
				<td class="wpsg-col-task">
					<div class="wpsg-task-cell">
						<span class="wpsg-task-title">${escapeHtml(task.title)}</span>
						<span class="wpsg-task-desc">${escapeHtml(task.description)}</span>
						${isManualNginx ? `<div class="wpsg-task-evidence wpsg-evidence-notice"><span class="dashicons dashicons-warning" style="font-size:12px;width:12px;height:12px;margin-top:1px;"></span> <span>Cannot be applied automatically on this hosting setup — manual step required.</span></div>` : ''}
						${task.live_message ? `<div class="wpsg-task-evidence ${evidenceClass}"><span class="dashicons ${evidenceIcon}" style="font-size:12px;width:12px;height:12px;margin-top:1px;"></span> <span>${escapeHtml(task.live_message)}</span></div>` : ''}
						${task.note ? `<div class="wpsg-task-evidence"><span class="dashicons dashicons-edit" style="font-size:12px;width:12px;height:12px;margin-top:1px;"></span> <span>Note: ${escapeHtml(task.note)}</span></div>` : ''}
						${renderVulnerabilityFindingsHtml(task)}
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
			case 'applied_unverified':
				return '<span class="wpsg-status-indicator wpsg-status-unverified" title="Directive applied to server/config, but independent HTTP check has not verified live response"><span class="wpsg-status-dot"></span> Applied (Unverified)</span>';
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

		const verifiableTasks = [
			'clickjacking_protection', 'nosniff_header', 'hsts_header', 'hide_php_version',
			'disable_directory_listing', 'protect_sensitive_files', 'block_xmlrpc_htaccess',
			'deny_uploads_php', 'basic_firewall_rules', 'bad_bots_noise_reduction',
			'hide_wordpress_fingerprint', 'security_headers_csp', 'security_txt_check',
			'login_url_rename', 'disable_file_edit', 'wp_debug_display_check',
			'block_user_enumeration'
		];
		const canVerify = task.can_verify || verifiableTasks.indexOf(task.id) !== -1;

		// If server is Nginx and directive cannot be automated (lacks Tier 1 and Tier 2)
		if (!state.supportsHtaccess && task.nginx_snippet && !task.has_nginx_tier1 && !task.has_nginx_tier2) {
			html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-view-nginx" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-networking"></span> Nginx Snippet</button> `;
			if (canVerify) {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-outline wpsg-btn-verify" data-id="${escapeHtml(task.id)}" title="Run live HTTP verification"><span class="dashicons dashicons-update"></span> Check Now</button>`;
			}
			html += '</div>';
			return html;
		}

		// Level A: Automated
		if (task.automation_level === 'A') {
			const isScanner = isScannerTask(task);

			if (task.id === 'detect_unwanted_plugins' && task.status === 'attention') {
				const plugins = (task.live_data && Array.isArray(task.live_data.plugins)) ? task.live_data.plugins : [];
				if (plugins.length > 0) {
					plugins.forEach(p => {
						html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-danger wpsg-btn-open-delete-plugin" data-slug="${escapeHtml(p.slug)}" data-name="${escapeHtml(p.name)}" data-path="${escapeHtml(p.plugin_path || '')}"><span class="dashicons dashicons-trash"></span> Remove ${escapeHtml(p.name)}</button> `;
					});
				} else {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-danger wpsg-btn-open-delete-plugin" data-slug="" data-name="" data-path=""><span class="dashicons dashicons-trash"></span> Remove Plugin</button> `;
				}
			}

			if (task.has_diff && task.status !== 'done') {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-diff" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-visibility"></span> Diff</button> `;
			}

			if (task.status === 'done') {
				if (task.has_undo) {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-undo" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-undo"></span> Undo</button> `;
				} else {
					const label = isScanner ? 'Re-scan' : 'Re-run';
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-run" data-id="${escapeHtml(task.id)}" title="Task completed. Click to re-run."><span class="dashicons dashicons-update"></span> ${label}</button> `;
				}
			} else if (task.status === 'applied_unverified') {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-warning wpsg-btn-verify" data-id="${escapeHtml(task.id)}" title="Run live HTTP verification check"><span class="dashicons dashicons-update"></span> Check Now</button> `;
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-run" data-id="${escapeHtml(task.id)}" title="Re-apply directive"><span class="dashicons dashicons-controls-play"></span> Re-apply</button> `;
				if (task.has_undo) {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary wpsg-btn-undo" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-undo"></span> Undo</button> `;
				}
			} else if (task.status === 'attention') {
				if (isScanner) {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-warning wpsg-btn-run" data-id="${escapeHtml(task.id)}" title="Findings detected. Click to re-scan."><span class="dashicons dashicons-search"></span> Re-scan (Findings)</button> `;
				} else {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-warning wpsg-btn-run" data-id="${escapeHtml(task.id)}" title="Action needed. Click to re-run."><span class="dashicons dashicons-controls-play"></span> Re-run</button> `;
				}
			} else if (task.status === 'failed') {
				const retryLabel = isScanner ? 'Retry Scan' : 'Retry';
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-danger wpsg-btn-run" data-id="${escapeHtml(task.id)}" title="Task execution failed. Click to retry."><span class="dashicons dashicons-warning"></span> ${retryLabel}</button> `;
			} else {
				if (isScanner) {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-run" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-search"></span> Run Scan</button> `;
				} else {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-run" data-id="${escapeHtml(task.id)}"><span class="dashicons dashicons-controls-play"></span> Run</button> `;
				}
			}

			if (canVerify && task.status !== 'applied_unverified') {
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-outline wpsg-btn-verify" data-id="${escapeHtml(task.id)}" title="Run live HTTP verification"><span class="dashicons dashicons-update"></span> Check Now</button>`;
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
				html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-primary wpsg-btn-open-login-rename"><span class="dashicons dashicons-admin-network"></span> Change Login URL</button> `;
				if (canVerify) {
					html += `<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-outline wpsg-btn-verify" data-id="${escapeHtml(task.id)}" title="Run live HTTP verification"><span class="dashicons dashicons-update"></span> Check Now</button>`;
				}
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
		// Event delegation on dom.app handles all row buttons reliably
	}

	/**
	 * Run a single task via REST API
	 */
	async function runTask(taskId, btnElement = null, reauthToken = null) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		const isScanner = isScannerTask(task);
		const token = reauthToken || getActiveReauthToken();

		// Set button loading state
		if (btnElement) {
			btnElement.disabled = true;
			btnElement.innerHTML = `<span class="wpsg-spinner"></span> ${isScanner ? 'Scanning...' : 'Running...'}`;
		}

		try {
			const headers = {};
			if (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.run_task) {
				headers['X-WPSG-Nonce'] = window.wpsgData.nonces.run_task;
			}
			if (token) {
				headers['X-WPSG-Reauth'] = token;
			}

			const res = await apiCall({
				path: `/site-checkup-pro/v1/tasks/${taskId}/run`,
				method: 'POST',
				headers: headers,
				data: Object.assign(
					{ force: true, force_refresh: true },
					token ? { reauth_token: token } : {}
				),
			});

			// Re-authentication check
			if (res && res.reauth_required) {
				clearReauthToken();
				if (btnElement) {
					btnElement.disabled = false;
					btnElement.innerHTML = '<span class="dashicons dashicons-lock"></span> Password Required';
				}
				requireReauth((freshToken) => {
					runTask(taskId, btnElement, freshToken);
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
					btnElement.innerHTML = `<span class="dashicons dashicons-controls-play"></span> ${isScanner ? 'Run Scan' : 'Run'}`;
				}
				return;
			}

			// Update task state
			task.status = res.status || (res.success ? 'done' : 'failed');
			task.live_message = res.live_message || res.message;
			task.last_run_at = res.last_run_at || new Date().toISOString();
			if (res.live_data) {
				task.live_data = res.live_data;
			}

			// Keep track of the task just updated so it highlights and doesn't get filtered out
			state.justUpdatedTaskId = taskId;

			// Recalculate summary metrics
			state.doneCount = state.tasks.filter(t => t.status === 'done').length;
			state.sopCoveragePct = Math.round((state.doneCount / state.totalCount) * 100);

			updateKpis();
			renderTasksTable();

		} catch (err) {
			console.error(`Task ${taskId} failed:`, err);
			task.status = 'failed';
			task.live_message = err.message || 'Execution error.';
			state.justUpdatedTaskId = taskId;
			renderTasksTable();
		}
	}

	/**
	 * Undo a task via REST API
	 */
	async function undoTask(taskId, btnElement = null, reauthToken = null) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		const token = reauthToken || getActiveReauthToken();

		if (btnElement) {
			btnElement.disabled = true;
			btnElement.innerHTML = '<span class="wpsg-spinner"></span> Undoing...';
		}

		try {
			const headers = {};
			if (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.undo_task) {
				headers['X-WPSG-Nonce'] = window.wpsgData.nonces.undo_task;
			}
			if (token) {
				headers['X-WPSG-Reauth'] = token;
			}

			const res = await apiCall({
				path: `/site-checkup-pro/v1/tasks/${taskId}/undo`,
				method: 'POST',
				headers: headers,
				data: token ? { reauth_token: token } : {},
			});

			if (res && res.reauth_required) {
				clearReauthToken();
				if (btnElement) {
					btnElement.disabled = false;
					btnElement.innerHTML = '<span class="dashicons dashicons-lock"></span> Password Required';
				}
				requireReauth((freshToken) => {
					undoTask(taskId, btnElement, freshToken);
				});
				return;
			}

			task.status = res.status || 'pending';
			task.live_message = res.live_message || res.message;
			state.justUpdatedTaskId = taskId;

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
	 * Verify a task via independent live HTTP verification REST API
	 */
	async function verifyTask(taskId, btnElement = null) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		let origHtml = '';
		if (btnElement) {
			origHtml = btnElement.innerHTML;
			btnElement.disabled = true;
			btnElement.innerHTML = '<span class="wpsg-spinner"></span> Checking...';
		}

		try {
			const res = await apiCall({
				path: `/site-checkup-pro/v1/tasks/${taskId}/verify`,
				method: 'POST',
				data: { force_fresh: true },
			});

			task.status = res.status || (res.verified ? 'done' : 'applied_unverified');
			task.live_message = res.message || (res.verified ? 'Enforcement verified live.' : 'Verification check could not confirm enforcement.');
			task.last_run_at = new Date().toISOString();
			state.justUpdatedTaskId = taskId;

			state.doneCount = state.tasks.filter(t => t.status === 'done').length;
			state.sopCoveragePct = Math.round((state.doneCount / state.totalCount) * 100);

			updateKpis();
			renderTasksTable();
		} catch (err) {
			console.error(`Verification check failed for ${taskId}:`, err);
			task.live_message = `Verification error: ${err.message || 'Check failed'}`;
			state.justUpdatedTaskId = taskId;
			renderTasksTable();
		} finally {
			if (btnElement) {
				btnElement.disabled = false;
				btnElement.innerHTML = origHtml;
			}
		}
	}

	/**
	 * Verify fix for a specific vulnerability finding via REST API
	 */
	async function verifyVulnerabilityFix(slug, type, btnElement = null) {
		let origHtml = '';
		if (btnElement) {
			origHtml = btnElement.innerHTML;
			btnElement.disabled = true;
			btnElement.innerHTML = '<span class="wpsg-spinner" style="width:12px;height:12px;display:inline-block;vertical-align:middle;margin-right:4px;"></span> Checking Fix...';
		}

		try {
			const res = await apiCall({
				path: '/site-checkup-pro/v1/vulnerabilities/verify-fix',
				method: 'POST',
				data: { slug: slug, type: type },
			});

			if (res && res.verification_status) {
				if (res.verification_status === 'done') {
					alert(res.message || `Verified: Vulnerability in "${slug}" is resolved.`);
				} else if (res.verification_status === 'applied_unverified') {
					alert(res.message || `Applied, Not Verified: "${slug}" version was updated, but live external check was unreachable.`);
				} else {
					alert(res.message || `Verification Failed: "${slug}" remains vulnerable within the affected range.`);
				}

				// Re-fetch tasks to update live findings state and metrics
				await loadTasks();
				if (state.currentTab === 'audit') {
					await loadAuditLogs();
				}
			} else {
				alert((res && res.message) ? res.message : 'Fix verification failed: Unexpected response.');
			}
		} catch (err) {
			console.error(`Fix verification error for ${slug}:`, err);
			alert('Fix verification error: ' + (err.message || 'Unknown error'));
		} finally {
			if (btnElement) {
				btnElement.disabled = false;
				btnElement.innerHTML = origHtml;
			}
		}
	}

	/**
	 * Run All Safe Tasks (Hero button & Quick Action entry point)
	 */
	function runBatchSafeTasks() {
		return startBatchRunner();
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

		if (dom.batchBanner) dom.batchBanner.style.display = 'flex';
		if (dom.batchProgress) dom.batchProgress.style.backgroundColor = 'var(--wpsg-brand)';
		if (dom.btnBatchRun) dom.btnBatchRun.disabled = true;
		if (dom.btnHeroRunSafe) dom.btnHeroRunSafe.disabled = true;
		if (dom.btnRunSection) dom.btnRunSection.disabled = true;

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
			const res = await apiCall({
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
				dom.batchText.textContent = `Paused: Task "${task ? task.title : taskId}" reported an issue.`;
				dom.batchProgress.style.backgroundColor = '#ef4444';
				state.batchRunning = false;
				if (dom.btnBatchRun) dom.btnBatchRun.disabled = false;
				if (dom.btnHeroRunSafe) dom.btnHeroRunSafe.disabled = false;
				if (dom.btnRunSection) dom.btnRunSection.disabled = false;
				renderTasksTable();
				return;
			}

			state.batchIndex++;
			state.doneCount = state.tasks.filter(t => t.status === 'done').length;
			state.sopCoveragePct = state.totalCount > 0 ? Math.round((state.doneCount / state.totalCount) * 100) : 0;
			updateKpis();
			renderTasksTable();

			// Short pause before next request to keep browser responsive
			setTimeout(processNextBatchTask, 400);

		} catch (err) {
			dom.batchText.textContent = `Paused on error: ${err.message || 'Execution failed'}`;
			dom.batchProgress.style.backgroundColor = '#ef4444';
			state.batchRunning = false;
			if (dom.btnBatchRun) dom.btnBatchRun.disabled = false;
			if (dom.btnHeroRunSafe) dom.btnHeroRunSafe.disabled = false;
			if (dom.btnRunSection) dom.btnRunSection.disabled = false;
			renderTasksTable();
		}
	}

	function finishBatchRunner() {
		state.batchRunning = false;
		if (dom.batchProgress) dom.batchProgress.style.width = '100%';
		if (dom.batchText) dom.batchText.textContent = 'All safe tasks executed successfully!';
		if (dom.btnBatchRun) dom.btnBatchRun.disabled = false;
		if (dom.btnHeroRunSafe) dom.btnHeroRunSafe.disabled = false;
		if (dom.btnRunSection) dom.btnRunSection.disabled = false;

		state.doneCount = state.tasks.filter(t => t.status === 'done').length;
		state.sopCoveragePct = state.totalCount > 0 ? Math.round((state.doneCount / state.totalCount) * 100) : 0;
		updateKpis();

		setTimeout(() => {
			if (dom.batchBanner) dom.batchBanner.style.display = 'none';
			if (dom.batchProgress) {
				dom.batchProgress.style.width = '0%';
				dom.batchProgress.style.backgroundColor = '';
			}
		}, 4000);
	}

	function stopBatchRunner() {
		state.batchRunning = false;
		if (dom.batchBanner) dom.batchBanner.style.display = 'none';
		if (dom.btnBatchRun) dom.btnBatchRun.disabled = false;
		if (dom.btnHeroRunSafe) dom.btnHeroRunSafe.disabled = false;
		if (dom.btnRunSection) dom.btnRunSection.disabled = false;
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
			const res = await apiCall({
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
			await apiCall({
				path: `/site-checkup-pro/v1/tasks/${taskId}/status`,
				method: 'POST',
				headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.update_status) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.update_status } : {},
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
			const res = await apiCall({
				path: '/site-checkup-pro/v1/tasks/set-login-slug',
				method: 'POST',
				headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.set_login_slug) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.set_login_slug } : {},
				data: { slug, confirm },
			});

			if (res && res.reauth_required) {
				clearReauthToken();
				dom.btnConfirmLoginRename.disabled = false;
				requireReauth(() => {
					submitLoginRename();
				});
				return;
			}

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
	 * Open Delete Plugin Modal
	 */
	function openDeletePluginModal(slug, name, path) {
		if (!slug) {
			const task = state.tasks.find(t => t.id === 'detect_unwanted_plugins');
			if (task && task.live_data && Array.isArray(task.live_data.plugins) && task.live_data.plugins.length > 0) {
				slug = task.live_data.plugins[0].slug;
				name = task.live_data.plugins[0].name;
				path = task.live_data.plugins[0].plugin_path;
			}
		}
		state.pendingDeletePlugin = { slug: slug || '', name: name || slug || 'Unknown Plugin', path: path || '' };
		if (dom.deletePluginName) dom.deletePluginName.textContent = state.pendingDeletePlugin.name;
		if (dom.deletePluginSlug) dom.deletePluginSlug.textContent = state.pendingDeletePlugin.slug;
		openModal(dom.modalDeletePlugin);
	}

	/**
	 * Submit Plugin Deletion with ZIP Backup
	 */
	async function submitDeletePlugin() {
		if (!state.pendingDeletePlugin || !state.pendingDeletePlugin.slug) return;

		if (dom.btnConfirmDeletePlugin) {
			dom.btnConfirmDeletePlugin.disabled = true;
			dom.btnConfirmDeletePlugin.innerHTML = '<span class="wpsg-spinner"></span> Archiving & Deleting...';
		}

		try {
			const headers = {};
			if (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.delete_plugin) {
				headers['X-WPSG-Nonce'] = window.wpsgData.nonces.delete_plugin;
			}

			const res = await apiCall({
				path: '/site-checkup-pro/v1/tasks/delete-plugin',
				method: 'POST',
				headers: headers,
				data: {
					slug: state.pendingDeletePlugin.slug,
					plugin_path: state.pendingDeletePlugin.path,
				},
			});

			if (res && res.reauth_required) {
				clearReauthToken();
				if (dom.btnConfirmDeletePlugin) {
					dom.btnConfirmDeletePlugin.disabled = false;
					dom.btnConfirmDeletePlugin.innerHTML = '<span class="dashicons dashicons-trash"></span> Archive to ZIP & Delete Plugin';
				}
				requireReauth(() => {
					submitDeletePlugin();
				});
				return;
			}

			if (res.success) {
				closeAllModals();
				alert(res.message || `Plugin "${state.pendingDeletePlugin.name}" successfully archived and removed.`);
				await runTask('detect_unwanted_plugins');
			} else {
				alert(`Error: ${res.message || 'Could not delete plugin.'}`);
			}
		} catch (err) {
			alert(`Error: ${err.message || 'Could not delete plugin.'}`);
		} finally {
			if (dom.btnConfirmDeletePlugin) {
				dom.btnConfirmDeletePlugin.disabled = false;
				dom.btnConfirmDeletePlugin.innerHTML = '<span class="dashicons dashicons-trash"></span> Archive to ZIP & Delete Plugin';
			}
		}
	}

	/**
	 * Confirm manual backup
	 */
	async function confirmManualBackup(callback = null) {
		try {
			await apiCall({
				path: '/site-checkup-pro/v1/tasks/confirm-backup',
				method: 'POST',
				headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.confirm_backup) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.confirm_backup } : {},
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
		const confirmed = await showConfirmModal({
			title: 'Update Baseline Snapshot',
			message: 'Update the baseline snapshot to accept all current administrators and configuration settings as trusted?',
			notice: 'Future integrity audits will use this current state as the trusted baseline.',
			confirmText: 'Update Baseline',
			confirmClass: 'wpsg-btn-primary',
			iconClass: 'dashicons-yes-alt'
		});
		if (!confirmed) {
			return;
		}

		try {
			const res = await apiCall({
				path: '/site-checkup-pro/v1/tasks/update-baseline',
				method: 'POST',
				headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.update_baseline) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.update_baseline } : {},
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
		const activeToken = getActiveReauthToken();
		if (activeToken) {
			if (typeof callback === 'function') {
				callback(activeToken);
			}
			return;
		}
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
			const res = await apiCall({
				path: '/site-checkup-pro/v1/reauth',
				method: 'POST',
				headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.reauth) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.reauth } : {},
				data: { password },
			});

			if (res && res.reauth_token) {
				setReauthToken(res.reauth_token, res.expires_in || 1800);
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
			const res = await apiCall({
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
					const confirmed = await showConfirmModal({
						title: 'Terminate Remote Session',
						message: 'Are you sure you want to terminate this remote session?',
						notice: 'The user on that device will be immediately logged out.',
						confirmText: 'Terminate Session',
						confirmClass: 'wpsg-btn-danger',
						iconClass: 'dashicons-dismiss'
					});
					if (!confirmed) return;
					btn.disabled = true;
					try {
						await apiCall({
							path: '/site-checkup-pro/v1/sessions/destroy',
							method: 'POST',
							headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.destroy_session) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.destroy_session } : {},
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
		const confirmed = await showConfirmModal({
			title: 'Terminate All Other Sessions',
			message: 'Log out all other browser sessions across all devices?',
			notice: 'Your current session will remain active, but all other active logins will be invalidated.',
			confirmText: 'Log Out All Others',
			confirmClass: 'wpsg-btn-danger',
			iconClass: 'dashicons-shield-alt'
		});
		if (!confirmed) return;
		if (dom.btnDestroyOtherSessions) dom.btnDestroyOtherSessions.disabled = true;

		try {
			await apiCall({
				path: '/site-checkup-pro/v1/sessions/destroy-others',
				method: 'POST',
				headers: (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.destroy_session) ? { 'X-WPSG-Nonce': window.wpsgData.nonces.destroy_session } : {},
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
			const res = await apiCall({
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
				btn.addEventListener('click', async () => {
					const uuid = btn.getAttribute('data-uuid');
					const userId = btn.getAttribute('data-user-id');
					const confirmed = await showConfirmModal({
						title: 'Revoke Application Password',
						message: 'Are you sure you want to revoke this application password?',
						notice: 'WARNING: Revoking this application password will permanently break external REST API clients, third-party integrations, or mobile apps using it.',
						confirmText: 'Revoke Password',
						confirmClass: 'wpsg-btn-danger',
						iconClass: 'dashicons-warning'
					});
					if (!confirmed) return;

					requireReauth(async (token) => {
						btn.disabled = true;
						try {
							const revRes = await apiCall({
								path: '/site-checkup-pro/v1/app-passwords/revoke',
								method: 'POST',
								headers: {
									'X-WPSG-Nonce': (window.wpsgData && window.wpsgData.nonces && window.wpsgData.nonces.revoke_app_pass) || '',
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
			const res = await apiCall({
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
			const res = await apiCall({
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
					<td class="wpsg-audit-col-time">${escapeHtml(l.created_at)}</td>
					<td class="wpsg-audit-col-task"><code>${escapeHtml(l.task_id)}</code></td>
					<td class="wpsg-audit-col-action">${escapeHtml(l.action)}</td>
					<td class="wpsg-audit-col-user">${escapeHtml(l.display_name || l.user_login || 'System')}</td>
					<td class="wpsg-audit-col-result">
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
	 * Standardized, accessible confirmation modal replacing browser confirm()
	 */
	function showConfirmModal({
		title = 'Confirm Action',
		message = '',
		notice = '',
		confirmText = 'Confirm',
		confirmClass = 'wpsg-btn-danger',
		iconClass = 'dashicons-warning'
	} = {}) {
		return new Promise((resolve) => {
			if (!dom.modalConfirm) {
				resolve(window.confirm(message));
				return;
			}
			if (dom.confirmTitleText) dom.confirmTitleText.textContent = title;
			if (dom.confirmMessage) dom.confirmMessage.textContent = message;
			if (dom.confirmNotice && dom.confirmNoticeText) {
				if (notice) {
					dom.confirmNoticeText.textContent = notice;
					dom.confirmNotice.style.display = 'flex';
				} else {
					dom.confirmNotice.style.display = 'none';
				}
			}
			if (dom.btnConfirmSubmit) {
				dom.btnConfirmSubmit.textContent = confirmText;
				dom.btnConfirmSubmit.className = `wpsg-btn ${confirmClass}`;
			}
			if (dom.confirmIcon) {
				dom.confirmIcon.className = `dashicons ${iconClass}`;
			}

			let resolved = false;

			const cleanup = (val) => {
				if (!resolved) {
					resolved = true;
					dom.btnConfirmSubmit?.removeEventListener('click', onConfirm);
					dom.btnConfirmCancel?.removeEventListener('click', onCancel);
					if (dom.modalConfirm) dom.modalConfirm.style.display = 'none';
					resolve(val);
				}
			};

			const onConfirm = (e) => {
				e?.preventDefault();
				cleanup(true);
			};

			const onCancel = (e) => {
				e?.preventDefault();
				cleanup(false);
			};

			dom.btnConfirmSubmit?.addEventListener('click', onConfirm, { once: true });
			dom.btnConfirmCancel?.addEventListener('click', onCancel, { once: true });

			openModal(dom.modalConfirm);
		});
	}

	/**
	 * REST API Security Auditor Methods
	 */
	async function loadRestAudit(force = false) {
		if (!dom.restTbody) return;
		if (state.restData && !force) {
			renderRestTable();
			return;
		}

		dom.restTbody.innerHTML = `
			<tr>
				<td colspan="5" style="text-align: center; padding: 36px;">
					<span class="wpsg-spinner" aria-hidden="true"></span>
					<p style="margin: 8px 0 0; color: var(--wpsg-text-secondary); font-size: 13px;">Analyzing all registered REST routes across active plugins and themes...</p>
				</td>
			</tr>
		`;

		try {
			state.restLoading = true;
			const res = await apiCall({ path: '/site-checkup-pro/v1/developer/rest-audit' });
			const data = (res && res.data) ? res.data : res;
			if (data && Array.isArray(data.endpoints)) {
				state.restData = data;
				const sum = data.summary || {};
				if (dom.restStatTotal) dom.restStatTotal.textContent = sum.total_endpoints || data.endpoints.length || 0;
				if (dom.restStatProtected) dom.restStatProtected.textContent = sum.protected || 0;
				if (dom.restStatPublic) dom.restStatPublic.textContent = sum.public || 0;
				if (dom.restStatHigh) dom.restStatHigh.textContent = sum.high_risk || 0;
				renderRestTable();
			} else {
				throw new Error((res && res.message) || 'Failed to inspect REST routes.');
			}
		} catch (err) {
			dom.restTbody.innerHTML = `
				<tr>
					<td colspan="5" class="wpsg-error-state" style="text-align: center; padding: 30px;">
						<span class="dashicons dashicons-warning" style="font-size: 28px; color: var(--wpsg-danger);"></span>
						<p style="color: var(--wpsg-text-secondary); margin: 6px 0 10px;">${escapeHtml(err.message || 'Error running REST API security audit.')}</p>
						<button type="button" class="wpsg-btn wpsg-btn-sm wpsg-btn-secondary" id="wpsg-btn-retry-rest-audit">Retry Audit</button>
					</td>
				</tr>
			`;
			const retryBtn = document.getElementById('wpsg-btn-retry-rest-audit');
			if (retryBtn) retryBtn.addEventListener('click', () => loadRestAudit(true));
		} finally {
			state.restLoading = false;
		}
	}

	function renderRestTable() {
		if (!dom.restTbody || !state.restData || !Array.isArray(state.restData.endpoints)) return;

		const query = (state.restSearch || '').toLowerCase();
		const filter = state.restFilter || 'all';

		const filtered = state.restData.endpoints.filter(ep => {
			const status = ep.status || ep.permission_status || 'public';
			const risk = ep.risk_level || 'low';

			// Status / Risk filter
			if (filter === 'public' && status !== 'public') return false;
			if (filter === 'protected' && status !== 'protected') return false;
			if (filter === 'needs_review' && status !== 'needs_review') return false;
			if (filter === 'critical' && risk !== 'critical' && risk !== 'high') return false;

			// Search query
			if (query) {
				const matchRoute = (ep.route || '').toLowerCase().includes(query);
				const matchNamespace = (ep.namespace || '').toLowerCase().includes(query);
				const matchSource = (ep.source || ep.source_name || '').toLowerCase().includes(query);
				const methodsStr = Array.isArray(ep.methods) ? ep.methods.join(' ') : (ep.methods || '');
				const matchMethod = methodsStr.toLowerCase().includes(query);
				if (!matchRoute && !matchNamespace && !matchSource && !matchMethod) return false;
			}
			return true;
		});

		if (filtered.length === 0) {
			dom.restTbody.innerHTML = `
				<tr>
					<td colspan="5" style="text-align: center; padding: 32px; color: var(--wpsg-text-secondary); font-size: 13px;">
						No REST endpoints matched your filter or search criteria.
					</td>
				</tr>
			`;
			return;
		}

		let html = '';
		filtered.forEach(ep => {
			const status = ep.status || ep.permission_status || 'public';
			const risk = ep.risk_level || 'low';
			const sourceName = ep.source || ep.source_name || 'WordPress';
			const detail = ep.detail || ep.permission_detail || '';

			let riskBadge = '';
			if (risk === 'critical' || risk === 'high') {
				riskBadge = '<span class="wpsg-badge wpsg-badge-danger" style="text-transform: uppercase;">' + escapeHtml(risk) + '</span>';
			} else if (risk === 'medium') {
				riskBadge = '<span class="wpsg-badge wpsg-badge-warning" style="text-transform: uppercase;">Medium</span>';
			} else {
				riskBadge = '<span class="wpsg-badge wpsg-badge-success" style="text-transform: uppercase;">Low</span>';
			}

			let permBadge = '';
			if (status === 'protected') {
				permBadge = '<span class="wpsg-badge wpsg-badge-success" style="margin-right: 6px;">Protected</span>';
			} else if (status === 'public') {
				permBadge = '<span class="wpsg-badge wpsg-badge-warning" style="margin-right: 6px;">Public</span>';
			} else {
				permBadge = '<span class="wpsg-badge wpsg-badge-neutral" style="margin-right: 6px;">Review</span>';
			}

			const methodsStr = Array.isArray(ep.methods) ? ep.methods.join(', ') : (ep.methods || 'GET');

			html += `
				<tr>
					<td>
						<code style="font-size: 12px; font-weight: 600; color: var(--wpsg-text-primary);">${escapeHtml(ep.route)}</code>
					</td>
					<td>
						<span style="font-size: 11px; font-weight: 700; color: var(--wpsg-text-secondary);">${escapeHtml(methodsStr)}</span>
					</td>
					<td>
						<div style="font-size: 13px; font-weight: 500; color: var(--wpsg-text-primary);">${escapeHtml(sourceName)}</div>
					</td>
					<td>
						<div style="display: flex; align-items: center; flex-wrap: wrap; gap: 4px;">
							${permBadge}
							<span style="font-size: 11px; color: var(--wpsg-text-secondary); font-family: monospace;">${escapeHtml(detail)}</span>
						</div>
					</td>
					<td>
						${riskBadge}
					</td>
				</tr>
			`;
		});

		dom.restTbody.innerHTML = html;
	}

	/**
	 * Developer Toolkit Methods
	 */
	function loadDevToolkit() {
		// Populate environment select
		if (dom.devEnvSelect) {
			const currentEnv = (window.wpsgData && window.wpsgData.environmentType) || 'production';
			dom.devEnvSelect.value = currentEnv;
		}

		loadCronAudit(false);
		loadDbHealth(false);
		loadMigrationReadiness(false);
		loadChangelogDigest(false);
	}

	async function saveEnvironmentType() {
		if (!dom.devEnvSelect || !dom.btnSaveEnv) return;
		const env = dom.devEnvSelect.value;
		const originalText = dom.btnSaveEnv.innerHTML;
		dom.btnSaveEnv.disabled = true;
		dom.btnSaveEnv.innerHTML = '<span class="wpsg-spinner" aria-hidden="true"></span> Saving...';

		try {
			const res = await apiCall({
				path: '/site-checkup-pro/v1/settings',
				method: 'POST',
				data: { wpsg_environment_type: env },
			});
			if (res && res.success) {
				if (window.wpsgData) window.wpsgData.environmentType = env;
				dom.btnSaveEnv.innerHTML = '<span class="dashicons dashicons-yes"></span> Saved!';
				if (dom.devEnvHelp) {
					dom.devEnvHelp.textContent = `Environment badge updated to ${env.toUpperCase()}. Refresh page to view admin bar badge change.`;
				}
				setTimeout(() => {
					dom.btnSaveEnv.disabled = false;
					dom.btnSaveEnv.innerHTML = originalText;
				}, 2000);
			} else {
				throw new Error(res.message || 'Failed to update environment setting.');
			}
		} catch (err) {
			alert('Error updating environment badge: ' + (err.message || 'Unknown error'));
			dom.btnSaveEnv.disabled = false;
			dom.btnSaveEnv.innerHTML = originalText;
		}
	}

	async function getDiagnosticSnapshot() {
		if (state.diagnosticMarkdown) return state.diagnosticMarkdown;
		const res = await apiCall({ path: '/site-checkup-pro/v1/developer/diagnostic-snapshot' });
		if (res && res.markdown) {
			state.diagnosticMarkdown = res.markdown;
			return res.markdown;
		}
		throw new Error(res.message || 'Failed to generate diagnostic snapshot.');
	}

	async function copyDiagnosticSnapshot() {
		const btn = dom.btnCopyDiagnostic;
		const original = btn ? btn.innerHTML : '';
		if (btn) {
			btn.disabled = true;
			btn.innerHTML = '<span class="wpsg-spinner" aria-hidden="true"></span> Generating...';
		}

		try {
			const markdown = await getDiagnosticSnapshot();
			if (navigator.clipboard && navigator.clipboard.writeText) {
				await navigator.clipboard.writeText(markdown);
			} else {
				const textarea = document.createElement('textarea');
				textarea.value = markdown;
				document.body.appendChild(textarea);
				textarea.select();
				document.execCommand('copy');
				document.body.removeChild(textarea);
			}
			if (btn) {
				btn.innerHTML = '<span class="dashicons dashicons-yes"></span> Markdown Copied!';
				setTimeout(() => {
					btn.disabled = false;
					btn.innerHTML = original;
				}, 2000);
			}
		} catch (err) {
			alert('Could not copy diagnostic snapshot: ' + err.message);
			if (btn) {
				btn.disabled = false;
				btn.innerHTML = original;
			}
		}
	}

	async function viewDiagnosticSnapshot() {
		if (dom.modalDiagnostic) {
			openModal(dom.modalDiagnostic);
		}
		if (dom.diagnosticContent) {
			dom.diagnosticContent.textContent = 'Generating sanitized diagnostic report...';
		}
		try {
			const md = await getDiagnosticSnapshot();
			if (dom.diagnosticContent) {
				dom.diagnosticContent.textContent = md;
			}
		} catch (err) {
			if (dom.diagnosticContent) {
				dom.diagnosticContent.textContent = 'Error: ' + err.message;
			}
		}
	}

	async function loadCronAudit(force = false) {
		if (!dom.devCronSummary) return;
		if (force) dom.devCronSummary.textContent = 'Scanning WP-Cron schedules and hooks...';

		try {
			const res = await apiCall({ path: '/site-checkup-pro/v1/developer/cron-audit' });
			const data = (res && res.data) ? res.data : res;
			const sum = (data && data.summary) ? data.summary : data;
			if (sum) {
				const overdue = sum.overdue_count || 0;
				const dupes = sum.duplicate_count || 0;
				const total = sum.total_jobs || sum.total_events || (Array.isArray(data.jobs) ? data.jobs.length : 0);

				if (overdue === 0 && dupes === 0) {
					dom.devCronSummary.innerHTML = `<span style="color: var(--wpsg-success); font-weight: 600;">&bull; Healthy:</span> ${total} scheduled events active. 0 overdue, 0 duplicate hooks.`;
				} else {
					let issues = [];
					if (overdue > 0) issues.push(`<strong>${overdue} overdue events</strong> (>10m late)`);
					if (dupes > 0) issues.push(`<strong>${dupes} duplicate hooks</strong>`);
					dom.devCronSummary.innerHTML = `<span style="color: var(--wpsg-warning); font-weight: 600;">&bull; Warning:</span> ${issues.join(' and ')} detected across ${total} scheduled tasks.`;
				}
			}
		} catch (err) {
			dom.devCronSummary.textContent = 'Unable to check cron jobs: ' + err.message;
		}
	}

	async function loadDbHealth(force = false) {
		if (!dom.devDbSummary) return;
		if (force) dom.devDbSummary.textContent = 'Scanning database for bloat and orphaned records...';

		try {
			const res = await apiCall({ path: '/site-checkup-pro/v1/developer/db-health' });
			const data = (res && res.data) ? res.data : res;
			const sum = (data && data.summary) ? data.summary : data;
			const details = (data && data.details) ? data.details : {};

			const bloat = sum.total_bloat_items !== undefined ? sum.total_bloat_items : (sum.total_bloat || 0);
			const postmeta = (details.orphaned_postmeta && details.orphaned_postmeta.count !== undefined) ? details.orphaned_postmeta.count : (sum.orphaned_postmeta || 0);
			const usermeta = (details.orphaned_usermeta && details.orphaned_usermeta.count !== undefined) ? details.orphaned_usermeta.count : (sum.orphaned_usermeta || 0);
			const transients = (details.expired_transients && details.expired_transients.count !== undefined) ? details.expired_transients.count : (sum.expired_transients || 0);
			const revisions = (details.excess_revisions && details.excess_revisions.count !== undefined) ? details.excess_revisions.count : (sum.excess_revisions || 0);

			if (bloat === 0) {
				dom.devDbSummary.innerHTML = `<span style="color: var(--wpsg-success); font-weight: 600;">&bull; Clean:</span> 0 bloat records found. Database tables are lean and optimized.`;
				if (dom.btnCleanDb) dom.btnCleanDb.disabled = true;
			} else {
				dom.devDbSummary.innerHTML = `<span style="color: var(--wpsg-danger); font-weight: 600;">&bull; ${bloat} bloat items detected:</span> ${postmeta} orphaned postmeta, ${usermeta} orphaned usermeta, ${transients} expired transients, ${revisions} excess revisions.`;
				if (dom.btnCleanDb) dom.btnCleanDb.disabled = false;
			}
		} catch (err) {
			dom.devDbSummary.textContent = 'Unable to check database health: ' + err.message;
		}
	}

	async function cleanDbBloat() {
		const confirmed = await showConfirmModal({
			title: 'Clean Database Bloat?',
			message: 'This operation will permanently delete orphaned metadata, expired transients, and excess revisions.',
			notice: 'Ensure you have a recent database backup before proceeding.',
			confirmText: 'Verify & Clean',
			confirmClass: 'wpsg-btn-danger',
			iconClass: 'dashicons-database'
		});

		if (!confirmed) return;

		requireReauth(async (reauthToken) => {
			if (!dom.btnCleanDb) return;
			const orig = dom.btnCleanDb.innerHTML;
			dom.btnCleanDb.disabled = true;
			dom.btnCleanDb.innerHTML = '<span class="wpsg-spinner" aria-hidden="true"></span> Cleaning...';

			try {
				const res = await apiCall({
					path: '/site-checkup-pro/v1/developer/db-health/clean',
					method: 'POST',
					data: {
						type: 'all',
						reauth_token: reauthToken,
					},
					headers: {
						'X-WPSG-Reauth': reauthToken,
					}
				});

				if (res && res.success) {
					const deleted = res.total_deleted !== undefined ? res.total_deleted : (res.deleted_total || 0);
					alert(`Cleanup complete! Deleted ${deleted} bloat records.`);
					loadDbHealth(true);
					loadTasks();
				} else {
					throw new Error(res.message || 'Cleanup operation failed.');
				}
			} catch (err) {
				alert('Database cleanup failed: ' + (err.message || 'Unknown error'));
			} finally {
				dom.btnCleanDb.disabled = false;
				dom.btnCleanDb.innerHTML = orig;
			}
		});
	}

	async function loadMigrationReadiness(force = false) {
		if (!dom.devMigrationSummary) return;
		if (force) dom.devMigrationSummary.textContent = 'Scanning options and postmeta for serialized URL hazards...';

		try {
			const res = await apiCall({ path: '/site-checkup-pro/v1/developer/migration-readiness' });
			const data = (res && res.data) ? res.data : res;
			if (data) {
				const sum = data.summary || {};
				const count = sum.total_findings !== undefined ? sum.total_findings : (data.serialized_url_count || 0);
				if (count === 0) {
					dom.devMigrationSummary.innerHTML = `<span style="color: var(--wpsg-success); font-weight: 600;">&bull; Migration Ready:</span> 0 serialized URL hazards detected. Plain SQL replacement safe.`;
				} else {
					dom.devMigrationSummary.innerHTML = `<span style="color: var(--wpsg-warning); font-weight: 600;">&bull; Serialization Risk:</span> Found <strong>${count} serialized absolute URLs</strong>. Use <code style="font-size: 11px;">wp search-replace</code> instead of raw SQL dumps.`;
				}
			}
		} catch (err) {
			dom.devMigrationSummary.textContent = 'Unable to verify migration readiness: ' + err.message;
		}
	}

	async function loadChangelogDigest(force = false) {
		if (!dom.devChangelogSummary) return;
		if (force) dom.devChangelogSummary.textContent = 'Compiling changelog notices from active updates...';

		try {
			const res = await apiCall({ path: '/site-checkup-pro/v1/developer/changelog-digest' });
			const d = (res && res.data) ? res.data : res;
			if (d) {
				const items = Array.isArray(d.items) ? d.items : [];
				const sum = d.summary || {};
				const total = sum.total_updates !== undefined ? sum.total_updates : items.length;

				if (total === 0) {
					dom.devChangelogSummary.innerHTML = `<span style="color: var(--wpsg-success); font-weight: 600;">&bull; Up to Date:</span> All active plugins and themes are running the latest versions.`;
				} else {
					const firstPlugin = items[0] ? `${escapeHtml(items[0].name)} (${escapeHtml(items[0].current_version)} &rarr; ${escapeHtml(items[0].new_version)})` : '';
					dom.devChangelogSummary.innerHTML = `<span style="color: var(--wpsg-primary); font-weight: 600;">&bull; ${total} Update(s) Available:</span> ${firstPlugin ? firstPlugin : `${total} items pending update.`}`;
				}
			}
		} catch (err) {
			dom.devChangelogSummary.textContent = 'Unable to check changelog digest: ' + err.message;
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
	 * Settings Operations (Modal & In-Page)
	 */
	async function openSettingsModal() {
		if (!dom.modalSettings) return;
		openModal(dom.modalSettings);
		await loadSettings(false);
	}

	async function loadSettings(isPage = false) {
		const statusEl = isPage ? dom.pageSettingsSaveStatus : dom.settingsSaveStatus;
		if (statusEl) statusEl.textContent = 'Loading settings...';
		try {
			const res = await apiCall({ path: '/site-checkup-pro/v1/settings' });
			if (res && res.settings) {
				const s = res.settings;

				// Patchstack API Key
				if (dom.settingPatchstackKey) dom.settingPatchstackKey.value = '';
				if (dom.pageSettingPatchstackKey) dom.pageSettingPatchstackKey.value = '';

				const patchstackMasked = s.has_patchstack_key
					? `Current Key: ${s.patchstack_api_key_masked}`
					: 'No API key set (default checks active).';
				if (dom.patchstackMaskedStatus) dom.patchstackMaskedStatus.textContent = patchstackMasked;
				if (dom.pagePatchstackMaskedStatus) dom.pagePatchstackMaskedStatus.textContent = patchstackMasked;

				if (dom.settingPatchstackOptin) dom.settingPatchstackOptin.checked = !!s.patchstack_optin;
				if (dom.pageSettingPatchstackOptin) dom.pageSettingPatchstackOptin.checked = !!s.patchstack_optin;

				// GitHub Security Advisories
				if (dom.settingGhsaKey) dom.settingGhsaKey.value = '';
				if (dom.pageSettingGhsaKey) dom.pageSettingGhsaKey.value = '';
				const ghsaMasked = s.has_ghsa_key ? 'Token configured & encrypted at rest.' : 'No token set (public rate limits apply).';
				if (dom.ghsaMaskedStatus) dom.ghsaMaskedStatus.textContent = ghsaMasked;
				if (dom.pageGhsaMaskedStatus) dom.pageGhsaMaskedStatus.textContent = ghsaMasked;
				if (dom.settingGhsaOptin) dom.settingGhsaOptin.checked = !!s.ghsa_optin;
				if (dom.pageSettingGhsaOptin) dom.pageSettingGhsaOptin.checked = !!s.ghsa_optin;

				// OSV
				if (dom.settingOsvKey) dom.settingOsvKey.value = '';
				if (dom.pageSettingOsvKey) dom.pageSettingOsvKey.value = '';
				const osvMasked = s.has_osv_key ? 'API key configured & encrypted at rest.' : 'No API key set (public queries active).';
				if (dom.osvMaskedStatus) dom.osvMaskedStatus.textContent = osvMasked;
				if (dom.pageOsvMaskedStatus) dom.pageOsvMaskedStatus.textContent = osvMasked;
				if (dom.settingOsvOptin) dom.settingOsvOptin.checked = !!s.osv_optin;
				if (dom.pageSettingOsvOptin) dom.pageSettingOsvOptin.checked = !!s.osv_optin;

				// NVD
				if (dom.settingNvdKey) dom.settingNvdKey.value = '';
				if (dom.pageSettingNvdKey) dom.pageSettingNvdKey.value = '';
				const nvdMasked = s.has_nvd_key ? 'API key configured & encrypted at rest.' : 'No API key set (rate-limited public API active).';
				if (dom.nvdMaskedStatus) dom.nvdMaskedStatus.textContent = nvdMasked;
				if (dom.pageNvdMaskedStatus) dom.pageNvdMaskedStatus.textContent = nvdMasked;
				if (dom.settingNvdOptin) dom.settingNvdOptin.checked = !!s.nvd_optin;
				if (dom.pageSettingNvdOptin) dom.pageSettingNvdOptin.checked = !!s.nvd_optin;

				// CISA KEV
				if (dom.settingCisaKevKey) dom.settingCisaKevKey.value = '';
				if (dom.pageSettingCisaKevKey) dom.pageSettingCisaKevKey.value = '';
				const cisaKevMasked = s.has_cisa_kev_key ? 'Token configured & encrypted at rest.' : 'No custom token set (public catalog feed active).';
				if (dom.cisaKevMaskedStatus) dom.cisaKevMaskedStatus.textContent = cisaKevMasked;
				if (dom.pageCisaKevMaskedStatus) dom.pageCisaKevMaskedStatus.textContent = cisaKevMasked;
				if (dom.settingCisaKevOptin) dom.settingCisaKevOptin.checked = !!s.cisa_kev_optin;
				if (dom.pageSettingCisaKevOptin) dom.pageSettingCisaKevOptin.checked = !!s.cisa_kev_optin;

				// WPScan
				if (dom.settingWpscanToken) dom.settingWpscanToken.value = '';
				if (dom.pageSettingWpscanToken) dom.pageSettingWpscanToken.value = '';
				const wpscanMasked = (s.has_wpscan_key || s.wpscan_has_token) ? 'API token configured & encrypted at rest.' : 'No API token configured.';
				if (dom.wpscanMaskedStatus) dom.wpscanMaskedStatus.textContent = wpscanMasked;
				if (dom.pageWpscanMaskedStatus) dom.pageWpscanMaskedStatus.textContent = wpscanMasked;
				if (dom.settingWpscanOptin) dom.settingWpscanOptin.checked = !!s.wpscan_optin;
				if (dom.pageSettingWpscanOptin) dom.pageSettingWpscanOptin.checked = !!s.wpscan_optin;

				// Hosting Panel Bridge
				if (dom.settingPanelType) dom.settingPanelType.value = s.hosting_panel_type || '';
				if (dom.pageSettingPanelType) dom.pageSettingPanelType.value = s.hosting_panel_type || '';

				if (dom.settingPanelUrl) dom.settingPanelUrl.value = s.hosting_panel_url || '';
				if (dom.pageSettingPanelUrl) dom.pageSettingPanelUrl.value = s.hosting_panel_url || '';

				if (dom.settingPanelToken) dom.settingPanelToken.value = '';
				if (dom.pageSettingPanelToken) dom.pageSettingPanelToken.value = '';

				const panelMasked = s.hosting_panel_has_token
					? 'Token configured & encrypted at rest with HKDF + AES-256-GCM / libsodium.'
					: 'No API token configured.';
				if (dom.panelMaskedStatus) dom.panelMaskedStatus.textContent = panelMasked;
				if (dom.pagePanelMaskedStatus) dom.pagePanelMaskedStatus.textContent = panelMasked;

				if (dom.settingPanelOptin) dom.settingPanelOptin.checked = !!s.hosting_panel_optin;
				if (dom.pageSettingPanelOptin) dom.pageSettingPanelOptin.checked = !!s.hosting_panel_optin;

				let detectionHtml = '';
				if (s.detected_panel) {
					detectionHtml += `Auto-detected server environment: <strong>${escapeHtml(s.detected_panel)}</strong>. `;
				}
				if (s.nginx_tier === 'tier2') {
					detectionHtml += '<span style="color:#10b981;font-weight:600;">&bull; Tier 2 Companion Script Active</span>';
				} else if (s.nginx_tier === 'tier1') {
					detectionHtml += '<span style="color:#10b981;font-weight:600;">&bull; Tier 1 Control Panel Bridge Active</span>';
				} else {
					detectionHtml += '<span style="color:#d97706;font-weight:500;">&bull; Direct file-write mode / Manual Nginx</span>';
				}

				if (dom.panelDetectionInfo) {
					dom.panelDetectionInfo.innerHTML = detectionHtml;
					dom.panelDetectionInfo.style.display = 'block';
				}
				if (dom.pagePanelDetectionInfo) {
					dom.pagePanelDetectionInfo.innerHTML = detectionHtml;
					dom.pagePanelDetectionInfo.style.display = 'block';
				}

				// Webhook
				if (dom.settingWebhookUrl) dom.settingWebhookUrl.value = s.webhook_url || '';
				if (dom.pageSettingWebhookUrl) dom.pageSettingWebhookUrl.value = s.webhook_url || '';

				if (dom.settingWebhookOptin) dom.settingWebhookOptin.checked = !!s.webhook_optin;
				if (dom.pageSettingWebhookOptin) dom.pageSettingWebhookOptin.checked = !!s.webhook_optin;

				// Incident Contact & Agency
				if (dom.settingIncidentName) dom.settingIncidentName.value = s.incident_contact_name || '';
				if (dom.pageSettingIncidentName) dom.pageSettingIncidentName.value = s.incident_contact_name || '';

				if (dom.settingIncidentEmail) dom.settingIncidentEmail.value = s.incident_contact_email || '';
				if (dom.pageSettingIncidentEmail) dom.pageSettingIncidentEmail.value = s.incident_contact_email || '';

				if (dom.settingIncidentPhone) dom.settingIncidentPhone.value = s.incident_contact_phone || '';
				if (dom.pageSettingIncidentPhone) dom.pageSettingIncidentPhone.value = s.incident_contact_phone || '';

				if (dom.settingIncidentNotes) dom.settingIncidentNotes.value = s.incident_contact_notes || '';
				if (dom.pageSettingIncidentNotes) dom.pageSettingIncidentNotes.value = s.incident_contact_notes || '';

				if (dom.settingAgencyName) dom.settingAgencyName.value = s.agency_name || '';
				if (dom.pageSettingAgencyName) dom.pageSettingAgencyName.value = s.agency_name || '';

				if (statusEl) statusEl.textContent = '';
			}
		} catch (e) {
			if (statusEl) statusEl.textContent = 'Error loading settings.';
		}
	}

	async function saveSettings(isPage = false) {
		const btn = isPage ? dom.btnPageSaveSettings : dom.btnSaveSettings;
		const statusEl = isPage ? dom.pageSettingsSaveStatus : dom.settingsSaveStatus;
		if (!btn) return;

		const orig = btn.innerHTML;
		btn.disabled = true;
		btn.innerHTML = '<span class="wpsg-spinner" aria-hidden="true"></span> Saving...';
		if (statusEl) statusEl.textContent = '';

		const payload = {};

		if (isPage) {
			if (dom.pageSettingPatchstackKey && dom.pageSettingPatchstackKey.value.trim()) {
				payload.patchstack_api_key = dom.pageSettingPatchstackKey.value.trim();
			}
			if (dom.pageSettingPatchstackOptin) payload.patchstack_optin = dom.pageSettingPatchstackOptin.checked ? 1 : 0;

			if (dom.pageSettingGhsaKey && dom.pageSettingGhsaKey.value.trim()) {
				payload.ghsa_api_key = dom.pageSettingGhsaKey.value.trim();
			}
			if (dom.pageSettingGhsaOptin) payload.ghsa_optin = dom.pageSettingGhsaOptin.checked ? 1 : 0;

			if (dom.pageSettingOsvKey && dom.pageSettingOsvKey.value.trim()) {
				payload.osv_api_key = dom.pageSettingOsvKey.value.trim();
			}
			if (dom.pageSettingOsvOptin) payload.osv_optin = dom.pageSettingOsvOptin.checked ? 1 : 0;

			if (dom.pageSettingNvdKey && dom.pageSettingNvdKey.value.trim()) {
				payload.nvd_api_key = dom.pageSettingNvdKey.value.trim();
			}
			if (dom.pageSettingNvdOptin) payload.nvd_optin = dom.pageSettingNvdOptin.checked ? 1 : 0;

			if (dom.pageSettingCisaKevKey && dom.pageSettingCisaKevKey.value.trim()) {
				payload.cisa_kev_api_key = dom.pageSettingCisaKevKey.value.trim();
			}
			if (dom.pageSettingCisaKevOptin) payload.cisa_kev_optin = dom.pageSettingCisaKevOptin.checked ? 1 : 0;

			if (dom.pageSettingWpscanToken && dom.pageSettingWpscanToken.value.trim()) {
				payload.wpscan_token = dom.pageSettingWpscanToken.value.trim();
			}
			if (dom.pageSettingWpscanOptin) payload.wpscan_optin = dom.pageSettingWpscanOptin.checked ? 1 : 0;

			if (dom.pageSettingPanelType) payload.hosting_panel_type = dom.pageSettingPanelType.value;
			if (dom.pageSettingPanelUrl) payload.hosting_panel_url = dom.pageSettingPanelUrl.value.trim();
			if (dom.pageSettingPanelToken && dom.pageSettingPanelToken.value.trim()) {
				payload.hosting_panel_token = dom.pageSettingPanelToken.value.trim();
			}
			if (dom.pageSettingPanelOptin) payload.hosting_panel_optin = dom.pageSettingPanelOptin.checked ? 1 : 0;

			if (dom.pageSettingWebhookUrl) payload.webhook_url = dom.pageSettingWebhookUrl.value.trim();
			if (dom.pageSettingWebhookOptin) payload.webhook_optin = dom.pageSettingWebhookOptin.checked ? 1 : 0;
			if (dom.pageSettingIncidentName) payload.incident_contact_name = dom.pageSettingIncidentName.value.trim();
			if (dom.pageSettingIncidentEmail) payload.incident_contact_email = dom.pageSettingIncidentEmail.value.trim();
			if (dom.pageSettingIncidentPhone) payload.incident_contact_phone = dom.pageSettingIncidentPhone.value.trim();
			if (dom.pageSettingIncidentNotes) payload.incident_contact_notes = dom.pageSettingIncidentNotes.value.trim();
			if (dom.pageSettingAgencyName) payload.agency_name = dom.pageSettingAgencyName.value.trim();
		} else {
			if (dom.settingPatchstackKey && dom.settingPatchstackKey.value.trim()) {
				payload.patchstack_api_key = dom.settingPatchstackKey.value.trim();
			}
			if (dom.settingPatchstackOptin) payload.patchstack_optin = dom.settingPatchstackOptin.checked ? 1 : 0;

			if (dom.settingGhsaKey && dom.settingGhsaKey.value.trim()) {
				payload.ghsa_api_key = dom.settingGhsaKey.value.trim();
			}
			if (dom.settingGhsaOptin) payload.ghsa_optin = dom.settingGhsaOptin.checked ? 1 : 0;

			if (dom.settingOsvKey && dom.settingOsvKey.value.trim()) {
				payload.osv_api_key = dom.settingOsvKey.value.trim();
			}
			if (dom.settingOsvOptin) payload.osv_optin = dom.settingOsvOptin.checked ? 1 : 0;

			if (dom.settingNvdKey && dom.settingNvdKey.value.trim()) {
				payload.nvd_api_key = dom.settingNvdKey.value.trim();
			}
			if (dom.settingNvdOptin) payload.nvd_optin = dom.settingNvdOptin.checked ? 1 : 0;

			if (dom.settingCisaKevKey && dom.settingCisaKevKey.value.trim()) {
				payload.cisa_kev_api_key = dom.settingCisaKevKey.value.trim();
			}
			if (dom.settingCisaKevOptin) payload.cisa_kev_optin = dom.settingCisaKevOptin.checked ? 1 : 0;

			if (dom.settingWpscanToken && dom.settingWpscanToken.value.trim()) {
				payload.wpscan_token = dom.settingWpscanToken.value.trim();
			}
			if (dom.settingWpscanOptin) payload.wpscan_optin = dom.settingWpscanOptin.checked ? 1 : 0;

			if (dom.settingPanelType) payload.hosting_panel_type = dom.settingPanelType.value;
			if (dom.settingPanelUrl) payload.hosting_panel_url = dom.settingPanelUrl.value.trim();
			if (dom.settingPanelToken && dom.settingPanelToken.value.trim()) {
				payload.hosting_panel_token = dom.settingPanelToken.value.trim();
			}
			if (dom.settingPanelOptin) payload.hosting_panel_optin = dom.settingPanelOptin.checked ? 1 : 0;

			if (dom.settingWebhookUrl) payload.webhook_url = dom.settingWebhookUrl.value.trim();
			if (dom.settingWebhookOptin) payload.webhook_optin = dom.settingWebhookOptin.checked ? 1 : 0;
			if (dom.settingIncidentName) payload.incident_contact_name = dom.settingIncidentName.value.trim();
			if (dom.settingIncidentEmail) payload.incident_contact_email = dom.settingIncidentEmail.value.trim();
			if (dom.settingIncidentPhone) payload.incident_contact_phone = dom.settingIncidentPhone.value.trim();
			if (dom.settingIncidentNotes) payload.incident_contact_notes = dom.settingIncidentNotes.value.trim();
			if (dom.settingAgencyName) payload.agency_name = dom.settingAgencyName.value.trim();
		}

		try {
			const res = await apiCall({
				path: '/site-checkup-pro/v1/settings',
				method: 'POST',
				data: payload,
			});
			if (res && res.success) {
				if (statusEl) {
					statusEl.style.color = 'var(--wpsg-success)';
					statusEl.textContent = 'Settings saved successfully!';
				}
				await loadSettings(isPage);

				if (!isPage) {
					setTimeout(() => {
						closeAllModals();
						loadTasks();
					}, 600);
				} else {
					loadTasks();
					setTimeout(() => {
						if (statusEl) statusEl.textContent = '';
					}, 4000);
				}
			}
		} catch (e) {
			if (statusEl) {
				statusEl.style.color = 'var(--wpsg-danger)';
				statusEl.textContent = e.message || 'Failed to save settings.';
			}
		} finally {
			btn.disabled = false;
			btn.innerHTML = orig;
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
				await apiCall({
					path: '/site-checkup-pro/v1/review-prompt/dismiss',
					method: 'POST',
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

	// Boot immediately if document is already parsed, or wait for DOMContentLoaded
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	window.addEventListener('load', init);

})();
