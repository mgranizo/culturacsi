<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Multi-language support: Italian, Spanish, English, French
 * Language is determined by URL path, query parameter, or cookie
 */
function culturacsi_get_current_language(): string {
	static $cookie_written = false;

	// Check query parameter first (takes priority)
	if ( isset( $_GET['lang'] ) ) {
		$lang = sanitize_text_field( wp_unslash( $_GET['lang'] ) );

		$allowed = array( 'it', 'es', 'en', 'fr' );
		if ( in_array( $lang, $allowed, true ) ) {
			// Persist language once per request to avoid duplicating Set-Cookie headers.
			if ( ! $cookie_written && ( ! isset( $_COOKIE['culturacsi_lang'] ) || $_COOKIE['culturacsi_lang'] !== $lang ) ) {
				$cookie_expires = time() + ( 30 * DAY_IN_SECONDS );
				$cookie_secure  = is_ssl();
				if ( PHP_VERSION_ID >= 70300 ) {
					setcookie(
						'culturacsi_lang',
						$lang,
						array(
							'expires'  => $cookie_expires,
							'path'     => '/',
							'secure'   => $cookie_secure,
							'httponly' => true,
							'samesite' => 'Lax',
						)
					);
				} else {
					// Fallback for older PHP versions.
					setcookie( 'culturacsi_lang', $lang, $cookie_expires, '/; samesite=Lax', '', $cookie_secure, true );
				}
				$cookie_written = true;
			}
			// Keep current-request reads consistent after switching via query string.
			$_COOKIE['culturacsi_lang'] = $lang;
			return $lang;
		}
	}
	
	// Check cookie if no query parameter
	if ( isset( $_COOKIE['culturacsi_lang'] ) ) {
		$lang = sanitize_text_field( wp_unslash( $_COOKIE['culturacsi_lang'] ) );
		$allowed = array( 'it', 'es', 'en', 'fr' );
		if ( in_array( $lang, $allowed, true ) ) {
			return $lang;
		}
	}
	
	// Check URL path
	$path = trim( parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

	if ( preg_match( '#^(it|es|en|fr)(/|$)#', $path, $matches ) ) {

		return $matches[1];
	}
	
	// Default to Italian
	return 'it';
}

/**
 * Get the locale for the current language.
 *
 * @return string WordPress locale code (e.g., 'it_IT', 'es_ES', 'en_US', 'fr_FR')
 */
function culturacsi_get_current_locale(): string {
	$current_lang = culturacsi_get_current_language();
	$lang_locale_map = array(
		'it' => 'it_IT',
		'es' => 'es_ES',
		'en' => 'en_US',
		'fr' => 'fr_FR',
	);
	return $lang_locale_map[ $current_lang ] ?? 'it_IT';
}

// Store current language globally for use throughout the plugin
// Note: this is set once at file load time; filters use culturacsi_get_current_language() directly for accuracy.
$GLOBALS['culturacsi_current_lang'] = culturacsi_get_current_language();

/**
 * Prevent full-page cache from serving stale language variants.
 */
add_action(
	'init',
	static function() {
		if ( isset( $_GET['lang'] ) || isset( $_COOKIE['culturacsi_lang'] ) ) {
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}
			if ( ! defined( 'DONOTCACHEDB' ) ) {
				define( 'DONOTCACHEDB', true );
			}
			if ( ! defined( 'DONOTMINIFY' ) ) {
				define( 'DONOTMINIFY', true );
			}
		}
	},
	1
);

add_action(
	'template_redirect',
	static function() {
		if ( isset( $_GET['lang'] ) || isset( $_COOKIE['culturacsi_lang'] ) ) {
			nocache_headers();
			header( 'Vary: Cookie, Accept-Language', false );
		}
	},
	1
);

add_filter(
	'pre_option_WPLANG',
	static function() {
		return culturacsi_get_current_locale();
	}
);

add_filter(
	'locale',
	static function() {
		return culturacsi_get_current_locale();
	},
	20
);

add_filter(
	'determine_locale',
	static function() {
		return culturacsi_get_current_locale();
	},
	20
);

add_filter(
	'language_attributes',
	static function( $output ) {
		$current_lang = culturacsi_get_current_language();
		$lang_map = array(
			'it' => 'it-IT',
			'es' => 'es',
			'en' => 'en-US',
			'fr' => 'fr-FR',
		);
		$lang_attr = $lang_map[ $current_lang ] ?? 'it-IT';
		return preg_replace( '/lang=("|\')[^\'"]+("|\')/i', 'lang="' . $lang_attr . '"', $output );
	},
	20
);

// Hide the frontend WordPress admin bar for all logged-in users.
add_filter(
	'show_admin_bar',
	static function( $show ) {
		if ( is_admin() ) {
			return $show;
		}
		return false;
	},
	20
);

/**
 * Ensure reserved-area routes are real editable WP pages.
 */
function culturacsi_it_ensure_reserved_area_pages(): void {
	if ( wp_installing() ) {
		return;
	}

	// Version string: bump this ONLY when new reserved-area pages need to be created.
	// The function runs once per version, then permanently stops until the next version bump.
	// This prevents deleted pages from being recreated on every request.
	$pages_schema_version = 'v5';

	if ( get_option( 'culturacsi_reserved_pages_version' ) === $pages_schema_version && ! isset( $_GET['force_pages'] ) ) {
		return;
	}
	// Mark as run permanently (autoload: false = no overhead on every request).
	update_option( 'culturacsi_reserved_pages_version', $pages_schema_version, false );

	$reserved_nav_block = '<!-- wp:shortcode -->[assoc_reserved_nav]<!-- /wp:shortcode -->';
	$dashboard_block    = '<!-- wp:shortcode -->[assoc_dashboard]<!-- /wp:shortcode -->';
	$events_list_block  = '<!-- wp:shortcode -->[culturacsi_events_search]<!-- /wp:shortcode -->' . "\n\n" . '<!-- wp:shortcode -->[assoc_events_list]<!-- /wp:shortcode -->';
	$event_form_block   = '<!-- wp:shortcode -->[assoc_event_form]<!-- /wp:shortcode -->';
	$news_list_block    = '<!-- wp:shortcode -->[culturacsi_news_panel_search]<!-- /wp:shortcode -->' . "\n\n" . '<!-- wp:shortcode -->[assoc_news_list]<!-- /wp:shortcode -->';
	$news_form_block    = '<!-- wp:shortcode -->[assoc_news_form]<!-- /wp:shortcode -->';
	$content_list_block = '<!-- wp:shortcode -->[assoc_content_entries_list]<!-- /wp:shortcode -->';
	$content_form_block = '<!-- wp:shortcode -->[assoc_content_entry_form]<!-- /wp:shortcode -->';
	$sections_manager_block = '<!-- wp:shortcode -->[assoc_content_sections_manager]<!-- /wp:shortcode -->';
	$users_list_block   = '<!-- wp:shortcode -->[culturacsi_users_search]<!-- /wp:shortcode -->' . "\n\n" . '<!-- wp:shortcode -->[assoc_users_list]<!-- /wp:shortcode -->';
	$users_form_block   = '<!-- wp:shortcode -->[assoc_users_form]<!-- /wp:shortcode -->';
	$assocs_list_block  = '<!-- wp:shortcode -->[culturacsi_associations_search]<!-- /wp:shortcode -->' . "\n\n" . '<!-- wp:shortcode -->[assoc_associations_list]<!-- /wp:shortcode -->';
	$assocs_form_block  = '<!-- wp:shortcode -->[assoc_associations_form]<!-- /wp:shortcode -->';
	$user_profile_block = '<!-- wp:shortcode -->[assoc_user_profile_form]<!-- /wp:shortcode -->';
	$profile_block      = '<!-- wp:shortcode -->[assoc_profile_form]<!-- /wp:shortcode -->';
	$association_block  = '<!-- wp:shortcode -->[assoc_association_form]<!-- /wp:shortcode -->';
	$admin_panel_block  = '<!-- wp:shortcode -->[assoc_admin_control_panel]<!-- /wp:shortcode -->';

	$compose_content = static function( string $primary_block ) use ( $reserved_nav_block ): string {
		return $reserved_nav_block . "\n\n" . $primary_block;
	};

	$normalize_markup = static function( string $markup ): string {
		$markup = trim( $markup );
		return (string) preg_replace( '/\s+/', ' ', $markup );
	};

	$parent_page = get_page_by_path( 'area-riservata', OBJECT, 'page' );
	$parent_id   = 0;

	if ( $parent_page instanceof WP_Post ) {
		$parent_id = (int) $parent_page->ID;

		$current_parent_content = (string) $parent_page->post_content;
		$legacy_parent_content  = array(
			'',
			$dashboard_block,
		);
		$normalized_parent      = $normalize_markup( $current_parent_content );
		$target_parent_content  = $compose_content( $dashboard_block );

		if ( in_array( $normalized_parent, array_map( $normalize_markup, $legacy_parent_content ), true ) ) {
			wp_update_post(
				array(
					'ID'           => $parent_id,
					'post_content' => $target_parent_content,
				)
			);
		}
	} else {
		$parent_created = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Area Riservata',
				'post_name'    => 'area-riservata',
				'post_content' => $compose_content( $dashboard_block ),
			),
			true
		);

		if ( is_wp_error( $parent_created ) || (int) $parent_created <= 0 ) {
			return;
		}

		$parent_id = (int) $parent_created;
	}

	$ensure_child_page = static function( string $path, string $title, string $slug, string $content, int $expected_parent_id, array $legacy_contents = array() ) use ( $normalize_markup ): int {
		$page = get_page_by_path( $path, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			$updates = array( 'ID' => (int) $page->ID );
			$needs_update = false;

			if ( (int) $page->post_parent !== $expected_parent_id ) {
				$updates['post_parent'] = $expected_parent_id;
				$needs_update = true;
			}

			if ( trim( (string) $page->post_content ) === '' ) {
				$updates['post_content'] = $content;
				$needs_update = true;
			} elseif ( ! empty( $legacy_contents ) && strpos( (string) $page->post_content, '[assoc_reserved_nav]' ) === false ) {
				$current_normalized = $normalize_markup( (string) $page->post_content );
				$legacy_normalized  = array_map( $normalize_markup, $legacy_contents );
				if ( in_array( $current_normalized, $legacy_normalized, true ) ) {
					$updates['post_content'] = $content;
					$needs_update = true;
				}
			}

			if ( $needs_update ) {
				wp_update_post( $updates );
			}

			return (int) $page->ID;
		}

		$created_id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_parent'  => $expected_parent_id,
				'post_content' => $content,
			),
			true
		);

		return is_wp_error( $created_id ) ? 0 : (int) $created_id;
	};

	$events_page_id = $ensure_child_page(
		'area-riservata/eventi',
		'Area Riservata Eventi',
		'eventi',
		$compose_content( $events_list_block ),
		$parent_id,
		array( $events_list_block )
	);

	if ( $events_page_id > 0 ) {
		$ensure_child_page(
			'area-riservata/eventi/nuovo',
			'Nuovo Evento',
			'nuovo',
			$compose_content( $event_form_block ),
			$events_page_id,
			array( $event_form_block )
		);
	}

	$news_page_id = $ensure_child_page(
		'area-riservata/notizie',
		'Area Riservata Notizie',
		'notizie',
		$compose_content( $news_list_block ),
		$parent_id,
		array( $news_list_block )
	);

	if ( $news_page_id > 0 ) {
		$ensure_child_page(
			'area-riservata/notizie/nuova',
			'Nuova Notizia',
			'nuova',
			$compose_content( $news_form_block ),
			$news_page_id,
			array( $news_form_block )
		);
	}

	$content_page_id = $ensure_child_page(
		'area-riservata/contenuti',
		'Area Riservata Contenuti',
		'contenuti',
		$compose_content( $content_list_block ),
		$parent_id,
		array( $content_list_block )
	);

	if ( $content_page_id > 0 ) {
		$ensure_child_page(
			'area-riservata/contenuti/nuovo',
			'Nuovo Contenuto',
			'nuovo',
			$compose_content( $content_form_block ),
			$content_page_id,
			array( $content_form_block )
		);
	}
	$ensure_child_page(
		'area-riservata/sezioni',
		'Area Riservata Sezioni',
		'sezioni',
		$compose_content( $sections_manager_block ),
		$parent_id,
		array( $sections_manager_block )
	);

	$users_page_id = $ensure_child_page(
		'area-riservata/utenti',
		'Area Riservata Utenti',
		'utenti',
		$compose_content( $users_list_block ),
		$parent_id,
		array( $users_list_block )
	);

	if ( $users_page_id > 0 ) {
		$ensure_child_page(
			'area-riservata/utenti/nuovo',
			'Nuovo Utente',
			'nuovo',
			$compose_content( $users_form_block ),
			$users_page_id,
			array( $users_form_block )
		);
	}

	$assocs_page_id = $ensure_child_page(
		'area-riservata/associazioni',
		'Area Riservata Associazioni',
		'associazioni',
		$compose_content( $assocs_list_block ),
		$parent_id,
		array( $assocs_list_block )
	);

	if ( $assocs_page_id > 0 ) {
		$ensure_child_page(
			'area-riservata/associazioni/nuova',
			'Nuova Associazione',
			'nuova',
			$compose_content( $assocs_form_block ),
			$assocs_page_id,
			array( $assocs_form_block )
		);
	}

	$ensure_child_page(
		'area-riservata/profilo-utente',
		'Area Riservata Profilo Utente',
		'profilo-utente',
		$compose_content( $user_profile_block ),
		$parent_id,
		array( $user_profile_block )
	);

	$ensure_child_page(
		'area-riservata/profilo',
		'Area Riservata Profilo',
		'profilo',
		$compose_content( $profile_block ),
		$parent_id,
		array( $profile_block )
	);

	$ensure_child_page(
		'area-riservata/associazione',
		'Area Riservata Associazione',
		'associazione',
		$compose_content( $association_block ),
		$parent_id,
		array( $association_block )
	);

	$ensure_child_page(
		'area-riservata/amministrazione',
		'Area Riservata Amministrazione',
		'amministrazione',
		$compose_content( $admin_panel_block ),
		$parent_id,
		array( $admin_panel_block )
	);
}
add_action( 'init', 'culturacsi_it_ensure_reserved_area_pages', 25 );

/**
 * Safety net: force users/associations reserved pages to render expected shortcodes
 * even if page content was manually altered.
 */
function culturacsi_it_force_reserved_section_content( string $content ): string {
	if ( is_admin() || ! is_singular( 'page' ) ) {
		return $content;
	}

	$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	$queried_id = get_queried_object_id();
	$raw_content = ( $queried_id > 0 ) ? (string) get_post_field( 'post_content', $queried_id ) : $content;
	$forced_markup = '';

	if (
		'area-riservata/eventi' === $path &&
		(
			false === strpos( $raw_content, '[assoc_events_list]' ) ||
			false === strpos( $raw_content, '[culturacsi_events_search]' )
		)
	) {
		$forced_markup = '[assoc_reserved_nav]' . "\n\n" . '[culturacsi_events_search]' . "\n\n" . '[assoc_events_list]';
	}
	if (
		'area-riservata/notizie' === $path &&
		(
			false === strpos( $raw_content, '[assoc_news_list]' ) ||
			false === strpos( $raw_content, '[culturacsi_news_panel_search]' )
		)
	) {
		$forced_markup = '[assoc_reserved_nav]' . "\n\n" . '[culturacsi_news_panel_search]' . "\n\n" . '[assoc_news_list]';
	}
	if (
		'area-riservata/contenuti' === $path &&
		false === strpos( $raw_content, '[assoc_content_entries_list]' )
	) {
		$forced_markup = '[assoc_reserved_nav]' . "\n\n" . '[assoc_content_entries_list]';
	}
	if (
		'area-riservata/sezioni' === $path &&
		false === strpos( $raw_content, '[assoc_content_sections_manager]' )
	) {
		$forced_markup = '[assoc_reserved_nav]' . "\n\n" . '[assoc_content_sections_manager]';
	}
	if (
		'area-riservata/utenti' === $path &&
		(
			false === strpos( $raw_content, '[assoc_users_list]' ) ||
			false === strpos( $raw_content, '[culturacsi_users_search]' )
		)
	) {
		$forced_markup = '[assoc_reserved_nav]' . "\n\n" . '[culturacsi_users_search]' . "\n\n" . '[assoc_users_list]';
	}
	if (
		'area-riservata/associazioni' === $path &&
		(
			false === strpos( $raw_content, '[assoc_associations_list]' ) ||
			false === strpos( $raw_content, '[culturacsi_associations_search]' )
		)
	) {
		$forced_markup = '[assoc_reserved_nav]' . "\n\n" . '[culturacsi_associations_search]' . "\n\n" . '[assoc_associations_list]';
	}

	if ( '' !== $forced_markup ) {
		return $forced_markup;
	}

	return $content;
}
// Disabled to preserve editor-defined block layout/spacing (Spacer/Separator) on reserved pages.
// add_filter( 'the_content', 'culturacsi_it_force_reserved_section_content', 1 );

