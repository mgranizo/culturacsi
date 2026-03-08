(function () {
    'use strict';

    /**
     * Initializes the modal functionality for the Content Hub.
     * Attaches click event listeners to the body to handle dynamically generated triggers.
     */
    function initContentHubModals() {
        document.body.addEventListener('click', function (e) {
            // Find the closest anchor tag that matches the modal post hash pattern
            let trigger = e.target.closest('a[href*="#csi-modal-post-"]');
            let isAjax = true;
            let postId = 0;

            if (!trigger) {
                // Fallback for explicitly defined triggers (like shortcodes) without a hash
                trigger = e.target.closest('[data-csi-modal-trigger="library"]');
                isAjax = false;
            } else {
                // Extract the post ID from the href hash
                const href = trigger.getAttribute('href');
                const match = href.match(/#csi-modal-post-(\d+)/);
                if (match && match[1]) {
                    postId = parseInt(match[1], 10);
                } else {
                    return; // Not a valid hash, ignore the click
                }
            }

            if (!trigger) return;

            e.preventDefault();
            e.stopPropagation();

            // Ensure the modal DOM element exists
            let modal = document.getElementById('csi-library-modal');
            if (!modal) {
                createModal();
                modal = document.getElementById('csi-library-modal');
            }

            if (isAjax && postId > 0) {
                // Fetch library modal content via AJAX using the post ID
                openModalLoading(modal);
                fetchModalData(modal, postId);
            } else {
                // Parse modal data embedded in the data-csi-modal-content attribute
                try {
                    const data = JSON.parse(trigger.getAttribute('data-csi-modal-content') || '{}');
                    openModal(modal, data);
                } catch (err) {
                    console.error('CulturaCSI: Error parsing modal data', err);
                }
            }
        });
    }

    /**
     * Fetches modal data from the server via AJAX for a specific post.
     * 
     * @param {HTMLElement} modal - The modal container element.
     * @param {number} postId - The ID of the post to fetch data for.
     */
    function fetchModalData(modal, postId) {
        // Retrieve localized configuration or fallback to an empty object
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
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(res => {
                if (res && res.success && res.data) {
                    // Populate and display the modal with the received data
                    openModal(modal, res.data);
                    return;
                } else {
                    // Display an error message from the server or a default one
                    const bodyContent = modal.querySelector('#csi-library-modal-body');
                    if (bodyContent) {
                        const serverMessage = (res && res.data && res.data.message) ? res.data.message : 'Impossibile caricare i dati del documento.';
                        bodyContent.innerHTML = '<h2 class="csi-library-modal-title">Errore</h2><div class="csi-library-modal-description">' + serverMessage + '</div>';
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

    /**
     * Creates and injects the modal DOM structure into the body.
     * Attaches close events for the button, background click, and Escape key.
     */
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

        // Close logic handles removing visibility classes
        const close = () => {
            modal.classList.remove('is-open');
            document.body.classList.remove('csi-library-modal-open');
        };

        if (closeBtn) closeBtn.addEventListener('click', close);

        // Close the modal when clicking outside the content area
        modal.addEventListener('click', function (e) {
            if (e.target === modal) close();
        });

        // Close the modal when pressing the Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.classList.contains('is-open')) {
                close();
            }
        });
    }

    /**
     * Initializes zoom controls for modal images (if present).
     * Binds click events for zoom buttons and scroll wheel for zooming.
     * 
     * @param {HTMLElement} modalBody - The body container of the modal.
     */
    function bindZoomControls(modalBody) {
        var zoomRoot = modalBody.querySelector('.csi-library-modal-image-zoom');
        if (!zoomRoot) return;

        var image = zoomRoot.querySelector('.csi-library-modal-image');
        var frame = zoomRoot.querySelector('.csi-library-modal-image-frame');
        var readout = zoomRoot.querySelector('.csi-library-modal-zoom-readout');
        if (!image || !frame) return;

        // Zoom configuration
        var minZoom = 0.6;
        var maxZoom = 3;
        var step = 0.2;
        var zoom = 1;

        // Constrains the zoom value between min and max bounds
        function clamp(value) {
            return Math.max(minZoom, Math.min(maxZoom, value));
        }

        // Applies the current zoom level to the image transform and updates readout
        function updateZoom() {
            image.style.transform = 'scale(' + zoom.toFixed(2) + ')';
            zoomRoot.setAttribute('data-scale', zoom.toFixed(2));
            if (readout) {
                readout.textContent = Math.round(zoom * 100) + '%';
            }
        }

        // Handle clicks on zoom action buttons
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

        // Handle mouse wheel zoom within the image frame (when ctrl/meta key is held)
        frame.addEventListener('wheel', function (event) {
            if (!event.ctrlKey && !event.metaKey) return;
            event.preventDefault(); // Prevent standard page scroll
            // Determine zoom direction based on scroll delta
            zoom = clamp(zoom + (event.deltaY < 0 ? step : -step));
            updateZoom();
        }, { passive: false });

        // Initialize default zoom
        updateZoom();
    }

    /**
     * Updates the modal to a loading state.
     * 
     * @param {HTMLElement} modal - The modal container element.
     */
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

    /**
     * Parses payload data and constructs the modal's internal HTML representation.
     * Evaluates which CTA buttons to render based on payload links.
     * 
     * @param {HTMLElement} modal - The modal container element.
     * @param {Object} data - Modal payload data containing title, excerpt, content, urls, etc.
     */
    function openModal(modal, data) {
        const bodyContent = modal.querySelector('#csi-library-modal-body');
        if (!bodyContent) return;

        let html = '';

        // Add image with zoom frame if imageUrl is present
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

        // Modal Title
        html += '<h2 class="csi-library-modal-title" id="csi-library-modal-title">' + (data.title || '') + '</h2>';

        // Modal Excerpt/Details
        if (data.excerpt) {
            html += '<div class="csi-library-modal-details">' + data.excerpt + '</div>';
        }

        // Modal Full Content
        if (data.content) {
            html += '<div class="csi-library-modal-description">' + data.content + '</div>';
        }

        // Determine available links and their corresponding labels
        const hasFile = !!(data.fileUrl && String(data.fileUrl).trim() !== '');
        const hasExternal = !!(data.externalUrl && String(data.externalUrl).trim() !== '');
        const selectedLabel = (data.buttonLabel || '').trim();
        const externalLabel = (selectedLabel === 'Acquista' || selectedLabel === 'Visita') ? selectedLabel : 'Visita';
        const fileLabel = data.fileNote ? ('Scarica (' + data.fileNote + ')') : 'Scarica';

        // CTA Rendering Rules:
        // 1) if both resources exist, show both buttons. Order depends on the selected label.
        // 2) if only one exists, render the appropriate button.
        if (hasFile && hasExternal) {
            if (selectedLabel === 'Acquista' || selectedLabel === 'Visita') {
                html += '<a href="' + data.externalUrl + '" class="csi-library-modal-button" target="_blank" rel="noopener noreferrer">' + externalLabel + '</a>';
                html += '<a href="' + data.fileUrl + '" class="csi-library-modal-button" download>' + fileLabel + '</a>';
            } else {
                html += '<a href="' + data.fileUrl + '" class="csi-library-modal-button" download>' + fileLabel + '</a>';
                html += '<a href="' + data.externalUrl + '" class="csi-library-modal-button" target="_blank" rel="noopener noreferrer">' + externalLabel + '</a>';
            }
        } else if (hasFile && selectedLabel === 'Scarica') {
            html += '<a href="' + data.fileUrl + '" class="csi-library-modal-button" download>' + fileLabel + '</a>';
        } else if (hasExternal && (selectedLabel === 'Acquista' || selectedLabel === 'Visita')) {
            html += '<a href="' + data.externalUrl + '" class="csi-library-modal-button" target="_blank" rel="noopener noreferrer">' + externalLabel + '</a>';
        } else if (hasFile) {
            html += '<a href="' + data.fileUrl + '" class="csi-library-modal-button" download>' + fileLabel + '</a>';
        } else if (hasExternal) {
            html += '<a href="' + data.externalUrl + '" class="csi-library-modal-button" target="_blank" rel="noopener noreferrer">' + externalLabel + '</a>';
        }

        // Update the modal's inner content
        bodyContent.innerHTML = html;

        // Try to bind zoom controls to newly injected image elements
        bindZoomControls(bodyContent);

        // Open and transition the modal into view
        modal.classList.add('is-open');
        document.body.classList.add('csi-library-modal-open');
    }

    // Ensure initialization only happens when the DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initContentHubModals);
    } else {
        initContentHubModals();
    }
})();
