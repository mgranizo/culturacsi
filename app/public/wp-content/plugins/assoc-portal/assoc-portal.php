<?php
/**
 * Plugin Name:       Association Portal
 * Plugin URI:        https://culturacsi.it/
 * Description:       Provides a portal for associations to manage their profiles and events, including a calendar.
 * Version:           1.0.0
 * Author:            Gemini AI
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       assoc-portal
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register Custom Post Types (Association, Event).
 */
function assoc_portal_register_post_types() {
    // CPT: Association
    $association_labels = [
        'name'                  => _x( 'Associations', 'Post Type General Name', 'assoc-portal' ),
        'singular_name'         => _x( 'Association', 'Post Type Singular Name', 'assoc-portal' ),
        'menu_name'             => __( 'Associations', 'assoc-portal' ),
        'name_admin_bar'        => __( 'Association', 'assoc-portal' ),
        'archives'              => __( 'Association Archives', 'assoc-portal' ),
        'attributes'            => __( 'Association Attributes', 'assoc-portal' ),
        'parent_item_colon'     => __( 'Parent Association:', 'assoc-portal' ),
        'all_items'             => __( 'All Associations', 'assoc-portal' ),
        'add_new_item'          => __( 'Add New Association', 'assoc-portal' ),
        'add_new'               => __( 'Add New', 'assoc-portal' ),
        'new_item'              => __( 'New Association', 'assoc-portal' ),
        'edit_item'             => __( 'Edit Association', 'assoc-portal' ),
        'update_item'           => __( 'Update Association', 'assoc-portal' ),
        'view_item'             => __( 'View Association', 'assoc-portal' ),
        'view_items'            => __( 'View Associations', 'assoc-portal' ),
        'search_items'          => __( 'Search Association', 'assoc-portal' ),
        'not_found'             => __( 'Not found', 'assoc-portal' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'assoc-portal' ),
        'featured_image'        => __( 'Logo', 'assoc-portal' ),
        'set_featured_image'    => __( 'Set logo', 'assoc-portal' ),
        'remove_featured_image' => __( 'Remove logo', 'assoc-portal' ),
        'use_featured_image'    => __( 'Use as logo', 'assoc-portal' ),
        'insert_into_item'      => __( 'Insert into association', 'assoc-portal' ),
        'uploaded_to_this_item' => __( 'Uploaded to this association', 'assoc-portal' ),
        'items_list'            => __( 'Associations list', 'assoc-portal' ),
        'items_list_navigation' => __( 'Associations list navigation', 'assoc-portal' ),
        'filter_items_list'     => __( 'Filter associations list', 'assoc-portal' ),
    ];
    $association_args = [
        'label'                 => __( 'Association', 'assoc-portal' ),
        'description'           => __( 'Association profiles', 'assoc-portal' ),
        'labels'                => $association_labels,
        'supports'              => [ 'title', 'thumbnail', 'revisions' ],
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-groups',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => 'associazioni',
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
        'rewrite'               => [ 'slug' => 'associazione' ],
    ];
    register_post_type( 'association', $association_args );

    // CPT: Event
    $event_labels = [
        'name'                  => _x( 'Events', 'Post Type General Name', 'assoc-portal' ),
        'singular_name'         => _x( 'Event', 'Post Type Singular Name', 'assoc-portal' ),
        'menu_name'             => __( 'Events', 'assoc-portal' ),
        'name_admin_bar'        => __( 'Event', 'assoc-portal' ),
        'archives'              => __( 'Event Archives', 'assoc-portal' ),
        'attributes'            => __( 'Event Attributes', 'assoc-portal' ),
        'parent_item_colon'     => __( 'Parent Event:', 'assoc-portal' ),
        'all_items'             => __( 'All Events', 'assoc-portal' ),
        'add_new_item'          => __( 'Add New Event', 'assoc-portal' ),
        'add_new'               => __( 'Add New', 'assoc-portal' ),
        'new_item'              => __( 'New Event', 'assoc-portal' ),
        'edit_item'             => __( 'Edit Event', 'assoc-portal' ),
        'update_item'           => __( 'Update Event', 'assoc-portal' ),
        'view_item'             => __( 'View Event', 'assoc-portal' ),
        'view_items'            => __( 'View Events', 'assoc-portal' ),
        'search_items'          => __( 'Search Event', 'assoc-portal' ),
    ];
    $event_args = [
        'label'                 => __( 'Event', 'assoc-portal' ),
        'description'           => __( 'Events submitted by associations', 'assoc-portal' ),
        'labels'                => $event_labels,
        'supports'              => [ 'title', 'thumbnail', 'author', 'editor' ],
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 6,
        'menu_icon'             => 'dashicons-calendar-alt',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => 'calendario',
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
        'rewrite'               => [ 'slug' => 'evento', 'with_front' => false ],
    ];
    register_post_type( 'event', $event_args );
}
add_action( 'init', 'assoc_portal_register_post_types', 0 );

/**
 * Register News CPT.
 */
function assoc_portal_register_news_cpt() {
    $labels = [
        'name'                  => _x( 'News', 'Post Type General Name', 'assoc-portal' ),
        'singular_name'         => _x( 'News Item', 'Post Type Singular Name', 'assoc-portal' ),
        'menu_name'             => __( 'News', 'assoc-portal' ),
        'name_admin_bar'        => __( 'News Item', 'assoc-portal' ),
        'archives'              => __( 'News Archives', 'assoc-portal' ),
        'attributes'            => __( 'News Item Attributes', 'assoc-portal' ),
        'parent_item_colon'     => __( 'Parent News Item:', 'assoc-portal' ),
        'all_items'             => __( 'All News', 'assoc-portal' ),
        'add_new_item'          => __( 'Add New News Item', 'assoc-portal' ),
        'add_new'               => __( 'Add New', 'assoc-portal' ),
        'new_item'              => __( 'New News Item', 'assoc-portal' ),
        'edit_item'             => __( 'Edit News Item', 'assoc-portal' ),
        'update_item'           => __( 'Update News Item', 'assoc-portal' ),
        'view_item'             => __( 'View News Item', 'assoc-portal' ),
        'view_items'            => __( 'View News Items', 'assoc-portal' ),
        'search_items'          => __( 'Search News', 'assoc-portal' ),
    ];
    $args = [
        'label'                 => __( 'News', 'assoc-portal' ),
        'description'           => __( 'News articles', 'assoc-portal' ),
        'labels'                => $labels,
        'supports'              => [ 'title', 'editor', 'thumbnail', 'excerpt', 'author' ],
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 7,
        'menu_icon'             => 'dashicons-megaphone',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => 'notizie',
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post',
        'show_in_rest'          => true,
        'rewrite'               => [ 'slug' => 'notizia' ],
    ];
    register_post_type( 'news', $args );
}
add_action( 'init', 'assoc_portal_register_news_cpt', 0 );

/**
 * Register Taxonomies (activity_category, event_type).
 */
function assoc_portal_register_taxonomies() {
    // Taxonomy: Activity Category
    $activity_labels = [
        'name'              => _x( 'Activity Categories', 'taxonomy general name', 'assoc-portal' ),
        'singular_name'     => _x( 'Activity Category', 'taxonomy singular name', 'assoc-portal' ),
        'search_items'      => __( 'Search Categories', 'assoc-portal' ),
        'all_items'         => __( 'All Categories', 'assoc-portal' ),
        'parent_item'       => __( 'Parent Category', 'assoc-portal' ),
        'parent_item_colon' => __( 'Parent Category:', 'assoc-portal' ),
        'edit_item'         => __( 'Edit Category', 'assoc-portal' ),
        'update_item'       => __( 'Update Category', 'assoc-portal' ),
        'add_new_item'      => __( 'Add New Category', 'assoc-portal' ),
        'new_item_name'     => __( 'New Category Name', 'assoc-portal' ),
        'menu_name'         => __( 'Activity Categories', 'assoc-portal' ),
    ];
    $activity_args = [
        'hierarchical'      => true, // Hierarchical (3 levels)
        'labels'            => $activity_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'categoria-attivita' ],
        'show_in_rest'      => true,
    ];
    register_taxonomy( 'activity_category', [ 'association' ], $activity_args );

    // Taxonomy: Event Type
    $event_type_labels = [
        'name'              => _x( 'Event Types', 'taxonomy general name', 'assoc-portal' ),
        'singular_name'     => _x( 'Event Type', 'taxonomy singular name', 'assoc-portal' ),
        'search_items'      => __( 'Search Event Types', 'assoc-portal' ),
        'all_items'         => __( 'All Event Types', 'assoc-portal' ),
        'edit_item'         => __( 'Edit Event Type', 'assoc-portal' ),
        'update_item'       => __( 'Update Event Type', 'assoc-portal' ),
        'add_new_item'      => __( 'Add New Event Type', 'assoc-portal' ),
        'new_item_name'     => __( 'New Event Type Name', 'assoc-portal' ),
        'menu_name'         => __( 'Event Types', 'assoc-portal' ),
    ];
    $event_type_args = [
        'hierarchical'      => false,
        'labels'            => $event_type_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'tipo-evento' ],
        'show_in_rest'      => true,
    ];
    register_taxonomy( 'event_type', [ 'event' ], $event_type_args );
}
add_action( 'init', 'assoc_portal_register_taxonomies', 0 );

