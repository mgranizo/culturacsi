<?php
require_once('wp-load.php');
global $wpdb;
$snippets = $wpdb->get_results("SELECT name, code FROM {$wpdb->prefix}snippets WHERE code LIKE '%cronologia%' OR code LIKE '%cronologia%'");
if ($snippets) {
    foreach ($snippets as $s) {
        echo "SNIPPET MATCH: " . $s->name . "\n";
    }
} else {
    echo "No snippets found.\n";
}

$posts = $wpdb->get_results("SELECT post_title, post_name FROM {$wpdb->prefix}posts WHERE post_content LIKE '%cronologia%'");
if ($posts) {
    foreach ($posts as $p) {
        echo "POST MATCH: " . $p->post_title . "\n";
    }
} else {
    echo "No posts found.\n";
}

$options = $wpdb->get_results("SELECT option_name FROM {$wpdb->prefix}options WHERE option_value LIKE '%cronologia%'");
if ($options) {
    echo "OPTIONS MATCHES: \n";
    foreach ($options as $o) {
        echo "- " . $o->option_name . "\n";
    }
} else {
    echo "No options found.\n";
}
