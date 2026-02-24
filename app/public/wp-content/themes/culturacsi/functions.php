<?php
/**
 * Cultura ACSI Theme Functions
 */

function culturacsi_enqueue_scripts() {
    // Main Stylesheet
    wp_enqueue_style( 'culturacsi-style', get_stylesheet_uri() );

    // Font Awesome
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0' );

    // Google Fonts
    wp_enqueue_style( 'google-fonts', 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap', array(), null );

    // Main JS
    wp_enqueue_script( 'culturacsi-main', get_template_directory_uri() . '/js/main.js', array(), '1.0', true );

    // Enqueue news styles only on the news archive page
    if ( is_post_type_archive( 'news' ) ) {
        wp_enqueue_style( 'culturacsi-news-style', get_template_directory_uri() . '/css/news.css', array(), filemtime( get_template_directory() . '/css/news.css' ) );
    }
}
add_action( 'wp_enqueue_scripts', 'culturacsi_enqueue_scripts' );

// Include Patterns
require get_template_directory() . '/inc/patterns.php';
require get_template_directory() . '/inc/page-options.php';

// Include Customizer Options
require get_template_directory() . '/inc/customizer.php';
require get_template_directory() . '/inc/dynamic-css.php';

// Load setup tooling only where it is needed.
if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
    require get_template_directory() . '/inc/settori-setup.php';
    require_once get_template_directory() . '/admin-settori-setup.php';
}

/**
 * Fallback menu if no menu is assigned
 */
function culturacsi_fallback_menu() {
    echo '<ul class="nav-list">';
    echo '<li class="nav-item"><a href="' . esc_url( home_url( '/' ) ) . '" class="nav-link"><i class="fas fa-home"></i></a></li>';
    echo '<li class="nav-item"><a href="' . esc_url( home_url( '/' ) ) . '" class="nav-link">ACSI Cultura</a></li>';
    echo '<li class="nav-item"><a href="' . esc_url( home_url( '/' ) ) . '" class="nav-link">Servizi</a></li>';
    echo '<li class="nav-item"><a href="' . esc_url( home_url( '/' ) ) . '" class="nav-link">Convenzioni</a></li>';
    echo '<li class="nav-item"><a href="' . esc_url( home_url( '/' ) ) . '" class="nav-link">Settori</a></li>';
    echo '<li class="nav-item"><a href="' . esc_url( home_url( '/' ) ) . '" class="nav-link">Documenti online</a></li>';
    echo '<li class="nav-item"><a href="' . esc_url( home_url( '/' ) ) . '" class="nav-link">Notizie</a></li>';
    echo '<li class="nav-item"><a href="' . esc_url( home_url( '/' ) ) . '" class="nav-link">Contatti</a></li>';
    echo '</ul>';
}

/**
 * Custom Walker for Navigation Menu to support dropdowns
 */
class Culturacsi_Walker_Nav_Menu extends Walker_Nav_Menu {
    
    function start_lvl( &$output, $depth = 0, $args = null ) {
        $indent = str_repeat( "\t", $depth );
        $output .= "\n$indent<ul class=\"dropdown\">\n";
    }
    
    function end_lvl( &$output, $depth = 0, $args = null ) {
        $indent = str_repeat( "\t", $depth );
        $output .= "$indent</ul>\n";
    }
    
    function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
        $indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';
        
        $classes = empty( $item->classes ) ? array() : (array) $item->classes;
        $classes[] = 'nav-item menu-item-' . $item->ID;
        
        if ( in_array( 'current-menu-item', $classes ) ) {
            $classes[] = 'active';
        }
        
        $class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );
        $class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';
        
        $id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args );
        $id = $id ? ' id="' . esc_attr( $id ) . '"' : '';
        
        $output .= $indent . '<li' . $id . $class_names . '>';
        
        $attributes = ! empty( $item->attr_title ) ? ' title="' . esc_attr( $item->attr_title ) . '"' : '';
        $attributes .= ! empty( $item->target ) ? ' target="' . esc_attr( $item->target ) . '"' : '';
        $attributes .= ! empty( $item->xfn ) ? ' rel="' . esc_attr( $item->xfn ) . '"' : '';
        $attributes .= ! empty( $item->url ) ? ' href="' . esc_attr( $item->url ) . '"' : '';
        
        $item_output = isset( $args->before ) ? $args->before : '';
        $item_output .= '<a class="nav-link"' . $attributes . '>';
        $item_output .= ( isset( $args->link_before ) ? $args->link_before : '' ) . apply_filters( 'the_title', $item->title, $item->ID ) . ( isset( $args->link_after ) ? $args->link_after : '' );
        $item_output .= '</a>';
        $item_output .= isset( $args->after ) ? $args->after : '';
        
        $output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
    }
    
    function end_el( &$output, $item, $depth = 0, $args = null ) {
        $output .= "</li>\n";
    }
}

