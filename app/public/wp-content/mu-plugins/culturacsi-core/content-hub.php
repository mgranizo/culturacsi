<?php
/**
 * Reusable content hubs (library, services, convenzioni, formazione, etc.).
 *
 * @package CulturaCSI
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CULTURACSI_CONTENT_HUB_POST_TYPE' ) ) {
	define( 'CULTURACSI_CONTENT_HUB_POST_TYPE', 'csi_content_entry' );
}

if ( ! defined( 'CULTURACSI_CONTENT_HUB_TAXONOMY' ) ) {
	define( 'CULTURACSI_CONTENT_HUB_TAXONOMY', 'csi_content_section' );
}

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

/**
 * Keep content hub management reserved to Site Admins only.
 *
 * @return void
 */
function culturacsi_content_hub_grant_role_capabilities() {
	$post_type_caps = array_values( culturacsi_content_hub_post_type_capabilities() );
	$post_type_caps = array_values( array_unique( $post_type_caps ) );

	$admin_caps = array_merge(
		$post_type_caps,
		array( 'manage_csi_content_sections' )
	);

	$admin_role = get_role( 'administrator' );
	if ( $admin_role instanceof WP_Role ) {
		foreach ( $admin_caps as $cap ) {
			$admin_role->add_cap( (string) $cap );
		}
	}

	// Association roles must not manage reusable content sections/entries.
	$restricted_roles = array( 'association_manager', 'association_pending' );
	$caps_to_remove   = array_merge( $post_type_caps, array( 'manage_csi_content_sections' ) );

	// Ensure roles exist as fallback (in case assoc-portal plugin is not active).
	foreach ( $restricted_roles as $role_name ) {
		if ( ! get_role( $role_name ) ) {
			if ( 'association_manager' === $role_name ) {
				add_role( $role_name, 'Association Manager', array( 'read' => true, 'upload_files' => true ) );
			} elseif ( 'association_pending' === $role_name ) {
				add_role( $role_name, 'Association Pending', array( 'read' => true ) );
			}
		}
	}

	foreach ( $restricted_roles as $role_name ) {
		$role = get_role( (string) $role_name );
		if ( ! $role instanceof WP_Role ) {
			continue;
		}
		foreach ( $caps_to_remove as $cap ) {
			$role->remove_cap( (string) $cap );
		}
	}
}
add_action( 'init', 'culturacsi_content_hub_grant_role_capabilities', 11 );

/**
 * Keep the original assoc-portal admin redirect.
 * Association admins should not access wp-admin for content hub management.
 *
 * @return void
 */
function culturacsi_content_hub_override_assoc_portal_redirect() {
	// Intentionally left blank.
}
add_action( 'plugins_loaded', 'culturacsi_content_hub_override_assoc_portal_redirect', 30 );

/**
 * Determine whether current admin request is inside content hub area.
 *
 * @return bool
 */
function culturacsi_content_hub_is_allowed_admin_request() {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( 'csi-content-hub-guide' === $page ) {
		return true;
	}

	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	if ( CULTURACSI_CONTENT_HUB_POST_TYPE === $post_type ) {
		return true;
	}

	$post_id = 0;
	if ( isset( $_GET['post'] ) ) {
		$post_id = absint( wp_unslash( $_GET['post'] ) );
	} elseif ( isset( $_POST['post_ID'] ) ) {
		$post_id = absint( wp_unslash( $_POST['post_ID'] ) );
	}

	if ( $post_id > 0 && CULTURACSI_CONTENT_HUB_POST_TYPE === get_post_type( $post_id ) ) {
		return true;
	}

	return false;
}

/**
 * Redirect association managers out of admin, except for content hub screens.
 *
 * @return void
 */
function culturacsi_content_hub_assoc_portal_redirect() {
	if ( ! current_user_can( 'association_manager' ) || ! is_admin() || wp_doing_ajax() ) {
		return;
	}
	if ( culturacsi_content_hub_is_allowed_admin_request() ) {
		return;
	}
	wp_safe_redirect( home_url( '/area-riservata/' ) );
	exit;
}

/**
 * Optionally seed default reusable sections.
 *
 * Disabled by default: sections should be created only by explicit user action.
 * Enable with: add_filter( 'culturacsi_content_hub_auto_seed_sections', '__return_true' );
 *
 * @return void
 */
function culturacsi_content_hub_seed_default_sections() {
	$auto_seed_enabled = (bool) apply_filters( 'culturacsi_content_hub_auto_seed_sections', false );
	if ( ! $auto_seed_enabled ) {
		return;
	}

	if ( ! taxonomy_exists( CULTURACSI_CONTENT_HUB_TAXONOMY ) ) {
		return;
	}

	$defaults = array(
		'library'             => __( 'Biblioteca', 'culturacsi' ),
		'services'            => __( 'Servizi CulturaCSI', 'culturacsi' ),
		'convenzioni'         => __( 'Convenzioni', 'culturacsi' ),
		'formazione'          => __( 'Formazione', 'culturacsi' ),
		'progetti'            => __( 'Progetti', 'culturacsi' ),
		'infopoint-stranieri' => __( 'Infopoint Stranieri', 'culturacsi' ),
	);

	foreach ( $defaults as $slug => $label ) {
		$existing = term_exists( $slug, CULTURACSI_CONTENT_HUB_TAXONOMY );
		if ( ! empty( $existing ) ) {
			continue;
		}
		wp_insert_term(
			$label,
			CULTURACSI_CONTENT_HUB_TAXONOMY,
			array(
				'slug' => $slug,
			)
		);
	}
}
add_action( 'init', 'culturacsi_content_hub_seed_default_sections', 25 );

/**
 * Ensure default reusable pages exist once.
 *
 * @return void
 */
function culturacsi_content_hub_seed_default_pages() {
	$done_key = 'culturacsi_content_hub_pages_v1';
	if ( '1' === (string) get_option( $done_key, '0' ) ) {
		return;
	}

	if ( ! post_type_exists( 'page' ) ) {
		return;
	}

	$definitions = array(
		'library' => array(
			'title'     => __( 'Biblioteca', 'culturacsi' ),
			'slug'      => 'library',
			'shortcode' => '[culturacsi_library]',
			'parent'    => '',
		),
		'services' => array(
			'title'     => __( 'Servizi CulturaCSI', 'culturacsi' ),
			'slug'      => 'servizi-culturacsi',
			'shortcode' => '[culturacsi_services]',
			'parent'    => '',
		),
		'convenzioni' => array(
			'title'     => __( 'Convenzioni', 'culturacsi' ),
			'slug'      => 'convenzioni',
			'shortcode' => '[culturacsi_convenzioni]',
			'parent'    => 'services',
		),
		'formazione' => array(
			'title'     => __( 'Formazione', 'culturacsi' ),
			'slug'      => 'formazione',
			'shortcode' => '[culturacsi_formazione]',
			'parent'    => 'services',
		),
		'progetti' => array(
			'title'     => __( 'Progetti', 'culturacsi' ),
			'slug'      => 'progetti',
			'shortcode' => '[culturacsi_progetti]',
			'parent'    => '',
		),
		'infopoint-stranieri' => array(
			'title'     => __( 'Infopoint Stranieri', 'culturacsi' ),
			'slug'      => 'infopoint-stranieri',
			'shortcode' => '[culturacsi_infopoint_stranieri]',
			'parent'    => '',
		),
	);

	$page_ids  = array();
	$had_error = false;

	foreach ( $definitions as $key => $definition ) {
		$slug = (string) $definition['slug'];
		if ( '' === trim( $slug ) ) {
			continue;
		}

		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing instanceof WP_Post ) {
			$page_ids[ $key ] = (int) $existing->ID;
			continue;
		}

		$parent_id  = 0;
		$parent_key = (string) $definition['parent'];
		if ( '' !== $parent_key ) {
			if ( isset( $page_ids[ $parent_key ] ) ) {
				$parent_id = (int) $page_ids[ $parent_key ];
			} else {
				$parent_definition = isset( $definitions[ $parent_key ] ) ? $definitions[ $parent_key ] : array();
				$parent_slug       = isset( $parent_definition['slug'] ) ? (string) $parent_definition['slug'] : '';
				if ( '' !== $parent_slug ) {
					$parent_page = get_page_by_path( $parent_slug, OBJECT, 'page' );
					if ( $parent_page instanceof WP_Post ) {
						$parent_id = (int) $parent_page->ID;
					}
				}
			}
		}

		$post_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => (string) $definition['title'],
				'post_name'      => $slug,
				'post_parent'    => $parent_id,
				'post_content'   => (string) $definition['shortcode'],
				'comment_status' => 'closed',
				'ping_status'    => 'closed',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			$had_error = true;
			continue;
		}

		$page_ids[ $key ] = (int) $post_id;
	}

	if ( ! $had_error ) {
		update_option( $done_key, '1', false );
	}
}
add_action( 'init', 'culturacsi_content_hub_seed_default_pages', 26 );

