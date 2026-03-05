(function () {
    'use strict';

    function initContentHubModals() {
        document.body.addEventListener('click', function (e) {
            let trigger = e.target.closest('a[href*="#csi-modal-post-"]');
            let isAjax = true;
            let postId = 0;

            if (!trigger) {
                // Fallback for explicitly defined triggers (like shortcodes)
                trigger = e.target.closest('[data-csi-modal-trigger="library"]');
                isAjax = false;
            } else {
                const href = trigger.getAttribute('href');
                const match = href.match(/#csi-modal-post-(\d+)/);
                if (match && match[1]) {
                    postId = parseInt(match[1], 10);
                } else {
                    return; // Not a valid hash
                }
            }

            if (!trigger) return;

            e.preventDefault();
            e.stopPropagation();

            let modal = document.getElementById('csi-library-modal');
            if (!modal) {
                createModal();
                modal = document.getElementById('csi-library-modal');
            }

            if (isAjax && postId > 0) {
                openModalLoading(modal);
                fetchModalData(modal, postId);
            } else {
                try {
                    const data = JSON.parse(trigger.getAttribute('data-csi-modal-content') || '{}');
                    openModal(modal, data);
                } catch (err) {
                    console.error('CulturaCSI: Error parsing modal data', err);
                }
            }
        });
    }

    function fetchModalData(modal, postId) {
        var cfg = (typeof window.CSIContentHubConfig === 'object' && window.CSIContentHubConfig)
            ? window.CSIContentHubConfig
            : {};

        const formData = new FormData();
        formData.append('action', 'csi_get_library_modal');
        formData.append('post_id', postId);
        formData.append('nonce', cfg.modalNonce || '');

        const ajaxUrl = cfg.ajaxUrl || (window.location.origin + '/wp-admin/admin-ajax.php');

        fetch(ajaxUrl, {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(res => {
                if (res.success && res.data) {
                    openModal(modal, res.data);
                } else {
                    const bodyContent = modal.querySelector('#csi-library-modal-body');
                    if (bodyContent) {
                        bodyContent.innerHTML = '<h2 class="csi-library-modal-title">Errore</h2><div class="csi-library-modal-description">Impossibile caricare i dati del documento.</div>';
                    }
                }
            })
            .catch(err => {
                console.error('AJAX Modal Error', err);
                const bodyContent = modal.querySelector('#csi-library-modal-body');
                if (bodyContent) {
                    bodyContent.innerHTML = '<h2 class="csi-library-modal-title">Errore di connessione</h2><div class="csi-library-modal-description">Verifica la tua connessione e riprova.</div>';
                }
            });
    }

    function createModal() {
        const modal = document.createElement('div');
        modal.id = 'csi-library-modal';
        modal.className = 'csi-library-modal';
        modal.innerHTML = `
            <div class="csi-library-modal-content" role="dialog" aria-modal="true" aria-labelledby="csi-library-modal-title">
                <button type="button" class="csi-library-modal-close" aria-label="Chiudi">&times;</button>
                <div id="csi-library-modal-body"></div>
            </div>
        `;
        document.body.appendChild(modal);

        const closeBtn = modal.querySelector('.csi-library-modal-close');

        const close = () => {
            modal.classList.remove('is-open');
            document.body.classList.remove('csi-library-modal-open');
        };

        if (closeBtn) closeBtn.addEventListener('click', close);

        modal.addEventListener('click', function (e) {
            if (e.target === modal) close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                close();
            }
        });
    }

    function bindZoomControls(modalBody) {
        var zoomRoot = modalBody.querySelector('.csi-library-modal-image-zoom');
        if (!zoomRoot) return;

        var image = zoomRoot.querySelector('.csi-library-modal-image');
        var frame = zoomRoot.querySelector('.csi-library-modal-image-frame');
        var readout = zoomRoot.querySelector('.csi-library-modal-zoom-readout');
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

    function openModalLoading(modal) {
        const bodyContent = modal.querySelector('#csi-library-modal-body');
        if (bodyContent) {
            bodyContent.innerHTML = `
                <h2 class="csi-library-modal-title" id="csi-library-modal-title">Caricamento...</h2>
                <div class="csi-library-modal-description"><div style="text-align: center; color: #64748b;">Recupero dati in corso...</div></div>
            `;
        }
        modal.classList.add('is-open');
        document.body.classList.add('csi-library-modal-open');
    }

    function openModal(modal, data) {
        const bodyContent = modal.querySelector('#csi-library-modal-body');
        if (!bodyContent) return;

        let html = '';

        if (data.imageUrl) {
            html += '<div class="csi-library-modal-image-zoom" data-scale="1">';
            html += '<div class="csi-library-modal-image-toolbar">';
            html += '<button type="button" class="csi-library-modal-zoom-btn" data-zoom="out" aria-label="Riduci">−</button>';
            html += '<button type="button" class="csi-library-modal-zoom-btn csi-library-modal-zoom-readout" data-zoom="reset" aria-label="Reimposta zoom">100%</button>';
            html += '<button type="button" class="csi-library-modal-zoom-btn" data-zoom="in" aria-label="Ingrandisci">+</button>';
            html += '</div>';
            html += '<div class="csi-library-modal-image-frame">';
            html += '<img src="' + data.imageUrl + '" alt="" class="csi-library-modal-image" loading="lazy">';
            html += '</div>';
            html += '</div>';
        }

        html += '<h2 class="csi-library-modal-title" id="csi-library-modal-title">' + (data.title || '') + '</h2>';

        if (data.excerpt) {
            html += '<div class="csi-library-modal-details">' + data.excerpt + '</div>';
        }

        if (data.content) {
            html += '<div class="csi-library-modal-description">' + data.content + '</div>';
        }

        if (data.fileUrl) {
            html += '<a href="' + data.fileUrl + '" class="csi-library-modal-button" download>' + (data.fileNote ? 'Scarica (' + data.fileNote + ')' : 'Scarica Documento') + '</a>';
        }

        bodyContent.innerHTML = html;
        bindZoomControls(bodyContent);

        modal.classList.add('is-open');
        document.body.classList.add('csi-library-modal-open');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initContentHubModals);
    } else {
        initContentHubModals();
    }
})();