function culturacsi_it_gettext( $translated, $text, $domain ) {
	$domains = array( 'kadence', 'assoc-portal', 'culturacsi' );
	if ( ! in_array( $domain, $domains, true ) ) {
		return $translated;
	}

	$current_lang = culturacsi_get_current_language();

	static $it_map = null;
	static $es_map = null;
	static $en_map = null;
	static $fr_map = null;

	if ( null === $it_map ) {
		$it_map = array(
			// Kadence/common.
			'News'                           => 'Notizie',
			'Read More'                      => 'Leggi di più',
			'Previous'                       => 'Precedente',
			'Next'                           => 'Successivo',
			'‹ Prev'                         => '‹ Prec',
			'Next ›'                         => 'Succ ›',
			'Sorry, no news items were found.' => 'Nessuna notizia trovata.',
			'Home'                           => 'Inizio',
			'Search'                         => 'Cerca',
			'Search for:'                    => 'Cerca:',
			'Search ...'                     => 'Cerca ...',
			'Submit'                         => 'Invia',

			// Assoc portal.
			'Associations'                   => 'Associazioni',
			'Association'                    => 'Associazione',
			'Association Archives'           => 'Archivio Associazioni',
			'Association Attributes'         => 'Attributi Associazione',
			'Parent Association:'            => 'Associazione genitore:',
			'All Associations'               => 'Tutte le associazioni',
			'Add New Association'            => 'Aggiungi nuova associazione',
			'Add New'                        => 'Aggiungi nuova',
			'New Association'                => 'Nuova associazione',
			'Edit Association'               => 'Modifica associazione',
			'Update Association'             => 'Aggiorna associazione',
			'View Association'               => 'Visualizza associazione',
			'View Associations'              => 'Visualizza associazioni',
			'Search Association'             => 'Cerca associazione',
			'Not found'                      => 'Non trovato',
			'Not found in Trash'             => 'Non trovato nel cestino',
			'Set logo'                       => 'Imposta logo',
			'Remove logo'                    => 'Rimuovi logo',
			'Use as logo'                    => 'Usa come logo',
			'Insert into association'        => 'Inserisci nell\'associazione',
			'Uploaded to this association'   => 'Caricato in questa associazione',
			'Associations list'              => 'Elenco associazioni',
			'Associations list navigation'   => 'Navigazione elenco associazioni',
			'Filter associations list'       => 'Filtra elenco associazioni',
			'Association profiles'           => 'Profili associazioni',
			'Events'                         => 'Eventi',
			'Event'                          => 'Evento',
			'Event Archives'                 => 'Archivio eventi',
			'Event Attributes'               => 'Attributi evento',
			'Parent Event:'                  => 'Evento genitore:',
			'All Events'                     => 'Tutti gli eventi',
			'Add New Event'                  => 'Aggiungi nuovo evento',
			'New Event'                      => 'Nuovo evento',
			'Edit Event'                     => 'Modifica evento',
			'Update Event'                   => 'Aggiorna evento',
			'View Event'                     => 'Visualizza evento',
			'View Events'                    => 'Visualizza eventi',
			'Search Event'                   => 'Cerca evento',
			'Events submitted by associations' => 'Eventi inviati dalle associazioni',
			'News Item'                      => 'Notizia',
			'News Archives'                  => 'Archivio notizie',
			'News Item Attributes'           => 'Attributi notizia',
			'Parent News Item:'              => 'Notizia genitore:',
			'All News'                       => 'Tutte le notizie',
			'Add New News Item'              => 'Aggiungi nuova notizia',
			'New News Item'                  => 'Nuova notizia',
			'Edit News Item'                 => 'Modifica notizia',
			'Update News Item'               => 'Aggiorna notizia',
			'View News Item'                 => 'Visualizza notizia',
			'View News Items'                => 'Visualizza notizie',
			'Search News'                    => 'Cerca notizie',
			'News articles'                  => 'Articoli notizie',
			'Activity Categories'            => 'Categorie attività',
			'Activity Category'              => 'Categoria attività',
			'Search Categories'              => 'Cerca categorie',
			'All Categories'                 => 'Tutte le categorie',
			'Parent Category'                => 'Categoria genitore',
			'Parent Category:'               => 'Categoria genitore:',
			'Edit Category'                  => 'Modifica categoria',
			'Update Category'                => 'Aggiorna categoria',
			'Add New Category'               => 'Aggiungi nuova categoria',
			'New Category Name'              => 'Nome nuova categoria',
			'Event Types'                    => 'Tipi di evento',
			'Event Type'                     => 'Tipo di evento',
			'Search Event Types'             => 'Cerca tipi evento',
			'All Event Types'                => 'Tutti i tipi evento',
			'Edit Event Type'                => 'Modifica tipo evento',
			'Update Event Type'              => 'Aggiorna tipo evento',
			'Add New Event Type'             => 'Aggiungi nuovo tipo evento',
			'New Event Type Name'            => 'Nome nuovo tipo evento',
			'Association Manager'            => 'Gestore Associazione',
			'Association Link'               => 'Collegamento associazione',
			'Managed Association'            => 'Associazione gestita',
			'-- Select Association --'       => '-- Seleziona associazione --',
			'Link this user to the association they manage.' => 'Collega questo utente all\'associazione che gestisce.',
			'You must be logged in as an Association Manager to view this page.' => 'Devi essere autenticato come Gestore Associazione per visualizzare questa pagina.',
			'Association Portal'             => 'Portale associazioni',
			'Welcome to your portal. From here you can manage your association profile and your events.' => 'Benvenuto nel portale. Da qui puoi gestire profilo associazione ed eventi.',
			'Edit My Association Profile'    => 'Dati Associazione',
			'Manage My Events'               => 'Gestisci i miei eventi',
			'Your user account is not linked to an association. Please contact an administrator.' => 'Il tuo account non è collegato a un\'associazione. Contatta un amministratore.',
			'The linked item is not a valid association. Please contact an administrator.' => 'L\'elemento collegato non è un\'associazione valida. Contatta un amministratore.',
			'Profile updated successfully!'  => 'Profilo aggiornato correttamente!',
			'Editing Profile for: %s'        => 'Modifica profilo per: %s',
			'Contact Information'            => 'Informazioni di contatto',
			'Contact Email'                  => 'Email contatto',
			'Phone'                          => 'Telefono',
			'Website'                        => 'Sito web',
			'Location'                       => 'Sede',
			'Address'                        => 'Indirizzo',
			'City'                           => 'Città',
			'Province (e.g., "MI")'          => 'Provincia (es. "MI")',
			'Social Media'                   => 'Social',
			'Facebook URL'                   => 'URL Facebook',
			'Instagram URL'                  => 'URL Instagram',
			'YouTube URL'                    => 'URL YouTube',
			'TikTok URL'                     => 'URL TikTok',
			'X (Twitter) URL'                => 'URL X (Twitter)',
			'Classification'                 => 'Classificazione',
			'Select the categories that best describe your association\'s activities.' => 'Seleziona le categorie che descrivono meglio le attività della tua associazione.',
			'Association Logo'               => 'Logo associazione',
			'Current Logo:'                  => 'Logo attuale:',
			'Upload New Logo (replaces existing)' => 'Carica nuovo logo (sostituisce quello esistente)',
			'Max file size 5MB. Images only.' => 'Dimensione massima 5MB. Solo immagini.',
			'Save Profile'                   => 'Salva profilo',
			'Event submitted successfully for review!' => 'Evento inviato correttamente per revisione!',
			'My Events'                      => 'I miei eventi',
			'Title'                          => 'Titolo',
			'Start Date'                     => 'Data inizio',
			'Status'                         => 'Stato',
			'Actions'                        => 'Azioni',
			'You have not created any events yet.' => 'Non hai ancora creato eventi.',
			'Edit'                           => 'Modifica',
			'You do not have permission to edit this event, or the event does not exist.' => 'Non hai i permessi per modificare questo evento, oppure non esiste.',
			'Create New Event'               => 'Crea nuovo evento',
			'All events are submitted for review and must be approved by an administrator before appearing on the calendar.' => 'Tutti gli eventi vengono inviati in revisione e devono essere approvati da un amministratore prima di apparire nel calendario.',
			'Event Title'                    => 'Titolo evento',
			'Start Date & Time'              => 'Data e ora inizio',
			'End Date & Time (Optional)'     => 'Data e ora fine (facoltativa)',
			'Event Description'              => 'Descrizione evento',
			'Venue & Location'               => 'Luogo e località',
			'Venue Name'                     => 'Nome sede',
			'Details'                        => 'Dettagli',
			'Registration URL'               => 'URL iscrizione',
			'Event Image'                    => 'Immagine evento',
			'Current Image:'                 => 'Immagine attuale:',
			'Submit for Review'              => 'Invia per revisione',
			'Prev'                           => 'Prec',
		);

		// Spanish translations
		$es_map = array(
			'News'                           => 'Noticias',
			'Read More'                      => 'Leer más',
			'Previous'                       => 'Anterior',
			'Next'                           => 'Siguiente',
			'‹ Prev'                         => '‹ Ant',
			'Next ›'                         => 'Sig ›',
			'Sorry, no news items were found.' => 'No se encontraron noticias.',
			'Home'                           => 'Inicio',
			'Search'                         => 'Buscar',
			'Search for:'                    => 'Buscar:',
			'Search ...'                     => 'Buscar ...',
			'Submit'                         => 'Enviar',
			'Associations'                   => 'Asociaciones',
			'Association'                    => 'Asociación',
			'Events'                         => 'Eventos',
			'Event'                          => 'Evento',
			'News Item'                      => 'Noticia',
			'All News'                       => 'Todas las noticias',
			'All Events'                     => 'Todos los eventos',
			'All Associations'               => 'Todas las asociaciones',
			'Add New'                        => 'Añadir nuevo',
			'New Event'                      => 'Nuevo evento',
			'New News Item'                  => 'Nueva noticia',
			'Edit Event'                     => 'Editar evento',
			'Edit News Item'                 => 'Editar noticia',
			'Create New Event'               => 'Crear nuevo evento',
			'Event Title'                    => 'Título del evento',
			'Start Date & Time'              => 'Fecha y hora de inicio',
			'End Date & Time (Optional)'     => 'Fecha y hora de fin (opcional)',
			'Event Description'              => 'Descripción del evento',
			'Vevenue & Location'             => 'Lugar y ubicación',
			'Veune Name'                     => 'Nombre del lugar',
			'Registration URL'               => 'URL de inscripción',
			'Save Profile'                   => 'Guardar perfil',
			'Title'                          => 'Título',
			'Start Date'                     => 'Fecha de inicio',
			'Status'                         => 'Estado',
			'Actions'                        => 'Acciones',
			'Edit'                           => 'Editar',
			'Submit for Review'              => 'Enviar para revisión',
			'Contact Information'            => 'Información de contacto',
			'Contact Email'                  => 'Email de contacto',
			'Phone'                          => 'Teléfono',
			'Website'                        => 'Sitio web',
			'Location'                       => 'Ubicación',
			'Address'                        => 'Dirección',
			'City'                           => 'Ciudad',
			'Province (e.g., "MI")'          => 'Provincia (ej. "M")',
			'Profile updated successfully!'  => '¡Perfil actualizado correctamente!',
			'You must be logged in as an Association Manager to view this page.' => 'Debes iniciar sesión como Gestor de Asociación para ver esta página.',
			'Your user account is not linked to an association. Please contact an administrator.' => 'Tu cuenta no está vinculada a una asociación. Contacta a un administrador.',
			'Welcome to your portal. From here you can manage your association profile and your events.' => 'Bienvenido a tu portal. Desde aquí puedes gestionar el perfil de tu asociación y tus eventos.',
			'Edit My Association Profile'    => 'Datos de la Asociación',
			'Manage My Events'               => 'Gestionar mis eventos',
			'My Events'                      => 'Mis eventos',
		);

		// English translations
		$en_map = array(
			'News'                           => 'News',
			'Read More'                      => 'Read more',
			'Previous'                       => 'Previous',
			'Next'                           => 'Next',
			'‹ Prev'                         => '‹ Prev',
			'Next ›'                         => 'Next ›',
			'Sorry, no news items were found.' => 'Sorry, no news items were found.',
			'Home'                           => 'Home',
			'Search'                         => 'Search',
			'Search for:'                    => 'Search for:',
			'Search ...'                     => 'Search ...',
			'Submit'                         => 'Submit',
			'Associations'                   => 'Associations',
			'Association'                    => 'Association',
			'Events'                         => 'Events',
			'Event'                          => 'Event',
			'News Item'                      => 'News Item',
			'All News'                       => 'All News',
			'All Events'                     => 'All Events',
			'All Associations'               => 'All Associations',
			'Add New'                        => 'Add New',
			'New Event'                      => 'New Event',
			'New News Item'                  => 'New News Item',
			'Edit Event'                     => 'Edit Event',
			'Edit News Item'                 => 'Edit News Item',
			'Create New Event'               => 'Create New Event',
			'Event Title'                    => 'Event Title',
			'Start Date & Time'              => 'Start Date & Time',
			'End Date & Time (Optional)'     => 'End Date & Time (Optional)',
			'Event Description'              => 'Event Description',
			'Veune & Location'               => 'Venue & Location',
			'Veune Name'                     => 'Venue Name',
			'Registration URL'               => 'Registration URL',
			'Save Profile'                   => 'Save Profile',
			'Title'                          => 'Title',
			'Start Date'                     => 'Start Date',
			'Status'                         => 'Status',
			'Actions'                        => 'Actions',
			'Edit'                           => 'Edit',
			'Submit for Review'              => 'Submit for Review',
			'Contact Information'            => 'Contact Information',
			'Contact Email'                  => 'Contact Email',
			'Phone'                          => 'Phone',
			'Website'                        => 'Website',
			'Location'                       => 'Location',
			'Address'                        => 'Address',
			'City'                           => 'City',
			'Province (e.g., "MI")'          => 'Province (e.g., "MI")',
			'Profile updated successfully!'  => 'Profile updated successfully!',
			'You must be logged in as an Association Manager to view this page.' => 'You must be logged in as an Association Manager to view this page.',
			'Your user account is not linked to an association. Please contact an administrator.' => 'Your user account is not linked to an association. Please contact an administrator.',
			'Welcome to your portal. From here you can manage your association profile and your events.' => 'Welcome to your portal. From here you can manage your association profile and your events.',
			'Edit My Association Profile'    => 'Association Data',
			'Manage My Events'               => 'Manage My Events',
			'My Events'                      => 'My Events',
		);

		// French translations
		$fr_map = array(
			'News'                           => 'Actualités',
			'Read More'                      => 'Lire la suite',
			'Previous'                       => 'Précédent',
			'Next'                           => 'Suivant',
			'‹ Prev'                         => '‹ Préc',
			'Next ›'                         => 'Suiv ›',
			'Sorry, no news items were found.' => 'Aucune actualité trouvée.',
			'Home'                           => 'Accueil',
			'Search'                         => 'Rechercher',
			'Search for:'                    => 'Rechercher :',
			'Search ...'                     => 'Rechercher ...',
			'Submit'                         => 'Soumettre',
			'Associations'                   => 'Associations',
			'Association'                    => 'Association',
			'Events'                         => 'Événements',
			'Event'                          => 'Événement',
			'News Item'                      => 'Nouvelle',
			'All News'                       => 'Toutes les actualités',
			'All Events'                     => 'Tous les événements',
			'All Associations'               => 'Toutes les associations',
			'Add New'                        => 'Ajouter nouveau',
			'New Event'                      => 'Nouvel événement',
			'New News Item'                  => 'Nouvelle actualité',
			'Edit Event'                     => 'Modifier événement',
			'Edit News Item'                 => 'Modifier actualité',
			'Create New Event'               => 'Créer nouvel événement',
			'Event Title'                    => 'Titre de l\'événement',
			'Start Date & Time'              => 'Date et heure de début',
			'End Date & Time (Optional)'     => 'Date et heure de fin (facultatif)',
			'Event Description'              => 'Description de l\'événement',
			'Veune & Location'               => 'Lieu et adresse',
			'Veune Name'                     => 'Nom du lieu',
			'Registration URL'               => 'URL d\'inscription',
			'Save Profile'                   => 'Enregistrer le profil',
			'Title'                          => 'Titre',
			'Start Date'                     => 'Date de début',
			'Status'                         => 'Statut',
			'Actions'                        => 'Actions',
			'Edit'                           => 'Modifier',
			'Submit for Review'              => 'Soumettre pour révision',
			'Contact Information'            => 'Informations de contact',
			'Contact Email'                  => 'Email de contact',
			'Phone'                          => 'Téléphone',
			'Website'                        => 'Site web',
			'Location'                       => 'Emplacement',
			'Address'                        => 'Adresse',
			'City'                           => 'Ville',
			'Province (e.g., "MI")'          => 'Province (ex. "M")',
			'Profile updated successfully!'  => 'Profil mis à jour avec succès !',
			'You must be logged in as an Association Manager to view this page.' => 'Vous devez être connecté en tant que Gestionnaire d\'Association pour voir cette page.',
			'Your user account is not linked to an association. Please contact an administrator.' => 'Votre compte n\'est pas lié à une association. Veuillez contacter un administrateur.',
			'Welcome to your portal. From here you can manage your association profile and your events.' => 'Bienvenue sur votre portail. Vous pouvez gérer ici le profil de votre association et vos événements.',
			'Edit My Association Profile'    => 'Données de l\'Association',
			'Manage My Events'               => 'Gérer mes événements',
			'My Events'                      => 'Mes événements',
		);
	}

	// Select the correct map based on language
	switch ( $current_lang ) {
		case 'es':
			$map = $es_map;
			break;
		case 'en':
			$map = $en_map;
			break;
		case 'fr':
			$map = $fr_map;
			break;
		case 'it':
		default:
			$map = $it_map;
			break;
	}

	if ( isset( $map[ $text ] ) ) {
		return $map[ $text ];
	}

	// Many project strings are authored directly in Italian. When a non-IT
	// language is selected, remap those Italian source strings back to the
	// canonical key and then to the target language.
	if ( 'it' !== $current_lang ) {
		static $it_to_key = null;
		if ( null === $it_to_key ) {
			$it_to_key = array();
			foreach ( $it_map as $canonical_key => $italian_value ) {
				$it_to_key[ $italian_value ] = $canonical_key;
			}
		}

		if ( isset( $it_to_key[ $text ] ) ) {
			$canonical_key = $it_to_key[ $text ];
			if ( isset( $map[ $canonical_key ] ) ) {
				return $map[ $canonical_key ];
			}
		}
	}

	return $translated;
}
add_filter( 'gettext', 'culturacsi_it_gettext', 20, 3 );

