<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reserved-area page provisioning and guardrails.
 *
 * These routines ensure `/area-riservata` routes exist as editable WordPress
 * pages while preserving editor-managed layouts whenever possible.
 */

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
	$pages_schema_version = 'v6';

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
	$forum_block        = '<!-- wp:shortcode -->[culturacsi_reserved_forum]<!-- /wp:shortcode -->';
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
		'area-riservata/bacheca',
		'Area Riservata Bacheca',
		'bacheca',
		$compose_content( $forum_block ),
		$parent_id,
		array( $forum_block )
	);
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
