(function () {
  'use strict';

  var cfg = window.AB_SETTORI_PATTERN_LINKS;
  if (!cfg || typeof cfg !== 'object') return;

  var baseUrl = typeof cfg.baseUrl === 'string' ? cfg.baseUrl.trim() : '';
  var heroParam = textValue(cfg.heroParam || 'abf_hero');
  var queryKeysRaw = cfg.queryKeys && typeof cfg.queryKeys === 'object' ? cfg.queryKeys : {};
  var lookup = cfg.lookup && typeof cfg.lookup === 'object' ? cfg.lookup : {};
  var heroUrlMap = cfg.heroUrlMap && typeof cfg.heroUrlMap === 'object' ? cfg.heroUrlMap : {};
  var captionMap = cfg.captionMap && typeof cfg.captionMap === 'object' ? cfg.captionMap : {};
  var labelTranslations = cfg.labelTranslations && typeof cfg.labelTranslations === 'object' ? cfg.labelTranslations : {};

  function normalizeKeyList(value) {
    if (Array.isArray(value)) {
      return value
        .map(function (item) { return textValue(item); })
        .filter(function (item) { return !!item; });
    }
    var single = textValue(value);
    return single ? [single] : [];
  }

  var queryKeys = {
    macro: normalizeKeyList(queryKeysRaw.macro),
    settore: normalizeKeyList(queryKeysRaw.settore),
    settore2: normalizeKeyList(queryKeysRaw.settore2)
  };

  if (!baseUrl || !queryKeys.macro.length || !queryKeys.settore.length || !queryKeys.settore2.length) return;

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

  function normalizeRuntimeUrl(value) {
    var input = textValue(value);
    if (!input) return '';
    var origin = textValue(window.location.origin).replace(/\/+$/, '');
    if (!origin) return input;

    var out = input.replace(/https?:\/\/localhost(?::\d+)?/ig, origin);
    var encodedOrigin = encodeURIComponent(origin);
    out = out.replace(/https?%3A%2F%2Flocalhost(?:%3A\d+)?/ig, encodedOrigin);
    return out;
  }

  function extractNestedImageUrl(value) {
    var raw = textValue(value);
    if (!raw) return '';
    var lower = raw.toLowerCase();
    var idx = lower.lastIndexOf('https://');
    if (idx < 0) idx = lower.lastIndexOf('http://');
    if (idx > 0) return raw.slice(idx);
    return raw;
  }

  function normalizeImageUrlForMatch(value) {
    var candidate = extractNestedImageUrl(normalizeRuntimeUrl(value));
    var url = safeUrl(candidate);
    if (!url) return '';
    try {
      var parsed = new URL(url);
      parsed.search = '';
      parsed.hash = '';
      return parsed.toString();
    } catch (e) {}
    return '';
  }

  function basenameTokenFromUrl(value) {
    var normalized = normalizeImageUrlForMatch(value);
    if (!normalized) return '';
    try {
      var parsed = new URL(normalized);
      var parts = (parsed.pathname || '').split('/');
      var base = parts.length ? parts[parts.length - 1] : '';
      base = textValue(base);
      if (!base) return '';
      base = base.replace(/\.[a-z0-9]+$/i, '');
      try {
        base = decodeURIComponent(base);
      } catch (e) {}
      return normalizeKey(base);
    } catch (e) {}
    return '';
  }

  var heroUrlKeyByUrl = {};
  var heroUrlKeyByBase = {};
  Object.keys(heroUrlMap).forEach(function (rawKey) {
    var key = normalizeKey(rawKey);
    if (!key) return;
    var rawUrl = heroUrlMap[rawKey];
    var normalizedUrl = normalizeImageUrlForMatch(rawUrl);
    if (normalizedUrl && !heroUrlKeyByUrl[normalizedUrl]) {
      heroUrlKeyByUrl[normalizedUrl] = key;
    }
    var base = basenameTokenFromUrl(rawUrl);
    if (base && !heroUrlKeyByBase[base]) {
      heroUrlKeyByBase[base] = key;
    }
  });

  function findLookupKey(label) {
    var key = normalizeKey(label);
    if (key && lookup[key]) return key;

    // Handle common filename variants: trailing numbers and pluralization.
    if (key && /\-\d+$/.test(key)) {
      var noNum = key.replace(/\-\d+$/, '');
      if (lookup[noNum]) return noNum;
    }
    if (key && /i$/.test(key)) {
      var singular = key.replace(/i$/, 'a');
      if (lookup[singular]) return singular;
    }

    var wanted = softKey(label);
    if (!wanted) return '';

    var keys = Object.keys(lookup);
    for (var i = 0; i < keys.length; i++) {
      var candidate = keys[i];
      if (softKey(candidate) === wanted) {
        return candidate;
      }
    }

    return '';
  }

  function findLookupEntry(label) {
    var key = findLookupKey(label);
    if (key && lookup[key]) return lookup[key];
    return null;
  }

  function translatedLabelByText(label) {
    var text = textValue(label);
    if (!text) return '';
    if (Object.prototype.hasOwnProperty.call(labelTranslations, text)) {
      return textValue(labelTranslations[text]);
    }
    var normalized = normalizeKey(text);
    if (!normalized) return '';
    var keys = Object.keys(labelTranslations);
    for (var i = 0; i < keys.length; i++) {
      if (normalizeKey(keys[i]) === normalized) {
        return textValue(labelTranslations[keys[i]]);
      }
    }
    return '';
  }

  function resolveLookupKeyFromFigureImage(figure) {
    if (!figure || !(figure instanceof Element)) return '';
    var img = figure.querySelector('img');
    if (!img) return '';

    var attrs = ['data-full-image', 'data-light-image', 'data-splide-lazy', 'data-opt-src', 'src'];
    var urls = [];
    for (var i = 0; i < attrs.length; i++) {
      var raw = textValue(img.getAttribute(attrs[i]));
      if (!raw) continue;
      urls.push(raw);
    }

    for (var j = 0; j < urls.length; j++) {
      var normalizedUrl = normalizeImageUrlForMatch(urls[j]);
      if (!normalizedUrl) continue;
      if (heroUrlKeyByUrl[normalizedUrl]) return heroUrlKeyByUrl[normalizedUrl];
    }

    for (var k = 0; k < urls.length; k++) {
      var baseKey = basenameTokenFromUrl(urls[k]);
      if (!baseKey) continue;
      if (heroUrlKeyByBase[baseKey]) return heroUrlKeyByBase[baseKey];
      var noNumKey = baseKey.replace(/\-\d+$/, '');
      if (noNumKey && heroUrlKeyByBase[noNumKey]) return heroUrlKeyByBase[noNumKey];
      var labelTry = baseKey.replace(/[-_]+/g, ' ');
      var lookupKey = findLookupKey(labelTry);
      if (lookupKey) return lookupKey;
    }

    return '';
  }

  function setQueryKeys(target, keys, value) {
    if (!target || !Array.isArray(keys) || !keys.length) return;
    var cleanValue = textValue(value);
    for (var i = 0; i < keys.length; i++) {
      var key = textValue(keys[i]);
      if (!key) continue;
      if (cleanValue) target.searchParams.set(key, cleanValue);
      else target.searchParams.delete(key);
    }
  }

  function entryDisplayLabel(entry, fallback) {
    if (!entry || typeof entry !== 'object') return textValue(fallback);
    var settore2 = textValue(entry.settore2);
    if (settore2) return settore2;
    var settore = textValue(entry.settore);
    if (settore) return settore;
    var macro = textValue(entry.macro);
    if (macro) return macro;
    return textValue(fallback);
  }

  function buildTargetUrl(label, entry, forcedHeroKey) {
    var cleanLabel = textValue(label);
    if (!cleanLabel && entry && typeof entry === 'object') {
      cleanLabel = entryDisplayLabel(entry, '');
    }
    if (!cleanLabel && forcedHeroKey) {
      cleanLabel = textValue(forcedHeroKey).replace(/-/g, ' ');
    }
    if (!cleanLabel) return '';

    var url = safeUrl(baseUrl);
    if (!url) return '';

    if (!entry || typeof entry !== 'object') {
      entry = findLookupEntry(cleanLabel);
    }
    var target = new URL(url, window.location.origin);
    var hasFilter = false;

    if (entry && typeof entry === 'object') {
      var macro = textValue(entry.macro);
      var settore = textValue(entry.settore);
      var settore2 = textValue(entry.settore2);

      if (macro) {
        setQueryKeys(target, queryKeys.macro, macro);
        hasFilter = true;
      }
      if (settore) {
        setQueryKeys(target, queryKeys.settore, settore);
        hasFilter = true;
      }
      if (settore2) {
        setQueryKeys(target, queryKeys.settore2, settore2);
        hasFilter = true;
      }
    }

    if (!hasFilter) {
      setQueryKeys(target, queryKeys.settore2, cleanLabel);
    }

    var heroKey = textValue(forcedHeroKey);
    if (!heroKey) {
      heroKey = normalizeKey(entryDisplayLabel(entry, cleanLabel));
    }
    if (heroParam && heroKey) {
      target.searchParams.set(heroParam, heroKey);
    }

    return target.toString();
  }

  function getFigureForNode(node) {
    if (!node || !(node instanceof Element)) return null;
    var figure = node.closest('.wp-block-kadence-advancedgallery figure.kb-gallery-figure');
    if (!figure) return null;

    var caption = figure.querySelector('figcaption.kadence-blocks-gallery-item__caption');
    var label = caption ? textValue(caption.textContent || '') : '';
    var lookupKey = label ? findLookupKey(label) : '';
    if (!lookupKey) {
      lookupKey = resolveLookupKeyFromFigureImage(figure);
    }

    var entry = null;
    if (lookupKey && lookup[lookupKey]) {
      entry = lookup[lookupKey];
    } else if (label) {
      entry = findLookupEntry(label);
    }

    if (!label && entry) {
      label = entryDisplayLabel(entry, '');
    }
    if (!label && lookupKey) {
      label = lookupKey.replace(/-/g, ' ');
    }
    if (!label) return null;

    // Apply render-level caption translation from exact label or lookup key.
    var translatedCaption = translatedLabelByText(label);
    if (!translatedCaption && lookupKey && Object.prototype.hasOwnProperty.call(captionMap, lookupKey)) {
      translatedCaption = textValue(captionMap[lookupKey]);
    }
    if (caption && translatedCaption && translatedCaption !== label) {
      caption.textContent = translatedCaption;
      label = translatedCaption;
    } else if (caption && !textValue(caption.textContent || '')) {
      var displayLabel = entryDisplayLabel(entry, label);
      if (displayLabel) {
        caption.textContent = displayLabel;
        label = displayLabel;
      }
    }

    var heroKey = textValue(lookupKey) || normalizeKey(label);
    var targetUrl = buildTargetUrl(label, entry, heroKey);
    if (!targetUrl) return null;

    var anchor = figure.querySelector('a[href]');

    return {
      figure: figure,
      caption: caption,
      anchor: anchor,
      url: targetUrl
    };
  }

  function decorateFigure(figure, caption, anchor, url) {
    if (!figure) return;
    if (figure.dataset.abSettoriLinked !== '1') {
      figure.dataset.abSettoriLinked = '1';
      figure.setAttribute('role', 'link');
      figure.setAttribute('tabindex', '0');
      figure.style.cursor = 'pointer';
    }
    if (caption) caption.style.cursor = 'pointer';
    if (anchor && url) {
      anchor.setAttribute('href', url);
      anchor.setAttribute('target', '_self');
      anchor.style.cursor = 'pointer';
    }
  }

  function initFigures() {
    var figures = document.querySelectorAll('.wp-block-kadence-advancedgallery figure.kb-gallery-figure');
    for (var i = 0; i < figures.length; i++) {
      var data = getFigureForNode(figures[i]);
      if (!data) continue;
      decorateFigure(data.figure, data.caption, data.anchor, data.url);
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
