<?php
/**
 * Settori Menu and Pages Setup Script
 * 
 * This script creates all Settori pages and builds the mega-menu structure.
 * Run this once via WordPress admin or WP-CLI.
 * 
 * @package CulturaCSI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Define the complete Settori menu structure
 */
function culturacsi_get_settori_structure() {
    return array(
        'Arte' => array(
            'slug' => 'arte',
            'children' => array(
                'Arti Visive' => array(
                    'slug' => 'arti-visive',
                    'children' => array(
                        'Fotografia e Pittura' => array( 'slug' => 'fotografia-e-pittura' ),
                        'Scultura' => array( 'slug' => 'scultura' ),
                        'Arte Digitale' => array( 'slug' => 'arte-digitale' ),
                    ),
                ),
                'Arti Performative / Danza e Movimento' => array(
                    'slug' => 'arti-performative-danza-e-movimento',
                    'children' => array(
                        'Danza' => array( 'slug' => 'danza' ),
                        'Danza Aerea' => array( 'slug' => 'danza-aerea' ),
                        'Break Dance' => array( 'slug' => 'break-dance' ),
                        'Ginnastica Ritmica' => array( 'slug' => 'ginnastica-ritmica' ),
                        'Pattinaggio Artistico' => array( 'slug' => 'pattinaggio-artistico' ),
                        'Tango' => array( 'slug' => 'tango' ),
                    ),
                ),
                'Musica e Canto' => array(
                    'slug' => 'musica-e-canto',
                    'children' => array(
                        'Musica' => array( 'slug' => 'musica' ),
                        'Canto' => array( 'slug' => 'canto' ),
                    ),
                ),
                'Teatro e Spettacolo' => array(
                    'slug' => 'teatro-e-spettacolo',
                    'children' => array(
                        'Teatro e Spettacolo' => array( 'slug' => 'teatro-e-spettacolo-dettaglio' ),
                    ),
                ),
                'Letteratura, Poesia ed Editoria' => array(
                    'slug' => 'letteratura-poesia-ed-editoria',
                    'children' => array(
                        'Poesia' => array( 'slug' => 'poesia' ),
                        'Editoria' => array( 'slug' => 'editoria' ),
                    ),
                ),
                'Attività Culturali e Locali / Ricreative' => array(
                    'slug' => 'attivita-culturali-e-locali-ricreative',
                    'children' => array(
                        'Attività Culturali e Ricreative' => array( 'slug' => 'attivita-culturali-e-ricreative' ),
                        'Pro Loco' => array( 'slug' => 'pro-loco' ),
                    ),
                ),
                'Attività Educative e Ricreative per Persone con Disabilità' => array(
                    'slug' => 'attivita-educative-e-ricreative-per-persone-con-disabilita',
                    'children' => array(
                        'Danza con disabili' => array( 'slug' => 'danza-con-disabili' ),
                        'Attività subacquee inclusive' => array( 'slug' => 'attivita-subacquee-inclusive' ),
                        'Altre attività artistiche o culturali adattate' => array( 'slug' => 'altre-attivita-artistiche-o-culturali-adattate' ),
                    ),
                ),
                'Attività di Supporto e Volontariato' => array(
                    'slug' => 'attivita-di-supporto-e-volontariato',
                    'children' => array(
                        'Volontariato, Beneficenza & Protezione Civile' => array( 'slug' => 'volontariato-beneficenza-protezione-civile' ),
                    ),
                ),
                'Attività Terapeutiche e di Benessere' => array(
                    'slug' => 'attivita-terapeutiche-e-di-benessere',
                    'children' => array(
                        'Equitazione e Benessere' => array( 'slug' => 'equitazione-e-benessere' ),
                    ),
                ),
            ),
        ),
        'Ambiente' => array(
            'slug' => 'ambiente',
            'children' => array(
                'Ambiente acquatico' => array(
                    'slug' => 'ambiente-acquatico',
                    'children' => array(
                        'Attività subacquee' => array( 'slug' => 'attivita-subacquee' ),
                        'Attività velistiche e Surfing' => array( 'slug' => 'attivita-velistiche-e-surfing' ),
                        'Surfing & Kayak' => array( 'slug' => 'surfing-kayak' ),
                    ),
                ),
                'Ambiente terrestre' => array(
                    'slug' => 'ambiente-terrestre',
                    'children' => array(
                        'Cicloturismo' => array( 'slug' => 'cicloturismo' ),
                        'Escursionismo e Trekking' => array( 'slug' => 'escursionismo-e-trekking' ),
                        'Nord Walking' => array( 'slug' => 'nord-walking' ),
                        'Sci & Alpinismo' => array( 'slug' => 'sci-alpinismo' ),
                    ),
                ),
                'Attività aeree' => array(
                    'slug' => 'attivita-aeree',
                    'children' => array(
                        'Parapendio e Paracadutismo' => array( 'slug' => 'parapendio-e-paracadutismo' ),
                        'Volo' => array( 'slug' => 'volo' ),
                    ),
                ),
            ),
        ),
        'Valorizzazione del Territorio' => array(
            'slug' => 'valorizzazione-del-territorio',
            'children' => array(
                'Tradizioni Popolari e Identità Locale' => array(
                    'slug' => 'tradizioni-popolari-e-identita-locale',
                    'children' => array(
                        'Attività folkloristiche' => array( 'slug' => 'attivita-folkloristiche' ),
                        'Rievocazioni Storiche' => array( 'slug' => 'rievocazioni-storiche' ),
                    ),
                ),
                'Arti Marziali Storiche e Tradizionali' => array(
                    'slug' => 'arti-marziali-storiche-e-tradizionali',
                    'children' => array(
                        'Scherma Antica' => array( 'slug' => 'scherma-antica' ),
                    ),
                ),
                'Giochi di Tradizione e Cultura Strategica' => array(
                    'slug' => 'giochi-di-tradizione-e-cultura-strategica',
                    'children' => array(
                        'Bridge' => array( 'slug' => 'bridge' ),
                        'Backgammon' => array( 'slug' => 'backgammon' ),
                        'Burraco' => array( 'slug' => 'burraco' ),
                        'Dama e Scacchi' => array( 'slug' => 'dama-e-scacchi' ),
                    ),
                ),
                'Giochi Storici e Identitari Moderni' => array(
                    'slug' => 'giochi-storici-e-identitari-moderni',
                    'children' => array(
                        'Subbuteo' => array( 'slug' => 'subbuteo' ),
                    ),
                ),
            ),
        ),
        'Culture di nicchia' => array(
            'slug' => 'culture-di-nicchia',
            'children' => array(
                'Cultura Motoristica Storica' => array(
                    'slug' => 'cultura-motoristica-storica',
                    'children' => array(
                        'Auto Storiche' => array( 'slug' => 'auto-storiche' ),
                        'Moto d\'Epoca' => array( 'slug' => 'moto-depoca' ),
                    ),
                ),
                'Collezionismo e Cultura del Dettaglio' => array(
                    'slug' => 'collezionismo-e-cultura-del-dettaglio',
                    'children' => array(
                        'Collezionismo' => array( 'slug' => 'collezionismo' ),
                        'Modellismo (statico e dinamico)' => array( 'slug' => 'modellismo-statico-e-dinamico' ),
                    ),
                ),
                'Cultura Enogastronomica Identitaria' => array(
                    'slug' => 'cultura-enogastronomica-identitaria',
                    'children' => array(
                        'Enogastronomia' => array( 'slug' => 'enogastronomia' ),
                    ),
                ),
            ),
        ),
    );
}

