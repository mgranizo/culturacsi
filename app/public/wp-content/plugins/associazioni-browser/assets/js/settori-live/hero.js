(function (window, document) {
  'use strict';

  var app = window.ABFSettoriLive = window.ABFSettoriLive || {};
  if (!app.utils || !app.state) return;

  var state = app.state;
  var toArray = app.utils.toArray;
  var findByRole = app.utils.findByRole;
  var safeUrl = app.utils.safeUrl;
  var textValue = app.utils.textValue;
  var normalizeKey = app.utils.normalizeKey;
  var normalizeRuntimeUrl = app.utils.normalizeRuntimeUrl;
  var normalizeRuntimeSrcset = app.utils.normalizeRuntimeSrcset;

  function getHeroQueryParamName() {
    var cfg = window.AB_SETTORI_PATTERN_LINKS;
    if (cfg && typeof cfg === 'object') {
      var param = textValue(cfg.heroParam);
      if (param) return param;
    }
    return 'abf_hero';
  }

  function getQueryParamValue(name) {
    var key = textValue(name);
    if (!key) return '';
    try {
      var url = new URL(window.location.href);
      return textValue(url.searchParams.get(key) || '');
    } catch (e) {
      return '';
    }
  }

  function clearQueryParam(name) {
    var key = textValue(name);
    if (!key) return;
    try {
      var url = new URL(window.location.href);
      if (!url.searchParams.has(key)) return;
      url.searchParams.delete(key);
      window.history.replaceState(window.history.state || {}, '', url.toString());
    } catch (e) { }
  }

  function sanitizeHeroImageAttributes(img) {
    if (!img || !img.getAttribute) return;

    ['src', 'data-splide-lazy', 'data-full-image', 'data-light-image', 'data-opt-src'].forEach(function (attr) {
      var current = img.getAttribute(attr);
      if (!current) return;
      var normalized = normalizeRuntimeUrl(current);
      if (normalized && normalized !== current) {
        img.setAttribute(attr, normalized);
      }
    });

    ['srcset', 'data-splide-lazy-srcset'].forEach(function (attr) {
      var current = img.getAttribute(attr);
      if (!current) return;
      var normalized = normalizeRuntimeSrcset(current);
      if (normalized && normalized !== current) {
        img.setAttribute(attr, normalized);
      }
    });
  }

  function getHeroConfig() {
    var cfg = window.AB_SETTORI_HERO;
    if (!cfg || typeof cfg !== 'object') return { enabled: false, map: {} };
    return {
      enabled: !!cfg.enabled,
      map: cfg.map && typeof cfg.map === 'object' ? cfg.map : {},
      overrides: cfg.overrides && typeof cfg.overrides === 'object' ? cfg.overrides : {}
    };
  }

  function hasSelectedHeroCategory(form) {
    if (!form) return false;
    var roles = ['macro', 'settore', 'settore2'];
    for (var i = 0; i < roles.length; i++) {
      var field = findByRole(form, roles[i]);
      if (field && textValue(field.value) !== '') return true;
    }
    return false;
  }

  function softKey(value) {
    return normalizeKey(value).replace(/(.)\1+/g, '$1');
  }

  function resolveHeroUrlByKey(cfg, key) {
    var normalizedKey = normalizeKey(key);
    if (!normalizedKey) return '';

    var direct = '';
    if (cfg.overrides && cfg.overrides[normalizedKey]) {
      direct = safeUrl(cfg.overrides[normalizedKey]) || '';
    } else if (cfg.map && cfg.map[normalizedKey]) {
      direct = safeUrl(cfg.map[normalizedKey]) || '';
    }
    if (direct) return normalizeRuntimeUrl(direct);

    var wantedSoft = softKey(normalizedKey);
    if (!wantedSoft) return '';

    var buckets = [cfg.overrides || {}, cfg.map || {}];
    for (var b = 0; b < buckets.length; b++) {
      var bucket = buckets[b];
      var keys = Object.keys(bucket);
      for (var i = 0; i < keys.length; i++) {
        var candidate = keys[i];
        if (softKey(candidate) !== wantedSoft) continue;
        var resolved = safeUrl(bucket[candidate]) || '';
        if (resolved) return normalizeRuntimeUrl(resolved);
      }
    }

    return '';
  }

  function runAfterInitialPaint(fn) {
    if (typeof fn !== 'function') return;
    if (typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(function () {
        window.setTimeout(fn, 0);
      });
      return;
    }
    window.setTimeout(fn, 0);
  }

  function scheduleHeroApply(form, force) {
    var cfg = getHeroConfig();
    if (!cfg.enabled || !form) return;
    if (!force && !hasSelectedHeroCategory(form)) {
      var heroData = ensureHeroState(form);
      var pendingQueryHero = !!(heroData && !heroData.queryHeroHandled && textValue(heroData.queryHeroKey) !== '');
      if (!pendingQueryHero && !(heroData && heroData.staticMode)) return;
    }
    runAfterInitialPaint(function () {
      applyHeroImageForForm(form);
    });
  }

  function ensureHeroStaticStyles() {
    if (document.getElementById('abf-hero-static-style')) return;
    var style = document.createElement('style');
    style.id = 'abf-hero-static-style';
    style.textContent = [
      '.wp-block-kadence-advancedgallery{position:relative;}',
      '.wp-block-kadence-advancedgallery .abf-hero-static-overlay{display:none;position:absolute;inset:0;z-index:20;background:#eef2f6;pointer-events:none;}',
      '.wp-block-kadence-advancedgallery .abf-hero-static-overlay img{width:100%;height:100%;object-fit:cover;display:block;}',
      '.wp-block-kadence-advancedgallery.abf-hero-static .abf-hero-static-overlay{display:block;}',
      '.wp-block-kadence-advancedgallery.abf-hero-static .kb-gallery-image-contain img,',
      '.wp-block-kadence-advancedgallery.abf-hero-static .splide__slide img,',
      '.wp-block-kadence-advancedgallery.abf-hero-static .splide__slide,',
      '.wp-block-kadence-advancedgallery.abf-hero-static .splide__list{',
      'animation:none !important;',
      'transition:none !important;',
      '}',
      '.wp-block-kadence-advancedgallery.abf-hero-static .splide__arrows,',
      '.wp-block-kadence-advancedgallery.abf-hero-static .splide__pagination{',
      'pointer-events:none !important;',
      'opacity:0.35;',
      '}'
    ].join('');
    document.head.appendChild(style);
  }

  function findHeroContainer(form) {
    var galleries = toArray(document.querySelectorAll('.wp-block-kadence-advancedgallery'));
    var sliderGalleries = [];
    for (var i = 0; i < galleries.length; i++) {
      var gallery = galleries[i];
      var heroSlider = gallery.querySelector('.kb-gallery-ul.kb-gallery-type-slider');
      if (heroSlider) sliderGalleries.push(gallery);
    }
    if (!sliderGalleries.length) return null;
    if (!form) return sliderGalleries[0];

    var anchor = form.closest('[data-abf-live="1"]') || form;
    var before = null;
    for (var j = 0; j < sliderGalleries.length; j++) {
      var candidate = sliderGalleries[j];
      var relation = candidate.compareDocumentPosition(anchor);
      if (relation & Node.DOCUMENT_POSITION_FOLLOWING) {
        before = candidate;
      }
    }

    return before || sliderGalleries[0];
  }

  function heroImageFallbackCandidates(img) {
    if (!img || !img.getAttribute) return [];
    var list = [
      img.getAttribute('data-splide-lazy'),
      img.getAttribute('data-opt-src'),
      img.getAttribute('data-full-image'),
      img.getAttribute('data-light-image'),
      img.getAttribute('src')
    ];
    var out = [];
    list.forEach(function (raw) {
      var value = normalizeRuntimeUrl(raw || '');
      if (!value) return;
      if (out.indexOf(value) !== -1) return;
      out.push(value);
    });
    return out;
  }

  function bindHeroImageErrorFallback(img) {
    if (!img || img.dataset.abHeroFallbackBound === '1') return;
    img.dataset.abHeroFallbackBound = '1';
    img.addEventListener('error', function () {
      var candidates = heroImageFallbackCandidates(img);
      if (!candidates.length) return;
      var current = normalizeRuntimeUrl(img.getAttribute('src') || '');
      var next = '';
      for (var i = 0; i < candidates.length; i++) {
        if (candidates[i] !== current) {
          next = candidates[i];
          break;
        }
      }
      if (!next) return;
      img.setAttribute('src', next);
      img.setAttribute('data-opt-lazy-loaded', '1');
    });
  }

  function materializeHeroImage(img) {
    if (!img || !img.getAttribute) return;
    sanitizeHeroImageAttributes(img);
    bindHeroImageErrorFallback(img);

    var src = normalizeRuntimeUrl(img.getAttribute('src') || '');
    var isPlaceholder = !src || /^data:image\//i.test(src);
    if (!isPlaceholder) return;

    var candidates = heroImageFallbackCandidates(img);
    if (!candidates.length) return;
    var chosen = candidates[0];
    img.setAttribute('src', chosen);
    img.setAttribute('data-opt-lazy-loaded', '1');
  }

  function ensureHeroOverlay(container) {
    if (!container) return { wrap: null, img: null };

    var existing = container.querySelector('.abf-hero-static-overlay');
    if (existing) {
      var existingImg = existing.querySelector('img');
      if (!existingImg) {
        existingImg = document.createElement('img');
        existingImg.alt = '';
        existing.appendChild(existingImg);
      }
      return { wrap: existing, img: existingImg };
    }

    var wrap = document.createElement('div');
    wrap.className = 'abf-hero-static-overlay';
    var img = document.createElement('img');
    img.alt = '';
    wrap.appendChild(img);
    container.appendChild(wrap);
    return { wrap: wrap, img: img };
  }

  function ensureHeroState(form) {
    if (state.heroState && state.heroState.container && document.body.contains(state.heroState.container)) {
      return state.heroState;
    }

    var container = findHeroContainer(form);
    if (!container) return null;

    var images = toArray(container.querySelectorAll(
      '.kb-gallery-image-contain img, .kb-gallery-ul.kb-gallery-type-slider img, .splide__slide img'
    ));
    if (!images.length) return null;

    images.forEach(function (img) {
      materializeHeroImage(img);
      if (!img.dataset.abHeroOrigSrc) img.dataset.abHeroOrigSrc = img.getAttribute('src') || '';
      if (!img.dataset.abHeroOrigLazy) img.dataset.abHeroOrigLazy = img.getAttribute('data-splide-lazy') || '';
      if (!img.dataset.abHeroOrigFull) img.dataset.abHeroOrigFull = img.getAttribute('data-full-image') || '';
      if (!img.dataset.abHeroOrigLight) img.dataset.abHeroOrigLight = img.getAttribute('data-light-image') || '';
      if (!img.dataset.abHeroOrigOptSrc) img.dataset.abHeroOrigOptSrc = img.getAttribute('data-opt-src') || '';
      if (!img.dataset.abHeroOrigSrcset) img.dataset.abHeroOrigSrcset = img.getAttribute('srcset') || '';
      if (!img.dataset.abHeroOrigLazySrcset) img.dataset.abHeroOrigLazySrcset = img.getAttribute('data-splide-lazy-srcset') || '';
      if (!img.dataset.abHeroOrigLoading) img.dataset.abHeroOrigLoading = img.getAttribute('loading') || '';
      if (!img.dataset.abHeroOrigFetchpriority) img.dataset.abHeroOrigFetchpriority = img.getAttribute('fetchpriority') || '';
      if (!img.dataset.abHeroOrigOptLoaded) img.dataset.abHeroOrigOptLoaded = img.getAttribute('data-opt-lazy-loaded') || '';
    });

    var sliderRoots = [];
    var seenRoots = [];
    toArray(container.querySelectorAll('.splide, .kb-gallery-ul.kb-gallery-type-slider')).forEach(function (root) {
      if (!root || seenRoots.indexOf(root) !== -1) return;
      seenRoots.push(root);
      sliderRoots.push(root);
    });

    var overlay = ensureHeroOverlay(container);

    state.heroState = {
      container: container,
      images: images,
      sliderRoots: sliderRoots,
      overlayWrap: overlay.wrap,
      overlayImg: overlay.img,
      currentUrl: '',
      applyToken: 0,
      lastCategoryKey: null,
      staticMode: false,
      visibilityObserverBound: false,
      queryHeroParam: getHeroQueryParamName(),
      queryHeroKey: normalizeKey(getQueryParamValue(getHeroQueryParamName())),
      queryHeroHandled: false
    };
    bindHeroVisibilityObserver(state.heroState);
    return state.heroState;
  }

  function getSplideInstance(root) {
    if (!root || typeof root !== 'object') return null;
    var instance = root.splide || root.__splide || null;
    if (instance && typeof instance === 'object') return instance;
    return null;
  }

  function getAutoplayComponent(splide) {
    if (!splide || !splide.Components) return null;
    if (splide.Components.Autoplay) return splide.Components.Autoplay;
    if (splide.Components.autoScroll) return splide.Components.autoScroll;
    if (splide.Components.AutoScroll) return splide.Components.AutoScroll;
    return null;
  }

  function pauseComponent(component) {
    if (!component) return;
    if (typeof component.pause === 'function') {
      try { component.pause(); } catch (e) { }
      return;
    }
    if (typeof component.stop === 'function') {
      try { component.stop(); } catch (e) { }
    }
  }

  function playComponent(component) {
    if (!component) return;
    if (typeof component.play === 'function') {
      try { component.play(); } catch (e) { }
      return;
    }
    if (typeof component.start === 'function') {
      try { component.start(); } catch (e) { }
    }
  }

  function shouldResumeComponent(component, splide) {
    if (!component) return false;
    if (typeof component.isPaused === 'function') {
      try { return !component.isPaused(); } catch (e) { }
    }
    if (splide && splide.options && splide.options.autoplay) return true;
    return false;
  }

  function lockSplideIndex(root, splide, index) {
    if (!root || !splide) return;
    var targetIndex = parseInt(index, 10);
    if (!isFinite(targetIndex) || targetIndex < 0) targetIndex = 0;
    root.dataset.abHeroLockedIndex = String(targetIndex);
    root.dataset.abHeroLocked = '1';
    try { splide.go(targetIndex); } catch (e) { }

    if (root.__abHeroMovedHandlerBound) return;
    if (typeof splide.on !== 'function') return;

    var movedHandler = function () {
      if (root.dataset.abHeroLocked !== '1') return;
      var wanted = parseInt(root.dataset.abHeroLockedIndex || '0', 10);
      if (!isFinite(wanted) || wanted < 0) wanted = 0;
      if (typeof splide.index !== 'number' || splide.index === wanted) return;
      window.setTimeout(function () {
        if (root.dataset.abHeroLocked !== '1') return;
        try { splide.go(wanted); } catch (e) { }
      }, 0);
    };

    root.__abHeroMovedHandlerBound = true;
    try { splide.on('moved', movedHandler); } catch (e) { }
    try { splide.on('move', movedHandler); } catch (e) { }
  }

  function unlockSplideIndex(root) {
    if (!root) return;
    delete root.dataset.abHeroLocked;
    delete root.dataset.abHeroLockedIndex;
  }

  function setHeroOverlayImage(heroData, url) {
    if (!heroData || !heroData.overlayImg) return;
    var resolvedUrl = normalizeRuntimeUrl(url);
    if (!resolvedUrl) return;
    heroData.overlayImg.setAttribute('src', resolvedUrl);
    heroData.overlayImg.setAttribute('loading', 'eager');
    heroData.overlayImg.setAttribute('decoding', 'async');
  }

  function clearHeroOverlayImage(heroData) {
    if (!heroData || !heroData.overlayImg) return;
    heroData.overlayImg.removeAttribute('src');
  }

  function bindHeroVisibilityObserver(heroData) {
    if (!heroData || !heroData.container || heroData.visibilityObserverBound) return;
    heroData.visibilityObserverBound = true;

    if (typeof IntersectionObserver === 'undefined') return;

    try {
      var observer = new IntersectionObserver(function (entries) {
        if (!heroData.staticMode || !heroData.currentUrl) return;
        var visible = false;
        for (var i = 0; i < entries.length; i++) {
          if (entries[i] && entries[i].isIntersecting) {
            visible = true;
            break;
          }
        }
        if (!visible) return;

        setHeroOverlayImage(heroData, heroData.currentUrl);
        setHeroStaticMode(heroData, true);
      }, { threshold: 0.05 });

      observer.observe(heroData.container);
    } catch (e) { }
  }

  function setHeroStaticMode(heroData, enabled) {
    if (!heroData || !heroData.container) return;
    if (!!heroData.staticMode === !!enabled) return;

    ensureHeroStaticStyles();
    heroData.staticMode = !!enabled;

    if (enabled) heroData.container.classList.add('abf-hero-static');
    else heroData.container.classList.remove('abf-hero-static');
    if (!enabled) clearHeroOverlayImage(heroData);

    (heroData.sliderRoots || []).forEach(function (root) {
      if (!root) return;
      if (enabled) root.classList.add('abf-hero-static');
      else root.classList.remove('abf-hero-static');

      var splide = getSplideInstance(root);
      if (!splide) return;
      var autoplay = getAutoplayComponent(splide);

      if (enabled) {
        var shouldResume = shouldResumeComponent(autoplay, splide);
        root.dataset.abHeroResumeAutoplay = shouldResume ? '1' : '0';
        pauseComponent(autoplay);
        var index = (typeof splide.index === 'number' && splide.index >= 0) ? splide.index : 0;
        lockSplideIndex(root, splide, index);
        return;
      }

      unlockSplideIndex(root);
      var resume = root.dataset.abHeroResumeAutoplay === '1';
      delete root.dataset.abHeroResumeAutoplay;

      if (!resume && splide && splide.options && splide.options.autoplay) {
        resume = true;
      }

      if (!resume) return;
      playComponent(autoplay);
    });
  }

  function restoreHeroImageAttributes(img) {
    if (img.dataset.abHeroOrigSrc !== undefined) {
      if (img.dataset.abHeroOrigSrc) img.setAttribute('src', img.dataset.abHeroOrigSrc);
      else img.removeAttribute('src');
    }
    if (img.dataset.abHeroOrigLazy !== undefined) {
      if (img.dataset.abHeroOrigLazy) img.setAttribute('data-splide-lazy', img.dataset.abHeroOrigLazy);
      else img.removeAttribute('data-splide-lazy');
    }
    if (img.dataset.abHeroOrigFull !== undefined) {
      if (img.dataset.abHeroOrigFull) img.setAttribute('data-full-image', img.dataset.abHeroOrigFull);
      else img.removeAttribute('data-full-image');
    }
    if (img.dataset.abHeroOrigLight !== undefined) {
      if (img.dataset.abHeroOrigLight) img.setAttribute('data-light-image', img.dataset.abHeroOrigLight);
      else img.removeAttribute('data-light-image');
    }
    if (img.dataset.abHeroOrigOptSrc !== undefined) {
      if (img.dataset.abHeroOrigOptSrc) img.setAttribute('data-opt-src', img.dataset.abHeroOrigOptSrc);
      else img.removeAttribute('data-opt-src');
    }
    if (img.dataset.abHeroOrigSrcset !== undefined) {
      if (img.dataset.abHeroOrigSrcset) img.setAttribute('srcset', img.dataset.abHeroOrigSrcset);
      else img.removeAttribute('srcset');
    }
    if (img.dataset.abHeroOrigLazySrcset !== undefined) {
      if (img.dataset.abHeroOrigLazySrcset) img.setAttribute('data-splide-lazy-srcset', img.dataset.abHeroOrigLazySrcset);
      else img.removeAttribute('data-splide-lazy-srcset');
    }
    if (img.dataset.abHeroOrigLoading !== undefined) {
      if (img.dataset.abHeroOrigLoading) img.setAttribute('loading', img.dataset.abHeroOrigLoading);
      else img.removeAttribute('loading');
    }
    if (img.dataset.abHeroOrigFetchpriority !== undefined) {
      if (img.dataset.abHeroOrigFetchpriority) img.setAttribute('fetchpriority', img.dataset.abHeroOrigFetchpriority);
      else img.removeAttribute('fetchpriority');
    }
    if (img.dataset.abHeroOrigOptLoaded !== undefined) {
      if (img.dataset.abHeroOrigOptLoaded) img.setAttribute('data-opt-lazy-loaded', img.dataset.abHeroOrigOptLoaded);
      else img.removeAttribute('data-opt-lazy-loaded');
    }

    var origSrc = img.getAttribute('src') || '';
    var origLazy = img.getAttribute('data-splide-lazy') || '';
    var origOpt = img.getAttribute('data-opt-src') || '';
    var isPlaceholder = /^data:image\//i.test(origSrc);
    if (origLazy && (!origSrc || isPlaceholder)) {
      img.setAttribute('src', origLazy);
      img.removeAttribute('data-splide-lazy');
      img.removeAttribute('data-splide-lazy-srcset');
      img.setAttribute('data-opt-lazy-loaded', '1');
    } else if (origOpt && (!origSrc || isPlaceholder)) {
      img.setAttribute('src', origOpt);
      img.setAttribute('data-opt-lazy-loaded', '1');
    }
  }

  function restoreHeroCarousel(heroData) {
    if (!heroData || !heroData.images) return;
    setHeroStaticMode(heroData, false);
    heroData.images.forEach(restoreHeroImageAttributes);
    heroData.currentUrl = '';
  }

  function preloadHeroImage(url, onDone) {
    var resolvedUrl = normalizeRuntimeUrl(url);
    if (!resolvedUrl) {
      onDone(false);
      return;
    }

    var done = false;
    var probe = new Image();
    var timer = setTimeout(function () {
      if (done) return;
      done = true;
      onDone(false);
    }, 10000);

    probe.onload = function () {
      if (done) return;
      done = true;
      clearTimeout(timer);
      onDone(true);
    };
    probe.onerror = function () {
      if (done) return;
      done = true;
      clearTimeout(timer);
      onDone(false);
    };
    probe.src = resolvedUrl;
  }

  function getHeroUrlFromForm(form, heroData) {
    var cfg = getHeroConfig();
    if (!cfg.enabled) return '';

    if (heroData && !heroData.queryHeroHandled) {
      var queryHeroKey = normalizeKey(heroData.queryHeroKey || '');
      if (queryHeroKey) {
        var queryHeroUrl = resolveHeroUrlByKey(cfg, queryHeroKey);
        heroData.queryHeroHandled = true;
        if (queryHeroUrl) {
          clearQueryParam(heroData.queryHeroParam || 'abf_hero');
          return queryHeroUrl;
        }
      } else {
        heroData.queryHeroHandled = true;
      }
    }

    var roles = ['settore2', 'settore', 'macro'];
    for (var i = 0; i < roles.length; i++) {
      var select = findByRole(form, roles[i]);
      if (!select) continue;
      var key = normalizeKey(select.value || '');
      if (!key) continue;

      var roleHeroUrl = resolveHeroUrlByKey(cfg, key);
      if (roleHeroUrl) {
        return roleHeroUrl;
      }
    }
    return '';
  }

  function getHeroCategorySelectionKey(form) {
    if (!form) return '';
    var macro = normalizeKey((findByRole(form, 'macro') || {}).value || '');
    var settore = normalizeKey((findByRole(form, 'settore') || {}).value || '');
    var settore2 = normalizeKey((findByRole(form, 'settore2') || {}).value || '');
    return [macro, settore, settore2].join('|');
  }

  function applyHeroImageForForm(form) {
    var heroData = ensureHeroState(form);
    if (!heroData || !form) return;

    var categoryKey = getHeroCategorySelectionKey(form);
    var pendingQueryHero = !heroData.queryHeroHandled && textValue(heroData.queryHeroKey) !== '';
    if (heroData.lastCategoryKey === categoryKey && !pendingQueryHero) {
      return;
    }
    heroData.lastCategoryKey = categoryKey;

    var nextUrl = getHeroUrlFromForm(form, heroData);
    if (!nextUrl) {
      restoreHeroCarousel(heroData);
      return;
    }

    if (heroData.currentUrl === nextUrl) {
      setHeroOverlayImage(heroData, nextUrl);
      setHeroStaticMode(heroData, true);
      return;
    }

    heroData.applyToken += 1;
    var token = heroData.applyToken;

    preloadHeroImage(nextUrl, function (ok) {
      if (!state.heroState || token !== state.heroState.applyToken) return;

      if (!ok) {
        restoreHeroCarousel(state.heroState);
        return;
      }

      setHeroOverlayImage(state.heroState, nextUrl);
      setHeroStaticMode(state.heroState, true);
      state.heroState.currentUrl = nextUrl;
    });
  }

  app.hero = {
    scheduleHeroApply: scheduleHeroApply,
    ensureHeroState: ensureHeroState,
    applyHeroImageForForm: applyHeroImageForForm
  };
})(window, document);
