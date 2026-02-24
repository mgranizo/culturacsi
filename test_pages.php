<?php
define( 'WP_USE_THEMES', false );
require_once( 'C:\Users\mgran\Local Sites\culturacsi\app\public\wp-load.php' );

$pages = get_pages();
foreach ( $pages as $page ) {
    if ( stripos( $page->post_title, 'notizie' ) !== false || stripos( $page->post_title, 'calendar' ) !== false || stripos( $page->post_title, 'eventi' ) !== false ) {
        echo "PAGE: " . $page->post_title . "\n";
        echo $page->post_content . "\n";
        echo "--------------------------\n";
    }
}
