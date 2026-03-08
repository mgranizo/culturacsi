<?php
/**
 * Reusable content hubs (library, services, convenzioni, formazione, etc.).
 *
 * @package CulturaCSI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CULTURACSI_CONTENT_HUB_POST_TYPE' ) ) {
	define( 'CULTURACSI_CONTENT_HUB_POST_TYPE', 'csi_content_entry' );
}

if ( ! defined( 'CULTURACSI_CONTENT_HUB_TAXONOMY' ) ) {
	define( 'CULTURACSI_CONTENT_HUB_TAXONOMY', 'csi_content_section' );
}

/**
 * Shared path helpers for content hub modules.
 */
function culturacsi_content_hub_base_path(): string {
	return dirname( __DIR__ );
}

function culturacsi_content_hub_asset_path( string $relative_path ): string {
	return culturacsi_content_hub_base_path() . '/assets/' . ltrim( $relative_path, '/' );
}

function culturacsi_content_hub_asset_url( string $relative_path ): string {
	return content_url( 'mu-plugins/culturacsi-core/assets/' . ltrim( $relative_path, '/' ) );
}

/**
 * Resolve a cache-busting version for a content hub asset.
 */
function culturacsi_content_hub_asset_version( string $relative_path ): ?string {
	$asset_path = culturacsi_content_hub_asset_path( $relative_path );

	if ( ! file_exists( $asset_path ) ) {
		return null;
	}

	return (string) filemtime( $asset_path );
}
