(function (window, document) {
  'use strict';

  var app = window.ABFSettoriLive = window.ABFSettoriLive || {};
  if (!app.utils || !app.state) return;

  var state = app.state;
  var safeUrl = app.utils.safeUrl;
  var textValue = app.utils.textValue;
  var runtimeCfg = window.AB_SETTORI_PATTERN_LINKS && typeof window.AB_SETTORI_PATTERN_LINKS === 'object'
    ? window.AB_SETTORI_PATTERN_LINKS
    : {};
  var uiMap = runtimeCfg.i18n && typeof runtimeCfg.i18n === 'object' ? runtimeCfg.i18n : {};

  function t(label) {
    var key = textValue(label);
    if (!key) return '';
    if (Object.prototype.hasOwnProperty.call(uiMap, key) && textValue(uiMap[key])) {
      return textValue(uiMap[key]);
    }
    return key;
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
    if (state.modalState) return state.modalState;

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
    closeBtn.textContent = t('Chiudi');

    header.appendChild(titleWrap);
    header.appendChild(closeBtn);

    var body = document.createElement('div');
    body.className = 'ab-assoc-modal-body';

    var detailsBox = document.createElement('div');
    detailsBox.className = 'ab-assoc-modal-box';
    var detailsTitle = document.createElement('h5');
    detailsTitle.textContent = t('Dettagli associazione');
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
    mapTitle.textContent = t('Mappa');
    var mapContainer = document.createElement('div');
    mapBox.appendChild(mapTitle);
    mapBox.appendChild(mapContainer);

    var descriptionBox = document.createElement('div');
    descriptionBox.className = 'ab-assoc-modal-desc';

    body.appendChild(detailsBox);
    body.appendChild(mapBox);
    body.appendChild(descriptionBox);

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

    state.modalState = {
      backdrop: backdrop,
      title: title,
      subtitle: subtitle,
      logoWrap: logoWrap,
      logoImg: logoImg,
      fields: fields,
      links: links,
      mapContainer: mapContainer,
      descriptionBox: descriptionBox,
      close: closeModal
    };

    return state.modalState;
  }

  function addField(modalState, label, value, opts) {
    opts = opts || {};
    var text = textValue(value);
    if (!text) return;

    var row = document.createElement('div');
    row.className = 'ab-assoc-modal-field';

    var lab = document.createElement('div');
    lab.className = 'ab-assoc-modal-field-label';
    lab.textContent = label;

    var val = document.createElement('div');
    val.className = 'ab-assoc-modal-field-value';
    if (opts.emphasis) {
      val.classList.add('is-emphasis');
    }

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
    modalState.fields.appendChild(row);
  }

  function addEmailField(modalState, label, value) {
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
      addField(modalState, label, raw);
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
    modalState.fields.appendChild(row);
  }

  function openCardModal(data) {
    if (!data || typeof data !== 'object') return;

    var modalState = ensureModal();

    var associationTitle = textValue(data.association_title) || textValue(data.organization);
    modalState.title.textContent = associationTitle || t('Associazione');
    modalState.subtitle.textContent = textValue(data.location);

    var logoUrl = safeUrl(textValue(data.logo_url));
    if (logoUrl) {
      modalState.logoImg.src = logoUrl;
      modalState.logoImg.alt = modalState.title.textContent;
      modalState.logoWrap.classList.add('is-visible');
    } else {
      modalState.logoImg.removeAttribute('src');
      modalState.logoImg.alt = '';
      modalState.logoWrap.classList.remove('is-visible');
    }

    modalState.fields.innerHTML = '';
    modalState.links.innerHTML = '';
    modalState.mapContainer.innerHTML = '';

    var websiteValue = textValue(data.website);
    var facebookValue = textValue(data.facebook);
    var instagramValue = textValue(data.instagram);
    var emailValue = textValue(data.emails);
    var descriptionHtml = textValue(data.association_description_html) || textValue(data.description_html);

    if (descriptionHtml) {
      modalState.descriptionBox.innerHTML = descriptionHtml;
      modalState.descriptionBox.style.display = 'block';
    } else {
      modalState.descriptionBox.innerHTML = '';
      modalState.descriptionBox.style.display = 'none';
    }

    addField(modalState, t('Titolo associazione'), associationTitle, { emphasis: true });
    var activityPathsLabel = textValue(data.activity_paths_label);
    if (!activityPathsLabel && Array.isArray(data.activity_paths)) {
      activityPathsLabel = data.activity_paths
        .map(function (item) { return textValue(item); })
        .filter(function (item) { return !!item; })
        .join(' | ');
    }

    addField(modalState, t('Attivita'), textValue(data.activities_label) || textValue(data.activity_categories), { emphasis: true });
    addField(modalState, t('Macro > Settore > Settore 2'), activityPathsLabel || textValue(data.all_categories) || textValue(data.category));
    addField(modalState, t('Regione'), data.region);
    addField(modalState, t('Provincia'), data.province);
    addField(modalState, t('Comune / Citta'), data.city);
    addField(modalState, t('Indirizzo'), data.address);
    addField(modalState, t('Telefono'), data.phone);
    addEmailField(modalState, t('Email'), emailValue);
    addField(modalState, t('Sito web'), websiteValue, { url: true });
    addField(modalState, t('Facebook'), facebookValue, { url: true });
    addField(modalState, t('Instagram'), instagramValue, { url: true });
    addField(modalState, t('Note'), data.notes);
    addField(modalState, t('Localita (sorgente)'), data.location_raw);

    var eventsUrl = safeUrl(textValue(data.events_url));
    if (eventsUrl) {
      var eventsBtn = document.createElement('a');
      eventsBtn.className = 'ab-btn';
      eventsBtn.href = eventsUrl;
      eventsBtn.textContent = t('Eventi');
      modalState.links.appendChild(eventsBtn);
    }

    var newsUrl = safeUrl(textValue(data.news_url));
    if (newsUrl) {
      var newsBtn = document.createElement('a');
      newsBtn.className = 'ab-btn';
      newsBtn.href = newsUrl;
      newsBtn.textContent = t('Notizie');
      modalState.links.appendChild(newsBtn);
    }

    var mapUrl = safeUrl(textValue(data.map_embed_url));
    if (mapUrl) {
      var iframe = document.createElement('iframe');
      iframe.loading = 'lazy';
      iframe.referrerPolicy = 'no-referrer-when-downgrade';
      iframe.src = mapUrl;
      iframe.title = t('Mappa associazione');
      modalState.mapContainer.appendChild(iframe);
    } else {
      var empty = document.createElement('div');
      empty.className = 'ab-muted';
      empty.textContent = t('Mappa non disponibile per questa associazione.');
      modalState.mapContainer.appendChild(empty);
    }

    modalState.backdrop.classList.add('is-open');
    modalState.backdrop.setAttribute('aria-hidden', 'false');
    document.body.classList.add('ab-assoc-modal-open');
  }

  app.modal = {
    parseCardData: parseCardData,
    ensureModal: ensureModal,
    openCardModal: openCardModal
  };
})(window, document);
