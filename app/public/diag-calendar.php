<?php
require_once dirname(__FILE__) . '/wp-load.php';

$q = new WP_Query([
    'post_type' => 'event',
    'posts_per_page' => 10,
    'post_status' => 'any'
]);

echo "Total events found: " . $q->found_posts . "\n\n";

foreach ($q->posts as $p) {
    echo "Event ID: " . $p->ID . " - " . $p->post_title . "\n";
    $meta = get_post_meta($p->ID);
    foreach (['organizer_association_id', 'association_id', 'association_post_id', 'city', 'province', 'comune'] as $key) {
        $val = isset($meta[$key]) ? $meta[$key][0] : 'N/A';
        echo "  $key: $val\n";
    }
    
    // Check if association exists if ID > 0
    $assoc_id = 0;
    foreach (['organizer_association_id', 'association_id', 'association_post_id'] as $key) {
        if (!empty($meta[$key][0])) {
            $assoc_id = (int)$meta[$key][0];
            break;
        }
    }
    
    if ($assoc_id > 0) {
        $assoc_post = get_post($assoc_id);
        if ($assoc_post) {
            echo "  Association Found: ID $assoc_id (" . $assoc_post->post_type . ") " . $assoc_post->post_title . "\n";
            $assoc_meta = get_post_meta($assoc_id);
            foreach (['_ab_csv_macro', 'macro', 'macro_categoria', '_ab_csv_settore', 'settore', 'regione'] as $akey) {
                $aval = isset($assoc_meta[$akey]) ? $assoc_meta[$akey][0] : 'N/A';
                echo "    Assoc Meta $akey: $aval\n";
            }
        } else {
            echo "  Association ID $assoc_id NOT FOUND in DB\n";
        }
    }
    echo "--------------------\n";
}