/**
 * Generate page content based on title and children
 */
function culturacsi_generate_page_content( $title, $children = array(), $full_slug = '' ) {
    $content = '<!-- wp:heading {"level":1} -->' . "\n";
    $content .= '<h1>' . esc_html( $title ) . '</h1>' . "\n";
    $content .= '<!-- /wp:heading -->' . "\n\n";
    
    // Add intro paragraph
    $intro_text = 'Esplora le attività e i servizi relativi a ' . strtolower( $title ) . ' offerti da ACSI Cultura.';
    $content .= '<!-- wp:paragraph -->' . "\n";
    $content .= '<p>' . esc_html( $intro_text ) . '</p>' . "\n";
    $content .= '<!-- /wp:paragraph -->' . "\n\n";
    
    // Add child items section if there are children
    if ( ! empty( $children ) ) {
        $content .= '<!-- wp:heading {"level":2} -->' . "\n";
        $content .= '<h2>Attività correlate</h2>' . "\n";
        $content .= '<!-- /wp:heading -->' . "\n\n";
        
        $content .= '<!-- wp:list -->' . "\n";
        $content .= '<ul>' . "\n";
        foreach ( $children as $child_title ) {
            $child_slug = sanitize_title( $child_title );
            $child_url = ! empty( $full_slug ) ? $full_slug . '/' . $child_slug : 'settori/' . $child_slug;
            $content .= '<li><a href="' . esc_url( home_url( '/' . $child_url . '/' ) ) . '">' . esc_html( $child_title ) . '</a></li>' . "\n";
        }
        $content .= '</ul>' . "\n";
        $content .= '<!-- /wp:list -->' . "\n";
    }
    
    return $content;
}

