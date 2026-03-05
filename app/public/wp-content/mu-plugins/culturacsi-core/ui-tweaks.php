<?php
/**
 * UI Tweaks — consolidated from ui-fixes and culturacsi-ui-tweaks plugins.
 * Absorbed into culturacsi-core to reduce plugin count.
 */

if ( ! defined( 'ABSPATH' ) ) exit;



// =========================================================================
// 1. Frontend CSS — hero overlays, full-height carousel arrows
// =========================================================================
add_action( 'wp_enqueue_scripts', static function () {
	$handle = null;
	foreach ( [ 'kadence-blocks', 'kadence', 'style', 'global-styles' ] as $h ) {
		if ( wp_style_is( $h, 'enqueued' ) || wp_style_is( $h, 'registered' ) ) {
			$handle = $h;
			break;
		}
	}
	if ( ! $handle ) {
		$handle = 'culturacsi-ui-tweaks';
		wp_register_style( $handle, false );
		wp_enqueue_style( $handle );
	}

	$css = '
/* =========================================================
   culturacsi-core / ui-tweaks — hero overlays + arrows
   ========================================================= */

/* 1) Full-height side arrow panels (add class "fullheight-arrows" to wrapper) */
.fullheight-arrows { position: relative; }

.fullheight-arrows .splide,
.fullheight-arrows .kt-post-grid-layout-carousel,
.fullheight-arrows .kt-post-grid-layout-carousel-wrap { position: relative !important; }

.fullheight-arrows .slick-prev,
.fullheight-arrows .slick-next,
.fullheight-arrows .kb-slider-arrow-prev,
.fullheight-arrows .kb-slider-arrow-next,
.fullheight-arrows .kb-slider-arrow,
.fullheight-arrows .splide__arrow,
.fullheight-arrows .splide__arrow--prev,
.fullheight-arrows .splide__arrow--next {
	top: 0 !important; bottom: 0 !important; height: auto !important;
	transform: none !important; margin-top: 0 !important;
	display: flex !important; align-items: center !important; justify-content: center !important;
	width: 30px !important; border-radius: 0 !important;
	background: rgba(0,72,150,0.55) !important; z-index: 50 !important;
}
.fullheight-arrows .splide__arrows {
	position: absolute !important; top: 0 !important; right: 0 !important;
	bottom: 0 !important; left: 0 !important;
	pointer-events: none !important; z-index: 50 !important;
}
.fullheight-arrows .splide__arrow { pointer-events: auto !important; }
.fullheight-arrows .splide__arrow--prev { left: 0 !important; }
.fullheight-arrows .splide__arrow--next { right: 0 !important; }
.fullheight-arrows .kb-slider-arrow-wrap,
.fullheight-arrows .kb-gallery-slider-nav,
.fullheight-arrows .slick-arrow { top: 0 !important; bottom: 0 !important; }
.fullheight-arrows .slick-prev:before,
.fullheight-arrows .slick-next:before { font-size: 36px !important; line-height: 1 !important; }
.fullheight-arrows .kb-slider-arrow-prev svg,
.fullheight-arrows .kb-slider-arrow-next svg,
.fullheight-arrows .kb-slider-arrow svg,
.fullheight-arrows .splide__arrow svg { width: 36px !important; height: 36px !important; }

/* 2) Hero overlay (add class "hero-overlay" to the block wrapper) */
.hero-overlay .kadence-post-grid-item,
.hero-overlay .kadence-post-grid-item-inner,
.hero-overlay .kadence-post-grid-item-wrap,
.hero-overlay .kb-post-grid-item,
.hero-overlay .kb-post-grid-item-inner { position: relative !important; overflow: hidden !important; background: transparent !important; }
.hero-overlay img { display: block !important; }
.hero-overlay .kt-image-ratio-56-25,
.hero-overlay .kadence-post-image-intrisic.kt-image-ratio-56-25,
.hero-overlay .kadence-post-image-intrisic,
.hero-overlay .kadence-post-image-inner-intrisic,
.hero-overlay .kadence-post-image-inner-intrisic img,
.hero-overlay .kadence-post-image img { width: 100% !important; }
.hero-overlay .kadence-post-image-inner-intrisic { position: absolute !important; inset: 0 !important; }
.hero-overlay .kadence-post-image-inner-intrisic img,
.hero-overlay .kadence-post-image img { height: 100% !important; object-fit: cover !important; object-position: center center !important; }
.hero-overlay .kadence-post-content,
.hero-overlay .kadence-post-grid-content,
.hero-overlay .kadence-post-text,
.hero-overlay .kb-post-grid-text,
.hero-overlay .kb-post-grid-content,
.hero-overlay .kb-post-grid-inner .entry-content {
	position: absolute !important; left: 0 !important; right: 0 !important; bottom: 0 !important;
	z-index: 30 !important; padding: 18px 20px !important; margin: 0 !important;
	background: linear-gradient(to top, rgba(0,0,0,0.82) 0%, rgba(0,0,0,0.65) 35%, rgba(0,0,0,0.35) 65%, rgba(0,0,0,0) 100%) !important;
	color: #fff !important;
}
.hero-overlay .kadence-post-title,
.hero-overlay .kadence-post-grid-title,
.hero-overlay .kb-post-grid-title,
.hero-overlay .kadence-post-excerpt,
.hero-overlay .kadence-post-grid-excerpt,
.hero-overlay .kb-post-grid-excerpt,
.hero-overlay .kadence-post-meta,
.hero-overlay .kb-post-grid-meta,
.hero-overlay p, .hero-overlay span, .hero-overlay small { color: #fff !important; text-shadow: 0 1px 2px rgba(0,0,0,0.55); }
.hero-overlay a, .hero-overlay a:visited, .hero-overlay a:hover, .hero-overlay a:active {
	color: #fff !important; text-decoration: none !important; text-shadow: 0 1px 2px rgba(0,0,0,0.55);
}
';

	wp_add_inline_style( $handle, $css );
}, 50 );

// =========================================================================
// 2. JS nav fix — remove spurious "Progetti" active state on /chi-siamo
//    Runs at priority 99 so it fires AFTER Kadence block nav has initialized.
// =========================================================================
add_action( 'wp_head', static function () {
	?>
	<script>
	(function() {
		function fixProgettiNav() {
			if (window.location.href.indexOf('/chi-siamo') === -1) return;
			// Classic wp_nav_menu li elements
			document.querySelectorAll('li.menu-item, li.wp-block-kadence-navigation-link').forEach(function(li) {
				var link = li.querySelector('a');
				if (!link) return;
				var text = link.textContent.trim();
				if (text === 'Progetti' || text.startsWith('Progetti')) {
					li.classList.remove(
						'current-menu-item', 'current-menu-ancestor',
						'current-menu-parent', 'current_page_parent',
						'current_page_ancestor', 'kb-link-current'
					);
					link.removeAttribute('aria-current');
				}
			});
		}
		// window.load fires after ALL scripts (including Kadence nav block JS) have run
		window.addEventListener('load', fixProgettiNav);
		// Fallback in case load already fired
		if (document.readyState === 'complete') fixProgettiNav();
	})();
	</script>
	<?php
}, 99 );

// =========================================================================
// 3. Frontend JS — calendar duplicate month text fix
// =========================================================================
add_action( 'wp_head', static function () {
	?>
	<script>
	document.addEventListener('DOMContentLoaded', function() {
		setTimeout(function() {
			var overlay = document.querySelector('.calendar-hero-month-overlay');
			if (overlay) {
				var parent = overlay.parentElement;
				if (parent) {
					Array.from(parent.querySelectorAll('h1,h2,h3,h4,h5,h6,p,span')).filter(function(el) {
						return !el.classList.contains('calendar-hero-month-overlay') &&
						       !el.closest('.calendar-hero-month-overlay') &&
						       el.textContent.trim().toUpperCase() === el.textContent.trim().toLocaleUpperCase('it');
					}).forEach(function(el) { el.style.display = 'none'; });
					Array.from(parent.childNodes).forEach(function(node) {
						if (node.nodeType === Node.TEXT_NODE && node.textContent.trim().length > 0
						    && node.textContent.trim() === node.textContent.trim().toUpperCase()) {
							node.textContent = '';
						}
					});
				}
			}
		}, 500);
	});
	</script>
	<?php
}, 99 );

// =========================================================================
// 4. Contatti page — collapse/expand "DOVE SIAMO" map on icon/label click
// =========================================================================
if ( ! function_exists( 'culturacsi_ui_is_contatti_request' ) ) {
	function culturacsi_ui_is_contatti_request(): bool {
		if ( is_admin() ) {
			return false;
		}
		$path = trim( strtolower( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ), '/' );
		return (bool) preg_match( '~(^|/)contatti/?$~', $path );
	}
}

add_action( 'wp_head', static function () {
	?>
	<style id="culturacsi-contatti-map-toggle-css">
		.csi-map-collapsible {
			max-height: 0 !important;
			opacity: 0;
			overflow: hidden;
			pointer-events: none;
			transform: translateY(-10px);
			transition: max-height .35s ease, opacity .25s ease, transform .25s ease;
		}
		.csi-map-collapsible.is-open {
			max-height: 1200px !important;
			opacity: 1;
			pointer-events: auto;
			transform: translateY(0);
		}
		.csi-map-toggle-target {
			cursor: pointer;
			user-select: none;
		}
		.csi-map-toggle-target:focus-visible {
			outline: 2px solid #1e5bb0;
			outline-offset: 3px;
			border-radius: 4px;
		}
		.csi-map-toggle-arrow {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			margin-left: .4rem;
			color: #1e5bb0;
			font-size: .95em;
			line-height: 1;
			transition: transform .2s ease;
		}
		.csi-map-toggle-target.is-open .csi-map-toggle-arrow {
			transform: rotate(180deg);
		}
	</style>
	<?php
}, 100 );

add_action( 'wp_footer', static function () {
	?>
	<script id="culturacsi-contatti-map-toggle-js">
	(function() {
		function normalizeText(s) {
			return (s || '').replace(/\s+/g, ' ').trim().toUpperCase();
		}

		function initContattiMapToggle() {
			var mapBlocks = Array.prototype.slice.call(document.querySelectorAll('#mapa, .mapa'));
			if (!mapBlocks.length) return;

			mapBlocks.forEach(function(mapBlock, idx) {
				if (!mapBlock || mapBlock.dataset.csiMapInit === '1') return;
				mapBlock.dataset.csiMapInit = '1';

				if (!mapBlock.id) {
					mapBlock.id = 'csi-contatti-map-' + (idx + 1);
				}

				mapBlock.classList.add('csi-map-collapsible');
				mapBlock.classList.remove('is-open');

				var scope = mapBlock.closest('.kt-inside-inner-col') || mapBlock.parentElement || document;

				var iconGlyph = scope.querySelector('.kb-svg-icon-fas_map-marker-alt');
				var iconTrigger = iconGlyph ? (iconGlyph.closest('.wp-block-kadence-icon') || iconGlyph.closest('.wp-block-kadence-single-icon') || iconGlyph.closest('.kb-svg-icon-wrap') || iconGlyph) : null;

				var labelTrigger = null;
				var strongNodes = scope.querySelectorAll('p strong');
				for (var i = 0; i < strongNodes.length; i++) {
					if (normalizeText(strongNodes[i].textContent).indexOf('ACSI NAZIONALE') !== -1) {
						labelTrigger = strongNodes[i];
						break;
					}
				}
				if (!labelTrigger) {
					var paragraphs = scope.querySelectorAll('p');
					for (var j = 0; j < paragraphs.length; j++) {
						if (normalizeText(paragraphs[j].textContent).indexOf('ACSI NAZIONALE') !== -1) {
							labelTrigger = paragraphs[j];
							break;
						}
					}
				}

				var triggers = [];
				if (iconTrigger) triggers.push(iconTrigger);
				if (labelTrigger && triggers.indexOf(labelTrigger) === -1) triggers.push(labelTrigger);
				if (!triggers.length) return;

				var arrowHost = labelTrigger || iconTrigger;
				if (arrowHost && !arrowHost.querySelector('.csi-map-toggle-arrow')) {
					var arrow = document.createElement('span');
					arrow.className = 'csi-map-toggle-arrow';
					arrow.setAttribute('aria-hidden', 'true');
					arrow.textContent = '▾';
					arrowHost.appendChild(arrow);
				}

				function setOpen(open) {
					mapBlock.classList.toggle('is-open', open);
					triggers.forEach(function(trigger) {
						trigger.classList.toggle('is-open', open);
						trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
					});
				}

				function toggleMap(event) {
					if (event) event.preventDefault();
					setOpen(!mapBlock.classList.contains('is-open'));
				}

				triggers.forEach(function(trigger) {
					trigger.classList.add('csi-map-toggle-target');
					trigger.setAttribute('role', 'button');
					trigger.setAttribute('tabindex', '0');
					trigger.setAttribute('aria-controls', mapBlock.id);
					trigger.setAttribute('aria-expanded', 'false');

					if (trigger.dataset.csiMapBound === '1') return;
					trigger.dataset.csiMapBound = '1';
					trigger.addEventListener('click', toggleMap);
					trigger.addEventListener('keydown', function(event) {
						if (event.key === 'Enter' || event.key === ' ' || event.code === 'Space') {
							toggleMap(event);
						}
					});
				});

				setOpen(false);
			});
		}

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', initContattiMapToggle);
		} else {
			initContattiMapToggle();
		}
	})();
	</script>
	<?php
}, 100 );

