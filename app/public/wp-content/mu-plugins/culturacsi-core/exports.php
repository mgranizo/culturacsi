<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Efficiently fetch post meta for multiple posts in a single query.
 * Returns array indexed by post_id => meta_key => meta_value
 *
 * @param array $post_ids Array of post IDs.
 * @return array Array of meta data indexed by post ID.
 */
function get_post_meta_chunked(array $post_ids): array {
    global $wpdb;
    
    if (empty($post_ids)) {
        return array();
    }
    
    $post_ids = array_map('intval', $post_ids);
    $post_ids = array_filter($post_ids);
    
    if (empty($post_ids)) {
        return array();
    }
    
    $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
    
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders)",
            $post_ids
        ),
        ARRAY_A
    );
    
    $meta = array();
    foreach ($results as $row) {
        $post_id = (int) $row['post_id'];
        $key = $row['meta_key'];
        $value = $row['meta_value'];
        
        if (!isset($meta[$post_id])) {
            $meta[$post_id] = array();
        }
        
        // Handle multiple values for same key (use array)
        if (isset($meta[$post_id][$key])) {
            if (!is_array($meta[$post_id][$key])) {
                $meta[$post_id][$key] = array($meta[$post_id][$key]);
            }
            $meta[$post_id][$key][] = $value;
        } else {
            $meta[$post_id][$key] = $value;
        }
    }
    
    return $meta;
}

/**
 * Build a signed export URL for portal CSV downloads.
 *
 * @param string $type        Export type.
 * @param string $current_url Current page URL/path.
 * @return string
 */
function culturacsi_export_build_url( string $type, string $current_url = '' ): string {
    $type = sanitize_key( $type );
    if ( '' === $type ) {
        return '';
    }

    $base_url = '' !== trim( $current_url ) ? $current_url : (string) ( $_SERVER['REQUEST_URI'] ?? '' );

    return (string) add_query_arg(
        array(
            'culturacsi_export'       => $type,
            'culturacsi_export_nonce' => wp_create_nonce( 'culturacsi_export_' . $type ),
        ),
        $base_url
    );
}

