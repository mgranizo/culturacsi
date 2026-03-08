<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

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

