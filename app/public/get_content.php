<?php
require_once 'wp-load.php';
$post = get_page_by_path('calendario');
if (!$post) $post = get_page_by_path('calendar');
echo $post->post_content;