/**
 * Create or get page by title and slug
 * Returns array with 'id' and 'was_created' flag
 */
function culturacsi_get_or_create_page( $title, $slug, $parent_id = 0, $content = '' ) {
    $was_created = false;
    
    // Check if page already exists by slug
    $existing_page = get_page_by_path( $slug );
    
    if ( $existing_page ) {
        // Update parent if needed
        if ( $existing_page->post_parent != $parent_id ) {
            wp_update_post( array(
                'ID' => $existing_page->ID,
                'post_parent' => $parent_id,
            ) );
        }
        // Update content if empty
        if ( empty( $existing_page->post_content ) && ! empty( $content ) ) {
            wp_update_post( array(
                'ID' => $existing_page->ID,
                'post_content' => $content,
            ) );
        }
        return array( 'id' => $existing_page->ID, 'was_created' => false );
    }
    
    // Check if page exists by title
    $pages = get_posts( array(
        'post_type' => 'page',
        'title' => $title,
        'post_status' => 'any',
        'numberposts' => 1,
    ) );
    
    if ( ! empty( $pages ) ) {
        $page = $pages[0];
        // Update slug and parent
        wp_update_post( array(
            'ID' => $page->ID,
            'post_name' => $slug,
            'post_parent' => $parent_id,
            'post_content' => ! empty( $content ) ? $content : $page->post_content,
        ) );
        return array( 'id' => $page->ID, 'was_created' => false );
    }
    
    // Create new page
    $page_data = array(
        'post_title'    => $title,
        'post_name'     => $slug,
        'post_content'  => $content,
        'post_status'   => 'publish',
        'post_type'     => 'page',
        'post_parent'   => $parent_id,
        'post_author'   => 1,
    );
    
    $page_id = wp_insert_post( $page_data );
    $was_created = true;
    
    return array( 'id' => $page_id, 'was_created' => $was_created );
}

/**
 * Count pages recursively
 */
function culturacsi_count_pages_recursive( $pages_structure ) {
    $count = 0;
    foreach ( $pages_structure as $page_data ) {
        $count++;
        if ( ! empty( $page_data['children'] ) ) {
            $count += culturacsi_count_pages_recursive( $page_data['children'] );
        }
    }
    return $count;
}

/**
 * Recursively create pages from structure
 */
function culturacsi_create_pages_recursive( $structure, $parent_id = 0, $parent_slug = '', &$stats = null ) {
    if ( $stats === null ) {
        $stats = array( 'created' => 0, 'reused' => 0 );
    }
    
    $created_pages = array();
    
    foreach ( $structure as $title => $data ) {
        $slug = isset( $data['slug'] ) ? $data['slug'] : sanitize_title( $title );
        $full_slug = ! empty( $parent_slug ) ? $parent_slug . '/' . $slug : $slug;
        
        // Generate content
        $children_titles = isset( $data['children'] ) ? array_keys( $data['children'] ) : array();
        $content = culturacsi_generate_page_content( $title, $children_titles, $full_slug );
        
        // Create or get page
        $page_result = culturacsi_get_or_create_page( $title, $full_slug, $parent_id, $content );
        $page_id = $page_result['id'];
        
        // Track statistics
        if ( $page_result['was_created'] ) {
            $stats['created']++;
        } else {
            $stats['reused']++;
        }
        
        $created_pages[ $title ] = array(
            'id' => $page_id,
            'slug' => $full_slug,
            'children' => array(),
        );
        
        // Recursively create children
        if ( isset( $data['children'] ) && ! empty( $data['children'] ) ) {
            $created_pages[ $title ]['children'] = culturacsi_create_pages_recursive( 
                $data['children'], 
                $page_id, 
                $full_slug,
                $stats
            );
        }
    }
    
    return $created_pages;
}

/**
 * Build menu structure from created pages
 */
