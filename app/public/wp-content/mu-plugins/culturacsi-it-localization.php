<?php
/**
 * Plugin Name: CulturaCSI Italian Localization
 * Description: Forces key frontend/admin strings to Italian across Kadence and custom plugins.
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/culturacsi-core/translations.php';
require_once __DIR__ . '/culturacsi-core/routing.php';
require_once __DIR__ . '/culturacsi-core/portal-shortcodes.php';
require_once __DIR__ . '/culturacsi-core/admin-ui.php';
require_once __DIR__ . '/culturacsi-core/queries-filters.php';
require_once __DIR__ . '/culturacsi-core/logging.php';
require_once __DIR__ . '/culturacsi-core/performance-hints.php';

