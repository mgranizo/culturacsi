(function () {
	'use strict';

	// Runtime configuration is injected by PHP so environment-specific values
	// stay out of the static bundle.
	var config = window.culturacsiUiTweaks || {};

	// Small bootstrap helper so each feature can be initialized safely without
	// repeating DOM readiness checks.
	function onReady(callback) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', callback);
			return;
		}
		callback();
	}

	// Historical fix:
	// On /chi-siamo, one navigation item ("Progetti") can inherit an incorrect
	// active state from the theme/navigation stack. This removes only the known
	// false-positive classes and leaves native WP menu behavior untouched elsewhere.
	function fixProgettiNav() {
		if (window.location.href.indexOf('/chi-siamo') === -1) {
			return;
		}

		document.querySelectorAll('li.menu-item, li.wp-block-kadence-navigation-link').forEach(function (li) {
			var link = li.querySelector('a');
			if (!link) {
				return;
			}

			var text = link.textContent.trim();
			if (text === 'Progetti' || text.indexOf('Progetti') === 0) {
				li.classList.remove(
					'current-menu-item',
					'current-menu-ancestor',
					'current-menu-parent',
					'current_page_parent',
					'current_page_ancestor',
					'kb-link-current'
				);
				link.removeAttribute('aria-current');
			}
		});
	}

	// The calendar hero sometimes renders the month label twice: once as the custom
	// overlay and once as uppercase text inside the source block. We hide only the
	// duplicate uppercase text near the overlay instead of globally suppressing text.
	function fixCalendarHeroDuplicateText() {
		window.setTimeout(function () {
			var overlay = document.querySelector('.calendar-hero-month-overlay');
			if (!overlay || !overlay.parentElement) {
				return;
			}

			var parent = overlay.parentElement;
			Array.from(parent.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span')).filter(function (el) {
				var text = el.textContent.trim();
				return !el.classList.contains('calendar-hero-month-overlay') &&
					!el.closest('.calendar-hero-month-overlay') &&
					text.length > 0 &&
					text.toUpperCase() === text.toLocaleUpperCase('it');
			}).forEach(function (el) {
				el.style.display = 'none';
			});

			Array.from(parent.childNodes).forEach(function (node) {
				if (
					node.nodeType === Node.TEXT_NODE &&
					node.textContent.trim().length > 0 &&
					node.textContent.trim() === node.textContent.trim().toUpperCase()
				) {
					node.textContent = '';
				}
			});
		}, 500);
	}

	function getCalendarHeroCandidateImage(candidate) {
		if (!candidate) {
			return null;
		}

		if (candidate.tagName === 'IMG') {
			return candidate;
		}

		return candidate.querySelector('img') || candidate.querySelector('.kb-img');
	}

	function findCalendarHeroCandidate() {
		var entryContent = document.querySelector('.entry-content');
		if (!entryContent) {
			return null;
		}

		var images = Array.from(entryContent.querySelectorAll('.wp-block-image, .wp-block-kadence-image, .kb-image-wrap'));
		var heroCandidate = null;
		var maxArea = 0;

		images.forEach(function (candidate) {
			if (candidate.closest('.assoc-portal-calendar-browser, header, .my-sticky-header')) {
				return;
			}

			var img = getCalendarHeroCandidateImage(candidate);
			if (!img) {
				return;
			}

			var width = img.naturalWidth || img.clientWidth || 0;
			if (width > 0 && width < 300) {
				return;
			}

			var area = width * (img.naturalHeight || img.clientHeight || 1);
			if (area > maxArea) {
				maxArea = area;
				heroCandidate = candidate;
			}
		});

		if (!heroCandidate) {
			heroCandidate = entryContent.querySelector('.kb-row-layout-wrap:not(.my-sticky-header)');
		}

		return heroCandidate;
	}

	function queueCalendarHeroOverlayBootstrap() {
		if (!isCalendarPage() || !document.body) {
			return;
		}

		if (document.querySelector('.calendar-hero-month-overlay')) {
			return;
		}

		if (document.body.dataset.csiCalendarHeroBootstrapQueued === '1') {
			return;
		}

		document.body.dataset.csiCalendarHeroBootstrapQueued = '1';

		function runBootstrap() {
			document.body.removeAttribute('data-csi-calendar-hero-bootstrap-queued');

			if (document.querySelector('.calendar-hero-month-overlay')) {
				return;
			}

			var overlay = ensureCalendarHeroOverlayExists(true);
			if (!overlay) {
				return;
			}

			scheduleCalendarHeroMonthFit();
			fixCalendarHeroDuplicateText();
		}

		if (document.readyState === 'complete') {
			window.setTimeout(runBootstrap, 0);
			return;
		}

		window.addEventListener('load', runBootstrap, { once: true });
	}

	function ensureCalendarHeroOverlayExists(allowCreate) {
		if (!isCalendarPage()) {
			return null;
		}

		var overlay = document.querySelector('.calendar-hero-month-overlay');
		if (overlay && overlay.parentElement) {
			return overlay;
		}

		if (!allowCreate) {
			queueCalendarHeroOverlayBootstrap();
			return null;
		}

		var heroCandidate = findCalendarHeroCandidate();
		if (!heroCandidate) {
			return null;
		}

		overlay = heroCandidate.querySelector('.calendar-hero-month-overlay');
		if (overlay && overlay.parentElement) {
			return overlay;
		}

		heroCandidate.style.position = 'relative';
		heroCandidate.style.overflow = 'hidden';
		heroCandidate.style.display = 'block';

		var heroImg = getCalendarHeroCandidateImage(heroCandidate);
		if (heroImg) {
			heroImg.style.width = '100%';
			if (heroImg.naturalWidth && heroImg.naturalHeight) {
				var naturalAspect = heroImg.naturalWidth / heroImg.naturalHeight;
				heroImg.style.aspectRatio = (naturalAspect * 1.5).toString();
				heroImg.style.objectFit = 'cover';
			}
		}

		var tintOverlay = heroCandidate.querySelector('.calendar-hero-tint-overlay');
		if (!tintOverlay) {
			tintOverlay = document.createElement('div');
			tintOverlay.className = 'calendar-hero-tint-overlay';
			tintOverlay.style.cssText = 'position: absolute !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background-color: rgba(17, 89, 175, 0.6) !important; z-index: 5 !important; pointer-events: none !important;';
			heroCandidate.appendChild(tintOverlay);
		}

		overlay = document.createElement('div');
		overlay.className = 'calendar-hero-month-overlay';
		overlay.style.cssText = 'position: absolute !important; left: 50% !important; bottom: -2.2% !important; width: 100% !important; text-align: center !important; font-family: \'Raleway\', \'Roboto\', sans-serif !important; font-weight: 800 !important; color: #ffffff !important; text-transform: uppercase !important; z-index: 10 !important; pointer-events: none !important; margin: 0 !important; padding: 0 !important; text-shadow: none !important; white-space: nowrap !important; transform: translateX(-50%) !important; display: flex; justify-content: center;';

		var textSpan = document.createElement('span');
		textSpan.style.display = 'inline-block';
		textSpan.style.transformOrigin = 'bottom center';
		textSpan.style.lineHeight = '0.73';
		textSpan.style.marginLeft = '-0.15em';
		textSpan.style.marginRight = '-0.1em';
		textSpan.textContent = getMonthLabelFromUrl(new URL(window.location.href));
		overlay.appendChild(textSpan);

		heroCandidate.appendChild(overlay);
		return overlay;
	}

	// Keep the calendar month title fitted by width only.
	// We leave the original hero/image layout intact and reproduce the same
	// transform-based sizing model that the calendar snippet uses on a full page
	// load. This avoids a mismatch between reload behavior and in-page month
	// changes.
	function ensureManagedCalendarHeroOverlay() {
		var overlay = ensureCalendarHeroOverlayExists(false);
		if (!overlay || !overlay.parentElement) {
			return null;
		}

		if (overlay.dataset.csiManaged === '1') {
			return overlay;
		}

		overlay.dataset.csiManaged = '1';
		return overlay;
	}

	function ensureCalendarHeroTextSpan(overlay) {
		if (!overlay) {
			return null;
		}

		var textSpan = overlay.querySelector('span');
		if (textSpan) {
			return textSpan;
		}

		textSpan = document.createElement('span');
		textSpan.textContent = overlay.textContent ? overlay.textContent.trim() : '';
		overlay.textContent = '';
		overlay.appendChild(textSpan);

		return textSpan;
	}

	function fitCalendarHeroMonthWidth() {
		window.setTimeout(function () {
			var overlay = ensureManagedCalendarHeroOverlay();
			if (!overlay || !overlay.parentElement) {
				return;
			}

			var textSpan = ensureCalendarHeroTextSpan(overlay);
			if (!textSpan) {
				return;
			}

			var hero = overlay.parentElement;
			// Optical compensation:
			// a mathematically exact 3px bleed still looks inset because this font
			// keeps sidebearings around the outer glyphs. We slightly overfit the
			// width so the rendered letterforms visually reach the frame edges.
			var targetWidth = hero.clientWidth + 24;

			overlay.style.bottom = '-3px';

			textSpan.style.transformOrigin = 'bottom center';
			textSpan.style.marginBottom = '-3px';
			textSpan.style.display = 'inline-block';
			textSpan.style.lineHeight = '0.73';
			textSpan.style.marginLeft = '0';
			textSpan.style.marginRight = '0';
			textSpan.style.position = 'relative';
			textSpan.style.left = '-5px';
			textSpan.style.fontSize = '100px';
			textSpan.style.transform = 'scale(1)';

			var measurementSpan = textSpan.cloneNode(true);
			var overlayStyles = window.getComputedStyle(overlay);
			measurementSpan.style.position = 'absolute';
			measurementSpan.style.left = '-99999px';
			measurementSpan.style.top = '0';
			measurementSpan.style.visibility = 'hidden';
			measurementSpan.style.pointerEvents = 'none';
			measurementSpan.style.transform = 'scale(1)';
			measurementSpan.style.fontFamily = overlayStyles.fontFamily;
			measurementSpan.style.fontWeight = overlayStyles.fontWeight;
			measurementSpan.style.fontStyle = overlayStyles.fontStyle;
			measurementSpan.style.letterSpacing = overlayStyles.letterSpacing;
			measurementSpan.style.textTransform = overlayStyles.textTransform;
			document.body.appendChild(measurementSpan);

			var naturalWidth = measurementSpan.getBoundingClientRect().width;
			measurementSpan.remove();

			if (!targetWidth || !naturalWidth) {
				return;
			}

			var finalScale = targetWidth / naturalWidth;
			textSpan.style.transform = 'scale(' + finalScale + ')';
		}, 40);
	}

	function scheduleCalendarHeroMonthFit() {
		fitCalendarHeroMonthWidth();
		window.requestAnimationFrame(fitCalendarHeroMonthWidth);
		window.setTimeout(fitCalendarHeroMonthWidth, 180);
		window.setTimeout(fitCalendarHeroMonthWidth, 720);
	}

	function dispatchCalendarHeroResize() {
		var resizeEvent = new Event('resize');
		window.dispatchEvent(resizeEvent);
		window.requestAnimationFrame(function () {
			window.dispatchEvent(new Event('resize'));
		});
		window.setTimeout(function () {
			window.dispatchEvent(new Event('resize'));
		}, 180);
		window.setTimeout(function () {
			window.dispatchEvent(new Event('resize'));
		}, 720);
	}

	function initCalendarHeroMonthObserver() {
		if (!isCalendarPage()) {
			return;
		}

		var queued = false;
		function queueFit() {
			if (queued) {
				return;
			}
			queued = true;
			window.requestAnimationFrame(function () {
				queued = false;
				scheduleCalendarHeroMonthFit();
				fixCalendarHeroDuplicateText();
			});
		}

		var observer = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var mutation = mutations[i];
				if (mutation.type === 'characterData') {
					var textParent = mutation.target && mutation.target.parentElement;
					if (textParent && textParent.closest('.calendar-hero-month-overlay')) {
						queueFit();
						return;
					}
				}

				if (mutation.type === 'childList') {
					var mutationScope = mutation.target && mutation.target.nodeType === 1 ? mutation.target : mutation.target.parentElement;
					if (mutationScope && mutationScope.closest('.calendar-hero-month-overlay')) {
						queueFit();
						return;
					}
				}

				for (var j = 0; j < mutation.addedNodes.length; j++) {
					var node = mutation.addedNodes[j];
					if (node.nodeType !== 1) {
						continue;
					}
					if (
						(node.matches && node.matches('.calendar-hero-month-overlay, .calendar-hero-month-overlay *')) ||
						(node.querySelector && node.querySelector('.calendar-hero-month-overlay'))
					) {
						queueFit();
						return;
					}
				}
			}
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true,
			characterData: true
		});

		if (document.fonts && document.fonts.ready) {
			document.fonts.ready.then(queueFit);
		}
	}

	function initCalendarHeroMonthControlSync() {
		if (!isCalendarPage()) {
			return;
		}

		document.addEventListener('change', function (event) {
			var control = event.target;
			if (!control || !control.matches('select[name="ev_m"], select[name="ev_y"], select[name="m"], select[name="y"]')) {
				return;
			}

			scheduleCalendarHeroMonthFit();
			dispatchCalendarHeroResize();
		});
	}

	function isCalendarPage() {
		var path = window.location.pathname.replace(/\/+$/, '');
		return path === '/calendar' || path === '/calendario';
	}

	function getMonthLabelFromUrl(url) {
		var currentUrl = url instanceof URL ? url : new URL(url || window.location.href, window.location.href);
		var today = new Date();

		var monthValue = currentUrl.searchParams.get('ev_m');
		if (monthValue === null || monthValue === '') {
			monthValue = currentUrl.searchParams.get('m');
		}
		if (monthValue === null || monthValue === '') {
			monthValue = String(today.getMonth() + 1);
		}

		var yearValue = currentUrl.searchParams.get('ev_y');
		if (yearValue === null || yearValue === '') {
			yearValue = currentUrl.searchParams.get('y');
		}
		if (yearValue === null || yearValue === '') {
			yearValue = String(today.getFullYear());
		}

		var month = parseInt(monthValue, 10);
		if (!(month > 0)) {
			month = today.getMonth() + 1;
		}

		var year = parseInt(yearValue, 10);
		if (!(year > 0)) {
			year = today.getFullYear();
		}

		try {
			return new Intl.DateTimeFormat('it-IT', { month: 'long' }).format(new Date(year, month - 1, 1)).toLocaleUpperCase('it-IT');
		} catch (error) {
			var fallbackMonths = ['GENNAIO', 'FEBBRAIO', 'MARZO', 'APRILE', 'MAGGIO', 'GIUGNO', 'LUGLIO', 'AGOSTO', 'SETTEMBRE', 'OTTOBRE', 'NOVEMBRE', 'DICEMBRE'];
			return fallbackMonths[new Date(year, month - 1, 1).getMonth()] || '';
		}
	}

	// The calendar page currently changes month via normal GET navigation.
	// That rebuilds the entire document even though the hero image stays the same.
	// We intercept same-origin month links, fetch the next page in the background,
	// replace the page content block, and preserve the existing hero node so the
	// browser does not need to re-render that heavy image region unnecessarily.
	function initCalendarMonthNavigationOptimization() {
		if (!isCalendarPage()) {
			return;
		}

		var inFlightRequest = null;
		var pendingUrl = '';

		function findCalendarSwapTarget(scope) {
			var browser = scope.querySelector('.assoc-portal-calendar-browser');
			if (browser) {
				return browser;
			}

			var calendar = scope.querySelector('.assoc-portal-calendar');
			if (calendar) {
				return calendar;
			}

			var pagination = scope.querySelector('.calendar-results-pagination');
			if (pagination) {
				return pagination.closest('.entry-content, .wp-block-group, main, .site-main');
			}

			return null;
		}

		function getCalendarRequestUrl(urlLike) {
			var url = new URL(urlLike, window.location.href);
			var current = new URL(window.location.href);
			var changesMonth = url.searchParams.has('ev_m') || url.searchParams.has('ev_y') || url.searchParams.has('m') || url.searchParams.has('y');
			if (!changesMonth) {
				return null;
			}
			if (url.origin !== current.origin || url.pathname !== current.pathname) {
				return null;
			}
			return url;
		}

		function updateHeroMonthLabel(url) {
			var overlay = ensureManagedCalendarHeroOverlay();
			if (!overlay) {
				return;
			}

			var overlayText = ensureCalendarHeroTextSpan(overlay);
			if (!overlayText) {
				return;
			}

			overlayText.textContent = getMonthLabelFromUrl(url);
			scheduleCalendarHeroMonthFit();
			dispatchCalendarHeroResize();
			fixCalendarHeroDuplicateText();
		}

		function swapCalendarContent(nextDocument, requestUrl) {
			var currentTarget = findCalendarSwapTarget(document);
			var nextTarget = findCalendarSwapTarget(nextDocument);
			if (!currentTarget || !nextTarget || currentTarget.tagName !== nextTarget.tagName) {
				window.location.href = requestUrl.toString();
				return;
			}

			currentTarget.replaceWith(nextTarget);
			document.title = nextDocument.title || document.title;
			updateHeroMonthLabel(requestUrl);
			fixCalendarHeroDuplicateText();
		}

		function fetchAndSwap(url, pushState) {
			if (inFlightRequest === url.toString()) {
				return;
			}

			inFlightRequest = url.toString();
			pendingUrl = url.toString();
			document.body.setAttribute('aria-busy', 'true');

			window.fetch(url.toString(), {
				credentials: 'same-origin',
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('Calendar request failed');
					}
					return response.text();
				})
				.then(function (html) {
					var parser = new DOMParser();
					var nextDocument = parser.parseFromString(html, 'text/html');
					if (pendingUrl !== url.toString()) {
						return;
					}
					swapCalendarContent(nextDocument, url);
					if (pushState) {
						window.history.pushState({ csiCalendarUrl: url.toString() }, '', url.toString());
					}
				})
				.catch(function () {
					window.location.href = url.toString();
				})
				.finally(function () {
					if (pendingUrl === url.toString()) {
						inFlightRequest = null;
						document.body.removeAttribute('aria-busy');
					}
				});
		}

		document.addEventListener('click', function (event) {
			var link = event.target.closest('a[href]');
			if (!link || link.target === '_blank' || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
				return;
			}

			var url = getCalendarRequestUrl(link.href);
			if (!url) {
				return;
			}

			event.preventDefault();
			fetchAndSwap(url, true);
		});

		window.addEventListener('popstate', function () {
			if (!isCalendarPage()) {
				return;
			}
			fetchAndSwap(new URL(window.location.href), false);
		});
	}

	// Normalizes label text for resilient text matching across block/editor markup
	// variations and inconsistent whitespace.
	function normalizeText(value) {
		return (value || '').replace(/\s+/g, ' ').trim().toUpperCase();
	}

	// Enhances the Contatti page map block into a collapsible section.
	// The markup is not strongly structured, so the code finds the trigger
	// heuristically from the nearby map-marker icon or the "ACSI NAZIONALE" label.
	function initContattiMapToggle() {
		var mapBlocks = Array.prototype.slice.call(document.querySelectorAll('#mapa, .mapa'));
		if (!mapBlocks.length) {
			return;
		}

		mapBlocks.forEach(function (mapBlock, idx) {
			if (!mapBlock || mapBlock.dataset.csiMapInit === '1') {
				return;
			}
			mapBlock.dataset.csiMapInit = '1';

			if (!mapBlock.id) {
				mapBlock.id = 'csi-contatti-map-' + (idx + 1);
			}

			mapBlock.classList.add('csi-map-collapsible');
			mapBlock.classList.remove('is-open');

			var scope = mapBlock.closest('.kt-inside-inner-col') || mapBlock.parentElement || document;
			var iconGlyph = scope.querySelector('.kb-svg-icon-fas_map-marker-alt');
			var iconTrigger = iconGlyph ? (
				iconGlyph.closest('.wp-block-kadence-icon') ||
				iconGlyph.closest('.wp-block-kadence-single-icon') ||
				iconGlyph.closest('.kb-svg-icon-wrap') ||
				iconGlyph
			) : null;

			var labelTrigger = null;
			var strongNodes = scope.querySelectorAll('p strong');
			for (var i = 0; i < strongNodes.length; i++) {
				if (normalizeText(strongNodes[i].textContent).indexOf('ACSI NAZIONALE') !== -1) {
					labelTrigger = strongNodes[i];
					break;
				}
			}

			if (!labelTrigger) {
				var paragraphs = scope.querySelectorAll('p');
				for (var j = 0; j < paragraphs.length; j++) {
					if (normalizeText(paragraphs[j].textContent).indexOf('ACSI NAZIONALE') !== -1) {
						labelTrigger = paragraphs[j];
						break;
					}
				}
			}

			var triggers = [];
			if (iconTrigger) {
				triggers.push(iconTrigger);
			}
			if (labelTrigger && triggers.indexOf(labelTrigger) === -1) {
				triggers.push(labelTrigger);
			}
			if (!triggers.length) {
				return;
			}

			var arrowHost = labelTrigger || iconTrigger;
			if (arrowHost && !arrowHost.querySelector('.csi-map-toggle-arrow')) {
				var arrow = document.createElement('span');
				arrow.className = 'csi-map-toggle-arrow';
				arrow.setAttribute('aria-hidden', 'true');
				arrow.textContent = '▾';
				arrowHost.appendChild(arrow);
			}

			// Keep the DOM self-describing so an admin inspecting the page can
			// understand state directly from classes/ARIA attributes.
			function setOpen(open) {
				mapBlock.classList.toggle('is-open', open);
				triggers.forEach(function (trigger) {
					trigger.classList.toggle('is-open', open);
					trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
				});
			}

			function toggleMap(event) {
				if (event) {
					event.preventDefault();
				}
				setOpen(!mapBlock.classList.contains('is-open'));
			}

			triggers.forEach(function (trigger) {
				trigger.classList.add('csi-map-toggle-target');
				trigger.setAttribute('role', 'button');
				trigger.setAttribute('tabindex', '0');
				trigger.setAttribute('aria-controls', mapBlock.id);
				trigger.setAttribute('aria-expanded', 'false');

				if (trigger.dataset.csiMapBound === '1') {
					return;
				}

				trigger.dataset.csiMapBound = '1';
				trigger.addEventListener('click', toggleMap);
				trigger.addEventListener('keydown', function (event) {
					if (event.key === 'Enter' || event.key === ' ' || event.code === 'Space') {
						toggleMap(event);
					}
				});
			});

			setOpen(false);
		});
	}

	// Converts a configured video URL into the appropriate embed/player markup.
	// This keeps modal state management separate from media-provider branching.
	function buildVideoEmbed(videoUrl) {
		if (videoUrl.indexOf('youtube.com') !== -1 || videoUrl.indexOf('youtu.be') !== -1 || videoUrl.indexOf('vimeo.com') !== -1) {
			var embedUrl = videoUrl;
			if (videoUrl.indexOf('youtube.com/watch?v=') !== -1) {
				embedUrl = videoUrl.replace('watch?v=', 'embed/') + '?autoplay=1';
			} else if (videoUrl.indexOf('youtu.be/') !== -1) {
				embedUrl = videoUrl.replace('youtu.be/', 'youtube.com/embed/') + '?autoplay=1';
			}
			return '<iframe src="' + embedUrl + '" allow="autoplay; fullscreen" allowfullscreen></iframe>';
		}

		return '<video src="' + videoUrl + '" controls autoplay></video>';
	}

	// Initializes the 5xmille promo modal.
	// The modal shell is server-rendered by PHP; JS only injects media and controls
	// visibility, which keeps failure modes simpler and debugging easier.
	function initVideoModal() {
		var videoUrl = config.videoUrl || '';
		var modal = document.getElementById('csi-5xmille-modal');
		var wrapper = document.getElementById('csi-5xmille-video-wrapper');
		var closeBtn = document.getElementById('csi-5xmille-close');

		if (!modal || !wrapper || !closeBtn) {
			return;
		}

		function openModal(event) {
			if (event) {
				event.preventDefault();
			}
			if (!videoUrl) {
				return;
			}

			wrapper.innerHTML = buildVideoEmbed(videoUrl);
			modal.classList.add('is-visible');
			modal.setAttribute('aria-hidden', 'false');
			document.body.classList.add('csi-modal-open');
		}

		function closeModal() {
			modal.classList.remove('is-visible');
			modal.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('csi-modal-open');
			// Clear media after closing so embedded players stop audio reliably.
			window.setTimeout(function () {
				wrapper.innerHTML = '';
			}, 300);
		}

		document.addEventListener('click', function (event) {
			var trigger = event.target.closest('.\\35 xmille') || event.target.closest('[class*="5xmille"]');
			if (trigger) {
				openModal(event);
			}
		});

		closeBtn.addEventListener('click', closeModal);
		modal.addEventListener('click', function (event) {
			if (event.target === modal) {
				closeModal();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && modal.classList.contains('is-visible')) {
				closeModal();
			}
		});
	}

	// Mobile header hardening:
	// some pages use reusable/header-block layouts that need one additional
	// hamburger trigger on small screens. We only enhance if the expected
	// structure exists, and we tag the header to avoid double initialization.
	function initMobileHeaderFix() {
		if (window.innerWidth > 900) {
			return;
		}

		var stickyHeader = document.querySelector('.my-sticky-header');
		if (!stickyHeader || stickyHeader.dataset.csiMobileFix === '1') {
			return;
		}
		stickyHeader.dataset.csiMobileFix = '1';

		var row = stickyHeader.querySelector(':scope > .wp-block-group__inner-container > .wp-block-columns') || stickyHeader.querySelector('.wp-block-columns');
		if (!row) {
			return;
		}

		var navPanel = stickyHeader.querySelector('.wp-block-kadence-navigation');
		if (!navPanel) {
			var globalNav = document.querySelector('.wp-block-kadence-navigation');
			if (globalNav) {
				var sourceCol = globalNav.closest('.wp-block-kadence-column, .wp-block-column') || globalNav;
				navPanel = sourceCol.cloneNode(true);
				row.appendChild(navPanel);
			} else {
				return;
			}
		} else {
			navPanel = navPanel.closest('.wp-block-kadence-column, .wp-block-column') || navPanel;
		}

		navPanel.classList.add('csi-mobile-nav-panel');

		var toggleBtn = document.createElement('button');
		toggleBtn.type = 'button';
		toggleBtn.className = 'csi-mobile-hamburger';
		toggleBtn.setAttribute('aria-expanded', 'false');
		toggleBtn.setAttribute('aria-label', 'Apri menu');
		// SVG hamburger icon instead of text
		toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';

		row.appendChild(toggleBtn);

		toggleBtn.addEventListener('click', function () {
			var open = stickyHeader.classList.toggle('csi-mobile-nav-open');
			toggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
			if (open) {
				toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
			} else {
				toggleBtn.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>';
			}
		});
	}

	// Feature boot order is intentionally simple:
	// each initializer is independent and should fail in isolation.
	onReady(function () {
		scheduleCalendarHeroMonthFit();
		initCalendarHeroMonthObserver();
		initCalendarHeroMonthControlSync();
		fixCalendarHeroDuplicateText();
		initCalendarMonthNavigationOptimization();
		initContattiMapToggle();
		initVideoModal();
		initMobileHeaderFix();
	});

	window.addEventListener('resize', scheduleCalendarHeroMonthFit);

	window.addEventListener('load', fixProgettiNav);
	if (document.readyState === 'complete') {
		fixProgettiNav();
	}
})();