function culturacsi_build_menu_structure( $pages_structure, $menu_id, $parent_item_id = 0 ) {
    $menu_items_added = 0;
    
    foreach ( $pages_structure as $title => $page_data ) {
        // Check if menu item already exists
        $existing_items = wp_get_nav_menu_items( $menu_id, array( 'post_status' => 'any' ) );
        $existing_item_id = 0;
        
        foreach ( $existing_items as $item ) {
            if ( $item->object_id == $page_data['id'] && $item->menu_item_parent == $parent_item_id ) {
                $existing_item_id = $item->ID;
                break;
            }
        }
        
        $menu_item_data = array(
            'menu-item-object-id' => $page_data['id'],
            'menu-item-object' => 'page',
            'menu-item-parent-id' => $parent_item_id,
            'menu-item-type' => 'post_type',
            'menu-item-title' => $title,
            'menu-item-status' => 'publish',
        );
        
        if ( $existing_item_id ) {
            // Update existing item
            $menu_item_data['menu-item-db-id'] = $existing_item_id;
            $menu_item_id = wp_update_nav_menu_item( $menu_id, $existing_item_id, $menu_item_data );
        } else {
            // Create new item
            $menu_item_id = wp_update_nav_menu_item( $menu_id, 0, $menu_item_data );
        }
        
        $menu_items_added++;
        
        // Recursively add children
        if ( ! empty( $page_data['children'] ) ) {
            $child_count = culturacsi_build_menu_structure( $page_data['children'], $menu_id, $menu_item_id );
            $menu_items_added += $child_count;
        }
    }
    
    return $menu_items_added;
}

/**
 * Main setup function
 */
function culturacsi_setup_settori_menu() {
    // Check user capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        return new WP_Error( 'unauthorized', 'You do not have permission to run this setup.' );
    }
    
    $results = array(
        'pages_created' => 0,
        'pages_reused' => 0,
        'menu_items_added' => 0,
        'errors' => array(),
    );
    
    // Get the Settori structure
    $structure = culturacsi_get_settori_structure();
    
    // Create all pages (with stats tracking)
    $stats = array( 'created' => 0, 'reused' => 0 );
    $pages_structure = culturacsi_create_pages_recursive( $structure, 0, 'settori', $stats );
    
    // Set results
    $results['pages_created'] = $stats['created'];
    $results['pages_reused'] = $stats['reused'];
    
    // Get or create primary menu
    $menu_name = 'Primary Menu';
    $menu_exists = wp_get_nav_menu_object( $menu_name );
    
    if ( ! $menu_exists ) {
        $menu_id = wp_create_nav_menu( $menu_name );
    } else {
        $menu_id = $menu_exists->term_id;
    }
    
    // Assign menu to location
    $locations = get_theme_mod( 'nav_menu_locations' );
    $locations['primary'] = $menu_id;
    set_theme_mod( 'nav_menu_locations', $locations );
    
    // Find or create Settori menu item
    $settori_item = null;
    $menu_items = wp_get_nav_menu_items( $menu_id );
    
    foreach ( $menu_items as $item ) {
        if ( strtolower( $item->title ) === 'settori' || stripos( $item->title, 'settori' ) !== false ) {
            $settori_item = $item;
            break;
        }
    }
    
    // If Settori doesn't exist, create it as a custom link first
    if ( ! $settori_item ) {
        $settori_page = get_page_by_path( 'settori' );
        if ( ! $settori_page ) {
            // Create Settori parent page
            $settori_page = culturacsi_get_or_create_page(
                'Settori', 
                'settori', 
                0, 
                '<h1>Settori</h1><p>Esplora tutti i settori di attività offerti da ACSI Cultura.</p>'
            );
            $settori_page_id = $settori_page['id'];
        } else {
            $settori_page_id = $settori_page->ID;
        }
        
        $settori_item_data = array(
            'menu-item-object-id' => $settori_page_id,
            'menu-item-object' => 'page',
            'menu-item-type' => 'post_type',
            'menu-item-title' => 'Settori',
            'menu-item-status' => 'publish',
        );
        
        $settori_item_id = wp_update_nav_menu_item( $menu_id, 0, $settori_item_data );
        $settori_item = (object) array( 'ID' => $settori_item_id );
    }
    
    // Add all Settori sub-items
    $menu_items_added = culturacsi_build_menu_structure( $pages_structure, $menu_id, $settori_item->ID );
    
    $results['menu_items_added'] = $menu_items_added;
    
    return $results;
}

// If this file is accessed directly (for testing), run the setup
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'settori setup', function() {
        $results = culturacsi_setup_settori_menu();
        WP_CLI::success( 'Settori menu setup completed!' );
        WP_CLI::line( 'Pages created: ' . $results['pages_created'] );
        WP_CLI::line( 'Menu items added: ' . $results['menu_items_added'] );
    } );
}
