/*
 * Reserved-area frontend behaviors.
 *
 * This file is loaded only on `/area-riservata` routes. It owns progressive
 * enhancement for search panels, autosubmitting filters, and row-detail AJAX
 * expansion. It should not contain business rules; it only enhances markup
 * already rendered by portal shortcodes.
 */

(function () {
	'use strict';

	function getConfig() {
		return window.culturacsiAdminUiReserved || {};
	}

	function normalizeAttivitaLabels(scope) {
		if (!scope) {
			return;
		}

		try {
			scope.querySelectorAll('.assoc-details-label').forEach(function (label) {
				const text = (label.textContent || '').trim();
				if (text.startsWith('Attivit')) {
					label.textContent = 'Attività';
				}
			});
		} catch (error) {
			/* Keep the UI usable even if label normalization fails. */
		}
	}

	function initCollapsibleSearchPanels() {
		document.querySelectorAll('.assoc-search-panel').forEach(function (panel) {
			if (!panel || panel.dataset.csiCollapsible === '1') {
				return;
			}

			const head = panel.querySelector('.assoc-search-head');
			if (!head) {
				return;
			}

			let actions = head.querySelector('.assoc-search-actions');
			if (!actions) {
				actions = document.createElement('p');
				actions.className = 'assoc-search-actions';
				head.appendChild(actions);
			}

			const toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'assoc-search-toggle';
			toggle.textContent = 'Apri ricerca';
			toggle.setAttribute('aria-expanded', 'false');
			toggle.addEventListener('click', function () {
				const willOpen = !panel.classList.contains('is-open');
				panel.classList.toggle('is-open', willOpen);
				toggle.textContent = willOpen ? 'Chiudi ricerca' : 'Apri ricerca';
				toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
			});

			actions.insertBefore(toggle, actions.firstChild);
			panel.classList.add('assoc-search-collapsible');
			panel.classList.remove('is-open');
			panel.dataset.csiCollapsible = '1';
		});
	}

	function initSearchAutosubmit() {
		document.querySelectorAll('.assoc-search-form').forEach(function (form) {
			const inputs = form.querySelectorAll('input, select');
			let debounceTimer;

			inputs.forEach(function (input) {
				input.addEventListener('change', function () {
					form.submit();
				});

				if (input.type === 'text') {
					input.addEventListener('input', function () {
						window.clearTimeout(debounceTimer);
						debounceTimer = window.setTimeout(function () {
							form.submit();
						}, 600);
					});
				}
			});
		});
	}

	function removeOpenDetailsInTbody(tbody) {
		tbody.querySelectorAll('tr.assoc-row-details').forEach(function (element) {
			element.remove();
		});
	}

	function buildErrorMessage(message) {
		const paragraph = document.createElement('p');
		paragraph.style.padding = '20px';
		paragraph.style.textAlign = 'center';
		paragraph.style.color = '#ef4444';
		paragraph.textContent = message;
		return paragraph;
	}

	function initRowDetails() {
		const config = getConfig();
		if (!config.ajaxUrl || !config.modalNonce) {
			return;
		}

		document.addEventListener('click', function (event) {
			const row = event.target.closest('.assoc-admin-table tbody tr[data-id]');
			if (!row) {
				return;
			}

			if (event.target.closest('.assoc-action-group') || event.target.closest('a') || event.target.closest('button')) {
				return;
			}

			const id = row.getAttribute('data-id');
			const type = row.getAttribute('data-type');
			if (!id || !type) {
				return;
			}

			const next = row.nextElementSibling;
			if (next && next.classList.contains('assoc-row-details')) {
				next.remove();
				return;
			}

			const tbody = row.parentElement;
			removeOpenDetailsInTbody(tbody);

			const colSpan = row.children.length || 8;
			const cache = (window.assocRowCache = window.assocRowCache || {});
			const cacheKey = type + ':' + id;
			const detailsRow = document.createElement('tr');
			detailsRow.className = 'assoc-row-details';
			detailsRow.setAttribute('data-for-id', id);
			detailsRow.innerHTML = '<td colspan="' + colSpan + '"><div class="assoc-row-details-inner"><div class="assoc-modal-loader"></div></div><div class="assoc-row-details-footer" id="assoc-row-details-footer-' + id + '"></div></td>';
			row.insertAdjacentElement('afterend', detailsRow);

			if (cache[cacheKey]) {
				const inner = detailsRow.querySelector('.assoc-row-details-inner');
				const footer = detailsRow.querySelector('#assoc-row-details-footer-' + id);
				inner.innerHTML = cache[cacheKey].html;
				footer.innerHTML = cache[cacheKey].footer || '';
				normalizeAttivitaLabels(inner);
				return;
			}

			const requestUrl = new URL(config.ajaxUrl, window.location.origin);
			requestUrl.searchParams.set('action', 'culturacsi_get_modal_data');
			requestUrl.searchParams.set('id', id);
			requestUrl.searchParams.set('type', type);
			requestUrl.searchParams.set('nonce', config.modalNonce);

			window.fetch(requestUrl.toString())
				.then(function (response) {
					return response.json();
				})
				.then(function (result) {
					const inner = detailsRow.querySelector('.assoc-row-details-inner');
					const footer = detailsRow.querySelector('#assoc-row-details-footer-' + id);

					if (result && result.success) {
						inner.innerHTML = result.data.html;
						footer.innerHTML = result.data.footer || '';
						cache[cacheKey] = {
							html: result.data.html,
							footer: result.data.footer || ''
						};
						normalizeAttivitaLabels(inner);
						return;
					}

					inner.innerHTML = '';
					inner.appendChild(buildErrorMessage((result && result.data) || 'Errore di caricamento.'));
					footer.innerHTML = '';
				})
				.catch(function () {
					const inner = detailsRow.querySelector('.assoc-row-details-inner');
					inner.innerHTML = '';
					inner.appendChild(buildErrorMessage('Errore di caricamento.'));
				});
		});
	}

	function initModifiedFieldsTracking() {
		document.querySelectorAll('.assoc-portal-form').forEach(function (form) {
			let hiddenInput = form.querySelector('input[name="_assoc_modified_fields"]');
			if (!hiddenInput) {
				hiddenInput = document.createElement('input');
				hiddenInput.type = 'hidden';
				hiddenInput.name = '_assoc_modified_fields';
				hiddenInput.value = '';
				form.appendChild(hiddenInput);
			}

			const changedFields = new Set();
			const recordChange = function (event) {
				if (!event.target || !event.target.name) {
					return;
				}

				if (event.target.name === '_assoc_modified_fields' || event.target.name.indexOf('culturacsi_') === 0) {
					return;
				}

				changedFields.add(event.target.name);
				hiddenInput.value = Array.from(changedFields).join(',');
			};

			form.addEventListener('input', recordChange);
			form.addEventListener('change', recordChange);
		});
	}

	function initPasswordToggles() {
		document.querySelectorAll('.password-toggle-wrapper').forEach(function (wrapper) {
			var button = wrapper.querySelector('.password-toggle-btn');
			var input = wrapper.querySelector('input[type="password"], input[type="text"]');
			if (!button || !input) {
				return;
			}

			button.addEventListener('click', function () {
				var isPassword = input.type === 'password';
				input.type = isPassword ? 'text' : 'password';
				button.textContent = isPassword ? 'Nascondi' : 'Mostra';
			});
		});
	}

	function initCronologiaDetails() {
		var table = document.querySelector('.assoc-table-cronologia');
		if (!table) {
			return;
		}

		table.addEventListener('click', function (event) {
			var row = event.target.closest('.cron-data-row');
			if (!row) {
				return;
			}

			var targetId = row.getAttribute('data-target');
			if (!targetId) {
				return;
			}

			var detail = document.getElementById(targetId);
			if (!detail) {
				return;
			}

			var isOpen = detail.classList.contains('is-open');
			detail.classList.toggle('is-open', !isOpen);
			row.classList.toggle('is-open', !isOpen);
			row.setAttribute('aria-expanded', String(!isOpen));
		});
	}

	function initContentSearchTypeToggle() {
		var form = document.getElementById('assoc-content-search-form');
		if (!form) {
			return;
		}

		var typeField = form.querySelector('#c_type');
		if (!typeField || typeField.dataset.csiToggleBound === '1') {
			return;
		}

		var toggle = function () {
			var current = typeField.value || 'all';
			var eventRow = form.querySelector('[data-type-field="event"]');
			if (eventRow) {
				eventRow.style.display = current === 'event' ? '' : 'none';
			}
		};

		typeField.dataset.csiToggleBound = '1';
		typeField.addEventListener('change', toggle);
		toggle();
	}

	function initPortalGuidance() {
		if (window.__csiPortalUxBound) {
			return;
		}
		window.__csiPortalUxBound = true;

		var isValueFilled = function (node) {
			if (!node) {
				return false;
			}
			var tag = (node.tagName || '').toLowerCase();
			var type = (node.type || '').toLowerCase();
			if (type === 'checkbox' || type === 'radio') {
				return !!node.checked;
			}
			if (tag === 'select') {
				var val = (node.value || '').trim();
				return val !== '' && val !== '0' && val !== 'all';
			}
			if (tag === 'input' || tag === 'textarea') {
				return (node.value || '').trim() !== '';
			}
			return (node.textContent || '').trim() !== '';
		};

		var selectorDone = function (scope, selector) {
			if (!scope || !selector) {
				return false;
			}
			var nodes = scope.querySelectorAll(selector);
			if (!nodes || !nodes.length) {
				return false;
			}
			for (var i = 0; i < nodes.length; i++) {
				if (isValueFilled(nodes[i])) {
					return true;
				}
			}
			return false;
		};

		var findChecklistForm = function (checklist) {
			if (!checklist) {
				return null;
			}
			var insideForm = checklist.closest('form');
			if (insideForm) {
				return insideForm;
			}
			var anchor = checklist.closest('.csi-process-guide') || checklist;
			var node = anchor.nextElementSibling;
			while (node) {
				if ((node.tagName || '').toLowerCase() === 'form') {
					return node;
				}
				if (node.querySelector) {
					var nested = node.querySelector('form');
					if (nested) {
						return nested;
					}
				}
				node = node.nextElementSibling;
			}
			return null;
		};

		var toggleFormSubmitState = function (form, enabled) {
			if (!form) {
				return;
			}
			var submits = form.querySelectorAll('button[type="submit"], input[type="submit"]');
			for (var i = 0; i < submits.length; i++) {
				var submit = submits[i];
				submit.disabled = !enabled;
				submit.setAttribute('aria-disabled', enabled ? 'false' : 'true');
				submit.classList.toggle('is-disabled-by-checklist', !enabled);
			}
			form.setAttribute('data-csi-checklist-required', '1');
			form.setAttribute('data-csi-checklist-complete', enabled ? '1' : '0');
		};

		var stickyInstances = [];
		var stickyTicking = false;

		var stickyTopOffset = function () {
			var offset = 12;
			var adminBar = document.getElementById('wpadminbar');
			if (adminBar && adminBar.offsetHeight > 0) {
				offset += adminBar.offsetHeight + 6;
			}
			var header = document.querySelector('body.assoc-reserved-page #masthead, body.assoc-reserved-page header.site-header');
			if (header) {
				var style = window.getComputedStyle(header);
				if (style && (style.position === 'fixed' || style.position === 'sticky')) {
					offset += header.offsetHeight;
				}
			}
			return offset;
		};

		var updateStickyInstance = function (instance) {
			if (!instance || !instance.node || !instance.placeholder) {
				return;
			}
			var checklist = instance.node;
			var placeholder = instance.placeholder;
			if (window.innerWidth < 960) {
				checklist.classList.remove('is-sticky-active');
				checklist.style.position = '';
				checklist.style.top = '';
				checklist.style.left = '';
				checklist.style.right = '';
				checklist.style.width = '';
				placeholder.style.display = 'none';
				placeholder.style.height = '';
				return;
			}
			var topOffset = stickyTopOffset();
			var startTop = instance.startTop || checklist.getBoundingClientRect().top + window.scrollY;
			var shouldStick = window.scrollY > startTop - topOffset;

			if (!shouldStick) {
				checklist.classList.remove('is-sticky-active');
				checklist.style.position = '';
				checklist.style.top = '';
				checklist.style.left = '';
				checklist.style.right = '';
				checklist.style.width = '';
				placeholder.style.display = 'none';
				placeholder.style.height = '';
				instance.startTop = checklist.getBoundingClientRect().top + window.scrollY;
				return;
			}

			var content = checklist.closest('.entry-content') || document.querySelector('body.assoc-reserved-page .entry-content');
			var contentRect = content ? content.getBoundingClientRect() : document.body.getBoundingClientRect();
			var stickyWidth = Math.min(360, Math.max(260, Math.floor(contentRect.width * 0.33)));
			var left = Math.max(12, Math.floor(contentRect.left + contentRect.width - stickyWidth - 8));
			placeholder.style.display = 'block';
			placeholder.style.height = checklist.offsetHeight + 'px';
			checklist.classList.add('is-sticky-active');
			checklist.style.position = 'fixed';
			checklist.style.top = topOffset + 'px';
			checklist.style.left = left + 'px';
			checklist.style.right = '';
			checklist.style.width = stickyWidth + 'px';
		};

		var refreshSticky = function () {
			stickyTicking = false;
			for (var i = 0; i < stickyInstances.length; i++) {
				updateStickyInstance(stickyInstances[i]);
			}
		};

		var queueStickyRefresh = function () {
			if (stickyTicking) {
				return;
			}
			stickyTicking = true;
			window.requestAnimationFrame(refreshSticky);
		};

		var initChecklistSticky = function (checklist) {
			if (!checklist || checklist.__csiStickyBound) {
				return;
			}
			checklist.__csiStickyBound = true;
			var placeholder = document.createElement('div');
			placeholder.className = 'csi-checklist-placeholder';
			checklist.parentNode.insertBefore(placeholder, checklist);
			stickyInstances.push({
				node: checklist,
				placeholder: placeholder,
				startTop: checklist.getBoundingClientRect().top + window.scrollY
			});
		};

		var refreshChecklist = function (checklist) {
			if (!checklist) {
				return;
			}
			var form = checklist.closest('form') || document;
			var linkedForm = findChecklistForm(checklist);
			var items = checklist.querySelectorAll('li[data-csi-selectors]');
			var total = 0;
			var done = 0;
			for (var i = 0; i < items.length; i++) {
				var item = items[i];
				var raw = (item.getAttribute('data-csi-selectors') || '').trim();
				if (!raw) {
					continue;
				}
				item.classList.remove('is-current');
				var selectors = raw.split('||');
				var mode = (item.getAttribute('data-csi-mode') || 'all').toLowerCase();
				var state = mode === 'any' ? false : true;
				for (var j = 0; j < selectors.length; j++) {
					var selector = (selectors[j] || '').trim();
					if (!selector) {
						continue;
					}
					var ok = selectorDone(form, selector);
					if (mode === 'any') {
						state = state || ok;
					} else {
						state = state && ok;
					}
				}
				total++;
				if (state) {
					done++;
				}
				item.classList.toggle('is-done', state);
				var mark = item.querySelector('.csi-check-state');
				if (mark) {
					var stepNum = (item.getAttribute('data-csi-step-num') || String(total)).trim();
					mark.textContent = state ? 'OK' : stepNum;
				}
			}
			if (done < total) {
				for (var k = 0; k < items.length; k++) {
					if (!items[k].classList.contains('is-done')) {
						items[k].classList.add('is-current');
						break;
					}
				}
			}
			var progress = checklist.querySelector('[data-csi-progress]');
			if (progress) {
				progress.textContent = 'Completati ' + done + ' di ' + total + ' passaggi';
			}
			if (linkedForm && total > 0) {
				toggleFormSubmitState(linkedForm, done >= total);
			}
		};

		var refreshAll = function () {
			var lists = document.querySelectorAll('[data-csi-checklist="1"]');
			for (var i = 0; i < lists.length; i++) {
				initChecklistSticky(lists[i]);
				refreshChecklist(lists[i]);
			}
			queueStickyRefresh();
		};

		document.addEventListener('change', function (event) {
			var target = event.target;
			if (target && target.classList && target.classList.contains('csi-creation-hub-select')) {
				var selectedUrl = (target.value || '').trim();
				if (selectedUrl) {
					window.location.href = selectedUrl;
					return;
				}
				var resetUrl = (target.getAttribute('data-reset-url') || '').trim();
				if (resetUrl) {
					window.location.href = resetUrl;
					return;
				}
				if (window.location && window.location.pathname) {
					window.location.href = window.location.pathname;
					return;
				}
			}
			refreshAll();
		});

		var bindTinyMce = function () {
			if (!window.tinymce || !window.tinymce.editors) {
				return;
			}
			for (var i = 0; i < window.tinymce.editors.length; i++) {
				var editor = window.tinymce.editors[i];
				if (!editor || editor.__csiChecklistBound) {
					continue;
				}
				editor.__csiChecklistBound = true;
				var handler = function () {
					try {
						editor.save();
					} catch (error) {}
					refreshAll();
				};
				editor.on('change keyup input SetContent Undo Redo', handler);
			}
		};

		window.setInterval(bindTinyMce, 1200);
		document.addEventListener('focusin', bindTinyMce);
		document.addEventListener('input', refreshAll);
		document.addEventListener('submit', function (event) {
			var form = event.target;
			if (!form || !form.getAttribute) {
				return;
			}
			if (form.getAttribute('data-csi-checklist-required') !== '1') {
				return;
			}
			if (form.getAttribute('data-csi-checklist-complete') === '1') {
				return;
			}
			event.preventDefault();
			refreshAll();
			var firstChecklist = document.querySelector('[data-csi-checklist="1"]');
			if (firstChecklist && firstChecklist.scrollIntoView) {
				firstChecklist.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		}, true);
		window.addEventListener('scroll', queueStickyRefresh, { passive: true });
		window.addEventListener('resize', queueStickyRefresh);
		document.addEventListener('DOMContentLoaded', refreshAll);
		refreshAll();
	}

	document.addEventListener('DOMContentLoaded', function () {
		initCollapsibleSearchPanels();
		initSearchAutosubmit();
		initRowDetails();
		initModifiedFieldsTracking();
		initPasswordToggles();
		initCronologiaDetails();
		initContentSearchTypeToggle();
		initPortalGuidance();
	});
}());
