<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function culturacsi_portal_cronologia_list_shortcode(): string {
	$is_site_admin = current_user_can( 'manage_options' );
	if ( ! culturacsi_portal_can_access() || ( ! $is_site_admin && ! current_user_can( 'association_manager' ) ) ) {
		return '<p>Permessi insufficienti.</p>';
	}
	
	global $wpdb;
	$table_name = culturacsi_logging_get_table_name();
	if ( $wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name ) {
		return '<p>Database della cronologia non trovato.</p>';
	}

	$per_page     = 50;
	$current_page = isset( $_GET['c_page'] ) ? max( 1, absint( wp_unslash( $_GET['c_page'] ) ) ) : 1;
	$offset       = ( $current_page - 1 ) * $per_page;
	
	$sort_state = culturacsi_portal_get_sort_state(
		'c_sort',
		'c_dir',
		'created_at',
		'desc',
		array( 'id', 'user_name', 'action', 'object_type', 'object_id', 'created_at' )
	);
	
	$orderby_sql = "l.created_at";
	if ( $sort_state['sort'] === 'id' ) $orderby_sql = "l.id";
	if ( $sort_state['sort'] === 'user_name' ) $orderby_sql = "u.display_name";
	if ( $sort_state['sort'] === 'action' ) $orderby_sql = "l.action";
	if ( $sort_state['sort'] === 'object_type' ) $orderby_sql = "l.object_type";
	if ( $sort_state['sort'] === 'object_id' ) $orderby_sql = "l.object_id";
	$order_dir = $sort_state['dir'] === 'asc' ? 'ASC' : 'DESC';

	// Filters
	$where = "1=1";
	$q = isset( $_GET['c_q'] ) ? sanitize_text_field( wp_unslash( $_GET['c_q'] ) ) : '';
	if ( '' !== $q ) {
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$where .= $wpdb->prepare( " AND (l.details LIKE %s OR u.display_name LIKE %s)", $like, $like );
	}
	
	if ( ! $is_site_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id <= 0 ) {
			return '<p>Associazione non trovata.</p>';
		}
		
		$allowed_objects = array( "(l.object_type = 'association' AND l.object_id = " . (int) $assoc_id . ")" );
		
		$event_ids = get_posts( array( 'post_type' => 'event', 'meta_query' => array( array( 'key' => 'organizer_association_id', 'value' => $assoc_id ) ), 'fields' => 'ids', 'posts_per_page' => -1 ) );
		if ( ! empty( $event_ids ) ) {
			$allowed_objects[] = "(l.object_type = 'event' AND l.object_id IN (" . implode( ',', array_map( 'intval', $event_ids ) ) . "))";
		}
		
		$news_ids = get_posts( array( 'post_type' => 'news', 'meta_query' => array( array( 'key' => 'organizer_association_id', 'value' => $assoc_id ) ), 'fields' => 'ids', 'posts_per_page' => -1 ) );
		if ( ! empty( $news_ids ) ) {
			$allowed_objects[] = "(l.object_type = 'news' AND l.object_id IN (" . implode( ',', array_map( 'intval', $news_ids ) ) . "))";
		}
		
		$user_ids = get_users( array( 'meta_query' => array( array( 'key' => 'association_post_id', 'value' => $assoc_id ) ), 'fields' => 'ID' ) );
		if ( ! empty( $user_ids ) ) {
			$allowed_objects[] = "(l.object_type = 'user' AND l.object_id IN (" . implode( ',', array_map( 'intval', $user_ids ) ) . "))";
		}
		
		$where .= " AND (" . implode( " OR ", $allowed_objects ) . ")";
	}

	$total_items = (int) $wpdb->get_var( "SELECT COUNT(l.id) FROM $table_name l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID WHERE $where" );
	$max_pages   = max( 1, (int) ceil( $total_items / $per_page ) );
	
	$logs = $wpdb->get_results( 
		"SELECT l.*, u.display_name as user_name 
		 FROM $table_name l 
		 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
		 WHERE $where 
		 ORDER BY $orderby_sql $order_dir 
		 LIMIT $per_page OFFSET $offset" 
	);
	
	$base_url = culturacsi_portal_reserved_current_page_url();

	ob_start();
	echo '<div class="assoc-portal-cronologia-list assoc-portal-section">';
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Cronologia (Audit Log)</h2>';
	echo '<div style="display:flex;gap:10px;"><a class="button" style="background-color: #22c55e; color: white; border-color: #16a34a;" href="' . esc_url( add_query_arg( 'culturacsi_export', 'cronologia', $_SERVER['REQUEST_URI'] ?? '' ) ) . '">Esporta CSV</a></div></div>';
	
	echo '<div class="assoc-search-panel" style="margin-bottom:20px;">
		<form method="get" action="' . esc_url( $base_url ) . '" class="assoc-search-form" style="display:flex;gap:10px;align-items:center;">
			<input type="text" name="c_q" value="' . esc_attr( $q ) . '" placeholder="Cerca...">
			<button type="submit" class="button">Cerca</button>
			<a href="' . esc_url( $base_url ) . '" class="button">Azzera</a>
		</form>
	</div>';

	echo '<table class="widefat striped assoc-admin-table assoc-table-cronologia"><thead><tr>';
	echo culturacsi_portal_sortable_th( 'ID', 'id', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, '', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'Data e Ora', 'created_at', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, '', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'Utente', 'user_name', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, '', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'Azione', 'action', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, '', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'Tipo', 'object_type', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, '', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'ID Ogg.', 'object_id', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, '', array( 'c_page' ) );
	echo '<th>Dettagli</th>';
	echo '</tr></thead><tbody>';
	
	if ( ! empty( $logs ) ) {
		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( $log->id ) . '</td>';
			echo '<td>' . esc_html( date_i18n( 'd/m/Y H:i:s', strtotime( $log->created_at ) ) ) . '</td>';
			echo '<td><strong>' . esc_html( $log->user_name ?: 'Utente ' . $log->user_id ) . '</strong><br><small style="color:#64748b;">IP: ' . esc_html( $log->ip_address ) . '</small></td>';
			$action_labels = array(
				'create_post' => 'CREATO',
				'update_post' => 'MODIFICATO',
				'trash_post'  => 'ELIMINATO',
				'wp_insert_user' => 'REGISTRATO (UTENTE)',
				'update_user' => 'MODIFICATO (UTENTE)',
				'approve'     => 'APPROVATO',
				'reject'      => 'RIFIUTATO',
				'hold'        => 'IN ATTESA',
				'login'       => 'ACCESSO (LOGIN)'
			);
			$display_action = isset( $action_labels[ $log->action ] ) ? $action_labels[ $log->action ] : strtoupper( $log->action );
			echo '<td><span class="assoc-status-pill">' . esc_html( $display_action ) . '</span></td>';
			$display_type = $log->object_type;
			if ( $display_type === 'event' ) $display_type = 'Evento';
			elseif ( $display_type === 'news' ) $display_type = 'Notizia';
			elseif ( $display_type === 'user' ) $display_type = 'Utente';
			elseif ( $display_type === 'association' ) $display_type = 'Associazione';

			echo '<td>' . esc_html( $display_type ) . '</td>';
			
			$obj_link = '#';
			if ( $log->object_id > 0 ) {
				if ( $log->object_type === 'user' ) {
					$obj_link = culturacsi_portal_admin_user_form_url( $log->object_id );
				} elseif ( $log->object_type === 'association' ) {
					$obj_link = culturacsi_portal_admin_association_form_url( $log->object_id );
				} elseif ( $log->object_type === 'event' ) {
					$obj_link = home_url( '/area-riservata/eventi/nuovo/?event_id=' . $log->object_id );
				} elseif ( $log->object_type === 'news' ) {
					$obj_link = home_url( '/area-riservata/notizie/nuova/?news_id=' . $log->object_id );
				}
			}
			echo '<td><a href="' . esc_url( $obj_link ) . '">#' . esc_html( $log->object_id ) . '</a></td>';
			echo '<td><div style="font-size:12px;line-height:1.4;background:#f8fafc;padding:5px;border-radius:4px;word-break:break-word;">' . esc_html( $log->details ) . '</div></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="7">Nessuna cronologia trovata.</td></tr>';
	}
	echo '</tbody></table>';

	if ( $max_pages > 1 ) {
		echo '<div class="assoc-pagination" style="margin-top:20px; text-align:right;">';
		$page_links = paginate_links(
			array(
				'base'      => add_query_arg( 'c_page', '%#%', $base_url ),
				'format'    => '',
				'prev_text' => '&laquo; Precedente',
				'next_text' => 'Successivo &raquo;',
				'total'     => $max_pages,
				'current'   => $current_page,
			)
		);
		if ( $page_links ) {
			echo $page_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
	
	echo '</div>';
	return ob_get_clean();
}