/**
 * Register frontend CSS asset.
 *
 * @return void
 */
function culturacsi_content_hub_register_assets() {
	$css_path = __DIR__ . '/assets/content-hub.css';
	if ( ! file_exists( $css_path ) ) {
		return;
	}

	wp_register_style(
		'culturacsi-content-hub-style',
		plugins_url( 'assets/content-hub.css', __FILE__ ),
		array(),
		'1.0.1'
	);

	$js_path = __DIR__ . '/assets/content-hub.js';
	wp_register_script(
		'culturacsi-content-hub-script',
		plugins_url( 'assets/content-hub.js', __FILE__ ),
		array(),
		'1.0.1',
		true
	);

	wp_localize_script(
		'culturacsi-content-hub-script',
		'CSIContentHubConfig',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'modalNonce' => wp_create_nonce( 'csi_library_modal' ),
		)
	);

	if ( ! is_admin() ) {
		wp_enqueue_style( 'culturacsi-content-hub-style' );
		wp_enqueue_script( 'culturacsi-content-hub-script' );
	}
}
add_action( 'wp_enqueue_scripts', 'culturacsi_content_hub_register_assets', 5 );

/**
 * Enqueue media picker for content hub metabox.
 *
 * @return void
 */
function culturacsi_content_hub_admin_assets() {
	if ( ! is_admin() ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || CULTURACSI_CONTENT_HUB_POST_TYPE !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();

	$style = <<<'CSS'
.post-type-csi_content_entry #title {
	font-size: 1.15rem;
	min-height: 44px;
}

.post-type-csi_content_entry #major-publishing-actions .button,
.post-type-csi_content_entry .wrap .button {
	min-height: 38px;
	padding: 0.35rem 0.8rem;
}

.post-type-csi_content_entry #major-publishing-actions #publish {
	font-size: 1rem;
	font-weight: 700;
	min-height: 44px;
	min-width: 150px;
}

.post-type-csi_content_entry #poststuff .inside p,
.post-type-csi_content_entry #poststuff .inside li,
.post-type-csi_content_entry #poststuff .inside label {
	font-size: 14px;
	line-height: 1.45;
}

.post-type-csi_content_entry .csi-hub-guide {
	background: #f8fbff;
	border: 1px solid #d6e6f8;
	border-radius: 8px;
	padding: 10px 12px;
}

.post-type-csi_content_entry .csi-hub-guide-list {
	list-style: none;
	margin: 0;
	padding: 0;
}

.post-type-csi_content_entry .csi-hub-guide-item {
	align-items: center;
	display: flex;
	gap: 8px;
	margin: 0 0 8px;
}

.post-type-csi_content_entry .csi-hub-guide-item:last-child {
	margin-bottom: 0;
}

.post-type-csi_content_entry .csi-hub-guide-status {
	align-items: center;
	background: #dbe5ee;
	border-radius: 999px;
	display: inline-flex;
	font-weight: 700;
	height: 24px;
	justify-content: center;
	min-width: 24px;
}

.post-type-csi_content_entry .csi-hub-guide-item.is-complete .csi-hub-guide-status {
	background: #15803d;
	color: #fff;
}

.post-type-csi_content_entry .csi-content-hub-download-fields input[type="text"],
.post-type-csi_content_entry .csi-content-hub-download-fields input[type="url"] {
	max-width: 100%;
	min-height: 40px;
}
CSS;

	wp_register_style( 'culturacsi-content-hub-admin', false, array(), '1.0.0' );
	wp_enqueue_style( 'culturacsi-content-hub-admin' );
	wp_add_inline_style( 'culturacsi-content-hub-admin', $style );

	$script = <<<'JS'
(function ($) {
	'use strict';
	$(function () {
		var frame = null;
		var $fileId = $('#csi-content-hub-file-id');
		var $fileName = $('#csi-content-hub-file-name');
		var $current = $('#csi-content-hub-current-file');
		var $externalUrl = $('#csi-content-hub-external-url');
		var $buttonLabel = $('#csi-content-hub-button-label');

		function updateChecklist() {
			var hasTitle = $.trim($('#title').val() || '') !== '';
			var hasSection = $('input[name="tax_input[csi_content_section][]"]:checked').length > 0;
			var hasSummary = $.trim($('#excerpt').val() || '') !== '' || $.trim($('#content').val() || '') !== '';
			var hasMedia = ($.trim($('#_thumbnail_id').val() || '') !== '' && $('#_thumbnail_id').val() !== '-1')
				|| $.trim($fileId.val() || '') !== '' || $.trim($externalUrl.val() || '') !== '';

			var steps = {
				'title': hasTitle,
				'section': hasSection,
				'summary': hasSummary,
				'media': hasMedia
			};

			$('.csi-hub-guide-item').each(function () {
				var $item = $(this);
				var key = $item.data('step');
				var done = !!steps[key];
				$item.toggleClass('is-complete', done);
				$item.find('.csi-hub-guide-status').text(done ? 'OK' : '...');
			});
		}

		function clearFileSelection() {
			$fileId.val('');
			$fileName.val('');
			$current.empty();
			updateChecklist();
		}

		$('.js-csi-content-hub-select-file').on('click', function (event) {
			event.preventDefault();
			if (frame) {
				frame.open();
				return;
			}
			frame = wp.media({
				title: 'Seleziona file da scaricare',
				button: { text: 'Usa questo file' },
				multiple: false
			});

			frame.on('select', function () {
				var attachment = frame.state().get('selection').first().toJSON();
				var label = attachment.filename || attachment.title || ('ID ' + attachment.id);
				$fileId.val(attachment.id || '');
				$fileName.val(label);
				if ($.trim($buttonLabel.val() || '') === '') {
					$buttonLabel.val('Scarica documento');
				}
				if (attachment.url) {
					$current.html('<a href="' + attachment.url + '" target="_blank" rel="noopener noreferrer">' + label + '</a>');
				} else {
					$current.text(label);
				}
				updateChecklist();
			});

			frame.open();
		});

		$('.js-csi-content-hub-remove-file').on('click', function (event) {
			event.preventDefault();
			clearFileSelection();
		});

		$externalUrl.on('blur', function () {
			if ($.trim($externalUrl.val() || '') !== '' && $.trim($buttonLabel.val() || '') === '' && $.trim($fileId.val() || '') === '') {
				$buttonLabel.val('Apri risorsa');
			}
			updateChecklist();
		});

		$(document).on('input change', '#title, #excerpt, #content, #_thumbnail_id, input[name="tax_input[csi_content_section][]"]', updateChecklist);
		updateChecklist();
	});
})(jQuery);
JS;

	wp_add_inline_script( 'jquery-core', $script );
}
add_action( 'admin_enqueue_scripts', 'culturacsi_content_hub_admin_assets' );

/**
 * Register content hub metaboxes.
 *
 * @return void
 */
