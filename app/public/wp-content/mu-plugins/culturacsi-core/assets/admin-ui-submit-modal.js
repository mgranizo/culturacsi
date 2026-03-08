/*
 * Submit-status modal for portal forms.
 *
 * This script is frontend-only. It intercepts portal form submissions,
 * displays a processing state, expects the portal handlers to return JSON when
 * `is_portal_ajax=1` is present, and then redirects or shows an error.
 */

(function () {
	'use strict';

	function clearElement(element) {
		while (element.firstChild) {
			element.removeChild(element.firstChild);
		}
	}

	function showSubmitStatus(content, modal, icon, title, message, retryLabel) {
		clearElement(content);

		const wrap = document.createElement('div');
		wrap.className = 'assoc-submit-status';

		if (icon) {
			const iconElement = document.createElement('span');
			iconElement.className = 'assoc-submit-status-icon';
			iconElement.textContent = icon;
			wrap.appendChild(iconElement);
		}

		const titleElement = document.createElement('span');
		titleElement.className = 'assoc-submit-status-text';
		titleElement.textContent = title;
		wrap.appendChild(titleElement);

		const messageElement = document.createElement('span');
		messageElement.className = 'assoc-submit-status-subtext';
		messageElement.textContent = message;
		wrap.appendChild(messageElement);

		if (retryLabel) {
			const buttonWrap = document.createElement('div');
			buttonWrap.style.marginTop = '20px';

			const button = document.createElement('button');
			button.type = 'button';
			button.className = 'button assoc-modal-reset';
			button.textContent = retryLabel;
			button.addEventListener('click', function () {
				modal.classList.remove('is-open');
			});

			buttonWrap.appendChild(button);
			wrap.appendChild(buttonWrap);
		}

		content.appendChild(wrap);
	}

	function showLoadingState(content) {
		clearElement(content);

		const wrap = document.createElement('div');
		wrap.className = 'assoc-submit-status';

		const loader = document.createElement('div');
		loader.className = 'assoc-modal-loader-big';
		wrap.appendChild(loader);

		const title = document.createElement('span');
		title.className = 'assoc-submit-status-text';
		title.textContent = 'Elaborazione in corso...';
		wrap.appendChild(title);

		const message = document.createElement('span');
		message.className = 'assoc-submit-status-subtext';
		message.textContent = 'Stiamo salvando i dati, attendi un istante.';
		wrap.appendChild(message);

		content.appendChild(wrap);
	}

	function resolveRedirectUrl(defaultUrl, responseData) {
		if (!responseData || typeof responseData !== 'object' || !responseData.redirect) {
			return defaultUrl;
		}

		try {
			const redirectUrl = new URL(responseData.redirect, window.location.origin);
			if (redirectUrl.origin === window.location.origin) {
				return redirectUrl.href;
			}
		} catch (error) {
			/* Fall back to the page-provided redirect URL below. */
		}

		return defaultUrl;
	}

	document.addEventListener('DOMContentLoaded', function () {
		document.addEventListener('submit', function (event) {
			const form = event.target.closest('.assoc-portal-form');
			if (!form || form.classList.contains('assoc-auth-form')) {
				return;
			}

			if (event.submitter && event.submitter.classList.contains('bypass-modal')) {
				return;
			}

			const modal = document.getElementById('assoc-submit-modal');
			if (!modal) {
				return;
			}

			event.preventDefault();

			const content = modal.querySelector('.assoc-modal-content');
			const action = form.getAttribute('action') || window.location.href;
			const redirectUrl = form.dataset.redirectUrl || window.location.href;
			showLoadingState(content);
			modal.classList.add('is-open');

			const formData = new window.FormData(form);
			if (event.submitter && event.submitter.name) {
				formData.append(event.submitter.name, event.submitter.value || '1');
			}
			formData.append('is_portal_ajax', '1');

			window.fetch(action, {
				method: 'POST',
				body: formData,
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
				.then(async function (response) {
					const text = await response.text();

					if (!response.ok) {
						throw new Error('Errore del server (' + response.status + '). Riprova tra qualche istante.');
					}

					try {
						return JSON.parse(text);
					} catch (parseError) {
						console.error('Portal JSON parse error:', parseError, text);
						throw new Error('Risposta non valida dal server. Controlla i log e riprova.');
					}
				})
				.then(function (result) {
					if (result && result.success) {
						const successMessage = typeof result.data === 'string' && result.data ? result.data : 'I dati sono stati salvati correttamente.';
						showSubmitStatus(content, modal, '✅', 'Operazione completata!', successMessage, null);

						window.setTimeout(function () {
							window.location.href = resolveRedirectUrl(redirectUrl, result.data);
						}, 1600);
						return;
					}

					const errorMessage = typeof (result && result.data) === 'string' && result.data ? result.data : 'Errore durante il salvataggio.';
					showSubmitStatus(content, modal, '❌', 'Impossibile procedere', errorMessage, 'Riprova');
				})
				.catch(function (error) {
					console.error('Portal submit error:', error);
					const errorMessage = error && error.message ? error.message : 'Verifica la tua connessione e riprova.';
					showSubmitStatus(content, modal, '⚠️', 'Errore di sistema', errorMessage, 'Chiudi');
				});
		});
	});
}());
