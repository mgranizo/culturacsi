<?php
/**
 * Performance Hints
 *
 * Adds resource hints (preload) for critical above-the-fold assets to
 * reduce perceived load time. Specifically targets the hero slider's
 * first image on the homepage, which is the largest LCP candidate.
 *
 * Why this matters:
 * - The Kadence slider uses <img src="..."> with no loading="lazy" attribute,
 *   meaning all 47 images start loading immediately. The first slide (Accademie-1.jpg)
 *   is the LCP element but the browser only discovers it mid-parse, after CSS/JS.
 * - A <link rel="preload"> in <head> tells the browser to fetch it right away,
 *   before anything else — shaving 200–800ms off the perceived hero appearance.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Inject a <link rel="preload"> for the hero slider's first image.
 * Only runs on the front page (homepage) since that's where the
 * un-cached slider is the primary LCP bottleneck.
 */
function culturacsi_preload_hero_first_image(): void {
	// Only on front page (homepage)
	if ( ! is_front_page() ) {
		return;
	}

	// The first slide image in the Kadence hero gallery.
	// This matches what Kadence renders as the first <img src> in the slider.
	// Resolved from the uploads URL — avoids a DB lookup on every request.
	$uploads     = wp_get_upload_dir();
	$base_url    = trailingslashit( (string) ( $uploads['baseurl'] ?? '' ) );

	if ( $base_url === '/' ) {
		return;
	}

	// First slide filename (Accademie-1.jpg). If the slider order changes,
	// update this filename to match the new first slide.
	$first_slide = 'Accademie-1.jpg';

	// Try to find the image in uploads (could be in a year/month subdirectory).
	// We use a transient so the attachment URL lookup only happens once per day.
	$cache_key  = 'culturacsi_hero_preload_url';
	$image_url  = get_transient( $cache_key );

	if ( false === $image_url ) {
		global $wpdb;
		// Find the attachment with this filename in any subdirectory.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
				 WHERE p.post_type = 'attachment'
				   AND p.post_status = 'inherit'
				   AND pm.meta_value LIKE %s
				 ORDER BY p.ID ASC
				 LIMIT 1",
				'%' . $wpdb->esc_like( $first_slide )
			),
			ARRAY_A
		);

		if ( ! empty( $row['ID'] ) ) {
			$url = wp_get_attachment_image_url( (int) $row['ID'], 'full' );
			$image_url = $url ?: '';
		} else {
			$image_url = '';
		}

		// Cache for 24 hours — image URL is stable.
		set_transient( $cache_key, $image_url, DAY_IN_SECONDS );
	}

	if ( empty( $image_url ) ) {
		return;
	}

	// Output the preload link. fetchpriority="high" is the modern equivalent of
	// the old browser hint for critical resources (Chrome 102+, Firefox 113+).
	printf(
		'<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
		esc_url( $image_url )
	);
}
// Priority 1 = very early in <head>, before any theme/plugin styles.
add_action( 'wp_head', 'culturacsi_preload_hero_first_image', 1 );

/**
 * Add a stable min-height to the hero gallery container on the homepage
 * so there is no layout shift (CLS) while the first image loads.
 * The value (480px) matches the typical rendered height of the slider.
 */
function culturacsi_hero_cls_fix(): void {
	if ( ! is_front_page() ) {
		return;
	}
	?>
	<style id="culturacsi-hero-cls">
	/* Prevent layout shift while the hero slider's first image loads */
	.wp-block-kadence-advancedgallery .kb-gallery-type-slider {
		min-height: 480px;
	}
	</style>
	<?php
}
add_action( 'wp_head', 'culturacsi_hero_cls_fix', 2 );
