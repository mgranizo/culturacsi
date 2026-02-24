(function () {
  'use strict';

  var cfg = window.AB_SETTORI_PATTERN_LINKS;
  if (!cfg || typeof cfg !== 'object') return;

  var baseUrl = typeof cfg.baseUrl === 'string' ? cfg.baseUrl.trim() : '';
  var queryKeys = cfg.queryKeys && typeof cfg.queryKeys === 'object' ? cfg.queryKeys : {};
  var lookup = cfg.lookup && typeof cfg.lookup === 'object' ? cfg.lookup : {};

  if (!baseUrl || !queryKeys.macro || !queryKeys.settore || !queryKeys.settore2) return;

  function textValue(value) {
    if (value === null || value === undefined) return '';
    return String(value).trim();
  }

  function normalizeKey(value) {
    var v = textValue(value);
    if (!v) return '';
    if (v.indexOf('&') !== -1 && v.indexOf(';') !== -1) {
      var txt = document.createElement('textarea');
      txt.innerHTML = v;
      v = txt.value || v;
    }
    v = v.toLowerCase();
    if (!v) return '';
    if (v.normalize) {
      v = v.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    v = v.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    return v;
  }

  function softKey(value) {
    return normalizeKey(value).replace(/(.)\1+/g, '$1');
  }

  function safeUrl(value) {
    try {
      var url = new URL(value, window.location.origin);
      if (url.protocol === 'http:' || url.protocol === 'https:') return url.href;
    } catch (e) {}
    return '';
  }

  function findLookupEntry(label) {
    var key = normalizeKey(label);
    if (key && lookup[key]) return lookup[key];

    var wanted = softKey(label);
    if (!wanted) return null;

    var keys = Object.keys(lookup);
    for (var i = 0; i < keys.length; i++) {
      var candidate = keys[i];
      if (softKey(candidate) === wanted) {
        return lookup[candidate];
      }
    }

    return null;
  }

  function buildTargetUrl(label) {
    var cleanLabel = textValue(label);
    if (!cleanLabel) return '';

    var url = safeUrl(baseUrl);
    if (!url) return '';

    var entry = findLookupEntry(cleanLabel);
    var target = new URL(url, window.location.origin);
    var hasFilter = false;

    if (entry && typeof entry === 'object') {
      var macro = textValue(entry.macro);
      var settore = textValue(entry.settore);
      var settore2 = textValue(entry.settore2);

      if (macro) {
        target.searchParams.set(queryKeys.macro, macro);
        hasFilter = true;
      }
      if (settore) {
        target.searchParams.set(queryKeys.settore, settore);
        hasFilter = true;
      }
      if (settore2) {
        target.searchParams.set(queryKeys.settore2, settore2);
        hasFilter = true;
      }
    }

    if (!hasFilter) {
      target.searchParams.set(queryKeys.settore2, cleanLabel);
    }

    return target.toString();
  }

  function getFigureForNode(node) {
    if (!node || !(node instanceof Element)) return null;
    var figure = node.closest('.wp-block-kadence-advancedgallery figure.kb-gallery-figure');
    if (!figure) return null;

    if (figure.querySelector('a[href]')) return null;

    var caption = figure.querySelector('figcaption.kadence-blocks-gallery-item__caption');
    if (!caption) return null;

    var label = textValue(caption.textContent || '');
    if (!label) return null;

    var targetUrl = buildTargetUrl(label);
    if (!targetUrl) return null;

    return {
      figure: figure,
      caption: caption,
      url: targetUrl
    };
  }

  function decorateFigure(figure, caption) {
    if (!figure || figure.dataset.abSettoriLinked === '1') return;
    figure.dataset.abSettoriLinked = '1';
    figure.setAttribute('role', 'link');
    figure.setAttribute('tabindex', '0');
    figure.style.cursor = 'pointer';
    if (caption) caption.style.cursor = 'pointer';
  }

  function initFigures() {
    var figures = document.querySelectorAll('.wp-block-kadence-advancedgallery figure.kb-gallery-figure');
    for (var i = 0; i < figures.length; i++) {
      var data = getFigureForNode(figures[i]);
      if (!data) continue;
      decorateFigure(data.figure, data.caption);
    }
  }

  function onFigureActivate(evt) {
    var data = getFigureForNode(evt.target);
    if (!data) return;

    if (evt.type === 'click') {
      if (evt.target.closest('a,button,input,select,textarea,label')) return;
      evt.preventDefault();
      window.location.href = data.url;
      return;
    }

    if (evt.type === 'keydown') {
      var key = evt.key || '';
      if (key !== 'Enter' && key !== ' ') return;
      if (!evt.target.closest('[data-ab-settori-linked], figure.kb-gallery-figure[role=\"link\"]')) return;
      evt.preventDefault();
      window.location.href = data.url;
    }
  }

  document.addEventListener('click', onFigureActivate, true);
  document.addEventListener('keydown', onFigureActivate, true);

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
