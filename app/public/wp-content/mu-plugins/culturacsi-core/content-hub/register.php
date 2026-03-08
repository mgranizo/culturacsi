<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content hub registration and query plumbing.
 *
 * This module owns post types, taxonomies, virtual query sources, and the
 * low-level query rewriting required by block-based content feeds.
 */

/**
 * Return capability mapping for content hub post type.
 *
 * @return array<string,string>
 */
function culturacsi_content_hub_post_type_capabilities() {
	return array(
		'edit_post'              => 'edit_csi_content_entry',
		'read_post'              => 'read_csi_content_entry',
		'delete_post'            => 'delete_csi_content_entry',
		'edit_posts'             => 'edit_csi_content_entries',
		'edit_others_posts'      => 'edit_others_csi_content_entries',
		'publish_posts'          => 'publish_csi_content_entries',
		'read_private_posts'     => 'read_private_csi_content_entries',
		'delete_posts'           => 'delete_csi_content_entries',
		'delete_private_posts'   => 'delete_private_csi_content_entries',
		'delete_published_posts' => 'delete_published_csi_content_entries',
		'delete_others_posts'    => 'delete_others_csi_content_entries',
		'edit_private_posts'     => 'edit_private_csi_content_entries',
		'edit_published_posts'   => 'edit_published_csi_content_entries',
		'create_posts'           => 'create_csi_content_entries',
	);
}

/**
 * Register reusable content entry post type and section taxonomy.
 *
 * @return void
 */
function culturacsi_content_hub_register_types() {
	$post_type_caps = culturacsi_content_hub_post_type_capabilities();
	$post_type_labels = array(
		'name'               => __( 'Contenuti Riutilizzabili', 'culturacsi' ),
		'singular_name'      => __( 'Contenuto Riutilizzabile', 'culturacsi' ),
		'menu_name'          => __( 'Contenuti Riutilizzabili', 'culturacsi' ),
		'name_admin_bar'     => __( 'Contenuto', 'culturacsi' ),
		'add_new'            => __( 'Aggiungi Nuovo', 'culturacsi' ),
		'add_new_item'       => __( 'Aggiungi Nuovo Contenuto', 'culturacsi' ),
		'edit_item'          => __( 'Modifica Contenuto', 'culturacsi' ),
		'new_item'           => __( 'Nuovo Contenuto', 'culturacsi' ),
		'view_item'          => __( 'Visualizza Contenuto', 'culturacsi' ),
		'search_items'       => __( 'Cerca Contenuti', 'culturacsi' ),
		'not_found'          => __( 'Nessun contenuto trovato.', 'culturacsi' ),
		'not_found_in_trash' => __( 'Nessun contenuto nel cestino.', 'culturacsi' ),
		'all_items'          => __( 'Tutti i Contenuti', 'culturacsi' ),
	);

	register_post_type(
		CULTURACSI_CONTENT_HUB_POST_TYPE,
		array(
			'labels'             => $post_type_labels,
			'public'             => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_admin_bar'  => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-media-document',
			'menu_position'      => 8,
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
			'has_archive'        => false,
			'rewrite'            => false,
			'capability_type'    => array( 'csi_content_entry', 'csi_content_entries' ),
			'capabilities'       => $post_type_caps,
			'map_meta_cap'       => true,
			'exclude_from_search'=> false,
			'publicly_queryable' => true,
			'can_export'         => true,
		)
	);

	$taxonomy_labels = array(
		'name'              => __( 'Sezioni Contenuti', 'culturacsi' ),
		'singular_name'     => __( 'Sezione Contenuti', 'culturacsi' ),
		'search_items'      => __( 'Cerca Sezioni', 'culturacsi' ),
		'all_items'         => __( 'Tutte le Sezioni', 'culturacsi' ),
		'edit_item'         => __( 'Modifica Sezione', 'culturacsi' ),
		'update_item'       => __( 'Aggiorna Sezione', 'culturacsi' ),
		'add_new_item'      => __( 'Aggiungi Nuova Sezione', 'culturacsi' ),
		'new_item_name'     => __( 'Nome Nuova Sezione', 'culturacsi' ),
		'menu_name'         => __( 'Sezioni', 'culturacsi' ),
	);

	register_taxonomy(
		CULTURACSI_CONTENT_HUB_TAXONOMY,
		array( CULTURACSI_CONTENT_HUB_POST_TYPE ),
		array(
			'labels'            => $taxonomy_labels,
			'public'            => true,
			'show_ui'           => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'rewrite'           => false,
			'capabilities'      => array(
				'manage_terms' => 'manage_csi_content_sections',
				'edit_terms'   => 'manage_csi_content_sections',
				'delete_terms' => 'manage_csi_content_sections',
				'assign_terms' => 'edit_csi_content_entries',
			),
		)
	);
}
add_action( 'init', 'culturacsi_content_hub_register_types', 9 );

