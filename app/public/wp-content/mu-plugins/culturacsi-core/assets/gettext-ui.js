(function () {
	'use strict';

	function initReservedLoginModal() {
		var modal = document.getElementById('culturacsi-login-modal');
		if (!modal) {
			return;
		}

		var closeBtn = modal.querySelector('.culturacsi-login-close');
		var overlay = modal.querySelector('.culturacsi-login-overlay');

		function setActiveAuthTab(tabName) {
			var wrap = modal.querySelector('[data-assoc-auth-wrap]');
			if (!wrap) {
				return;
			}
			wrap.querySelectorAll('[data-auth-tab]').forEach(function (tab) {
				var isActive = (tab.getAttribute('data-auth-tab') || '') === tabName;
				tab.classList.toggle('is-active', isActive);
				tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
			});
			wrap.querySelectorAll('[data-auth-pane]').forEach(function (pane) {
				var isActive = (pane.getAttribute('data-auth-pane') || '') === tabName;
				pane.classList.toggle('is-active', isActive);
			});
		}

		function openModal(event, forceTab) {
			if (event) {
				event.preventDefault();
			}
			if (forceTab !== 'recover' && forceTab !== 'register') {
				forceTab = 'login';
			}
			setActiveAuthTab(forceTab);
			modal.classList.add('is-open');
			modal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('culturacsi-login-open');

			var input = forceTab === 'register'
				? document.getElementById('assoc_reg_first_name')
				: (forceTab === 'recover' ? document.getElementById('assoc_recover_identifier') : document.getElementById('assoc_login_identifier'));
			if (!input) {
				input = document.getElementById('assoc_login_identifier');
			}
			if (input) {
				input.focus();
			}
		}

		function closeModal() {
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('culturacsi-login-open');
		}

		document.addEventListener('click', function (event) {
			var trigger = event.target.closest('a[href]');
			if (!trigger || trigger.closest('#culturacsi-login-modal')) {
				return;
			}
			var href = trigger.getAttribute('href') || '';
			if (href.indexOf('/area-riservata/') === -1 && href.indexOf('area_riservata_login=1') === -1) {
				return;
			}
			event.preventDefault();
			var forceTab = 'login';
			if (href.indexOf('auth=recover') !== -1) {
				forceTab = 'recover';
			}
			if (href.indexOf('auth=register') !== -1) {
				forceTab = 'register';
			}
			openModal(null, forceTab);
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', closeModal);
		}
		if (overlay) {
			overlay.addEventListener('click', closeModal);
		}
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeModal();
			}
		});

		try {
			var url = new URL(window.location.href);
			if (url.searchParams.get('area_riservata_login') === '1') {
				var tabFromUrl = url.searchParams.get('auth');
				if (tabFromUrl !== 'recover' && tabFromUrl !== 'register') {
					tabFromUrl = 'login';
				}
				openModal(null, tabFromUrl);
				url.searchParams.delete('area_riservata_login');
				url.searchParams.delete('auth');
				url.searchParams.delete('assoc_notice');
				if (window.history && window.history.replaceState) {
					window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
				}
			}
		} catch (error) {}
	}

	function initLanguageSelectors() {
		document.querySelectorAll('.culturacsi-language-selector.dropdown').forEach(function (container) {
			if (container.dataset.csiLangBound === '1') {
				return;
			}
			container.dataset.csiLangBound = '1';
			var button = container.querySelector('.culturacsi-lang-btn');
			if (!button) {
				return;
			}
			button.addEventListener('click', function (event) {
				event.stopPropagation();
				container.classList.toggle('open');
			});
			document.addEventListener('click', function () {
				container.classList.remove('open');
			});
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initReservedLoginModal();
		initLanguageSelectors();
	});
}());
