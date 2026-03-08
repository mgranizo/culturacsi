<?php
/**
 * UI Tweaks — consolidated from ui-fixes and culturacsi-ui-tweaks plugins.
 * Absorbed into culturacsi-core to reduce plugin count.
 *
 * Architecture note:
 * - This file is intentionally small and acts as the PHP bootstrap only.
 * - All durable frontend presentation logic lives in assets/ui-tweaks.css.
 * - All durable frontend behavior lives in assets/ui-tweaks.js.
 * - PHP is only responsible for:
 *   1. resolving asset paths/URLs,
 *   2. versioning assets via filemtime,
 *   3. passing runtime configuration to JS,
 *   4. rendering any markup shell that JS enhances.
 *
 * Keeping this boundary strict makes future debugging much easier:
 * if a problem is visual, start in CSS; if it is interactive, start in JS;
 * if it is data/config related, start here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve an asset path inside culturacsi-core/assets.
 *
 * Centralizing this avoids hardcoded path duplication across enqueue calls.
 */
function culturacsi_ui_tweaks_asset_path( string $relative ): string {
	return __DIR__ . '/assets/' . ltrim( $relative, '/' );
}

/**
 * Resolve an asset URL inside culturacsi-core/assets.
 *
 * We intentionally use content_url() because this module lives in mu-plugins,
 * not in a regular plugin directory.
 */
function culturacsi_ui_tweaks_asset_url( string $relative ): string {
	return content_url( 'mu-plugins/culturacsi-core/assets/' . ltrim( $relative, '/' ) );
}

/**
 * Resolve a cache-busting version for a UI tweaks asset.
 */
function culturacsi_ui_tweaks_asset_version( string $relative ): ?string {
	$asset_path = culturacsi_ui_tweaks_asset_path( $relative );

	if ( ! file_exists( $asset_path ) ) {
		return null;
	}

	return (string) filemtime( $asset_path );
}

/**
 * Resolve the media URL used by the 5xmille modal.
 *
 * Supported inputs:
 * - full remote URLs,
 * - uploads-relative paths,
 * - wp-content/uploads/... paths.
 *
 * The frontend JS only receives a final browser-safe URL; all path normalization
 * is handled here so the interactive layer stays simple.
 */
function culturacsi_ui_tweaks_video_url(): string {
	$video_path = 'https://www.youtube.com/embed/boNsU48htiI';
	$video_src  = trim( $video_path );

	if ( preg_match( '#^https?://#i', $video_src ) ) {
		return esc_url_raw( $video_src );
	}

	$normalized = str_replace( '\\', '/', ltrim( $video_src, '/' ) );
	if ( 0 === strpos( strtolower( $normalized ), 'wp-content/uploads/' ) ) {
		$normalized = substr( $normalized, strlen( 'wp-content/uploads/' ) );
	}

	$normalized = ltrim( $normalized, '/' );
	if ( '' === $normalized ) {
		return '';
	}

	$uploads          = wp_get_upload_dir();
	$encoded_segments = array_map( 'rawurlencode', explode( '/', $normalized ) );

	return trailingslashit( $uploads['baseurl'] ) . implode( '/', $encoded_segments );
}

/**
 * Enqueue the shared UI tweak assets.
 *
 * Priority 50 intentionally loads after most theme/plugin base styles so our
 * shared UI system can act as the final normalization layer.
 *
 * Versioning strategy:
 * - filemtime is used so edits invalidate browser cache automatically in dev
 *   and on deploy without manual version bumps.
 */
function culturacsi_ui_tweaks_enqueue_assets(): void {
	wp_enqueue_style(
		'culturacsi-ui-tweaks',
		culturacsi_ui_tweaks_asset_url( 'ui-tweaks.css' ),
		array(),
		culturacsi_ui_tweaks_asset_version( 'ui-tweaks.css' )
	);

	wp_add_inline_style(
		'culturacsi-ui-tweaks',
		// This stays inline because the value is runtime-configurable from PHP.
		':root{--csi-video-modal-alpha:0.6;}'
	);

	wp_enqueue_script(
		'culturacsi-ui-tweaks',
		culturacsi_ui_tweaks_asset_url( 'ui-tweaks.js' ),
		array(),
		culturacsi_ui_tweaks_asset_version( 'ui-tweaks.js' ),
		true
	);

	wp_localize_script(
		'culturacsi-ui-tweaks',
		'culturacsiUiTweaks',
		array(
			// JS receives normalized, browser-ready config only.
			'videoUrl' => culturacsi_ui_tweaks_video_url(),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'culturacsi_ui_tweaks_enqueue_assets', 50 );

/**
 * Render the 5xmille modal shell.
 *
 * Important:
 * - Markup stays server-rendered so the modal is always present in the DOM.
 * - JS is responsible only for open/close behavior and media injection.
 * - This makes the feature easier to inspect in dev tools and easier to recover
 *   from if JS partially fails.
 */
function culturacsi_ui_tweaks_render_video_modal(): void {
	?>
	<div id="csi-5xmille-modal" class="csi-video-modal" aria-hidden="true" role="dialog">
		<div class="csi-video-modal-container">
			<button type="button" class="csi-video-modal-close" id="csi-5xmille-close" aria-label="Close video modal">&times;</button>
			<div class="csi-video-modal-player">
				<div id="csi-5xmille-video-wrapper" style="width:100%; height:100%;"></div>
			</div>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'culturacsi_ui_tweaks_render_video_modal', 110 );
