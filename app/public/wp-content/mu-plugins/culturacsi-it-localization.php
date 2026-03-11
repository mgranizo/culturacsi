<?php
/**
 * Plugin Name: CulturaCSI Italian Localization
 * Description: Forces key frontend/admin strings to Italian across Kadence and custom plugins.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Safely include a core MU module.
 *
 * @param string $relative_path Path relative to this bootstrap file.
 * @return void
 */
function culturacsi_mu_require( $relative_path ) {
	$path = __DIR__ . '/' . ltrim( $relative_path, '/' );
	if ( file_exists( $path ) ) {
		require_once $path;
		return;
	}

	error_log( sprintf( 'CulturaCSI MU bootstrap missing module: %s', $path ) );
}

$culturacsi_mu_modules = array(
	'culturacsi-core/translations.php',
	'culturacsi-core/routing.php',
	'culturacsi-core/activity-tree.php',
	'culturacsi-core/portal-shortcodes.php',
	'culturacsi-core/content-hub.php',
	'culturacsi-core/moderation.php',
	'culturacsi-core/admin-ui.php',
	'culturacsi-core/queries-filters.php',
	'culturacsi-core/logging.php',
	'culturacsi-core/performance-hints.php',
	'culturacsi-core/exports.php',
	'culturacsi-core/notification-triggers.php',
	'culturacsi-core/kadence-hardening.php',
	'culturacsi-core/migrations.php',
	'culturacsi-core/ui-tweaks.php',
);

foreach ( $culturacsi_mu_modules as $culturacsi_mu_module ) {
	culturacsi_mu_require( $culturacsi_mu_module );
}
