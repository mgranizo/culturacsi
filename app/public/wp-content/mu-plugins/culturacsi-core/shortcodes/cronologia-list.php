<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function culturacsi_portal_cronologia_list_shortcode(): string {
	$is_site_admin = current_user_can( 'manage_options' );
	if ( ! culturacsi_portal_can_access() || ( ! $is_site_admin && ! current_user_can( 'association_manager' ) ) ) {
		return '<p>Permessi insufficienti.</p>';
	}
	
	global $wpdb;
	$table_name = culturacsi_logging_get_table_name();
	$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
	if ( $table_name !== $table_exists ) {
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
	$where_parts = array( '1=1' );
	$where_bindings = array();
	$q = isset( $_GET['c_q'] ) ? sanitize_text_field( wp_unslash( $_GET['c_q'] ) ) : '';
	if ( '' !== $q ) {
		$like = '%' . $wpdb->esc_like( $q ) . '%';
		$where_parts[]   = '(l.details LIKE %s OR u.display_name LIKE %s)';
		$where_bindings[] = $like;
		$where_bindings[] = $like;
	}

	// Object type filter: news, event, user, association
	$type = isset( $_GET['c_type'] ) ? sanitize_key( wp_unslash( $_GET['c_type'] ) ) : '';
	$allowed_types = array( 'news', 'event', 'user', 'association' );
	if ( in_array( $type, $allowed_types, true ) ) {
		$where_parts[] = 'l.object_type = %s';
		$where_bindings[] = $type;
	} else {
		$type = '';
	}
	
	if ( ! $is_site_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id <= 0 ) {
			return '<p>Associazione non trovata.</p>';
		}
		
		$allowed_objects_parts = array();
		$allowed_objects_parts[] = '(l.object_type = %s AND l.object_id = %d)';
		$allowed_objects_bindings = array( 'association', (int) $assoc_id );
		
		$event_ids = get_posts( array( 'post_type' => 'event', 'meta_query' => array( array( 'key' => 'organizer_association_id', 'value' => $assoc_id ) ), 'fields' => 'ids', 'posts_per_page' => -1 ) );
		if ( ! empty( $event_ids ) ) {
			$event_ids = array_values( array_filter( array_map( 'intval', (array) $event_ids ) ) );
			if ( ! empty( $event_ids ) ) {
				$event_placeholders = implode( ', ', array_fill( 0, count( $event_ids ), '%d' ) );
				$allowed_objects_parts[] = "(l.object_type = %s AND l.object_id IN ({$event_placeholders}))";
				$allowed_objects_bindings[] = 'event';
				$allowed_objects_bindings = array_merge( $allowed_objects_bindings, $event_ids );
			}
		}
		
		$news_ids = get_posts( array( 'post_type' => 'news', 'meta_query' => array( array( 'key' => 'organizer_association_id', 'value' => $assoc_id ) ), 'fields' => 'ids', 'posts_per_page' => -1 ) );
		if ( ! empty( $news_ids ) ) {
			$news_ids = array_values( array_filter( array_map( 'intval', (array) $news_ids ) ) );
			if ( ! empty( $news_ids ) ) {
				$news_placeholders = implode( ', ', array_fill( 0, count( $news_ids ), '%d' ) );
				$allowed_objects_parts[] = "(l.object_type = %s AND l.object_id IN ({$news_placeholders}))";
				$allowed_objects_bindings[] = 'news';
				$allowed_objects_bindings = array_merge( $allowed_objects_bindings, $news_ids );
			}
		}
		
		$user_ids = get_users( array( 'meta_query' => array( array( 'key' => 'association_post_id', 'value' => $assoc_id ) ), 'fields' => 'ID' ) );
		if ( ! empty( $user_ids ) ) {
			$user_ids = array_values( array_filter( array_map( 'intval', (array) $user_ids ) ) );
			if ( ! empty( $user_ids ) ) {
				$user_placeholders = implode( ', ', array_fill( 0, count( $user_ids ), '%d' ) );
				$allowed_objects_parts[] = "(l.object_type = %s AND l.object_id IN ({$user_placeholders}))";
				$allowed_objects_bindings[] = 'user';
				$allowed_objects_bindings = array_merge( $allowed_objects_bindings, $user_ids );
			}
		}
		
		$where_parts[] = '( ' . implode( ' OR ', $allowed_objects_parts ) . ' )';
		$where_bindings = array_merge( $where_bindings, $allowed_objects_bindings );
	}

	$where_sql = implode( ' AND ', $where_parts );
	$count_sql = "SELECT COUNT(l.id) FROM {$table_name} l LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID WHERE {$where_sql}";
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared dynamically with safe placeholders.
	$count_prepared = ! empty( $where_bindings ) ? $wpdb->prepare( $count_sql, $where_bindings ) : $count_sql;

	$total_items = (int) $wpdb->get_var( $count_prepared );
	$max_pages   = max( 1, (int) ceil( $total_items / $per_page ) );

	$list_sql = "SELECT l.*, COALESCE(NULLIF(u.display_name, ''), NULLIF(l.user_display_name, ''), NULLIF(l.user_login, ''), IF(l.user_id > 0, CONCAT('ID ', l.user_id), 'Sistema')) AS user_name
		 FROM {$table_name} l
		 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
		 WHERE {$where_sql}
		 ORDER BY {$orderby_sql} {$order_dir}
		 LIMIT %d OFFSET %d";
	$list_bindings = array_merge( $where_bindings, array( (int) $per_page, (int) $offset ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared dynamically with safe placeholders.
	$list_prepared = $wpdb->prepare( $list_sql, $list_bindings );
	$logs = $wpdb->get_results( $list_prepared );
	
	$base_url = culturacsi_portal_reserved_current_page_url();

	ob_start();
	echo '<div class="assoc-portal-cronologia-list assoc-portal-section">';
	
	echo '<div class="assoc-search-panel assoc-cronologia-search">'
		. '<div class="assoc-search-head">'
		. '<div class="assoc-search-meta">'
		. '<h3 class="assoc-search-title">Ricerca Cronologia</h3>'
		. '<p class="assoc-search-count">Elementi trovati: ' . esc_html( (string) $total_items ) . '</p>'
		. '</div>'
		. '<p class="assoc-search-actions"><a class="button" href="' . esc_url( $base_url ) . '">Azzera</a></p>'
		. '</div>'
		. '<form method="get" action="' . esc_url( $base_url ) . '" class="assoc-search-form">'
		. '<p class="assoc-search-field is-type">'
		. '<label for="c_type">Tipo</label>'
		. '<select id="c_type" name="c_type">'
		. '<option value=""' . selected( $type, '', false ) . '>Tutti</option>'
		. '<option value="news"' . selected( $type, 'news', false ) . '>Notizie</option>'
		. '<option value="event"' . selected( $type, 'event', false ) . '>Eventi</option>'
		. '<option value="user"' . selected( $type, 'user', false ) . '>Utenti</option>'
		. '<option value="association"' . selected( $type, 'association', false ) . '>Associazioni</option>'
		. '</select>'
		. '</p>'
		. '<p class="assoc-search-field is-q">'
		. '<label for="c_q">Cerca</label>'
		. '<input type="text" id="c_q" name="c_q" value="' . esc_attr( $q ) . '" placeholder="Testo libero">'
		. '</p>'
		. '</form>'
		. '</div>';

	if ( function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
		echo culturacsi_portal_render_process_tutorial(
			array(
				'title'   => '',
				'summary' => 'Come usare questa sezione',
				'open'    => false,
				'steps'   => array(
					array( 'text' => 'Filtra per tipo e testo per ridurre i risultati.' ),
					array( 'text' => 'Clicca una riga per vedere i dettagli dell\'azione.' ),
					array( 'text' => 'Usa Esporta CSV per analisi esterne.' ),
				),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	
	$export_url = function_exists( 'culturacsi_export_build_url' ) ? culturacsi_export_build_url( 'cronologia', (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ) : (string) add_query_arg( 'culturacsi_export', 'cronologia', $_SERVER['REQUEST_URI'] ?? '' );
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Cronologia (Audit Log)</h2>';
	echo '<div style="display:flex;gap:10px;"><a class="button" style="background-color: #22c55e; color: white; border-color: #16a34a;" href="' . esc_url( $export_url ) . '">Esporta CSV</a></div></div>';
	echo '<table class="widefat striped assoc-admin-table assoc-table-cronologia"><colgroup><col style="width:3.5ch"><col style="width:11rem"><col style="width:30%"><col style="width:12rem"><col style="width:10rem"><col style="width:7rem"></colgroup><thead><tr>';
	echo culturacsi_portal_sortable_th( 'ID', 'id', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-index', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'Data e Ora', 'created_at', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-date', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'Utente', 'user_name', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-user', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'Azione', 'action', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-status', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'Tipo', 'object_type', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-type', array( 'c_page' ) );
	echo culturacsi_portal_sortable_th( 'ID Ogg.', 'object_id', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-index', array( 'c_page' ) );
	echo '</tr></thead><tbody>';
	
	if ( ! empty( $logs ) ) {
		foreach ( $logs as $log ) {
			$log_id  = (int) $log->id;
			$detail_id = 'cron-detail-' . $log_id;

			$action_labels = array(
				'create_post'    => 'CREATO',
				'update_post'    => 'MODIFICATO',
				'trash_post'     => 'ELIMINATO',
				'wp_insert_user' => 'REGISTRATO',
				'update_user'    => 'MODIFICATO',
				'approve'        => 'APPROVATO',
				'reject'         => 'RIFIUTATO',
				'hold'           => 'IN ATTESA',
				'login'          => 'LOGIN',
			);
			$display_action = $action_labels[ $log->action ] ?? strtoupper( $log->action );

			$display_type = match( $log->object_type ) {
				'event'       => 'Evento',
				'news'        => 'Notizia',
				'user'        => 'Utente',
				'association' => 'Associazione',
				default       => esc_html( $log->object_type ),
			};

			$obj_link = '';
			if ( (int) $log->object_id > 0 ) {
				$obj_link = match( $log->object_type ) {
					'user'        => culturacsi_portal_admin_user_form_url( $log->object_id ),
					'association' => culturacsi_portal_admin_association_form_url( $log->object_id ),
					'event'       => home_url( '/area-riservata/eventi/nuovo/?event_id=' . $log->object_id ),
					'news'        => home_url( '/area-riservata/notizie/nuova/?news_id=' . $log->object_id ),
					default       => '',
				};
			}

			// ── Main summary row (clickable) ──────────────────────────────
			echo '<tr class="cron-data-row" data-target="' . esc_attr( $detail_id ) . '" aria-expanded="false">';
			echo '<td>' . esc_html( $log->id ) . '</td>';
			echo '<td style="white-space:nowrap;">' . esc_html( mysql2date( 'd/m/Y H:i', $log->created_at ) ) . '</td>';
			// Robust user display fallback with small in-memory cache
			static $usr_cache = array();
			$user_cell = '';
			if ( ! empty( $log->user_name ) ) {
				$user_cell = (string) $log->user_name;
			} elseif ( (int) $log->user_id > 0 ) {
				$uid = (int) $log->user_id;
				if ( ! isset( $usr_cache[ $uid ] ) ) {
					$u = get_userdata( $uid );
					$usr_cache[ $uid ] = ( $u instanceof WP_User ) ? ( $u->display_name ?: $u->user_login ?: ( 'ID ' . $uid ) ) : ( 'ID ' . $uid );
				}
				$user_cell = $usr_cache[ $uid ];
			} else {
				$user_cell = 'Sistema';
			}
			echo '<td>' . esc_html( $user_cell ) . '</td>';
			echo '<td><span class="assoc-status-pill">' . esc_html( $display_action ) . '</span></td>';
			echo '<td>' . esc_html( $display_type ) . '</td>';
			echo '<td style="color:#64748b;">' . ( (int) $log->object_id > 0 ? '#' . esc_html( $log->object_id ) : '—' ) . '</td>';
			echo '</tr>';

			// ── Detail panel row (hidden until click) ────────────────────
			echo '<tr class="cron-detail-row" id="' . esc_attr( $detail_id ) . '">';
			echo '<td colspan="6">';
			echo '<dl class="cron-detail-inner">';

			echo '<div><dt>Data e ora</dt><dd>' . esc_html( mysql2date( 'd/m/Y H:i:s', $log->created_at ) ) . '</dd></div>';
			echo '<div><dt>Utente</dt><dd>' . esc_html( $log->user_name ?: ( $log->user_id ? 'ID ' . $log->user_id : '—' ) ) . '</dd></div>';
			echo '<div><dt>Indirizzo IP</dt><dd>' . esc_html( $log->ip_address ?: '—' ) . '</dd></div>';
			echo '<div><dt>Azione</dt><dd>' . esc_html( $action_labels[ $log->action ] ?? $log->action ) . '</dd></div>';
			echo '<div><dt>Tipo oggetto</dt><dd>' . esc_html( $display_type ) . '</dd></div>';

			if ( (int) $log->object_id > 0 ) {
				$link_html = $obj_link
					? '<a href="' . esc_url( $obj_link ) . '">#' . esc_html( $log->object_id ) . ' &rarr; Apri</a>'
					: '#' . esc_html( $log->object_id );
				echo '<div><dt>ID oggetto</dt><dd>' . $link_html . '</dd></div>'; // phpcs:ignore
			}

			if ( '' !== trim( (string) $log->details ) ) {
				echo '<div style="grid-column:1/-1"><dt>Dettagli</dt><dd>' . esc_html( $log->details ) . '</dd></div>';
			}

			echo '</dl>';
			echo '</td></tr>';
		}
	} else {
		echo '<tr><td colspan="6">Nessuna cronologia trovata.</td></tr>';
	}
	echo '</tbody></table>';

	if ( $max_pages > 1 ) {
		echo '<div class="assoc-pagination" style="margin-top:20px; text-align:right;">';
		$add_args = array();
		if ( '' !== $q ) { $add_args['c_q'] = $q; }
		if ( '' !== $type ) { $add_args['c_type'] = $type; }
		$page_links = paginate_links( array(
			'base'      => add_query_arg( 'c_page', '%#%', $base_url ),
			'format'    => '',
			'prev_text' => '&laquo; Precedente',
			'next_text' => 'Successivo &raquo;',
			'total'     => $max_pages,
			'current'   => $current_page,
			'add_args'  => $add_args,
		) );
		if ( $page_links ) {
			echo $page_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';
	}
	
	echo '</div>';
	return ob_get_clean();
}
