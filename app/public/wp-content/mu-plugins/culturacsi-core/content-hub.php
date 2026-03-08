<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content hub bootstrap.
 *
 * This file intentionally stays small. Each module owns a stable subsystem:
 * - `bootstrap.php`: constants and shared path helpers
 * - `register.php`: post type/taxonomy registration and query plumbing
 * - `access.php`: role rules, redirects, and default seeding
 * - `admin.php`: assets, metaboxes, save handlers, and admin list UX
 * - `shortcodes.php`: public shortcodes and filter/query helpers
 * - `guide.php`: admin documentation screen
 * - `ajax.php`: modal payload endpoints
 */

require_once __DIR__ . '/content-hub/bootstrap.php';
require_once __DIR__ . '/content-hub/register.php';
require_once __DIR__ . '/content-hub/access.php';
require_once __DIR__ . '/content-hub/admin.php';
require_once __DIR__ . '/content-hub/shortcodes.php';
require_once __DIR__ . '/content-hub/guide.php';
require_once __DIR__ . '/content-hub/ajax.php';
