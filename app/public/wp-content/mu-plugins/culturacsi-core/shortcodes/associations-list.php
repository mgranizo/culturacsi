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
		// Pre-compute sort keys to prevent N*log(N) taxonomy lookups during usort
		$sort_keys = array();
		if ( 'category' === $sort_state['sort'] ) {
			foreach ( $posts as $p ) {
				if ( ! $p instanceof WP_Post ) continue;
				$activity_labels = function_exists( 'culturacsi_activity_labels_for_post' ) ? culturacsi_activity_labels_for_post( (int) $p->ID ) : array();
				$sort_keys[ $p->ID ] = ! empty( $activity_labels ) ? implode( ', ', $activity_labels ) : '';
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
	$export_url = function_exists( 'culturacsi_export_build_url' ) ? culturacsi_export_build_url( 'association', (string) ( $_SERVER['REQUEST_URI'] ?? '' ) ) : (string) add_query_arg( 'culturacsi_export', 'association', $_SERVER['REQUEST_URI'] ?? '' );
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Associazioni</h2><div style="display:flex;gap:10px;"><a class="button" style="background-color: #22c55e; color: white; border-color: #16a34a;" href="' . esc_url( $export_url ) . '">Esporta CSV</a> ';
	if ( $is_site_admin ) {
		echo '<a class="button button-primary" href="' . esc_url( culturacsi_portal_admin_association_form_url() ) . '">Crea Associazione</a>';
		echo ' <button type="button" id="abf-open-struct-modal-portal" class="button">Struttura Settori</button>';
	} else {
		echo '<a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/profilo/' ) ) . '">Dati Associazione</a>';
	}
		echo '</div></div>';
		// Inline modal bootstrap script for portal editor (site admin only)
		$nonce        = wp_create_nonce( 'abf_settori_tree' );
		$ajaxurl_json = wp_json_encode( admin_url( 'admin-ajax.php' ) );
		$nonce_json   = wp_json_encode( $nonce );
		echo <<<HTML
