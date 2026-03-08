(function () {
	'use strict';

	var openBtn = document.getElementById('abf-open-struct-modal-portal');
	if (!openBtn) {
		return;
	}

	var config = window.culturacsiAssociationsStructureEditor || {};
	var ajaxurl = config.ajaxUrl || window.ajaxurl || '';
	var nonce = config.nonce || '';
	if (!ajaxurl || !nonce) {
		return;
	}

	var modal = null;
	var overlay = null;
	var content = null;
	var footer = null;
	var closeBtn = null;
	var titleEl = null;
	var tree = { macros: [] };
	var nodeUidCounter = 1;
	var pendingFocusId = '';

	function qn(tag) {
		return document.createElement(tag);
	}

	function nextNodeUid() {
		return 'n' + nodeUidCounter++;
	}

	function ensureNodeUid(node) {
		if (!node || typeof node !== 'object') {
			return '';
		}
		if (!node._uid) {
			node._uid = nextNodeUid();
		}
		return node._uid;
	}

	function compareLabels(a, b) {
		return (a || '').trim().localeCompare((b || '').trim(), 'it', { sensitivity: 'base', numeric: true });
	}

	function queueFocus(node) {
		pendingFocusId = node && node._uid ? String(node._uid) : '';
	}

	function applyPendingFocus() {
		if (!pendingFocusId || !content) {
			return;
		}
		var focusTarget = null;
		var candidates = content.querySelectorAll('[data-node-id]');
		for (var i = 0; i < candidates.length; i++) {
			if ((candidates[i].getAttribute('data-node-id') || '') === pendingFocusId) {
				focusTarget = candidates[i];
				break;
			}
		}
		pendingFocusId = '';
		if (!focusTarget) {
			return;
		}
		try {
			focusTarget.focus({ preventScroll: true });
		} catch (error) {
			focusTarget.focus();
		}
		if (typeof focusTarget.select === 'function') {
			focusTarget.select();
		}
		try {
			focusTarget.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
		} catch (error) {
			focusTarget.scrollIntoView();
		}
	}

	function makeSettore(label) {
		return { _uid: nextNodeUid(), label: label || '', settori2: [{ _uid: nextNodeUid(), label: '' }] };
	}

	function makeMacro(label) {
		return { _uid: nextNodeUid(), label: label || '', settori: [makeSettore('')] };
	}

	function addMacroAfter(index, label) {
		var macros = tree.macros = Array.isArray(tree.macros) ? tree.macros : [];
		var next = makeMacro(label || '');
		if (index < 0 || index >= macros.length) {
			macros.push(next);
		} else {
			macros.splice(index + 1, 0, next);
		}
		return next;
	}

	function addMacroAlphabetical(label) {
		var macros = tree.macros = Array.isArray(tree.macros) ? tree.macros : [];
		var wanted = (label || '').trim();
		if (wanted === '') {
			var blank = makeMacro('');
			macros.push(blank);
			return blank;
		}
		for (var j = 0; j < macros.length; j++) {
			var existingLabel = macros[j] && macros[j].label ? String(macros[j].label).trim() : '';
			if (existingLabel !== '' && compareLabels(wanted, existingLabel) === 0) {
				return macros[j];
			}
		}
		var next = makeMacro(wanted);
		var insertAt = macros.length;
		for (var i = 0; i < macros.length; i++) {
			var currentLabel = macros[i] && macros[i].label ? String(macros[i].label).trim() : '';
			if (currentLabel === '') {
				continue;
			}
			if (compareLabels(wanted, currentLabel) < 0) {
				insertAt = i;
				break;
			}
		}
		macros.splice(insertAt, 0, next);
		return next;
	}

	function addSettoreAfter(macroNode, index, label) {
		if (!macroNode || typeof macroNode !== 'object') {
			return null;
		}
		var settori = macroNode.settori = Array.isArray(macroNode.settori) ? macroNode.settori : [];
		var next = makeSettore(label || '');
		if (index < 0 || index >= settori.length) {
			settori.push(next);
		} else {
			settori.splice(index + 1, 0, next);
		}
		return next;
	}

	function addSettore2After(settoreNode, index, label) {
		if (!settoreNode || typeof settoreNode !== 'object') {
			return null;
		}
		var leaves = settoreNode.settori2 = Array.isArray(settoreNode.settori2) ? settoreNode.settori2 : [];
		var next = { _uid: nextNodeUid(), label: label || '' };
		if (index < 0 || index >= leaves.length) {
			leaves.push(next);
		} else {
			leaves.splice(index + 1, 0, next);
		}
		return next;
	}

	function normalizeTreeShape() {
		if (!tree || typeof tree !== 'object') {
			tree = { macros: [] };
		}
		if (!Array.isArray(tree.macros)) {
			tree.macros = [];
		}
		tree.macros = tree.macros.map(function (macro) {
			macro = macro && typeof macro === 'object' ? macro : {};
			ensureNodeUid(macro);
			macro.label = macro.label || '';
			if (!Array.isArray(macro.settori)) {
				macro.settori = [];
			}
			macro.settori = macro.settori.map(function (settore) {
				settore = settore && typeof settore === 'object' ? settore : {};
				ensureNodeUid(settore);
				settore.label = settore.label || '';
				if (!Array.isArray(settore.settori2)) {
					settore.settori2 = [];
				}
				settore.settori2 = settore.settori2.map(function (leaf) {
					leaf = leaf && typeof leaf === 'object' ? leaf : {};
					ensureNodeUid(leaf);
					leaf.label = leaf.label || '';
					return leaf;
				});
				return settore;
			});
			return macro;
		});
	}

	function makeIconButton(sign, title, className) {
		var button = qn('button');
		button.type = 'button';
		button.className = 'assoc-struct-icon-btn ' + (className || '');
		button.title = title || '';
		button.setAttribute('aria-label', title || '');
		button.textContent = sign;
		return button;
	}

	function close() {
		if (!modal) {
			return;
		}
		modal.classList.remove('is-open');
		content.innerHTML = '';
		footer.innerHTML = '';
	}

	function ensureModal() {
		if (modal && content && footer && titleEl) {
			return true;
		}
		modal = document.getElementById('assoc-portal-modal');
		if (!modal) {
			modal = document.createElement('div');
			modal.id = 'assoc-portal-modal';
			modal.className = 'assoc-modal';
			modal.innerHTML = '<div class="assoc-modal-overlay"></div><div class="assoc-modal-container"><header class="assoc-modal-header"><h2 class="assoc-modal-title" id="assoc-modal-title">Dettagli</h2><button class="assoc-modal-close" aria-label="Chiudi">&#215;</button></header><main class="assoc-modal-content" id="assoc-modal-content"></main><footer class="assoc-modal-footer" id="assoc-modal-footer"></footer></div>';
			document.body.appendChild(modal);
		}
		overlay = modal.querySelector('.assoc-modal-overlay');
		content = document.getElementById('assoc-modal-content');
		footer = document.getElementById('assoc-modal-footer');
		closeBtn = modal.querySelector('.assoc-modal-close');
		titleEl = document.getElementById('assoc-modal-title');
		if (overlay) {
			overlay.addEventListener('click', close);
		}
		if (closeBtn) {
			closeBtn.addEventListener('click', close);
		}
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && modal && modal.classList.contains('is-open')) {
				close();
			}
		});
		return true;
	}

	function loadTree() {
		if (!ensureModal()) {
			return;
		}
		content.textContent = 'Caricamento...';
		var form = new FormData();
		form.append('action', 'abf_get_settori_tree');
		form.append('_wpnonce', nonce);
		fetch(ajaxurl, { method: 'POST', body: form })
			.then(function (response) { return response.json(); })
			.then(function (data) {
				if (!data || !data.success) {
					content.textContent = (data && data.message) || 'Errore';
					return;
				}
				tree = data.data || { macros: [] };
				normalizeTreeShape();
				render();
			})
			.catch(function () {
				content.textContent = 'Errore rete';
			});
	}

	function serialize() {
		var out = {};
		(tree.macros || []).forEach(function (macro) {
			var macroLabel = (macro.label || '').trim();
			if (!macroLabel) {
				return;
			}
			if (!out[macroLabel]) {
				out[macroLabel] = {};
			}
			(macro.settori || []).forEach(function (settore) {
				var settoreLabel = (settore.label || '').trim();
				if (!settoreLabel) {
					return;
				}
				if (!out[macroLabel][settoreLabel]) {
					out[macroLabel][settoreLabel] = [];
				}
				(settore.settori2 || []).forEach(function (settore2) {
					var settore2Label = (settore2.label || '').trim();
					if (!settore2Label) {
						return;
					}
					out[macroLabel][settoreLabel].push(settore2Label);
				});
			});
		});
		return out;
	}

	function save() {
		var form = new FormData();
		form.append('action', 'abf_save_settori_tree');
		form.append('_wpnonce', nonce);
		form.append('manual_nodes', JSON.stringify(serialize()));
		fetch(ajaxurl, { method: 'POST', body: form })
			.then(function (response) { return response.json(); })
			.then(function (data) {
				if (!data || !data.success) {
					window.alert((data && data.message) || 'Errore salvataggio');
					return;
				}
				window.alert('Struttura salvata.');
				close();
			})
			.catch(function () {
				window.alert('Errore rete');
			});
	}

	function render() {
		if (!ensureModal()) {
			return;
		}
		normalizeTreeShape();
		content.innerHTML = '';
		footer.innerHTML = '';

		var wrap = qn('div');
		wrap.className = 'assoc-struct-editor';

		var addMacro = qn('div');
		addMacro.className = 'assoc-struct-top-add';
		var macroInput = qn('input');
		macroInput.type = 'text';
		macroInput.className = 'assoc-struct-input';
		macroInput.placeholder = 'Nuova macro categoria';
		var macroAddBtn = qn('button');
		macroAddBtn.type = 'button';
		macroAddBtn.className = 'button button-primary';
		macroAddBtn.textContent = 'Aggiungi Macro';
		macroAddBtn.addEventListener('click', function () {
			var value = (macroInput.value || '').trim();
			if (!value) {
				return;
			}
			var createdMacro = addMacroAlphabetical(value);
			queueFocus(createdMacro);
			macroInput.value = '';
			render();
		});
		addMacro.appendChild(macroInput);
		addMacro.appendChild(macroAddBtn);
		wrap.appendChild(addMacro);

		if (!Array.isArray(tree.macros) || tree.macros.length === 0) {
			var empty = qn('div');
			empty.className = 'assoc-struct-empty';
			empty.textContent = 'Nessuna macro categoria. Aggiungi la prima voce.';
			wrap.appendChild(empty);
		}

		(tree.macros || []).forEach(function (macro, macroIndex) {
			var macroCard = qn('section');
			macroCard.className = 'assoc-struct-card';

			var macroHead = qn('div');
			macroHead.className = 'assoc-struct-row assoc-struct-row-macro';
			var macroEdit = qn('input');
			macroEdit.type = 'text';
			macroEdit.className = 'assoc-struct-input';
			macroEdit.setAttribute('data-node-id', String(ensureNodeUid(macro)));
			macroEdit.value = macro.label || '';
			macroEdit.addEventListener('input', function (event) {
				tree.macros[macroIndex].label = event.target.value;
			});
			var macroActions = qn('div');
			macroActions.className = 'assoc-struct-actions';
			var macroAdd = makeIconButton('+', 'Aggiungi nuova macro categoria', 'is-plus');
			macroAdd.addEventListener('click', function () {
				var created = addMacroAfter(macroIndex, '');
				queueFocus(created);
				render();
			});
			var macroDelete = makeIconButton('-', 'Rimuovi macro categoria', 'is-minus');
			macroDelete.addEventListener('click', function () {
				tree.macros.splice(macroIndex, 1);
				render();
			});
			macroActions.appendChild(macroAdd);
			macroActions.appendChild(macroDelete);
			var macroLevel = qn('span');
			macroLevel.className = 'assoc-struct-level assoc-level-macro';
			macroLevel.textContent = 'Macro Categoria';
			macroHead.appendChild(macroEdit);
			macroHead.appendChild(macroActions);
			macroHead.appendChild(macroLevel);
			macroCard.appendChild(macroHead);

			var macroBranch = qn('div');
			macroBranch.className = 'assoc-struct-children assoc-struct-children-macro';

			if (!Array.isArray(macro.settori) || macro.settori.length === 0) {
				var emptySettori = qn('div');
				emptySettori.className = 'assoc-struct-empty';
				emptySettori.textContent = 'Nessun settore in questa macro.';
				var quickAddSettoreWrap = qn('div');
				quickAddSettoreWrap.className = 'assoc-struct-quick-add';
				var quickAddSettore = qn('button');
				quickAddSettore.type = 'button';
				quickAddSettore.className = 'button button-small';
				quickAddSettore.textContent = 'Aggiungi primo settore';
				quickAddSettore.addEventListener('click', function () {
					var createdFirstSettore = addSettoreAfter(macro, -1, '');
					queueFocus(createdFirstSettore);
					render();
				});
				quickAddSettoreWrap.appendChild(quickAddSettore);
				macroBranch.appendChild(emptySettori);
				macroBranch.appendChild(quickAddSettoreWrap);
			}

			(macro.settori || []).forEach(function (settore, settoreIndex) {
				var settoreNode = qn('div');
				settoreNode.className = 'assoc-struct-settore-wrap';

				var settoreHead = qn('div');
				settoreHead.className = 'assoc-struct-row assoc-struct-row-settore';
				var settoreEdit = qn('input');
				settoreEdit.type = 'text';
				settoreEdit.className = 'assoc-struct-input';
				settoreEdit.setAttribute('data-node-id', String(ensureNodeUid(settore)));
				settoreEdit.value = settore.label || '';
				settoreEdit.addEventListener('input', function (event) {
					settore.label = event.target.value;
				});
				var settoreActions = qn('div');
				settoreActions.className = 'assoc-struct-actions';
				var settoreAdd = makeIconButton('+', 'Aggiungi nuovo settore', 'is-plus');
				settoreAdd.addEventListener('click', function () {
					var createdSettore = addSettoreAfter(macro, settoreIndex, '');
					queueFocus(createdSettore);
					render();
				});
				var settoreDelete = makeIconButton('-', 'Rimuovi settore', 'is-minus');
				settoreDelete.addEventListener('click', function () {
					macro.settori.splice(settoreIndex, 1);
					render();
				});
				settoreActions.appendChild(settoreAdd);
				settoreActions.appendChild(settoreDelete);
				var settoreLevel = qn('span');
				settoreLevel.className = 'assoc-struct-level assoc-level-settore';
				settoreLevel.textContent = 'Settore';
				settoreHead.appendChild(settoreEdit);
				settoreHead.appendChild(settoreActions);
				settoreHead.appendChild(settoreLevel);
				settoreNode.appendChild(settoreHead);

				var leafList = qn('div');
				leafList.className = 'assoc-struct-settore2-wrap';
				if (!Array.isArray(settore.settori2) || settore.settori2.length === 0) {
					var emptyLeaves = qn('div');
					emptyLeaves.className = 'assoc-struct-empty';
					emptyLeaves.textContent = 'Nessun settore 2 in questo settore.';
					var quickAddSettore2Wrap = qn('div');
					quickAddSettore2Wrap.className = 'assoc-struct-quick-add';
					var quickAddSettore2 = qn('button');
					quickAddSettore2.type = 'button';
					quickAddSettore2.className = 'button button-small';
					quickAddSettore2.textContent = 'Aggiungi primo settore 2';
					quickAddSettore2.addEventListener('click', function () {
						var createdSettore2 = addSettore2After(settore, -1, '');
						queueFocus(createdSettore2);
						render();
					});
					quickAddSettore2Wrap.appendChild(quickAddSettore2);
					leafList.appendChild(emptyLeaves);
					leafList.appendChild(quickAddSettore2Wrap);
				}

				(settore.settori2 || []).forEach(function (settore2, settore2Index) {
					var leaf = qn('div');
					leaf.className = 'assoc-struct-row assoc-struct-row-settore2';
					var leafEdit = qn('input');
					leafEdit.type = 'text';
					leafEdit.className = 'assoc-struct-input';
					leafEdit.setAttribute('data-node-id', String(ensureNodeUid(settore2)));
					leafEdit.value = settore2.label || '';
					leafEdit.addEventListener('input', function (event) {
						settore2.label = event.target.value;
					});
					var leafActions = qn('div');
					leafActions.className = 'assoc-struct-actions';
					var leafAdd = makeIconButton('+', 'Aggiungi nuovo settore 2', 'is-plus');
					leafAdd.addEventListener('click', function () {
						var createdLeaf = addSettore2After(settore, settore2Index, '');
						queueFocus(createdLeaf);
						render();
					});
					var leafDelete = makeIconButton('-', 'Rimuovi settore 2', 'is-minus');
					leafDelete.addEventListener('click', function () {
						settore.settori2.splice(settore2Index, 1);
						render();
					});
					leafActions.appendChild(leafAdd);
					leafActions.appendChild(leafDelete);
					var leafLevel = qn('span');
					leafLevel.className = 'assoc-struct-level assoc-level-settore2';
					leafLevel.textContent = 'Settore 2';
					leaf.appendChild(leafEdit);
					leaf.appendChild(leafActions);
					leaf.appendChild(leafLevel);
					leafList.appendChild(leaf);
				});
				settoreNode.appendChild(leafList);
				macroBranch.appendChild(settoreNode);
			});

			macroCard.appendChild(macroBranch);
			wrap.appendChild(macroCard);
		});

		content.appendChild(wrap);

		var closeBtnFooter = qn('button');
		closeBtnFooter.type = 'button';
		closeBtnFooter.className = 'button';
		closeBtnFooter.textContent = 'Chiudi';
		closeBtnFooter.addEventListener('click', close);

		var saveBtn = qn('button');
		saveBtn.type = 'button';
		saveBtn.className = 'button button-primary';
		saveBtn.textContent = 'Salva Struttura';
		saveBtn.addEventListener('click', save);

		footer.appendChild(closeBtnFooter);
		footer.appendChild(saveBtn);
		applyPendingFocus();
	}

	function open() {
		if (!ensureModal()) {
			return;
		}
		modal.classList.add('is-open');
		titleEl.textContent = 'Struttura Settori';
		loadTree();
	}

	openBtn.addEventListener('click', open);
}());
