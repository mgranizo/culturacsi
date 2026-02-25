<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('init', 'culturacsi_export_csv_handler');
function culturacsi_export_csv_handler() {
    if ( ! isset( $_GET['culturacsi_export'] ) ) {
        return;
    }

    if ( ! function_exists('culturacsi_portal_can_access') || ! culturacsi_portal_can_access() ) {
        wp_die('Accesso negato.');
    }

    $type = sanitize_key( $_GET['culturacsi_export'] );
    
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

        fputcsv($output, array('ID', 'Titolo', 'Data Evento', 'Stato', 'Luogo', 'Citta', 'Autore', 'Data Creazione'));
        
        $query = new WP_Query($args);
        foreach($query->posts as $post) {
            $status_obj = get_post_status_object($post->post_status);
            $status_label = $status_obj ? $status_obj->label : $post->post_status;
            fputcsv($output, array(
                $post->ID,
                html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                get_post_meta($post->ID, 'start_date', true),
                $status_label,
                get_post_meta($post->ID, 'venue_name', true),
                get_post_meta($post->ID, 'city', true) ?: get_post_meta($post->ID, 'comune', true),
                get_the_author_meta('display_name', $post->post_author),
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

        fputcsv($output, array('ID', 'Titolo', 'Data', 'Stato', 'Link Esterno', 'Autore', 'Associazione ID'));
        $query = new WP_Query($args);
        foreach($query->posts as $post) {
            $status_obj = get_post_status_object($post->post_status);
            $status_label = $status_obj ? $status_obj->label : $post->post_status;
            fputcsv($output, array(
                $post->ID,
                html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                $post->post_date,
                $status_label,
                get_post_meta($post->ID, '_hebeae_external_url', true),
                get_the_author_meta('display_name', $post->post_author),
                get_post_meta($post->ID, 'organizer_association_id', true)
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

        fputcsv($output, array('ID', 'Nome', 'Cognome', 'Username', 'Email', 'Ruoli', 'Stato', 'Codice Fiscale', 'Societa', 'Telefono', 'Data Registrazione'));
        foreach($users as $user) {
            $roles = implode(', ', $user->roles);
            $status_label = culturacsi_portal_user_approval_label($user);
            fputcsv($output, array(
                $user->ID,
                get_user_meta($user->ID, 'first_name', true),
                get_user_meta($user->ID, 'last_name', true),
                $user->user_login,
                $user->user_email,
                $roles,
                $status_label,
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

        fputcsv($output, array('ID', 'Ragione Sociale', 'Email', 'Codice Fiscale / PIVA', 'Telefono', 'Indirizzo', 'Citta', 'Provincia', 'Regione', 'CAP', 'Categoria', 'Stato', 'Data Inserimento'));
        $query = new WP_Query($query_args);
        foreach($query->posts as $post) {
            $status_obj = get_post_status_object($post->post_status);
            $status_label = $status_obj ? $status_obj->label : $post->post_status;
            
            $terms = wp_get_post_terms( $post->ID, 'activity_category', array( 'fields' => 'names' ) );
            $category = ! empty( $terms ) ? implode( ', ', array_map( 'sanitize_text_field', $terms ) ) : '';

            $city = get_post_meta($post->ID, 'city', true) ?: get_post_meta($post->ID, 'comune', true);

            fputcsv($output, array(
                $post->ID,
                html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
                get_post_meta($post->ID, 'email', true),
                get_post_meta($post->ID, 'codice_fiscale', true),
                get_post_meta($post->ID, 'phone', true),
                get_post_meta($post->ID, 'address', true),
                $city,
                get_post_meta($post->ID, 'province', true),
                get_post_meta($post->ID, 'region', true) ?: get_post_meta($post->ID, 'regione', true),
                get_post_meta($post->ID, 'cap', true),
                $category,
                $status_label,
                $post->post_date
            ));
        }
    } elseif ( $type === 'cronologia' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'culturacsi_audit_log';
        if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name ) {
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