/**
 * Use the classic editor for content hub entries to keep authoring simple.
 *
 * @param bool   $use_block_editor Current decision.
 * @param string $post_type        Current post type.
 * @return bool
 */
function culturacsi_content_hub_disable_block_editor( $use_block_editor, $post_type ) {
	if ( CULTURACSI_CONTENT_HUB_POST_TYPE === $post_type ) {
		return false;
	}
	return $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', 'culturacsi_content_hub_disable_block_editor', 10, 2 );

/**
 * Add a clearer placeholder for content hub titles.
 *
 * @param string  $title Placeholder text.
 * @param WP_Post $post  Current post.
 * @return string
 */
function culturacsi_content_hub_title_placeholder( $title, $post ) {
	if ( $post instanceof WP_Post && CULTURACSI_CONTENT_HUB_POST_TYPE === $post->post_type ) {
		return __( 'Inserisci il titolo del documento o del servizio', 'culturacsi' );
	}
	return $title;
}
add_filter( 'enter_title_here', 'culturacsi_content_hub_title_placeholder', 10, 2 );

/**
 * Register virtual post types for each section. This
 * allows them to appear as separate "categories" or "Entry Types"
 * in the dynamic source selector of blocks (e.g. Kadence Post Grid).
 *
 * @return void
 */
function culturacsi_content_hub_register_virtual_post_types() {
	$sections = culturacsi_content_hub_sections_map();
	if ( empty( $sections ) ) {
		return;
	}

	foreach ( $sections as $slug => $label ) {
		// WordPress post_type length limit is 20 chars.
		// "ch_s_" (5) + slug max 15. Total 20.
		$pt_name = 'ch_s_' . substr( sanitize_key( (string) $slug ), 0, 15 );
		if ( post_type_exists( $pt_name ) ) {
			continue;
		}

		register_post_type(
			$pt_name,
			array(
				'label'               => $label,
				'public'              => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => true, // Required for Gutenberg/Block source lists
				'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'has_archive'         => false,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'can_export'          => true,
			)
		);
	}
}
add_action( 'init', 'culturacsi_content_hub_register_virtual_post_types', 30 );

/**
 * Map virtual post type queries back to the real Post Type + Taxonomy filter.
 *
 * @param WP_Query $query
 * @return void
 */
function culturacsi_content_hub_resolve_virtual_post_types( $query ) {
	if ( is_admin() && ! wp_doing_ajax() ) {
		return;
	}

	$post_types = $query->get( 'post_type' );
	if ( empty( $post_types ) ) {
		return;
	}

	// Handle single or multiple post types (for query loops)
	$pts = (array) $post_types;
	$target_virtuals = array();
	$has_virtual     = false;

	foreach ( $pts as $pt_name ) {
		if ( 0 === strpos( (string) $pt_name, 'ch_s_' ) ) {
			$target_virtuals[] = $pt_name;
			$has_virtual       = true;
		}
	}

	if ( ! $has_virtual ) {
		return;
	}

	// We found a virtual post type. We must find its original section slug.
	$sections = culturacsi_content_hub_sections_map();
	$resolved_slugs = array();

	foreach ( $target_virtuals as $pt_name ) {
		$stripped = substr( (string) $pt_name, 5 ); // remove "ch_s_"
		foreach ( array_keys( $sections ) as $slug ) {
			if ( 0 === strpos( sanitize_key( (string) $slug ), $stripped ) ) {
				$resolved_slugs[] = $slug;
				break;
			}
		}
	}

	if ( empty( $resolved_slugs ) ) {
		return;
	}

	// Rewrite query to use real post type and specific taxonomy filter.
	$query->set( 'post_type', CULTURACSI_CONTENT_HUB_POST_TYPE );

	$tax_query = $query->get( 'tax_query' );
	if ( ! is_array( $tax_query ) ) {
		$tax_query = array();
	}

	$tax_query[] = array(
		'taxonomy' => CULTURACSI_CONTENT_HUB_TAXONOMY,
		'field'    => 'slug',
		'terms'    => $resolved_slugs,
		'operator' => 'IN',
	);

	$query->set( 'tax_query', $tax_query );
}
add_action( 'pre_get_posts', 'culturacsi_content_hub_resolve_virtual_post_types', 5 );

/**
 * Automatically update a hidden meta field for file extension to enable efficient filtering.
 *
 * @param int $post_id Post ID.
 * @return void
 */
function culturacsi_content_hub_update_post_file_ext( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( get_post_type( $post_id ) !== CULTURACSI_CONTENT_HUB_POST_TYPE ) {
		return;
	}

	$file_id = get_post_meta( $post_id, '_csi_content_hub_file_id', true );
	$ext     = '';

	if ( $file_id > 0 ) {
		$file = get_attached_file( $file_id );
		if ( $file ) {
			$ext = strtoupper( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
		} else {
			// Fallback: get mime if file path not resolved
			$mime = get_post_mime_type( $file_id );
			if ( $mime ) {
				$parts = explode( '/', $mime );
				$ext = strtoupper( end( $parts ) );
			}
		}
	} else {
		$url = get_post_meta( $post_id, '_csi_content_hub_external_url', true );
		if ( $url ) {
			$ext = 'LINK';
		}
	}

	update_post_meta( $post_id, '_csi_content_hub_file_ext', sanitize_text_field( $ext ) );
}
add_action( 'save_post', 'culturacsi_content_hub_update_post_file_ext', 30 );

/**
 * Get all available file extensions from the Content Hub to populate filter dropdowns.
 *
 * @return array
 */
function culturacsi_content_hub_get_available_doc_types() {
	global $wpdb;
	$results = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta} pm 
			INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
			WHERE p.post_type = %s AND p.post_status = 'publish' AND pm.meta_key = '_csi_content_hub_file_ext' AND pm.meta_value != ''",
			CULTURACSI_CONTENT_HUB_POST_TYPE
		)
	);

	if ( empty( $results ) ) {
		// Backfill: if meta query returns nothing, it might be the first time.
		return array( 'PDF' );
	}

	sort( $results );
	return array_filter( $results );
}

