<?php
/**
 * Plugin Name: CulturaCSI Pattern Registration
 * Description: Register block patterns for the active theme (works with Kadence theme).
 * Version: 1.0.0
 * Author: CulturaCSI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build the Gutenberg block markup for the dynamic Settori grid pattern.
 * Iterates over the top-level macro categories in the activity tree and
 * creates one column per entry with a matching image from the media library.
 */
function culturacsi_build_settori_pattern_content(): string {
	if ( ! function_exists( 'culturacsi_activity_tree_flat_entries' )
		|| ! function_exists( 'culturacsi_activity_tree_entry_link' )
		|| ! function_exists( 'culturacsi_activity_tree_entry_key' )
		|| ! function_exists( 'culturacsi_activity_tree_entry_image_url' ) ) {
		return '';
	}

	$cache_key = 'culturacsi_settori_pattern_content_v2';
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return (string) $cached;
	}

	$entries = culturacsi_activity_tree_flat_entries();
	if ( empty( $entries ) ) {
		return '';
	}

	// Get hero image map for image URLs
	$hero_map = [];
	if ( function_exists( 'abf_get_hero_image_map' ) ) {
		$hero_map = abf_get_hero_image_map();
	}

	$columns_markup = '';
	foreach ( $entries as $entry ) {
		$label_raw = trim( (string) ( $entry['label'] ?? '' ) );
		if ( '' === $label_raw ) {
			continue;
		}
		$label = esc_html( $label_raw );
		$key = (string) ( $entry['key'] ?? culturacsi_activity_tree_entry_key( $label_raw ) );
		$img_url = culturacsi_activity_tree_entry_image_url( $entry, $hero_map );

		if ( $img_url ) {
			$img_tag = sprintf( '<img src="%s" alt="%s"/>', esc_url( $img_url ), esc_attr( $label_raw ) );
		} else {
			$img_tag = '<img alt=""/>';
		}

		$link_url = culturacsi_activity_tree_entry_link( $entry );

		$columns_markup .= sprintf(
			'<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover","linkDestination":"custom"} -->
<figure class="wp-block-image"><a href="%1$s">%2$s</a></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"className":"culturacsi-settori-pattern-label","align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="culturacsi-settori-pattern-label has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">%3$s</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

',
			esc_url( $link_url ),
			$img_tag,
			$label
		);
	}

	if ( '' === $columns_markup ) {
		return '';
	}

	$content = '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:columns -->
<div class="wp-block-columns">' . $columns_markup . '</div>
<!-- /wp:columns --></div>
<!-- /wp:group -->';

	set_transient( $cache_key, $content, HOUR_IN_SECONDS );
	return $content;
}

/**
 * Register the block patterns.
 */
function culturacsi_register_patterns_mu() {

	// 1. Hero Pattern
	register_block_pattern(
		'culturacsi/hero-section',
		array(
			'title'       => __( 'Sezione Hero Slider', 'culturacsi' ),
			'categories'  => array( 'header', 'featured' ),
			'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"100px","bottom":"100px"}}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-light-gray-background-color has-background" style="padding-top:100px;padding-bottom:100px"><!-- wp:heading {"textAlign":"center","style":{"typography":{"textTransform":"uppercase"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="text-transform:uppercase">Titolo sezione Hero</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Questa e una sezione hero dinamica. Puoi sostituire immagine di sfondo e testi tramite editor.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
		)
	);

	// 2. Settori Grid Pattern – dynamically generated from the activity tree
	$settori_content = culturacsi_build_settori_pattern_content();

	// Fall back to the static version if the tree is not yet populated
	if ( '' === $settori_content ) {
		$settori_content = '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"className":"culturacsi-settori-pattern-label","align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="culturacsi-settori-pattern-label has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">CONVENZIONI</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"className":"culturacsi-settori-pattern-label","align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="culturacsi-settori-pattern-label has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">FORMAZIONE</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"className":"culturacsi-settori-pattern-label","align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="culturacsi-settori-pattern-label has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">EVENTI</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"className":"culturacsi-settori-pattern-label","align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="culturacsi-settori-pattern-label has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">PROGETTI</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"className":"culturacsi-settori-pattern-label","align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="culturacsi-settori-pattern-label has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">CROWDFUNDING</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->';
	}

	register_block_pattern(
		'culturacsi/services-grid',
		array(
			'title'       => __( 'Griglia Settori', 'culturacsi' ),
			'description' => __( 'Griglia generata dinamicamente dai settori dell\'albero delle attività.', 'culturacsi' ),
			'categories'  => array( 'services' ),
			'content'     => $settori_content,
		)
	);
}
add_action( 'init', 'culturacsi_register_patterns_mu', 20 );

/**
 * Translate Settori pattern labels at render level only.
 * Leaves canonical keys/slugs untouched; affects visual labels in tagged paragraph blocks.
 */
add_filter(
	'render_block',
	static function( $block_content, $block ) {
		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}
		if ( ! is_array( $block ) || ( $block['blockName'] ?? '' ) !== 'core/paragraph' ) {
			return $block_content;
		}

		$class_name = (string) ( $block['attrs']['className'] ?? '' );
		$is_tagged_label = false !== strpos( $class_name, 'culturacsi-settori-pattern-label' );
		$is_legacy_settori_label = false !== strpos( $block_content, 'has-primary-blue-background-color' )
			&& false !== strpos( $block_content, 'has-text-align-center' )
			&& false !== strpos( $block_content, 'font-weight:700' );
		if ( ! $is_tagged_label && ! $is_legacy_settori_label ) {
			return $block_content;
		}

		if ( ! function_exists( 'culturacsi_get_current_language' ) || 'it' === culturacsi_get_current_language() ) {
			return $block_content;
		}

		return (string) preg_replace_callback(
			'~(<p\b[^>]*>)(.*?)(</p>)~is',
			static function( $m ) {
				$raw_inner = html_entity_decode( wp_strip_all_tags( (string) $m[2], true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$raw_inner = trim( preg_replace( '/\s+/u', ' ', $raw_inner ) );
				if ( '' === $raw_inner ) {
					return $m[0];
				}

				$translated = $raw_inner;
				if ( function_exists( 'culturacsi_translate_visual_label' ) ) {
					$translated = culturacsi_translate_visual_label( $raw_inner );
				} elseif ( function_exists( 'culturacsi_it_runtime_label_map' ) ) {
					$translated = culturacsi_it_runtime_label_map( $raw_inner );
				}

				if ( ! is_string( $translated ) || '' === trim( $translated ) || $translated === $raw_inner ) {
					return $m[0];
				}

				return $m[1] . esc_html( $translated ) . $m[3];
			},
			$block_content
		);
	},
	30,
	2
);