add_action('init', 'culturacsi_export_csv_handler');
function culturacsi_export_csv_handler() {
    if ( ! isset( $_GET['culturacsi_export'] ) ) {
        return;
    }

    if ( ! function_exists('culturacsi_portal_can_access') || ! culturacsi_portal_can_access() ) {
        wp_die('Accesso negato.');
    }

    $type = isset( $_GET['culturacsi_export'] ) ? sanitize_key( wp_unslash( $_GET['culturacsi_export'] ) ) : '';
    $allowed_types = array( 'event', 'news', 'user', 'association', 'cronologia' );
    if ( ! in_array( $type, $allowed_types, true ) ) {
        wp_die( 'Tipo esportazione non valido.', 'Accesso negato', array( 'response' => 400 ) );
    }

    $nonce = isset( $_GET['culturacsi_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['culturacsi_export_nonce'] ) ) : '';
    if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'culturacsi_export_' . $type ) ) {
        wp_die( 'Verifica di sicurezza non valida.', 'Accesso negato', array( 'response' => 403 ) );
    }
    
    // Determine file name
    $filename = 'esportazione-' . $type . '-' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    // Output BOM for Excel UTF-8
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    $is_site_admin = current_user_can( 'manage_options' );
    $user_id       = get_current_user_id();

    if ( $type === 'event' ) {
        $filters = culturacsi_portal_events_filters_from_request();
        $args = array(
            'post_type'      => 'event',
            'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        if ( ! $is_site_admin ) {
            $assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
            if ( $assoc_id > 0 ) {
                $args['meta_query'] = array( array( 'key' => 'organizer_association_id', 'value' => $assoc_id, 'compare' => '=' ) );
            } else {
                $args['author'] = $user_id;
            }
        } elseif ( $filters['author'] > 0 ) {
            $args['author'] = $filters['author'];
        }
        if ( '' !== $filters['q'] ) { $args['s'] = $filters['q']; }
        if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
            $args['date_query'] = array( array( 'year' => (int) $matches[1], 'monthnum' => (int) $matches[2] ) );
        }
        if ( 'all' !== $filters['status'] ) {
            $allowed_status = array( 'publish', 'pending', 'draft', 'future', 'private' );
            if ( in_array( $filters['status'], $allowed_status, true ) ) { $args['post_status'] = array( $filters['status'] ); }
        }

        fputcsv($output, array('ID', 'Titolo', 'Data Evento', 'Stato', 'Luogo', 'Citta', 'Autore', 'Associazione', 'Data Creazione'));
        
        $query = new WP_Query($args);
        
        // OPTIMIZATION: Pre-fetch all post meta to avoid N+1 queries
        $post_ids = wp_list_pluck($query->posts, 'ID');
        $all_meta = array();
        if (!empty($post_ids)) {
            $all_meta = get_post_meta_chunked($post_ids);
        }
        
        foreach($query->posts as $post) {
            $post_id = $post->ID;
            $status_obj = get_post_status_object($post->post_status);
            $status_label = $status_obj ? $status_obj->label : $post->post_status;
            $assoc_id = isset($all_meta[$post_id]['organizer_association_id']) ? (int) $all_meta[$post_id]['organizer_association_id'] : 0;
            $assoc_name = $assoc_id > 0 ? (string) get_the_title($assoc_id) : '';
            $city = isset($all_meta[$post_id]['city']) ? $all_meta[$post_id]['city'] : (isset($all_meta[$post_id]['comune']) ? $all_meta[$post_id]['comune'] : '');
            fputcsv($output, array(
                $post->ID,
                html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                isset($all_meta[$post_id]['start_date']) ? $all_meta[$post_id]['start_date'] : '',
                $status_label,
                isset($all_meta[$post_id]['venue_name']) ? $all_meta[$post_id]['venue_name'] : '',
                $city,
                get_the_author_meta('display_name', $post->post_author),
                html_entity_decode($assoc_name, ENT_QUOTES, 'UTF-8'),
                $post->post_date
            ));
        }

    } elseif ( $type === 'news' ) {
        $filters = culturacsi_portal_news_panel_filters_from_request();
        $args = array(
            'post_type'      => 'news',
            'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        if ( ! $is_site_admin ) {
            $assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
            if ( $assoc_id > 0 ) {
                $args['meta_query'] = array( array( 'key' => 'organizer_association_id', 'value' => $assoc_id, 'compare' => '=' ) );
            } else {
                $args['author'] = $user_id;
            }
        } elseif ( $filters['author'] > 0 ) {
            $args['author'] = $filters['author'];
        }
        if ( '' !== $filters['q'] ) { $args['s'] = $filters['q']; }
        if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
            $args['date_query'] = array( array( 'year' => (int) $matches[1], 'monthnum' => (int) $matches[2] ) );
        }
        if ( $filters['assoc'] > 0 ) {
            $allowed_ids = culturacsi_news_get_association_post_ids( $filters['assoc'] );
            $args['post__in'] = ! empty( $allowed_ids ) ? $allowed_ids : array( 0 );
        }
        if ( 'all' !== $filters['status'] ) {
            $allowed_status = array( 'publish', 'pending', 'draft', 'future', 'private' );
            if ( in_array( $filters['status'], $allowed_status, true ) ) { $args['post_status'] = array( $filters['status'] ); }
        }

        fputcsv($output, array('ID', 'Titolo', 'Data', 'Stato', 'Link Esterno', 'Autore', 'Associazione'));
        $query = new WP_Query($args);
        
        // OPTIMIZATION: Pre-fetch all post meta to avoid N+1 queries
        $post_ids = wp_list_pluck($query->posts, 'ID');
        $all_meta = array();
        if (!empty($post_ids)) {
            $all_meta = get_post_meta_chunked($post_ids);
        }
        
        foreach($query->posts as $post) {
            $post_id = $post->ID;
            $status_obj = get_post_status_object($post->post_status);
            $status_label = $status_obj ? $status_obj->label : $post->post_status;
            $assoc_id = isset($all_meta[$post_id]['organizer_association_id']) ? (int) $all_meta[$post_id]['organizer_association_id'] : 0;
            $assoc_name = $assoc_id > 0 ? (string) get_the_title($assoc_id) : '';
            fputcsv($output, array(
                $post->ID,
                html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                $post->post_date,
                $status_label,
                isset($all_meta[$post_id]['_hebeae_external_url']) ? $all_meta[$post_id]['_hebeae_external_url'] : '',
                get_the_author_meta('display_name', $post->post_author),
                html_entity_decode($assoc_name, ENT_QUOTES, 'UTF-8')
            ));
        }

    } elseif ( $type === 'user' ) {
        $filters = culturacsi_portal_users_filters_from_request();
        if ( $is_site_admin ) {
            $users = get_users(array('orderby' => 'registered', 'order' => 'DESC', 'number' => -1));
        } else {
            $assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
            if ( $assoc_id > 0 ) {
                $users = get_users(array(
                    'meta_query' => array( array( 'key' => 'association_post_id', 'value' => (string) $assoc_id, 'compare' => '=' ) ),
                    'role__not_in' => array( 'administrator' ),
                    'number' => -1
                ));
            } else {
                $users = array( wp_get_current_user() );
            }
        }
        
        $search_q = function_exists( 'mb_strtolower' ) ? mb_strtolower( $filters['q'] ) : strtolower( $filters['q'] );
        $users = array_filter( $users, static function($user) use ($filters, $search_q, $is_site_admin) {
            if ( ! $user instanceof WP_User ) return false;
            // Non-admins should never see Site Admins in the list (consistency with UI)
            if ( ! $is_site_admin && user_can( $user, 'manage_options' ) ) return false;

            if ( '' !== $search_q ) {
                $haystack = trim( implode( ' ', array( (string) $user->display_name, (string) $user->user_email, (string) $user->user_login ) ) );
                $haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
                if ( false === strpos( $haystack, $search_q ) ) return false;
            }
            if ( $is_site_admin && 'all' !== $filters['role'] && ! in_array( $filters['role'], (array) $user->roles, true ) ) return false;
            if ( ! culturacsi_portal_user_matches_status( $user, $filters['status'] ) ) return false;
            return true;
        });

        fputcsv($output, array('ID', 'Nome', 'Cognome', 'Username', 'Email', 'Ruoli', 'Stato', 'Associazione', 'Codice Fiscale', 'Societa', 'Telefono', 'Data Registrazione'));
        foreach($users as $user) {
            $roles = implode(', ', $user->roles);
            $status_label = culturacsi_portal_user_approval_label($user);
            $assoc_id = (int) get_user_meta($user->ID, 'association_post_id', true);
            $assoc_name = $assoc_id > 0 ? (string) get_the_title($assoc_id) : '';
            fputcsv($output, array(
                $user->ID,
                get_user_meta($user->ID, 'first_name', true),
                get_user_meta($user->ID, 'last_name', true),
                $user->user_login,
                $user->user_email,
                $roles,
                $status_label,
                html_entity_decode($assoc_name, ENT_QUOTES, 'UTF-8'),
                get_user_meta($user->ID, 'codice_fiscale', true),
                get_user_meta($user->ID, 'company', true),
                get_user_meta($user->ID, 'phone', true),
                $user->user_registered
            ));
        }

    } elseif ( $type === 'association' ) {
        $filters = culturacsi_portal_associations_filters_from_request();
        $query_args = array(
            'post_type'      => 'association',
            'post_status'    => ( $is_site_admin && 'all' !== $filters['status'] ) ? array( $filters['status'] ) : array( 'publish', 'private', 'pending', 'draft' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        if ( '' !== $filters['q'] ) { $query_args['s'] = $filters['q']; }
        if ( $filters['cat'] > 0 ) {
            $query_args['tax_query'] = array( array( 'taxonomy' => 'activity_category', 'field' => 'term_id', 'terms' => array( (int) $filters['cat'] ) ) );
        }
        if ( ! $is_site_admin ) {
            $managed_id = culturacsi_portal_get_managed_association_id( $user_id );
            $query_args['post__in'] = $managed_id > 0 ? array( $managed_id ) : array( 0 );
        }
        $location_meta_query = culturacsi_portal_association_location_meta_query( $filters );
        if ( ! empty( $location_meta_query ) ) {
            $query_args['meta_query'] = $location_meta_query;
        }

        // Associations: export all key contact + visibility data available in the admin page
        fputcsv($output, array(
            'ID',
            'Ragione Sociale',
            'Email',
            'Codice Fiscale / PIVA',
            'Telefono',
            'Indirizzo',
            'Citta',
            'Provincia',
            'Regione',
            'CAP',
            'Sito Web',
            'Facebook',
            'Instagram',
            'YouTube',
            'TikTok',
            'X',
            'Categoria',
            'Stato',
            'Data Inserimento'
        ));
        $query = new WP_Query($query_args);
        
        // OPTIMIZATION: Pre-fetch all post meta to avoid N+1 queries
        $post_ids = wp_list_pluck($query->posts, 'ID');
        $all_meta = array();
        if (!empty($post_ids)) {
            $all_meta = get_post_meta_chunked($post_ids);
        }
        
        // Helper function to get meta with fallback keys
        $get_meta_with_fallback = function($post_id, $meta_key, $fallback_keys = array()) use ($all_meta) {
            $value = isset($all_meta[$post_id][$meta_key]) ? $all_meta[$post_id][$meta_key] : '';
            if (empty($value) && !empty($fallback_keys)) {
                foreach ($fallback_keys as $fallback) {
                    if (isset($all_meta[$post_id][$fallback]) && !empty($all_meta[$post_id][$fallback])) {
                        $value = $all_meta[$post_id][$fallback];
                        break;
                    }
                }
            }
            return $value;
        };
        
        foreach($query->posts as $post) {
            $post_id = $post->ID;
            $status_obj = get_post_status_object($post->post_status);
            $status_label = $status_obj ? $status_obj->label : $post->post_status;
            
            $terms = wp_get_post_terms( $post->ID, 'activity_category', array( 'fields' => 'names' ) );
            $category = ! empty( $terms ) ? implode( ', ', array_map( 'sanitize_text_field', $terms ) ) : '';

            // Use optimized meta retrieval with fallbacks
            $city = $get_meta_with_fallback($post_id, 'city') ?: $get_meta_with_fallback($post_id, 'comune');
            $website = $get_meta_with_fallback($post_id, 'website', array('sito', 'sito_web', 'web', 'url', '_ab_csv_website'));
            $facebook = $get_meta_with_fallback($post_id, 'facebook', array('facebook_url', 'fb', '_ab_csv_facebook'));
            $instagram = $get_meta_with_fallback($post_id, 'instagram', array('instagram_url', 'ig', '_ab_csv_instagram'));
            $youtube = $get_meta_with_fallback($post_id, 'youtube');
            $tiktok = $get_meta_with_fallback($post_id, 'tiktok');
            $x_link = $get_meta_with_fallback($post_id, 'x');

            fputcsv($output, array(
                $post->ID,
                html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                $get_meta_with_fallback($post_id, 'email'),
                $get_meta_with_fallback($post_id, 'codice_fiscale'),
                $get_meta_with_fallback($post_id, 'phone'),
                $get_meta_with_fallback($post_id, 'address'),
                $city,
                $get_meta_with_fallback($post_id, 'province'),
                $get_meta_with_fallback($post_id, 'region') ?: $get_meta_with_fallback($post_id, 'regione'),
                $get_meta_with_fallback($post_id, 'cap'),
                $website,
                $facebook,
                $instagram,
                $youtube,
                $tiktok,
                $x_link,
                $category,
                $status_label,
                $post->post_date
            ));
        }
    } elseif ( $type === 'cronologia' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'culturacsi_audit_log';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
            $where = "1=1";
            if ( ! $is_site_admin ) {
                $assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
                if ( $assoc_id > 0 ) {
                    $allowed_objects = array( "(l.object_type = 'association' AND l.object_id = " . (int) $assoc_id . ")" );
                    
                    $event_ids = get_posts( array( 'post_type' => 'event', 'meta_query' => array( array( 'key' => 'organizer_association_id', 'value' => $assoc_id ) ), 'fields' => 'ids', 'posts_per_page' => -1 ) );
                    if ( ! empty( $event_ids ) ) {
                        $allowed_objects[] = "(l.object_type = 'event' AND l.object_id IN (" . implode( ',', array_map( 'intval', $event_ids ) ) . "))";
                    }
                    
                    $news_ids = get_posts( array( 'post_type' => 'news', 'meta_query' => array( array( 'key' => 'organizer_association_id', 'value' => $assoc_id ) ), 'fields' => 'ids', 'posts_per_page' => -1 ) );
                    if ( ! empty( $news_ids ) ) {
                        $allowed_objects[] = "(l.object_type = 'news' AND l.object_id IN (" . implode( ',', array_map( 'intval', $news_ids ) ) . "))";
                    }
                    
                    $user_ids = get_users( array( 'meta_query' => array( array( 'key' => 'association_post_id', 'value' => $assoc_id ) ), 'fields' => 'ID' ) );
                    if ( ! empty( $user_ids ) ) {
                        $allowed_objects[] = "(l.object_type = 'user' AND l.object_id IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . "))";
                    }
                    
                    $where .= " AND (" . implode( " OR ", $allowed_objects ) . ")";
                } else {
                    $where .= " AND 0=1";
                }
            }

            fputcsv($output, array('ID', 'Data e Ora', 'Azione', 'Tipo Oggetto', 'ID Oggetto', 'Dettagli', 'Utente Responsabile', 'IP Utente'));
            $logs = $wpdb->get_results( 
                "SELECT l.*, u.display_name as user_name 
                 FROM $table_name l 
                 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
                 WHERE $where 
                 ORDER BY l.created_at DESC" 
            );
            $action_labels = array(
                'create_post' => 'CREATO',
                'update_post' => 'MODIFICATO',
                'trash_post'  => 'ELIMINATO',
                'wp_insert_user' => 'REGISTRATO (UTENTE)',
                'update_user' => 'MODIFICATO (UTENTE)',
                'approve'     => 'APPROVATO',
                'reject'      => 'RIFIUTATO',
                'hold'        => 'IN ATTESA',
                'login'       => 'ACCESSO (LOGIN)'
            );
            foreach($logs as $log) {
                $display_action = isset( $action_labels[ $log->action ] ) ? $action_labels[ $log->action ] : strtoupper( $log->action );
                $display_type = $log->object_type;
                if ( $display_type === 'event' ) $display_type = 'Evento';
                elseif ( $display_type === 'news' ) $display_type = 'Notizia';
                elseif ( $display_type === 'user' ) $display_type = 'Utente';
                elseif ( $display_type === 'association' ) $display_type = 'Associazione';

                fputcsv($output, array(
                    $log->id,
                    $log->created_at,
                    $display_action,
                    $display_type,
                    $log->object_id,
                    $log->details,
                    $log->user_name ?: 'Utente ' . $log->user_id,
                    $log->ip_address
                ));
            }
        }
    }

    fclose($output);
    exit;
}
