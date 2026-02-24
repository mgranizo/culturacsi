(function () {
  var ROLE_ORDER = ['macro', 'settore', 'settore2', 'regione', 'provincia', 'comune'];
  var modalState = null;
  var heroState = null;

  function toArray(nodeList) {
    return Array.prototype.slice.call(nodeList || []);
  }

  function roleIndex(role) {
    return ROLE_ORDER.indexOf(role);
  }

  function findByRole(form, role) {
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

  function getHeroConfig() {
    var cfg = window.AB_SETTORI_HERO;
    if (!cfg || typeof cfg !== 'object') return { enabled: false, map: {} };
    return {
      enabled: !!cfg.enabled,
      map: cfg.map && typeof cfg.map === 'object' ? cfg.map : {}
    };
  }

  function ensureHeroStaticStyles() {
    if (document.getElementById('abf-hero-static-style')) return;
    var style = document.createElement('style');
    style.id = 'abf-hero-static-style';
    style.textContent = [
      '.wp-block-kadence-advancedgallery.abf-hero-static .kb-gallery-image-contain img,',
      '.wp-block-kadence-advancedgallery.abf-hero-static .splide__slide img,',
      '.wp-block-kadence-advancedgallery.abf-hero-static .splide__slide,',
      '.wp-block-kadence-advancedgallery.abf-hero-static .splide__list{',
      'animation:none !important;',
      'transition:none !important;',
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

  function ensureHeroState(form) {
    if (heroState && heroState.container && document.body.contains(heroState.container)) {
      return heroState;
    }

    var container = findHeroContainer(form);
    if (!container) return null;

    var images = toArray(container.querySelectorAll('.kb-gallery-image-contain img, img[data-splide-lazy], img'));
    if (!images.length) return null;

    images.forEach(function (img) {
      if (!img.dataset.abHeroOrigSrc) img.dataset.abHeroOrigSrc = img.getAttribute('src') || '';
      if (!img.dataset.abHeroOrigLazy) img.dataset.abHeroOrigLazy = img.getAttribute('data-splide-lazy') || '';
      if (!img.dataset.abHeroOrigFull) img.dataset.abHeroOrigFull = img.getAttribute('data-full-image') || '';
      if (!img.dataset.abHeroOrigLight) img.dataset.abHeroOrigLight = img.getAttribute('data-light-image') || '';
      if (!img.dataset.abHeroOrigSrcset) img.dataset.abHeroOrigSrcset = img.getAttribute('srcset') || '';
      if (!img.dataset.abHeroOrigLazySrcset) img.dataset.abHeroOrigLazySrcset = img.getAttribute('data-splide-lazy-srcset') || '';
    });

    var sliderRoots = [];
    var seenRoots = [];
    toArray(container.querySelectorAll('.splide, .kb-gallery-ul.kb-gallery-type-slider')).forEach(function (root) {
      if (!root || seenRoots.indexOf(root) !== -1) return;
      seenRoots.push(root);
      sliderRoots.push(root);
    });

    heroState = {
      container: container,
      images: images,
      sliderRoots: sliderRoots,
      currentUrl: '',
      applyToken: 0,
      lastCategoryKey: null,
      staticMode: false
    };
    return heroState;
  }

  function getSplideInstance(root) {
    if (!root || typeof root !== 'object') return null;
    var instance = root.splide || root.__splide || null;
    if (instance && typeof instance === 'object') return instance;
    return null;
  }

  function setHeroStaticMode(state, enabled) {
    if (!state || !state.container) return;
    if (!!state.staticMode === !!enabled) return;

    ensureHeroStaticStyles();
    state.staticMode = !!enabled;

    if (enabled) state.container.classList.add('abf-hero-static');
    else state.container.classList.remove('abf-hero-static');

    (state.sliderRoots || []).forEach(function (root) {
      if (!root) return;
      if (enabled) root.classList.add('abf-hero-static');
      else root.classList.remove('abf-hero-static');

      var splide = getSplideInstance(root);
      if (!splide || !splide.Components || !splide.Components.Autoplay) return;
      var autoplay = splide.Components.Autoplay;

      if (enabled) {
        var shouldResume = false;
        if (typeof autoplay.isPaused === 'function') {
          try { shouldResume = !autoplay.isPaused(); } catch (e) {}
        } else if (splide.options && splide.options.autoplay) {
          shouldResume = true;
        }
        root.dataset.abHeroResumeAutoplay = shouldResume ? '1' : '0';
        try { autoplay.pause(); } catch (e) {}
        return;
      }

      var resume = root.dataset.abHeroResumeAutoplay === '1';
      delete root.dataset.abHeroResumeAutoplay;
      if (!resume) return;
      try { autoplay.play(); } catch (e) {}
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
    if (img.dataset.abHeroOrigSrcset !== undefined) {
      if (img.dataset.abHeroOrigSrcset) img.setAttribute('srcset', img.dataset.abHeroOrigSrcset);
      else img.removeAttribute('srcset');
    }
    if (img.dataset.abHeroOrigLazySrcset !== undefined) {
      if (img.dataset.abHeroOrigLazySrcset) img.setAttribute('data-splide-lazy-srcset', img.dataset.abHeroOrigLazySrcset);
      else img.removeAttribute('data-splide-lazy-srcset');
    }
  }

  function setHeroImageAttributes(img, url) {
    img.setAttribute('src', url);
    img.setAttribute('data-splide-lazy', url);
    img.setAttribute('data-full-image', url);
    img.setAttribute('data-light-image', url);
    img.removeAttribute('srcset');
    img.removeAttribute('data-splide-lazy-srcset');
  }

  function restoreHeroCarousel(state) {
    if (!state || !state.images) return;
    setHeroStaticMode(state, false);
    state.images.forEach(restoreHeroImageAttributes);
    state.currentUrl = '';
  }

  function preloadHeroImage(url, onDone) {
    if (!url) {
      onDone(false);
      return;
    }

    var done = false;
    var probe = new Image();
    var timer = setTimeout(function () {
      if (done) return;
      done = true;
      onDone(false);
    }, 3500);

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
    probe.src = url;
  }

  function getHeroUrlFromForm(form) {
    var cfg = getHeroConfig();
    if (!cfg.enabled) return '';

    var roles = ['settore2', 'settore', 'macro'];
    for (var i = 0; i < roles.length; i++) {
      var select = findByRole(form, roles[i]);
      if (!select) continue;
      var key = normalizeKey(select.value || '');
      if (key && cfg.map[key]) {
        return safeUrl(cfg.map[key]) || '';
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
    var state = ensureHeroState(form);
    if (!state || !form) return;

    var categoryKey = getHeroCategorySelectionKey(form);
    if (state.lastCategoryKey === categoryKey) {
      return;
    }
    state.lastCategoryKey = categoryKey;

    var nextUrl = getHeroUrlFromForm(form);
    if (!nextUrl) {
      restoreHeroCarousel(state);
      return;
    }

    if (state.currentUrl === nextUrl) {
      setHeroStaticMode(state, true);
      return;
    }

    state.applyToken += 1;
    var token = state.applyToken;

    preloadHeroImage(nextUrl, function (ok) {
      if (!heroState || token !== heroState.applyToken) return;

      if (!ok) {
        restoreHeroCarousel(heroState);
        return;
      }

      heroState.images.forEach(function (img) {
        setHeroImageAttributes(img, nextUrl);
      });
      setHeroStaticMode(heroState, true);
      heroState.currentUrl = nextUrl;
    });
  }

  function parseCardData(card) {
    var script = card.querySelector('script.ab-assoc-data');
    if (!script) return null;
    try {
      var parsed = JSON.parse(script.textContent || '{}');
      return parsed && typeof parsed === 'object' ? parsed : null;
    } catch (e) {
      return null;
    }
  }

  function ensureModal() {
    if (modalState) return modalState;

    var backdrop = document.createElement('div');
    backdrop.className = 'ab-assoc-modal-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');

    var modal = document.createElement('div');
    modal.className = 'ab-assoc-modal';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'ab-assoc-modal-title');

    var header = document.createElement('div');
    header.className = 'ab-assoc-modal-header';

    var titleWrap = document.createElement('div');
    titleWrap.className = 'ab-assoc-modal-title-wrap';

    var logoWrap = document.createElement('div');
    logoWrap.className = 'ab-assoc-modal-logo';
    var logoImg = document.createElement('img');
    logoImg.alt = '';
    logoWrap.appendChild(logoImg);

    var titleTextWrap = document.createElement('div');
    var title = document.createElement('h3');
    title.className = 'ab-assoc-modal-title';
    title.id = 'ab-assoc-modal-title';
    var subtitle = document.createElement('div');
    subtitle.className = 'ab-assoc-modal-subtitle';
    titleTextWrap.appendChild(title);
    titleTextWrap.appendChild(subtitle);

    titleWrap.appendChild(logoWrap);
    titleWrap.appendChild(titleTextWrap);

    var closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ab-assoc-modal-close';
    closeBtn.textContent = 'Chiudi';

    header.appendChild(titleWrap);
    header.appendChild(closeBtn);

    var body = document.createElement('div');
    body.className = 'ab-assoc-modal-body';

    var detailsBox = document.createElement('div');
    detailsBox.className = 'ab-assoc-modal-box';
    var detailsTitle = document.createElement('h5');
    detailsTitle.textContent = 'Dettagli associazione';
    var fields = document.createElement('div');
    fields.className = 'ab-assoc-modal-fields';
    var links = document.createElement('div');
    links.className = 'ab-assoc-modal-links';
    detailsBox.appendChild(detailsTitle);
    detailsBox.appendChild(fields);
    detailsBox.appendChild(links);

    var mapBox = document.createElement('div');
    mapBox.className = 'ab-assoc-modal-box ab-assoc-modal-map';
    var mapTitle = document.createElement('h5');
    mapTitle.textContent = 'Mappa';
    var mapContainer = document.createElement('div');
    mapBox.appendChild(mapTitle);
    mapBox.appendChild(mapContainer);

    body.appendChild(detailsBox);
    body.appendChild(mapBox);

    modal.appendChild(header);
    modal.appendChild(body);
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);

    function closeModal() {
      backdrop.classList.remove('is-open');
      backdrop.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('ab-assoc-modal-open');
    }

    closeBtn.addEventListener('click', closeModal);
    backdrop.addEventListener('click', function (event) {
      if (event.target === backdrop) closeModal();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && backdrop.classList.contains('is-open')) {
        closeModal();
      }
    });

    modalState = {
      backdrop: backdrop,
      title: title,
      subtitle: subtitle,
      logoWrap: logoWrap,
      logoImg: logoImg,
      fields: fields,
      links: links,
      mapContainer: mapContainer,
      close: closeModal
    };

    return modalState;
  }

  function addField(state, label, value, opts) {
    opts = opts || {};
    var text = textValue(value);
    if (!text) return;

    var row = document.createElement('div');
    row.className = 'ab-assoc-modal-field';

    var lab = document.createElement('div');
    lab.className = 'ab-assoc-modal-field-label';
    lab.textContent = label;

    var val = document.createElement('div');

    if (opts.url) {
      var href = safeUrl(text);
      if (href) {
        var a = document.createElement('a');
        a.href = href;
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.textContent = text;
        val.appendChild(a);
      } else {
        val.textContent = text;
      }
    } else {
      val.textContent = text;
    }

    row.appendChild(lab);
    row.appendChild(val);
    state.fields.appendChild(row);
  }

  function addEmailField(state, label, value) {
    var raw = textValue(value);
    if (!raw) return;

    var seen = {};
    var emails = [];
    raw.split(/[|,;\s]+/).forEach(function (token) {
      var email = textValue(token).toLowerCase();
      if (!email) return;
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
      if (seen[email]) return;
      seen[email] = true;
      emails.push(email);
    });

    if (!emails.length) {
      addField(state, label, raw);
      return;
    }

    var row = document.createElement('div');
    row.className = 'ab-assoc-modal-field';

    var lab = document.createElement('div');
    lab.className = 'ab-assoc-modal-field-label';
    lab.textContent = label;

    var val = document.createElement('div');
    emails.forEach(function (email, idx) {
      if (idx > 0) val.appendChild(document.createTextNode(', '));
      var a = document.createElement('a');
      a.href = 'mailto:' + email;
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
      a.textContent = email;
      val.appendChild(a);
    });

    row.appendChild(lab);
    row.appendChild(val);
    state.fields.appendChild(row);
  }

  function openCardModal(data) {
    if (!data || typeof data !== 'object') return;

    var state = ensureModal();

    state.title.textContent = textValue(data.organization) || 'Associazione';
    state.subtitle.textContent = textValue(data.location);

    var logoUrl = safeUrl(textValue(data.logo_url));
    if (logoUrl) {
      state.logoImg.src = logoUrl;
      state.logoImg.alt = state.title.textContent;
      state.logoWrap.classList.add('is-visible');
    } else {
      state.logoImg.removeAttribute('src');
      state.logoImg.alt = '';
      state.logoWrap.classList.remove('is-visible');
    }

    state.fields.innerHTML = '';
    state.links.innerHTML = '';
    state.mapContainer.innerHTML = '';

    var websiteValue = textValue(data.website);
    var facebookValue = textValue(data.facebook);
    var instagramValue = textValue(data.instagram);
    var emailValue = textValue(data.emails);

    addField(state, 'Categorie', textValue(data.category_groups_label) || textValue(data.all_categories) || textValue(data.category));
    addField(state, 'Attivita', textValue(data.activities_label) || textValue(data.activity_categories));
    addField(state, 'Regione', data.region);
    addField(state, 'Provincia', data.province);
    addField(state, 'Comune / Citta', data.city);
    addField(state, 'Indirizzo', data.address);
    addField(state, 'Telefono', data.phone);
    addEmailField(state, 'Email', emailValue);
    addField(state, 'Sito web', websiteValue, { url: true });
    addField(state, 'Facebook', facebookValue, { url: true });
    addField(state, 'Instagram', instagramValue, { url: true });
    addField(state, 'Note', data.notes);
    addField(state, 'Localita (sorgente)', data.location_raw);

    var eventsUrl = safeUrl(textValue(data.events_url));
    if (eventsUrl) {
      var eventsBtn = document.createElement('a');
      eventsBtn.className = 'ab-btn';
      eventsBtn.href = eventsUrl;
      eventsBtn.target = '_blank';
      eventsBtn.rel = 'noopener noreferrer';
      eventsBtn.textContent = 'Eventi';
      state.links.appendChild(eventsBtn);
    }

    var newsUrl = safeUrl(textValue(data.news_url));
    if (newsUrl) {
      var newsBtn = document.createElement('a');
      newsBtn.className = 'ab-btn';
      newsBtn.href = newsUrl;
      newsBtn.target = '_blank';
      newsBtn.rel = 'noopener noreferrer';
      newsBtn.textContent = 'Notizie';
      state.links.appendChild(newsBtn);
    }

    var mapUrl = safeUrl(textValue(data.map_embed_url));
    if (mapUrl) {
      var iframe = document.createElement('iframe');
      iframe.loading = 'lazy';
      iframe.referrerPolicy = 'no-referrer-when-downgrade';
      iframe.src = mapUrl;
      iframe.title = 'Mappa associazione';
      state.mapContainer.appendChild(iframe);
    } else {
      var empty = document.createElement('div');
      empty.className = 'ab-muted';
      empty.textContent = 'Mappa non disponibile per questa associazione.';
      state.mapContainer.appendChild(empty);
    }

    state.backdrop.classList.add('is-open');
    state.backdrop.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ab-assoc-modal-open');
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
    window.history.replaceState({}, '', requestUrl);
  }

  function fetchAndReplace(wrapper, requestUrl, controllerRef) {
    if (controllerRef.current && typeof controllerRef.current.abort === 'function') {
      controllerRef.current.abort();
    }

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
      applyHeroImageForForm(form);

      form.addEventListener('change', function (event) {
        var target = event.target;
        if (!target || target.tagName !== 'SELECT') return;

        var role = target.getAttribute('data-abf-role') || '';
        if (!role || role === 'page') return;

        setFirstPage(form);
        syncImmediateState(form);
        applyHeroImageForForm(form);

        var requestUrl = buildRequestUrl(form);
        fetchAndReplace(wrapper, requestUrl, controllerRef);
      });

      form.addEventListener('submit', function (event) {
        event.preventDefault();
        setFirstPage(form);
        syncImmediateState(form);
        applyHeroImageForForm(form);
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
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
