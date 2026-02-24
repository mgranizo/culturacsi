<?php
define('WP_USE_THEMES', false);
require_once dirname(__FILE__) . '/wp-load.php';

global $wpdb;
$table_name = $wpdb->prefix . 'snippets';
$snippet = $wpdb->get_row("SELECT * FROM $table_name WHERE id = 14");
echo "\n\n=== START SNIPPET CODE ===\n\n";
echo $snippet->code;
echo "\n\n=== END SNIPPET CODE ===\n\n";
die();
