<?php
/**
 * Plugin Name: CulturACSI UI Tweaks (MU)
 * Description: Forces custom CSS for Kadence hero overlays + full-height carousel arrows.
 */

if (!defined('ABSPATH')) exit;

add_action('wp_enqueue_scripts', function () {

  // Ensure a stylesheet handle exists to attach inline CSS to.
  // Prefer Kadence handles if present; otherwise fall back to theme style.
  $handle = null;
  foreach (['kadence-blocks', 'kadence', 'style', 'global-styles'] as $h) {
    if (wp_style_is($h, 'enqueued') || wp_style_is($h, 'registered')) { $handle = $h; break; }
  }
  if (!$handle) {
    $handle = 'culturacsi-ui-tweaks';
    wp_register_style($handle, false);
    wp_enqueue_style($handle);
  }

  $css = <<<CSS
/* =========================================================
   Kadence tweaks — MU-plugin forced (stable)
   ========================================================= */

/* 1) Full-height side arrow panels
   Apply class: fullheight-arrows to the block/section wrapper */
.fullheight-arrows {
  position: relative;
}

/* Support: Slick/Kadence legacy arrows + Splide (Kadence post carousel) */
.fullheight-arrows .splide,
.fullheight-arrows .kt-post-grid-layout-carousel,
.fullheight-arrows .kt-post-grid-layout-carousel-wrap {
  position: relative !important;
}

.fullheight-arrows .slick-prev,
.fullheight-arrows .slick-next,
.fullheight-arrows .kb-slider-arrow-prev,
.fullheight-arrows .kb-slider-arrow-next,
.fullheight-arrows .kb-slider-arrow,
.fullheight-arrows .splide__arrow,
.fullheight-arrows .splide__arrow--prev,
.fullheight-arrows .splide__arrow--next {
  top: 0 !important;
  bottom: 0 !important;
  height: auto !important;
  transform: none !important;
  margin-top: 0 !important;

  display: flex !important;
  align-items: center !important;
  justify-content: center !important;

  width: 30px !important;
  border-radius: 0 !important;
  background: rgba(0, 72, 150, 0.55) !important;
  z-index: 50 !important;
}

/* Splide arrow container should span full carousel height */
.fullheight-arrows .splide__arrows {
  position: absolute !important;
  top: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  left: 0 !important;
  pointer-events: none !important;
  z-index: 50 !important;
}
.fullheight-arrows .splide__arrow {
  pointer-events: auto !important;
}
.fullheight-arrows .splide__arrow--prev {
  left: 0 !important;
}
.fullheight-arrows .splide__arrow--next {
  right: 0 !important;
}

/* Sometimes Kadence wraps arrow button; give full height to wrappers too */
.fullheight-arrows .kb-slider-arrow-wrap,
.fullheight-arrows .kb-gallery-slider-nav,
.fullheight-arrows .slick-arrow {
  top: 0 !important;
  bottom: 0 !important;
}

/* Icon sizing */
.fullheight-arrows .slick-prev:before,
.fullheight-arrows .slick-next:before {
  font-size: 36px !important;
  line-height: 1 !important;
}
.fullheight-arrows .kb-slider-arrow-prev svg,
.fullheight-arrows .kb-slider-arrow-next svg,
.fullheight-arrows .kb-slider-arrow svg,
.fullheight-arrows .splide__arrow svg {
  width: 36px !important;
  height: 36px !important;
}

/* 2) HERO overlay (hardened selectors)
   Apply class: hero-overlay to the HERO grid/carousel block wrapper */
.hero-overlay .kadence-post-grid-item,
.hero-overlay .kadence-post-grid-item-inner,
.hero-overlay .kadence-post-grid-item-wrap,
.hero-overlay .kb-post-grid-item,
.hero-overlay .kb-post-grid-item-inner {
  position: relative !important;
  overflow: hidden !important;
  background: transparent !important;
}

/* Ensure media fills (covers Kadence ratio wrappers) */
.hero-overlay img {
  display: block !important;
}

/* Kadence image ratio wrappers (cover) */
.hero-overlay .kt-image-ratio-56-25,
.hero-overlay .kadence-post-image-intrisic.kt-image-ratio-56-25,
.hero-overlay .kadence-post-image-intrisic,
.hero-overlay .kadence-post-image-inner-intrisic,
.hero-overlay .kadence-post-image-inner-intrisic img,
.hero-overlay .kadence-post-image img {
  width: 100% !important;
}

.hero-overlay .kadence-post-image-inner-intrisic {
  position: absolute !important;
  inset: 0 !important;
}
.hero-overlay .kadence-post-image-inner-intrisic img,
.hero-overlay .kadence-post-image img {
  height: 100% !important;
  object-fit: cover !important;
  object-position: center center !important;
}

/* Overlay block: target known text containers across Kadence variants */
.hero-overlay .kadence-post-content,
.hero-overlay .kadence-post-grid-content,
.hero-overlay .kadence-post-text,
.hero-overlay .kb-post-grid-text,
.hero-overlay .kb-post-grid-content,
.hero-overlay .kb-post-grid-inner .entry-content {
  position: absolute !important;
  left: 0 !important;
  right: 0 !important;
  bottom: 0 !important;
  z-index: 30 !important;

  padding: 18px 20px !important;
  margin: 0 !important;

  background: linear-gradient(
    to top,
    rgba(0,0,0,0.82) 0%,
    rgba(0,0,0,0.65) 35%,
    rgba(0,0,0,0.35) 65%,
    rgba(0,0,0,0.00) 100%
  ) !important;

  color: #fff !important;
}

/* Contrast */
.hero-overlay .kadence-post-title,
.hero-overlay .kadence-post-grid-title,
.hero-overlay .kb-post-grid-title,
.hero-overlay .kadence-post-excerpt,
.hero-overlay .kadence-post-grid-excerpt,
.hero-overlay .kb-post-grid-excerpt,
.hero-overlay .kadence-post-meta,
.hero-overlay .kb-post-grid-meta,
.hero-overlay p,
.hero-overlay span,
.hero-overlay small {
  color: #fff !important;
  text-shadow: 0 1px 2px rgba(0,0,0,0.55);
}

.hero-overlay a,
.hero-overlay a:visited,
.hero-overlay a:hover,
.hero-overlay a:active {
  color: #fff !important;
  text-decoration: none !important;
  text-shadow: 0 1px 2px rgba(0,0,0,0.55);
}

CSS;

  wp_add_inline_style($handle, $css);
}, 50);
