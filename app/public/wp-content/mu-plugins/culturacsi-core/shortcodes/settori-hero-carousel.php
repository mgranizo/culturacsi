<?php
/**
 * Shortcode: [culturacsi_settori_hero_carousel]
 *
 * Renders a dynamic hero carousel whose slides are driven by:
 *   - The authoritative activity tree  (macro categories = one slide each)
 *   - The Settori Immagini admin panel (Tools → Settori Immagini) for images
 *
 * Usage in the block editor: add a Shortcode block and type
 *   [culturacsi_settori_hero_carousel]
 *
 * Optional attributes:
 *   autoplay="true|false"   (default: true)
 *   interval="5000"         milliseconds between slides (default: 5000)
 *   height="480px"          CSS height of the carousel (default: 480px)
 *   show_labels="true|false" show macro label overlay (default: true)
 *
 * @package CulturaCSI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the ordered list of slides from the activity tree + hero image map.
 * Includes all levels: macro categoria, settore, settore 2.
 *
 * @return array<int, array{label: string, url: string, link: string}>
 */
function culturacsi_settori_hero_carousel_slides(): array {
	if ( ! function_exists( 'culturacsi_activity_tree_flat_entries' )
		|| ! function_exists( 'abf_get_hero_image_map' )
		|| ! function_exists( 'culturacsi_activity_tree_entry_link' )
		|| ! function_exists( 'culturacsi_activity_tree_entry_image_url' ) ) {
		return [];
	}

	$entries  = culturacsi_activity_tree_flat_entries();
	$hero_map = abf_get_hero_image_map(); // key => url, already includes overrides

	$slides = [];
	foreach ( $entries as $entry ) {
		$label = trim( (string) ( $entry['label'] ?? '' ) );
		if ( $label === '' ) {
			continue;
		}
		$display_label = function_exists( 'culturacsi_activity_tree_display_label' )
			? culturacsi_activity_tree_display_label( $label )
			: $label;

		$key = trim( (string) ( $entry['key'] ?? '' ) );
		$url = culturacsi_activity_tree_entry_image_url( $entry, $hero_map );

		$link = culturacsi_activity_tree_entry_link( $entry );

		$slides[] = [
			'label' => $display_label,
			'url'   => $url,
			'link'  => $link,
			'key'   => $key,
			'level' => (string) ( $entry['level'] ?? 'macro' ),
		];
	}

	return $slides;
}

/**
 * Render the hero carousel shortcode.
 *
 * @param array<string,string> $atts
 * @return string HTML output.
 */