// =========================================================================
// 5. Nav active-state fix temporarily disabled (stability rollback)
// =========================================================================

// =========================================================================
// 5. Video Modal (5xmille) — plays a video when element with class 5xmille is clicked
// =========================================================================
add_action( 'wp_footer', static function () {
	/**
	 * Configuration: Change these values to control the video and modal appearance.
	 */
	$video_url = 'http://localhost:10010/wp-content/uploads/2026/03/VIDEO%205xMille.mp4'; // Full URL or local uploads path.
	$bg_alpha  = 0.6; // Background opacity (0.0 to 1.0)

	// Accept either web URLs or local filesystem paths under wp-content/uploads.
	$video_src = trim( (string) $video_url );
	if ( preg_match( '#^https?://#i', $video_src ) ) {
		$video_url_for_js = esc_url_raw( $video_src );
	} else {
		$normalized = str_replace( '\\', '/', $video_src );
		$marker_pos = strpos( strtolower( $normalized ), '/wp-content/uploads/' );

		if ( false !== $marker_pos ) {
			$relative     = ltrim( substr( $normalized, $marker_pos ), '/' );
			$segments     = array_map( 'rawurlencode', explode( '/', $relative ) );
			$video_url_for_js = home_url( '/' . implode( '/', $segments ) );
		} else {
			$video_url_for_js = '';
		}
	}

	?>
	<style id="culturacsi-5xmille-modal-css">
		.csi-video-modal {
			position: fixed;
			top: 5%;
			left: 0;
			width: 100%;
			height: 90%;
			background: rgba(0, 0, 0, <?php echo esc_attr( (string) $bg_alpha ); ?>);
			display: none;
			align-items: center;
			justify-content: center;
			z-index: 999999;
			opacity: 0;
			transition: opacity 0.3s ease;
		}
		.csi-video-modal.is-visible {
			display: flex;
			opacity: 1;
		}
		.csi-video-modal-container {
			position: relative;
			width: 90%;
			max-width: 1000px;
			aspect-ratio: 16 / 9;
			overflow: visible;
		}
		.csi-video-modal-player {
			width: 100%;
			height: 100%;
			background: #000;
			box-shadow: 0 20px 50px rgba(0,0,0,0.5);
			border-radius: 8px;
			overflow: hidden;
		}
		.csi-video-modal-player iframe,
		.csi-video-modal-player video {
			width: 100%;
			height: 100%;
			border: none;
		}
		.csi-video-modal-close {
			position: absolute;
			top: -46px;
			right: 0;
			width: 36px;
			height: 36px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			border: 0;
			border-radius: 999px;
			background: #fff;
			color: #000;
			font-size: 28px;
			cursor: pointer;
			line-height: 1;
			transition: transform 0.2s;
			z-index: 2;
		}
		.csi-video-modal-close:hover {
			transform: scale(1.1);
		}
		body.csi-modal-open {
			overflow: hidden;
		}
	</style>

	<div id="csi-5xmille-modal" class="csi-video-modal" aria-hidden="true" role="dialog">
		<div class="csi-video-modal-container">
			<button type="button" class="csi-video-modal-close" id="csi-5xmille-close" aria-label="Close video modal">&times;</button>
			<div class="csi-video-modal-player">
				<div id="csi-5xmille-video-wrapper" style="width:100%; height:100%;">
					<!-- Video injected via JS -->
				</div>
			</div>
		</div>
	</div>

	<script id="culturacsi-5xmille-modal-js">
		(function() {
			const videoUrl = "<?php echo esc_url( $video_url_for_js ); ?>";
			const modal = document.getElementById('csi-5xmille-modal');
			const wrapper = document.getElementById('csi-5xmille-video-wrapper');
			const closeBtn = document.getElementById('csi-5xmille-close');

			function openModal(e) {
				if (e) e.preventDefault();
				if (!videoUrl) return;
				
				// Determine if it's YouTube/Vimeo or direct file
				let html = '';
				if (videoUrl.includes('youtube.com') || videoUrl.includes('youtu.be') || videoUrl.includes('vimeo.com')) {
					let embedUrl = videoUrl;
					if (videoUrl.includes('youtube.com/watch?v=')) {
						embedUrl = videoUrl.replace('watch?v=', 'embed/') + '?autoplay=1';
					} else if (videoUrl.includes('youtu.be/')) {
						embedUrl = videoUrl.replace('youtu.be/', 'youtube.com/embed/') + '?autoplay=1';
					}
					html = `<iframe src="${embedUrl}" allow="autoplay; fullscreen" allowfullscreen></iframe>`;
				} else {
					html = `<video src="${videoUrl}" controls autoplay></video>`;
				}

				wrapper.innerHTML = html;
				modal.classList.add('is-visible');
				modal.setAttribute('aria-hidden', 'false');
				document.body.classList.add('csi-modal-open');
			}

			function closeModal() {
				modal.classList.remove('is-visible');
				modal.setAttribute('aria-hidden', 'true');
				document.body.classList.remove('csi-modal-open');
				// Clear video to stop playback
				setTimeout(() => { wrapper.innerHTML = ''; }, 300);
			}

			document.addEventListener('click', function(e) {
				const trigger = e.target.closest('.\\35 xmille') || e.target.closest('[class*="5xmille"]');
				if (trigger) {
					openModal(e);
				}
			});

			closeBtn.addEventListener('click', closeModal);
			modal.addEventListener('click', function(e) {
				if (e.target === modal) closeModal();
			});

			document.addEventListener('keydown', function(e) {
				if (e.key === 'Escape' && modal.classList.contains('is-visible')) {
					closeModal();
				}
			});
		})();
	</script>
	<?php
}, 110 );

