<?php
/**
 * Post-to-DB sync
 * Ensures manual updates in WordPress are reflected in the custom search index.
 */

function ab_sync_association_post_to_db(int $post_id): void {
  static $queued = [];
  if (isset($queued[$post_id])) return;
  
  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'association') return;
  
  $queued[$post_id] = true;

  // Defer execution until the very end of the PHP request
  // This ensures custom frontend scripts or backend metaboxes 
  // have already finished saving metadata and taxonomy terms.
  add_action('shutdown', function() use ($post_id) {
    ab_sync_association_post_to_db_execute($post_id);
  });
}

function ab_sync_association_delete_rows_for_post(int $post_id, string $organization = ''): void {
  global $wpdb;
  $table = ab_table();
  $organization = trim($organization);

  // Prefer stable post ID linkage when available.
  if (function_exists('ab_table_has_column') && ab_table_has_column('source_post_id')) {
    $wpdb->delete($table, ['source_post_id' => $post_id], ['%d']);
    return;
  }

  // Legacy fallback (kept for backward compatibility on older schemas).
  if ($organization !== '') {
    $wpdb->delete($table, ['organization' => $organization]);
  }
}

function ab_sync_association_post_to_db_execute(int $post_id): void {
  $post = get_post($post_id);
  if (!$post || $post->post_type !== 'association') return;

  global $wpdb;
  $table = ab_table();

  // If trashed or not published, remove it from the browser DB and skip inserts
  if ($post->post_status !== 'publish' && $post->post_status !== 'private') {
    ab_sync_association_delete_rows_for_post($post_id, trim((string)$post->post_title));
    return;
  }

  $org_target = trim($post->post_title);
  if ($org_target === '') return;

  // Clean existing rows for this specific association post.
  ab_sync_association_delete_rows_for_post($post_id, $org_target);

  // Extract meta
  $region = (string)get_post_meta($post_id, 'region', true);
  $province = (string)get_post_meta($post_id, 'province', true);
  $city = (string)get_post_meta($post_id, 'city', true);
  $location_raw = trim("$city $province $region");
  $urls = (string)get_post_meta($post_id, 'url', true);
  $emails = (string)get_post_meta($post_id, 'email', true);
  $notes = (string)get_post_meta($post_id, 'notes', true);

  if ( function_exists( 'culturacsi_activity_paths_for_post' ) ) {
    $path_map = culturacsi_activity_paths_for_post( $post_id );
  } else {
    // Minimal fallback if the core function is not available (though it should be).
    $macro    = (string) get_post_meta( $post_id, 'macro', true );
    $settore  = (string) get_post_meta( $post_id, 'settore', true );
    $settore2 = (string) get_post_meta( $post_id, 'settore2', true );
    if ( '' !== $macro ) {
      $path = $macro;
      if ( '' !== $settore ) {
        $path .= ' > ' . $settore;
      }
      if ( '' !== $settore2 ) {
        $path .= ' > ' . $settore2;
      }
      $path_map[] = $path;
    } else {
      $path_map[] = 'Altro > Varie > Non specificato';
    }
  }

  // Insert rows for each path
  foreach (array_unique($path_map) as $path) {
    $parts = array_map('trim', explode('>', $path));
    $macro = $parts[0] ?? '';
    $settore = $parts[1] ?? '';
    $settore2 = $parts[2] ?? '';

    $row = [
      'category' => $path,
      'macro' => $macro,
      'settore' => $settore,
      'settore2' => $settore2,
      'region' => $region,
      'organization' => $org_target,
      'city' => $city,
      'province' => $province,
      'location_raw' => $location_raw,
      'urls' => $urls,
      'emails' => $emails,
      'notes' => $notes,
      'source_block' => 'Manually updated via panel'
    ];
    if (function_exists('ab_table_has_column') && ab_table_has_column('source_post_id')) {
      $row['source_post_id'] = $post_id;
    }

    $wpdb->insert($table, $row);
  }

  if (function_exists('ab_sync_clear_cache')) {
    ab_sync_clear_cache();
  }
}

add_action('save_post_association', 'ab_sync_association_post_to_db', 999);
add_action('wp_trash_post', function($post_id) {
  $post = get_post($post_id);
  if ($post && $post->post_type === 'association') {
     ab_sync_association_delete_rows_for_post((int)$post_id, trim((string)$post->post_title));
  }
}, 10);