function culturacsi_settori_hero_carousel_shortcode( array $atts ): string {
	$atts = shortcode_atts(
		[
			'autoplay'    => 'true',
			'interval'    => '5000',
			'height'      => '480px',
			'show_labels' => 'true',
		],
		$atts,
		'culturacsi_settori_hero_carousel'
	);

	$autoplay    = ( strtolower( (string) $atts['autoplay'] ) !== 'false' );
	$interval    = max( 1000, (int) $atts['interval'] );
	$height      = sanitize_text_field( (string) $atts['height'] );
	$show_labels = ( strtolower( (string) $atts['show_labels'] ) !== 'false' );

	$slides = culturacsi_settori_hero_carousel_slides();

	if ( empty( $slides ) ) {
		// Nothing to show — return empty string so the page layout is unaffected.
		return '';
	}

	$uid = 'csc-hero-' . wp_unique_id();

	ob_start();
	?>
	<div
		id="<?php echo esc_attr( $uid ); ?>"
		class="csc-hero-carousel"
		data-autoplay="<?php echo $autoplay ? 'true' : 'false'; ?>"
		data-interval="<?php echo (int) $interval; ?>"
		aria-label="<?php esc_attr_e( 'Settori carousel', 'culturacsi' ); ?>"
		style="position:relative;overflow:hidden;width:100%;height:<?php echo esc_attr( $height ); ?>;background:#1a2a4a;"
	>
		<!-- Slides -->
		<div class="csc-hero-track" style="display:flex;height:100%;transition:transform .5s ease;will-change:transform;">
			<?php foreach ( $slides as $i => $slide ) : ?>
				<div
					class="csc-hero-slide"
					data-hero-key="<?php echo esc_attr( $slide['key'] ); ?>"
					aria-label="<?php echo esc_attr( $slide['label'] ); ?>"
					style="min-width:100%;height:100%;position:relative;overflow:hidden;flex-shrink:0;"
				>
					<?php if ( $slide['url'] !== '' ) : ?>
						<img
							src="<?php echo esc_url( $slide['url'] ); ?>"
							alt="<?php echo esc_attr( $slide['label'] ); ?>"
							loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>"
							style="width:100%;height:100%;object-fit:cover;display:block;"
						>
					<?php else : ?>
						<div style="width:100%;height:100%;background:#2a3f6f;"></div>
					<?php endif; ?>

					<?php if ( $show_labels ) : ?>
						<div class="csc-hero-label" style="position:absolute;bottom:0;left:0;right:0;padding:16px 24px;background:linear-gradient(transparent,rgba(0,0,0,.55));color:#fff;font-size:1.25rem;font-weight:700;text-shadow:0 1px 3px rgba(0,0,0,.6);">
							<a href="<?php echo esc_url( $slide['link'] ); ?>" style="color:inherit;text-decoration:none;">
								<?php echo esc_html( $slide['label'] ); ?>
							</a>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<!-- Prev / Next arrows -->
		<button
			class="csc-hero-prev"
			aria-label="<?php esc_attr_e( 'Precedente', 'culturacsi' ); ?>"
			style="position:absolute;top:50%;left:12px;transform:translateY(-50%);z-index:10;background:rgba(0,0,0,.4);border:none;border-radius:50%;width:44px;height:44px;cursor:pointer;color:#fff;font-size:22px;display:flex;align-items:center;justify-content:center;"
		>&#8249;</button>
		<button
			class="csc-hero-next"
			aria-label="<?php esc_attr_e( 'Successivo', 'culturacsi' ); ?>"
			style="position:absolute;top:50%;right:12px;transform:translateY(-50%);z-index:10;background:rgba(0,0,0,.4);border:none;border-radius:50%;width:44px;height:44px;cursor:pointer;color:#fff;font-size:22px;display:flex;align-items:center;justify-content:center;"
		>&#8250;</button>

		<!-- Dot pagination -->
		<div class="csc-hero-dots" style="position:absolute;bottom:10px;left:50%;transform:translateX(-50%);display:flex;gap:6px;z-index:10;">
			<?php foreach ( $slides as $i => $slide ) : ?>
				<button
					class="csc-hero-dot<?php echo $i === 0 ? ' is-active' : ''; ?>"
					data-index="<?php echo (int) $i; ?>"
					aria-label="<?php printf( esc_attr__( 'Vai alla slide %d', 'culturacsi' ), $i + 1 ); ?>"
					style="width:10px;height:10px;border-radius:50%;border:2px solid #fff;background:<?php echo $i === 0 ? '#fff' : 'transparent'; ?>;cursor:pointer;padding:0;"
				></button>
			<?php endforeach; ?>
		</div>
	</div>

	<script>
	(function () {
		var root = document.getElementById(<?php echo wp_json_encode( $uid ); ?>);
		if (!root) return;

		var track  = root.querySelector('.csc-hero-track');
		var slides = root.querySelectorAll('.csc-hero-slide');
		var dots   = root.querySelectorAll('.csc-hero-dot');
		var prev   = root.querySelector('.csc-hero-prev');
		var next   = root.querySelector('.csc-hero-next');
		var total  = slides.length;
		var current = 0;
		var timer  = null;
		var autoplay = <?php echo $autoplay ? 'true' : 'false'; ?>;
		var interval = <?php echo (int) $interval; ?>;

		function goTo(index) {
			current = ((index % total) + total) % total;
			track.style.transform = 'translateX(-' + (current * 100) + '%)';
			dots.forEach(function (d, i) {
				d.classList.toggle('is-active', i === current);
				d.style.background = i === current ? '#fff' : 'transparent';
			});
		}

		function startAutoplay() {
			if (!autoplay) return;
			timer = setInterval(function () { goTo(current + 1); }, interval);
		}

		function stopAutoplay() {
			if (timer) { clearInterval(timer); timer = null; }
		}

		if (prev) prev.addEventListener('click', function () { stopAutoplay(); goTo(current - 1); startAutoplay(); });
		if (next) next.addEventListener('click', function () { stopAutoplay(); goTo(current + 1); startAutoplay(); });

		dots.forEach(function (d) {
			d.addEventListener('click', function () {
				stopAutoplay();
				goTo(parseInt(d.dataset.index, 10));
				startAutoplay();
			});
		});

		// Pause on hover
		root.addEventListener('mouseenter', stopAutoplay);
		root.addEventListener('mouseleave', startAutoplay);

		// Touch / swipe support
		var touchStartX = 0;
		root.addEventListener('touchstart', function (e) { touchStartX = e.touches[0].clientX; }, { passive: true });
		root.addEventListener('touchend', function (e) {
			var dx = e.changedTouches[0].clientX - touchStartX;
			if (Math.abs(dx) > 40) { stopAutoplay(); goTo(current + (dx < 0 ? 1 : -1)); startAutoplay(); }
		}, { passive: true });

		// Sync with AB_SETTORI_HERO filter selection (hero.js integration):
		// When the settori filter changes, jump to the matching slide.
		document.addEventListener('abf:hero:key', function (e) {
			var key = e && e.detail && typeof e.detail.key === 'string' ? e.detail.key : '';
			if (!key) return;
			for (var i = 0; i < slides.length; i++) {
				if (slides[i].dataset.heroKey === key) {
					stopAutoplay();
					goTo(i);
					startAutoplay();
					break;
				}
			}
		});

		startAutoplay();
	}());
	</script>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'culturacsi_settori_hero_carousel', 'culturacsi_settori_hero_carousel_shortcode' );

/**
 * Update performance-hints.php preload to use the first slide from the
 * dynamic hero map instead of the hardcoded filename.
 *
 * Hooked at priority 5 so it runs before the default preload at priority 1
 * only when the dynamic carousel is present on the page.
 * We override the transient key used by culturacsi_preload_hero_first_image()
 * so it picks up the correct first image.
 */
add_action( 'wp', static function () {
	if ( ! is_front_page() ) {
		return;
	}
	if ( ! function_exists( 'abf_get_hero_image_map' ) ) {
		return;
	}

	// Warm the preload transient with the first macro image from the hero map.
	$cache_key = 'culturacsi_hero_preload_url';
	if ( false !== get_transient( $cache_key ) ) {
		return; // Already set — don't override.
	}

	$slides = culturacsi_settori_hero_carousel_slides();
	if ( empty( $slides ) ) {
		return;
	}

	// Find the first slide that has an image.
	foreach ( $slides as $slide ) {
		if ( $slide['url'] !== '' ) {
			set_transient( $cache_key, $slide['url'], DAY_IN_SECONDS );
			break;
		}
	}
}, 5 );
