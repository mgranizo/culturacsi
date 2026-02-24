<?php
require_once dirname(__FILE__) . '/wp-load.php';

$q = new WP_Query([
    'post_type' => 'event',
    'posts_per_page' => 50,
    'post_status' => 'any'
]);

$results = [
    'total' => $q->found_posts,
    'events' => []
];

foreach ($q->posts as $p) {
    $meta = get_post_meta($p->ID);
    $assoc_id = 0;
    foreach (['organizer_association_id', 'association_id', 'association_post_id'] as $key) {
        if (!empty($meta[$key][0])) {
            $assoc_id = (int)$meta[$key][0];
            break;
        }
    }
    
    $event_data = [
        'id' => $p->ID,
        'title' => $p->post_title,
        'assoc_id' => $assoc_id,
        'has_association' => ($assoc_id > 0 && get_post_type($assoc_id) === 'association'),
        'meta_keys' => array_keys($meta)
    ];
    
    if ($assoc_id > 0) {
        $assoc_meta = get_post_meta($assoc_id);
        $event_data['assoc_meta'] = [
            'macro' => $assoc_meta['_ab_csv_macro'][0] ?? $assoc_meta['macro'][0] ?? '',
            'settore' => $assoc_meta['_ab_csv_settore'][0] ?? $assoc_meta['settore'][0] ?? '',
            'regione' => $assoc_meta['_ab_csv_region'][0] ?? $assoc_meta['regione'][0] ?? '',
        ];
    }
    
    $results['events'][] = $event_data;
}

file_put_contents('diag_calendar_data.json', json_encode($results, JSON_PRETTY_PRINT));
echo "Done. Created diag_calendar_data.json\n";
