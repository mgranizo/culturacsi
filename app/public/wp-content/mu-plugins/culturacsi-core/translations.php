<?php
if ( ! defined( 'ABSPATH' ) ) exit;
add_filter(
	'pre_option_WPLANG',
	static function() {
		return 'it_IT';
	}
);

add_filter(
	'locale',
	static function() {
		return 'it_IT';
	},
	20
);

add_filter(
	'determine_locale',
	static function() {
		return 'it_IT';
	},
	20
);

add_filter(
	'language_attributes',
	static function( $output ) {
		return preg_replace( '/lang=("|\')en-US("|\')/i', 'lang="it-IT"', $output );
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
	$pages_schema_version = 'v3';

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

	static $map = null;
	if ( null === $map ) {
		$map = array(
			// Kadence/common.
			'News'                           => 'Notizie',
			'Read More'                      => 'Leggi di piu',
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
			'Insert into association'        => 'Inserisci nell associazione',
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
			'Activity Categories'            => 'Categorie attivita',
			'Activity Category'              => 'Categoria attivita',
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
			'Link this user to the association they manage.' => 'Collega questo utente all associazione che gestisce.',
			'You must be logged in as an Association Manager to view this page.' => 'Devi essere autenticato come Gestore Associazione per visualizzare questa pagina.',
			'Association Portal'             => 'Portale associazioni',
			'Welcome to your portal. From here you can manage your association profile and your events.' => 'Benvenuto nel portale. Da qui puoi gestire profilo associazione ed eventi.',
			'Edit My Association Profile'    => 'Dati Associazione',
			'Manage My Events'               => 'Gestisci i miei eventi',
			'Your user account is not linked to an association. Please contact an administrator.' => 'Il tuo account non e collegato a una associazione. Contatta un amministratore.',
			'The linked item is not a valid association. Please contact an administrator.' => 'L elemento collegato non e una associazione valida. Contatta un amministratore.',
			'Profile updated successfully!'  => 'Profilo aggiornato correttamente!',
			'Editing Profile for: %s'        => 'Modifica profilo per: %s',
			'Contact Information'            => 'Informazioni contatto',
			'Contact Email'                  => 'Email contatto',
			'Phone'                          => 'Telefono',
			'Website'                        => 'Sito web',
			'Location'                       => 'Sede',
			'Address'                        => 'Indirizzo',
			'City'                           => 'Citta',
			'Province (e.g., "MI")'          => 'Provincia (es. "MI")',
			'Social Media'                   => 'Social',
			'Facebook URL'                   => 'URL Facebook',
			'Instagram URL'                  => 'URL Instagram',
			'YouTube URL'                    => 'URL YouTube',
			'TikTok URL'                     => 'URL TikTok',
			'X (Twitter) URL'                => 'URL X (Twitter)',
			'Classification'                 => 'Classificazione',
			'Select the categories that best describe your association\'s activities.' => 'Seleziona le categorie che descrivono meglio le attivita della tua associazione.',
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
			'Venue & Location'               => 'Luogo e localita',
			'Venue Name'                     => 'Nome sede',
			'Details'                        => 'Dettagli',
			'Registration URL'               => 'URL iscrizione',
			'Event Image'                    => 'Immagine evento',
			'Current Image:'                 => 'Immagine attuale:',
			'Submit for Review'              => 'Invia per revisione',
			'Prev'                           => 'Prec',
		);
	}

	return isset( $map[ $text ] ) ? $map[ $text ] : $translated;
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

function culturacsi_it_runtime_label_map( $text ) {
	$map = array(
		'News'      => 'Notizie',
		'My Events' => 'I miei eventi',
		'Upcoming Events' => 'Prossimi eventi',
		'Dashboard' => 'Area riservata',
		'Profile'   => 'Profilo',
	);
	return isset( $map[ $text ] ) ? $map[ $text ] : $text;
}

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

add_filter(
	'render_block',
	static function( $block_content ) {
		if ( ! is_string( $block_content ) || '' === $block_content ) {
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

		// Ensure the Kadence header CTA is always a real link to the reserved area gateway.
		$block_content = preg_replace(
			'~<span class="kb-button([^"]*)">\s*<span class="kt-btn-inner-text">\s*AREA\s+RISERVATA\s*</span>(.*?)</span>~is',
			'<a class="kb-button$1" href="' . esc_url( home_url( '/area-riservata/' ) ) . '"><span class="kt-btn-inner-text">AREA RISERVATA</span>$2</a>',
			$block_content
		);

		return $block_content;
	},
	20
);

