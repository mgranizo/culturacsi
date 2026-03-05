(function (window, document) {
  'use strict';

  var app = window.ABFSettoriLive = window.ABFSettoriLive || {};
  if (!app.utils || !app.state || !app.hero || !app.modal) return;

  var toArray = app.utils.toArray;
  var roleIndex = app.utils.roleIndex;
  var findByRole = app.utils.findByRole;
  var ensureHeroState = app.hero.ensureHeroState;
  var scheduleHeroApply = app.hero.scheduleHeroApply;
  var parseCardData = app.modal.parseCardData;
  var openCardModal = app.modal.openCardModal;
  var EVENT_CAROUSEL_SELECTOR = '.wp-block-kadence-postgrid.kt-post-grid-layout-carousel.fullheight-arrows';

  function isSettoriPage() {
    var path = (window.location.pathname || '').toLowerCase();
    return path.indexOf('/settori') !== -1;
  }

  function ensureEventCarouselStyles() {
    if (document.getElementById('csi-events-carousel-empty-style')) return;
    var style = document.createElement('style');
    style.id = 'csi-events-carousel-empty-style';
    style.textContent = [
      '.csi-events-carousel-empty .csi-event-placeholder{width:100%;padding-bottom:150%;background:#e5e7eb;border-radius:6px;}',
      '.csi-events-carousel-empty .csi-event-placeholder-slide{list-style:none;}',
      '.csi-events-carousel-empty .splide__arrows,.csi-events-carousel-empty .splide__pagination{opacity:.45;pointer-events:none;}'
    ].join('');
    document.head.appendChild(style);
  }

  function decorateEventCarousels(scope) {
    if (!isSettoriPage()) return;

    ensureEventCarouselStyles();
    var root = scope && scope.querySelectorAll ? scope : document;
    var carousels = toArray(root.querySelectorAll(EVENT_CAROUSEL_SELECTOR));

    carousels.forEach(function (carousel) {
      var list = carousel.querySelector('.kt-post-grid-wrap');
      if (!list) return;

      var realSlides = toArray(list.querySelectorAll('.kt-post-slider-item:not(.csi-event-placeholder-slide)'));
      var hasRealEventSlides = realSlides.length > 0;

      toArray(list.querySelectorAll('.csi-event-placeholder-slide')).forEach(function (node) {
        node.remove();
      });
      carousel.classList.remove('csi-events-carousel-empty');

      if (!hasRealEventSlides) {
        carousel.classList.add('csi-events-carousel-empty');
        for (var i = 0; i < 4; i++) {
          var li = document.createElement('li');
          li.className = 'kt-post-slider-item csi-event-placeholder-slide';
          li.innerHTML = '<article class="kt-blocks-post-grid-item"><div class="csi-event-placeholder" aria-hidden="true"></div></article>';
          list.appendChild(li);
        }
      }

      // Do not force gray backgrounds for lazy images:
      // many setups bootstrap img[src] as a tiny data URI and swap real
      // sources via data-* attributes asynchronously.
    });
  }

  function syncImmediateState(form) {
    ['macro', 'settore', 'settore2', 'regione', 'provincia', 'comune'].forEach(function (role) {
      var field = findByRole(form, role);
      if (field) field.disabled = false;
    });
  }

  function clearDownstream(form, changedRole) {
    var baseIndex = roleIndex(changedRole);
    if (baseIndex < 0) return;

    toArray(form.querySelectorAll('select[data-abf-role]')).forEach(function (select) {
      var role = select.getAttribute('data-abf-role') || '';
      var idx = roleIndex(role);
      if (idx > baseIndex) {
        select.value = '';
      }
    });
  }

  function setFirstPage(form) {
    var pageField = findByRole(form, 'page');
    if (pageField) pageField.value = '1';
  }

  function buildRequestUrl(form) {
    var url = new URL(window.location.href);
    var fields = toArray(form.querySelectorAll('[name]'));
    var names = [];

    fields.forEach(function (field) {
      var name = field.getAttribute('name');
      if (name) names.push(name);
    });

    names = names.filter(function (name, idx) { return names.indexOf(name) === idx; });
    names.forEach(function (name) { url.searchParams.delete(name); });

    fields.forEach(function (field) {
      var name = field.getAttribute('name');
      if (!name) return;

      var value = (field.value || '').trim();
      var role = field.getAttribute('data-abf-role') || '';

      if (!value) return;
      if (role === 'page' && value === '1') return;
      url.searchParams.set(name, value);
    });

    return url.toString();
  }

  function replaceWrapperFromResponse(wrapper, html, requestUrl) {
    var parser = new DOMParser();
    var doc = parser.parseFromString(html, 'text/html');
    var next = doc.getElementById(wrapper.id);

    if (!next) {
      window.location.assign(requestUrl);
      return;
    }

    wrapper.replaceWith(next);
    initWrapper(next);
    decorateEventCarousels(document);
    window.history.replaceState({}, '', requestUrl);
  }

  function fetchAndReplace(wrapper, requestUrl, controllerRef) {
    if (isSettoriPage() && document.querySelector(EVENT_CAROUSEL_SELECTOR)) {
      window.location.assign(requestUrl);
      return;
    }

    if (controllerRef.current && typeof controllerRef.current.abort === 'function') {
      controllerRef.current.abort();
    }

    // Preserve focus & cursor.
    var activeElement = document.activeElement;
    var focusedId = activeElement ? activeElement.id : null;
    var focusedSelectionStart = activeElement ? activeElement.selectionStart : null;
    var focusedSelectionEnd = activeElement ? activeElement.selectionEnd : null;

    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    controllerRef.current = controller;

    wrapper.classList.add('abf-loading');

    var fetchOptions = {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    };
    if (controller) fetchOptions.signal = controller.signal;

    fetch(requestUrl, fetchOptions)
      .then(function (resp) {
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        return resp.text();
      })
      .then(function (html) {
        replaceWrapperFromResponse(wrapper, html, requestUrl);

        // Restore focus.
        if (focusedId) {
          var el = document.getElementById(focusedId);
          if (el && typeof el.focus === 'function') {
            el.focus();
            if (focusedSelectionStart !== null && focusedSelectionEnd !== null) {
              try { el.setSelectionRange(focusedSelectionStart, focusedSelectionEnd); } catch (e) { }
            }
          }
        }
      })
      .catch(function (err) {
        if (err && err.name === 'AbortError') return;
        window.location.assign(requestUrl);
      })
      .finally(function () {
        if (document.body.contains(wrapper)) {
          wrapper.classList.remove('abf-loading');
        }
      });
  }

  function initWrapper(wrapper) {
    if (!wrapper || wrapper.__abfBound) return;
    wrapper.__abfBound = true;

    var form = wrapper.querySelector('form.abf-form');
    var controllerRef = { current: null };

    if (form) {
      syncImmediateState(form);
      // Always normalize/repair hero image URLs on first render, even with no
      // selected category, so stale localhost URLs do not stall loading.
      ensureHeroState(form);
      scheduleHeroApply(form, false);

      var searchTimer = null;
      form.addEventListener('input', function (event) {
        var target = event.target;
        if (!target || target.nodeName !== 'INPUT') return;

        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
          setFirstPage(form);
          syncImmediateState(form);
          scheduleHeroApply(form, true);
          var requestUrl = buildRequestUrl(form);
          fetchAndReplace(wrapper, requestUrl, controllerRef);
        }, 300);
      });

      form.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || target.tagName !== 'SELECT') return;

        var role = target.getAttribute('data-abf-role') || '';
        if (!role || role === 'page') return;

        clearDownstream(form, role);
        setFirstPage(form);
        syncImmediateState(form);
        scheduleHeroApply(form, true);

        var requestUrl = buildRequestUrl(form);
        fetchAndReplace(wrapper, requestUrl, controllerRef);
      });

      form.addEventListener('submit', function (event) {
        event.preventDefault();
        setFirstPage(form);
        syncImmediateState(form);
        scheduleHeroApply(form, true);
        var requestUrl = buildRequestUrl(form);
        fetchAndReplace(wrapper, requestUrl, controllerRef);
      });
    }

    wrapper.addEventListener('click', function (event) {
      var link = event.target && event.target.closest ? event.target.closest('a') : null;
      if (link && wrapper.contains(link)) {
        if (link.target === '_blank' || link.hasAttribute('download')) return;

        var inPager = !!link.closest('.abf-pager');
        var isReset = link.hasAttribute('data-abf-reset');
        if (inPager || isReset) {
          event.preventDefault();
          fetchAndReplace(wrapper, link.href, controllerRef);
        }
        return;
      }

      var card = event.target && event.target.closest ? event.target.closest('[data-ab-assoc-card="1"]') : null;
      if (!card || !wrapper.contains(card)) return;
      if (event.target.closest('button,input,select,textarea,label')) return;

      var data = parseCardData(card);
      if (data) openCardModal(data);
    });

    wrapper.addEventListener('keydown', function (event) {
      var target = event.target;
      if (!target || !target.matches || !target.matches('[data-ab-assoc-card="1"]')) return;
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();

      var data = parseCardData(target);
      if (data) openCardModal(data);
    });
  }

  function initAll() {
    toArray(document.querySelectorAll('[data-abf-live="1"]')).forEach(initWrapper);
    decorateEventCarousels(document);
  }

  app.filters = {
    initAll: initAll,
    initWrapper: initWrapper,
    fetchAndReplace: fetchAndReplace
  };
})(window, document);