<script>(function(){
	var openBtn=document.getElementById("abf-open-struct-modal-portal"); if(!openBtn) return;
	var modal=null, overlay=null, content=null, footer=null, closeBtn=null, titleEl=null;
	var ajaxurl = window.ajaxurl || {$ajaxurl_json};
	var nonce = {$nonce_json};
	var tree = { macros: [] };
	var nodeUidCounter = 1;
	var pendingFocusId = "";

	function qn(t){ return document.createElement(t); }
	function nextNodeUid(){ return "n" + (nodeUidCounter++); }
	function ensureNodeUid(node){
		if(!node || typeof node !== "object") return "";
		if(!node._uid) node._uid = nextNodeUid();
		return node._uid;
	}
	function compareLabels(a, b){
		var al = (a || "").trim();
		var bl = (b || "").trim();
		return al.localeCompare(bl, "it", { sensitivity: "base", numeric: true });
	}
	function queueFocus(node){
		pendingFocusId = (node && node._uid) ? String(node._uid) : "";
	}
	function applyPendingFocus(){
		if(!pendingFocusId || !content) return;
		var focusTarget = null;
		var candidates = content.querySelectorAll("[data-node-id]");
		for(var i = 0; i < candidates.length; i++){
			if((candidates[i].getAttribute("data-node-id") || "") === pendingFocusId){
				focusTarget = candidates[i];
				break;
			}
		}
		pendingFocusId = "";
		if(!focusTarget) return;
		try { focusTarget.focus({ preventScroll: true }); } catch(e) { focusTarget.focus(); }
		if(typeof focusTarget.select === "function") focusTarget.select();
		try { focusTarget.scrollIntoView({ behavior: "smooth", block: "center", inline: "nearest" }); }
		catch(e){ focusTarget.scrollIntoView(); }
	}

	function makeSettore(label){
		return { _uid: nextNodeUid(), label: label || "", settori2: [ { _uid: nextNodeUid(), label: "" } ] };
	}

	function makeMacro(label){
		return { _uid: nextNodeUid(), label: label || "", settori: [ makeSettore("") ] };
	}

	function addMacroAfter(index, label){
		var macros = tree.macros = Array.isArray(tree.macros) ? tree.macros : [];
		var next = makeMacro(label || "");
		if (index < 0 || index >= macros.length) {
			macros.push(next);
		} else {
			macros.splice(index + 1, 0, next);
		}
		return next;
	}

	function addMacroAlphabetical(label){
		var macros = tree.macros = Array.isArray(tree.macros) ? tree.macros : [];
		var wanted = (label || "").trim();
		if(wanted === ""){
			var next = makeMacro("");
			macros.push(next);
			return next;
		}
		for(var j = 0; j < macros.length; j++){
			var existingLabel = (macros[j] && macros[j].label) ? String(macros[j].label).trim() : "";
			if(existingLabel !== "" && compareLabels(wanted, existingLabel) === 0){
				return macros[j];
			}
		}
		var next = makeMacro(wanted);
		var insertAt = macros.length;
		for(var i = 0; i < macros.length; i++){
			var currentLabel = (macros[i] && macros[i].label) ? String(macros[i].label).trim() : "";
			if(currentLabel === "") continue;
			if(compareLabels(wanted, currentLabel) < 0){
				insertAt = i;
				break;
			}
		}
		macros.splice(insertAt, 0, next);
		return next;
	}

	function addSettoreAfter(macroNode, index, label){
		if (!macroNode || typeof macroNode !== "object") return;
		var settori = macroNode.settori = Array.isArray(macroNode.settori) ? macroNode.settori : [];
		var next = makeSettore(label || "");
		if (index < 0 || index >= settori.length) {
			settori.push(next);
		} else {
			settori.splice(index + 1, 0, next);
		}
		return next;
	}

	function addSettore2After(settoreNode, index, label){
		if (!settoreNode || typeof settoreNode !== "object") return;
		var leaves = settoreNode.settori2 = Array.isArray(settoreNode.settori2) ? settoreNode.settori2 : [];
		var next = { _uid: nextNodeUid(), label: label || "" };
		if (index < 0 || index >= leaves.length) {
			leaves.push(next);
		} else {
			leaves.splice(index + 1, 0, next);
		}
		return next;
	}

	function normalizeTreeShape(){
		if (!tree || typeof tree !== "object") tree = { macros: [] };
		if (!Array.isArray(tree.macros)) tree.macros = [];
		tree.macros = tree.macros.map(function(macro){
			macro = (macro && typeof macro === "object") ? macro : {};
			ensureNodeUid(macro);
			macro.label = (macro.label || "");
			if (!Array.isArray(macro.settori)) macro.settori = [];
			macro.settori = macro.settori.map(function(settore){
				settore = (settore && typeof settore === "object") ? settore : {};
				ensureNodeUid(settore);
				settore.label = (settore.label || "");
				if (!Array.isArray(settore.settori2)) settore.settori2 = [];
				settore.settori2 = settore.settori2.map(function(leaf){
					leaf = (leaf && typeof leaf === "object") ? leaf : {};
					ensureNodeUid(leaf);
					leaf.label = (leaf.label || "");
					return leaf;
				});
				return settore;
			});
			return macro;
		});
	}

	function makeIconButton(sign, title, className){
		var b = qn("button");
		b.type = "button";
		b.className = "assoc-struct-icon-btn " + (className || "");
		b.title = title || "";
		b.setAttribute("aria-label", title || "");
		b.textContent = sign;
		return b;
	}

	function ensureEditorStyles(){
		var styleId = "assoc-portal-struct-editor-style";
		if (document.getElementById(styleId)) return;
		var st = document.createElement("style");
		st.id = styleId;
		st.textContent = [
			".assoc-struct-editor{--ab-blue-900:#123a6d;--ab-blue-800:#1f4f8e;--ab-blue-700:#2b67ad;--ab-blue-300:#b9d2ee;--ab-blue-200:#d4e4f5;--ab-blue-100:#ebf3fb;display:grid;gap:8px;color:#11263f;font-size:13px;line-height:1.2}",
			".assoc-struct-row{display:grid;grid-template-columns:minmax(220px,1fr) auto minmax(150px,180px);align-items:center;gap:8px;padding:5px 8px;border:1px solid var(--ab-blue-300);border-radius:10px}",
			".assoc-struct-row-macro{background:#e8f0fa;border-color:#a8c4e6}",
			".assoc-struct-row-settore{background:#f1f6fd;border-color:#c2d8ef}",
			".assoc-struct-row-settore2{background:#f7fbff;border-color:#d5e4f4}",
			".assoc-struct-input{width:100%;max-width:100%;min-height:30px;padding:4px 10px;border:1px solid #9fbce0;border-radius:999px;background:#fff;color:#0f2744;font-size:12px;font-weight:600}",
			".assoc-struct-input::placeholder{color:#516a86;opacity:1;font-weight:500}",
			".assoc-struct-actions{display:inline-flex;gap:4px;justify-self:center}",
			".assoc-struct-icon-btn{width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #7ea5cf;border-radius:7px;background:var(--ab-blue-700);color:#fff;font-size:19px;line-height:1;font-weight:800;cursor:pointer;padding:0}",
			".assoc-struct-icon-btn:hover,.assoc-struct-icon-btn:focus{background:var(--ab-blue-800);border-color:#628ebd;color:#fff}",
			".assoc-struct-icon-btn.is-minus{background:#ecf2f9;color:#1e456f;border-color:#98b6d8}",
			".assoc-struct-icon-btn.is-minus:hover,.assoc-struct-icon-btn.is-minus:focus{background:#dbe8f6;color:#163a61;border-color:#7d9fc6}",
			".assoc-struct-level{justify-self:end;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#365579}",
			".assoc-struct-level.assoc-level-macro{color:#1b4675}",
			".assoc-struct-level.assoc-level-settore{color:#2b5f98}",
			".assoc-struct-level.assoc-level-settore2{color:#4b6788}",
			".assoc-struct-card{border:1px solid #b9d0ea;border-radius:12px;background:#fff;overflow:hidden}",
			".assoc-struct-children{display:grid;gap:6px;padding:6px 8px 8px 8px}",
			".assoc-struct-children-macro{border-top:1px solid #d7e5f5;background:#fcfeff}",
			".assoc-struct-settore-wrap{display:grid;gap:6px;padding-left:14px;border-left:2px solid #d3e3f4;margin-left:8px}",
			".assoc-struct-settore2-wrap{display:grid;gap:5px;padding-left:14px;border-left:2px solid #e1ebf7;margin-left:8px}",
			".assoc-struct-empty{padding:7px 9px;border:1px dashed #bed1e7;border-radius:8px;color:#35506f;background:#f5f9fe;font-size:12px}",
			".assoc-struct-top-add{display:grid;grid-template-columns:minmax(220px,1fr) auto;gap:8px;align-items:center}",
			".assoc-struct-top-add .button{min-height:30px;line-height:28px;padding:0 12px;font-weight:700}",
			".assoc-struct-quick-add{display:inline-flex;align-items:center;justify-content:flex-start;gap:6px}",
			".assoc-struct-quick-add .button{min-height:28px;line-height:26px;padding:0 10px;font-size:12px}",
			"@media (max-width:900px){.assoc-struct-row{grid-template-columns:1fr auto;grid-template-areas:'input actions' 'level level'}.assoc-struct-row .assoc-struct-input{grid-area:input}.assoc-struct-row .assoc-struct-actions{grid-area:actions}.assoc-struct-row .assoc-struct-level{grid-area:level;justify-self:start}.assoc-struct-top-add{grid-template-columns:1fr}.assoc-struct-settore-wrap,.assoc-struct-settore2-wrap{padding-left:8px;margin-left:2px}}"
		].join("");
		document.head.appendChild(st);
	}

	function ensureModal(){
		if (modal && content && footer && titleEl) return true;
		ensureEditorStyles();
		var styleId="assoc-portal-modal-inline-style";
		if(!document.getElementById(styleId)){
			var mst=document.createElement("style"); mst.id=styleId;
			mst.textContent=".assoc-modal{display:none;position:fixed;inset:0;z-index:10000;align-items:center;justify-content:center;padding:8px}.assoc-modal.is-open{display:flex}.assoc-modal-overlay{position:absolute;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px)}.assoc-modal-container{position:relative;background:#fff;width:min(1180px,98vw);max-height:96vh;border-radius:14px;box-shadow:0 18px 38px -12px rgba(0,0,0,.32);overflow:hidden;display:flex;flex-direction:column}.assoc-modal-header{padding:9px 12px;border-bottom:1px solid #dbe4f0;display:flex;align-items:center;justify-content:space-between;background:#f8fafc}.assoc-modal-title{margin:0;font-size:1.05rem;font-weight:800;color:#0f172a}.assoc-modal-close{background:transparent;border:0;cursor:pointer;padding:4px 8px;border-radius:8px;color:#334155}.assoc-modal-close:hover{background:#e8eef8;color:#0f172a}.assoc-modal-content{padding:8px;overflow-y:auto;flex-grow:1}.assoc-modal-footer{padding:8px;border-top:1px solid #dbe4f0;background:#f8fafc;display:flex;justify-content:flex-end;gap:8px}";
			document.head.appendChild(mst);
		}
		modal=document.getElementById("assoc-portal-modal");
		if(!modal){
			modal=document.createElement("div"); modal.id="assoc-portal-modal"; modal.className="assoc-modal";
			modal.innerHTML="<div class=\"assoc-modal-overlay\"></div><div class=\"assoc-modal-container\"><header class=\"assoc-modal-header\"><h2 class=\"assoc-modal-title\" id=\"assoc-modal-title\">Dettagli</h2><button class=\"assoc-modal-close\" aria-label=\"Chiudi\">&#215;</button></header><main class=\"assoc-modal-content\" id=\"assoc-modal-content\"></main><footer class=\"assoc-modal-footer\" id=\"assoc-modal-footer\"></footer></div>";
			document.body.appendChild(modal);
		}
		overlay=modal.querySelector(".assoc-modal-overlay");
		content=document.getElementById("assoc-modal-content");
		footer=document.getElementById("assoc-modal-footer");
		closeBtn=modal.querySelector(".assoc-modal-close");
		titleEl=document.getElementById("assoc-modal-title");
		if(overlay){ overlay.addEventListener("click", close); }
		if(closeBtn){ closeBtn.addEventListener("click", close); }
		document.addEventListener("keydown", function(ev){ if(ev.key==="Escape" && modal && modal.classList.contains("is-open")) close(); });
		return true;
	}

	function open(){
		if(!ensureModal()) return;
		modal.classList.add("is-open");
		titleEl.textContent="Struttura Settori";
		loadTree();
	}

	function close(){
		if(!ensureModal()) return;
		modal.classList.remove("is-open");
		content.innerHTML="";
		footer.innerHTML="";
	}

	openBtn.addEventListener("click", open);

	function loadTree(){
		if(!ensureModal()) return;
		content.textContent = "Caricamento...";
		var form = new FormData();
		form.append("action","abf_get_settori_tree");
		form.append("_wpnonce", nonce);
		fetch(ajaxurl,{method:"POST", body:form}).then(function(r){ return r.json(); }).then(function(data){
			if(!data || !data.success){ content.textContent=(data && data.message)||"Errore"; return; }
			tree = data.data || {macros:[]};
			normalizeTreeShape();
			render();
		}).catch(function(){ content.textContent="Errore rete"; });
	}

	function render(){
		if(!ensureModal()) return;
		normalizeTreeShape();
		content.innerHTML="";
		footer.innerHTML="";

		var wrap = qn("div");
		wrap.className = "assoc-struct-editor";

		var addMacro = qn("div");
		addMacro.className = "assoc-struct-top-add";
		var macroInput = qn("input");
		macroInput.type = "text";
		macroInput.className = "assoc-struct-input";
		macroInput.placeholder = "Nuova macro categoria";
		var macroAddBtn = qn("button");
		macroAddBtn.type = "button";
		macroAddBtn.className = "button button-primary";
		macroAddBtn.textContent = "Aggiungi Macro";
		macroAddBtn.addEventListener("click", function(){
			var v = (macroInput.value||"").trim();
			if(!v) return;
			var createdMacro = addMacroAlphabetical(v);
			queueFocus(createdMacro);
			macroInput.value = "";
			render();
		});
		addMacro.appendChild(macroInput);
		addMacro.appendChild(macroAddBtn);
		wrap.appendChild(addMacro);

		if(!Array.isArray(tree.macros) || tree.macros.length===0){
			var empty = qn("div");
			empty.className = "assoc-struct-empty";
			empty.textContent = "Nessuna macro categoria. Aggiungi la prima voce.";
			wrap.appendChild(empty);
		}

		(tree.macros||[]).forEach(function(m,mi){
			var macroCard = qn("section");
			macroCard.className = "assoc-struct-card";

			var macroHead = qn("div");
			macroHead.className = "assoc-struct-row assoc-struct-row-macro";
			var macroEdit = qn("input");
			macroEdit.type = "text";
			macroEdit.className = "assoc-struct-input";
			macroEdit.setAttribute("data-node-id", String(ensureNodeUid(m)));
			macroEdit.value = m.label || "";
			macroEdit.addEventListener("input", function(e){ tree.macros[mi].label = e.target.value; });
			var macroActions = qn("div");
			macroActions.className = "assoc-struct-actions";
			var macroAdd = makeIconButton("+", "Aggiungi nuova macro categoria", "is-plus");
			macroAdd.addEventListener("click", function(){
				var created = addMacroAfter(mi, "");
				queueFocus(created);
				render();
			});
			var macroDelete = makeIconButton("-", "Rimuovi macro categoria", "is-minus");
			macroDelete.addEventListener("click", function(){ tree.macros.splice(mi,1); render(); });
			macroActions.appendChild(macroAdd);
			macroActions.appendChild(macroDelete);
			var macroLevel = qn("span");
			macroLevel.className = "assoc-struct-level assoc-level-macro";
			macroLevel.textContent = "Macro Categoria";
			macroHead.appendChild(macroEdit);
			macroHead.appendChild(macroActions);
			macroHead.appendChild(macroLevel);
			macroCard.appendChild(macroHead);

			var macroBranch = qn("div");
			macroBranch.className = "assoc-struct-children assoc-struct-children-macro";

			if(!Array.isArray(m.settori) || m.settori.length===0){
				var emptySettori = qn("div");
				emptySettori.className = "assoc-struct-empty";
				emptySettori.textContent = "Nessun settore in questa macro.";
				var quickAddSettoreWrap = qn("div");
				quickAddSettoreWrap.className = "assoc-struct-quick-add";
				var quickAddSettore = qn("button");
				quickAddSettore.type = "button";
				quickAddSettore.className = "button button-small";
				quickAddSettore.textContent = "Aggiungi primo settore";
				quickAddSettore.addEventListener("click", function(){
					var createdFirstSettore = addSettoreAfter(m, -1, "");
					queueFocus(createdFirstSettore);
					render();
				});
				quickAddSettoreWrap.appendChild(quickAddSettore);
				macroBranch.appendChild(emptySettori);
				macroBranch.appendChild(quickAddSettoreWrap);
			}

			(m.settori||[]).forEach(function(s,si){
				var settoreNode = qn("div");
				settoreNode.className = "assoc-struct-settore-wrap";

				var settoreHead = qn("div");
				settoreHead.className = "assoc-struct-row assoc-struct-row-settore";
				var settoreEdit = qn("input");
				settoreEdit.type = "text";
				settoreEdit.className = "assoc-struct-input";
				settoreEdit.setAttribute("data-node-id", String(ensureNodeUid(s)));
				settoreEdit.value = s.label || "";
				settoreEdit.addEventListener("input", function(e){ s.label = e.target.value; });
				var settoreActions = qn("div");
				settoreActions.className = "assoc-struct-actions";
				var settoreAdd = makeIconButton("+", "Aggiungi nuovo settore", "is-plus");
				settoreAdd.addEventListener("click", function(){
					var createdSettore = addSettoreAfter(m, si, "");
					queueFocus(createdSettore);
					render();
				});
				var settoreDelete = makeIconButton("-", "Rimuovi settore", "is-minus");
				settoreDelete.addEventListener("click", function(){ m.settori.splice(si,1); render(); });
				settoreActions.appendChild(settoreAdd);
				settoreActions.appendChild(settoreDelete);
				var settoreLevel = qn("span");
				settoreLevel.className = "assoc-struct-level assoc-level-settore";
				settoreLevel.textContent = "Settore";
				settoreHead.appendChild(settoreEdit);
				settoreHead.appendChild(settoreActions);
				settoreHead.appendChild(settoreLevel);
				settoreNode.appendChild(settoreHead);

				var leafList = qn("div");
				leafList.className = "assoc-struct-settore2-wrap";
				if(!Array.isArray(s.settori2) || s.settori2.length===0){
					var emptyLeaves = qn("div");
					emptyLeaves.className = "assoc-struct-empty";
					emptyLeaves.textContent = "Nessun settore 2 in questo settore.";
					var quickAddSettore2Wrap = qn("div");
					quickAddSettore2Wrap.className = "assoc-struct-quick-add";
					var quickAddSettore2 = qn("button");
					quickAddSettore2.type = "button";
					quickAddSettore2.className = "button button-small";
					quickAddSettore2.textContent = "Aggiungi primo settore 2";
					quickAddSettore2.addEventListener("click", function(){
						var createdSettore2 = addSettore2After(s, -1, "");
						queueFocus(createdSettore2);
						render();
					});
					quickAddSettore2Wrap.appendChild(quickAddSettore2);
					leafList.appendChild(emptyLeaves);
					leafList.appendChild(quickAddSettore2Wrap);
				}
				(s.settori2||[]).forEach(function(s2,s2i){
					var leaf = qn("div");
					leaf.className = "assoc-struct-row assoc-struct-row-settore2";
					var leafEdit = qn("input");
					leafEdit.type = "text";
					leafEdit.className = "assoc-struct-input";
					leafEdit.setAttribute("data-node-id", String(ensureNodeUid(s2)));
					leafEdit.value = s2.label || "";
					leafEdit.addEventListener("input", function(e){ s2.label = e.target.value; });
					var leafActions = qn("div");
					leafActions.className = "assoc-struct-actions";
					var leafAdd = makeIconButton("+", "Aggiungi nuovo settore 2", "is-plus");
					leafAdd.addEventListener("click", function(){
						var createdLeaf = addSettore2After(s, s2i, "");
						queueFocus(createdLeaf);
						render();
					});
					var leafDelete = makeIconButton("-", "Rimuovi settore 2", "is-minus");
					leafDelete.addEventListener("click", function(){ s.settori2.splice(s2i,1); render(); });
					leafActions.appendChild(leafAdd);
					leafActions.appendChild(leafDelete);
					var leafLevel = qn("span");
					leafLevel.className = "assoc-struct-level assoc-level-settore2";
					leafLevel.textContent = "Settore 2";
					leaf.appendChild(leafEdit);
					leaf.appendChild(leafActions);
					leaf.appendChild(leafLevel);
					leafList.appendChild(leaf);
				});
				settoreNode.appendChild(leafList);
				macroBranch.appendChild(settoreNode);
			});

			macroCard.appendChild(macroBranch);
			wrap.appendChild(macroCard);
		});

		content.appendChild(wrap);

		var closeBtnFooter = qn("button");
		closeBtnFooter.type = "button";
		closeBtnFooter.className = "button";
		closeBtnFooter.textContent = "Chiudi";
		closeBtnFooter.addEventListener("click", close);

		var saveBtn = qn("button");
		saveBtn.type = "button";
		saveBtn.className = "button button-primary";
		saveBtn.textContent = "Salva Struttura";
		saveBtn.addEventListener("click", save);

		footer.appendChild(closeBtnFooter);
		footer.appendChild(saveBtn);
		applyPendingFocus();
	}

	function serialize(){
		var out={};
		(tree.macros||[]).forEach(function(m){
			var ml=(m.label||"").trim(); if(!ml) return;
			if(!out[ml]) out[ml]={};
			(m.settori||[]).forEach(function(s){
				var sl=(s.label||"").trim(); if(!sl) return;
				if(!out[ml][sl]) out[ml][sl]=[];
				(s.settori2||[]).forEach(function(s2){
					var s2l=(s2.label||"").trim(); if(!s2l) return;
					out[ml][sl].push(s2l);
				});
			});
		});
		return out;
	}

	function save(){
		var form=new FormData();
		form.append("action","abf_save_settori_tree");
		form.append("_wpnonce", nonce);
		form.append("manual_nodes", JSON.stringify(serialize()));
		fetch(ajaxurl,{method:"POST", body:form}).then(function(r){ return r.json(); }).then(function(data){
			if(!data||!data.success){ alert((data&&data.message)||"Errore salvataggio"); return; }
			alert("Struttura salvata.");
			close();
		}).catch(function(){ alert("Errore rete"); });
	}
})();</script>
HTML;
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
			$activity_labels = function_exists( 'culturacsi_activity_labels_for_post' ) ? culturacsi_activity_labels_for_post( $post_id ) : array();
			if ( ! empty( $activity_labels ) ) {
				$category = implode( ', ', $activity_labels );
			} else {
				$category = '-';
			}
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
			$seen_location  = array();
			$push_location  = static function( string $label, string $value ) use ( &$location_parts, &$seen_location ): void {
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
			$edit_url    = $is_site_admin ? culturacsi_portal_admin_association_form_url( (int) $post_id ) : home_url( '/area-riservata/profilo/' );

			$row_class  = ( $is_site_admin && 'pending' === get_post_status( $post_id ) ) ? ' is-pending-approval' : '';
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
