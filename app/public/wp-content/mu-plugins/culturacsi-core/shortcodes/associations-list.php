<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'culturacsi_portal_enqueue_association_structure_editor' ) ) {
	/**
	 * Enqueue the Settori structure editor only on the associations portal screen.
	 */
	function culturacsi_portal_enqueue_association_structure_editor(): void {
		if ( ! function_exists( 'culturacsi_admin_ui_asset_url' ) || ! function_exists( 'culturacsi_admin_ui_asset_version' ) ) {
			return;
		}

		wp_enqueue_style(
			'culturacsi-associations-structure-editor',
			culturacsi_admin_ui_asset_url( 'associations-structure-editor.css' ),
			array(),
			culturacsi_admin_ui_asset_version( 'associations-structure-editor.css' )
		);

		wp_enqueue_script(
			'culturacsi-associations-structure-editor',
			culturacsi_admin_ui_asset_url( 'associations-structure-editor.js' ),
			array(),
			culturacsi_admin_ui_asset_version( 'associations-structure-editor.js' ),
			true
		);

		wp_localize_script(
			'culturacsi-associations-structure-editor',
			'culturacsiAssociationsStructureEditor',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'abf_settori_tree' ),
			)
		);
	}
}

function culturacsi_portal_associations_list_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$message_html  = culturacsi_portal_process_post_row_action( 'association', 'association', false );
	$is_site_admin = current_user_can( 'manage_options' );
	$user_id       = get_current_user_id();
	$filters       = culturacsi_portal_associations_filters_from_request();
	$current_page  = isset( $_GET['a_page'] ) ? max( 1, absint( wp_unslash( $_GET['a_page'] ) ) ) : 1;
	$base_url      = culturacsi_portal_reserved_current_page_url();
	$sort_state    = culturacsi_portal_get_sort_state(
		'a_sort',
		'a_dir',
		'name',
		'asc',
		array( 'index', 'name', 'category', 'status', 'history' )
	);
	$per_page = 50;

	$query_args = array(
		'post_type'      => 'association',
		'post_status'    => ( $is_site_admin && 'all' !== $filters['status'] ) ? array( $filters['status'] ) : array( 'publish', 'private', 'pending', 'draft' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
	);
	if ( '' !== $filters['q'] ) {
		$query_args['s'] = $filters['q'];
	}
	if ( $filters['cat'] > 0 ) {
		$query_args['tax_query'] = array(
			array(
				'taxonomy' => 'activity_category',
				'field'    => 'term_id',
				'terms'    => array( (int) $filters['cat'] ),
			),
		);
	}
	if ( ! $is_site_admin ) {
		$managed_id = culturacsi_portal_get_managed_association_id( $user_id );
		$query_args['post__in'] = $managed_id > 0 ? array( $managed_id ) : array( 0 );
	}
	$location_meta_query = culturacsi_portal_association_location_meta_query( $filters );
	if ( ! empty( $location_meta_query ) ) {
		$query_args['meta_query'] = $location_meta_query;
	}

	$query = new WP_Query( $query_args );
	$posts = culturacsi_portal_normalize_posts_list( (array) $query->posts );
	if ( ! empty( $posts ) ) {
		// Pre-compute sort keys to prevent N*log(N) taxonomy lookups during usort.
		$sort_keys = array();
		if ( 'category' === $sort_state['sort'] ) {
			foreach ( $posts as $post_obj ) {
				if ( ! $post_obj instanceof WP_Post ) {
					continue;
				}
				$activity_labels = function_exists( 'culturacsi_activity_labels_for_post' ) ? culturacsi_activity_labels_for_post( (int) $post_obj->ID ) : array();
				$sort_keys[ $post_obj->ID ] = ! empty( $activity_labels ) ? implode( ', ', $activity_labels ) : '';
			}
		}

		usort(
			$posts,
			static function( $a, $b ) use ( $sort_state, $sort_keys ): int {
				if ( ! $a instanceof WP_Post || ! $b instanceof WP_Post ) {
					return 0;
				}
				$cmp = 0;
				switch ( $sort_state['sort'] ) {
					case 'name':
						$cmp = strcasecmp( (string) $a->post_title, (string) $b->post_title );
						break;
					case 'category':
						$cat_a = $sort_keys[ $a->ID ] ?? '';
						$cat_b = $sort_keys[ $b->ID ] ?? '';
						$cmp   = strcasecmp( $cat_a, $cat_b );
						break;
					case 'status':
						$cmp = strcmp( (string) $a->post_status, (string) $b->post_status );
						break;
					case 'history':
						static $history_cache = array();
						if ( ! isset( $history_cache[ $a->ID ] ) ) {
							$modified = culturacsi_logging_get_last_modified( 'association', $a->ID );
							$creator  = culturacsi_logging_get_creator( 'association', $a->ID );
							$history_cache[ $a->ID ] = $modified ? strtotime( $modified->created_at ) : ( $creator ? strtotime( $creator->created_at ) : strtotime( $a->post_date ) );
						}
						if ( ! isset( $history_cache[ $b->ID ] ) ) {
							$modified = culturacsi_logging_get_last_modified( 'association', $b->ID );
							$creator  = culturacsi_logging_get_creator( 'association', $b->ID );
							$history_cache[ $b->ID ] = $modified ? strtotime( $modified->created_at ) : ( $creator ? strtotime( $creator->created_at ) : strtotime( $b->post_date ) );
						}
						$cmp = $history_cache[ $a->ID ] <=> $history_cache[ $b->ID ];
						break;
					case 'index':
					default:
						$cmp = (int) $a->ID <=> (int) $b->ID;
						break;
				}

				return ( 'asc' === $sort_state['dir'] ) ? $cmp : -$cmp;
			}
		);
	}

	$total_items  = count( $posts );
	$max_pages    = max( 1, (int) ceil( $total_items / $per_page ) );
	$current_page = min( $current_page, $max_pages );
	$offset       = ( $current_page - 1 ) * $per_page;
	$paged_posts  = array_slice( $posts, $offset, $per_page );

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="assoc-portal-associations-list assoc-portal-section">';

	$export_url = function_exists( 'culturacsi_export_build_url' ) ? culturacsi_export_build_url( 'association', (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ) : (string) add_query_arg( 'culturacsi_export', 'association', $_SERVER['REQUEST_URI'] ?? '' );
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Associazioni</h2><div style="display:flex;gap:10px;"><a class="button" style="background-color: #22c55e; color: white; border-color: #16a34a;" href="' . esc_url( $export_url ) . '">Esporta CSV</a> ';
	if ( $is_site_admin ) {
		culturacsi_portal_enqueue_association_structure_editor();
		echo '<a class="button button-primary" href="' . esc_url( culturacsi_portal_admin_association_form_url() ) . '">Crea Associazione</a>';
		echo ' <button type="button" id="abf-open-struct-modal-portal" class="button">Struttura Settori</button>';
	} else {
		echo '<a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/profilo/' ) ) . '">Dati Associazione</a>';
	}
	echo '</div></div>';

	echo '<table class="widefat striped assoc-admin-table assoc-table-assocs"><colgroup><col style="width:4ch"><col style="width:42%"><col style="width:16%"><col style="width:6.2rem"><col style="width:140px"><col style="width:110px"></colgroup><thead><tr>';
	echo culturacsi_portal_sortable_th( '#', 'index', $sort_state['sort'], $sort_state['dir'], 'a_sort', 'a_dir', $base_url, 'assoc-col-index', array( 'a_page' ) );
	echo culturacsi_portal_sortable_th( 'Nome', 'name', $sort_state['sort'], $sort_state['dir'], 'a_sort', 'a_dir', $base_url, 'assoc-col-title', array( 'a_page' ) );
	echo culturacsi_portal_sortable_th( 'Categoria', 'category', $sort_state['sort'], $sort_state['dir'], 'a_sort', 'a_dir', $base_url, 'assoc-col-category', array( 'a_page' ) );
	echo culturacsi_portal_sortable_th( 'Stato', 'status', $sort_state['sort'], $sort_state['dir'], 'a_sort', 'a_dir', $base_url, 'assoc-col-status assoc-col-status-compact', array( 'a_page' ) );
	$th_html_a = culturacsi_portal_sortable_th( 'Cronologia', 'history', $sort_state['sort'], $sort_state['dir'], 'a_sort', 'a_dir', $base_url, 'assoc-col-history', array( 'a_page' ) );
	echo str_replace( '<th class="assoc-col-history">', '<th class="assoc-col-history" style="width:180px;">', $th_html_a );
	echo '<th class="assoc-col-actions">Azioni</th>';
	echo '</tr></thead><tbody>';

	if ( ! empty( $paged_posts ) ) {
		$row_num = $offset;
		foreach ( $paged_posts as $post_item ) {
			if ( ! $post_item instanceof WP_Post ) {
				continue;
			}

			++$row_num;
			$post_id         = (int) $post_item->ID;
			$status          = (string) get_post_status( $post_id );
			$status_obj      = get_post_status_object( $status );
			$activity_labels = function_exists( 'culturacsi_activity_labels_for_post' ) ? culturacsi_activity_labels_for_post( $post_id ) : array();
			$category        = ! empty( $activity_labels ) ? implode( ', ', $activity_labels ) : '-';
			$city            = trim( (string) get_post_meta( $post_id, 'city', true ) );
			$province        = trim( (string) get_post_meta( $post_id, 'province', true ) );
			$region          = trim( (string) get_post_meta( $post_id, 'region', true ) );
			$location_parts  = array();
			$seen_location   = array();

			if ( '' === $city ) {
				$city = trim( (string) get_post_meta( $post_id, 'comune', true ) );
			}
			if ( '' === $region ) {
				$region = trim( (string) get_post_meta( $post_id, 'regione', true ) );
			}

			$push_location = static function( string $label, string $value ) use ( &$location_parts, &$seen_location ): void {
				$value = trim( $value );
				if ( '' === $value ) {
					return;
				}
				$norm = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $value ) : $value );
				$norm = preg_replace( '/\s+/u', ' ', $norm );
				$norm = is_string( $norm ) ? trim( $norm ) : '';
				if ( '' === $norm || isset( $seen_location[ $norm ] ) ) {
					return;
				}
				$seen_location[ $norm ] = true;
				$location_parts[]       = $label . ': ' . $value;
			};

			$push_location( 'Citta', $city );
			$push_location( 'Prov', strtoupper( $province ) );
			$push_location( 'Regione', $region );

			$location_summary = implode( ' | ', $location_parts );
			$edit_url         = $is_site_admin ? culturacsi_portal_admin_association_form_url( $post_id ) : home_url( '/area-riservata/profilo/' );
			$row_class        = ( $is_site_admin && 'pending' === $status ) ? ' is-pending-approval' : '';

			echo '<tr class="' . esc_attr( trim( $row_class ) ) . '" data-id="' . esc_attr( (string) $post_id ) . '" data-type="association">';
			echo '<td class="assoc-col-index">' . esc_html( (string) $row_num ) . '</td>';
			echo '<td class="assoc-col-title"><span class="assoc-association-name">' . esc_html( get_the_title( $post_id ) ) . '</span>';
			if ( '' !== $location_summary ) {
				echo '<span class="assoc-association-location">' . esc_html( $location_summary ) . '</span>';
			}
			echo '</td>';
			if ( '-' !== $category ) {
				echo '<td class="assoc-col-category"><strong class="assoc-category-activities">' . esc_html( $category ) . '</strong></td>';
			} else {
				echo '<td class="assoc-col-category">-</td>';
			}
			echo '<td class="assoc-col-status assoc-col-status-compact"><span class="assoc-status-pill status-' . esc_attr( $status ) . '">' . esc_html( $status_obj ? $status_obj->label : $status ) . '</span></td>';

			echo '<td class="assoc-col-history" style="font-size:11px;line-height:1.2;color:#64748b;vertical-align:middle;">';
			$last_mod = culturacsi_logging_get_last_modified( 'association', $post_id );
			if ( $last_mod ) {
				echo '<strong>Mod:</strong> ' . esc_html( date_i18n( 'd/m/y H:i', strtotime( $last_mod->created_at ) ) ) . '<br>';
				echo esc_html( $last_mod->user_name );
			} else {
				$creator = culturacsi_logging_get_creator( 'association', $post_id );
				if ( $creator ) {
					echo '<strong>Reg:</strong> ' . esc_html( date_i18n( 'd/m/y H:i', strtotime( $creator->created_at ) ) ) . '<br>';
					echo esc_html( $creator->user_name );
				} else {
					echo '<strong>Reg:</strong> ' . esc_html( get_the_date( 'd/m/y H:i', $post_id ) ) . '<br>';
					echo esc_html( get_the_author_meta( 'display_name', $post_item->post_author ) );
				}
			}
			echo '</td>';

			echo '<td class="assoc-col-actions"><div class="assoc-action-group">';
			echo '<a class="assoc-action-chip chip-edit" href="' . esc_url( $edit_url ) . '">Mod.</a>';
			if ( $is_site_admin ) {
				echo culturacsi_portal_action_button_form(
					array(
						'context'      => 'association',
						'action'       => 'delete',
						'target_id'    => $post_id,
						'label'        => 'Elim.',
						'class'        => 'chip-delete',
						'confirm'      => true,
						'confirm_text' => 'Confermi l\'eliminazione di questa associazione?',
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				$is_published = 'publish' === $status;
				$toggle_label = $is_published ? 'Rif.' : 'Appr.';
				$toggle_action = $is_published ? 'reject' : 'approve';
				$toggle_class = $is_published ? 'chip-reject chip-toggle' : 'chip-approve chip-toggle';
				echo culturacsi_portal_action_button_form(
					array(
						'context'   => 'association',
						'action'    => $toggle_action,
						'target_id' => $post_id,
						'label'     => $toggle_label,
						'class'     => $toggle_class,
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="6">Nessuna associazione trovata.</td></tr>';
	}

	echo '</tbody></table>';
	if ( $max_pages > 1 ) {
		$pagination_links = paginate_links(
			array(
				'base'      => add_query_arg( 'a_page', '%#%', remove_query_arg( 'a_page' ) ),
				'format'    => '',
				'current'   => $current_page,
				'total'     => $max_pages,
				'type'      => 'array',
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			)
		);
		if ( is_array( $pagination_links ) && ! empty( $pagination_links ) ) {
			echo '<nav class="assoc-pagination" aria-label="Paginazione associazioni"><ul class="assoc-pagination-list">';
			foreach ( $pagination_links as $link_html ) {
				$is_current = false !== strpos( $link_html, 'current' );
				echo '<li class="assoc-pagination-item' . ( $is_current ? ' is-current' : '' ) . '">' . $link_html . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</ul></nav>';
		}
	}
	echo '</div>';

	return ob_get_clean();
}