function culturacsi_setup() {
    // Add title tag support
    add_theme_support( 'title-tag' );
    // Add post thumbnails support
    add_theme_support( 'post-thumbnails' );
    // Add support for block styles
    add_theme_support( 'wp-block-styles' );
    // Add support for editor styles
    add_theme_support( 'editor-styles' );
    // Enqueue editor styles
    add_editor_style( 'style.css' );
    
    // Register navigation menus
    register_nav_menus( array(
        'primary' => __( 'Menu principale', 'culturacsi' ),
    ) );
    
    // Add HTML5 support
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ) );
}
add_action( 'after_setup_theme', 'culturacsi_setup' );

/**
 * Force Italian translations for theme/plugin user-facing strings.
 */
function culturacsi_force_italian_gettext( $translated, $text, $domain ) {
    if ( ! in_array( $domain, array( 'culturacsi', 'assoc-portal' ), true ) ) {
        return $translated;
    }

    static $map = null;
    if ( null === $map ) {
        $map = array(
            'Primary Menu' => 'Menu principale',
            'News' => 'Notizie',
            'Read More' => 'Leggi di piu',
            'No content found' => 'Nessun contenuto trovato',
            'Pages:' => 'Pagine:',
            'Page Display Options' => 'Opzioni visualizzazione pagina',
            'Control the display of header and footer elements for this page.' => 'Controlla la visualizzazione di header e footer per questa pagina.',
            'Hide Header' => 'Nascondi header',
            'Transparent Header' => 'Header trasparente',
            'Hide Footer' => 'Nascondi footer',
            'Hide Page Title' => 'Nascondi titolo pagina',
            'Global Colors' => 'Colori globali',
            'Define your global color palette.' => 'Definisci la palette colori globale.',
            'Layout & Containers' => 'Layout e contenitori',
            'Accent 1' => 'Accento 1',
            'Accent 2' => 'Accento 2',
            'Contrast 1' => 'Contrasto 1',
            'Contrast 2' => 'Contrasto 2',
            'Contrast 3' => 'Contrasto 3',
            'Base 1 (Background)' => 'Base 1 (Sfondo)',
            'Base 2 (Content)' => 'Base 2 (Contenuto)',
            'Base 3' => 'Base 3',
            'White' => 'Bianco',
            'Black' => 'Nero',
            'Control the width of the main layout containers. You can use any valid CSS unit (px, %, vw, etc).' => 'Controlla la larghezza dei contenitori principali. Puoi usare qualsiasi unita CSS valida (px, %, vw, ecc).',
            'Exterior Container Width' => 'Larghezza contenitore esterno',
            'Exterior Container Background' => 'Sfondo contenitore esterno',
            'Middle Container Width' => 'Larghezza contenitore centrale',
            'Middle Container Background' => 'Sfondo contenitore centrale',
            'Inner Container Layout Mode' => 'Modalita layout contenitore interno',
            'Choose "Fluid" to let content stretch to the edges of the Wrapper. Choose "Boxed" to constrain it to a specific width.' => 'Scegli "Fluido" per estendere il contenuto ai bordi del wrapper. Scegli "Boxed" per vincolarlo a una larghezza specifica.',
            'Boxed (Fixed Width)' => 'Boxed (larghezza fissa)',
            'Fluid (100% of Wrapper)' => 'Fluido (100% del wrapper)',
            'Inner Container Width' => 'Larghezza contenitore interno',
            'Inner Container Background' => 'Sfondo contenitore interno',
            'Global Typography' => 'Tipografia globale',
            'Base Font Family' => 'Famiglia font base',
            'Headings Font Family' => 'Famiglia font titoli',
            'Base Font Size' => 'Dimensione font base',
            '%s Font Size' => 'Dimensione font %s',
            'Header Options' => 'Opzioni header',
            'Header Background Color' => 'Colore sfondo header',
            'Inherit Width from Middle Container' => 'Eredita larghezza dal contenitore centrale',
            'If checked, Header will match the width of the Middle Container.' => 'Se attivo, l header avra la stessa larghezza del contenitore centrale.',
            'Header Wrapper Width' => 'Larghezza wrapper header',
            'Header Wrapper Padding (px)' => 'Padding wrapper header (px)',
            'Header Logos Container Width' => 'Larghezza contenitore loghi header',
            'Header Horizontal Padding' => 'Padding orizzontale header',
            'Navbar Container Width' => 'Larghezza contenitore navbar',
            'Navigation Options' => 'Opzioni navigazione',
            'Nav Background Color' => 'Colore sfondo navigazione',
            'Nav Link Color' => 'Colore link navigazione',
            'Nav Link Hover Color' => 'Colore hover link navigazione',
            'Nav Font Size' => 'Dimensione font navigazione',
            'Navbar Vertical Padding' => 'Padding verticale navbar',
            'Navbar Horizontal Padding' => 'Padding orizzontale navbar',
            'Full Width Navbar Background' => 'Sfondo navbar a larghezza piena',
            'Footer Options' => 'Opzioni footer',
            'Copyright Text' => 'Testo copyright',
            '© 2023 CulturaCSI. All rights reserved.' => '© 2023 CulturaCSI. Tutti i diritti riservati.',
            'Settori Menu and Pages Setup' => 'Configurazione menu e pagine Settori',
            'This will create all Settori pages and build the mega-menu structure under the existing "Settori" menu item.' => 'Questa operazione crea tutte le pagine Settori e costruisce la struttura menu sotto la voce "Settori".',
            'What will be created:' => 'Cosa verra creato:',
            'All Settori category pages (Arte, Ambiente, Valorizzazione del Territorio, Culture di nicchia)' => 'Tutte le pagine categoria Settori (Arte, Ambiente, Valorizzazione del Territorio, Culture di nicchia)',
            'All subcategory and leaf pages with proper hierarchy' => 'Tutte le sottocategorie e pagine foglia con gerarchia corretta',
            'Menu structure nested under "Settori" in the primary navigation' => 'Struttura menu annidata sotto "Settori" nella navigazione principale',
            'Page content with H1, intro paragraph, and child links' => 'Contenuto pagina con H1, paragrafo introduttivo e link ai figli',
            'Note:' => 'Nota:',
            'If pages with the same titles already exist, they will be reused and updated with the correct hierarchy.' => 'Se pagine con lo stesso titolo esistono gia, verranno riutilizzate e aggiornate con la gerarchia corretta.',
            'Run Setup' => 'Esegui configurazione',
            'Current Menu Status' => 'Stato attuale del menu',
            'Primary Menu:' => 'Menu principale:',
            'No Settori menu items found yet.' => 'Nessuna voce menu Settori trovata.',
            'No primary menu assigned yet.' => 'Nessun menu principale assegnato.',
            'Setup completed successfully! Created %d new pages, reused %d existing pages, and added %d menu items.' => 'Configurazione completata! Create %d nuove pagine, riutilizzate %d pagine esistenti e aggiunte %d voci menu.',

            'Associations' => 'Associazioni',
            'Association' => 'Associazione',
            'Association Archives' => 'Archivio Associazioni',
            'Association Attributes' => 'Attributi Associazione',
            'Parent Association:' => 'Associazione genitore:',
            'All Associations' => 'Tutte le associazioni',
            'Add New Association' => 'Aggiungi nuova associazione',
            'Add New' => 'Aggiungi nuova',
            'New Association' => 'Nuova associazione',
            'Edit Association' => 'Modifica associazione',
            'Update Association' => 'Aggiorna associazione',
            'View Association' => 'Visualizza associazione',
            'View Associations' => 'Visualizza associazioni',
            'Search Association' => 'Cerca associazione',
            'Not found' => 'Non trovato',
            'Not found in Trash' => 'Non trovato nel cestino',
            'Set logo' => 'Imposta logo',
            'Remove logo' => 'Rimuovi logo',
            'Use as logo' => 'Usa come logo',
            'Insert into association' => 'Inserisci nell associazione',
            'Uploaded to this association' => 'Caricato in questa associazione',
            'Associations list' => 'Elenco associazioni',
            'Associations list navigation' => 'Navigazione elenco associazioni',
            'Filter associations list' => 'Filtra elenco associazioni',
            'Association profiles' => 'Profili associazioni',
            'Events' => 'Eventi',
            'Event' => 'Evento',
            'Event Archives' => 'Archivio eventi',
            'Event Attributes' => 'Attributi evento',
            'Parent Event:' => 'Evento genitore:',
            'All Events' => 'Tutti gli eventi',
            'Add New Event' => 'Aggiungi nuovo evento',
            'New Event' => 'Nuovo evento',
            'Edit Event' => 'Modifica evento',
            'Update Event' => 'Aggiorna evento',
            'View Event' => 'Visualizza evento',
            'View Events' => 'Visualizza eventi',
            'Search Event' => 'Cerca evento',
            'Events submitted by associations' => 'Eventi inviati dalle associazioni',
            'News Item' => 'Notizia',
            'News Archives' => 'Archivio notizie',
            'News Item Attributes' => 'Attributi notizia',
            'Parent News Item:' => 'Notizia genitore:',
            'All News' => 'Tutte le notizie',
            'Add New News Item' => 'Aggiungi nuova notizia',
            'New News Item' => 'Nuova notizia',
            'Edit News Item' => 'Modifica notizia',
            'Update News Item' => 'Aggiorna notizia',
            'View News Item' => 'Visualizza notizia',
            'View News Items' => 'Visualizza notizie',
            'Search News' => 'Cerca notizie',
            'News articles' => 'Articoli notizie',
            'Activity Categories' => 'Categorie attivita',
            'Activity Category' => 'Categoria attivita',
            'Search Categories' => 'Cerca categorie',
            'All Categories' => 'Tutte le categorie',
            'Parent Category' => 'Categoria genitore',
            'Parent Category:' => 'Categoria genitore:',
            'Edit Category' => 'Modifica categoria',
            'Update Category' => 'Aggiorna categoria',
            'Add New Category' => 'Aggiungi nuova categoria',
            'New Category Name' => 'Nome nuova categoria',
            'Event Types' => 'Tipi di evento',
            'Event Type' => 'Tipo di evento',
            'Search Event Types' => 'Cerca tipi evento',
            'All Event Types' => 'Tutti i tipi evento',
            'Edit Event Type' => 'Modifica tipo evento',
            'Update Event Type' => 'Aggiorna tipo evento',
            'Add New Event Type' => 'Aggiungi nuovo tipo evento',
            'New Event Type Name' => 'Nome nuovo tipo evento',
            'Association Manager' => 'Gestore Associazione',
            'Association Link' => 'Collegamento associazione',
            'Managed Association' => 'Associazione gestita',
            '-- Select Association --' => '-- Seleziona associazione --',
            'Link this user to the association they manage.' => 'Collega questo utente all associazione che gestisce.',
            'You must be logged in as an Association Manager to view this page.' => 'Devi essere autenticato come Gestore Associazione per visualizzare questa pagina.',
            'Association Portal' => 'Portale associazioni',
            'Welcome to your portal. From here you can manage your association profile and your events.' => 'Benvenuto nel portale. Da qui puoi gestire profilo associazione ed eventi.',
            'Edit My Association Profile' => 'Modifica il mio profilo associazione',
            'Manage My Events' => 'Gestisci i miei eventi',
            'Your user account is not linked to an association. Please contact an administrator.' => 'Il tuo account non e collegato a una associazione. Contatta un amministratore.',
            'The linked item is not a valid association. Please contact an administrator.' => 'L elemento collegato non e una associazione valida. Contatta un amministratore.',
            'Profile updated successfully!' => 'Profilo aggiornato correttamente!',
            'Editing Profile for: %s' => 'Modifica profilo per: %s',
            'Contact Information' => 'Informazioni contatto',
            'Contact Email' => 'Email contatto',
            'Phone' => 'Telefono',
            'Website' => 'Sito web',
            'Location' => 'Sede',
            'Address' => 'Indirizzo',
            'City' => 'Citta',
            'Province (e.g., "MI")' => 'Provincia (es. "MI")',
            'Social Media' => 'Social',
            'Facebook URL' => 'URL Facebook',
            'Instagram URL' => 'URL Instagram',
            'YouTube URL' => 'URL YouTube',
            'TikTok URL' => 'URL TikTok',
            'X (Twitter) URL' => 'URL X (Twitter)',
            'Classification' => 'Classificazione',
            'Select the categories that best describe your association\'s activities.' => 'Seleziona le categorie che descrivono meglio le attivita della tua associazione.',
            'Association Logo' => 'Logo associazione',
            'Current Logo:' => 'Logo attuale:',
            'Upload New Logo (replaces existing)' => 'Carica nuovo logo (sostituisce quello esistente)',
            'Max file size 5MB. Images only.' => 'Dimensione massima 5MB. Solo immagini.',
            'Save Profile' => 'Salva profilo',
            'Event submitted successfully for review!' => 'Evento inviato correttamente per revisione!',
            'My Events' => 'I miei eventi',
            'Title' => 'Titolo',
            'Start Date' => 'Data inizio',
            'Status' => 'Stato',
            'Actions' => 'Azioni',
            'You have not created any events yet.' => 'Non hai ancora creato eventi.',
            'Edit' => 'Modifica',
            'You do not have permission to edit this event, or the event does not exist.' => 'Non hai i permessi per modificare questo evento, oppure non esiste.',
            'Create New Event' => 'Crea nuovo evento',
            'All events are submitted for review and must be approved by an administrator before appearing on the calendar.' => 'Tutti gli eventi vengono inviati in revisione e devono essere approvati da un amministratore prima di apparire nel calendario.',
            'Event Title' => 'Titolo evento',
            'Start Date & Time' => 'Data e ora inizio',
            'End Date & Time (Optional)' => 'Data e ora fine (facoltativa)',
            'Event Description' => 'Descrizione evento',
            'Venue & Location' => 'Luogo e localita',
            'Venue Name' => 'Nome sede',
            'Details' => 'Dettagli',
            'Registration URL' => 'URL iscrizione',
            'Event Image' => 'Immagine evento',
            'Current Image:' => 'Immagine attuale:',
            'Submit for Review' => 'Invia per revisione',
            'Prev' => 'Prec',
            'Next' => 'Succ',
        );
    }

    return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
}
add_filter( 'gettext', 'culturacsi_force_italian_gettext', 20, 3 );

