<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function culturacsi_portal_events_list_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$message_html = culturacsi_portal_process_post_row_action( 'event', 'event', true );
	$filters      = culturacsi_portal_events_filters_from_request();
	$base_url     = culturacsi_portal_reserved_current_page_url();
	$sort_state   = culturacsi_portal_get_sort_state(
		'e_sort',
		'e_dir',
		'date',
		'desc',
		array( 'index', 'title', 'date', 'status', 'history' )
	);

	$args = array(
		'post_type'      => 'event',
		'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		'posts_per_page' => 200,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( ! current_user_can( 'manage_options' ) ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id > 0 ) {
			// Query events linked to this association
			$args['meta_query'] = array(
				array(
					'key'     => 'organizer_association_id',
					'value'   => $assoc_id,
					'compare' => '=',
				),
			);
		} else {
			$args['author'] = get_current_user_id();
		}
	} elseif ( $filters['author'] > 0 ) {
		$args['author'] = $filters['author'];
	}
	if ( '' !== $filters['q'] ) {
		$args['s'] = $filters['q'];
	}
	if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
		$args['date_query'] = array(
			array(
				'year'     => (int) $matches[1],
				'monthnum' => (int) $matches[2],
			),
		);
	}
	if ( 'all' !== $filters['status'] ) {
		$allowed_status = array( 'publish', 'pending', 'draft', 'future', 'private' );
		if ( in_array( $filters['status'], $allowed_status, true ) ) {
			$args['post_status'] = array( $filters['status'] );
		}
	}

	$query = new WP_Query( $args );
	$posts = culturacsi_portal_normalize_posts_list( (array) $query->posts );
	if ( ! empty( $posts ) ) {
		usort(
			$posts,
			static function( $a, $b ) use ( $sort_state ): int {
				if ( ! $a instanceof WP_Post || ! $b instanceof WP_Post ) {
					return 0;
				}
				$cmp = 0;
				switch ( $sort_state['sort'] ) {
					case 'title':
						$cmp = strcasecmp( (string) $a->post_title, (string) $b->post_title );
						break;
					case 'status':
						$cmp = strcmp( (string) $a->post_status, (string) $b->post_status );
						break;
					case 'date':
						$cmp = strtotime( (string) $a->post_date ) <=> strtotime( (string) $b->post_date );
						break;
					case 'history':
						static $h_cache = array();
						if(!isset($h_cache[$a->ID])) { $m=culturacsi_logging_get_last_modified('event',$a->ID); $c=culturacsi_logging_get_creator('event',$a->ID); $h_cache[$a->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($a->post_date)); }
						if(!isset($h_cache[$b->ID])) { $m=culturacsi_logging_get_last_modified('event',$b->ID); $c=culturacsi_logging_get_creator('event',$b->ID); $h_cache[$b->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($b->post_date)); }
						$cmp = $h_cache[$a->ID] <=> $h_cache[$b->ID];
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
	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="assoc-portal-events-list assoc-portal-section">';
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Eventi</h2><div style="display:flex;gap:10px;"><a class="button" style="background-color: #22c55e; color: white; border-color: #16a34a;" href="' . esc_url( add_query_arg( 'culturacsi_export', 'event', $_SERVER['REQUEST_URI'] ?? '' ) ) . '">Esporta CSV</a> <a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/eventi/nuovo/' ) ) . '">Nuovo Evento</a></div></div>';
	echo '<style>.assoc-admin-table tr.is-pending-approval td { background-color: #fef2f2 !important; border-top: 2px solid #ef4444 !important; border-bottom: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:first-child { border-left: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:last-child { border-right: 2px solid #ef4444 !important; }</style>';
	echo '<table class="widefat striped assoc-admin-table assoc-table-events"><colgroup><col style="width:4ch"><col style="width:38%"><col style="width:7.2rem"><col style="width:6.2rem"><col style="width:140px"><col style="width:110px"></colgroup><thead><tr>';
	echo culturacsi_portal_sortable_th( '#', 'index', $sort_state['sort'], $sort_state['dir'], 'e_sort', 'e_dir', $base_url, 'assoc-col-index' );
	echo culturacsi_portal_sortable_th( 'Titolo', 'title', $sort_state['sort'], $sort_state['dir'], 'e_sort', 'e_dir', $base_url, 'assoc-col-title' );
	echo culturacsi_portal_sortable_th( 'Data', 'date', $sort_state['sort'], $sort_state['dir'], 'e_sort', 'e_dir', $base_url, 'assoc-col-date' );
	echo culturacsi_portal_sortable_th( 'Stato', 'status', $sort_state['sort'], $sort_state['dir'], 'e_sort', 'e_dir', $base_url, 'assoc-col-status' );
	$th_html = culturacsi_portal_sortable_th( 'Cronologia', 'history', $sort_state['sort'], $sort_state['dir'], 'e_sort', 'e_dir', $base_url, 'assoc-col-history' );
	echo str_replace( '<th class="assoc-col-history">', '<th class="assoc-col-history" style="width:180px;">', $th_html );
	echo '<th class="assoc-col-actions">Azioni</th>';
	echo '</tr></thead><tbody>';
	if ( ! empty( $posts ) ) {
		$row_num = 0;
		foreach ( $posts as $post_item ) {
			if ( ! $post_item instanceof WP_Post ) {
				continue;
			}
			++$row_num;
			$post_id    = (int) $post_item->ID;
			$status_obj = get_post_status_object( get_post_status( $post_id ) );
			$is_admin   = current_user_can( 'manage_options' );
			$edit_url   = add_query_arg( array( 'event_id' => $post_id ), home_url( '/area-riservata/eventi/nuovo/' ) );

			$row_class  = ( $is_admin && 'pending' === get_post_status( $post_id ) ) ? ' is-pending-approval' : '';
			echo '<tr class="' . esc_attr( trim( $row_class ) ) . '" data-id="' . esc_attr( (string) $post_id ) . '" data-type="event">';
			echo '<td class="assoc-col-index">' . esc_html( (string) $row_num ) . '</td>';
			echo '<td class="assoc-col-title">' . esc_html( get_the_title( $post_id ) ) . '</td>';
			echo '<td class="assoc-col-date">' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $post_item->post_date ) ) ) . '</td>';
			echo '<td class="assoc-col-status"><span class="assoc-status-pill status-' . esc_attr( (string) get_post_status( $post_id ) ) . '">' . esc_html( $status_obj ? $status_obj->label : (string) get_post_status( $post_id ) ) . '</span></td>';
			
			// History column for Events
			echo '<td class="assoc-col-history" style="font-size:11px;line-height:1.2;color:#64748b;">';
			$last_mod = culturacsi_logging_get_last_modified( 'event', $post_id );
			if ( $last_mod ) {
				echo '<strong>Modificato:</strong> ' . esc_html( date_i18n( 'd/m/y H:i', strtotime( $last_mod->created_at ) ) ) . '<br>';
				echo '<strong>Da:</strong> ' . esc_html( $last_mod->user_name );
			} else {
				$creator = culturacsi_logging_get_creator( 'event', $post_id );
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
			echo culturacsi_portal_action_button_form(
				array(
					'context'      => 'event',
					'action'       => 'delete',
					'target_id'    => $post_id,
					'label'        => 'Elim.',
					'class'        => 'chip-delete',
					'confirm'      => true,
					'confirm_text' => 'Confermi l\'eliminazione di questo evento?',
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $is_admin ) {
				$is_published = 'publish' === (string) get_post_status( $post_id );
				$toggle_label = $is_published ? 'Rif.' : 'Appr.';
				$toggle_action = $is_published ? 'reject' : 'approve';
				$toggle_class = $is_published ? 'chip-reject chip-toggle' : 'chip-approve chip-toggle';
				echo culturacsi_portal_action_button_form( array( 'context' => 'event', 'action' => $toggle_action, 'target_id' => $post_id, 'label' => $toggle_label, 'class' => $toggle_class ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="5">Nessun evento trovato.</td></tr>';
	}
	echo '</tbody></table></div>';
	return ob_get_clean();
}
