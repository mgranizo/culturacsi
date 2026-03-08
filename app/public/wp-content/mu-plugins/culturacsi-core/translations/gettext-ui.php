<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gettext remapping plus translation-related UI fragments.
 *
 * This module keeps the heavy string maps close to the frontend pieces that
 * still depend on them, such as the reserved-area login modal and language
 * selector shortcode.
 */

function culturacsi_gettext_ui_asset_url( string $relative_path ): string {
	return content_url( 'mu-plugins/culturacsi-core/assets/' . ltrim( $relative_path, '/' ) );
}

function culturacsi_gettext_ui_asset_path( string $relative_path ): string {
	return dirname( __DIR__ ) . '/assets/' . ltrim( $relative_path, '/' );
}

function culturacsi_gettext_ui_asset_version( string $relative_path ): ?string {
	$asset_path = culturacsi_gettext_ui_asset_path( $relative_path );
	return file_exists( $asset_path ) ? (string) filemtime( $asset_path ) : null;
}

function culturacsi_enqueue_gettext_ui_assets(): void {
	static $enqueued = false;
	if ( $enqueued ) {
		return;
	}
	$enqueued = true;

	wp_enqueue_style(
		'culturacsi-gettext-ui',
		culturacsi_gettext_ui_asset_url( 'gettext-ui.css' ),
		array(),
		culturacsi_gettext_ui_asset_version( 'gettext-ui.css' )
	);

	wp_enqueue_script(
		'culturacsi-gettext-ui',
		culturacsi_gettext_ui_asset_url( 'gettext-ui.js' ),
		array(),
		culturacsi_gettext_ui_asset_version( 'gettext-ui.js' ),
		true
	);
}

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
	culturacsi_enqueue_gettext_ui_assets();
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
	<?php
}
add_action( 'wp_footer', 'culturacsi_it_reserved_login_modal', 100 );

/**
 * Language selector shortcode
 * Usage: [culturacsi_language_selector] or [culturacsi_language_selector style="dropdown"]
 */
function culturacsi_language_selector_shortcode( $atts ) {
	culturacsi_enqueue_gettext_ui_assets();

	
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
	}
	
	return $html;
}
add_shortcode( 'culturacsi_language_selector', 'culturacsi_language_selector_shortcode' );
