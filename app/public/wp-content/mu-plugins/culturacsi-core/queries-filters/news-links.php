<?php
/**
 * News and Content Entry permalink behavior.
 *
 * Scope:
 * - External URL resolution for News posts.
 * - Global permalink overrides for News.
 * - Modal/external-link behavior for Content Hub entries.
 *
 * Why this lives separately:
 * permalink logic is high-impact and should not be mixed with query parsing or
 * frontend search UI. When links look wrong, this is the first file to inspect.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the preferred external URL for a News post.
 *
 * Rules:
 * - Applies only to post_type=news.
 * - If `_hebeae_external_enabled` is explicitly "0", keep the internal URL.
 * - If an external URL exists, normalize it and return the browser-safe value.
 */
function culturacsi_news_external_url_for_post( int $post_id ): string {
	if ( $post_id <= 0 ) {
		return '';
	}

	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'news' !== $post->post_type ) {
		return '';
	}

	$enabled = (string) get_post_meta( $post_id, '_hebeae_external_enabled', true );
	if ( '0' === $enabled ) {
		return '';
	}

	$url = trim( (string) get_post_meta( $post_id, '_hebeae_external_url', true ) );
	if ( '' === $url ) {
		return '';
	}

	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . ltrim( $url, '/' );
	}

	$url = esc_url_raw( $url );

	return '' !== $url ? $url : '';
}

/**
 * Replace News permalinks with external URLs where configured.
 *
 * Hook target:
 * - post_type_link is used by archives, query loops and most permalink builders.
 */
function culturacsi_news_force_external_post_type_link( string $post_link, WP_Post $post, bool $leavename, bool $sample ): string {
	unset( $leavename );

	if ( $sample || 'news' !== $post->post_type ) {
		return $post_link;
	}

	$external = culturacsi_news_external_url_for_post( (int) $post->ID );

	return '' !== $external ? $external : $post_link;
}
add_filter( 'post_type_link', 'culturacsi_news_force_external_post_type_link', 99, 4 );

/**
 * Fallback for contexts that render `the_permalink()` directly for News.
 */
function culturacsi_news_force_external_the_permalink( string $permalink ): string {
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) || 'news' !== $post->post_type ) {
		return $permalink;
	}

	$external = culturacsi_news_external_url_for_post( (int) $post->ID );

	return '' !== $external ? $external : $permalink;
}
add_filter( 'the_permalink', 'culturacsi_news_force_external_the_permalink', 99 );

/**
 * Resolve Content Hub entry links.
 *
 * Current business rule:
 * - Library entries always open in a modal on HTML pages.
 * - Non-library entries open in a modal when they have a downloadable file or
 *   when no external URL is configured.
 * - External URLs remain canonical for non-HTML contexts such as REST/feed/sitemap.
 */
function culturacsi_content_entry_force_external_link( string $post_link, $post ): string {
	$post = get_post( $post );
	if ( ! ( $post instanceof WP_Post ) || 'csi_content_entry' !== $post->post_type ) {
		return $post_link;
	}

	$is_rest = defined( 'REST_REQUEST' ) && REST_REQUEST;
	$is_feed = function_exists( 'is_feed' ) && is_feed();

	$is_sitemap = false;
	if ( function_exists( 'is_sitemap' ) ) {
		$is_sitemap = (bool) call_user_func( 'is_sitemap' );
	}

	$is_html = ! $is_rest && ! $is_feed && ! $is_sitemap;

	$external      = trim( (string) get_post_meta( $post->ID, '_csi_content_hub_external_url', true ) );
	$has_external  = '' !== $external;
	$file_id       = (int) get_post_meta( $post->ID, '_csi_content_hub_file_id', true );
	$attachment    = $file_id > 0 ? (string) wp_get_attachment_url( $file_id ) : '';
	$has_file_link = $file_id > 0 && '' !== $attachment;

	$is_library = has_term( 'library', 'csi_content_section', $post )
		|| has_term( 'biblioteca', 'csi_content_section', $post )
		|| has_term( 'document-library', 'csi_content_section', $post );

	if ( $is_library ) {
		return $is_html ? '#csi-modal-post-' . $post->ID : $post_link;
	}

	if ( $is_html && ( $has_file_link || ! $has_external ) ) {
		return '#csi-modal-post-' . $post->ID;
	}

	if ( $has_external ) {
		if ( ! preg_match( '#^https?://#i', $external ) ) {
			$external = 'https://' . ltrim( $external, '/' );
		}

		return esc_url( $external );
	}

	return $post_link;
}
add_filter( 'post_type_link', 'culturacsi_content_entry_force_external_link', 99, 2 );

/**
 * Fallback for `the_permalink()` on Content Hub entries.
 */
function culturacsi_content_entry_force_external_the_permalink( string $permalink ): string {
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) || 'csi_content_entry' !== $post->post_type ) {
		return $permalink;
	}

	return culturacsi_content_entry_force_external_link( $permalink, $post );
}
add_filter( 'the_permalink', 'culturacsi_content_entry_force_external_the_permalink', 99 );
