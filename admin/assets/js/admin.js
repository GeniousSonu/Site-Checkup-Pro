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
		activeTab: 'all',
		filterLevel: '',
		filterStatus: '',
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
	}

	/**
	 * Cache DOM elements
	 */
	function cacheDom() {
		dom.app = document.getElementById('wpsg-app');
		if (!dom.app) return;

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
	}

	/**
	 * Bind UI event listeners
	 */
	function bindEvents() {
		if (!dom.app) return;

		// Tab Switching
		dom.tabs.forEach(tab => {
			tab.addEventListener('click', () => {
				dom.tabs.forEach(t => t.classList.remove('active'));
				tab.classList.add('active');
				state.activeTab = tab.getAttribute('data-tab');
				renderViews();
			});
		});

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
	}

	/**
	 * Render views depending on active tab
	 */
	function renderViews() {
		if (state.activeTab === 'audit_trail') {
			if (dom.tableWrapper) dom.tableWrapper.style.display = 'none';
			if (dom.auditView) dom.auditView.style.display = 'block';
			loadAuditLogs();
		} else {
			if (dom.tableWrapper) dom.tableWrapper.style.display = 'block';
			if (dom.auditView) dom.auditView.style.display = 'none';
			renderTasksTable();
		}
	}

	/**
	 * Render checklist table rows with filters
	 */
	function renderTasksTable() {
		if (!dom.tbody) return;

		let filtered = state.tasks;

		// 1. Filter by Section tab
		if (state.activeTab !== 'all' && state.activeTab !== 'audit_trail') {
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
							<p>No tasks match the active filters. Try switching section tabs or clearing status filters.</p>
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
			if (task.guide_data && task.guide_data.action === 'modal_login_rename') {
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
	}

	/**
	 * Run a single task via REST API
	 */
	async function runTask(taskId, btnElement = null) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		// Set button loading state
		if (btnElement) {
			btnElement.disabled = true;
			btnElement.innerHTML = '<span class="wpsg-spinner"></span> Running...';
		}

		try {
			const res = await wp.apiFetch({
				path: `/site-checkup-pro/v1/tasks/${taskId}/run`,
				method: 'POST',
			});

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
	async function undoTask(taskId, btnElement = null) {
		const task = state.tasks.find(t => t.id === taskId);
		if (!task) return;

		if (btnElement) {
			btnElement.disabled = true;
			btnElement.innerHTML = '<span class="wpsg-spinner"></span> Undoing...';
		}

		try {
			const res = await wp.apiFetch({
				path: `/site-checkup-pro/v1/tasks/${taskId}/undo`,
				method: 'POST',
			});

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
			});

			alert(res.message || 'Baseline updated.');
			loadTasks();
		} catch (err) {
			alert(`Failed to update baseline: ${err.message || 'Error'}`);
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
		document.querySelectorAll('.wpsg-modal-overlay').forEach(m => {
			m.style.display = 'none';
		});
		state.pendingActionTask = null;
		if (dom.inputLoginConfirm) dom.inputLoginConfirm.value = '';
		if (dom.btnConfirmLoginRename) dom.btnConfirmLoginRename.disabled = true;
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

	// Boot on DOM Ready
	document.addEventListener('DOMContentLoaded', init);

})();