/**
 * Ensure Kadence and Core Query Loop blocks also support these virtual post types.
 *
 * @param array $query_args
 * @return array
 */
function culturacsi_content_hub_filter_block_query_vars( $query_args ) {
	$pt = isset( $query_args['post_type'] ) ? $query_args['post_type'] : '';
	if ( is_string( $pt ) && 0 === strpos( $pt, 'ch_s_' ) ) {
		// For blocks, pre_get_posts is sometimes bypassed or called later.
		// We perform an early translation here to be safe.
		$sections = culturacsi_content_hub_sections_map();
		$stripped = substr( $pt, 5 );
		foreach ( array_keys( $sections ) as $slug ) {
			if ( 0 === strpos( sanitize_key( (string) $slug ), $stripped ) ) {
				$query_args['post_type'] = CULTURACSI_CONTENT_HUB_POST_TYPE;
				$query_args['tax_query'][] = array(
					'taxonomy' => CULTURACSI_CONTENT_HUB_TAXONOMY,
					'field'    => 'slug',
					'terms'    => $slug,
				);
				break;
			}
		}
	}
	return $query_args;
}
add_filter( 'query_loop_block_query_vars', 'culturacsi_content_hub_filter_block_query_vars', 10 );
add_filter( 'kadence_blocks_posts_query_args', 'culturacsi_content_hub_filter_block_query_vars', 10 );
add_filter( 'kadence_blocks_pro_posts_grid_query_args', 'culturacsi_content_hub_filter_block_query_vars', 10 );
add_filter( 'kadence_blocks_post_grid_query_args', 'culturacsi_content_hub_filter_block_query_vars', 10 );
