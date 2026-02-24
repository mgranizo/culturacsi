document.addEventListener('DOMContentLoaded', function () {
    var filterForms = document.querySelectorAll('.assoc-portal-calendar-filters');
    filterForms.forEach(function (form) {
        form.addEventListener('change', function (event) {
            if (!event.target || event.target.tagName !== 'SELECT') return;
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        });
    });

    var hasInteractiveEvents = document.querySelector('.assoc-portal-calendar .event-item, .assoc-portal-events-cards .event-item');
    if (!hasInteractiveEvents) return;

    var modal = document.createElement('div');
    modal.id = 'event-modal';
    modal.className = 'event-modal';
    modal.innerHTML = '\
        <div class="event-modal-content" role="dialog" aria-modal="true" aria-labelledby="event-modal-title">\
            <button type="button" class="event-modal-close" aria-label="Chiudi">&times;</button>\
            <div id="event-modal-body"></div>\
        </div>\
    ';
    document.body.appendChild(modal);

    var modalBody = modal.querySelector('#event-modal-body');
    var closeButton = modal.querySelector('.event-modal-close');

    function closeModal() {
        modal.style.display = 'none';
        document.body.classList.remove('event-modal-open');
    }

    function openModal() {
        modal.style.display = 'flex';
        document.body.classList.add('event-modal-open');
    }

    function bindZoomControls() {
        var zoomRoot = modalBody.querySelector('.event-modal-image-zoom');
        if (!zoomRoot) return;

        var image = zoomRoot.querySelector('.event-modal-image');
        var frame = zoomRoot.querySelector('.event-modal-image-frame');
        var readout = zoomRoot.querySelector('.event-modal-zoom-readout');
        if (!image || !frame) return;

        var minZoom = 0.6;
        var maxZoom = 3;
        var step = 0.2;
        var zoom = 1;

        function clamp(value) {
            return Math.max(minZoom, Math.min(maxZoom, value));
        }

        function updateZoom() {
            image.style.transform = 'scale(' + zoom.toFixed(2) + ')';
            zoomRoot.setAttribute('data-scale', zoom.toFixed(2));
            if (readout) {
                readout.textContent = Math.round(zoom * 100) + '%';
            }
        }

        zoomRoot.addEventListener('click', function (event) {
            var btn = event.target.closest('[data-zoom]');
            if (!btn) return;

            var action = btn.getAttribute('data-zoom');
            if (action === 'in') {
                zoom = clamp(zoom + step);
            } else if (action === 'out') {
                zoom = clamp(zoom - step);
            } else if (action === 'reset') {
                zoom = 1;
            }
            updateZoom();
        });

        frame.addEventListener('wheel', function (event) {
            if (!event.ctrlKey && !event.metaKey) return;
            event.preventDefault();
            zoom = clamp(zoom + (event.deltaY < 0 ? step : -step));
            updateZoom();
        }, { passive: false });

        updateZoom();
    }

    function renderEventHtml(data) {
        var html = '';

        if (data.image) {
            html += '<div class="event-modal-image-zoom" data-scale="1">';
            html += '<div class="event-modal-image-toolbar">';
            html += '<button type="button" class="event-modal-zoom-btn" data-zoom="out" aria-label="Riduci">−</button>';
            html += '<button type="button" class="event-modal-zoom-btn event-modal-zoom-readout" data-zoom="reset" aria-label="Reimposta zoom">100%</button>';
            html += '<button type="button" class="event-modal-zoom-btn" data-zoom="in" aria-label="Ingrandisci">+</button>';
            html += '</div>';
            html += '<div class="event-modal-image-frame">';
            html += '<img src="' + data.image + '" alt="' + (data.title || 'Evento') + '" class="event-modal-image" loading="lazy">';
            html += '</div>';
            html += '</div>';
        }

        html += '<h2 class="event-modal-title" id="event-modal-title">' + (data.title || '') + '</h2>';
        html += '<div class="event-modal-details">';
        if (data.startDate) html += '<p><strong>Inizio:</strong> ' + data.startDate + '</p>';
        if (data.endDate) html += '<p><strong>Fine:</strong> ' + data.endDate + '</p>';
        if (data.venue) html += '<p><strong>Sede:</strong> ' + data.venue + '</p>';
        if (data.address) html += '<p><strong>Indirizzo:</strong> ' + data.address + '</p>';
        html += '</div>';

        if (data.description) {
            html += '<div class="event-modal-description">' + data.description + '</div>';
        }

        if (data.registrationUrl) {
            html += '<a href="' + data.registrationUrl + '" class="event-modal-button" target="_blank" rel="noopener noreferrer">Iscriviti</a>';
        }

        return html;
    }

    function openEventItem(eventItem) {
        if (!eventItem) return false;
        var data = eventItem.dataset || {};
        modalBody.innerHTML = renderEventHtml(data);
        bindZoomControls();
        openModal();
        return true;
    }

    function readRequestedEventId() {
        var raw = '';
        if (typeof URLSearchParams !== 'undefined') {
            raw = new URLSearchParams(window.location.search).get('ev_event_id') || '';
        } else {
            var match = window.location.search.match(/[?&]ev_event_id=([^&]+)/);
            raw = match ? decodeURIComponent(match[1]) : '';
        }
        raw = String(raw || '').replace(/[^\d]/g, '');
        return raw ? parseInt(raw, 10) : 0;
    }

    function autoOpenRequestedEvent() {
        var requestedId = readRequestedEventId();
        if (!requestedId || requestedId < 1) return;

        var selector = '.assoc-portal-calendar .event-item[data-event-id="' + requestedId + '"], .assoc-portal-events-cards .event-item[data-event-id="' + requestedId + '"]';
        var targetItem = document.querySelector(selector);
        if (!targetItem) return;
        openEventItem(targetItem);
    }

    document.addEventListener('click', function (event) {
        var eventItem = event.target.closest('.event-item');
        if (!eventItem) return;

        if (!eventItem.closest('.assoc-portal-calendar') && !eventItem.closest('.assoc-portal-events-cards')) {
            return;
        }

        event.preventDefault();
        openEventItem(eventItem);
    });

    closeButton.addEventListener('click', closeModal);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) closeModal();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.style.display !== 'none') {
            closeModal();
        }
    });

    autoOpenRequestedEvent();
});