/**
 * Italian slugs/archives for custom post types and taxonomies.
 */
function culturacsi_force_italian_cpt_slugs( $args, $post_type ) {
    if ( 'association' === $post_type ) {
        $args['has_archive'] = 'associazioni';
        $args['rewrite']     = array( 'slug' => 'associazione' );
    } elseif ( 'event' === $post_type ) {
        $args['has_archive'] = 'calendario';
        $args['rewrite']     = array( 'slug' => 'evento', 'with_front' => false );
    } elseif ( 'news' === $post_type ) {
        $args['has_archive'] = 'notizie';
        $args['rewrite']     = array( 'slug' => 'notizia' );
    }

    return $args;
}
add_filter( 'register_post_type_args', 'culturacsi_force_italian_cpt_slugs', 20, 2 );

function culturacsi_force_italian_tax_slugs( $args, $taxonomy ) {
    if ( 'activity_category' === $taxonomy ) {
        $args['rewrite'] = array( 'slug' => 'categoria-attivita' );
    } elseif ( 'event_type' === $taxonomy ) {
        $args['rewrite'] = array( 'slug' => 'tipo-evento' );
    }

    return $args;
}
add_filter( 'register_taxonomy_args', 'culturacsi_force_italian_tax_slugs', 20, 2 );

function add_favicon() {
    echo '<link rel="icon" href="' . get_stylesheet_directory_uri() . '/images/favicon.ico" type="image/x-icon" />';
}
add_action('wp_head', 'add_favicon');