function culturacsi_it_reserved_login_modal() {
	if ( is_user_logged_in() ) {
		return;
	}
	?>
	<div id="culturacsi-login-modal" class="culturacsi-login-modal" aria-hidden="true">
		<div class="culturacsi-login-overlay"></div>
		<div class="culturacsi-login-panel" role="dialog" aria-modal="true" aria-labelledby="culturacsi-login-title">
			<button type="button" class="culturacsi-login-close" aria-label="Chiudi">&times;</button>
			<h2 id="culturacsi-login-title">Area Riservata</h2>
			<div class="culturacsi-login-body">
				<?php
				if ( shortcode_exists( 'assoc_reserved_access' ) ) {
					$GLOBALS['assoc_portal_force_modal_auth'] = true;
					echo do_shortcode( '[assoc_reserved_access]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					unset( $GLOBALS['assoc_portal_force_modal_auth'] );
				}
				?>
			</div>
		</div>
	</div>
	<style>
		.culturacsi-login-modal{display:none;position:fixed;inset:0;z-index:2147483002 !important;padding:14px;overflow-y:auto}
		.culturacsi-login-modal.is-open{display:block}
		.culturacsi-login-overlay{position:fixed;inset:0;background:rgba(10,23,46,.58);backdrop-filter:blur(5px)}
		.culturacsi-login-panel{position:relative;width:min(500px,100%);margin:clamp(8px,5vh,42px) auto;background:linear-gradient(180deg,#fff 0%,#f6faff 100%);border:1px solid #d8e3f0;border-radius:18px;box-shadow:0 24px 65px -28px rgba(2,8,23,.68);overflow:hidden}
		.culturacsi-login-panel::before{content:"";position:absolute;inset:0 0 auto 0;height:4px;background:linear-gradient(90deg,#0b3d91 0%,#2f6fc4 100%)}
		.culturacsi-login-close{position:absolute;top:10px;right:10px;display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1px solid #d4deeb;background:#fff;border-radius:999px;font-size:20px;line-height:1;cursor:pointer;color:#334155;transition:all .15s ease}
		.culturacsi-login-close:hover,.culturacsi-login-close:focus{background:#f1f6fd;border-color:#b7cae4;color:#0f172a}
		.culturacsi-login-panel h2{margin:0;padding:16px 18px 10px;font-size:1.5rem;line-height:1.2;letter-spacing:.01em;color:#0f172a}
		.culturacsi-login-body{padding:0 18px 16px}
		.culturacsi-login-body .assoc-auth-wrap{margin:0;max-width:100%}
		.culturacsi-login-body .assoc-auth-tabs{display:flex;flex-wrap:nowrap;gap:0;margin:0 0 10px;border-bottom:1px solid #cfd9e7}
		.culturacsi-login-body .assoc-auth-tab{display:inline-flex;align-items:center;justify-content:center;min-height:35px;padding:0 14px;border:1px solid transparent;border-bottom:0;border-radius:10px 10px 0 0;background:transparent;color:#334155;font-size:.91rem;font-weight:600;line-height:1;cursor:pointer;transition:all .15s ease;margin:0 4px -1px 0}
		.culturacsi-login-body .assoc-auth-tab:hover,.culturacsi-login-body .assoc-auth-tab:focus{background:#edf3fb;color:#0b3d91}
		.culturacsi-login-body .assoc-auth-tab.is-active{background:#fff;border-color:#cfd9e7;color:#0b3d91;box-shadow:none}
		.culturacsi-login-body .assoc-auth-pane{display:none}
		.culturacsi-login-body .assoc-auth-pane.is-active{display:block}
		.culturacsi-login-body .assoc-auth-form{padding:10px 0 0;border:0;background:transparent}
		.culturacsi-login-body .assoc-auth-form h3{margin:0 0 6px;font-size:1.17rem;color:#0f172a}
		.culturacsi-login-body .assoc-auth-form .description{margin:0 0 8px;font-size:.89rem;line-height:1.35;color:#475569}
		.culturacsi-login-body .assoc-auth-form p{margin:0 0 8px}
		.culturacsi-login-body .assoc-auth-form label{display:block;margin-bottom:4px;font-size:.89rem;font-weight:600;color:#1f2937}
		.culturacsi-login-body .assoc-portal-form input[type=text],
		.culturacsi-login-body .assoc-portal-form input[type=email],
		.culturacsi-login-body .assoc-portal-form input[type=password]{max-width:none;min-height:38px;padding:8px 10px;border-radius:9px;border:1px solid #c9d6e6}
		.culturacsi-login-body .assoc-portal-form input[type=text]:focus,
		.culturacsi-login-body .assoc-portal-form input[type=email]:focus,
		.culturacsi-login-body .assoc-portal-form input[type=password]:focus{border-color:#2f6fc4;box-shadow:0 0 0 3px rgba(47,111,196,.14);outline:none}
		.culturacsi-login-body .assoc-auth-form .button{min-height:38px;padding:8px 12px;border-radius:9px}
		.culturacsi-login-body .assoc-auth-pane .button.button-primary{width:100%;margin-top:2px}
		.culturacsi-login-body .assoc-admin-notice{margin:0 0 10px;border-radius:10px;padding:11px 12px;text-align:center;font-weight:700;font-size:.96rem;border:2px solid #d1deef;background:#f7faff;color:#0f172a}
		.culturacsi-login-body .assoc-admin-notice-success{border-color:#8bcaa5;background:#e8f8ee;color:#14532d}
		.culturacsi-login-body .assoc-admin-notice-warning{border-color:#f5be62;background:#fff7e8;color:#7c4303}
		.culturacsi-login-body .assoc-admin-notice-error{border-color:#dc2626;background:#fee2e2;color:#991b1b;box-shadow:0 10px 18px -16px rgba(153,27,27,.8)}
		.culturacsi-login-body .assoc-admin-notice a{color:inherit;font-weight:800;text-decoration:underline}
		@media (max-width: 680px){
			.culturacsi-login-modal{padding:8px}
			.culturacsi-login-panel{width:100%;margin:6px auto;border-radius:14px}
			.culturacsi-login-panel h2{padding:14px 14px 8px;font-size:1.32rem}
			.culturacsi-login-body{padding:0 14px 12px}
			.culturacsi-login-body .assoc-auth-tabs{margin-bottom:8px}
			.culturacsi-login-body .assoc-auth-tab{min-height:31px;padding:0 10px;font-size:.85rem;margin-right:2px}
			.culturacsi-login-body .assoc-auth-form p{margin-bottom:7px}
		}
		body.culturacsi-login-open{overflow:hidden}
	</style>
	<script>
		(function(){
			var modal=document.getElementById('culturacsi-login-modal');
			if(!modal){return;}
			var closeBtn=modal.querySelector('.culturacsi-login-close');
			var overlay=modal.querySelector('.culturacsi-login-overlay');
			function setActiveAuthTab(tabName){
				var wrap = modal.querySelector('[data-assoc-auth-wrap]');
				if(!wrap){return;}
				var tabs = wrap.querySelectorAll('[data-auth-tab]');
				var panes = wrap.querySelectorAll('[data-auth-pane]');
				tabs.forEach(function(tab){
					var isActive = (tab.getAttribute('data-auth-tab')||'') === tabName;
					tab.classList.toggle('is-active', isActive);
					tab.setAttribute('aria-pressed', isActive ? 'true' : 'false');
				});
				panes.forEach(function(pane){
					var paneActive = (pane.getAttribute('data-auth-pane')||'') === tabName;
					pane.classList.toggle('is-active', paneActive);
				});
			}
			function openModal(e, forceTab){
				if(e){e.preventDefault();}
				if(forceTab !== 'recover' && forceTab !== 'register'){
					forceTab = 'login';
				}
				if(forceTab){ setActiveAuthTab(forceTab); }
				modal.classList.add('is-open');
				modal.setAttribute('aria-hidden','false');
				document.body.classList.add('culturacsi-login-open');
				var input = forceTab === 'register'
					? document.getElementById('assoc_reg_first_name')
					: (forceTab === 'recover' ? document.getElementById('assoc_recover_identifier') : document.getElementById('assoc_login_identifier'));
				if(!input){ input = document.getElementById('assoc_login_identifier'); }
				if(input){input.focus();}
			}
			function closeModal(){
				modal.classList.remove('is-open');
				modal.setAttribute('aria-hidden','true');
				document.body.classList.remove('culturacsi-login-open');
			}
			document.addEventListener('click', function(e){
				var trigger=e.target.closest('a[href]');
				if(!trigger){return;}
				if(trigger.closest('#culturacsi-login-modal')){return;}
				var href=trigger.getAttribute('href')||'';
				if(href.indexOf('/area-riservata/')===-1 && href.indexOf('area_riservata_login=1')===-1){return;}
				e.preventDefault();
				var forceTab='login';
				if(href.indexOf('auth=recover')!==-1){ forceTab='recover'; }
				if(href.indexOf('auth=register')!==-1){ forceTab='register'; }
				openModal(null, forceTab);
			});
			if(closeBtn){ closeBtn.addEventListener('click', closeModal); }
			if(overlay){ overlay.addEventListener('click', closeModal); }
			document.addEventListener('keydown', function(e){ if(e.key==='Escape'){ closeModal(); } });

			try{
				var url=new URL(window.location.href);
				if(url.searchParams.get('area_riservata_login')==='1'){
					var tabFromUrl = url.searchParams.get('auth');
					if(tabFromUrl!=='recover' && tabFromUrl!=='register'){ tabFromUrl='login'; }
					openModal(null, tabFromUrl);
					url.searchParams.delete('area_riservata_login');
					url.searchParams.delete('auth');
					url.searchParams.delete('assoc_notice');
					if(window.history&&window.history.replaceState){
						window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : '') + url.hash);
					}
				}
			}catch(err){}
		})();
	</script>
	<?php
}
add_action( 'wp_footer', 'culturacsi_it_reserved_login_modal', 100 );

/**
 * Language selector shortcode
 * Usage: [culturacsi_language_selector] or [culturacsi_language_selector style="dropdown"]
 */
function culturacsi_language_selector_shortcode( $atts ) {

	
	$atts = shortcode_atts( array(
		'style' => 'inline', // inline, dropdown
	), $atts, 'culturacsi_language_selector' );

	$current_lang = culturacsi_get_current_language();

	
	$languages = array(
		'it' => array( 'name' => 'Italiano', 'flag' => '🇮🇹' ),
		'es' => array( 'name' => 'Español', 'flag' => '🇪🇸' ),
		'en' => array( 'name' => 'English', 'flag' => '🇬🇧' ),
		'fr' => array( 'name' => 'Français', 'flag' => '🇫🇷' ),
	);
	$selector_label = array(
		'it' => 'Lingua:',
		'es' => 'Idioma:',
		'en' => 'Language:',
		'fr' => 'Langue :',
	);

	// Sanitize server variables before constructing URL
	$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$host = preg_replace( '/[^a-zA-Z0-9.:-]/', '', $host ); // Additional sanitization for host
	$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $host . $request_uri;

	
	// Remove any existing lang parameter
	$current_url = remove_query_arg( 'lang', $current_url );

	
	$html = '';
	
	if ( 'dropdown' === $atts['style'] ) {
		$html .= '<div class="culturacsi-language-selector dropdown">';
		$html .= '<button type="button" class="culturacsi-lang-btn" aria-expanded="false">';
		$html .= '<span class="culturacsi-lang-flag">' . esc_html( $languages[ $current_lang ]['flag'] ) . '</span>';
		$html .= '<span class="culturacsi-lang-name">' . esc_html( $languages[ $current_lang ]['name'] ) . '</span>';
		$html .= '</button>';
		$html .= '<ul class="culturacsi-lang-options">';
		foreach ( $languages as $code => $lang ) {
			$url = add_query_arg( 'lang', $code, $current_url );
			$active = ( $code === $current_lang ) ? ' class="active"' : '';
			$html .= '<li' . $active . '><a href="' . esc_url( $url ) . '"><span>' . esc_html( $lang['flag'] ) . '</span> ' . esc_html( $lang['name'] ) . '</a></li>';
		}
		$html .= '</ul>';
		$html .= '</div>';
		$html .= '<style>';
		$html .= '.culturacsi-language-selector{position:relative;display:inline-block}';
		$html .= '.culturacsi-lang-btn{display:inline-flex;align-items:center;gap:8px;padding:5px 15px;background:#fff;border:1px solid #ddd;border-radius:4px;cursor:pointer;font-size:0.9rem;line-height:1.2;color:#0b3d91;box-sizing:border-box}';
		$html .= '.culturacsi-lang-btn:hover{background:#f5f5f5;color:#0b3d91}';
		$html .= '.culturacsi-lang-options{display:none;position:absolute;top:100%;left:0;margin:4px 0 0;padding:0;list-style:none;background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.15);z-index:9999;min-width:150px}';
		$html .= '.culturacsi-language-selector.open .culturacsi-lang-options{display:block}';
		$html .= '.culturacsi-lang-options li a{display:flex;align-items:center;gap:8px;padding:10px 14px;text-decoration:none;color:#333;font-size:14px}';
		$html .= '.culturacsi-lang-options li a:hover{background:#f5f5f5}';
		$html .= '.culturacsi-lang-options li.active a{background:#e8f4ff;color:#0b3d91;font-weight:600}';
		$html .= '</style>';
		$html .= '<script>';
		$html .= 'document.addEventListener("DOMContentLoaded",function(){var c=document.querySelector(".culturacsi-language-selector");if(c){var b=c.querySelector(".culturacsi-lang-btn");b.addEventListener("click",function(e){e.stopPropagation();c.classList.toggle("open")});document.addEventListener("click",function(){c.classList.remove("open")})}});';
		$html .= '</script>';
	} else {
		$html .= '<div class="culturacsi-language-selector inline">';
		$html .= '<span class="culturacsi-lang-label">' . esc_html( $selector_label[ $current_lang ] ?? 'Language:' ) . ' </span>';
		foreach ( $languages as $code => $lang ) {
			$url = add_query_arg( 'lang', $code, $current_url );
			$active = ( $code === $current_lang );
			if ( $active ) {
				$html .= '<span class="culturacsi-lang-item active"><span>' . esc_html( $lang['flag'] ) . '</span> ' . esc_html( $lang['name'] ) . '</span>';
			} else {
				$html .= '<a href="' . esc_url( $url ) . '" class="culturacsi-lang-item"><span>' . esc_html( $lang['flag'] ) . '</span> ' . esc_html( $lang['name'] ) . '</a>';
			}
		}
		$html .= '</div>';
		$html .= '<style>';
		$html .= '.culturacsi-language-selector.inline{display:inline-flex;align-items:center;gap:8px;flex-wrap:wrap}';
		$html .= '.culturacsi-lang-label{font-size:14px;color:#666}';
		$html .= '.culturacsi-lang-item{display:inline-flex;align-items:center;gap:4px;padding:4px 8px;text-decoration:none;color:#333;font-size:13px;border-radius:4px}';
		$html .= '.culturacsi-lang-item:hover{background:#f0f0f0}';
		$html .= '.culturacsi-lang-item.active{background:#0b3d91;color:#fff;font-weight:600}';
		$html .= '</style>';
	}
	
	return $html;
}
add_shortcode( 'culturacsi_language_selector', 'culturacsi_language_selector_shortcode' );

if ( ! function_exists( 'culturacsi_runtime_visual_label_phrases' ) ) {
	/**
	 * Load curated visual-label translations from local JSON.
	 * Render-only use: never use these labels as canonical identifiers.
	 *
	 * @return array<string,array<string,string>>
	 */
	function culturacsi_runtime_visual_label_phrases(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$cache = array();
		$file = __DIR__ . '/data/activity-visual-label-translations.json';
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			return $cache;
		}

		$raw = file_get_contents( $file );
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return $cache;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return $cache;
		}

		foreach ( $decoded as $source => $variants ) {
			$source = trim( (string) $source );
			if ( '' === $source || ! is_array( $variants ) ) {
				continue;
			}
			$cache[ $source ] = array(
				'it' => trim( (string) ( $variants['it'] ?? $source ) ),
				'en' => trim( (string) ( $variants['en'] ?? $source ) ),
				'es' => trim( (string) ( $variants['es'] ?? $source ) ),
				'fr' => trim( (string) ( $variants['fr'] ?? $source ) ),
			);
		}

		return $cache;
	}
}

function culturacsi_it_runtime_label_map( $text ) {
	$current_lang = culturacsi_get_current_language();
	$raw_text = (string) $text;
	$normalize = static function( string $value ): string {
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = preg_replace( '/[^\p{L}\p{N}\s]+/u', ' ', $value );
		$value = trim( preg_replace( '/\s+/u', ' ', $value ) );
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $value, 'UTF-8' );
		}
		return strtolower( $value );
	};

	$phrases = array(
		'News' => array(
			'it' => 'Notizie',
			'es' => 'Noticias',
			'en' => 'News',
			'fr' => 'Actualités',
		),
		'My Events' => array(
			'it' => 'I miei eventi',
			'es' => 'Mis eventos',
			'en' => 'My events',
			'fr' => 'Mes événements',
		),
		'Upcoming Events' => array(
			'it' => 'Prossimi eventi',
			'es' => 'Próximos eventos',
			'en' => 'Upcoming events',
			'fr' => 'Événements à venir',
		),
		'Dashboard' => array(
			'it' => 'Area riservata',
			'es' => 'Área reservada',
			'en' => 'Reserved area',
			'fr' => 'Espace réservé',
		),
		'Profile' => array(
			'it' => 'Profilo',
			'es' => 'Perfil',
			'en' => 'Profile',
			'fr' => 'Profil',
		),
		'ACSI Cultura' => array(
			'it' => 'ACSI Cultura',
			'es' => 'ACSI Cultura',
			'en' => 'ACSI Culture',
			'fr' => 'ACSI Culture',
		),
		'Servizi' => array(
			'it' => 'Servizi',
			'es' => 'Servicios',
			'en' => 'Services',
			'fr' => 'Services',
		),
		'Sevizi' => array(
			'it' => 'Servizi',
			'es' => 'Servicios',
			'en' => 'Services',
			'fr' => 'Services',
		),
		'SERVIZI' => array(
			'it' => 'SERVIZI',
			'es' => 'Servicios',
			'en' => 'Services',
			'fr' => 'Services',
		),
		'Convenzioni' => array(
			'it' => 'Convenzioni',
			'es' => 'Convenios',
			'en' => 'Agreements',
			'fr' => 'Conventions',
		),
		'Convenzione' => array(
			'it' => 'Convenzione',
			'es' => 'Convenio',
			'en' => 'Agreement',
			'fr' => 'Convention',
		),
		'Assicurazioni' => array(
			'it' => 'Assicurazioni',
			'es' => 'Seguros',
			'en' => 'Insurance',
			'fr' => 'Assurances',
		),
		'Le Nostre Polizze' => array(
			'it' => 'Le Nostre Polizze',
			'es' => 'Nuestras Pólizas',
			'en' => 'Our Policies',
			'fr' => 'Nos Polices',
		),
		'Polizze Integrali' => array(
			'it' => 'Polizze Integrali',
			'es' => 'Pólizas Integrales',
			'en' => 'Comprehensive Policies',
			'fr' => 'Polices Intégrales',
		),
		'Pilizze Integrali' => array(
			'it' => 'Pilizze Integrali',
			'es' => 'Pólizas Integrales',
			'en' => 'Comprehensive Policies',
			'fr' => 'Polices Intégrales',
		),
		'Nome e Dintorni' => array(
			'it' => 'Nome e Dintorni',
			'es' => 'Nombre y alrededores',
			'en' => 'Name and Surroundings',
			'fr' => 'Nom et alentours',
		),
		'Norme e Dintorni' => array(
			'it' => 'Norme e Dintorni',
			'es' => 'Normas y entorno',
			'en' => 'Rules and Surroundings',
			'fr' => 'Règles et alentours',
		),
		'Convenzione SIAE' => array(
			'it' => 'Convenzione SIAE',
			'es' => 'Convenio SIAE',
			'en' => 'SIAE Agreement',
			'fr' => 'Convention SIAE',
		),
		'Tutela Legale' => array(
			'it' => 'Tutela Legale',
			'es' => 'Protección Jurídica',
			'en' => 'Legal Protection',
			'fr' => 'Protection Juridique',
		),
		'Polizze Assicurative sport Vari' => array(
			'it' => 'Polizze Assicurative sport Vari',
			'es' => 'Pólizas de seguros para varios deportes',
			'en' => 'Insurance policies for various sports',
			'fr' => 'Polices d’assurance pour divers sports',
		),
		'Polizze Assicurative Equitazione' => array(
			'it' => 'Polizze Assicurative Equitazione',
			'es' => 'Pólizas de seguros para equitación',
			'en' => 'Equestrian insurance policies',
			'fr' => 'Polices d’assurance équestre',
		),
		'Polizze Assicurative Motori' => array(
			'it' => 'Polizze Assicurative Motori',
			'es' => 'Pólizas de seguros para motor',
			'en' => 'Motor insurance policies',
			'fr' => 'Polices d’assurance moteur',
		),
		'Polizze Assicurative Ciclismo' => array(
			'it' => 'Polizze Assicurative Ciclismo',
			'es' => 'Pólizas de seguros para ciclismo',
			'en' => 'Cycling insurance policies',
			'fr' => 'Polices d’assurance cyclisme',
		),
		'Polizza Attività Assistite con Animali' => array(
			'it' => 'Polizza Attività Assistite con Animali',
			'es' => 'Póliza para actividades asistidas con animales',
			'en' => 'Policy for animal-assisted activities',
			'fr' => 'Police pour activités assistées avec animaux',
		),
		'Polizza Attivita Assistite con Animali' => array(
			'it' => 'Polizza Attività Assistite con Animali',
			'es' => 'Póliza para actividades asistidas con animales',
			'en' => 'Policy for animal-assisted activities',
			'fr' => 'Police pour activités assistées avec animaux',
		),
		'Polizza Attivitá Assistite con Animali' => array(
			'it' => 'Polizza Attività Assistite con Animali',
			'es' => 'Póliza para actividades asistidas con animales',
			'en' => 'Policy for animal-assisted activities',
			'fr' => 'Police pour activités assistées avec animaux',
		),
		'Cittadini' => array(
			'it' => 'Cittadini',
			'es' => 'Ciudadanos',
			'en' => 'Citizens',
			'fr' => 'Citoyens',
		),
		'Citadini' => array(
			'it' => 'Cittadini',
			'es' => 'Ciudadanos',
			'en' => 'Citizens',
			'fr' => 'Citoyens',
		),
		'Settori' => array(
			'it' => 'Settori',
			'es' => 'Sectores',
			'en' => 'Sectors',
			'fr' => 'Secteurs',
		),
		'Documenti online' => array(
			'it' => 'Documenti online',
			'es' => 'Documentos en línea',
			'en' => 'Online documents',
			'fr' => 'Documents en ligne',
		),
		'Contatti' => array(
			'it' => 'Contatti',
			'es' => 'Contactos',
			'en' => 'Contacts',
			'fr' => 'Contacts',
		),
		'IL NOSTRO TEAM' => array(
			'it' => 'IL NOSTRO TEAM',
			'es' => 'NUESTRO EQUIPO',
			'en' => 'OUR TEAM',
			'fr' => 'NOTRE ÉQUIPE',
		),
		'DOVE SIAMO' => array(
			'it' => 'DOVE SIAMO',
			'es' => 'DÓNDE ESTAMOS',
			'en' => 'WHERE WE ARE',
			'fr' => 'OÙ NOUS SOMMES',
		),
		'ACSI NAZIONALE' => array(
			'it' => 'ACSI NAZIONALE',
			'es' => 'ACSI NACIONAL',
			'en' => 'ACSI NATIONAL',
			'fr' => 'ACSI NATIONAL',
		),
		'ACSI Nazionale' => array(
			'it' => 'ACSI Nazionale',
			'es' => 'ACSI Nacional',
			'en' => 'ACSI National',
			'fr' => 'ACSI National',
		),
		'Responsabile Amministrativo e Curatore Editoriale' => array(
			'it' => 'Responsabile Amministrativo e Curatore Editoriale',
			'es' => 'Responsable administrativo y curador editorial',
			'en' => 'Administrative Manager and Editorial Curator',
			'fr' => 'Responsable administratif et curateur éditorial',
		),
		'Responsabile Sportello foreigners infopoint e Supporto Amministrativo' => array(
			'it' => 'Responsabile Sportello foreigners infopoint e Supporto Amministrativo',
			'es' => 'Responsable del punto de atención infopoint extranjeros y soporte administrativo',
			'en' => 'Foreigners infopoint desk manager and administrative support',
			'fr' => 'Responsable du guichet infopoint étrangers et support administratif',
		),
		'contacts CULTURACSI' => array(
			'it' => 'contatti CULTURACSI',
			'es' => 'contactos CULTURACSI',
			'en' => 'CULTURACSI contacts',
			'fr' => 'contacts CULTURACSI',
		),
		'contacts SPORTELLO foreigners infopoint' => array(
			'it' => 'contatti SPORTELLO foreigners infopoint',
			'es' => 'contactos VENTANILLA infopoint extranjeros',
			'en' => 'Foreigners infopoint desk contacts',
			'fr' => 'contacts GUICHET infopoint étrangers',
		),
		'AREA RISERVATA' => array(
			'it' => 'AREA RISERVATA',
			'es' => 'ÁREA RESERVADA',
			'en' => 'RESERVED AREA',
			'fr' => 'ESPACE RÉSERVÉ',
		),
		'Associazione di Cultura Sport e Tempo Libero' => array(
			'it' => 'Associazione di Cultura Sport e Tempo Libero',
			'es' => 'Asociación de Cultura, Deporte y Tiempo Libre',
			'en' => 'Association of Culture, Sport and Leisure',
			'fr' => 'Association de Culture, Sport et Temps Libre',
		),
		'Area Riservata' => array(
			'it' => 'Area Riservata',
			'es' => 'Área Reservada',
			'en' => 'Reserved Area',
			'fr' => 'Espace Réservé',
		),
		'Cerca' => array(
			'it' => 'Cerca',
			'es' => 'Buscar',
			'en' => 'Search',
			'fr' => 'Rechercher',
		),
		'Calendario Eventi' => array(
			'it' => 'Calendario Eventi',
			'es' => 'Calendario de Eventos',
			'en' => 'Events Calendar',
			'fr' => 'Calendrier des Événements',
		),
		'Eventi' => array(
			'it' => 'Eventi',
			'es' => 'Eventos',
			'en' => 'Events',
			'fr' => 'Événements',
		),
		'Chiudi' => array(
			'it' => 'Chiudi',
			'es' => 'Cerrar',
			'en' => 'Close',
			'fr' => 'Fermer',
		),
		'Dettagli associazione' => array(
			'it' => 'Dettagli associazione',
			'es' => 'Detalles de la asociación',
			'en' => 'Association details',
			'fr' => 'Détails de l’association',
		),
		'Mappa' => array(
			'it' => 'Mappa',
			'es' => 'Mapa',
			'en' => 'Map',
			'fr' => 'Carte',
		),
		'Mappa associazione' => array(
			'it' => 'Mappa associazione',
			'es' => 'Mapa de la asociación',
			'en' => 'Association map',
			'fr' => 'Carte de l’association',
		),
		'Mappa non disponibile per questa associazione.' => array(
			'it' => 'Mappa non disponibile per questa associazione.',
			'es' => 'Mapa no disponible para esta asociación.',
			'en' => 'Map not available for this association.',
			'fr' => 'Carte non disponible pour cette association.',
		),
		'Attività' => array(
			'it' => 'Attività',
			'es' => 'Actividades',
			'en' => 'Activities',
			'fr' => 'Activités',
		),
		'Attivita' => array(
			'it' => 'Attività',
			'es' => 'Actividades',
			'en' => 'Activities',
			'fr' => 'Activités',
		),
		'Macro > Settore > Settore 2' => array(
			'it' => 'Macro > Settore > Settore 2',
			'es' => 'Macro > Sector > Sector 2',
			'en' => 'Macro > Sector > Sector 2',
			'fr' => 'Macro > Secteur > Secteur 2',
		),
		'Localita (sorgente)' => array(
			'it' => 'Localita (sorgente)',
			'es' => 'Localidad (origen)',
			'en' => 'Location (source)',
			'fr' => 'Localité (source)',
		),
		'Inizio' => array(
			'it' => 'Inizio',
			'es' => 'Inicio',
			'en' => 'Start',
			'fr' => 'Début',
		),
		'Fine' => array(
			'it' => 'Fine',
			'es' => 'Fin',
			'en' => 'End',
			'fr' => 'Fin',
		),
		'Sede' => array(
			'it' => 'Sede',
			'es' => 'Lugar',
			'en' => 'Venue',
			'fr' => 'Lieu',
		),
		'Iscriviti' => array(
			'it' => 'Iscriviti',
			'es' => 'Inscríbete',
			'en' => 'Register',
			'fr' => 'Inscrivez-vous',
		),
		'Riduci' => array(
			'it' => 'Riduci',
			'es' => 'Reducir',
			'en' => 'Zoom out',
			'fr' => 'Réduire',
		),
		'Reimposta zoom' => array(
			'it' => 'Reimposta zoom',
			'es' => 'Restablecer zoom',
			'en' => 'Reset zoom',
			'fr' => 'Réinitialiser le zoom',
		),
		'Ingrandisci' => array(
			'it' => 'Ingrandisci',
			'es' => 'Ampliar',
			'en' => 'Zoom in',
			'fr' => 'Agrandir',
		),
		'Evento' => array(
			'it' => 'Evento',
			'es' => 'Evento',
			'en' => 'Event',
			'fr' => 'Événement',
		),
		'Arte' => array(
			'it' => 'Arte',
			'es' => 'Arte',
			'en' => 'Art',
			'fr' => 'Art',
		),
		'Ambiente' => array(
			'it' => 'Ambiente',
			'es' => 'Medioambiente',
			'en' => 'Environment',
			'fr' => 'Environnement',
		),
		'Valorizzazione del Territorio' => array(
			'it' => 'Valorizzazione del Territorio',
			'es' => 'Valorización del Territorio',
			'en' => 'Territory Enhancement',
			'fr' => 'Valorisation du Territoire',
		),
		'Culture di nicchia' => array(
			'it' => 'Culture di nicchia',
			'es' => 'Culturas de nicho',
			'en' => 'Niche Cultures',
			'fr' => 'Cultures de niche',
		),
		'Canto' => array(
			'it' => 'Canto',
			'es' => 'Canto',
			'en' => 'Singing',
			'fr' => 'Chant',
		),
		'Musica' => array(
			'it' => 'Musica',
			'es' => 'Música',
			'en' => 'Music',
			'fr' => 'Musique',
		),
		'Danza' => array(
			'it' => 'Danza',
			'es' => 'Danza',
			'en' => 'Dance',
			'fr' => 'Danse',
		),
		'Scultura' => array(
			'it' => 'Scultura',
			'es' => 'Escultura',
			'en' => 'Sculpture',
			'fr' => 'Sculpture',
		),
		'Fotografia e Pittura' => array(
			'it' => 'Fotografia e Pittura',
			'es' => 'Fotografía y Pintura',
			'en' => 'Photography and Painting',
			'fr' => 'Photographie et Peinture',
		),
		'Editoria' => array(
			'it' => 'Editoria',
			'es' => 'Editorial',
			'en' => 'Publishing',
			'fr' => 'Édition',
		),
		'Dama e Scacchi' => array(
			'it' => 'Dama e Scacchi',
			'es' => 'Damas y Ajedrez',
			'en' => 'Checkers and Chess',
			'fr' => 'Dames et Échecs',
		),
		'Danza Aerea' => array(
			'it' => 'Danza Aerea',
			'es' => 'Danza Aérea',
			'en' => 'Aerial Dance',
			'fr' => 'Danse Aérienne',
		),
		'Arti Performative / Danza e Movimento' => array(
			'it' => 'Arti Performative / Danza e Movimento',
			'es' => 'Artes Escénicas / Danza y Movimiento',
			'en' => 'Performing Arts / Dance and Movement',
			'fr' => 'Arts Scéniques / Danse et Mouvement',
		),
		'ARTI PERFORMATIVE / DANZA E MOVIMENTO' => array(
			'it' => 'ARTI PERFORMATIVE / DANZA E MOVIMENTO',
			'es' => 'ARTES ESCÉNICAS / DANZA Y MOVIMIENTO',
			'en' => 'PERFORMING ARTS / DANCE AND MOVEMENT',
			'fr' => 'ARTS SCÉNIQUES / DANSE ET MOUVEMENT',
		),
		'Attività correlate' => array(
			'it' => 'Attività correlate',
			'es' => 'Actividades relacionadas',
			'en' => 'Related activities',
			'fr' => 'Activités associées',
		),
		'Home' => array(
			'it' => 'Inizio',
			'es' => 'Inicio',
			'en' => 'Home',
			'fr' => 'Accueil',
		),
		'Chi Siamo' => array(
			'it' => 'Chi Siamo',
			'es' => 'Quiénes Somos',
			'en' => 'About Us',
			'fr' => 'Qui Sommes-Nous',
		),
		'Progetti' => array(
			'it' => 'Progetti',
			'es' => 'Proyectos',
			'en' => 'Projects',
			'fr' => 'Projets',
		),
		'Formazione' => array(
			'it' => 'Formazione',
			'es' => 'Formación',
			'en' => 'Training',
			'fr' => 'Formation',
		),
		'Crowdfunding' => array(
			'it' => 'Crowdfunding',
			'es' => 'Crowdfunding',
			'en' => 'Crowdfunding',
			'fr' => 'Financement participatif',
		),
		'CROWDFUNDING' => array(
			'it' => 'CROWDFUNDING',
			'es' => 'CROWDFUNDING',
			'en' => 'CROWDFUNDING',
			'fr' => 'FINANCEMENT PARTICIPATIF',
		),
		'Affiliazione' => array(
			'it' => 'Affiliazione',
			'es' => 'Afiliación',
			'en' => 'Affiliation',
			'fr' => 'Affiliation',
		),
		'Afiliacion' => array(
			'it' => 'Affiliazione',
			'es' => 'Afiliación',
			'en' => 'Affiliation',
			'fr' => 'Affiliation',
		),
		'Afiliación' => array(
			'it' => 'Affiliazione',
			'es' => 'Afiliación',
			'en' => 'Affiliation',
			'fr' => 'Affiliation',
		),
		'Infopoint Stranieri' => array(
			'it' => 'Infopoint Stranieri',
			'es' => 'Infopoint Extranjeros',
			'en' => 'Foreigners Infopoint',
			'fr' => 'Infopoint Étrangers',
		),
		'Associazioni di cultura deporte e tiempo libre' => array(
			'it' => 'Associazioni di cultura sport e tempo libero',
			'es' => 'Asociaciones de cultura, deporte y tiempo libre',
			'en' => 'Associations of culture, sport and leisure',
			'fr' => 'Associations de culture, sport et temps libre',
		),
		'Associazioni di cultura sport e tempo libero' => array(
			'it' => 'Associazioni di cultura sport e tempo libero',
			'es' => 'Asociaciones de cultura, deporte y tiempo libre',
			'en' => 'Associations of culture, sport and leisure',
			'fr' => 'Associations de culture, sport et temps libre',
		),
		'Asociaciones de cultura deporte y tiempo libre' => array(
			'it' => 'Associazioni di cultura sport e tempo libero',
			'es' => 'Asociaciones de cultura, deporte y tiempo libre',
			'en' => 'Associations of culture, sport and leisure',
			'fr' => 'Associations de culture, sport et temps libre',
		),
		'Asociación de cultura deporte y tiempo libre' => array(
			'it' => 'Associazione di Cultura Sport e Tempo Libero',
			'es' => 'Asociación de Cultura, Deporte y Tiempo Libre',
			'en' => 'Association of Culture, Sport and Leisure',
			'fr' => 'Association de Culture, Sport et Temps Libre',
		),
		'Learn more' => array(
			'it' => 'Scopri di più',
			'es' => 'Aprende más',
			'en' => 'Learn more',
			'fr' => 'En savoir plus',
		),
		'LEARN MORE' => array(
			'it' => 'SCOPRI DI PIÙ',
			'es' => 'APRENDE MÁS',
			'en' => 'LEARN MORE',
			'fr' => 'EN SAVOIR PLUS',
		),
		'Tutti i diritti riservati.' => array(
			'it' => 'Tutti i diritti riservati.',
			'es' => 'Todos los derechos reservados.',
			'en' => 'All rights reserved.',
			'fr' => 'Tous droits réservés.',
		),
		'© 2023 CulturaCSI. Tutti i diritti riservati.' => array(
			'it' => '© 2023 CulturaCSI. Tutti i diritti riservati.',
			'es' => '© 2023 CulturaCSI. Todos los derechos reservados.',
			'en' => '© 2023 CulturaCSI. All rights reserved.',
			'fr' => '© 2023 CulturaCSI. Tous droits réservés.',
		),
		'Associazioni' => array(
			'it' => 'Associazioni',
			'es' => 'Asociaciones',
			'en' => 'Associations',
			'fr' => 'Associations',
		),
		'Nuovo Evento' => array(
			'it' => 'Nuovo Evento',
			'es' => 'Nuevo Evento',
			'en' => 'New Event',
			'fr' => 'Nouvel Événement',
		),
		'Nuova Notizia' => array(
			'it' => 'Nuova Notizia',
			'es' => 'Nueva Noticia',
			'en' => 'New News Item',
			'fr' => 'Nouvelle Actualité',
		),
		'Nuovo Contenuto' => array(
			'it' => 'Nuovo Contenuto',
			'es' => 'Nuevo Contenido',
			'en' => 'New Content',
			'fr' => 'Nouveau Contenu',
		),
		'Ricerca' => array(
			'it' => 'Ricerca',
			'es' => 'Búsqueda',
			'en' => 'Search',
			'fr' => 'Recherche',
		),
		'Ricerca in Biblioteca' => array(
			'it' => 'Ricerca in Biblioteca',
			'es' => 'Búsqueda en Biblioteca',
			'en' => 'Search in Library',
			'fr' => 'Recherche dans Bibliothèque',
		),
		'Ricerca in Convenzioni' => array(
			'it' => 'Ricerca in Convenzioni',
			'es' => 'Búsqueda en Convenios',
			'en' => 'Search in Agreements',
			'fr' => 'Recherche dans Conventions',
		),
		'Ricerca in Progetti' => array(
			'it' => 'Ricerca in Progetti',
			'es' => 'Búsqueda en Proyectos',
			'en' => 'Search in Projects',
			'fr' => 'Recherche dans Projets',
		),
		'Ricerca in Formazione' => array(
			'it' => 'Ricerca in Formazione',
			'es' => 'Búsqueda en Formación',
			'en' => 'Search in Training',
			'fr' => 'Recherche dans Formation',
		),
		'ASSICURAZIONI PER LE ASSOCIAZIONI, TECNICI E DIRIGENTI' => array(
			'it' => 'ASSICURAZIONI PER LE ASSOCIAZIONI, TECNICI E DIRIGENTI',
			'es' => 'SEGUROS PARA ASOCIACIONES, TÉCNICOS Y DIRECTIVOS',
			'en' => 'INSURANCE FOR ASSOCIATIONS, TECHNICIANS AND MANAGERS',
			'fr' => 'ASSURANCES POUR LES ASSOCIATIONS, TECHNICIENS ET DIRIGEANTS',
		),
		'Accedi' => array(
			'it' => 'Accedi',
			'es' => 'Iniciar sesión',
			'en' => 'Sign in',
			'fr' => 'Se connecter',
		),
		'Registrati' => array(
			'it' => 'Registrati',
			'es' => 'Registrarse',
			'en' => 'Register',
			'fr' => 'S’inscrire',
		),
		'Invia Link' => array(
			'it' => 'Invia Link',
			'es' => 'Enviar enlace',
			'en' => 'Send link',
			'fr' => 'Envoyer le lien',
		),
		'Recupero Password' => array(
			'it' => 'Recupero Password',
			'es' => 'Recuperar contraseña',
			'en' => 'Password recovery',
			'fr' => 'Récupération du mot de passe',
		),
		'Password' => array(
			'it' => 'Password',
			'es' => 'Contraseña',
			'en' => 'Password',
			'fr' => 'Mot de passe',
		),
		'Conferma Password' => array(
			'it' => 'Conferma Password',
			'es' => 'Confirmar contraseña',
			'en' => 'Confirm password',
			'fr' => 'Confirmer le mot de passe',
		),
		'Nome utente o email' => array(
			'it' => 'Nome utente o email',
			'es' => 'Usuario o correo electrónico',
			'en' => 'Username or email',
			'fr' => 'Nom d’utilisateur ou e-mail',
		),
		'Username o Email' => array(
			'it' => 'Nome utente o email',
			'es' => 'Usuario o correo electrónico',
			'en' => 'Username or email',
			'fr' => 'Nom d’utilisateur ou e-mail',
		),
		'Cerca per nome o parola chiave' => array(
			'it' => 'Cerca per nome o parola chiave',
			'es' => 'Buscar por nombre o palabra clave',
			'en' => 'Search by name or keyword',
			'fr' => 'Rechercher par nom ou mot-clé',
		),
		'search per nome o parola chiave' => array(
			'it' => 'Cerca per nome o parola chiave',
			'es' => 'Buscar por nombre o palabra clave',
			'en' => 'Search by name or keyword',
			'fr' => 'Rechercher par nom ou mot-clé',
		),
		'buscar per nome o parola chiave' => array(
			'it' => 'Cerca per nome o parola chiave',
			'es' => 'Buscar por nombre o palabra clave',
			'en' => 'Search by name or keyword',
			'fr' => 'Rechercher par nom ou mot-clé',
		),
		'Macro categoria' => array(
			'it' => 'Macro categoria',
			'es' => 'Macro categoría',
			'en' => 'Macro category',
			'fr' => 'Macro catégorie',
		),
		'Settore' => array(
			'it' => 'Settore',
			'es' => 'Sector',
			'en' => 'Sector',
			'fr' => 'Secteur',
		),
		'Settore 2' => array(
			'it' => 'Settore 2',
			'es' => 'Sector 2',
			'en' => 'Sector 2',
			'fr' => 'Secteur 2',
		),
		'Regione' => array(
			'it' => 'Regione',
			'es' => 'Región',
			'en' => 'Region',
			'fr' => 'Région',
		),
		'Provincia' => array(
			'it' => 'Provincia',
			'es' => 'Provincia',
			'en' => 'Province',
			'fr' => 'Province',
		),
		'Comune / Citta' => array(
			'it' => 'Comune / Citta',
			'es' => 'Municipio / Ciudad',
			'en' => 'Municipality / City',
			'fr' => 'Commune / Ville',
		),
		'Percorso selezionato:' => array(
			'it' => 'Percorso selezionato:',
			'es' => 'Ruta seleccionada:',
			'en' => 'Selected path:',
			'fr' => 'Parcours sélectionné :',
		),
		'Tutti i settori' => array(
			'it' => 'Tutti i settori',
			'es' => 'Todos los sectores',
			'en' => 'All sectors',
			'fr' => 'Tous les secteurs',
		),
		'Tutte' => array(
			'it' => 'Tutte',
			'es' => 'Todas',
			'en' => 'All',
			'fr' => 'Toutes',
		),
		'Tutti' => array(
			'it' => 'Tutti',
			'es' => 'Todos',
			'en' => 'All',
			'fr' => 'Tous',
		),
		'Prec' => array(
			'it' => 'Prec',
			'es' => 'Ant',
			'en' => 'Prev',
			'fr' => 'Préc',
		),
		'Succ' => array(
			'it' => 'Succ',
			'es' => 'Sig',
			'en' => 'Next',
			'fr' => 'Suiv',
		),
		'Pagina' => array(
			'it' => 'Pagina',
			'es' => 'Página',
			'en' => 'Page',
			'fr' => 'Page',
		),
		'Pagine' => array(
			'it' => 'Pagine',
			'es' => 'Páginas',
			'en' => 'Pages',
			'fr' => 'Pages',
		),
		'Vai alla pagina' => array(
			'it' => 'Vai alla pagina',
			'es' => 'Ir a la página',
			'en' => 'Go to page',
			'fr' => 'Aller à la page',
		),
		'Seleziona un blocco header Kadence pubblicato' => array(
			'it' => 'Seleziona un blocco header Kadence pubblicato',
			'es' => 'Selecciona un bloque de cabecera de Kadence publicado',
			'en' => 'Select a published Kadence header block',
			'fr' => 'Sélectionnez un bloc d’en-tête Kadence publié',
		),
		'In un mondo orientato alla tecnologia, alla comunicazione digitale ai social e con l’incognita dell’Intelligenza Artificiale, l’' => array(
			'it' => 'In un mondo orientato alla tecnologia, alla comunicazione digitale ai social e con l’incognita dell’Intelligenza Artificiale, l’',
			'es' => 'En un mundo orientado a la tecnología, la comunicación digital y las redes sociales, con la incógnita de la Inteligencia Artificial, la',
			'en' => 'In a world oriented toward technology, digital communication and social media, with the uncertainty of Artificial Intelligence, the',
			'fr' => 'Dans un monde orienté vers la technologie, la communication numérique et les réseaux sociaux, avec l’inconnue de l’Intelligence Artificielle, l’',
		),
		'avverte un bisogno di corporeità e di sostenere il valore che rende autentica l’esperienza umana: la capacità di creare, condividere e crescere insieme attraverso lo sport e la cultura. Da questo principio l’Ente presenta il sito web “' => array(
			'it' => 'avverte un bisogno di corporeità e di sostenere il valore che rende autentica l’esperienza umana: la capacità di creare, condividere e crescere insieme attraverso lo sport e la cultura. Da questo principio l’Ente presenta il sito web “',
			'es' => 'percibe la necesidad de corporeidad y de sostener el valor que hace auténtica la experiencia humana: la capacidad de crear, compartir y crecer juntos a través del deporte y la cultura. A partir de este principio, el Ente presenta el sitio web “',
			'en' => 'feels the need for physicality and to uphold the value that makes the human experience authentic: the ability to create, share and grow together through sport and culture. From this principle, the Organization presents the website “',
			'fr' => 'ressent le besoin de corporalité et de soutenir la valeur qui rend authentique l’expérience humaine : la capacité de créer, partager et grandir ensemble à travers le sport et la culture. À partir de ce principe, l’Ente présente le site web «',
		),
		'“, uno spazio dedicato alla conoscenza e all’implementazione degli elementi del patrimonio intellettuale formativo associazionistico dalla scrittura alla pittura, dalla danza alle composizioni tridimensionali. Un ambito per sua natura solidale e connesso ai giovani e alle comunità del territorio rivolte al futuro, un cuore pulsante di coesione e sviluppo.' => array(
			'it' => '“, uno spazio dedicato alla conoscenza e all’implementazione degli elementi del patrimonio intellettuale formativo associazionistico dalla scrittura alla pittura, dalla danza alle composizioni tridimensionali. Un ambito per sua natura solidale e connesso ai giovani e alle comunità del territorio rivolte al futuro, un cuore pulsante di coesione e sviluppo.',
			'es' => '”, un espacio dedicado al conocimiento y a la implementación de los elementos del patrimonio intelectual y formativo asociativo, desde la escritura hasta la pintura, desde la danza hasta las composiciones tridimensionales. Un ámbito solidario por naturaleza y conectado con los jóvenes y las comunidades del territorio orientadas al futuro, un corazón palpitante de cohesión y desarrollo.',
			'en' => '”, a space dedicated to knowledge and to implementing the elements of associative educational intellectual heritage, from writing to painting, from dance to three-dimensional compositions. A field that is inherently supportive and connected to young people and local communities focused on the future, a beating heart of cohesion and development.',
			'fr' => '», un espace dédié à la connaissance et à la mise en œuvre des éléments du patrimoine intellectuel et formatif associatif, de l’écriture à la peinture, de la danse aux compositions tridimensionnelles. Un domaine solidaire par nature et lié aux jeunes et aux communautés du territoire tournées vers l’avenir, un cœur battant de cohésion et de développement.',
		),
		'(per evitare missione che non mi riesce a piacere)' => array(
			'it' => '(per evitare missione che non mi riesce a piacere)',
			'es' => '(para evitar una misión que no logro apreciar)',
			'en' => '(to avoid a mission statement I do not like)',
			'fr' => '(pour éviter une mission qui ne me plaît pas)',
		),
		'ha la missione di promuovere il patrimonio di conoscenze come bene condiviso capace di avviare una partecipazione, un dialogo e un senso di appartenenza nel multiforme mondo associazionistico; interprete e mediatore delle sue attività sociali e spirituali, artistiche, meditative e naturali, affinché possa arrivare a un pubblico sempre più ampio, e non solo legato all’' => array(
			'it' => 'ha la missione di promuovere il patrimonio di conoscenze come bene condiviso capace di avviare una partecipazione, un dialogo e un senso di appartenenza nel multiforme mondo associazionistico; interprete e mediatore delle sue attività sociali e spirituali, artistiche, meditative e naturali, affinché possa arrivare a un pubblico sempre più ampio, e non solo legato all’',
			'es' => 'tiene la misión de promover el patrimonio de conocimientos como un bien compartido capaz de generar participación, diálogo y sentido de pertenencia en el multiforme mundo asociativo; intérprete y mediador de sus actividades sociales y espirituales, artísticas, meditativas y naturales, para que pueda llegar a un público cada vez más amplio, y no solo vinculado al',
			'en' => 'has the mission of promoting the heritage of knowledge as a shared good capable of generating participation, dialogue and a sense of belonging in the multifaceted associative world; an interpreter and mediator of its social and spiritual, artistic, meditative and natural activities, so that it can reach an ever wider audience, and not only linked to',
			'fr' => 'a pour mission de promouvoir le patrimoine de connaissances comme un bien partagé capable de susciter participation, dialogue et sentiment d’appartenance dans le monde associatif multiforme ; interprète et médiateur de ses activités sociales et spirituelles, artistiques, méditatives et naturelles, afin de toucher un public toujours plus large, et pas seulement lié à',
		),
		'La cultura è armonia ed equilibrio in tutte le sue forme comunicative e' => array(
			'it' => 'La cultura è armonia ed equilibrio in tutte le sue forme comunicative e',
			'es' => 'La cultura es armonía y equilibrio en todas sus formas comunicativas y',
			'en' => 'Culture is harmony and balance in all its communicative forms and',
			'fr' => 'La culture est harmonie et équilibre dans toutes ses formes de communication et',
		),
		'sostiene percorsi di formazione, specializzazione, aggregazione e integrazione che favoriscano consapevolmente un progresso individuale e collettivo.' => array(
			'it' => 'sostiene percorsi di formazione, specializzazione, aggregazione e integrazione che favoriscano consapevolmente un progresso individuale e collettivo.',
			'es' => 'sostiene itinerarios de formación, especialización, agregación e integración que favorezcan conscientemente un progreso individual y colectivo.',
			'en' => 'supports paths of training, specialization, aggregation and integration that consciously foster individual and collective progress.',
			'fr' => 'soutient des parcours de formation, de spécialisation, d’agrégation et d’intégration qui favorisent consciemment un progrès individuel et collectif.',
		),
		'è una associazione che opera su tutto il territorio nazionale, costituita nel 1960 con più di 60 anni di attività nel settore della promozione sportiva, nella promozione sociale, culturale e del tempo libero. Riconosciuta dal CONI quale Ente di Promozione Sportiva; dal Ministero Dell’Interno quale Ente Nazionale con finalità assistenziali; dal Ministero del Lavoro e delle Politiche Sociali quale Ente di Promozione Sociale.' => array(
			'it' => 'è una associazione che opera su tutto il territorio nazionale, costituita nel 1960 con più di 60 anni di attività nel settore della promozione sportiva, nella promozione sociale, culturale e del tempo libero. Riconosciuta dal CONI quale Ente di Promozione Sportiva; dal Ministero Dell’Interno quale Ente Nazionale con finalità assistenziali; dal Ministero del Lavoro e delle Politiche Sociali quale Ente di Promozione Sociale.',
			'es' => 'es una asociación que opera en todo el territorio nacional, constituida en 1960 con más de 60 años de actividad en el ámbito de la promoción deportiva, social, cultural y del tiempo libre. Reconocida por el CONI como Ente de Promoción Deportiva; por el Ministerio del Interior como Ente Nacional con fines asistenciales; por el Ministerio de Trabajo y Políticas Sociales como Ente de Promoción Social.',
			'en' => 'is an association operating throughout the national territory, established in 1960 with more than 60 years of activity in sports, social, cultural and leisure promotion. Recognized by CONI as a Sports Promotion Body; by the Ministry of the Interior as a National Body with welfare purposes; by the Ministry of Labour and Social Policies as a Social Promotion Body.',
			'fr' => 'est une association active sur tout le territoire national, fondée en 1960 avec plus de 60 ans d’activité dans la promotion sportive, sociale, culturelle et des loisirs. Reconnue par le CONI comme Organisme de Promotion Sportive ; par le Ministère de l’Intérieur comme Organisme National à finalités d’assistance ; par le Ministère du Travail et des Politiques Sociales comme Organisme de Promotion Sociale.',
		),
		'E’ membro della CSIT Confederation Sportive Internationale du Travail (International Labour Sports Confederation), organo riconosciuto dal CIO; dell’OITS (Organizzazione Internazionale del Turismo Sociale), e del Forum del Terzo Settore.' => array(
			'it' => 'E’ membro della CSIT Confederation Sportive Internationale du Travail (International Labour Sports Confederation), organo riconosciuto dal CIO; dell’OITS (Organizzazione Internazionale del Turismo Sociale), e del Forum del Terzo Settore.',
			'es' => 'Es miembro de la CSIT Confederation Sportive Internationale du Travail (International Labour Sports Confederation), órgano reconocido por el COI; de la OITS (Organización Internacional del Turismo Social), y del Foro del Tercer Sector.',
			'en' => 'It is a member of CSIT Confederation Sportive Internationale du Travail (International Labour Sports Confederation), recognized by the IOC; of OITS (International Organization of Social Tourism), and of the Third Sector Forum.',
			'fr' => 'Elle est membre de la CSIT Confederation Sportive Internationale du Travail (International Labour Sports Confederation), organe reconnu par le CIO ; de l’OITS (Organisation Internationale du Tourisme Social), et du Forum du Tiers Secteur.',
		),
		"Nella sua qualità di Ente di Promozione Sportiva, l’ACSI si prefigge di diffondere la pratica sportiva in tutte le discipline secondo i principi educativi e tecnici promozionali rivolgendosi peculiarmente ad una fascia sociale ampia che comprende tutti i cittadini, favorendo fra essi la diffusione della pratica sportiva per uno sport inteso come servizio sociale." => array(
			'it' => "Nella sua qualità di Ente di Promozione Sportiva, l’ACSI si prefigge di diffondere la pratica sportiva in tutte le discipline secondo i principi educativi e tecnici promozionali rivolgendosi peculiarmente ad una fascia sociale ampia che comprende tutti i cittadini, favorendo fra essi la diffusione della pratica sportiva per uno sport inteso come servizio sociale.",
			'es' => "En su calidad de Ente de Promoción Deportiva, ACSI se propone difundir la práctica deportiva en todas las disciplinas según principios educativos y técnicos promocionales, dirigiéndose especialmente a una amplia franja social que comprende a todos los ciudadanos, favoreciendo entre ellos la difusión de la práctica deportiva como un deporte entendido como servicio social.",
			'en' => "As a Sports Promotion Body, ACSI aims to spread sports practice in all disciplines according to educational and promotional technical principles, addressing especially a broad social group that includes all citizens, encouraging among them the spread of sports practice as sport intended as a social service.",
			'fr' => "En sa qualité d’Organisme de Promotion Sportive, l’ACSI se fixe pour objectif de diffuser la pratique sportive dans toutes les disciplines selon des principes éducatifs et techniques promotionnels, en s’adressant particulièrement à une large tranche sociale comprenant tous les citoyens, en favorisant parmi eux la diffusion de la pratique sportive comme un sport conçu en tant que service social.",
		),
		'Se la Ruota è l’estensione delle nostre gambe, la Cultura è l’estensione del nostro cervello. (Vito Mancuso, teologo e filosofo)' => array(
			'it' => 'Se la Ruota è l’estensione delle nostre gambe, la Cultura è l’estensione del nostro cervello. (Vito Mancuso, teologo e filosofo)',
			'es' => 'Si la rueda es la extensión de nuestras piernas, la Cultura es la extensión de nuestro cerebro. (Vito Mancuso, teólogo y filósofo)',
			'en' => 'If the wheel is the extension of our legs, Culture is the extension of our brain. (Vito Mancuso, theologian and philosopher)',
			'fr' => 'Si la roue est l’extension de nos jambes, la Culture est l’extension de notre cerveau. (Vito Mancuso, théologien et philosophe)',
		),
		'La registrazione deve essere approvata da un amministratore prima dell\'accesso.' => array(
			'it' => 'La registrazione deve essere approvata da un amministratore prima dell\'accesso.',
			'es' => 'El registro debe ser aprobado por un administrador antes del acceso.',
			'en' => 'Registration must be approved by an administrator before access.',
			'fr' => 'L’inscription doit être approuvée par un administrateur avant l’accès.',
		),
		'Inserisci username o email per ricevere il link di reset.' => array(
			'it' => 'Inserisci username o email per ricevere il link di reset.',
			'es' => 'Introduce nombre de usuario o correo para recibir el enlace de restablecimiento.',
			'en' => 'Enter username or email to receive the reset link.',
			'fr' => 'Saisissez le nom d’utilisateur ou l’e-mail pour recevoir le lien de réinitialisation.',
		),
	);
	$phrases = array_replace( $phrases, culturacsi_runtime_visual_label_phrases() );

	static $index = null;
	if ( null === $index ) {
		$index = array();
		foreach ( $phrases as $variants ) {
			foreach ( $variants as $variant_text ) {
				$key = $normalize( (string) $variant_text );
				if ( '' !== $key ) {
					$index[ $key ] = $variants;
				}
			}
		}
	}

	$lookup = $normalize( $raw_text );

	// Dynamic search/result lines.
	if ( preg_match( '/^(\d+)\s+associazioni\s+trovate$/iu', $raw_text, $m ) ) {
		switch ( $current_lang ) {
			case 'es':
				return $m[1] . ' asociaciones encontradas';
			case 'en':
				return $m[1] . ' associations found';
			case 'fr':
				return $m[1] . ' associations trouvées';
			default:
				return $raw_text;
		}
	}
	if ( preg_match( '/^Area\s+Riservata\s+Associazioni$/iu', $raw_text ) ) {
		switch ( $current_lang ) {
			case 'es':
				return 'Área Reservada Asociaciones';
			case 'en':
				return 'Reserved Area Associations';
			case 'fr':
				return 'Espace Réservé Associations';
			default:
				return $raw_text;
		}
	}
	if ( preg_match( '/^Accedi\s+all[\'’]?\s*(Area\s+Riservata|Reserved\s+Area|Área\s+Reservada|Espace\s+Réservé)$/iu', $raw_text ) ) {
		switch ( $current_lang ) {
			case 'es':
				return 'Inicia sesión en el Área Reservada';
			case 'en':
				return 'Sign in to the Reserved Area';
			case 'fr':
				return 'Connectez-vous à l’Espace Réservé';
			default:
				return 'Accedi all\'Area Riservata';
		}
	}
	if ( preg_match( '/^Ricerca\s+(in\s+.+)$/iu', $raw_text, $m ) ) {
		$tail = trim( $m[1] );
		switch ( $current_lang ) {
			case 'es':
				return 'Búsqueda ' . $tail;
			case 'en':
				return 'Search ' . $tail;
			case 'fr':
				return 'Recherche ' . $tail;
			default:
				return $raw_text;
		}
	}
	if ( preg_match( '/^(\d+)\s+risultati\s+della\s+ricerca\s*-\s*Mostrati\s+(\d+)-(\d+)\s+di\s+(\d+)\s*-\s*Nel\s+mese\s+selezionato:\s*(\d+)$/iu', $raw_text, $m ) ) {
		switch ( $current_lang ) {
			case 'es':
				return sprintf( '%1$s resultados de búsqueda - Mostrando %2$s-%3$s de %4$s - En el mes seleccionado: %5$s', $m[1], $m[2], $m[3], $m[4], $m[5] );
			case 'en':
				return sprintf( '%1$s search results - Showing %2$s-%3$s of %4$s - In selected month: %5$s', $m[1], $m[2], $m[3], $m[4], $m[5] );
			case 'fr':
				return sprintf( '%1$s résultats de recherche - Affichage %2$s-%3$s sur %4$s - Dans le mois sélectionné : %5$s', $m[1], $m[2], $m[3], $m[4], $m[5] );
			default:
				return $raw_text;
		}
	}

	// Handle dynamic copyright strings with variable year reliably.
	if ( preg_match( '/^©?\s*(\d{4})?\s*CulturaCSI\.?\s*(Tutti i diritti riservati\.?)$/iu', $raw_text, $m ) ) {
		$year = isset( $m[1] ) && '' !== $m[1] ? $m[1] . ' ' : '';
		switch ( $current_lang ) {
			case 'es':
				return trim( '© ' . $year . 'CulturaCSI. Todos los derechos reservados.' );
			case 'en':
				return trim( '© ' . $year . 'CulturaCSI. All rights reserved.' );
			case 'fr':
				return trim( '© ' . $year . 'CulturaCSI. Tous droits réservés.' );
			default:
				return trim( '© ' . $year . 'CulturaCSI. Tutti i diritti riservati.' );
		}
	}

	if ( isset( $index[ $lookup ] ) ) {
		$translated = $index[ $lookup ][ $current_lang ] ?? $raw_text;
		// Preserve all-caps labels such as "AREA RISERVATA".
		$skip_caps_preserve = array(
			'servizi',
		);
		if (
			strtoupper( $raw_text ) === $raw_text &&
			strtoupper( $translated ) !== $translated &&
			! in_array( $lookup, $skip_caps_preserve, true )
		) {
			$translated = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $translated, 'UTF-8' ) : strtoupper( $translated );
		}
		return $translated;
	}

	return $raw_text;
}

if ( ! function_exists( 'culturacsi_translate_visual_label' ) ) {
	/**
	 * Translate UI labels for display only.
	 * Never use this for canonical keys, slugs, IDs, or query values.
	 */
	function culturacsi_translate_visual_label( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
		if ( ! function_exists( 'culturacsi_get_current_language' ) || 'it' === culturacsi_get_current_language() ) {
			return $text;
		}

		$translated = function_exists( 'culturacsi_it_gettext' ) ? culturacsi_it_gettext( $text, $text, 'culturacsi' ) : $text;
		if ( is_string( $translated ) && '' !== trim( $translated ) && $translated !== $text ) {
			return $translated;
		}

		$mapped = function_exists( 'culturacsi_it_runtime_label_map' ) ? culturacsi_it_runtime_label_map( $text ) : $text;
		return ( is_string( $mapped ) && '' !== trim( $mapped ) ) ? $mapped : $text;
	}
}

add_filter(
	'wp_nav_menu_objects',
	static function( $items ) {
		if ( empty( $items ) || ! is_array( $items ) ) {
			return $items;
		}
		foreach ( $items as $item ) {
			if ( isset( $item->title ) && is_string( $item->title ) ) {
				$item->title = culturacsi_it_runtime_label_map( $item->title );
			}
		}
		return $items;
	},
	99
);

add_filter(
	'nav_menu_item_title',
	static function( $title ) {
		return culturacsi_it_runtime_label_map( $title );
	},
	20
);

add_filter(
	'the_title',
	static function( $title ) {
		if ( is_admin() ) {
			return $title;
		}
		return culturacsi_it_runtime_label_map( $title );
	},
	20
);

add_filter(
	'document_title_parts',
	static function( $parts ) {
		foreach ( $parts as $k => $v ) {
			if ( is_string( $v ) ) {
				$parts[ $k ] = culturacsi_it_runtime_label_map( $v );
			}
		}
		return $parts;
	},
	20
);

/**
 * Translate hardcoded frontend Italian labels emitted by custom templates/shortcodes.
 * This runs after rendering and only on normal frontend HTML responses.
 */
function culturacsi_translate_frontend_html_runtime( string $html ): string {
	if ( '' === $html || 'it' === culturacsi_get_current_language() ) {
		return $html;
	}

	$translate_runtime_text = static function( string $text ): string {
		$decoded = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		$translated = culturacsi_it_gettext( $decoded, $decoded, 'culturacsi' );
		if ( $translated !== $decoded ) {
			return $translated;
		}

		$translated = culturacsi_it_runtime_label_map( $decoded );
		if ( $translated !== $decoded ) {
			return $translated;
		}

		$lang = culturacsi_get_current_language();
		$phrase_maps = array(
			'en' => array(
				'Associazione di Cultura Sport e Tempo Libero' => 'Association of Culture, Sport and Leisure',
				'Esplora le attività e i servizi relativi a' => 'Explore the activities and services related to',
				'Attività correlate' => 'Related activities',
				'Nessun contenuto trovato' => 'No content found',
				'Area Riservata' => 'Reserved Area',
				'Calendario Eventi' => 'Events Calendar',
				'Lingua:' => 'Language:',
				'Chi Siamo' => 'About Us',
				'Progetti' => 'Projects',
				'Formazione' => 'Training',
				'Crowdfunding' => 'Crowdfunding',
				'CROWDFUNDING' => 'Crowdfunding',
				'Affiliazione' => 'Affiliation',
				'Infopoint Stranieri' => 'Foreigners Infopoint',
				'Tutti i diritti riservati.' => 'All rights reserved.',
			),
			'es' => array(
				'Associazione di Cultura Sport e Tempo Libero' => 'Asociación de Cultura, Deporte y Tiempo Libre',
				'Esplora le attività e i servizi relativi a' => 'Explora las actividades y servicios relacionados con',
				'Attività correlate' => 'Actividades relacionadas',
				'Nessun contenuto trovato' => 'No se encontró contenido',
				'Area Riservata' => 'Área Reservada',
				'Calendario Eventi' => 'Calendario de Eventos',
				'Lingua:' => 'Idioma:',
				'Chi Siamo' => 'Quiénes Somos',
				'Progetti' => 'Proyectos',
				'Formazione' => 'Formación',
				'Crowdfunding' => 'Crowdfunding',
				'CROWDFUNDING' => 'Crowdfunding',
				'Affiliazione' => 'Afiliación',
				'Infopoint Stranieri' => 'Infopoint Extranjeros',
				'Tutti i diritti riservati.' => 'Todos los derechos reservados.',
			),
			'fr' => array(
				'Associazione di Cultura Sport e Tempo Libero' => 'Association de Culture, Sport et Temps Libre',
				'Esplora le attività e i servizi relativi a' => 'Découvrez les activités et services liés à',
				'Attività correlate' => 'Activités associées',
				'Nessun contenuto trovato' => 'Aucun contenu trouvé',
				'Area Riservata' => 'Espace Réservé',
				'Calendario Eventi' => 'Calendrier des Événements',
				'Lingua:' => 'Langue :',
				'Chi Siamo' => 'Qui Sommes-Nous',
				'Progetti' => 'Projets',
				'Formazione' => 'Formation',
				'Crowdfunding' => 'Financement participatif',
				'CROWDFUNDING' => 'Financement participatif',
				'Affiliazione' => 'Affiliation',
				'Infopoint Stranieri' => 'Infopoint Étrangers',
				'Tutti i diritti riservati.' => 'Tous droits réservés.',
			),
		);

		if ( isset( $phrase_maps[ $lang ] ) ) {
			$candidate = strtr( $decoded, $phrase_maps[ $lang ] );
			if ( $candidate !== $decoded ) {
				$decoded = $candidate;
			}
		}

		// Last fallback: word-level translation for Italian-authored content snippets.
		$word_maps = array(
			'en' => array(
				'attività' => 'activities', 'servizi' => 'services', 'cultura' => 'culture', 'sport' => 'sport',
				'tempo libero' => 'leisure', 'contatti' => 'contacts', 'eventi' => 'events', 'notizie' => 'news',
				'settori' => 'sectors', 'area riservata' => 'reserved area', 'cerca' => 'search', 'profilo' => 'profile',
				'chi siamo' => 'about us', 'progetti' => 'projects', 'formazione' => 'training', 'affiliazione' => 'affiliation',
				'infopoint stranieri' => 'foreigners infopoint',
			),
			'es' => array(
				'attività' => 'actividades', 'servizi' => 'servicios', 'cultura' => 'cultura', 'sport' => 'deporte',
				'tempo libero' => 'tiempo libre', 'contatti' => 'contactos', 'eventi' => 'eventos', 'notizie' => 'noticias',
				'settori' => 'sectores', 'area riservata' => 'área reservada', 'cerca' => 'buscar', 'profilo' => 'perfil',
				'chi siamo' => 'quiénes somos', 'progetti' => 'proyectos', 'formazione' => 'formación', 'affiliazione' => 'afiliación',
				'infopoint stranieri' => 'infopoint extranjeros',
			),
			'fr' => array(
				'attività' => 'activités', 'servizi' => 'services', 'cultura' => 'culture', 'sport' => 'sport',
				'tempo libero' => 'temps libre', 'contatti' => 'contacts', 'eventi' => 'événements', 'notizie' => 'actualités',
				'settori' => 'secteurs', 'area riservata' => 'espace réservé', 'cerca' => 'rechercher', 'profilo' => 'profil',
				'chi siamo' => 'qui sommes-nous', 'progetti' => 'projets', 'formazione' => 'formation', 'affiliazione' => 'affiliation',
				'infopoint stranieri' => 'infopoint étrangers',
			),
		);

		if ( isset( $word_maps[ $lang ] ) ) {
			foreach ( $word_maps[ $lang ] as $it => $target ) {
				$decoded = preg_replace( '/\b' . preg_quote( $it, '/' ) . '\b/ui', $target, $decoded );
			}
		}

		return $decoded;
	};

	// Do not alter script/style/textarea/code/pre contents.
	$protected_chunks = array();
	$placeholder_i = 0;
	$html = preg_replace_callback(
		'~<(script|style|textarea|code|pre)\b[^>]*>.*?</\1>~is',
		static function( $m ) use ( &$protected_chunks, &$placeholder_i ) {
			$key = "__CSI_TR_PROTECTED_{$placeholder_i}__";
			$protected_chunks[ $key ] = $m[0];
			$placeholder_i++;
			return $key;
		},
		$html
	);

	$html = preg_replace_callback(
		'~>([^<>]+)<~u',
		static function( $m ) use ( $translate_runtime_text ) {
			$text = $m[1];
			if ( '' === trim( $text ) ) {
				return $m[0];
			}

			if ( ! preg_match( '/^(\s*)(.*?)(\s*)$/us', $text, $parts ) ) {
				return $m[0];
			}

			$leading  = $parts[1];
			$core_raw = $parts[2];
			$trailing = $parts[3];

			$core = html_entity_decode( $core_raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$translated = $translate_runtime_text( $core );

			if ( $translated === $core ) {
				return $m[0];
			}

			return '>' . $leading . esc_html( $translated ) . $trailing . '<';
		},
		$html
	);

	// Translate common human-facing attributes that are frequently hardcoded.
	$html = preg_replace_callback(
		'~\s(aria-label|title|placeholder|alt)=("|\')(.*?)\2~uis',
		static function( $m ) use ( $translate_runtime_text ) {
			$attr_name  = $m[1];
			$quote      = $m[2];
			$attr_value = (string) $m[3];
			if ( '' === trim( $attr_value ) ) {
				return $m[0];
			}

			$decoded = html_entity_decode( $attr_value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$translated = $translate_runtime_text( $decoded );
			if ( $translated === $decoded ) {
				return $m[0];
			}

			return ' ' . $attr_name . '=' . $quote . esc_attr( $translated ) . $quote;
		},
		$html
	);

	if ( ! empty( $protected_chunks ) ) {
		$html = strtr( $html, $protected_chunks );
	}

	return $html;
}

add_action(
	'template_redirect',
	static function() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( is_feed() || is_trackback() || is_robots() || is_embed() ) {
			return;
		}
		if ( 'GET' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
			return;
		}

		ob_start( 'culturacsi_translate_frontend_html_runtime' );
	},
	999
);

/**
 * Final client-side pass for visible labels that may bypass PHP string filters.
 */
add_action(
	'wp_footer',
	static function() {
		if ( is_admin() ) {
			return;
		}
		$lang = culturacsi_get_current_language();
		if ( 'it' === $lang ) {
			return;
		}

		$maps = array(
			'en' => array(
				'Chi Siamo' => 'About Us',
				'Servizi' => 'Services',
				'Sevizi' => 'Services',
				'SERVIZI' => 'Services',
				'Convenzione' => 'Agreement',
				'Convenzioni' => 'Agreements',
				'Assicurazioni' => 'Insurance',
				'Le Nostre Polizze' => 'Our Policies',
				'Polizze Integrali' => 'Comprehensive Policies',
				'Pilizze Integrali' => 'Comprehensive Policies',
				'Nome e Dintorni' => 'Name and Surroundings',
				'Norme e Dintorni' => 'Rules and Surroundings',
				'Editoria' => 'Publishing',
				'Dama e Scacchi' => 'Checkers and Chess',
				'Danza Aerea' => 'Aerial Dance',
				'Arti Performative / Danza e Movimento' => 'Performing Arts / Dance and Movement',
				'ARTI PERFORMATIVE / DANZA E MOVIMENTO' => 'Performing Arts / Dance and Movement',
				'IL NOSTRO TEAM' => 'OUR TEAM',
				'DOVE SIAMO' => 'WHERE WE ARE',
				'ACSI NAZIONALE' => 'ACSI NATIONAL',
				'ACSI Nazionale' => 'ACSI National',
				'Responsabile Amministrativo e Curatore Editoriale' => 'Administrative Manager and Editorial Curator',
				'Responsabile Sportello foreigners infopoint e Supporto Amministrativo' => 'Foreigners infopoint desk manager and administrative support',
				'contacts CULTURACSI' => 'CULTURACSI contacts',
				'contacts SPORTELLO foreigners infopoint' => 'Foreigners infopoint desk contacts',
				'Citadini' => 'Citizens',
				'Cittadini' => 'Citizens',
				'Polizza Attivitá Assistite con Animali' => 'Policy for animal-assisted activities',
				'Polizza Attività Assistite con Animali' => 'Policy for animal-assisted activities',
				'Progetti' => 'Projects',
				'Formazione' => 'Training',
				'Affiliazione' => 'Affiliation',
				'Afiliación' => 'Affiliation',
				'Afiliacion' => 'Affiliation',
				'Infopoint Stranieri' => 'Foreigners Infopoint',
				'Associazioni di cultura deporte e tiempo libre' => 'Associations of culture, sport and leisure',
				'Chiudi' => 'Close',
				'Dettagli associazione' => 'Association details',
				'Mappa' => 'Map',
				'Mappa associazione' => 'Association map',
				'Mappa non disponibile per questa associazione.' => 'Map not available for this association.',
				'Attività' => 'Activities',
				'Attivita' => 'Activities',
				'Macro > Settore > Settore 2' => 'Macro > Sector > Sector 2',
				'Localita (sorgente)' => 'Location (source)',
				'Percorso selezionato:' => 'Selected path:',
				'Tutti i settori' => 'All sectors',
				'Inizio:' => 'Start:',
				'Fine:' => 'End:',
				'Sede:' => 'Venue:',
				'Iscriviti' => 'Register',
				'Riduci' => 'Zoom out',
				'Reimposta zoom' => 'Reset zoom',
				'Ingrandisci' => 'Zoom in',
				'Evento' => 'Event',
				'Learn more' => 'Learn more',
				'LEARN MORE' => 'LEARN MORE',
				'Tutti i diritti riservati.' => 'All rights reserved.',
				'© 2023 CulturaCSI. Tutti i diritti riservati.' => '© 2023 CulturaCSI. All rights reserved.',
			),
			'es' => array(
				'Chi Siamo' => 'Quiénes Somos',
				'Servizi' => 'Servicios',
				'Sevizi' => 'Servicios',
				'SERVIZI' => 'Servicios',
				'Convenzione' => 'Convenio',
				'Convenzioni' => 'Convenios',
				'Assicurazioni' => 'Seguros',
				'Le Nostre Polizze' => 'Nuestras Pólizas',
				'Polizze Integrali' => 'Pólizas Integrales',
				'Pilizze Integrali' => 'Pólizas Integrales',
				'Nome e Dintorni' => 'Nombre y alrededores',
				'Norme e Dintorni' => 'Normas y entorno',
				'Editoria' => 'Editorial',
				'Dama e Scacchi' => 'Damas y Ajedrez',
				'Danza Aerea' => 'Danza Aérea',
				'Arti Performative / Danza e Movimento' => 'Artes Escénicas / Danza y Movimiento',
				'ARTI PERFORMATIVE / DANZA E MOVIMENTO' => 'Artes Escénicas / Danza y Movimiento',
				'IL NOSTRO TEAM' => 'NUESTRO EQUIPO',
				'DOVE SIAMO' => 'DÓNDE ESTAMOS',
				'ACSI NAZIONALE' => 'ACSI NACIONAL',
				'ACSI Nazionale' => 'ACSI Nacional',
				'Responsabile Amministrativo e Curatore Editoriale' => 'Responsable administrativo y curador editorial',
				'Responsabile Sportello foreigners infopoint e Supporto Amministrativo' => 'Responsable del punto de atención infopoint extranjeros y soporte administrativo',
				'contacts CULTURACSI' => 'contactos CULTURACSI',
				'contacts SPORTELLO foreigners infopoint' => 'contactos VENTANILLA infopoint extranjeros',
				'Citadini' => 'Ciudadanos',
				'Cittadini' => 'Ciudadanos',
				'Polizza Attivitá Assistite con Animali' => 'Póliza para actividades asistidas con animales',
				'Polizza Attività Assistite con Animali' => 'Póliza para actividades asistidas con animales',
				'Progetti' => 'Proyectos',
				'Formazione' => 'Formación',
				'Affiliazione' => 'Afiliación',
				'Infopoint Stranieri' => 'Infopoint Extranjeros',
				'Associazioni di cultura deporte e tiempo libre' => 'Asociaciones de cultura, deporte y tiempo libre',
				'Chiudi' => 'Cerrar',
				'Dettagli associazione' => 'Detalles de la asociación',
				'Mappa' => 'Mapa',
				'Mappa associazione' => 'Mapa de la asociación',
				'Mappa non disponibile per questa associazione.' => 'Mapa no disponible para esta asociación.',
				'Attività' => 'Actividades',
				'Attivita' => 'Actividades',
				'Macro > Settore > Settore 2' => 'Macro > Sector > Sector 2',
				'Localita (sorgente)' => 'Localidad (origen)',
				'Percorso selezionato:' => 'Ruta seleccionada:',
				'Tutti i settori' => 'Todos los sectores',
				'Inizio:' => 'Inicio:',
				'Fine:' => 'Fin:',
				'Sede:' => 'Lugar:',
				'Iscriviti' => 'Inscríbete',
				'Riduci' => 'Reducir',
				'Reimposta zoom' => 'Restablecer zoom',
				'Ingrandisci' => 'Ampliar',
				'Evento' => 'Evento',
				'Learn more' => 'Aprende más',
				'LEARN MORE' => 'APRENDE MÁS',
				'Tutti i diritti riservati.' => 'Todos los derechos reservados.',
				'© 2023 CulturaCSI. Tutti i diritti riservati.' => '© 2023 CulturaCSI. Todos los derechos reservados.',
			),
			'fr' => array(
				'Chi Siamo' => 'Qui Sommes-Nous',
				'Servizi' => 'Services',
				'Sevizi' => 'Services',
				'SERVIZI' => 'Services',
				'Convenzione' => 'Convention',
				'Convenzioni' => 'Conventions',
				'Assicurazioni' => 'Assurances',
				'Le Nostre Polizze' => 'Nos Polices',
				'Polizze Integrali' => 'Polices Intégrales',
				'Pilizze Integrali' => 'Polices Intégrales',
				'Nome e Dintorni' => 'Nom et alentours',
				'Norme e Dintorni' => 'Règles et alentours',
				'Editoria' => 'Édition',
				'Dama e Scacchi' => 'Dames et Échecs',
				'Danza Aerea' => 'Danse Aérienne',
				'Arti Performative / Danza e Movimento' => 'Arts Scéniques / Danse et Mouvement',
				'ARTI PERFORMATIVE / DANZA E MOVIMENTO' => 'Arts Scéniques / Danse et Mouvement',
				'IL NOSTRO TEAM' => 'NOTRE ÉQUIPE',
				'DOVE SIAMO' => 'OÙ NOUS SOMMES',
				'ACSI NAZIONALE' => 'ACSI NATIONAL',
				'ACSI Nazionale' => 'ACSI National',
				'Responsabile Amministrativo e Curatore Editoriale' => 'Responsable administratif et curateur éditorial',
				'Responsabile Sportello foreigners infopoint e Supporto Amministrativo' => 'Responsable du guichet infopoint étrangers et support administratif',
				'contacts CULTURACSI' => 'contacts CULTURACSI',
				'contacts SPORTELLO foreigners infopoint' => 'contacts GUICHET infopoint étrangers',
				'Citadini' => 'Citoyens',
				'Cittadini' => 'Citoyens',
				'Polizza Attivitá Assistite con Animali' => 'Police pour activités assistées avec animaux',
				'Polizza Attività Assistite con Animali' => 'Police pour activités assistées avec animaux',
				'Progetti' => 'Projets',
				'Formazione' => 'Formation',
				'Affiliazione' => 'Affiliation',
				'Infopoint Stranieri' => 'Infopoint Étrangers',
				'Associazioni di cultura deporte e tiempo libre' => 'Associations de culture, sport et temps libre',
				'Chiudi' => 'Fermer',
				'Dettagli associazione' => 'Détails de l’association',
				'Mappa' => 'Carte',
				'Mappa associazione' => 'Carte de l’association',
				'Mappa non disponibile per questa associazione.' => 'Carte non disponible pour cette association.',
				'Attività' => 'Activités',
				'Attivita' => 'Activités',
				'Macro > Settore > Settore 2' => 'Macro > Secteur > Secteur 2',
				'Localita (sorgente)' => 'Localité (source)',
				'Percorso selezionato:' => 'Parcours sélectionné :',
				'Tutti i settori' => 'Tous les secteurs',
				'Inizio:' => 'Début:',
				'Fine:' => 'Fin:',
				'Sede:' => 'Lieu:',
				'Iscriviti' => 'Inscrivez-vous',
				'Riduci' => 'Réduire',
				'Reimposta zoom' => 'Réinitialiser le zoom',
				'Ingrandisci' => 'Agrandir',
				'Evento' => 'Événement',
				'Learn more' => 'En savoir plus',
				'LEARN MORE' => 'EN SAVOIR PLUS',
				'Tutti i diritti riservati.' => 'Tous droits réservés.',
				'© 2023 CulturaCSI. Tutti i diritti riservati.' => '© 2023 CulturaCSI. Tous droits réservés.',
			),
		);

		$map = $maps[ $lang ] ?? array();
		if ( empty( $map ) ) {
			return;
		}
		?>
		<script id="culturacsi-runtime-dom-i18n">
		(function(){
			var map = <?php echo wp_json_encode( $map ); ?>;
			var selectors = [
				'header a','header button','nav a','nav button','.menu a','.menu button',
				'.footer-main','footer','a.button','.button','.wp-block-button__link','.kt-btn-inner-text',
				'.wp-block-navigation','.wp-block-navigation-item__content',
				'.kb-nav-link-content','.kb-nav-dropdown-toggle-btn','.kt-blocks-gallery-item__caption',
				'.ab-assoc-modal','.abf-wrap','#event-modal','.event-modal','.assoc-portal-calendar','.assoc-portal-events-cards'
			];
			var trimSpaces = function(s){ return (s || '').replace(/\s+/g,' ').trim(); };
			var normalize = function(s){
				var out = trimSpaces(s);
				if(!out){ return ''; }
				if(out.normalize){
					out = out.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
				}
				out = out.replace(/[^A-Za-z0-9\u00C0-\u024F\s]+/g, ' ');
				out = trimSpaces(out).toLowerCase();
				return out;
			};
			var normalizedMap = {};
			Object.keys(map).forEach(function(k){
				var nk = normalize(k);
				if(nk && !Object.prototype.hasOwnProperty.call(normalizedMap, nk)){
					normalizedMap[nk] = map[k];
				}
			});
			var replaceInString = function(input){
				var original = input || '';
				var core = trimSpaces(original);
				if(!core){ return original; }
				if(Object.prototype.hasOwnProperty.call(map, core)){
					return original.replace(core, map[core]);
				}
				var normalizedCore = normalize(core);
				if(normalizedCore && Object.prototype.hasOwnProperty.call(normalizedMap, normalizedCore)){
					return original.replace(core, normalizedMap[normalizedCore]);
				}
				var next = core;
				Object.keys(map).forEach(function(k){
					if(next.indexOf(k) !== -1){
						next = next.split(k).join(map[k]);
					}
				});
				if(next !== core){
					return original.replace(core, next);
				}
				return original;
			};
			var rewriteTextNodes = function(root){
				if(!root){ return; }
				var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
					acceptNode: function(n){
						if(!n || !n.nodeValue || !trimSpaces(n.nodeValue)){ return NodeFilter.FILTER_REJECT; }
						var parent = n.parentElement;
						if(!parent){ return NodeFilter.FILTER_REJECT; }
						var tag = (parent.tagName || '').toLowerCase();
						if(tag === 'script' || tag === 'style' || tag === 'textarea'){ return NodeFilter.FILTER_REJECT; }
						return NodeFilter.FILTER_ACCEPT;
					}
				});
				var textNodes = [];
				while(walker.nextNode()){ textNodes.push(walker.currentNode); }
				textNodes.forEach(function(n){
					var updated = replaceInString(n.nodeValue);
					if(updated !== n.nodeValue){
						n.nodeValue = updated;
					}
				});
			};
			var rewriteAttrs = function(root){
				if(!root || !root.querySelectorAll){ return; }
				var attrs = ['aria-label','title','placeholder','alt'];
				var nodes = [root];
				root.querySelectorAll('*').forEach(function(n){ nodes.push(n); });
				nodes.forEach(function(el){
					attrs.forEach(function(attr){
						if(!el.hasAttribute || !el.hasAttribute(attr)){ return; }
						var current = el.getAttribute(attr) || '';
						if(!trimSpaces(current)){ return; }
						var updated = replaceInString(current);
						if(updated !== current){
							el.setAttribute(attr, updated);
						}
					});
				});
			};
			var run = function(root){
				var scope = root || document;
				selectors.forEach(function(sel){
					if(scope.matches && scope.matches(sel)){
						rewriteTextNodes(scope);
						rewriteAttrs(scope);
					}
					scope.querySelectorAll(sel).forEach(function(node){
						rewriteTextNodes(node);
						rewriteAttrs(node);
					});
				});
			};
			run(document);
			var mo = new MutationObserver(function(muts){
				muts.forEach(function(m){
					if(m && m.target && m.target.nodeType === 1){
						run(m.target);
					}
				});
			});
			mo.observe(document.documentElement, { childList:true, subtree:true });
		})();
		</script>
		<?php
	},
	9999
);

add_filter(
	'render_block',
	static function( $block_content ) {
		if ( ! is_string( $block_content ) || '' === $block_content ) {
			return $block_content;
		}

		// Ensure the header CTA is always a real link to the reserved area gateway
		// across all runtime languages (render-level only).
		$block_content = preg_replace(
			'~<span class="kb-button([^"]*)">\s*<span class="kt-btn-inner-text">\s*(?:AREA\s+RISERVATA|RESERVED\s+AREA|[ÁA]REA\s+RESERVADA|ESPACE\s+R[ÉE]SERV[ÉE])\s*</span>(.*?)</span>~isu',
			'<a class="kb-button$1" href="' . esc_url( home_url( '/area-riservata/' ) ) . '"><span class="kt-btn-inner-text">AREA RISERVATA</span>$2</a>',
			$block_content
		);

		// Nav regression guard: on Chi Siamo pages, "Progetti/Projects/Proyectos/Projets"
		// must never carry current-menu-* active classes.
		$request_path = trim( strtolower( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ) ), '/' );
		$is_chi_siamo = (bool) preg_match( '~(^|/)(chi-siamo|about-us|quienes-somos|qui-sommes-nous)/?$~', $request_path );
		if ( $is_chi_siamo ) {
			$block_content = preg_replace_callback(
				'~<li(?P<before>[^>]*?)class=(["\'])(?P<class>[^"\']*)(\2)(?P<after>[^>]*)>\s*<div[^>]*>\s*<a[^>]*\brole=(["\'])button\6[^>]*>\s*(?P<label>Progetti|Projects|Proyectos|Projets)\s*</a>~iu',
				static function( $m ) {
					$classes = preg_split( '/\s+/', trim( (string) ( $m['class'] ?? '' ) ) );
					if ( ! is_array( $classes ) ) {
						return $m[0];
					}
					$remove = array(
						'current-menu-item',
						'current-menu-ancestor',
						'current-menu-parent',
						'current_page_parent',
						'current_page_ancestor',
						'kb-link-current',
					);
					$classes = array_values(
						array_filter(
							$classes,
							static function( $c ) use ( $remove ) {
								return '' !== $c && ! in_array( $c, $remove, true );
							}
						)
					);
					$new_class = implode( ' ', $classes );
					$segment   = (string) $m[0];
					return (string) preg_replace(
						'~class=(["\'])[^"\']*\1~',
						'class="' . esc_attr( $new_class ) . '"',
						$segment,
						1
					);
				},
				$block_content
			);
		}

		// These replacements are intentionally Italian-specific.
		// Do not force them when a different runtime language is selected.
		if ( 'it' !== culturacsi_get_current_language() ) {
			return $block_content;
		}

		// Fast-fail if no relevant keywords are found to avoid heavy processing
		if ( false === stripos( $block_content, 'news' ) && 
			 false === stripos( $block_content, 'events' ) && 
			 false === stripos( $block_content, 'dashboard' ) &&
			 false === stripos( $block_content, 'contatti' ) &&
			 false === stripos( $block_content, 'AREA RISERVATA' ) ) {
			return $block_content;
		}

		$site_base = untrailingslashit( home_url( '/' ) );
		$replacements = array(
			'>News<'          => '>Notizie<',
			' href="' . $site_base . '/news/"' => ' href="' . $site_base . '/notizie/"',
			' href="' . $site_base . '/events/"' => ' href="' . $site_base . '/calendario/"',
			' href="' . $site_base . '/dashboard/events/"' => ' href="' . $site_base . '/area-riservata/eventi/"',
			' href="' . $site_base . '/dashboard/profile/"' => ' href="' . $site_base . '/area-riservata/profilo/"',
		);
		$block_content = strtr( $block_content, $replacements );
		$block_content = preg_replace(
			'~<a([^>]+)href="' . preg_quote( $site_base, '~' ) . '/contatti/"([^>]*)>\s*Calendario\s+Eventi\s*</a>~i',
			'<a$1href="' . esc_url( home_url( '/calendario/' ) ) . '"$2>Calendario Eventi</a>',
			$block_content
		);

		return $block_content;
	},
	20
);

