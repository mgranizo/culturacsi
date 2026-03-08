<?php
/**
 * Query and permalink filters bootstrap.
 *
 * Architecture note:
 * - This file is intentionally only a loader.
 * - Each concern lives in its own module so future admins can change one query
 *   system without reading unrelated code paths.
 *
 * Module map:
 * - queries-filters/news-links.php
 *   External/permalink behavior for News and Content Hub entries.
 * - queries-filters/news-search.php
 *   Public News search shortcode, request parsing, query mutation, caching.
 * - queries-filters/settori-event-filters.php
 *   Settori activity path filters applied to Event archives and Query Loops.
 * - queries-filters/assets.php
 *   Frontend/admin CSS/JS used by the modules above.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/queries-filters/news-links.php';
require_once __DIR__ . '/queries-filters/news-search.php';
require_once __DIR__ . '/queries-filters/settori-event-filters.php';
require_once __DIR__ . '/queries-filters/assets.php';
