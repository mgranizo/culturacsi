(function () {
    'use strict';

    var cfg = window.ASSOC_PORTAL_EVENTI_PATTERN_LINKS;
    if (!cfg || typeof cfg !== 'object') return;

    var calendarUrl = typeof cfg.calendarUrl === 'string' ? cfg.calendarUrl.trim() : '';
    if (!calendarUrl) return;

    var attachmentMap = cfg.attachmentMap && typeof cfg.attachmentMap === 'object' ? cfg.attachmentMap : {};
    var fileMap = cfg.fileMap && typeof cfg.fileMap === 'object' ? cfg.fileMap : {};
    var defaultView = typeof cfg.defaultView === 'string' && cfg.defaultView.trim() ? cfg.defaultView.trim() : 'calendar';
    if (defaultView !== 'calendar' && defaultView !== 'rows' && defaultView !== 'cards') {
        defaultView = 'calendar';
    }
    var queryKeys = cfg.queryKeys && typeof cfg.queryKeys === 'object' ? cfg.queryKeys : {};

    var keyEventId = queryKeys.eventId || 'ev_event_id';
    var keyYear = queryKeys.year || 'ev_y';
    var keyMonth = queryKeys.month || 'ev_m';
    var keyView = queryKeys.view || 'ev_vista';

    function toText(value) {
        if (value === null || value === undefined) return '';
        return String(value).trim();
    }

    function normalizeFilename(value) {
        var input = toText(value);
        if (!input) return '';

        try {
            var parsed = new URL(input, window.location.origin);
            input = parsed.pathname || input;
        } catch (e) {}

        input = input.split('?')[0].split('#')[0];
        if (!input) return '';

        var segments = input.split('/');
        var basename = toText(segments[segments.length - 1]).toLowerCase();
        if (!basename) return '';

        try {
            basename = decodeURIComponent(basename);
        } catch (e) {}

        basename = basename.replace(/-\d+x\d+(?=\.[a-z0-9]+$)/i, '');
        basename = basename.replace(/\.[a-z0-9]+$/i, '');

        return basename;
    }

    function parseIntSafe(value) {
        var numeric = String(value === undefined || value === null ? '' : value).replace(/[^\d]/g, '');
        if (!numeric) return 0;
        var parsed = parseInt(numeric, 10);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function uniqueStrings(items) {
        var out = [];
        var seen = {};
        for (var i = 0; i < items.length; i++) {
            var item = toText(items[i]);
            if (!item || seen[item]) continue;
            seen[item] = true;
            out.push(item);
        }
        return out;
    }

    function configuredSelectorList() {
        var selectors = [];
        if (Array.isArray(cfg.gallerySelectors)) {
            selectors = cfg.gallerySelectors.slice(0);
        } else if (typeof cfg.gallerySelector === 'string') {
            selectors = [cfg.gallerySelector];
        }
        return uniqueStrings(selectors);
    }

    function discoverEventGalleries() {
        var selectors = configuredSelectorList();
        var roots = [];

        for (var i = 0; i < selectors.length; i++) {
            var found = document.querySelectorAll(selectors[i]);
            for (var j = 0; j < found.length; j++) {
                roots.push(found[j]);
            }
        }

        if (roots.length > 0) return roots;

        var headings = document.querySelectorAll('p, h1, h2, h3, h4, h5, h6, strong');
        for (var k = 0; k < headings.length; k++) {
            var text = toText(headings[k].textContent).toLowerCase();
            if (text !== 'eventi') continue;

            var sibling = headings[k].nextElementSibling;
            while (sibling) {
                if (sibling.matches && sibling.matches('.wp-block-kadence-advancedgallery')) {
                    roots.push(sibling);
                    break;
                }
                sibling = sibling.nextElementSibling;
            }
        }

        return roots;
    }

    function figureInEventGallery(figure) {
        if (!figure || !(figure instanceof Element)) return false;
        var roots = discoverEventGalleries();
        for (var i = 0; i < roots.length; i++) {
            if (roots[i] && roots[i].contains(figure)) return true;
        }
        return false;
    }

    function getFigureAttachmentId(figure) {
        if (!figure) return '';
        var image = figure.querySelector('img[data-id]');
        if (!image) return '';
        var id = parseIntSafe(image.getAttribute('data-id'));
        return id > 0 ? String(id) : '';
    }

    function collectFigureUrls(figure) {
        if (!figure) return [];
        var urls = [];

        var anchor = figure.querySelector('a.kb-gallery-item-link[href]');
        if (anchor) urls.push(anchor.getAttribute('href'));

        var image = figure.querySelector('img');
        if (image) {
            urls.push(image.getAttribute('src'));
            urls.push(image.getAttribute('currentSrc'));
            urls.push(image.getAttribute('data-splide-lazy'));
            urls.push(image.getAttribute('data-full-image'));
            urls.push(image.getAttribute('data-light-image'));
        }

        return uniqueStrings(urls);
    }

    function resolveEventEntry(figure) {
        var attachmentId = getFigureAttachmentId(figure);
        if (attachmentId && attachmentMap[attachmentId]) {
            return attachmentMap[attachmentId];
        }

        var urls = collectFigureUrls(figure);
        for (var i = 0; i < urls.length; i++) {
            var filenameKey = normalizeFilename(urls[i]);
            if (filenameKey && fileMap[filenameKey]) {
                return fileMap[filenameKey];
            }
        }

        return null;
    }

    function buildTargetUrl(entry) {
        if (!entry || typeof entry !== 'object') return '';

        var target = new URL(calendarUrl, window.location.origin);
        target.searchParams.set(keyView, defaultView);

        var eventId = parseIntSafe(entry.eventId);
        if (eventId < 1) return '';
        var year = parseIntSafe(entry.y);
        var month = parseIntSafe(entry.m);

        target.searchParams.set(keyEventId, String(eventId));
        if (year > 0) target.searchParams.set(keyYear, String(year));
        if (month > 0) target.searchParams.set(keyMonth, String(month));

        return target.toString();
    }

    function decorateFigure(figure) {
        if (!figure || figure.dataset.assocEventiLinked === '1') return;
        var entry = resolveEventEntry(figure);
        var url = buildTargetUrl(entry);
        if (!url) return;

        figure.dataset.assocEventiLinked = '1';
        figure.setAttribute('role', 'link');
        figure.setAttribute('tabindex', '0');
        figure.style.cursor = 'pointer';

        var anchor = figure.querySelector('a.kb-gallery-item-link');
        if (!anchor) return;

        anchor.setAttribute('href', url);
        anchor.setAttribute('target', '_self');
        anchor.removeAttribute('aria-haspopup');
        anchor.style.cursor = 'pointer';
    }

    function initFigures() {
        var roots = discoverEventGalleries();
        for (var i = 0; i < roots.length; i++) {
            var figures = roots[i].querySelectorAll('figure.kb-gallery-figure');
            for (var j = 0; j < figures.length; j++) {
                decorateFigure(figures[j]);
            }
        }
    }

    function navigateToFigureEvent(figure, event) {
        if (!figureInEventGallery(figure)) return;
        var entry = resolveEventEntry(figure);
        var url = buildTargetUrl(entry);
        if (!url) return;

        event.preventDefault();
        event.stopPropagation();
        if (typeof event.stopImmediatePropagation === 'function') {
            event.stopImmediatePropagation();
        }
        window.location.href = url;
    }

    function onActivate(event) {
        var target = event.target;
        if (!target || !(target instanceof Element)) return;

        var figure = target.closest('figure.kb-gallery-figure');
        if (!figure) return;

        if (event.type === 'click') {
            if (target.closest('.splide__arrow, .splide__pagination')) return;
            navigateToFigureEvent(figure, event);
            return;
        }

        if (event.type === 'keydown') {
            var key = event.key || '';
            if (key !== 'Enter' && key !== ' ') return;
            navigateToFigureEvent(figure, event);
        }
    }

    document.addEventListener('click', onActivate, true);
    document.addEventListener('keydown', onActivate, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFigures);
    } else {
        initFigures();
    }

    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function () {
            initFigures();
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();