/**
 * Add custom user role 'Association Manager'.
 */
function assoc_portal_add_role() {
    // Check if the role already exists to avoid errors on reactivation.
    if ( ! get_role( 'association_manager' ) ) {
        add_role(
            'association_manager',
            __( 'Association Manager', 'assoc-portal' ),
            [
                'read'         => true,  // Basic access to read posts.
                'upload_files' => true,  // Allows uploading images for profiles and events.
            ]
        );
    }

    // Registration role: users must be approved by an admin before they can manage associations.
    if ( ! get_role( 'association_pending' ) ) {
        add_role(
            'association_pending',
            __( 'Association Pending Approval', 'assoc-portal' ),
            [
                'read' => true,
            ]
        );
    }
}
register_activation_hook( __FILE__, 'assoc_portal_add_role' );

/**
 * Ensure custom roles also exist on normal requests (not only after activation).
 */
function assoc_portal_ensure_roles() {
    assoc_portal_add_role();
}
add_action( 'init', 'assoc_portal_ensure_roles', 1 );

/**
 * Flush rewrite rules on activation to ensure CPT/taxonomy slugs work.
 */
function assoc_portal_activate() {
    // Register post types and taxonomies first
    assoc_portal_register_post_types();
    assoc_portal_register_news_cpt();
    assoc_portal_register_taxonomies();
    // Then flush the rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'assoc_portal_activate' );

/**
 * Flush rewrite rules on deactivation as well.
 */
function assoc_portal_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'assoc_portal_deactivate' );

/**
 * Auth notices shown in the reserved-area modal.
 */
function assoc_portal_auth_notice_catalog() {
    return [
        'auth_nonce'                => [ 'type' => 'error',   'tab' => 'login',    'message' => __( 'Sessione scaduta. Riprova.', 'assoc-portal' ) ],
        'login_missing'             => [ 'type' => 'error',   'tab' => 'login',    'message' => __( 'Inserisci username o email e password.', 'assoc-portal' ) ],
        'login_invalid'             => [ 'type' => 'error',   'tab' => 'login',    'message' => __( 'Credenziali non valide.', 'assoc-portal' ) ],
        'login_pending'             => [ 'type' => 'warning', 'tab' => 'login',    'message' => __( "L'account e stato creato ma deve essere approvato da un amministratore.", 'assoc-portal' ) ],
        'login_hold'                => [ 'type' => 'warning', 'tab' => 'login',    'message' => __( "Il tuo account e momentaneamente in pausa. Contatta un amministratore per assistenza.", 'assoc-portal' ) ],
        'login_rejected'            => [ 'type' => 'error',   'tab' => 'login',    'message' => __( "L'accesso a questo account e stato revocato.", 'assoc-portal' ) ],
        'register_missing'          => [ 'type' => 'error',   'tab' => 'register', 'message' => __( 'Compila tutti i campi obbligatori.', 'assoc-portal' ) ],
        'register_email_invalid'    => [ 'type' => 'error',   'tab' => 'register', 'message' => __( 'Inserisci un indirizzo email valido.', 'assoc-portal' ) ],
        'register_username_invalid' => [ 'type' => 'error',   'tab' => 'register', 'message' => __( 'Scegli uno username valido.', 'assoc-portal' ) ],
        'register_username_exists'  => [ 'type' => 'error',   'tab' => 'register', 'message' => __( 'Questo username e gia in uso.', 'assoc-portal' ) ],
        'register_email_exists'     => [ 'type' => 'error',   'tab' => 'register', 'message' => __( 'Questa email e gia registrata.', 'assoc-portal' ) ],
        'register_password_mismatch'=> [ 'type' => 'error',   'tab' => 'register', 'message' => __( 'Le password non coincidono.', 'assoc-portal' ) ],
        'register_password_weak'    => [ 'type' => 'error',   'tab' => 'register', 'message' => __( 'La password deve avere almeno 8 caratteri.', 'assoc-portal' ) ],
        'register_failed'           => [ 'type' => 'error',   'tab' => 'register', 'message' => __( 'Registrazione non riuscita. Riprova.', 'assoc-portal' ) ],
        'register_success'          => [ 'type' => 'success', 'tab' => 'login',    'message' => __( 'Registrazione inviata. Un amministratore deve approvare il tuo account prima del primo accesso.', 'assoc-portal' ) ],
        'recover_missing'           => [ 'type' => 'error',   'tab' => 'recover',  'message' => __( 'Inserisci username o email.', 'assoc-portal' ) ],
        'recover_sent'              => [ 'type' => 'success', 'tab' => 'recover',  'message' => __( 'Se l\'utente esiste, abbiamo inviato un link per il recupero password.', 'assoc-portal' ) ],
        'recover_failed'            => [ 'type' => 'error',   'tab' => 'recover',  'message' => __( 'Impossibile avviare il recupero password. Controlla i dati inseriti.', 'assoc-portal' ) ],
    ];
}

/**
 * Return notice metadata for auth messages.
 */
function assoc_portal_get_auth_notice( $code ) {
    $catalog = assoc_portal_auth_notice_catalog();
    return isset( $catalog[ $code ] ) ? $catalog[ $code ] : null;
}

/**
 * Redirect back to the homepage and reopen the auth modal with a specific tab/notice.
 */
function assoc_portal_auth_redirect( $notice_code, $tab = 'login' ) {
    $notice = assoc_portal_get_auth_notice( $notice_code );
    if ( $notice && isset( $notice['tab'] ) ) {
        $tab = $notice['tab'];
    }
    if ( ! in_array( $tab, [ 'login', 'register', 'recover' ], true ) ) {
        $tab = 'login';
    }

    $redirect_url = wp_get_referer();
    if ( ! $redirect_url ) {
        $redirect_url = home_url( '/' );
    }

    $redirect_url = add_query_arg(
        [
            'area_riservata_login' => '1',
            'auth'                 => $tab,
            'assoc_notice'         => sanitize_key( (string) $notice_code ),
        ],
        $redirect_url
    );

    wp_safe_redirect( $redirect_url, 302 );
    exit;
}

/**
 * Whether a user must still be approved by an admin before accessing the reserved area.
 */
function assoc_portal_user_requires_approval( $user ) {
    if ( ! ( $user instanceof WP_User ) ) {
        return false;
    }

    if ( user_can( $user, 'manage_options' ) ) {
        return false;
    }

    $state = (string) get_user_meta( $user->ID, 'assoc_moderation_state', true );
    if ( in_array( $state, [ 'pending', 'hold', 'rejected' ], true ) ) {
        return true;
    }

    if ( in_array( 'association_pending', (array) $user->roles, true ) ) {
        return true;
    }

    return '1' === (string) get_user_meta( $user->ID, 'assoc_pending_approval', true );
}

/**
 * If an admin changes role from pending to another role, clear the pending flag.
 */
function assoc_portal_clear_pending_on_role_change( $user_id, $role ) {
    if ( 'association_pending' !== $role ) {
        delete_user_meta( $user_id, 'assoc_pending_approval' );
    }
}
add_action( 'set_user_role', 'assoc_portal_clear_pending_on_role_change', 10, 2 );

/**
 * Process login/register/recovery submissions from the reserved-area modal.
 */
