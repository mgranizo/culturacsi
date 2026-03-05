<?php
/**
 * Kadence rendering hardening hooks.
 *
 * @package CulturaCSI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'render_block_data', 'culturacsi_guard_kadence_advancedgallery_dimensions', 9, 3 );

/**
 * Ensure Kadence Advanced Gallery image arrays always include width/height keys.
 *
 * Kadence reads these indexes directly in one render path. On PHP 8+ that can
 * emit warnings when older content omits those keys.
 *
 * @param array          $parsed_block The parsed block data.
 * @param array|null     $source_block Source block data when available.
 * @param WP_Block|null  $parent_block Parent block when available.
 * @return array
 */
function culturacsi_guard_kadence_advancedgallery_dimensions( $parsed_block, $source_block = null, $parent_block = null ) {
	if ( ! is_array( $parsed_block ) || empty( $parsed_block['blockName'] ) || 'kadence/advancedgallery' !== $parsed_block['blockName'] ) {
		return $parsed_block;
	}

	if ( empty( $parsed_block['attrs'] ) || ! is_array( $parsed_block['attrs'] ) ) {
		return $parsed_block;
	}

	$gallery_keys = array( 'imagesDynamic', 'images' );
	foreach ( $gallery_keys as $gallery_key ) {
		if ( empty( $parsed_block['attrs'][ $gallery_key ] ) || ! is_array( $parsed_block['attrs'][ $gallery_key ] ) ) {
			continue;
		}

		foreach ( $parsed_block['attrs'][ $gallery_key ] as $index => $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}

			if ( ! array_key_exists( 'width', $image ) || null === $image['width'] ) {
				$parsed_block['attrs'][ $gallery_key ][ $index ]['width'] = '';
			}
			if ( ! array_key_exists( 'height', $image ) || null === $image['height'] ) {
				$parsed_block['attrs'][ $gallery_key ][ $index ]['height'] = '';
			}
		}
	}

	return $parsed_block;
}
