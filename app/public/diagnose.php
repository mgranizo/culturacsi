<?php
define('WP_USE_THEMES', false);
require_once dirname(__FILE__) . '/wp-load.php';

echo "=== DIAGNOSTICS ===\n";

global $wpdb;

// 1. Check Events
$events_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'tribe_events' AND post_status != 'auto-draft'");
echo "Events found in DB: " . (int)$events_count . "\n";

// 2. Check Other Post Types
$types = $wpdb->get_results("SELECT post_type, COUNT(*) as count FROM {$wpdb->posts} WHERE post_status != 'auto-draft' GROUP BY post_type", ARRAY_A);
echo "\nPost Types in DB:\n";
foreach ($types as $t) {
    if (!in_array($t['post_type'], ['revision', 'nav_menu_item', 'wp_global_styles', 'wp_navigation', 'acf-field', 'acf-field-group', 'attachment'])) {
        echo "- " . $t['post_type'] . ": " . $t['count'] . "\n";
    }
}

// 3. Active Plugins
$active_plugins = get_option('active_plugins');
echo "\nActive Plugins:\n";
foreach ($active_plugins as $plugin) {
    echo "- " . $plugin . "\n";
}

// 4. Broken Link Previews?
echo "\nChecking Assoc Portal status... \n";
if (function_exists('assoc_portal_events_calendar_browser_shortcode')) {
    echo "assoc_portal_events_calendar_browser_shortcode exists.\n";
} else {
    echo "assoc_portal_events_calendar_browser_shortcode MISSING.\n";
}

// Flush Permalinks
flush_rewrite_rules();
echo "\nFlushed permalinks.\n";
die();