function assoc_portal_process_reserved_auth_post() {
    if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
        return;
    }
    if ( ! isset( $_POST['assoc_auth_action'] ) ) {
        return;
    }

    $action = sanitize_key( wp_unslash( $_POST['assoc_auth_action'] ) );
    if ( ! in_array( $action, [ 'login', 'register', 'recover' ], true ) ) {
        return;
    }

    if ( 'login' === $action ) {
        if ( ! isset( $_POST['assoc_auth_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['assoc_auth_nonce'] ) ), 'assoc_auth_login' ) ) {
            assoc_portal_auth_redirect( 'auth_nonce', 'login' );
        }

        $identifier = isset( $_POST['assoc_login_identifier'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['assoc_login_identifier'] ) ) ) : '';
        $password   = isset( $_POST['assoc_login_password'] ) ? (string) wp_unslash( $_POST['assoc_login_password'] ) : '';
        $remember   = ! empty( $_POST['rememberme'] );

        if ( '' === $identifier || '' === $password ) {
            assoc_portal_auth_redirect( 'login_missing', 'login' );
        }

        $login_name = $identifier;
        if ( is_email( $identifier ) ) {
            $found_user = get_user_by( 'email', $identifier );
            if ( ! $found_user instanceof WP_User ) {
                assoc_portal_auth_redirect( 'login_invalid', 'login' );
            }
            $login_name = $found_user->user_login;
        }

        $creds = [
            'user_login'    => $login_name,
            'user_password' => $password,
            'remember'      => $remember,
        ];

        $signed_user = wp_signon( $creds, is_ssl() );
        if ( is_wp_error( $signed_user ) || ! ( $signed_user instanceof WP_User ) ) {
            assoc_portal_auth_redirect( 'login_invalid', 'login' );
        }

        if ( assoc_portal_user_requires_approval( $signed_user ) ) {
            wp_logout();
            $state = (string) get_user_meta( $signed_user->ID, 'assoc_moderation_state', true );
            if ( 'rejected' === $state ) {
                assoc_portal_auth_redirect( 'login_rejected', 'login' );
            } elseif ( 'hold' === $state ) {
                assoc_portal_auth_redirect( 'login_hold', 'login' );
            }
            assoc_portal_auth_redirect( 'login_pending', 'login' );
        }

        if ( user_can( $signed_user, 'manage_options' ) ) {
            wp_safe_redirect( add_query_arg( 'login_v', time(), home_url( '/area-riservata/amministrazione/' ) ), 302 );
            exit;
        }

        wp_safe_redirect( add_query_arg( 'login_v', time(), home_url( '/area-riservata/' ) ), 302 );
        exit;
    }

    if ( 'register' === $action ) {
        if ( ! isset( $_POST['assoc_auth_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['assoc_auth_nonce'] ) ), 'assoc_auth_register' ) ) {
            assoc_portal_auth_redirect( 'auth_nonce', 'register' );
        }

        $first_name  = isset( $_POST['assoc_reg_first_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['assoc_reg_first_name'] ) ) ) : '';
        $last_name   = isset( $_POST['assoc_reg_last_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['assoc_reg_last_name'] ) ) ) : '';
        $email       = isset( $_POST['assoc_reg_email'] ) ? trim( sanitize_email( wp_unslash( $_POST['assoc_reg_email'] ) ) ) : '';
        $raw_user    = isset( $_POST['assoc_reg_username'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['assoc_reg_username'] ) ) ) : '';
        $username    = sanitize_user( $raw_user, true );
        $password    = isset( $_POST['assoc_reg_password'] ) ? (string) wp_unslash( $_POST['assoc_reg_password'] ) : '';
        $password_2  = isset( $_POST['assoc_reg_password_confirm'] ) ? (string) wp_unslash( $_POST['assoc_reg_password_confirm'] ) : '';

        if ( $raw_user !== $username ) {
            assoc_portal_auth_redirect( 'register_username_invalid', 'register' );
        }

        if ( '' === $first_name || '' === $last_name || '' === $email || '' === $username || '' === $password || '' === $password_2 ) {
            assoc_portal_auth_redirect( 'register_missing', 'register' );
        }
        if ( ! is_email( $email ) ) {
            assoc_portal_auth_redirect( 'register_email_invalid', 'register' );
        }
        if ( '' === $username ) {
            assoc_portal_auth_redirect( 'register_username_invalid', 'register' );
        }
        if ( username_exists( $username ) ) {
            assoc_portal_auth_redirect( 'register_username_exists', 'register' );
        }
        if ( email_exists( $email ) ) {
            assoc_portal_auth_redirect( 'register_email_exists', 'register' );
        }
        if ( $password !== $password_2 ) {
            assoc_portal_auth_redirect( 'register_password_mismatch', 'register' );
        }
        if ( strlen( $password ) < 8 ) {
            assoc_portal_auth_redirect( 'register_password_weak', 'register' );
        }

        $new_user_id = wp_create_user( $username, $password, $email );
        if ( is_wp_error( $new_user_id ) || (int) $new_user_id <= 0 ) {
            assoc_portal_auth_redirect( 'register_failed', 'register' );
        }

        wp_update_user(
            [
                'ID'           => (int) $new_user_id,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim( $first_name . ' ' . $last_name ),
            ]
        );

        $new_user = get_user_by( 'id', (int) $new_user_id );
        if ( $new_user instanceof WP_User ) {
            $new_user->set_role( 'association_pending' );
        }
        update_user_meta( (int) $new_user_id, 'assoc_pending_approval', '1' );

        $admin_email = get_option( 'admin_email' );
        if ( is_email( $admin_email ) ) {
            $user_edit_link = admin_url( 'user-edit.php?user_id=' . (int) $new_user_id );
            $subject = __( 'Nuova registrazione in attesa di approvazione', 'assoc-portal' );
            $message = sprintf(
                "%s\n\n%s: %s\n%s: %s\n%s: %s\n\n%s\n%s",
                __( 'E arrivata una nuova richiesta di registrazione.', 'assoc-portal' ),
                __( 'Nome', 'assoc-portal' ),
                trim( $first_name . ' ' . $last_name ),
                __( 'Email', 'assoc-portal' ),
                $email,
                __( 'Username', 'assoc-portal' ),
                $username,
                __( 'Apri il profilo utente per approvare assegnando un ruolo operativo (es. Association Manager):', 'assoc-portal' ),
                $user_edit_link
            );
            wp_mail( $admin_email, $subject, $message );
        }

        assoc_portal_auth_redirect( 'register_success', 'login' );
    }

    if ( 'recover' === $action ) {
        if ( ! isset( $_POST['assoc_auth_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['assoc_auth_nonce'] ) ), 'assoc_auth_recover' ) ) {
            assoc_portal_auth_redirect( 'auth_nonce', 'recover' );
        }

        $identifier = isset( $_POST['assoc_recover_identifier'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['assoc_recover_identifier'] ) ) ) : '';
        if ( '' === $identifier ) {
            assoc_portal_auth_redirect( 'recover_missing', 'recover' );
        }

        if ( ! function_exists( 'retrieve_password' ) ) {
            require_once ABSPATH . 'wp-login.php';
        }
        retrieve_password( $identifier );
        
        // Always redirect to 'recover_sent' regardless of success or failure.
        // This prevents timing or response based username enumeration attacks.
        assoc_portal_auth_redirect( 'recover_sent', 'recover' );
    }
}
add_action( 'template_redirect', 'assoc_portal_process_reserved_auth_post', 5 );

/**
 * Shortcode: [assoc_reserved_access]
 * Login / register / password recovery tabs used inside the Area Riservata modal.
 */
function assoc_portal_reserved_access_shortcode() {
    if ( is_user_logged_in() ) {
        return '<div class="assoc-auth-wrap"><div class="assoc-admin-notice assoc-admin-notice-success">' .
            esc_html__( 'Sei gia autenticato.', 'assoc-portal' ) . ' <a href="' . esc_url( home_url( '/area-riservata/' ) ) . '">' .
            esc_html__( 'Vai all\'area riservata', 'assoc-portal' ) . '</a></div></div>';
    }

    $active_tab = isset( $_GET['auth'] ) ? sanitize_key( wp_unslash( $_GET['auth'] ) ) : 'login';
    if ( ! in_array( $active_tab, [ 'login', 'register', 'recover' ], true ) ) {
        $active_tab = 'login';
    }

    $notice_code = isset( $_GET['assoc_notice'] ) ? sanitize_key( wp_unslash( $_GET['assoc_notice'] ) ) : '';
    $notice      = assoc_portal_get_auth_notice( $notice_code );
    if ( $notice && isset( $notice['tab'] ) && in_array( $notice['tab'], [ 'login', 'register', 'recover' ], true ) ) {
        $active_tab = $notice['tab'];
    }

    $notice_class = 'assoc-admin-notice';
    if ( $notice && isset( $notice['type'] ) ) {
        $notice_class .= ' assoc-admin-notice-' . sanitize_html_class( $notice['type'] );
    }

    ob_start();
    ?>
    <div class="assoc-auth-wrap" data-assoc-auth-wrap>
        <div class="assoc-auth-tabs" role="tablist" aria-label="<?php echo esc_attr__( 'Accesso area riservata', 'assoc-portal' ); ?>">
            <button type="button" class="assoc-auth-tab <?php echo 'login' === $active_tab ? 'is-active' : ''; ?>" data-auth-tab="login" aria-pressed="<?php echo 'login' === $active_tab ? 'true' : 'false'; ?>">
                <?php esc_html_e( 'Login', 'assoc-portal' ); ?>
            </button>
            <button type="button" class="assoc-auth-tab <?php echo 'register' === $active_tab ? 'is-active' : ''; ?>" data-auth-tab="register" aria-pressed="<?php echo 'register' === $active_tab ? 'true' : 'false'; ?>">
                <?php esc_html_e( 'Registrati', 'assoc-portal' ); ?>
            </button>
            <button type="button" class="assoc-auth-tab <?php echo 'recover' === $active_tab ? 'is-active' : ''; ?>" data-auth-tab="recover" aria-pressed="<?php echo 'recover' === $active_tab ? 'true' : 'false'; ?>">
                <?php esc_html_e( 'Recupero Password', 'assoc-portal' ); ?>
            </button>
        </div>

        <?php if ( $notice ) : ?>
            <div class="<?php echo esc_attr( $notice_class ); ?>">
                <?php echo esc_html( $notice['message'] ); ?>
            </div>
        <?php endif; ?>

        <div class="assoc-auth-pane <?php echo 'login' === $active_tab ? 'is-active' : ''; ?>" data-auth-pane="login">
            <form class="assoc-portal-form assoc-auth-form" method="post" action="" data-auth-form="login">
                <input type="hidden" name="testcookie" value="1">
                <h3><?php esc_html_e( "Accedi all'Area Riservata", 'assoc-portal' ); ?></h3>
                <p>
                    <label for="assoc_login_identifier"><?php esc_html_e( 'Username o Email', 'assoc-portal' ); ?></label>
                    <input type="text" id="assoc_login_identifier" name="assoc_login_identifier" required autocomplete="username">
                </p>
                <p>
                    <label for="assoc_login_password"><?php esc_html_e( 'Password', 'assoc-portal' ); ?></label>
                    <input type="password" id="assoc_login_password" name="assoc_login_password" required autocomplete="current-password">
                </p>
                <p>
                    <label><input type="checkbox" name="rememberme" value="1"> <?php esc_html_e( 'Ricordami', 'assoc-portal' ); ?></label>
                </p>
                <p>
                    <input type="hidden" name="assoc_auth_action" value="login">
                    <?php wp_nonce_field( 'assoc_auth_login', 'assoc_auth_nonce' ); ?>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Accedi', 'assoc-portal' ); ?></button>
                </p>
            </form>
        </div>

        <div class="assoc-auth-pane <?php echo 'register' === $active_tab ? 'is-active' : ''; ?>" data-auth-pane="register">
            <form class="assoc-portal-form assoc-auth-form" method="post" action="" data-auth-form="register" novalidate>
                <input type="hidden" name="testcookie" value="1">
                <h3><?php esc_html_e( 'Crea un Account', 'assoc-portal' ); ?></h3>
                <p class="description"><?php esc_html_e( "La registrazione deve essere approvata da un amministratore prima dell'accesso.", 'assoc-portal' ); ?></p>
                <p>
                    <label for="assoc_reg_first_name"><?php esc_html_e( 'Nome', 'assoc-portal' ); ?></label>
                    <input type="text" id="assoc_reg_first_name" name="assoc_reg_first_name" required autocomplete="given-name">
                </p>
                <p>
                    <label for="assoc_reg_last_name"><?php esc_html_e( 'Cognome', 'assoc-portal' ); ?></label>
                    <input type="text" id="assoc_reg_last_name" name="assoc_reg_last_name" required autocomplete="family-name">
                </p>
                <p>
                    <label for="assoc_reg_email"><?php esc_html_e( 'Email', 'assoc-portal' ); ?></label>
                    <input type="email" id="assoc_reg_email" name="assoc_reg_email" required autocomplete="email">
                </p>
                <p>
                    <label for="assoc_reg_username"><?php esc_html_e( 'Username', 'assoc-portal' ); ?></label>
                    <input type="text" id="assoc_reg_username" name="assoc_reg_username" required autocomplete="username">
                </p>
                <p>
                    <label for="assoc_reg_password"><?php esc_html_e( 'Password', 'assoc-portal' ); ?></label>
                    <input type="password" id="assoc_reg_password" name="assoc_reg_password" required autocomplete="new-password">
                </p>
                <p>
                    <label for="assoc_reg_password_confirm"><?php esc_html_e( 'Conferma Password', 'assoc-portal' ); ?></label>
                    <input type="password" id="assoc_reg_password_confirm" name="assoc_reg_password_confirm" required autocomplete="new-password">
                </p>
                <p class="assoc-password-strength-row">
                    <span class="assoc-password-strength-label"><?php esc_html_e( 'Sicurezza password', 'assoc-portal' ); ?></span>
                    <span id="assoc-password-strength-meter" class="assoc-password-strength-meter is-empty">-<?php echo esc_html__( 'Nessuna', 'assoc-portal' ); ?></span>
                </p>
                <p id="assoc-auth-live-error" class="assoc-auth-live-error" aria-live="polite"></p>
                <p>
                    <input type="hidden" name="assoc_auth_action" value="register">
                    <?php wp_nonce_field( 'assoc_auth_register', 'assoc_auth_nonce' ); ?>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Registrati', 'assoc-portal' ); ?></button>
                </p>
            </form>
        </div>

        <div class="assoc-auth-pane <?php echo 'recover' === $active_tab ? 'is-active' : ''; ?>" data-auth-pane="recover">
            <form class="assoc-portal-form assoc-auth-form" method="post" action="" data-auth-form="recover">
                <input type="hidden" name="testcookie" value="1">
                <h3><?php esc_html_e( 'Recupero Password', 'assoc-portal' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Inserisci username o email per ricevere il link di reset.', 'assoc-portal' ); ?></p>
                <p>
                    <label for="assoc_recover_identifier"><?php esc_html_e( 'Username o Email', 'assoc-portal' ); ?></label>
                    <input type="text" id="assoc_recover_identifier" name="assoc_recover_identifier" required autocomplete="username">
                </p>
                <p>
                    <input type="hidden" name="assoc_auth_action" value="recover">
                    <?php wp_nonce_field( 'assoc_auth_recover', 'assoc_auth_nonce' ); ?>
                    <button type="submit" class="button button-primary"><?php esc_html_e( 'Invia Link', 'assoc-portal' ); ?></button>
                </p>
            </form>
        </div>
    </div>
    <script>
        (function() {
            var wrap = document.querySelector('[data-assoc-auth-wrap]');
            if (!wrap) { return; }

            function setActiveTab(tabName) {
                var tabs = wrap.querySelectorAll('[data-auth-tab]');
                var panes = wrap.querySelectorAll('[data-auth-pane]');
                tabs.forEach(function(tab) {
                    var active = tab.getAttribute('data-auth-tab') === tabName;
                    tab.classList.toggle('is-active', active);
                    tab.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                panes.forEach(function(pane) {
                    pane.classList.toggle('is-active', pane.getAttribute('data-auth-pane') === tabName);
                });
            }

            wrap.querySelectorAll('[data-auth-tab]').forEach(function(tabButton) {
                tabButton.addEventListener('click', function() {
                    setActiveTab(tabButton.getAttribute('data-auth-tab') || 'login');
                });
            });

            var regForm = wrap.querySelector('form[data-auth-form="register"]');
            if (!regForm) { return; }

            var firstName = regForm.querySelector('#assoc_reg_first_name');
            var lastName = regForm.querySelector('#assoc_reg_last_name');
            var email = regForm.querySelector('#assoc_reg_email');
            var username = regForm.querySelector('#assoc_reg_username');
            var password = regForm.querySelector('#assoc_reg_password');
            var passwordConfirm = regForm.querySelector('#assoc_reg_password_confirm');
            var liveError = regForm.querySelector('#assoc-auth-live-error');
            var strengthMeter = regForm.querySelector('#assoc-password-strength-meter');

            function showLiveError(message) {
                if (!liveError) { return; }
                liveError.textContent = message || '';
                liveError.classList.toggle('is-visible', !!message);
            }

            function updateStrength() {
                if (!strengthMeter || !password) { return; }
                var value = password.value || '';
                var score = 0;
                if (value.length >= 8) { score++; }
                if (/[A-Z]/.test(value) && /[a-z]/.test(value)) { score++; }
                if (/\d/.test(value)) { score++; }
                if (/[^A-Za-z0-9]/.test(value)) { score++; }

                strengthMeter.classList.remove('is-empty', 'is-bad', 'is-medium', 'is-strong');
                if (!value) {
                    strengthMeter.classList.add('is-empty');
                    strengthMeter.textContent = '-Nessuna';
                } else if (score <= 1) {
                    strengthMeter.classList.add('is-bad');
                    strengthMeter.textContent = 'Debole';
                } else if (score <= 2) {
                    strengthMeter.classList.add('is-medium');
                    strengthMeter.textContent = 'Media';
                } else {
                    strengthMeter.classList.add('is-strong');
                    strengthMeter.textContent = 'Forte';
                }
            }

            function validateRegister(showMessage) {
                if (!firstName.value.trim() || !lastName.value.trim() || !email.value.trim() || !username.value.trim() || !password.value || !passwordConfirm.value) {
                    if (showMessage) { showLiveError('Compila tutti i campi obbligatori.'); }
                    return false;
                }
                var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailPattern.test(email.value.trim())) {
                    if (showMessage) { showLiveError('Inserisci un indirizzo email valido.'); }
                    return false;
                }
                if (password.value.length < 8) {
                    if (showMessage) { showLiveError('La password deve avere almeno 8 caratteri.'); }
                    return false;
                }
                if (password.value !== passwordConfirm.value) {
                    if (showMessage) { showLiveError('Le password non coincidono.'); }
                    return false;
                }
                showLiveError('');
                return true;
            }

            ['input', 'change', 'blur'].forEach(function(evt) {
                [firstName, lastName, email, username, password, passwordConfirm].forEach(function(field) {
                    if (!field) { return; }
                    field.addEventListener(evt, function() {
                        updateStrength();
                        validateRegister(false);
                    });
                });
            });

            regForm.addEventListener('submit', function(e) {
                updateStrength();
                if (!validateRegister(true)) {
                    e.preventDefault();
                }
            });

            updateStrength();
        })();
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode( 'assoc_reserved_access', 'assoc_portal_reserved_access_shortcode' );

/**
 * Add a field to the user profile to link a user to an association post.
 * Only shown to users who can edit other users.
 */
function assoc_portal_add_user_association_field( $user ) {
    if ( ! current_user_can( 'edit_user', $user->ID ) ) {
        return;
    }

    // Get all association posts
    $associations = get_posts( [
        'post_type'      => 'association',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post_status'    => 'any',
    ] );

    if ( empty( $associations ) ) {
        return;
    }

    $selected_association_id = get_user_meta( $user->ID, 'association_post_id', true );

    ?>
    <h3><?php _e( 'Association Link', 'assoc-portal' ); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="association_post_id"><?php _e( 'Managed Association', 'assoc-portal' ); ?></label></th>
            <td>
                <select name="association_post_id" id="association_post_id">
                    <option value=""><?php _e( '-- Select Association --', 'assoc-portal' ); ?></option>
                    <?php foreach ( $associations as $association ) : ?>
                        <option value="<?php echo esc_attr( $association->ID ); ?>" <?php selected( $selected_association_id, $association->ID ); ?>>
                            <?php echo esc_html( $association->post_title ); ?> (ID: <?php echo esc_html( $association->ID ); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php _e( 'Link this user to the association they manage.', 'assoc-portal' ); ?>
                </p>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'assoc_portal_add_user_association_field' );
add_action( 'edit_user_profile', 'assoc_portal_add_user_association_field' );

/**
 * Save the custom user profile field.
 */
function assoc_portal_save_user_association_field( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    if ( isset( $_POST['association_post_id'] ) ) {
        $association_id = intval( $_POST['association_post_id'] );
        update_user_meta( $user_id, 'association_post_id', $association_id );
    }
}
add_action( 'personal_options_update', 'assoc_portal_save_user_association_field' );
add_action( 'edit_user_profile_update', 'assoc_portal_save_user_association_field' );

/**
 * Redirect 'Association Manager' role from wp-admin to the front-end dashboard.
 * This prevents them from accessing the confusing backend admin area.
 */
function assoc_portal_redirect_admin() {
    // Check if the user has the 'association_manager' role, is in the admin area, and it's not an AJAX request.
    if ( current_user_can( 'association_manager' ) && is_admin() && ! wp_doing_ajax() ) {
        // Redirect to the front-end dashboard page.
        wp_redirect( home_url( '/area-riservata/' ) );
        exit;
    }
}
add_action( 'admin_init', 'assoc_portal_redirect_admin' );

/**
 * Shortcode: [assoc_dashboard]
 * Displays the main dashboard navigation for associations.
 */
function assoc_portal_dashboard_shortcode() {
    // Ensure the user is logged in and has the correct role.
    if ( ! is_user_logged_in() || ( ! current_user_can( 'association_manager' ) && ! current_user_can( 'manage_options' ) ) ) {
        // Show a permission error if they don't have access.
        return '<p>' . __( 'You must be logged in to view this page.', 'assoc-portal' ) . '</p>';
    }

    // Use output buffering to capture the HTML.
    ob_start();
    ?>
    <div class="assoc-portal-dashboard">
        <h2><?php _e( 'Association Portal', 'assoc-portal' ); ?></h2>
        <p><?php _e( 'Welcome to your portal. From here you can manage your association profile and your events.', 'assoc-portal' ); ?></p>
        <ul class="assoc-portal-nav">
            <li><a href="<?php echo esc_url( home_url( '/area-riservata/profilo/' ) ); ?>"><?php _e( 'Edit My Association Profile', 'assoc-portal' ); ?></a></li>
            <li><a href="<?php echo esc_url( home_url( '/area-riservata/eventi/' ) ); ?>"><?php _e( 'Manage My Events', 'assoc-portal' ); ?></a></li>
        </ul>
    </div>
    <?php
    // Return the captured HTML.
    return ob_get_clean();
}
add_shortcode( 'assoc_dashboard', 'assoc_portal_dashboard_shortcode' );

/**
 * Shortcode: [assoc_profile_form]
 * Displays the form for editing an association's profile.
 * Handles the submission and updates the association data.
 */
function assoc_portal_profile_form_shortcode() {
    // Security: Must be a logged-in Association Manager.
    if ( ! is_user_logged_in() || ! current_user_can( 'association_manager' ) ) {
        return '<p>' . __( 'You must be logged in as an Association Manager to view this page.', 'assoc-portal' ) . '</p>';
    }

    $user_id = get_current_user_id();
    $association_id = get_user_meta( $user_id, 'association_post_id', true );

    // Check if the user is linked to an association.
    if ( empty( $association_id ) ) {
        return '<p>' . __( 'Your user account is not linked to an association. Please contact an administrator.', 'assoc-portal' ) . '</p>';
    }

    // Security Check: Make sure the post is indeed an 'association'.
    if ( 'association' !== get_post_type( $association_id ) ) {
        return '<p>' . __( 'The linked item is not a valid association. Please contact an administrator.', 'assoc-portal' ) . '</p>';
    }

    // Handle form submission before displaying the form.
    if ( isset( $_POST['assoc_profile_submit'] ) ) {
        // Verify nonce for security.
        if ( ! isset( $_POST['assoc_profile_nonce'] ) || ! wp_verify_nonce( $_POST['assoc_profile_nonce'], 'assoc_profile_update_action' ) ) {
            wp_die( 'Security check failed. Please try again.' );
        }

        // --- Update Post Meta ---
        $meta_fields = [
            'city', 'province', 'comune', 'address', 'phone', 'email', 'website',
            'facebook', 'instagram', 'youtube', 'tiktok', 'x'
        ];

        foreach ( $meta_fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $value = sanitize_text_field( wp_unslash( $_POST[ $field ] ) );
                // Sanitize URL fields specifically.
                if ( in_array( $field, ['website', 'facebook', 'instagram', 'youtube', 'tiktok', 'x'] ) ) {
                    $value = esc_url_raw( $value );
                }
                 if ( 'email' === $field ) {
                    $value = sanitize_email( $value );
                }
                update_post_meta( $association_id, $field, $value );
            }
        }

        // --- Update Taxonomy ---
        if ( isset( $_POST['tax_input']['activity_category'] ) ) {
            $term_ids = array_map( 'intval', $_POST['tax_input']['activity_category'] );
            wp_set_post_terms( $association_id, $term_ids, 'activity_category' );
        } else {
             wp_set_post_terms( $association_id, [], 'activity_category' );
        }

        // --- …2967 tokens truncated…) {
        return '<p>' . __( 'You must be logged in as an Association Manager to view this page.', 'assoc-portal' ) . '</p>';
    }
    
    $user_id = get_current_user_id();
    $association_id = get_user_meta( $user_id, 'association_post_id', true );

    if ( empty( $association_id ) ) {
        return '<p>' . __( 'Your user account is not linked to an association. Please contact an administrator.', 'assoc-portal' ) . '</p>';
    }

    $event_id = isset( $_GET['event_id'] ) ? intval( $_GET['event_id'] ) : 0;
    
    // Security check for edit mode
    if ( $event_id > 0 ) {
        $event_post = get_post( $event_id );
        if ( ! $event_post || 'event' !== $event_post->post_type || $event_post->post_author != $user_id ) {
            return '<p>' . __( 'You do not have permission to edit this event, or the event does not exist.', 'assoc-portal' ) . '</p>';
        }
    }

    // Handle form submission
    if ( isset( $_POST['assoc_event_submit'] ) ) {
        if ( ! isset( $_POST['assoc_event_nonce'] ) || ! wp_verify_nonce( $_POST['assoc_event_nonce'], 'assoc_event_action' ) ) {
            wp_die( 'Security check failed.' );
        }

        $title = sanitize_text_field( $_POST['post_title'] );
        $event_id_to_update = intval( $_POST['event_id'] );
        
        // Security check again on submission to prevent unauthorized updates
        if ( $event_id_to_update > 0 ) {
            $event_post_to_check = get_post( $event_id_to_update );
            if ( ! $event_post_to_check || $event_post_to_check->post_author != $user_id ) {
                wp_die( 'Permission denied during save.' );
            }
        }
        
        $content = '';
        if ( isset( $_POST['post_content'] ) ) {
            $content = wp_kses_post( $_POST['post_content'] );
        }

        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_type'    => 'event',
            'post_author'  => $user_id,
            'post_status'  => current_user_can( 'manage_options' ) ? 'publish' : 'pending',
            'ID'           => $event_id_to_update,
        ];
        
        $new_event_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $new_event_id ) ) {
             return '<div class="notice notice-error"><p>' . $new_event_id->get_error_message() . '</p></div>';
        } 
        
        // --- Update Post Meta ---
        $meta_fields = ['start_date', 'end_date', 'venue_name', 'address', 'city', 'province', 'comune', 'registration_url'];
        foreach ( $meta_fields as $field ) {
            if ( isset( $_POST[$field] ) ) {
                $value = sanitize_text_field( $_POST[$field] );
                if( in_array($field, ['registration_url'])) {
                    $value = esc_url_raw($value);
                }
                update_post_meta( $new_event_id, $field, $value );
            }
        }
        
        // Auto-set the organizer association ID
        update_post_meta( $new_event_id, 'organizer_association_id', $association_id );

        // Set event type taxonomy
        if ( isset( $_POST['event_type'] ) && $_POST['event_type'] > 0 ) {
            wp_set_post_terms( $new_event_id, [ intval( $_POST['event_type'] ) ], 'event_type' );
        } else {
            wp_set_post_terms( $new_event_id, [], 'event_type' );
        }

        // Handle featured image
        if ( ! empty( $_FILES['featured_image']['name'] ) ) {
            require_once( ABSPATH . 'wp-admin/includes/image.php' );
            require_once( ABSPATH . 'wp-admin/includes/file.php' );
            require_once( ABSPATH . 'wp-admin/includes/media.php' );
            $attachment_id = media_handle_upload( 'featured_image', $new_event_id );
            if ( ! is_wp_error( $attachment_id ) ) {
                set_post_thumbnail( $new_event_id, $attachment_id );
            }
        }
        
        // Redirect to the events list with a success message
        wp_redirect( home_url( '/area-riservata/eventi/?message=success' ) );
        exit;
    }

    // --- Display The Form ---
    $event_title = '';
    $meta = [];
    if ( $event_id > 0 ) {
        $event_post = get_post( $event_id );
        $event_title = $event_post->post_title;
        $meta = get_post_meta( $event_id );
    }
    
    $get_meta = function( $key ) use ( $meta ) { return $meta[ $key ][0] ?? ''; };

    ob_start();
    ?>
    <form class="assoc-portal-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
        <?php wp_nonce_field( 'assoc_event_action', 'assoc_event_nonce' ); ?>
        
        <h2><?php echo ( $event_id > 0 ) ? __( 'Edit Event', 'assoc-portal' ) : __( 'Create New Event', 'assoc-portal' ); ?></h2>
        <p class="description"><?php _e( 'All events are submitted for review and must be approved by an administrator before appearing on the calendar.', 'assoc-portal' ); ?></p>
        
        <fieldset>
            <p>
                <label for="post_title"><?php _e( 'Event Title', 'assoc-portal' ); ?>*</label>
                <input type="text" id="post_title" name="post_title" value="<?php echo esc_attr( $event_title ); ?>" required>
            </p>
            
            <p>
                <label for="start_date"><?php _e( 'Start Date & Time', 'assoc-portal' ); ?>*</label>
                <input type="datetime-local" id="start_date" name="start_date" value="<?php echo esc_attr( $get_meta('start_date') ); ?>" required>
            </p>
            
            <p>
                <label for="end_date"><?php _e( 'End Date & Time (Optional)', 'assoc-portal' ); ?></label>
                <input type="datetime-local" id="end_date" name="end_date" value="<?php echo esc_attr( $get_meta('end_date') ); ?>">
            </p>

            <p>
                <label for="event_type"><?php _e( 'Event Type', 'assoc-portal' ); ?></label>
                <?php
                $selected_term = wp_get_post_terms( $event_id, 'event_type', ['fields' => 'ids'] );
                wp_dropdown_categories( [
                    'taxonomy'         => 'event_type',
                    'name'             => 'event_type',
                    'selected'         => $selected_term[0] ?? 0,
                    'show_option_none' => 'Select type...',
                    'hierarchical'     => false,
                    'hide_empty'       => false,
                ] );
                ?>
            </p>

            <p>
                <label for="post_content"><?php _e( 'Event Description', 'assoc-portal' ); ?></label>
                <?php
                $content = ($event_id > 0) ? get_post_field('post_content', $event_id) : '';
                wp_editor( $content, 'post_content', ['textarea_name' => 'post_content', 'media_buttons' => false, 'textarea_rows' => 10] );
                ?>
            </p>
        </fieldset>

        <fieldset>
            <legend><?php _e( 'Venue & Location', 'assoc-portal' ); ?></legend>
            <p><label for="venue_name"><?php _e( 'Venue Name', 'assoc-portal' ); ?></label>
            <input type="text" id="venue_name" name="venue_name" value="<?php echo esc_attr( $get_meta('venue_name') ); ?>"></p>
            
            <p><label for="address"><?php _e( 'Address', 'assoc-portal' ); ?></label>
            <input type="text" id="address" name="address" value="<?php echo esc_attr( $get_meta('address') ); ?>"></p>

            <p><label for="city"><?php _e( 'City', 'assoc-portal' ); ?></label>
            <input type="text" id="city" name="city" value="<?php echo esc_attr( $get_meta('city') ); ?>"></p>

            <p><label for="comune"><?php _e( 'Comune', 'assoc-portal' ); ?></label>
            <input type="text" id="comune" name="comune" value="<?php echo esc_attr( $get_meta('comune') ); ?>"></p>
            
            <p><label for="province"><?php _e( 'Province (e.g., "MI")', 'assoc-portal' ); ?></label>
            <input type="text" id="province" name="province" maxlength="2" value="<?php echo esc_attr( $get_meta('province') ); ?>"></p>
        </fieldset>
        
        <fieldset>
             <legend><?php _e( 'Details', 'assoc-portal' ); ?></legend>
             <p><label for="registration_url"><?php _e( 'Registration URL', 'assoc-portal' ); ?></label>
            <input type="url" id="registration_url" name="registration_url" placeholder="https://" value="<?php echo esc_attr( $get_meta('registration_url') ); ?>"></p>
            
             <p>
                <label for="featured_image"><?php _e( 'Event Image', 'assoc-portal' ); ?></label>
                <input type="file" name="featured_image" id="featured_image" accept="image/*">
            </p>
            <?php if ( has_post_thumbnail( $event_id ) ) : ?>
                <div class="current-image">
                    <p><?php _e( 'Current Image:', 'assoc-portal' ); ?></p>
                    <?php echo get_the_post_thumbnail( $event_id, 'thumbnail' ); ?>
                </div>
            <?php endif; ?>
        </fieldset>

        <p><input type="submit" name="assoc_event_submit" class="button button-primary" value="<?php _e( 'Submit for Review', 'assoc-portal' ); ?>"></p>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode( 'assoc_event_form', 'assoc_event_form_shortcode' );

/**
 * Shortcode: [events_calendar]
 * Renders a public-facing monthly calendar of events.
 * Fetches published events and displays them in a grid.
 * Includes navigation for previous/next months.
 */
function assoc_portal_events_calendar_shortcode() {
    ob_start();

    // --- 1. Get Current Date & Prepare Navigation ---
    $current_year  = isset( $_GET['y'] ) ? intval( $_GET['y'] ) : intval( date( 'Y' ) );
    $current_month = isset( $_GET['m'] ) ? intval( $_GET['m'] ) : intval( date( 'n' ) );
    
    $timestamp = mktime( 0, 0, 0, $current_month, 1, $current_year );
    $month_name = date_i18n( 'F', $timestamp );

    $prev_month_ts = strtotime( '-1 month', $timestamp );
    $prev_link = esc_url( add_query_arg( [ 'y' => date('Y', $prev_month_ts), 'm' => date('n', $prev_month_ts) ], get_permalink() ) );

    $next_month_ts = strtotime( '+1 month', $timestamp );
    $next_link = esc_url( add_query_arg( [ 'y' => date('Y', $next_month_ts), 'm' => date('n', $next_month_ts) ], get_permalink() ) );

    // --- 2. Query Events for the Displayed Month ---
    $first_day_of_month = date( 'Y-m-01 00:00:00', $timestamp );
    $last_day_of_month  = date( 'Y-m-t 23:59:59', $timestamp );

    $events_query = new WP_Query( [
        'post_type'      => 'event',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'start_date',
                'compare' => '>=',
                'value'   => $first_day_of_month,
                'type'    => 'DATETIME'
            ],
            [
                'key'     => 'start_date',
                'compare' => '<=',
                'value'   => $last_day_of_month,
                'type'    => 'DATETIME'
            ]
        ],
        'orderby'   => 'meta_value',
        'meta_key'  => 'start_date',
        'order'     => 'ASC'
    ] );

    // --- 3. Process Events into a Day-based Array ---
    $events_by_day = [];
    if ( $events_query->have_posts() ) {
        while ( $events_query->have_posts() ) {
            $events_query->the_post();
            $event_id = get_the_ID();
            $start_date_str = get_post_meta( $event_id, 'start_date', true );
            if ( ! $start_date_str ) continue;

            try {
                $start_date_obj = new DateTime( $start_date_str );
                $day_number = $start_date_obj->format( 'j' );

                if ( ! isset( $events_by_day[ $day_number ] ) ) {
                    $events_by_day[ $day_number ] = [];
                }
                
                $end_date_str = get_post_meta( $event_id, 'end_date', true );
                $full_address_parts = array_filter([
                    get_post_meta($event_id, 'address', true),
                    get_post_meta($event_id, 'city', true),
                    get_post_meta($event_id, 'comune', true),
                    get_post_meta($event_id, 'province', true)
                ]);

                $events_by_day[ $day_number ][] = [
                    'title'             => get_the_title(),
                    'time'              => $start_date_obj->format( 'H:i' ),
                    'description'       => get_the_content(),
                    'image'             => get_the_post_thumbnail_url( $event_id, 'large' ),
                    'start_date'        => $start_date_obj->format('Y-m-d H:i'),
                    'end_date'          => $end_date_str ? (new DateTime($end_date_str))->format('Y-m-d H:i') : '',
                    'venue'             => get_post_meta( $event_id, 'venue_name', true ),
                    'address'           => implode(', ', $full_address_parts),
                    'registration_url'  => get_post_meta( $event_id, 'registration_url', true ),
                ];
            } catch ( Exception $e ) {
                // Ignore events with invalid dates
            }
        }
    }
    wp_reset_postdata();

    // --- 4. Render the Calendar Grid ---
    $weekdays = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];
    $start_of_week = intval( get_option( 'start_of_week', 1 ) );
     if ($start_of_week === 0) {
        $weekdays = [ 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' ];
    }
    $first_day_of_week = date( 'N', $timestamp );
    if ($start_of_week === 0) {
        $first_day_of_week = date( 'w', $timestamp ) + 1;
    }


    ?>
    <div class="assoc-portal-calendar">
        <div class="calendar-header">
            <a class="prev-month" href="<?php echo $prev_link; ?>">&laquo; <?php _e('Prev', 'assoc-portal'); ?></a>
            <h2 class="month-title"><?php echo esc_html( $month_name ) . ' ' . esc_html( $current_year ); ?></h2>
            <a class="next-month" href="<?php echo $next_link; ?>"><?php _e('Next', 'assoc-portal'); ?> &raquo;</a>
        </div>
        <table class="calendar-grid">
            <thead>
                <tr>
                    <?php foreach ( $weekdays as $day ) : ?>
                        <th><?php echo esc_html( __( substr($day, 0, 3) ) ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                <?php
                $blank_cells = ( $first_day_of_week - $start_of_week + 7 ) % 7;
                for ( $i = 0; $i < $blank_cells; $i++ ) {
                    echo '<td class="empty"></td>';
                }

                $day_count = 1 + $blank_cells;
                for ( $day = 1; $day <= date( 't', $timestamp ); $day++ ) {
                    if ( $day_count > 7 ) {
                        echo '</tr><tr>';
                        $day_count = 1;
                    }

                    $is_today = ( $current_year == date('Y') && $current_month == date('n') && $day == date('j') );
                    
                    echo '<td class="' . ($is_today ? 'today' : '') . '">';
                    echo '<div class="day-number">' . esc_html( $day ) . '</div>';

                    if ( isset( $events_by_day[ $day ] ) ) {
                        echo '<ul class="events-in-day">';
                        foreach ( $events_by_day[ $day ] as $event ) {
                             // Prepare data attributes
                            $data_attrs = 'class="event-item" ';
                            $data_attrs .= 'data-title="' . esc_attr($event['title']) . '" ';
                            $data_attrs .= 'data-description="' . esc_attr(wp_strip_all_tags($event['description'])) . '" ';
                            $data_attrs .= 'data-image="' . esc_attr($event['image']) . '" ';
                            $data_attrs .= 'data-start-date="' . esc_attr($event['start_date']) . '" ';
                            $data_attrs .= 'data-end-date="' . esc_attr($event['end_date']) . '" ';
                            $data_attrs .= 'data-venue="' . esc_attr($event['venue']) . '" ';
                            $data_attrs .= 'data-address="' . esc_attr($event['address']) . '" ';
                            $data_attrs .= 'data-registration-url="' . esc_attr($event['registration_url']) . '"';

                            echo '<li ' . $data_attrs . '>';
                            echo '<span class="event-time">' . esc_html( $event['time'] ) . '</span> ';
                            echo '<strong class="event-title">' . esc_html( $event['title'] ) . '</strong>';
                            if ( ! empty( $event['venue'] ) ) {
                                echo '<span class="event-venue"> @ ' . esc_html( $event['venue'] ) . '</span>';
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    echo '</td>';
                    $day_count++;
                }
                
                while( $day_count <= 7 && $day_count > 1 ) {
                    echo '<td class="empty"></td>';
                    $day_count++;
                }
                ?>
                </tr>
            </tbody>
        </table>
    </div>
    <?php
    
    return ob_get_clean();
}
add_shortcode( 'events_calendar', 'assoc_portal_events_calendar_shortcode' );


/**
 * Build the calendar URL that focuses a specific event and month.
 */
function assoc_portal_event_calendar_url( int $event_id ): string {
    if ( $event_id <= 0 ) {
        return (string) home_url( '/calendar/' );
    }

    $start_raw = trim( (string) get_post_meta( $event_id, 'start_date', true ) );
    if ( '' === $start_raw ) {
        $start_raw = trim( (string) get_post_field( 'post_date', $event_id ) );
    }

    $args = array(
        'ev_event_id' => $event_id,
    );

    $start_ts = strtotime( $start_raw );
    if ( $start_ts ) {
        $args['ev_y'] = (int) gmdate( 'Y', $start_ts );
        $args['ev_m'] = (int) gmdate( 'n', $start_ts );
    }

    return (string) add_query_arg( $args, home_url( '/calendar/' ) );
}

/**
 * Ensure front-end event permalinks point to calendar + modal target.
 */
function assoc_portal_event_permalink_to_calendar( $post_link, $post, $leavename, $sample ) {
    if ( is_admin() || $sample ) {
        return $post_link;
    }
    if ( ! ( $post instanceof WP_Post ) || 'event' !== $post->post_type ) {
        return $post_link;
    }

    return assoc_portal_event_calendar_url( (int) $post->ID );
}
add_filter( 'post_type_link', 'assoc_portal_event_permalink_to_calendar', 20, 4 );

/**
 * Redirect single event pages to the calendar focused on that event.
 * This fulfills the requirement that events do not have public single pages.
 */
function assoc_portal_redirect_single_events() {
    if ( ! is_singular( 'event' ) ) {
        return;
    }

    $event_id = (int) get_queried_object_id();
    $calendar_page_url = assoc_portal_event_calendar_url( $event_id );
    nocache_headers();
    wp_safe_redirect( $calendar_page_url, 302 );
    exit;
}
add_action( 'template_redirect', 'assoc_portal_redirect_single_events' );

/**
 * Enqueue front-end scripts and stylesheets.
 */
function assoc_portal_enqueue_scripts_styles() {
    global $post;
    
    // We enqueue the portal stylesheet globally (instead of conditionally via has_shortcode)
    // because Kadence blocks and archive loops frequently bypass raw shortcode detection.
    $plugin_url = plugin_dir_url( __FILE__ );
    $css_file = 'assets/css/portal.css';
    
    wp_enqueue_style(
        'assoc-portal-style',
        $plugin_url . $css_file,
        [],
        filemtime( plugin_dir_path( __FILE__ ) . $css_file )
    );

    // Calendar-specific JavaScript for the modal popup
    if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'events_calendar' ) ) {
        $plugin_url = plugin_dir_url( __FILE__ );
        $js_file = 'assets/js/calendar-popup.js';

        wp_enqueue_script(
            'assoc-portal-calendar-popup',
            $plugin_url . $js_file,
            [], // dependencies
            filemtime( plugin_dir_path( __FILE__ ) . $js_file ),
            true // in footer
        );
    }
}
add_action( 'wp_enqueue_scripts', 'assoc_portal_enqueue_scripts_styles' );

// Load the enhanced calendar browser (filters + row layout) and let it override legacy [events_calendar].
$assoc_portal_calendar_browser_file = plugin_dir_path( __FILE__ ) . 'inc/calendar-browser.php';
if ( file_exists( $assoc_portal_calendar_browser_file ) ) {
    require_once $assoc_portal_calendar_browser_file;
}

/**
 * Compatibility shortcodes for Area Riservata pages created by MU plugin.
 * These provide stable rendering even if legacy page content still references old shortcode names.
 */
function assoc_portal_reserved_nav_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '';
    }

    $items = array(
        array(
            'label' => __( 'Dashboard', 'assoc-portal' ),
            'url'   => home_url( '/area-riservata/' ),
        ),
    );

    if ( current_user_can( 'association_manager' ) || current_user_can( 'manage_options' ) ) {
        $items[] = array(
            'label' => __( 'Eventi', 'assoc-portal' ),
            'url'   => home_url( '/area-riservata/eventi/' ),
        );
        $items[] = array(
            'label' => __( 'Nuovo Evento', 'assoc-portal' ),
            'url'   => home_url( '/area-riservata/eventi/nuovo/' ),
        );
        if ( current_user_can( 'manage_options' ) ) {
            $items[] = array(
                'label' => __( 'Crea Associazione', 'assoc-portal' ),
                'url'   => culturacsi_portal_admin_association_form_url(),
            );
        } else {
            $items[] = array(
                'label' => __( 'Dati Associazione', 'assoc-portal' ),
                'url'   => home_url( '/area-riservata/profilo/' ),
            );
        }
    }

    if ( current_user_can( 'manage_options' ) ) {
        $items[] = array(
            'label' => __( 'Amministrazione', 'assoc-portal' ),
            'url'   => home_url( '/area-riservata/amministrazione/' ),
        );
    }

    $items[] = array(
        'label' => __( 'Esci', 'assoc-portal' ),
        'url'   => wp_logout_url( home_url( '/' ) ),
    );

    ob_start();
    echo '<nav class="assoc-portal-dashboard"><ul class="assoc-portal-nav">';
    foreach ( $items as $item ) {
        echo '<li><a href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
    }
    echo '</ul></nav>';
    return ob_get_clean();
}
add_shortcode( 'assoc_reserved_nav', 'assoc_portal_reserved_nav_shortcode' );

/**
 * Frontend admin panel shortcode used by /area-riservata/amministrazione.
 */
function assoc_portal_admin_control_panel_shortcode() {
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
        return '<p>' . esc_html__( 'Permessi insufficienti.', 'assoc-portal' ) . '</p>';
    }

    $cards = array(
        array(
            'title' => __( 'Associazioni', 'assoc-portal' ),
            'url'   => admin_url( 'edit.php?post_type=association' ),
        ),
        array(
            'title' => __( 'Eventi', 'assoc-portal' ),
            'url'   => admin_url( 'edit.php?post_type=event' ),
        ),
        array(
            'title' => __( 'Notizie', 'assoc-portal' ),
            'url'   => admin_url( 'edit.php?post_type=news' ),
        ),
        array(
            'title' => __( 'Utenti', 'assoc-portal' ),
            'url'   => admin_url( 'users.php' ),
        ),
    );

    ob_start();
    echo '<div class="assoc-portal-dashboard"><h2>' . esc_html__( 'Pannello Amministrazione', 'assoc-portal' ) . '</h2><ul class="assoc-portal-nav">';
    foreach ( $cards as $card ) {
        echo '<li><a href="' . esc_url( $card['url'] ) . '">' . esc_html( $card['title'] ) . '</a></li>';
    }
    echo '</ul></div>';
    return ob_get_clean();
}
add_shortcode( 'assoc_admin_control_panel', 'assoc_portal_admin_control_panel_shortcode' );

if ( function_exists( 'assoc_portal_profile_form_shortcode' ) && ! shortcode_exists( 'assoc_profile_form' ) ) {
    add_shortcode( 'assoc_profile_form', 'assoc_portal_profile_form_shortcode' );
}

if ( function_exists( 'assoc_portal_profile_form_shortcode' ) && ! shortcode_exists( 'assoc_association_form' ) ) {
    add_shortcode( 'assoc_association_form', 'assoc_portal_profile_form_shortcode' );
}

/**
 * Minimal fallback events list shortcode for pages using [assoc_events_list].
 */
function assoc_portal_events_list_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>' . esc_html__( 'Devi effettuare il login.', 'assoc-portal' ) . '</p>';
    }

    $author_id = get_current_user_id();
    $query_args = array(
        'post_type'      => 'event',
        'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
        'posts_per_page' => 100,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    if ( ! current_user_can( 'manage_options' ) ) {
        $query_args['author'] = $author_id;
    }
    $query = new WP_Query( $query_args );

    ob_start();
    echo '<div class="assoc-portal-events-list"><p><a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/eventi/nuovo/' ) ) . '">' . esc_html__( 'Nuovo Evento', 'assoc-portal' ) . '</a></p>';
    echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Titolo', 'assoc-portal' ) . '</th><th>' . esc_html__( 'Data', 'assoc-portal' ) . '</th><th>' . esc_html__( 'Stato', 'assoc-portal' ) . '</th><th>' . esc_html__( 'Azioni', 'assoc-portal' ) . '</th></tr></thead><tbody>';
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            echo '<tr>';
            echo '<td>' . esc_html( get_the_title() ) . '</td>';
            echo '<td>' . esc_html( get_the_date( 'd/m/Y H:i' ) ) . '</td>';
            echo '<td>' . esc_html( get_post_status_object( get_post_status() )->label ?? get_post_status() ) . '</td>';
            echo '<td><a href="' . esc_url( add_query_arg( array( 'event_id' => get_the_ID() ), home_url( '/area-riservata/eventi/nuovo/' ) ) ) . '">' . esc_html__( 'Modifica', 'assoc-portal' ) . '</a></td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="4">' . esc_html__( 'Nessun evento trovato.', 'assoc-portal' ) . '</td></tr>';
    }
    echo '</tbody></table></div>';
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode( 'assoc_events_list', 'assoc_portal_events_list_shortcode' );

/**
 * Use profile form for placeholders until dedicated shortcodes are implemented.
 */
function assoc_portal_placeholder_shortcode() {
    if ( function_exists( 'assoc_portal_profile_form_shortcode' ) ) {
        return assoc_portal_profile_form_shortcode();
    }
    return '';
}
add_shortcode( 'assoc_user_profile_form', 'assoc_portal_placeholder_shortcode' );
add_shortcode( 'assoc_news_list', 'assoc_portal_placeholder_shortcode' );
add_shortcode( 'assoc_news_form', 'assoc_portal_placeholder_shortcode' );

