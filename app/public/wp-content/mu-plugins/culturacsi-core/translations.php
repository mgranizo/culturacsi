<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CulturaCSI translations bootstrap.
 *
 * This file intentionally stays small. Each required module owns one concern:
 * - `context.php`: language resolution, locale filters, and cache headers
 * - `reserved-pages.php`: reserved-area page provisioning and content safety nets
 * - `gettext-ui.php`: gettext remapping plus frontend login/language-selector UI
 * - `runtime.php`: render-time and DOM-time translation fallbacks
 *
 * Load order matters:
 * 1. Language context must exist before any translation map or UI runs.
 * 2. Reserved-area provisioning is independent, so it can load next.
 * 3. Gettext/UI depends on the context helpers.
 * 4. Runtime translation layers load last because they depend on earlier helpers.
 */

require_once __DIR__ . '/translations/context.php';
require_once __DIR__ . '/translations/reserved-pages.php';
require_once __DIR__ . '/translations/gettext-ui.php';
require_once __DIR__ . '/translations/runtime.php';
