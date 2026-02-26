<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
		'posts_per_page' => 1000,
		'paged'          => $current_page,
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
		// Pre-compute sort keys to prevent N*log(N) taxonomy lookups during usort
		$sort_keys = array();
		if ( 'category' === $sort_state['sort'] ) {
			foreach ( $posts as $p ) {
				if ( ! $p instanceof WP_Post ) continue;
				$terms = get_the_terms( (int) $p->ID, 'activity_category' );
				if ( is_array( $terms ) && ! is_wp_error( $terms ) ) {
					$names = wp_list_pluck( $terms, 'name' );
					$sort_keys[ $p->ID ] = implode( ', ', array_map( 'sanitize_text_field', $names ) );
				} else {
					$sort_keys[ $p->ID ] = '';
				}
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
						static $h_cache_a = array();
						if(!isset($h_cache_a[$a->ID])) { $m=culturacsi_logging_get_last_modified('association',$a->ID); $c=culturacsi_logging_get_creator('association',$a->ID); $h_cache_a[$a->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($a->post_date)); }
						if(!isset($h_cache_a[$b->ID])) { $m=culturacsi_logging_get_last_modified('association',$b->ID); $c=culturacsi_logging_get_creator('association',$b->ID); $h_cache_a[$b->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($b->post_date)); }
						$cmp = $h_cache_a[$a->ID] <=> $h_cache_a[$b->ID];
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
	$total_items = count( $posts );
	$max_pages   = max( 1, (int) ceil( $total_items / $per_page ) );
	$current_page = min( $current_page, $max_pages );
	$offset      = ( $current_page - 1 ) * $per_page;
	$paged_posts = array_slice( $posts, $offset, $per_page );
	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="assoc-portal-associations-list assoc-portal-section">';
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Associazioni</h2><div style="display:flex;gap:10px;"><a class="button" style="background-color: #22c55e; color: white; border-color: #16a34a;" href="' . esc_url( add_query_arg( 'culturacsi_export', 'association', $_SERVER['REQUEST_URI'] ?? '' ) ) . '">Esporta CSV</a> ';
	if ( $is_site_admin ) {
		echo '<a class="button button-primary" href="' . esc_url( culturacsi_portal_admin_association_form_url() ) . '">Crea Associazione</a>';
		echo ' <button type="button" id="abf-open-struct-modal-portal" class="button">Struttura Settori</button>';
	} else {
		echo '<a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/profilo/' ) ) . '">Dati Associazione</a>';
	}
		echo '</div></div>';
		// Inline modal bootstrap script for portal editor (site admin only)
		$nonce = wp_create_nonce( 'abf_settori_tree' );
		echo '<script>(function(){
			var openBtn=document.getElementById("abf-open-struct-modal-portal"); if(!openBtn)return;
			var modal=null, overlay=null, content=null, footer=null, closeBtn=null, titleEl=null;
			var ajaxurl = window.ajaxurl || ' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ';
			var nonce = ' . wp_json_encode( $nonce ) . ';

			function ensureModal(){
				if (modal && content && footer && titleEl) return true;
				modal=document.getElementById("assoc-portal-modal");
				if(!modal) return false;
				overlay=modal.querySelector(".assoc-modal-overlay");
				content=document.getElementById("assoc-modal-content");
				footer=document.getElementById("assoc-modal-footer");
				closeBtn=modal.querySelector(".assoc-modal-close");
				titleEl=document.getElementById("assoc-modal-title");
				if(overlay){ overlay.addEventListener("click", close); }
				if(closeBtn){ closeBtn.addEventListener("click", close); }
				return true;
			}

			function open(){ if(!ensureModal()) return; modal.style.display="block"; titleEl.textContent="Editor Struttura"; loadTree(); }
			function close(){ if(!ensureModal()) return; modal.style.display="none"; content.innerHTML=' . wp_json_encode( '' ) . '; footer.innerHTML=' . wp_json_encode( '' ) . '; }
			openBtn.addEventListener("click", function(){ open(); });

			var tree = { macros: [] };

			function loadTree(){
				if(!ensureModal()) return;
				content.textContent = "Caricamento…";
				var form = new FormData(); form.append("action","abf_get_settori_tree"); form.append("_wpnonce", nonce);
				fetch(ajaxurl,{method:"POST", body:form}).then(r=>r.json()).then(function(data){
					if(!data || !data.success){ content.textContent=(data && data.message)||"Errore"; return; }
					tree = data.data || {macros:[]}; render();
				}).catch(function(){ content.textContent="Errore rete"; });
			}

			function qn(t){return document.createElement(t)}
			function render(){
				if(!ensureModal()) return;
				content.innerHTML=' . wp_json_encode( '' ) . ';
				footer.innerHTML=' . wp_json_encode( '' ) . ';
				var wrap = qn("div");
				var row = qn("div"); row.style.marginBottom="10px";
				var mIn = qn("input"); mIn.type="text"; mIn.placeholder="Nuova Macro"; mIn.style.minWidth="260px";
				var mBtn = qn("button"); mBtn.className="button"; mBtn.textContent="Aggiungi Macro"; mBtn.addEventListener("click", function(){ var v=mIn.value.trim(); if(!v)return; tree.macros.push({label:v, settori:[]}); mIn.value=' . wp_json_encode( '' ) . '; render(); });
				row.appendChild(mIn); row.appendChild(mBtn); wrap.appendChild(row);

				(tree.macros||[]).forEach(function(m,mi){
					var box=qn("div"); box.style.border="1px solid #e5e5e5"; box.style.borderRadius="8px"; box.style.padding="10px"; box.style.marginBottom="12px";
					var head=qn("div"); head.style.display="flex"; head.style.gap="8px"; head.style.alignItems="center";
					var mE=qn("input"); mE.type="text"; mE.value=m.label||""; mE.style.minWidth="260px"; mE.addEventListener("input", function(e){ tree.macros[mi].label=e.target.value; });
					var mDel=qn("button"); mDel.className="button-link-delete"; mDel.textContent="Rimuovi Macro"; mDel.addEventListener("click", function(){ tree.macros.splice(mi,1); render(); });
					head.appendChild(mE); head.appendChild(mDel); box.appendChild(head);

					var addS=qn("div"); addS.style.margin="8px 0"; var sIn=qn("input"); sIn.type="text"; sIn.placeholder="Nuovo Settore"; sIn.style.minWidth="240px";
					var sBtn=qn("button"); sBtn.className="button"; sBtn.textContent="Aggiungi Settore"; sBtn.addEventListener("click", function(){ var v=sIn.value.trim(); if(!v)return; (m.settori=m.settori||[]).push({label:v, settori2:[]}); sIn.value=' . wp_json_encode( '' ) . '; render(); });
					addS.appendChild(sIn); addS.appendChild(sBtn); box.appendChild(addS);

					(m.settori||[]).forEach(function(s,si){
						var row=qn("div"); row.style.margin="6px 0 6px 16px";
						var sE=qn("input"); sE.type="text"; sE.value=s.label||""; sE.style.minWidth="220px"; sE.addEventListener("input", function(e){ s.label=e.target.value; });
						var sDel=qn("button"); sDel.className="button-link-delete"; sDel.textContent="Rimuovi Settore"; sDel.addEventListener("click", function(){ m.settori.splice(si,1); render(); });
						row.appendChild(sE); row.appendChild(sDel);
						var addS2=qn("div"); addS2.style.margin="4px 0 4px 16px";
						var s2In=qn("input"); s2In.type="text"; s2In.placeholder="Nuovo Settore 2"; s2In.style.minWidth="200px";
						var s2Btn=qn("button"); s2Btn.className="button"; s2Btn.textContent="Aggiungi Settore 2"; s2Btn.addEventListener("click", function(){ var v=s2In.value.trim(); if(!v)return; (s.settori2=s.settori2||[]).push({label:v}); s2In.value=' . wp_json_encode( '' ) . '; render(); });
						addS2.appendChild(s2In); addS2.appendChild(s2Btn); row.appendChild(addS2);

						(s.settori2||[]).forEach(function(s2,s2i){
							var r2=qn("div"); r2.style.margin="2px 0 2px 32px";
							var s2E=qn("input"); s2E.type="text"; s2E.value=s2.label||""; s2E.style.minWidth="200px"; s2E.addEventListener("input", function(e){ s2.label=e.target.value; });
							var s2Del=qn("button"); s2Del.className="button-link-delete"; s2Del.textContent="Rimuovi"; s2Del.addEventListener("click", function(){ s.settori2.splice(s2i,1); render(); });
							r2.appendChild(s2E); r2.appendChild(s2Del); row.appendChild(r2);
						});
						box.appendChild(row);
					});
					wrap.appendChild(box);
				});
				content.appendChild(wrap);
				var saveBtn=document.createElement("button"); saveBtn.className="button button-primary"; saveBtn.textContent="Salva"; saveBtn.addEventListener("click", save);
				footer.appendChild(saveBtn);
			}

			function serialize(){ var out={}; (tree.macros||[]).forEach(function(m){ var ml=(m.label||"").trim(); if(!ml) return; if(!out[ml]) out[ml]={}; (m.settori||[]).forEach(function(s){ var sl=(s.label||"").trim(); if(!sl) return; if(!out[ml][sl]) out[ml][sl]=[]; (s.settori2||[]).forEach(function(s2){ var s2l=(s2.label||"").trim(); if(!s2l) return; out[ml][sl].push(s2l); }); }); }); return out; }

			function save(){ var form=new FormData(); form.append("action","abf_save_settori_tree"); form.append("_wpnonce", nonce); form.append("manual_nodes", JSON.stringify(serialize())); fetch(ajaxurl,{method:"POST", body:form}).then(r=>r.json()).then(function(data){ if(!data||!data.success){ alert((data&&data.message)||"Errore salvataggio"); return; } alert("Struttura salvata."); close(); }).catch(function(){ alert("Errore rete"); }); }
		})();</script>';
	echo '<style>.assoc-admin-table tr.is-pending-approval td { background-color: #fef2f2 !important; border-top: 2px solid #ef4444 !important; border-bottom: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:first-child { border-left: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:last-child { border-right: 2px solid #ef4444 !important; }</style>';
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
			$post_id     = (int) $post_item->ID;
			$status_obj  = get_post_status_object( get_post_status( $post_id ) );
			$terms       = wp_get_post_terms( $post_id, 'activity_category', array( 'fields' => 'names' ) );
			$category    = ! empty( $terms ) ? implode( ', ', array_map( 'sanitize_text_field', $terms ) ) : '-';
			$city        = trim( (string) get_post_meta( $post_id, 'city', true ) );
			if ( '' === $city ) {
				$city = trim( (string) get_post_meta( $post_id, 'comune', true ) );
			}
			$province = trim( (string) get_post_meta( $post_id, 'province', true ) );
			$region   = trim( (string) get_post_meta( $post_id, 'region', true ) );
			if ( '' === $region ) {
				$region = trim( (string) get_post_meta( $post_id, 'regione', true ) );
			}
			$location_parts = array();
			if ( '' !== $city ) {
				$location_parts[] = 'Citta: ' . $city;
			}
			if ( '' !== $province ) {
				$location_parts[] = 'Prov: ' . strtoupper( $province );
			}
			if ( '' !== $region ) {
				$location_parts[] = 'Regione: ' . $region;
			}
			$location_summary = implode( ' | ', $location_parts );
			$edit_url    = $is_site_admin ? culturacsi_portal_admin_association_form_url( (int) $post_id ) : home_url( '/area-riservata/profilo/' );

			$row_class  = ( $is_site_admin && 'pending' === get_post_status( $post_id ) ) ? ' is-pending-approval' : '';
			echo '<tr class="' . esc_attr( trim( $row_class ) ) . '" data-id="' . esc_attr( (string) $post_id ) . '" data-type="association">';
			echo '<td class="assoc-col-index">' . esc_html( (string) $row_num ) . '</td>';
			echo '<td class="assoc-col-title"><span class="assoc-association-name">' . esc_html( get_the_title( $post_id ) ) . '</span>';
			if ( '' !== $location_summary ) {
				echo '<span class="assoc-association-location">' . esc_html( $location_summary ) . '</span>';
			}
			echo '</td>';
			echo '<td class="assoc-col-category">' . esc_html( $category ) . '</td>';
			echo '<td class="assoc-col-status assoc-col-status-compact"><span class="assoc-status-pill status-' . esc_attr( (string) get_post_status( $post_id ) ) . '">' . esc_html( $status_obj ? $status_obj->label : (string) get_post_status( $post_id ) ) . '</span></td>';
			
			// History column for Associations
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
				$is_published = 'publish' === (string) get_post_status( $post_id );
				$toggle_label = $is_published ? 'Rif.' : 'Appr.';
				$toggle_action = $is_published ? 'reject' : 'approve';
				$toggle_class = $is_published ? 'chip-reject chip-toggle' : 'chip-approve chip-toggle';
				echo culturacsi_portal_action_button_form( array( 'context' => 'association', 'action' => $toggle_action, 'target_id' => $post_id, 'label' => $toggle_label, 'class' => $toggle_class ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="5">Nessuna associazione trovata.</td></tr>';
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
