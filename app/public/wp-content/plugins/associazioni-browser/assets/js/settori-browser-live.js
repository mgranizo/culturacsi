(function (window, document) {
  'use strict';

  function start() {
    var app = window.ABFSettoriLive || {};
    if (!app.filters || typeof app.filters.initAll !== 'function') return;
    app.filters.initAll();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})(window, document);