function culturacsi_content_hub_add_meta_boxes() {
	add_meta_box(
		'csi-content-hub-guide',
		__( 'Guida Rapida (4 Passi)', 'culturacsi' ),
		'culturacsi_content_hub_render_guide_meta_box',
		CULTURACSI_CONTENT_HUB_POST_TYPE,
		'normal',
		'high'
	);

	add_meta_box(
		'csi-content-hub-download',
		__( 'Download e Link', 'culturacsi' ),
		'culturacsi_content_hub_render_meta_box',
		CULTURACSI_CONTENT_HUB_POST_TYPE,
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes', 'culturacsi_content_hub_add_meta_boxes' );

/**
 * Simplify the edit screen by removing technical metaboxes for this post type.
 *
 * @return void
 */
function culturacsi_content_hub_simplify_edit_screen() {
	remove_meta_box( 'slugdiv', CULTURACSI_CONTENT_HUB_POST_TYPE, 'normal' );
	remove_meta_box( 'trackbacksdiv', CULTURACSI_CONTENT_HUB_POST_TYPE, 'normal' );
	remove_meta_box( 'commentstatusdiv', CULTURACSI_CONTENT_HUB_POST_TYPE, 'normal' );
	remove_meta_box( 'commentsdiv', CULTURACSI_CONTENT_HUB_POST_TYPE, 'normal' );
	remove_meta_box( 'authordiv', CULTURACSI_CONTENT_HUB_POST_TYPE, 'normal' );
}
add_action( 'add_meta_boxes_' . CULTURACSI_CONTENT_HUB_POST_TYPE, 'culturacsi_content_hub_simplify_edit_screen', 30 );

/**
 * Render quick guide and completeness checklist.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function culturacsi_content_hub_render_guide_meta_box( $post ) {
	$has_title = '' !== trim( (string) $post->post_title );
	$has_summary = '' !== trim( (string) $post->post_excerpt ) || '' !== trim( (string) $post->post_content );
	$has_thumbnail = has_post_thumbnail( $post );
	$file_id = (int) get_post_meta( $post->ID, '_csi_content_hub_file_id', true );
	$external_url = (string) get_post_meta( $post->ID, '_csi_content_hub_external_url', true );
	$has_link = $file_id > 0 || '' !== trim( $external_url );

	$terms = wp_get_post_terms(
		$post->ID,
		CULTURACSI_CONTENT_HUB_TAXONOMY,
		array( 'fields' => 'ids' )
	);
	$has_section = ! is_wp_error( $terms ) && ! empty( $terms );

	$steps = array(
		'title'   => array(
			'done'  => $has_title,
			'label' => __( 'Titolo chiaro del contenuto', 'culturacsi' ),
		),
		'section' => array(
			'done'  => $has_section,
			'label' => __( 'Seleziona la sezione (Biblioteca, Servizi, Convenzioni...)', 'culturacsi' ),
		),
		'summary' => array(
			'done'  => $has_summary,
			'label' => __( 'Inserisci un breve testo descrittivo', 'culturacsi' ),
		),
		'media'   => array(
			'done'  => ( $has_thumbnail || $has_link ),
			'label' => __( 'Aggiungi immagine e/o documento/link da aprire', 'culturacsi' ),
		),
	);
	?>
	<div class="csi-hub-guide">
		<p><strong><?php esc_html_e( 'Prima di pubblicare, completa questi passaggi:', 'culturacsi' ); ?></strong></p>
		<ul class="csi-hub-guide-list">
			<?php foreach ( $steps as $step_key => $step_data ) : ?>
				<li class="csi-hub-guide-item<?php echo ! empty( $step_data['done'] ) ? ' is-complete' : ''; ?>" data-step="<?php echo esc_attr( $step_key ); ?>">
					<span class="csi-hub-guide-status"><?php echo ! empty( $step_data['done'] ) ? 'OK' : '...'; ?></span>
					<span><?php echo esc_html( (string) $step_data['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="description" style="margin-top:10px;">
			<?php esc_html_e( 'Suggerimento: per la Biblioteca carica sempre un file PDF o un link esterno.', 'culturacsi' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Render metabox fields for download/external URL.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function culturacsi_content_hub_render_meta_box( $post ) {
	$file_id      = (int) get_post_meta( $post->ID, '_csi_content_hub_file_id', true );
	$external_url = (string) get_post_meta( $post->ID, '_csi_content_hub_external_url', true );
	$button_label = (string) get_post_meta( $post->ID, '_csi_content_hub_button_label', true );

	$file_label = '';
	$file_url   = '';
	if ( $file_id > 0 ) {
		$attachment = get_post( $file_id );
		$file_label = $attachment instanceof WP_Post ? $attachment->post_title : '';
		if ( '' === trim( $file_label ) ) {
			$file_label = (string) wp_basename( (string) get_attached_file( $file_id ) );
		}
		$file_url = (string) wp_get_attachment_url( $file_id );
	}

	wp_nonce_field( 'csi_content_hub_save', 'csi_content_hub_nonce' );
	?>
	<div class="csi-content-hub-download-fields">
		<p>
			<label for="csi-content-hub-file-name"><strong><?php esc_html_e( '1) Documento da scaricare', 'culturacsi' ); ?></strong></label><br>
			<input type="hidden" id="csi-content-hub-file-id" name="csi_content_hub_file_id" value="<?php echo esc_attr( (string) $file_id ); ?>">
			<input type="text" id="csi-content-hub-file-name" class="regular-text" value="<?php echo esc_attr( $file_label ); ?>" readonly>
			<button type="button" class="button button-primary js-csi-content-hub-select-file"><?php esc_html_e( 'Scegli documento', 'culturacsi' ); ?></button>
			<button type="button" class="button js-csi-content-hub-remove-file"><?php esc_html_e( 'Rimuovi documento', 'culturacsi' ); ?></button>
		</p>
		<p id="csi-content-hub-current-file">
			<?php if ( '' !== $file_url ) : ?>
				<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $file_label ); ?></a>
			<?php endif; ?>
		</p>
		<p>
			<label for="csi-content-hub-external-url"><strong><?php esc_html_e( '2) Oppure link esterno (facoltativo)', 'culturacsi' ); ?></strong></label><br>
			<input type="url" id="csi-content-hub-external-url" name="csi_content_hub_external_url" class="large-text" value="<?php echo esc_attr( $external_url ); ?>" placeholder="https://">
		</p>
		<p>
			<label for="csi-content-hub-button-label"><strong><?php esc_html_e( '3) Testo del pulsante (facoltativo)', 'culturacsi' ); ?></strong></label><br>
			<input type="text" id="csi-content-hub-button-label" name="csi_content_hub_button_label" class="regular-text" value="<?php echo esc_attr( $button_label ); ?>" placeholder="<?php esc_attr_e( 'Es: Scarica Documento', 'culturacsi' ); ?>">
		</p>
		<p class="description">
			<?php esc_html_e( 'Se inserisci sia file che URL esterno, verra usato il file scaricabile.', 'culturacsi' ); ?>
		</p>
	</div>
	<?php
}

/**
 * Save metabox values for content hub entries.
 *
 * @param int     $post_id Current post ID.
 * @param WP_Post $post    Current post object.
 * @return void
 */
function culturacsi_content_hub_save_meta( $post_id, $post ) {
	if ( ! $post instanceof WP_Post || CULTURACSI_CONTENT_HUB_POST_TYPE !== $post->post_type ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['csi_content_hub_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csi_content_hub_nonce'] ) ), 'csi_content_hub_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$file_id = isset( $_POST['csi_content_hub_file_id'] ) ? absint( wp_unslash( $_POST['csi_content_hub_file_id'] ) ) : 0;
	if ( $file_id > 0 ) {
		update_post_meta( $post_id, '_csi_content_hub_file_id', $file_id );
	} else {
		delete_post_meta( $post_id, '_csi_content_hub_file_id' );
	}

	$external_url = isset( $_POST['csi_content_hub_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['csi_content_hub_external_url'] ) ) : '';
	if ( '' !== trim( $external_url ) ) {
		update_post_meta( $post_id, '_csi_content_hub_external_url', $external_url );
	} else {
		delete_post_meta( $post_id, '_csi_content_hub_external_url' );
	}

	$button_label = isset( $_POST['csi_content_hub_button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['csi_content_hub_button_label'] ) ) : '';
	if ( '' !== trim( $button_label ) ) {
		update_post_meta( $post_id, '_csi_content_hub_button_label', $button_label );
	} else {
		delete_post_meta( $post_id, '_csi_content_hub_button_label' );
	}
}
add_action( 'save_post_' . CULTURACSI_CONTENT_HUB_POST_TYPE, 'culturacsi_content_hub_save_meta', 10, 2 );

/**
 * Add custom columns to content hub admin table.
 *
 * @param array<string,string> $columns Current columns.
 * @return array<string,string>
 */
function culturacsi_content_hub_admin_columns( $columns ) {
	$updated = array();
	foreach ( $columns as $key => $label ) {
		$updated[ $key ] = $label;
		if ( 'title' === $key ) {
			$updated['csi_content_hub_download'] = __( 'Download', 'culturacsi' );
		}
	}
	return $updated;
}
add_filter( 'manage_' . CULTURACSI_CONTENT_HUB_POST_TYPE . '_posts_columns', 'culturacsi_content_hub_admin_columns', 20 );

/**
 * Render custom admin column values.
 *
 * @param string $column  Column key.
 * @param int    $post_id Current post ID.
 * @return void
 */
function culturacsi_content_hub_admin_column_content( $column, $post_id ) {
	if ( 'csi_content_hub_download' !== $column ) {
		return;
	}

	$file_id      = (int) get_post_meta( $post_id, '_csi_content_hub_file_id', true );
	$external_url = (string) get_post_meta( $post_id, '_csi_content_hub_external_url', true );

	if ( $file_id > 0 ) {
		$url  = (string) wp_get_attachment_url( $file_id );
		$name = (string) wp_basename( (string) get_attached_file( $file_id ) );
		if ( '' === trim( $name ) ) {
			$name = __( 'File allegato', 'culturacsi' );
		}
		if ( '' !== trim( $url ) ) {
			echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>';
			return;
		}
		echo esc_html( $name );
		return;
	}

	if ( '' !== trim( $external_url ) ) {
		echo '<a href="' . esc_url( $external_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'URL esterno', 'culturacsi' ) . '</a>';
		return;
	}

	echo '<span style="opacity:.7;">' . esc_html__( 'Nessuno', 'culturacsi' ) . '</span>';
}
add_action( 'manage_' . CULTURACSI_CONTENT_HUB_POST_TYPE . '_posts_custom_column', 'culturacsi_content_hub_admin_column_content', 10, 2 );

/**
 * Add a dedicated success flag to content hub redirects after save.
 *
 * @param string $location Redirect URL.
 * @param int    $post_id  Post ID.
 * @return string
 */
function culturacsi_content_hub_redirect_location( $location, $post_id ) {
	if ( CULTURACSI_CONTENT_HUB_POST_TYPE !== get_post_type( $post_id ) ) {
		return $location;
	}
	return add_query_arg( 'csi_content_hub_saved', '1', $location );
}
add_filter( 'redirect_post_location', 'culturacsi_content_hub_redirect_location', 10, 2 );

/**
 * Show a clear save notice with the next action for editors.
 *
 * @return void
 */
function culturacsi_content_hub_admin_save_notice() {
	if ( ! is_admin() || empty( $_GET['csi_content_hub_saved'] ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || CULTURACSI_CONTENT_HUB_POST_TYPE !== $screen->post_type ) {
		return;
	}

	$list_url = admin_url( 'edit.php?post_type=' . CULTURACSI_CONTENT_HUB_POST_TYPE );
	echo '<div class="notice notice-success is-dismissible"><p>';
	echo esc_html__( 'Contenuto salvato correttamente. Prossimo passo: verifica che la sezione sia giusta e che il documento sia apribile.', 'culturacsi' );
	echo ' <a href="' . esc_url( $list_url ) . '"><strong>' . esc_html__( 'Apri elenco contenuti', 'culturacsi' ) . '</strong></a>';
	echo '</p></div>';
}
add_action( 'admin_notices', 'culturacsi_content_hub_admin_save_notice' );

/**
 * Add an easy section filter above the content hub list.
 *
 * @param string $post_type Current post type.
 * @param string $which     Position in list table.
 * @return void
 */
function culturacsi_content_hub_admin_section_filter( $post_type, $which = 'top' ) {
	if ( CULTURACSI_CONTENT_HUB_POST_TYPE !== $post_type || 'top' !== $which ) {
		return;
	}

	$taxonomy = CULTURACSI_CONTENT_HUB_TAXONOMY;
	$terms    = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return;
	}

	$selected = isset( $_GET[ $taxonomy ] ) ? sanitize_text_field( wp_unslash( $_GET[ $taxonomy ] ) ) : '';
	echo '<label class="screen-reader-text" for="filter-by-csi-section">' . esc_html__( 'Filtra per sezione', 'culturacsi' ) . '</label>';
	echo '<select id="filter-by-csi-section" name="' . esc_attr( $taxonomy ) . '">';
	echo '<option value="">' . esc_html__( 'Tutte le sezioni', 'culturacsi' ) . '</option>';
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		echo '<option value="' . esc_attr( (string) $term->slug ) . '"' . selected( $selected, (string) $term->slug, false ) . '>' . esc_html( (string) $term->name ) . '</option>';
	}
	echo '</select>';
}
add_action( 'restrict_manage_posts', 'culturacsi_content_hub_admin_section_filter', 10, 2 );

/**
 * Simplify update messages for content hub entries.
 *
 * @param array<string,mixed> $messages Existing messages.
 * @return array<string,mixed>
 */
function culturacsi_content_hub_updated_messages( $messages ) {
	$messages[ CULTURACSI_CONTENT_HUB_POST_TYPE ] = array(
		0  => '',
		1  => __( 'Contenuto aggiornato.', 'culturacsi' ),
		2  => __( 'Campo personalizzato aggiornato.', 'culturacsi' ),
		3  => __( 'Campo personalizzato eliminato.', 'culturacsi' ),
		4  => __( 'Contenuto aggiornato.', 'culturacsi' ),
		5  => isset( $_GET['revision'] ) ? __( 'Versione precedente ripristinata.', 'culturacsi' ) : false,
		6  => __( 'Contenuto pubblicato.', 'culturacsi' ),
		7  => __( 'Contenuto salvato.', 'culturacsi' ),
		8  => __( 'Contenuto inviato per revisione.', 'culturacsi' ),
		9  => __( 'Contenuto pianificato.', 'culturacsi' ),
		10 => __( 'Bozza aggiornata.', 'culturacsi' ),
	);
	return $messages;
}
add_filter( 'post_updated_messages', 'culturacsi_content_hub_updated_messages' );

/**
 * Parse truthy shortcode values.
 *
 * @param mixed $value Input value.
 * @return bool
 */
function culturacsi_content_hub_is_truthy( $value ) {
	$normalized = strtolower( trim( (string) $value ) );
	return in_array( $normalized, array( '1', 'true', 'yes', 'on', 'si' ), true );
}

/**
 * Return all content hub sections as slug => label.
 *
 * @return array<string,string>
 */
function culturacsi_content_hub_sections_map() {
	if ( ! taxonomy_exists( CULTURACSI_CONTENT_HUB_TAXONOMY ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => CULTURACSI_CONTENT_HUB_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) || empty( $terms ) ) {
		return array();
	}

	$map = array();
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$slug = sanitize_title( (string) $term->slug );
		if ( '' === $slug ) {
			continue;
		}
		$map[ $slug ] = sanitize_text_field( (string) $term->name );
	}
	return $map;
}

/**
 * Normalize a section identifier (e.g. section_library) to a section slug.
 *
 * @param string $raw_identifier Raw section identifier.
 * @return string
 */
function culturacsi_content_hub_section_slug_from_identifier( $raw_identifier ) {
	$value = sanitize_key( trim( (string) $raw_identifier ) );
	if ( '' === $value ) {
		return '';
	}

	if ( 0 === strpos( $value, 'csi_section_' ) ) {
		$value = substr( $value, strlen( 'csi_section_' ) );
	} elseif ( 0 === strpos( $value, 'section_' ) ) {
		$value = substr( $value, strlen( 'section_' ) );
	}

	$value = str_replace( '_', '-', $value );
	return sanitize_title( $value );
}

/**
 * Build public identifiers for each section.
 *
 * @return array<string,array<string,string>>
 */
function culturacsi_content_hub_section_identifiers() {
	$sections = culturacsi_content_hub_sections_map();
	$out      = array();
	foreach ( $sections as $slug => $label ) {
		$slug_key = sanitize_key( (string) $slug );
		if ( '' === $slug_key ) {
			continue;
		}
		$out[ $slug ] = array(
			'slug'       => $slug,
			'label'      => $label,
			'identifier' => 'section_' . $slug_key,
			'shortcode'  => 'culturacsi_section_' . str_replace( '-', '_', $slug_key ),
		);
	}
	return $out;
}

/**
 * Parse section filters from shortcode attributes.
 *
 * Supports explicit slugs via "section" and stable identifiers via
 * "identifier"/"id" (e.g. section_library, csi_section_progetti).
 *
 * @param array<string,mixed> $atts Shortcode attributes.
 * @return array<int,string>
 */
function culturacsi_content_hub_parse_sections_from_atts( $atts ) {
	$sections_map = culturacsi_content_hub_sections_map();
	$label_lookup = array();
	foreach ( $sections_map as $slug => $label ) {
		$label_lookup[ sanitize_title( (string) $label ) ] = $slug;
	}

	$raw_values = array();
	foreach ( array( 'section', 'identifier', 'id' ) as $key ) {
		if ( empty( $atts[ $key ] ) ) {
			continue;
		}
		$raw_values = array_merge(
			$raw_values,
			array_map(
				'trim',
				explode( ',', (string) $atts[ $key ] )
			)
		);
	}

	$resolved = array();
	foreach ( $raw_values as $raw_item ) {
		$item = trim( (string) $raw_item );
		if ( '' === $item ) {
			continue;
		}

		$slug = '';
		$by_identifier = culturacsi_content_hub_section_slug_from_identifier( $item );
		if ( '' !== $by_identifier ) {
			$slug = isset( $sections_map[ $by_identifier ] ) ? $by_identifier : $by_identifier;
		}

		if ( '' === $slug ) {
			$normalized = sanitize_title( $item );
			if ( isset( $sections_map[ $normalized ] ) ) {
				$slug = $normalized;
			} elseif ( isset( $label_lookup[ $normalized ] ) ) {
				$slug = $label_lookup[ $normalized ];
			}
		}

		if ( '' === $slug ) {
			continue;
		}
		$resolved[ $slug ] = $slug;
	}

	return array_values( $resolved );
}

/**
 * Render reusable content hub listing.
 *
 * @param array<string,mixed> $atts Shortcode attributes.
 * @return string
 */
function culturacsi_content_hub_shortcode( $atts = array() ) {
	if ( ! post_type_exists( CULTURACSI_CONTENT_HUB_POST_TYPE ) ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'section'        => '',
			'identifier'     => '',
			'id'             => '',
			'title'          => '',
			'per_page'       => 12,
			'search'         => 'yes',
			'downloads_only' => 'no',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'show_image'     => 'yes',
			'empty_message'  => __( 'Nessun contenuto trovato.', 'culturacsi' ),
			'instance'       => '',
			'is_library'     => 'no',
		),
		$atts,
		'culturacsi_content_hub'
	);

	$theme_news_css = get_template_directory() . '/css/news.css';
	if ( file_exists( $theme_news_css ) ) {
		wp_enqueue_style(
			'culturacsi-news-style',
			get_template_directory_uri() . '/css/news.css',
			array(),
			(string) filemtime( $theme_news_css )
		);
	}
	wp_enqueue_style( 'culturacsi-content-hub-style' );
	wp_enqueue_script( 'culturacsi-content-hub-script' );

	$sections = culturacsi_content_hub_parse_sections_from_atts( $atts );

	$instance_raw = trim( (string) $atts['instance'] );
	$instance_seed = 'hub';
	if ( '' !== $instance_raw ) {
		$instance_seed = $instance_raw;
	} elseif ( ! empty( $sections ) ) {
		$instance_seed = implode( '-', $sections );
	} elseif ( '' !== trim( (string) $atts['identifier'] ) ) {
		$instance_seed = (string) $atts['identifier'];
	} elseif ( '' !== trim( (string) $atts['id'] ) ) {
		$instance_seed = (string) $atts['id'];
	}
	$instance = sanitize_key( $instance_seed );
	if ( '' === $instance ) {
		$instance = 'hub';
	}

	$query_var_q    = 'hub_q_' . $instance;
	$query_var_page = 'hub_page_' . $instance;
	$query_text     = isset( $_GET[ $query_var_q ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query_var_q ] ) ) : ( isset( $_GET['ch_q'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_q'] ) ) : ( isset( $_GET['news_q'] ) ? sanitize_text_field( wp_unslash( $_GET['news_q'] ) ) : '' ) );
	$paged          = isset( $_GET[ $query_var_page ] ) ? max( 1, absint( wp_unslash( $_GET[ $query_var_page ] ) ) ) : 1;


	$per_page = max( 1, absint( $atts['per_page'] ) );
	$orderby  = sanitize_key( (string) $atts['orderby'] );
	if ( ! in_array( $orderby, array( 'date', 'title', 'modified', 'menu_order', 'rand' ), true ) ) {
		$orderby = 'date';
	}

	$order = strtoupper( sanitize_key( (string) $atts['order'] ) );
	if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
		$order = 'DESC';
	}

	$args = array(
		'post_type'      => CULTURACSI_CONTENT_HUB_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => $orderby,
		'order'          => $order,
	);

	if ( '' !== trim( $query_text ) ) {
		$args['s'] = $query_text;
	}

	$filters            = culturacsi_content_hub_filters_from_request();
	$has_global_filters = ( '' !== $filters['q'] || '' !== $filters['date'] || $filters['author'] > 0 || $filters['assoc'] > 0 || '' !== $filters['doc_type'] );
	if ( $has_global_filters ) {
		$global_section = $filters['section'];
		$match_section  = ( '' === $global_section || in_array( $global_section, $sections, true ) );

		if ( $match_section ) {
			$args = culturacsi_content_hub_apply_filters_to_query_vars( $args, $filters );
		}
	}


	if ( ! empty( $sections ) ) {
		if ( ! isset( $args['tax_query'] ) || ! is_array( $args['tax_query'] ) ) {
			$args['tax_query'] = array();
		}
		$args['tax_query'][] = array(
			'taxonomy' => CULTURACSI_CONTENT_HUB_TAXONOMY,
			'field'    => 'slug',
			'terms'    => array_values( $sections ),
		);
	}

	if ( culturacsi_content_hub_is_truthy( $atts['downloads_only'] ) ) {
		if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_csi_content_hub_file_id',
				'value'   => 0,
				'type'    => 'NUMERIC',
				'compare' => '>',
			),
			array(
				'key'     => '_csi_content_hub_external_url',
				'value'   => '',
				'compare' => '!=',
			),
		);
	}

	$query = new WP_Query( $args );

	$explicit_title = trim( (string) $atts['title'] );
	$title          = $explicit_title;
	if ( '' === $title && 1 === count( $sections ) ) {
		$term = get_term_by( 'slug', reset( $sections ), CULTURACSI_CONTENT_HUB_TAXONOMY );
		if ( $term instanceof WP_Term ) {
			$title = (string) $term->name;
		}
	}

	$show_search = culturacsi_content_hub_is_truthy( $atts['search'] );
	$show_image  = culturacsi_content_hub_is_truthy( $atts['show_image'] );

	$preserved = array();
	foreach ( $_GET as $key => $value ) {
		if ( $query_var_q === $key || $query_var_page === $key ) {
			continue;
		}
		if ( is_array( $value ) ) {
			continue;
		}
		$preserved[ (string) $key ] = (string) $value;
	}

	ob_start();
	?>
	<section class="csi-content-hub csi-content-hub-<?php echo esc_attr( $instance ); ?>">
		<?php if ( '' !== $title || $show_search ) : ?>
			<header class="csi-content-hub-header page-header">
				<?php if ( '' !== $title ) : ?>
					<h2 class="csi-content-hub-title page-title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( $show_search ) : ?>
					<form method="get" class="csi-content-hub-search" action="<?php echo esc_url( remove_query_arg( array( $query_var_q, $query_var_page ) ) ); ?>">
						<?php foreach ( $preserved as $key => $value ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
						<?php endforeach; ?>
						<label class="screen-reader-text" for="<?php echo esc_attr( $query_var_q ); ?>"><?php esc_html_e( 'Cerca', 'culturacsi' ); ?></label>
						<input type="search" id="<?php echo esc_attr( $query_var_q ); ?>" name="<?php echo esc_attr( $query_var_q ); ?>" value="<?php echo esc_attr( $query_text ); ?>" placeholder="<?php esc_attr_e( 'Cerca contenuti...', 'culturacsi' ); ?>">
						<button type="submit"><?php esc_html_e( 'Cerca', 'culturacsi' ); ?></button>
					</form>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<div class="csi-content-hub-grid news-grid">
			<?php if ( $query->have_posts() ) : ?>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$post_id      = get_the_ID();
					$file_id      = (int) get_post_meta( $post_id, '_csi_content_hub_file_id', true );
					$external_url = (string) get_post_meta( $post_id, '_csi_content_hub_external_url', true );
					$button_label = trim( (string) get_post_meta( $post_id, '_csi_content_hub_button_label', true ) );

					$link_url   = get_permalink( $post_id );
					$link_class = 'read-more';
					$link_attrs = '';
					$file_note  = '';

					$is_library_entry = ( culturacsi_content_hub_is_truthy( $atts['is_library'] ) ) || has_term( 'library', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id ) || has_term( 'biblioteca', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id ) || has_term( 'document-library', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id );
					$is_formazione    = has_term( 'formazione', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id );
					$is_progetti      = has_term( 'progetti', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id );
					$is_convenzioni   = has_term( 'convenzioni', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id );
					$is_external_hub  = ! $is_library_entry && ( $is_formazione || $is_progetti || $is_convenzioni );

					$attachment_url = $file_id > 0 ? (string) wp_get_attachment_url( $file_id ) : '';
					if ( $file_id > 0 && '' !== $attachment_url ) {
						$file_path = (string) get_attached_file( $file_id );
						if ( '' !== trim( $file_path ) && file_exists( $file_path ) ) {
							$file_note = size_format( (float) filesize( $file_path ) );
						}
					}

					if ( $is_library_entry ) {
						// Library: opens a modal
						$link_class .= ' csi-library-trigger';
						$link_attrs  = ' data-csi-modal-trigger="library"';
						$modal_data  = array(
							'title'    => get_the_title(),
							'excerpt'  => get_the_excerpt(),
							'content'  => apply_filters( 'the_content', get_the_content() ),
							'fileUrl'  => $attachment_url,
							'fileNote' => $file_note,
							'imageUrl' => has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'medium_large' ) : '',
						);
						$link_attrs .= ' data-csi-modal-content="' . esc_attr( wp_json_encode( $modal_data ) ) . '"';
						if ( '' === $button_label ) {
							$button_label = __( 'Dettagli', 'culturacsi' );
						}
					} elseif ( $is_external_hub && '' !== trim( $external_url ) ) {
						// Formazione, Progetti, Convenzioni: Prioritize external URL (like Notizie)
						$link_url   = $external_url;
						$link_attrs = ' target="_blank" rel="noopener noreferrer"';
						if ( '' === $button_label ) {
							$button_label = __( 'Apri Risorsa', 'culturacsi' );
						}
					} elseif ( $file_id > 0 && '' !== $attachment_url ) {
						// Fallback to File if no external URL or not external hub
						$link_url   = $attachment_url;
						$link_attrs = ' download';
						if ( '' === $button_label ) {
							$button_label = __( 'Scarica Documento', 'culturacsi' );
						}
					} elseif ( '' !== trim( $external_url ) ) {
						// Final fallback to external URL
						$link_url   = $external_url;
						$link_attrs = ' target="_blank" rel="noopener noreferrer"';
						if ( '' === $button_label ) {
							$button_label = __( 'Apri Risorsa', 'culturacsi' );
						}
					} elseif ( '' === $button_label ) {
						$button_label = __( 'Leggi di piu', 'culturacsi' );
					}
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'csi-content-hub-item news-item' ); ?>>
						<div class="news-item-inner">
							<?php if ( $show_image && has_post_thumbnail() ) : ?>
								<div class="news-item-image csi-content-hub-image">
									<a href="<?php echo esc_url( $link_url ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
										<?php the_post_thumbnail( 'medium_large' ); ?>
									</a>
								</div>
							<?php endif; ?>

							<div class="news-item-content csi-content-hub-content">
								<header class="entry-header">
									<h3 class="entry-title">
										<a href="<?php echo esc_url( $link_url ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
											<?php the_title(); ?>
										</a>
									</h3>
								</header>
								<div class="entry-summary">
									<?php echo wp_kses_post( wpautop( get_the_excerpt() ) ); ?>
								</div>
								<?php if ( '' !== $file_note ) : ?>
									<p class="csi-content-hub-file-note"><?php echo esc_html( $file_note ); ?></p>
								<?php endif; ?>
								<a class="<?php echo esc_attr( $link_class ); ?>" href="<?php echo esc_url( $link_url ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $button_label ); ?></a>
							</div>
						</div>
					</article>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p class="csi-content-hub-empty"><?php echo esc_html( (string) $atts['empty_message'] ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $query->max_num_pages > 1 ) : ?>
			<?php
			$pagination = paginate_links(
				array(
					'base'      => add_query_arg( $query_var_page, '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $query->max_num_pages,
					'type'      => 'list',
					'prev_text' => __( 'Prec', 'culturacsi' ),
					'next_text' => __( 'Succ', 'culturacsi' ),
				)
			);
			?>
			<?php if ( is_string( $pagination ) && '' !== trim( $pagination ) ) : ?>
				<nav class="csi-content-hub-pagination the-posts-pagination" aria-label="<?php esc_attr_e( 'Paginazione contenuti', 'culturacsi' ); ?>">
					<?php echo wp_kses_post( $pagination ); ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'culturacsi_content_hub', 'culturacsi_content_hub_shortcode' );

/**
 * Get content hub search filters from the request.
 *
 * @return array
 */
function culturacsi_content_hub_filters_from_request() {
	static $filters = null;
	if ( null !== $filters ) {
		return $filters;
	}
	$filters = array(
		'q'          => isset( $_GET['ch_q'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_q'] ) ) : ( isset( $_GET['news_q'] ) ? sanitize_text_field( wp_unslash( $_GET['news_q'] ) ) : ( isset( $_GET['a_q'] ) ? sanitize_text_field( wp_unslash( $_GET['a_q'] ) ) : '' ) ),
		'date'       => isset( $_GET['ch_date'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_date'] ) ) : ( isset( $_GET['news_date'] ) ? sanitize_text_field( wp_unslash( $_GET['news_date'] ) ) : '' ),
		'author'     => isset( $_GET['ch_author'] ) ? absint( $_GET['ch_author'] ) : ( isset( $_GET['news_author'] ) ? absint( $_GET['news_author'] ) : 0 ),
		'assoc'      => isset( $_GET['ch_assoc'] ) ? absint( $_GET['ch_assoc'] ) : ( isset( $_GET['news_assoc'] ) ? absint( $_GET['news_assoc'] ) : 0 ),
		'section'    => isset( $_GET['ch_section'] ) ? sanitize_title( wp_unslash( $_GET['ch_section'] ) ) : '',
		'doc_type'   => isset( $_GET['ch_doc_type'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_doc_type'] ) ) : '',
	);
	return $filters;
}

/**
 * Apply content-hub filters to a query vars array.
 *
 * @param array $query_vars
 * @param array $filters
 * @return array
 */
function culturacsi_content_hub_apply_filters_to_query_vars( $query_vars, $filters ) {
	if ( '' !== $filters['q'] ) {
		$query_vars['s'] = $filters['q'];
	}

	if ( $filters['author'] > 0 ) {
		$query_vars['author'] = (int) $filters['author'];
	}

	if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
		$query_vars['date_query'] = array(
			array(
				'year'     => (int) $matches[1],
				'monthnum' => (int) $matches[2],
			),
		);
	}

	if ( $filters['assoc'] > 0 ) {
		$query_vars['meta_query'][] = array(
			'key'   => 'organizer_association_id',
			'value' => (string) $filters['assoc'],
		);
	}

	if ( '' !== $filters['section'] ) {
		$query_vars['tax_query'][] = array(
			'taxonomy' => 'csi_content_section',
			'field'    => 'slug',
			'terms'    => $filters['section'],
		);
	}

	if ( '' !== $filters['doc_type'] ) {
		$query_vars['meta_query'][] = array(
			'key'   => '_csi_content_hub_file_ext',
			'value' => (string) $filters['doc_type'],
		);
	}

	return $query_vars;
}

/**
 * Filter the main query for content entries based on URL parameters.
 *
 * @param WP_Query $query
 * @return void
 */
function culturacsi_content_hub_search_filter_main_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$filters = culturacsi_content_hub_filters_from_request();
	$has_filters = ( '' !== $filters['q'] ) || ( '' !== $filters['date'] ) || ( $filters['author'] > 0 ) || ( $filters['assoc'] > 0 ) || ( '' !== $filters['section'] ) || ( '' !== $filters['doc_type'] );
	if ( ! $has_filters ) {
		return;
	}

	$post_type = $query->get( 'post_type' );
	$is_target =
		( is_string( $post_type ) && CULTURACSI_CONTENT_HUB_POST_TYPE === $post_type ) ||
		( is_array( $post_type ) && in_array( CULTURACSI_CONTENT_HUB_POST_TYPE, $post_type, true ) );

	if ( ! $is_target ) {
		return;
	}

	$query_vars = culturacsi_content_hub_apply_filters_to_query_vars( $query->query_vars, $filters );
	foreach ( $query_vars as $key => $value ) {
		$query->set( $key, $value );
	}
}
add_action( 'pre_get_posts', 'culturacsi_content_hub_search_filter_main_query', 20 );

/**
 * Filter Query Loop blocks for content entries.
 *
 * @param array    $query
 * @param WP_Block $block
 * @param int      $page
 * @return array
 */
function culturacsi_content_hub_search_filter_query_loop_vars( $query, $block = null, $page = null ) {
	if ( is_admin() ) {
		return $query;
	}

	$filters = culturacsi_content_hub_filters_from_request();
	$has_filters = ( '' !== $filters['q'] ) || ( '' !== $filters['date'] ) || ( $filters['author'] > 0 ) || ( $filters['assoc'] > 0 ) || ( '' !== $filters['section'] ) || ( '' !== $filters['doc_type'] );
	if ( ! $has_filters ) {
		return $query;
	}

	$post_type = isset( $query['post_type'] ) ? $query['post_type'] : 'post';
	$is_target = false;
	if ( is_string( $post_type ) ) {
		$is_target = ( CULTURACSI_CONTENT_HUB_POST_TYPE === $post_type || 0 === strpos( $post_type, 'ch_s_' ) );
	} elseif ( is_array( $post_type ) ) {
		foreach ( (array) $post_type as $pt ) {
			if ( CULTURACSI_CONTENT_HUB_POST_TYPE === $pt || 0 === strpos( (string) $pt, 'ch_s_' ) ) {
				$is_target = true;
				break;
			}
		}
	}

	if ( ! $is_target ) {
		return $query;
	}

	return culturacsi_content_hub_apply_filters_to_query_vars( (array) $query, $filters );
}


add_filter( 'query_loop_block_query_vars', 'culturacsi_content_hub_search_filter_query_loop_vars', 20, 3 );
add_filter( 'kadence_blocks_post_grid_query_args', 'culturacsi_content_hub_search_filter_query_loop_vars', 20, 2 );
add_filter( 'kadence_blocks_posts_query_args', 'culturacsi_content_hub_search_filter_query_loop_vars', 20, 2 );
add_filter( 'kadence_blocks_pro_posts_grid_query_args', 'culturacsi_content_hub_search_filter_query_loop_vars', 20, 2 );


/**
 * Shortcode to render a search form for content hub entries.
 *
 * @param array $atts
 * @return string
 */
function culturacsi_content_hub_search_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'title'      => '',
			'section'    => '',
			'placeholder' => __( 'Cerca...', 'culturacsi' ),
			'wrap_class' => '',
			'variant'    => '',
		),
		$atts,
		'culturacsi_content_search'
	);

	$filters    = culturacsi_content_hub_filters_from_request();
	$base_url   = get_permalink( get_queried_object_id() );
	$wrap_class = trim( (string) $atts['wrap_class'] );
	$section    = trim( (string) $atts['section'] );

	// Resolve title if empty and section provided.
	if ( '' === (string) $atts['title'] && '' !== $section ) {
		$term = get_term_by( 'slug', $section, 'csi_content_section' );
		if ( $term instanceof WP_Term ) {
			$atts['title'] = sprintf( __( 'Ricerca in %s', 'culturacsi' ), $term->name );
		}
	}

	$authors = get_users(
		array(
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'who'                 => 'authors',
			'has_published_posts' => array( CULTURACSI_CONTENT_HUB_POST_TYPE ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	);

	$associations = get_posts(
		array(
			'post_type'      => 'association',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => 1000,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$is_library     = ( 'library' === $atts['variant'] );
	$is_convenzioni = ( 'convenzioni' === $atts['variant'] );

	ob_start();
	?>
	<div class="culturacsi-content-search <?php echo esc_attr( $wrap_class ); ?> <?php echo $is_library ? 'is-variant-library' : ''; ?> <?php echo $is_convenzioni ? 'is-variant-convenzioni' : ''; ?>">
		<div class="assoc-search-panel assoc-content-search">
			<style>
				.assoc-content-search .assoc-search-form {
					display: grid;
					grid-auto-flow: row;
					gap: 10px 10px;
					align-items: end;
					grid-template-columns: repeat(4, minmax(0, 1fr));
				}
				.is-variant-library .assoc-search-form {
					grid-template-columns: repeat(4, minmax(0, 1fr));
				}
				.is-variant-convenzioni .assoc-search-form {
					grid-template-columns: repeat(2, minmax(0, 1fr));
				}
				.assoc-content-search .assoc-search-field { margin: 0; min-width: 0; }
				.is-variant-library .assoc-search-field.is-q { grid-column: span 2; }
				.assoc-content-search .assoc-search-field input,
				.assoc-content-search .assoc-search-field select {
					width: 100%;
					min-height: 44px;
					padding: 7px 10px;
					border: 1px solid #c7d3e4;
					border-radius: 8px;
					background: #fff;
				}
				@media (max-width: 719px) {
					.assoc-content-search .assoc-search-form,
					.is-variant-library .assoc-search-form,
					.is-variant-convenzioni .assoc-search-form { grid-template-columns: minmax(0, 1fr); }
					.assoc-content-search .assoc-search-field,
					.is-variant-library .assoc-search-field.is-q { grid-column: 1 / -1; }
				}
			</style>
			<div class="assoc-search-head">
				<div class="assoc-search-meta">
					<?php if ( '' !== trim( (string) $atts['title'] ) ) : ?>
						<h3 class="assoc-search-title"><?php echo esc_html( (string) $atts['title'] ); ?></h3>
					<?php endif; ?>
				</div>
				<p class="assoc-search-actions"><a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php _e( 'Azzera', 'culturacsi' ); ?></a></p>
			</div>
			<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
				<p class="assoc-search-field is-q">
					<label for="ch_q"><?php _e( 'Cerca', 'culturacsi' ); ?></label>
					<input type="text" id="ch_q" name="ch_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>">
				</p>
				<p class="assoc-search-field is-date">
					<label for="ch_date"><?php _e( 'Data', 'culturacsi' ); ?></label>
					<input type="month" id="ch_date" name="ch_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
				</p>
				<?php if ( $is_library ) : ?>
					<p class="assoc-search-field is-doc-type">
						<label for="ch_doc_type"><?php _e( 'Tipo documento', 'culturacsi' ); ?></label>
						<select id="ch_doc_type" name="ch_doc_type">
							<option value=""><?php _e( 'Tutti', 'culturacsi' ); ?></option>
							<?php foreach ( culturacsi_content_hub_get_available_doc_types() as $type ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['doc_type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php elseif ( ! $is_convenzioni ) : ?>
					<p class="assoc-search-field is-author">
						<label for="ch_author"><?php _e( 'Autore', 'culturacsi' ); ?></label>
						<select id="ch_author" name="ch_author">
							<option value="0"><?php _e( 'Tutti', 'culturacsi' ); ?></option>
							<?php foreach ( $authors as $author ) : ?>
								<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="assoc-search-field is-association">
						<label for="ch_assoc"><?php _e( 'Associazione', 'culturacsi' ); ?></label>
						<select id="ch_assoc" name="ch_assoc">
							<option value="0"><?php _e( 'Tutte', 'culturacsi' ); ?></option>
							<?php foreach ( $associations as $assoc_id ) : ?>
								<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $filters['assoc'], (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php endif; ?>
				<?php if ( ! $is_library && ! $is_convenzioni && '' !== $section ) : ?>
					<input type="hidden" name="ch_section" value="<?php echo esc_attr( $section ); ?>">
				<?php endif; ?>
				<button type="submit" style="display:none;"></button>
			</form>
		</div>
	</div>
	<script id="culturacsi-hub-search-autosubmit">
		document.addEventListener('DOMContentLoaded', function() {
			const hubSearchForms = document.querySelectorAll('.assoc-search-panel form.assoc-search-form');
			hubSearchForms.forEach(form => {
				const inputs = form.querySelectorAll('input, select');
				let debounce;
				inputs.forEach(input => {
					if (input.type === 'text' || input.type === 'month') {
						input.addEventListener('input', () => {
							clearTimeout(debounce);
							debounce = setTimeout(() => form.submit(), 600);
						});
					} else {
						input.addEventListener('change', () => form.submit());
					}
				});
			});
		});
	</script>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'culturacsi_content_search', 'culturacsi_content_hub_search_shortcode' );

/**
 * Register convenience shortcodes for common content sections.
 *
 * @return void
 */
function culturacsi_content_hub_register_alias_shortcodes() {
	$aliases = array(
		'culturacsi_library'             => array(
			'section'        => 'library',
			'title'          => __( 'Biblioteca', 'culturacsi' ),
			'downloads_only' => 'yes',
			'is_library'     => 'yes',
		),
		'culturacsi_services'            => array(
			'section' => 'services',
			'title'   => __( 'Servizi CulturaCSI', 'culturacsi' ),
		),
		'culturacsi_convenzioni'         => array(
			'section' => 'convenzioni',
			'title'   => __( 'Convenzioni', 'culturacsi' ),
		),
		'culturacsi_formazione'          => array(
			'section' => 'formazione',
			'title'   => __( 'Formazione', 'culturacsi' ),
		),
		'culturacsi_progetti'            => array(
			'section' => 'progetti',
			'title'   => __( 'Progetti', 'culturacsi' ),
		),
		'culturacsi_infopoint_stranieri' => array(
			'section' => 'infopoint-stranieri',
			'title'   => __( 'Infopoint Stranieri', 'culturacsi' ),
		),
	);

	$generic_tags = array(
		'culturacsi_section_feed',
		'culturacsi_sezione_feed',
		'culturacsi_sezione_contenuti',
	);
	foreach ( $generic_tags as $tag ) {
		add_shortcode(
			$tag,
			static function( $atts ) {
				$atts = is_array( $atts ) ? $atts : array();
				return culturacsi_content_hub_shortcode( $atts );
			}
		);
	}

	foreach ( $aliases as $tag => $defaults ) {
		add_shortcode(
			$tag,
			static function( $atts ) use ( $defaults ) {
				$atts = is_array( $atts ) ? $atts : array();
				return culturacsi_content_hub_shortcode( array_merge( $defaults, $atts ) );
			}
		);

		// Also register a search alias: [culturacsi_XXX_search]
		$search_tag = $tag . '_search';
		if ( ! shortcode_exists( $search_tag ) ) {
			add_shortcode(
				$search_tag,
				static function( $atts ) use ( $defaults, $tag ) {
					$atts = is_array( $atts ) ? $atts : array();
					$slug = isset( $defaults['section'] ) ? (string) $defaults['section'] : '';
					$atts['section'] = $slug;
					if ( 'culturacsi_library' === $tag ) {
						$atts['variant'] = 'library';
					} elseif ( 'culturacsi_convenzioni' === $tag ) {
						$atts['variant'] = 'convenzioni';
					}
					return culturacsi_content_hub_search_shortcode( $atts );
				}
			);
		}
	}

	// Dynamic aliases for every current and future section.
	$section_identifiers = culturacsi_content_hub_section_identifiers();
	foreach ( $section_identifiers as $section_data ) {
		$tag     = isset( $section_data['shortcode'] ) ? sanitize_key( (string) $section_data['shortcode'] ) : '';
		$slug    = isset( $section_data['slug'] ) ? sanitize_title( (string) $section_data['slug'] ) : '';
		$label   = isset( $section_data['label'] ) ? sanitize_text_field( (string) $section_data['label'] ) : '';
		if ( '' === $tag || '' === $slug || shortcode_exists( $tag ) ) {
			continue;
		}

		$defaults = array(
			'section' => $slug,
			'title'   => $label,
		);
		add_shortcode(
			$tag,
			static function( $atts ) use ( $defaults ) {
				$atts = is_array( $atts ) ? $atts : array();
				return culturacsi_content_hub_shortcode( array_merge( $defaults, $atts ) );
			}
		);

		// Also register a search alias: [culturacsi_section_XXX_search]
		$search_tag = $tag . '_search';
		if ( ! shortcode_exists( $search_tag ) ) {
			add_shortcode(
				$search_tag,
				static function( $atts ) use ( $slug ) {
					$atts = is_array( $atts ) ? $atts : array();
					$atts['section'] = $slug;
					if ( 'library' === $slug ) {
						$atts['variant'] = 'library';
					} elseif ( 'convenzioni' === $slug ) {
						$atts['variant'] = 'convenzioni';
					}
					return culturacsi_content_hub_search_shortcode( $atts );
				}
			);
		}
	}
}
add_action( 'init', 'culturacsi_content_hub_register_alias_shortcodes', 30 );

/**
 * Register a quick guide page in the content hub admin menu.
 *
 * @return void
 */
function culturacsi_content_hub_register_guide_page() {
	add_submenu_page(
		'edit.php?post_type=' . CULTURACSI_CONTENT_HUB_POST_TYPE,
		__( 'Guida Hub Contenuti', 'culturacsi' ),
		__( 'Guida Rapida', 'culturacsi' ),
		'edit_csi_content_entries',
		'csi-content-hub-guide',
		'culturacsi_content_hub_render_guide_page'
	);
}
add_action( 'admin_menu', 'culturacsi_content_hub_register_guide_page' );

/**
 * Render admin guide content.
 *
 * @return void
 */
function culturacsi_content_hub_render_guide_page() {
	if ( ! current_user_can( 'edit_csi_content_entries' ) ) {
		return;
	}

	$shortcodes = array(
		'[culturacsi_content_hub section="library" downloads_only="yes" search="yes"]',
		'[culturacsi_section_feed identifier="section_library"]',
	);
	$section_identifiers = culturacsi_content_hub_section_identifiers();
	foreach ( $section_identifiers as $section_data ) {
		$shortcode_tag = isset( $section_data['shortcode'] ) ? sanitize_key( (string) $section_data['shortcode'] ) : '';
		if ( '' === $shortcode_tag ) {
			continue;
		}
		$shortcodes[] = '[' . $shortcode_tag . ']';
	}
	$shortcodes = array_values( array_unique( $shortcodes ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Guida Rapida: Hub Contenuti Riutilizzabili', 'culturacsi' ); ?></h1>
		<p><?php esc_html_e( 'Usa questo flusso per Biblioteca, Servizi, Convenzioni, Formazione, Progetti e future sezioni.', 'culturacsi' ); ?></p>

		<h2><?php esc_html_e( 'Workflow consigliato', 'culturacsi' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Vai su Contenuti Riutilizzabili > Aggiungi Nuovo.', 'culturacsi' ); ?></li>
			<li><?php esc_html_e( 'Inserisci titolo, testo breve (riassunto) e immagine in evidenza se necessaria.', 'culturacsi' ); ?></li>
			<li><?php esc_html_e( 'Assegna la Sezione corretta (Biblioteca, Servizi, Convenzioni, Formazione, Progetti, Infopoint Stranieri).', 'culturacsi' ); ?></li>
			<li><?php esc_html_e( 'Nel box Download e Link allega un file oppure inserisci un URL esterno.', 'culturacsi' ); ?></li>
			<li><?php esc_html_e( 'Pubblica il contenuto: comparira automaticamente nella pagina che usa lo shortcode della sezione.', 'culturacsi' ); ?></li>
		</ol>

		<h2><?php esc_html_e( 'Shortcode disponibili', 'culturacsi' ); ?></h2>
		<ul>
			<?php foreach ( $shortcodes as $shortcode ) : ?>
				<li><code><?php echo esc_html( $shortcode ); ?></code></li>
			<?php endforeach; ?>
		</ul>

		<h2><?php esc_html_e( 'Nota operativa', 'culturacsi' ); ?></h2>
		<p><?php esc_html_e( 'Il sistema e progettato per evitare personalizzazioni su plugin di terze parti: tutta la logica vive nei MU plugin del progetto.', 'culturacsi' ); ?></p>
	</div>
	<?php
}

/**
 * Handle AJAX request for library modal data globally.
 */
function culturacsi_ajax_get_library_modal() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
	if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'csi_library_modal' ) ) {
		wp_send_json_error( array( 'message' => 'Nonce non valido' ), 403 );
	}

	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( $post_id <= 0 ) {
		wp_send_json_error( array( 'message' => 'Invalid post ID' ), 400 );
	}

	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'csi_content_entry' !== $post->post_type ) {
		wp_send_json_error( array( 'message' => 'Post non trovato' ), 404 );
	}

	$post_status = get_post_status( $post );
	if ( ! is_user_logged_in() ) {
		if ( 'publish' !== $post_status ) {
			wp_send_json_error( array( 'message' => 'Contenuto non disponibile' ), 403 );
		}
	} else {
		$can_read_private = current_user_can( 'read_post', $post_id );
		if ( 'publish' !== $post_status && ! $can_read_private ) {
			wp_send_json_error( array( 'message' => 'Permessi insufficienti' ), 403 );
		}
	}

	$file_id = (int) get_post_meta( $post_id, '_csi_content_hub_file_id', true );
	$attachment_url = $file_id > 0 ? (string) wp_get_attachment_url( $file_id ) : '';
	$file_note = '';
	if ( $file_id > 0 && '' !== $attachment_url ) {
		$file_path = (string) get_attached_file( $file_id );
		if ( '' !== trim( $file_path ) && file_exists( $file_path ) ) {
			$file_note = size_format( (float) filesize( $file_path ) );
		}
	}

	$modal_data = array(
		'title'    => get_the_title( $post ),
		'excerpt'  => get_the_excerpt( $post ),
		'content'  => apply_filters( 'the_content', $post->post_content ),
		'fileUrl'  => $attachment_url,
		'fileNote' => $file_note,
		'imageUrl' => has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'medium_large' ) : '',
	);

	wp_send_json_success( $modal_data );
}
add_action( 'wp_ajax_csi_get_library_modal', 'culturacsi_ajax_get_library_modal' );
add_action( 'wp_ajax_nopriv_csi_get_library_modal', 'culturacsi_ajax_get_library_modal' );
