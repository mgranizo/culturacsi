<?php
$db = new mysqli('127.0.0.1', 'root', 'root', 'local', 10014);
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "=== CSS Definitions ===\n";
$css = $db->query("SELECT option_value FROM wp_options WHERE option_name LIKE '%custom_css%' OR option_name LIKE '%kadence%' AND option_value LIKE '%fullheight-arrows%'");
while($r = $css->fetch_object()) {
    if (preg_match('/(?<=^|\s|\})[^{}]*fullheight-arrows[^{}]*\{[^}]+\}/i', $r->option_value, $m)) {
        echo "FOUND CSS: " . $m[0] . "\n";
    }
}

echo "\n=== Block Patterns ===\n";
$blocks = $db->query("SELECT ID, post_title, post_content FROM wp_posts WHERE post_type = 'wp_block' AND (post_title LIKE '%Eventi%' OR post_title LIKE '%Settori%')");
while($r = $blocks->fetch_object()) {
    echo "PATTERN: [{$r->ID}] {$r->post_title}\n";
    echo $r->post_content . "\n\n";
}
