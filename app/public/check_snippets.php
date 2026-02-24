<?php
define('WP_USE_THEMES', false);
require_once dirname(__FILE__) . '/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'snippets';
$snippets = $wpdb->get_results("SELECT id, name, active FROM $table_name WHERE snippet_scope = 'global' OR snippet_scope = 'front-end'", ARRAY_A);
foreach ($snippets as $s) {
    echo "Snippet ID " . $s['id'] . ": " . $s['name'] . " (Active: " . $s['active'] . ")\n";
}
die();