function culturacsi_settori_search_form() {
    ?>
    <div class="settori-search-form-container">
        <form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/associazioni/' ) ); ?>">
            <div class="search-form-grid">
                <div class="form-row top-row">
                    <div class="form-group">
                        <label for="macro_categoria">Macro categoria</label>
                        <select name="macro_categoria" id="macro_categoria">
                            <option value="">Tutte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="settore">Settore</label>
                        <select name="settore" id="settore">
                            <option value="">Tutti</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="settore2">Settore 2</label>
                        <select name="settore2" id="settore2">
                            <option value="">Tutti</option>
                        </select>
                    </div>
                </div>
                <div class="form-row bottom-row">
                    <div class="form-group">
                        <label for="regione">Regione</label>
                        <select name="regione" id="regione">
                            <option value="">Tutte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="provincia">Provincia</label>
                        <select name="provincia" id="provincia">
                            <option value="">Tutte</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="comune">Comune / Città</label>
                        <select name="comune" id="comune">
                            <option value="">Tutti</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="reset" class="reset-button">Azzera</button>
                    </div>
                </div>
            </div>
            <div class="search-submit-wrapper">
                <button type="submit" class="search-submit">Cerca</button>
            </div>
        </form>
    </div>
    <?php
}
add_action( 'culturacsi_settori_search_form', 'culturacsi_settori_search_form' );
