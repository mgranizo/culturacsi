<?php
/**
 * Assets used by the query/permalink modules.
 *
 * Split rationale:
 * - query logic should stay readable without embedded CSS/JS noise,
 * - UI assets can now be versioned and cached independently,
 * - future frontend tweaks no longer require editing PHP output buffers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve an asset path for the query-filters module.
 */
function culturacsi_queries_filters_asset_path( string $relative ): string {
	return __DIR__ . '/../assets/' . ltrim( $relative, '/' );
}

/**
 * Resolve an asset URL for the query-filters module.
 */
function culturacsi_queries_filters_asset_url( string $relative ): string {
	return content_url( 'mu-plugins/culturacsi-core/assets/' . ltrim( $relative, '/' ) );
}

/**
 * Resolve a cache-busting version for a query-filters asset.
 */
function culturacsi_queries_filters_asset_version( string $relative ): ?string {
	$asset_path = culturacsi_queries_filters_asset_path( $relative );

	if ( ! file_exists( $asset_path ) ) {
		return null;
	}

	return (string) filemtime( $asset_path );
}

/**
 * Frontend assets for the public News search UI.
 *
 * These assets are lightweight and self-guarding:
 * they load globally on the frontend, but they do nothing unless the public
 * News search markup exists in the DOM.
 */
function culturacsi_queries_filters_enqueue_frontend_assets(): void {
	if ( is_admin() ) {
		return;
	}

	wp_enqueue_style(
		'culturacsi-queries-filters',
		culturacsi_queries_filters_asset_url( 'queries-filters.css' ),
		array(),
		culturacsi_queries_filters_asset_version( 'queries-filters.css' )
	);

	wp_enqueue_script(
		'culturacsi-queries-filters',
		culturacsi_queries_filters_asset_url( 'queries-filters.js' ),
		array(),
		culturacsi_queries_filters_asset_version( 'queries-filters.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'culturacsi_queries_filters_enqueue_frontend_assets', 60 );

/**
 * Admin-only CSS for the News list zebra rows.
 *
 * This remains in PHP because it is intentionally scoped to one screen and does
 * not justify a separate admin-only bundle at this stage.
 */
function culturacsi_news_admin_zebra_rows(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! ( $screen instanceof WP_Screen ) || 'edit-news' !== $screen->id ) {
		return;
	}
	?>
	<style id="culturacsi-news-admin-zebra">
		.post-type-news .wp-list-table.widefat tbody tr:nth-child(odd) > * {
			background-color: #f7fbff;
		}
		.post-type-news .wp-list-table.widefat tbody tr:nth-child(even) > * {
			background-color: #ffffff;
		}
		.post-type-news .wp-list-table.widefat tbody tr:hover > * {
			background-color: #eaf3ff !important;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'culturacsi_news_admin_zebra_rows' );
