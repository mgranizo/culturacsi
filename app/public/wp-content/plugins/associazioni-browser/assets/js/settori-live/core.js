(function (window) {
  'use strict';

  var app = window.ABFSettoriLive = window.ABFSettoriLive || {};
  var state = app.state || {
    ROLE_ORDER: ['macro', 'settore', 'settore2', 'regione', 'provincia', 'comune'],
    modalState: null,
    heroState: null
  };
  app.state = state;

  function toArray(nodeList) {
    return Array.prototype.slice.call(nodeList || []);
  }

  function roleIndex(role) {
    return state.ROLE_ORDER.indexOf(role);
  }

  function findByRole(form, role) {
    if (!form || !form.querySelector) return null;
    return form.querySelector('[data-abf-role="' + role + '"]');
  }

  function safeUrl(value) {
    try {
      var url = new URL(value, window.location.origin);
      if (url.protocol === 'http:' || url.protocol === 'https:') return url.href;
    } catch (e) {}
    return '';
  }

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

  function normalizeRuntimeSrcset(value) {
    var srcset = textValue(value);
    if (!srcset) return '';
    return srcset
      .split(',')
      .map(function (chunk) {
        var token = textValue(chunk);
        if (!token) return '';
        var parts = token.split(/\s+/);
        if (!parts.length) return '';
        parts[0] = normalizeRuntimeUrl(parts[0]);
        return parts.join(' ');
      })
      .filter(function (chunk) { return !!chunk; })
      .join(', ');
  }

  app.utils = {
    toArray: toArray,
    roleIndex: roleIndex,
    findByRole: findByRole,
    safeUrl: safeUrl,
    textValue: textValue,
    normalizeKey: normalizeKey,
    normalizeRuntimeUrl: normalizeRuntimeUrl,
    normalizeRuntimeSrcset: normalizeRuntimeSrcset
  };
})(window);
