<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function culturacsi_portal_can_access(): bool {
	return is_user_logged_in() && ( current_user_can( 'association_manager' ) || current_user_can( 'manage_options' ) );
}

function culturacsi_portal_get_managed_association_id( int $user_id ): int {
	$assoc_id = (int) get_user_meta( $user_id, 'association_post_id', true );
	if ( $assoc_id > 0 && 'association' === get_post_type( $assoc_id ) ) {
		return $assoc_id;
	}
	return 0;
}

function culturacsi_portal_notice( string $message, string $type = 'success' ): string {
	$type = in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'success';
	return '<div class="assoc-admin-notice assoc-admin-notice-' . esc_attr( $type ) . '">' . esc_html( $message ) . '</div>';
}

function culturacsi_get_site_logo_url( string $size = 'full', bool $allow_site_icon = true ): string {
	$size = '' !== trim( $size ) ? $size : 'full';

	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id > 0 ) {
		$logo_url = wp_get_attachment_image_url( $logo_id, $size );
		if ( is_string( $logo_url ) && '' !== $logo_url ) {
			return $logo_url;
		}
	}

	if ( ! $allow_site_icon ) {
		return '';
	}

	$site_icon_id = (int) get_option( 'site_icon' );
	if ( $site_icon_id > 0 ) {
		$icon_url = wp_get_attachment_image_url( $site_icon_id, $size );
		if ( is_string( $icon_url ) && '' !== $icon_url ) {
			return $icon_url;
		}
	}

	return '';
}

function culturacsi_portal_panel_role_label(): string {
	if ( current_user_can( 'manage_options' ) ) {
		return 'Pannello di Controllo Site Admin';
	}
	if ( current_user_can( 'association_manager' ) ) {
		return 'Pannello di Controllo Association Admin';
	}
	return 'Area Riservata';
}

function culturacsi_portal_nav_item_is_active( string $item_url, string $current_path ): bool {
	$item_path = trim( (string) wp_parse_url( $item_url, PHP_URL_PATH ), '/' );
	if ( '' === $item_path ) {
		return false;
	}
	$current_with_slash = rtrim( $current_path, '/' ) . '/';
	$item_with_slash    = rtrim( $item_path, '/' ) . '/';
	return 0 === strpos( $current_with_slash, $item_with_slash );
}

function culturacsi_portal_reserved_nav_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}

	$is_admin = current_user_can( 'manage_options' );

	if ( $is_admin ) {
		// Site admin: eventi, notizie, utenti, associazioni
		$items = array(
			array( 'label' => 'Eventi',       'url' => home_url( '/area-riservata/eventi/' ) ),
			array( 'label' => 'Notizie',      'url' => home_url( '/area-riservata/notizie/' ) ),
			array( 'label' => 'Utenti',       'url' => home_url( '/area-riservata/utenti/' ) ),
			array( 'label' => 'Associazioni', 'url' => home_url( '/area-riservata/associazioni/' ) ),
		);
	} else {
		// Association manager: they manage one association.
		// Order: eventi, notizie, utenti (colleagues), association pages.
		$items = array(
			array( 'label' => 'Eventi',       'url' => home_url( '/area-riservata/eventi/' ) ),
			array( 'label' => 'Notizie',      'url' => home_url( '/area-riservata/notizie/' ) ),
			array( 'label' => 'Utenti',       'url' => home_url( '/area-riservata/utenti/' ) ),
			array( 'label' => 'Associazione', 'url' => home_url( '/area-riservata/associazione/' ) ),
		);
	}

	$current_path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	$logo_url     = culturacsi_get_site_logo_url( 'full', false );
	$role_label   = culturacsi_portal_panel_role_label();
	$nav_title = 'Area Riservata';

	if ( ! current_user_can( 'manage_options' ) ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id > 0 ) {
			$nav_title = get_the_title( $assoc_id );
		}
	}

	ob_start();
	echo '<nav class="assoc-reserved-nav">';
	echo '<div class="assoc-reserved-nav-head">';
	if ( '' !== $logo_url ) {
		echo '<div class="assoc-reserved-nav-top-gap" style="height:40px;" aria-hidden="true"></div>';
		echo '<a class="assoc-reserved-nav-brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr__( 'Home', 'culturacsi' ) . '"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"></a>';
	}
	echo '<a class="assoc-reserved-nav-logout" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Esci</a>';
	echo '</div>';
	echo '<div class="assoc-reserved-nav-title-wrap">';
	echo '<span class="assoc-reserved-nav-title" style="line-height:1.2;">' . esc_html( $nav_title ) . '</span>';
	if ( ! current_user_can( 'manage_options' ) ) {
		echo '<span class="assoc-reserved-nav-subtitle" style="margin-top:4px;">' . esc_html( $role_label ) . '</span>';
	} else {
		echo '<span class="assoc-reserved-nav-subtitle">' . esc_html( $role_label ) . '</span>';
	}
	echo '</div>';
	echo '<ul class="assoc-reserved-nav-list">';
	foreach ( $items as $item ) {
		$link_class = 'assoc-reserved-nav-link' . ( culturacsi_portal_nav_item_is_active( (string) $item['url'], $current_path ) ? ' is-active' : '' );
		echo '<li><a class="' . esc_attr( $link_class ) . '" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
	}
	echo '</ul></nav>';
	return ob_get_clean();
}

function culturacsi_portal_dashboard_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}
	ob_start();
	echo '<div class="assoc-portal-dashboard">';
	echo '<h2>Area Riservata</h2>';
	echo '<p>Seleziona una sezione dal menu.</p>';
	echo '</div>';
	return ob_get_clean();
}
add_action( 'wp_footer', 'culturacsi_portal_modal_html' );

function culturacsi_portal_modal_html(): void {
	if ( is_admin() || ! culturacsi_portal_can_access() ) return;
	?>
	<div id="assoc-portal-modal" class="assoc-modal">
		<div class="assoc-modal-overlay"></div>
		<div class="assoc-modal-container">
			<header class="assoc-modal-header">
				<h2 class="assoc-modal-title" id="assoc-modal-title">Dettagli</h2>
				<button class="assoc-modal-close"></button>
			</header>
			<main class="assoc-modal-content" id="assoc-modal-content"></main>
			<footer class="assoc-modal-footer" id="assoc-modal-footer"></footer>
		</div>
	</div>
	<script>window.assocPortalNonce = "<?php echo esc_js( wp_create_nonce( 'culturacsi_portal_ajax' ) ); ?>";</script>
	<?php
}

add_action( 'wp_ajax_culturacsi_get_modal_data', 'culturacsi_ajax_get_modal_data' );
function culturacsi_ajax_get_modal_data(): void {
	if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'culturacsi_portal_ajax' ) ) {
		wp_send_json_error( 'Sessione scaduta, ricarica la pagina.' );
	}
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
	if ( ! $id || ! $type ) wp_send_json_error( 'Parametri mancanti.' );

	ob_start();
	$footer = '';
	if ( 'user' === $type ) {
		$user = get_user_by( 'id', $id );
		if ( $user ) {
			$avatar_id = (int) get_user_meta( $id, 'assoc_user_avatar_id', true );
			$assoc_id = (int) get_user_meta( $id, 'association_post_id', true );
			$assoc_name = $assoc_id > 0 ? get_the_title( $assoc_id ) : '';
			
			echo '<div class="assoc-details-header" style="text-align:center;margin-bottom:20px;">';
			if ( $avatar_id ) echo wp_get_attachment_image( $avatar_id, array( 100, 100 ), false, array( 'class' => 'assoc-modal-avatar' ) );
			echo '<h3 style="margin-bottom:0;">' . esc_html( $user->display_name ) . '</h3>';
			if ( $assoc_name ) {
				echo '<p style="color:#2563eb; font-weight:600; margin:5px 0 0 0;">' . esc_html( $assoc_name ) . '</p>';
			}
			echo '<p style="color:#64748b;font-size:0.9rem; margin-top:5px;">@' . esc_html( $user->user_login ) . '</p>';
			echo '</div>';
			echo '<div class="assoc-details-grid">';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Email</span><span class="assoc-details-value">' . esc_html( $user->user_email ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Nome</span><span class="assoc-details-value">' . esc_html( $user->first_name ?: '-' ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Cognome</span><span class="assoc-details-value">' . esc_html( $user->last_name ?: '-' ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Ruolo</span><span class="assoc-details-value">' . esc_html( implode( ', ', $user->roles ) ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Registrato</span><span class="assoc-details-value">' . esc_html( date_i18n( 'd M Y H:i', strtotime( $user->user_registered ) ) ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Stato</span><span class="assoc-details-value">' . esc_html( culturacsi_portal_user_approval_label( $user ) ) . '</span></div>';
			
			// Show all other user meta
			$all_meta = get_user_meta( $id );
			$skip_keys = array( 'session_tokens', 'wp_capabilities', 'wp_user_level', 'dismissed_wp_pointers', 'assoc_user_avatar_id', 'first_name', 'last_name', 'use_ssl', 'comment_shortcuts', 'rich_editing', 'admin_color', 'show_admin_bar_front', 'wp_user_settings', 'wp_user_settings_time' );
			foreach ( $all_meta as $key => $values ) {
				if ( 0 === strpos( $key, 'wp_' ) || in_array( $key, $skip_keys, true ) ) continue;
				$val = $values[0];
				if ( is_serialized( $val ) ) continue;
				echo '<div class="assoc-details-item"><span class="assoc-details-label">' . esc_html( str_replace( '_', ' ', $key ) ) . '</span><span class="assoc-details-value">' . esc_html( (string)$val ) . '</span></div>';
			}
			echo '</div>';
			
			ob_start();
			echo '<div class="assoc-action-group">';
			if ( current_user_can( 'manage_options' ) ) {
				echo '<a class="assoc-action-chip chip-edit" href="' . esc_url( culturacsi_portal_admin_user_form_url( $id ) ) . '">Modifica</a>';
				$is_approved = culturacsi_portal_is_user_approved( $user );
				$toggle_label = $is_approved ? 'Sospendi' : 'Approva';
				$toggle_action = $is_approved ? 'hold' : 'approve';
				$toggle_class = $is_approved ? 'chip-reject' : 'chip-approve';
				echo culturacsi_portal_action_button_form( array( 'context' => 'user', 'action' => $toggle_action, 'target_id' => $id, 'label' => $toggle_label, 'class' => $toggle_class ) );
			}
			echo '</div>';
			$footer = ob_get_clean();
		}
	} else {
		$post = get_post( $id );
		if ( $post && $post->post_type === $type ) {
			echo '<div class="assoc-details-grid">';
			echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Titolo</span><span class="assoc-details-value" style="font-size:1.1rem;font-weight:700;">' . esc_html( $post->post_title ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Data</span><span class="assoc-details-value">' . esc_html( get_the_date( 'd/m/Y H:i', $post ) ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Stato</span><span class="assoc-details-value">' . esc_html( get_post_status_object( $post->post_status )->label ) . '</span></div>';
			
			// Show all custom fields (metadata)
			$all_meta = get_post_meta( $id );
			$skip_keys = array( '_edit_lock', '_edit_last', '_thumbnail_id', '_assoc_user_avatar_id' );
			foreach ( $all_meta as $key => $values ) {
				if ( 0 === strpos( $key, '_wp_' ) || in_array( $key, $skip_keys, true ) ) continue;
				$val = $values[0];
				if ( is_serialized( $val ) ) continue;
				// Clean up internal keys for display
				$display_key = ltrim( $key, '_' );
				echo '<div class="assoc-details-item"><span class="assoc-details-label">' . esc_html( str_replace( array('_', '-'), ' ', $display_key ) ) . '</span><span class="assoc-details-value">' . esc_html( (string)$val ) . '</span></div>';
			}

			if ( ! empty( $post->post_excerpt ) ) {
				echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Sommario</span><span class="assoc-details-value">' . wp_kses_post( $post->post_excerpt ) . '</span></div>';
			}
			echo '</div>';

			ob_start();
			echo '<div class="assoc-action-group">';
			$base_edit = ( 'association' === $type ) ? '/area-riservata/profilo-associazione/' : ( ( 'news' === $type ) ? '/area-riservata/notizie/nuova/' : '/area-riservata/eventi/nuovo/' );
			$edit_url = add_query_arg( array( ($type === 'association' ? 'association_id' : ($type === 'news' ? 'news_id' : 'event_id')) => $id ), home_url( $base_edit ) );
			echo '<a class="assoc-action-chip chip-edit" href="' . esc_url( $edit_url ) . '">Modifica</a>';
			if ( current_user_can( 'manage_options' ) ) {
				echo culturacsi_portal_action_button_form( array( 'context' => $type, 'action' => 'delete', 'target_id' => $id, 'label' => 'Elimina', 'class' => 'chip-delete', 'confirm' => true, 'confirm_text' => 'Confermi eliminazione?' ) );
			}
			echo '</div>';
			$footer = ob_get_clean();
		}
	}
	$content = ob_get_clean();
	if ( empty( $content ) ) wp_send_json_error( 'Dati non disponibili.' );
	wp_send_json_success( array( 'html' => $content, 'footer' => $footer ) );
}

function culturacsi_portal_action_button_form( array $args ): string {
	$context       = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : '';
	$action        = isset( $args['action'] ) ? sanitize_key( (string) $args['action'] ) : '';
	$target_id     = isset( $args['target_id'] ) ? (int) $args['target_id'] : 0;
	$label         = isset( $args['label'] ) ? (string) $args['label'] : '';
	$class         = isset( $args['class'] ) ? (string) $args['class'] : '';
	$confirm       = ! empty( $args['confirm'] );
	$confirm_text  = isset( $args['confirm_text'] ) ? (string) $args['confirm_text'] : 'Confermi questa azione?';
	$nonce_action  = 'culturacsi_row_action_' . $context;
	$button_class  = 'assoc-action-chip ' . $class;
	$onclick_attr  = $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm_text ) . '\');"' : '';
	$form_class    = 'assoc-row-action-form';
	if ( false !== strpos( $class, 'chip-toggle' ) ) {
		$form_class .= ' is-toggle';
	}

	if ( '' === $context || '' === $action || $target_id <= 0 || '' === $label ) {
		return '';
	}

	$html  = '<form method="post" class="' . esc_attr( $form_class ) . '">';
	$html .= wp_nonce_field( $nonce_action, 'culturacsi_row_action_nonce', true, false );
	$html .= '<input type="hidden" name="culturacsi_row_context" value="' . esc_attr( $context ) . '">';
	$html .= '<input type="hidden" name="culturacsi_row_action" value="' . esc_attr( $action ) . '">';
	$html .= '<input type="hidden" name="culturacsi_row_target_id" value="' . esc_attr( (string) $target_id ) . '">';
	$html .= '<button type="submit" name="culturacsi_row_action_submit" class="' . esc_attr( trim( $button_class ) ) . '"' . $onclick_attr . '>' . esc_html( $label ) . '</button>';
	$html .= '</form>';
	return $html;
}

function culturacsi_portal_process_post_row_action( string $context, string $post_type, bool $allow_non_admin_delete = true ): string {
	if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
		return '';
	}
	if ( ! isset( $_POST['culturacsi_row_action_submit'], $_POST['culturacsi_row_context'], $_POST['culturacsi_row_action'], $_POST['culturacsi_row_target_id'] ) ) {
		return '';
	}

	$posted_context = sanitize_key( wp_unslash( $_POST['culturacsi_row_context'] ) );
	if ( $posted_context !== $context ) {
		return '';
	}

	$nonce_action = 'culturacsi_row_action_' . $context;
	if ( ! isset( $_POST['culturacsi_row_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_row_action_nonce'] ) ), $nonce_action ) ) {
		return culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
	}

	$action    = sanitize_key( wp_unslash( $_POST['culturacsi_row_action'] ) );
	$target_id = absint( wp_unslash( $_POST['culturacsi_row_target_id'] ) );
	$post      = $target_id > 0 ? get_post( $target_id ) : null;
	if ( ! $post instanceof WP_Post || $post->post_type !== $post_type ) {
		return culturacsi_portal_notice( 'Elemento non trovato.', 'error' );
	}

	$user_id        = get_current_user_id();
	$is_site_admin  = current_user_can( 'manage_options' );
	$is_author      = (int) $post->post_author === $user_id;
	$can_edit_item  = $is_site_admin || $is_author;

	if ( 'delete' === $action ) {
		if ( ! $is_site_admin && ( ! $allow_non_admin_delete || ! $is_author ) ) {
			return culturacsi_portal_notice( 'Permessi insufficienti per eliminare.', 'error' );
		}
		$deleted = wp_trash_post( $target_id );
		if ( false === $deleted || null === $deleted ) {
			return culturacsi_portal_notice( 'Impossibile eliminare l\'elemento.', 'error' );
		}
		return culturacsi_portal_notice( 'Elemento spostato nel cestino.', 'success' );
	}

	if ( ! $can_edit_item ) {
		return culturacsi_portal_notice( 'Permessi insufficienti.', 'error' );
	}

	if ( in_array( $action, array( 'approve', 'reject', 'hold' ), true ) ) {
		if ( ! $is_site_admin ) {
			return culturacsi_portal_notice( 'Solo i Site Admin possono moderare lo stato.', 'error' );
		}
		$status_map = array(
			'approve' => 'publish',
			'reject'  => 'draft',
			'hold'    => 'pending',
		);
		$new_status = isset( $status_map[ $action ] ) ? $status_map[ $action ] : '';
		if ( '' === $new_status ) {
			return culturacsi_portal_notice( 'Azione non valida.', 'error' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->posts,
			array( 'post_status' => $new_status ),
			array( 'ID' => $target_id ),
			array( '%s' ),
			array( '%d' )
		);
		
		if ( false === $updated ) {
			return culturacsi_portal_notice( 'Errore durante l\'aggiornamento dello stato.', 'error' );
		}
		
		clean_post_cache( $target_id );
		wp_transition_post_status( $new_status, $post->post_status, $post );

		$labels = array(
			'approve' => 'Elemento approvato.',
			'reject'  => 'Elemento rifiutato.',
			'hold'    => 'Elemento messo in attesa.',
		);
		return culturacsi_portal_notice( $labels[ $action ], 'success' );
	}

	return culturacsi_portal_notice( 'Azione non riconosciuta.', 'error' );
}

function culturacsi_portal_events_filters_from_request(): array {
	return array(
		'q'      => isset( $_GET['e_q'] ) ? sanitize_text_field( wp_unslash( $_GET['e_q'] ) ) : '',
		'date'   => isset( $_GET['e_date'] ) ? sanitize_text_field( wp_unslash( $_GET['e_date'] ) ) : '',
		'status' => isset( $_GET['e_status'] ) ? sanitize_key( wp_unslash( $_GET['e_status'] ) ) : 'all',
		'author' => isset( $_GET['e_author'] ) ? absint( $_GET['e_author'] ) : 0,
	);
}

function culturacsi_events_search_shortcode( array $atts = array() ): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}
	$filters      = culturacsi_portal_events_filters_from_request();
	$is_site_admin = current_user_can( 'manage_options' );
	$base_url     = culturacsi_portal_reserved_current_page_url();
	$authors      = get_users(
		array(
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'who'                 => 'authors',
			'has_published_posts' => array( 'event' ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	);
	$count_args = array(
		'post_type'      => 'event',
		'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( ! $is_site_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id > 0 ) {
			$count_args['meta_query'] = array(
				array(
					'key'     => 'organizer_association_id',
					'value'   => $assoc_id,
					'compare' => '=',
				),
			);
		} else {
			$count_args['author'] = get_current_user_id();
		}
	} elseif ( $filters['author'] > 0 ) {
		$count_args['author'] = $filters['author'];
	}
	if ( '' !== $filters['q'] ) {
		$count_args['s'] = $filters['q'];
	}
	if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
		$count_args['date_query'] = array(
			array(
				'year'     => (int) $matches[1],
				'monthnum' => (int) $matches[2],
			),
		);
	}
	if ( 'all' !== $filters['status'] ) {
		$allowed_status = array( 'publish', 'pending', 'draft', 'future', 'private' );
		if ( in_array( $filters['status'], $allowed_status, true ) ) {
			$count_args['post_status'] = array( $filters['status'] );
		}
	}
	$count_query = new WP_Query( $count_args );
	$found_count = (int) $count_query->found_posts;

	ob_start();
	?>
	<div class="assoc-search-panel assoc-events-search">
		<div class="assoc-search-head">
			<div class="assoc-search-meta">
				<h3 class="assoc-search-title">Ricerca Eventi</h3>
				<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) $found_count ); ?></p>
			</div>
			<p class="assoc-search-actions">
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
			</p>
		</div>
		<form id="assoc-events-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
			<p class="assoc-search-field is-q">
				<label for="e_q">Cerca</label>
				<input type="text" id="e_q" name="e_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Titolo o contenuto">
			</p>
			<p class="assoc-search-field is-date">
				<label for="e_date">Data</label>
				<input type="month" id="e_date" name="e_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
			</p>
			<?php if ( $is_site_admin ) : ?>
				<p class="assoc-search-field is-author">
					<label for="e_author">Autore</label>
					<select id="e_author" name="e_author">
						<option value="0">Tutti</option>
						<?php foreach ( $authors as $author ) : ?>
							<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="assoc-search-field is-status">
					<label for="e_status">Stato</label>
					<select id="e_status" name="e_status">
						<option value="all" <?php selected( $filters['status'], 'all' ); ?>>Tutti</option>
						<option value="publish" <?php selected( $filters['status'], 'publish' ); ?>>Pubblicato</option>
						<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>>In attesa</option>
						<option value="draft" <?php selected( $filters['status'], 'draft' ); ?>>Bozza</option>
						<option value="future" <?php selected( $filters['status'], 'future' ); ?>>Programmato</option>
						<option value="private" <?php selected( $filters['status'], 'private' ); ?>>Privato</option>
					</select>
				</p>
			<?php endif; ?>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

function culturacsi_portal_news_panel_filters_from_request(): array {
	return array(
		'q'      => isset( $_GET['n_q'] ) ? sanitize_text_field( wp_unslash( $_GET['n_q'] ) ) : '',
		'date'   => isset( $_GET['n_date'] ) ? sanitize_text_field( wp_unslash( $_GET['n_date'] ) ) : '',
		'status' => isset( $_GET['n_status'] ) ? sanitize_key( wp_unslash( $_GET['n_status'] ) ) : 'all',
		'author' => isset( $_GET['n_author'] ) ? absint( $_GET['n_author'] ) : 0,
		'assoc'  => isset( $_GET['n_assoc'] ) ? absint( $_GET['n_assoc'] ) : 0,
	);
}

function culturacsi_news_panel_search_shortcode( array $atts = array() ): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}
	$filters       = culturacsi_portal_news_panel_filters_from_request();
	$is_site_admin = current_user_can( 'manage_options' );
	$base_url      = culturacsi_portal_reserved_current_page_url();
	$authors       = get_users(
		array(
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'who'                 => 'authors',
			'has_published_posts' => array( 'news' ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	);
	$associations = get_transient( 'culturacsi_assoc_dropdown_ids' );
	if ( false === $associations ) {
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
		set_transient( 'culturacsi_assoc_dropdown_ids', $associations, HOUR_IN_SECONDS );
	}
	$count_args = array(
		'post_type'      => 'news',
		'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( ! $is_site_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id > 0 ) {
			$count_args['meta_query'] = array(
				array(
					'key'     => 'organizer_association_id',
					'value'   => $assoc_id,
					'compare' => '=',
				),
			);
		} else {
			$count_args['author'] = get_current_user_id();
		}
	} elseif ( $filters['author'] > 0 ) {
		$count_args['author'] = $filters['author'];
	}
	if ( '' !== $filters['q'] ) {
		$count_args['s'] = $filters['q'];
	}
	if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
		$count_args['date_query'] = array(
			array(
				'year'     => (int) $matches[1],
				'monthnum' => (int) $matches[2],
			),
		);
	}
	if ( $filters['assoc'] > 0 ) {
		$allowed_ids = culturacsi_news_get_association_post_ids( $filters['assoc'] );
		$count_args['post__in'] = ! empty( $allowed_ids ) ? $allowed_ids : array( 0 );
	}
	if ( 'all' !== $filters['status'] ) {
		$allowed_status = array( 'publish', 'pending', 'draft', 'future', 'private' );
		if ( in_array( $filters['status'], $allowed_status, true ) ) {
			$count_args['post_status'] = array( $filters['status'] );
		}
	}
	$count_query = new WP_Query( $count_args );
	$found_count = (int) $count_query->found_posts;

	ob_start();
	?>
	<div class="assoc-search-panel assoc-news-search">
		<div class="assoc-search-head">
			<div class="assoc-search-meta">
				<h3 class="assoc-search-title">Ricerca Notizie</h3>
				<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) $found_count ); ?></p>
			</div>
			<p class="assoc-search-actions">
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
			</p>
		</div>
		<form id="assoc-news-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
			<p class="assoc-search-field is-q">
				<label for="n_q">Cerca</label>
				<input type="text" id="n_q" name="n_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Titolo o contenuto">
			</p>
			<p class="assoc-search-field is-date">
				<label for="n_date">Data</label>
				<input type="month" id="n_date" name="n_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
			</p>
			<?php if ( $is_site_admin ) : ?>
				<p class="assoc-search-field is-author">
					<label for="n_author">Autore</label>
					<select id="n_author" name="n_author">
						<option value="0">Tutti</option>
						<?php foreach ( $authors as $author ) : ?>
							<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="assoc-search-field is-association">
					<label for="n_assoc">Associazione</label>
					<select id="n_assoc" name="n_assoc">
						<option value="0">Tutte</option>
						<?php foreach ( $associations as $assoc_id ) : ?>
							<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $filters['assoc'], (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="assoc-search-field is-status">
					<label for="n_status">Stato</label>
					<select id="n_status" name="n_status">
						<option value="all" <?php selected( $filters['status'], 'all' ); ?>>Tutti</option>
						<option value="publish" <?php selected( $filters['status'], 'publish' ); ?>>Pubblicato</option>
						<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>>In attesa</option>
						<option value="draft" <?php selected( $filters['status'], 'draft' ); ?>>Bozza</option>
						<option value="future" <?php selected( $filters['status'], 'future' ); ?>>Programmato</option>
						<option value="private" <?php selected( $filters['status'], 'private' ); ?>>Privato</option>
					</select>
				</p>
			<?php endif; ?>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

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
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Eventi</h2><a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/eventi/nuovo/' ) ) . '">Nuovo Evento</a></div>';
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

function culturacsi_portal_event_form_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$user_id = get_current_user_id();
	$event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;
	$event = $event_id > 0 ? get_post( $event_id ) : null;

	if ( $event_id > 0 ) {
		if ( ! $event || 'event' !== $event->post_type ) {
			return culturacsi_portal_notice( 'Evento non trovato.', 'error' );
		}
		if ( ! current_user_can( 'manage_options' ) && (int) $event->post_author !== $user_id ) {
			return culturacsi_portal_notice( 'Non hai i permessi per modificare questo evento.', 'error' );
		}
	}

	$message_html = '';
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['culturacsi_event_submit'] ) ) {
		if ( ! isset( $_POST['culturacsi_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_event_nonce'] ) ), 'culturacsi_event_save' ) ) {
			$message_html = culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
		} else {
			$form_event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
			$form_event    = $form_event_id > 0 ? get_post( $form_event_id ) : null;
			if ( $form_event_id > 0 && ( ! $form_event || 'event' !== $form_event->post_type ) ) {
				$message_html = culturacsi_portal_notice( 'Evento non valido.', 'error' );
			} elseif ( $form_event_id > 0 && ! current_user_can( 'manage_options' ) && (int) $form_event->post_author !== $user_id ) {
				$message_html = culturacsi_portal_notice( 'Permessi insufficienti.', 'error' );
			} else {
				$title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
				$content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
				$post_data = array(
					'post_type'    => 'event',
					'post_title'   => $title,
					'post_content' => $content,
					'post_status'  => current_user_can( 'manage_options' ) ? 'publish' : 'pending',
					'post_author'  => $form_event_id > 0 ? (int) $form_event->post_author : $user_id,
					'ID'           => $form_event_id,
				);
				$saved_id = wp_insert_post( $post_data, true );
				if ( is_wp_error( $saved_id ) ) {
					$message_html = culturacsi_portal_notice( $saved_id->get_error_message(), 'error' );
				} else {
					$meta_fields = array( 'start_date', 'end_date', 'venue_name', 'address', 'city', 'province', 'comune', 'registration_url' );
					foreach ( $meta_fields as $key ) {
						$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
						$val = sanitize_text_field( $raw );
						if ( 'registration_url' === $key ) {
							$val = esc_url_raw( $val );
						}
						update_post_meta( $saved_id, $key, $val );
					}

					$assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
					if ( $assoc_id > 0 ) {
						update_post_meta( $saved_id, 'organizer_association_id', $assoc_id );
					}

					$event_type = isset( $_POST['event_type'] ) ? (int) $_POST['event_type'] : 0;
					if ( $event_type > 0 ) {
						wp_set_post_terms( $saved_id, array( $event_type ), 'event_type' );
					} else {
						wp_set_post_terms( $saved_id, array(), 'event_type' );
					}

					if ( ! empty( $_FILES['featured_image']['name'] ) ) {
						$file = $_FILES['featured_image'];
						$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
						if ( in_array( $file['type'], $allowed_types, true ) ) {
							require_once ABSPATH . 'wp-admin/includes/image.php';
							require_once ABSPATH . 'wp-admin/includes/file.php';
							require_once ABSPATH . 'wp-admin/includes/media.php';
							$attachment_id = media_handle_upload( 'featured_image', $saved_id );
							if ( ! is_wp_error( $attachment_id ) ) {
								set_post_thumbnail( $saved_id, (int) $attachment_id );
							}
						}
					}

					if ( ! current_user_can( 'manage_options' ) ) {
						global $wpdb;
						$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $saved_id ) );
						clean_post_cache( $saved_id );
					}

					wp_safe_redirect( add_query_arg( array( 'event_id' => (int) $saved_id, 'saved' => '1' ), home_url( '/area-riservata/eventi/nuovo/' ) ) );
					exit;
				}
			}
		}
	}

	if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) {
		$message_html = culturacsi_portal_notice( 'Evento salvato correttamente.', 'success' );
	}

	$meta = $event_id > 0 ? get_post_meta( $event_id ) : array();
	$get_meta = static function( string $key ) use ( $meta ): string {
		return isset( $meta[ $key ][0] ) ? (string) $meta[ $key ][0] : '';
	};
	$selected_event_type = $event_id > 0 ? wp_get_post_terms( $event_id, 'event_type', array( 'fields' => 'ids' ) ) : array();

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data">
		<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>">
		<?php wp_nonce_field( 'culturacsi_event_save', 'culturacsi_event_nonce' ); ?>
		<h2><?php echo esc_html( $event_id > 0 ? 'Modifica Evento' : 'Nuovo Evento' ); ?></h2>
		<p><label for="post_title">Titolo *</label><input type="text" id="post_title" name="post_title" required value="<?php echo esc_attr( $event_id > 0 ? (string) get_the_title( $event_id ) : '' ); ?>"></p>
		<p><label for="start_date">Data e ora inizio *</label><input type="datetime-local" id="start_date" name="start_date" required value="<?php echo esc_attr( $get_meta( 'start_date' ) ); ?>"></p>
		<p><label for="end_date">Data e ora fine</label><input type="datetime-local" id="end_date" name="end_date" value="<?php echo esc_attr( $get_meta( 'end_date' ) ); ?>"></p>
		<p><label for="event_type">Tipologia</label>
			<?php
			wp_dropdown_categories(
				array(
					'taxonomy'         => 'event_type',
					'name'             => 'event_type',
					'selected'         => isset( $selected_event_type[0] ) ? (int) $selected_event_type[0] : 0,
					'show_option_none' => 'Seleziona tipo...',
					'hide_empty'       => false,
					'hierarchical'     => false,
				)
			);
			?>
		</p>
		<p><label for="post_content">Descrizione</label><?php wp_editor( $event_id > 0 ? (string) get_post_field( 'post_content', $event_id ) : '', 'culturacsi_event_content', array( 'textarea_name' => 'post_content', 'media_buttons' => false, 'textarea_rows' => 7 ) ); ?></p>
		<p><label for="venue_name">Luogo</label><input type="text" id="venue_name" name="venue_name" value="<?php echo esc_attr( $get_meta( 'venue_name' ) ); ?>"></p>
		<p><label for="address">Indirizzo</label><input type="text" id="address" name="address" value="<?php echo esc_attr( $get_meta( 'address' ) ); ?>"></p>
		<p><label for="city">Citta</label><input type="text" id="city" name="city" value="<?php echo esc_attr( $get_meta( 'city' ) ); ?>"></p>
		<p><label for="comune">Comune</label><input type="text" id="comune" name="comune" value="<?php echo esc_attr( $get_meta( 'comune' ) ); ?>"></p>
		<p><label for="province">Provincia</label><input type="text" id="province" name="province" maxlength="2" value="<?php echo esc_attr( $get_meta( 'province' ) ); ?>"></p>
		<p><label for="registration_url">URL Registrazione</label><input type="url" id="registration_url" name="registration_url" value="<?php echo esc_attr( $get_meta( 'registration_url' ) ); ?>"></p>
		<p><label for="featured_image">Immagine evento</label><input type="file" id="featured_image" name="featured_image" accept="image/*"></p>
		<?php if ( $event_id > 0 && has_post_thumbnail( $event_id ) ) : ?>
			<p class="current-image"><?php echo get_the_post_thumbnail( $event_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<p><button type="submit" name="culturacsi_event_submit" class="button button-primary">Salva Evento</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function culturacsi_portal_news_list_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$message_html = culturacsi_portal_process_post_row_action( 'news', 'news', true );
	$filters      = culturacsi_portal_news_panel_filters_from_request();
	$base_url     = culturacsi_portal_reserved_current_page_url();
	$sort_state   = culturacsi_portal_get_sort_state(
		'n_sort',
		'n_dir',
		'date',
		'desc',
		array( 'index', 'title', 'date', 'status', 'ext', 'history' )
	);

	$args = array(
		'post_type'      => 'news',
		'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		'posts_per_page' => 200,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	if ( ! current_user_can( 'manage_options' ) ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id > 0 ) {
			// Query news linked to this association
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
	if ( $filters['assoc'] > 0 ) {
		$allowed_ids = culturacsi_news_get_association_post_ids( $filters['assoc'] );
		$args['post__in'] = ! empty( $allowed_ids ) ? $allowed_ids : array( 0 );
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
					case 'ext':
						$ext_a = (string) get_post_meta( (int) $a->ID, '_hebeae_external_url', true );
						$ext_b = (string) get_post_meta( (int) $b->ID, '_hebeae_external_url', true );
						$cmp   = strcasecmp( $ext_a, $ext_b );
						break;
					case 'date':
						$cmp = strtotime( (string) $a->post_date ) <=> strtotime( (string) $b->post_date );
						break;
					case 'history':
						static $h_cache_n = array();
						if(!isset($h_cache_n[$a->ID])) { $m=culturacsi_logging_get_last_modified('news',$a->ID); $c=culturacsi_logging_get_creator('news',$a->ID); $h_cache_n[$a->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($a->post_date)); }
						if(!isset($h_cache_n[$b->ID])) { $m=culturacsi_logging_get_last_modified('news',$b->ID); $c=culturacsi_logging_get_creator('news',$b->ID); $h_cache_n[$b->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($b->post_date)); }
						$cmp = $h_cache_n[$a->ID] <=> $h_cache_n[$b->ID];
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
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Notizie</h2><a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/notizie/nuova/' ) ) . '">Nuova Notizia</a></div>';
	echo '<style>.assoc-admin-table tr.is-pending-approval td { background-color: #fef2f2 !important; border-top: 2px solid #ef4444 !important; border-bottom: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:first-child { border-left: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:last-child { border-right: 2px solid #ef4444 !important; }</style>';
	echo '<table class="widefat striped assoc-admin-table assoc-table-news"><colgroup><col style="width:4ch"><col style="width:38%"><col style="width:7.2rem"><col style="width:6.2rem"><col style="width:4rem"><col style="width:140px"><col style="width:110px"></colgroup><thead><tr>';
	echo culturacsi_portal_sortable_th( '#', 'index', $sort_state['sort'], $sort_state['dir'], 'n_sort', 'n_dir', $base_url, 'assoc-col-index' );
	echo culturacsi_portal_sortable_th( 'Titolo', 'title', $sort_state['sort'], $sort_state['dir'], 'n_sort', 'n_dir', $base_url, 'assoc-col-title' );
	echo culturacsi_portal_sortable_th( 'Data', 'date', $sort_state['sort'], $sort_state['dir'], 'n_sort', 'n_dir', $base_url, 'assoc-col-date' );
	echo culturacsi_portal_sortable_th( 'Stato', 'status', $sort_state['sort'], $sort_state['dir'], 'n_sort', 'n_dir', $base_url, 'assoc-col-status' );
	echo culturacsi_portal_sortable_th( 'Ext URL', 'ext', $sort_state['sort'], $sort_state['dir'], 'n_sort', 'n_dir', $base_url, 'assoc-col-ext' );
	$th_html = culturacsi_portal_sortable_th( 'Cronologia', 'history', $sort_state['sort'], $sort_state['dir'], 'n_sort', 'n_dir', $base_url, 'assoc-col-history' );
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
			$ext_url    = (string) get_post_meta( $post_id, '_hebeae_external_url', true );
			$is_admin   = current_user_can( 'manage_options' );
			$edit_url   = add_query_arg( array( 'news_id' => $post_id ), home_url( '/area-riservata/notizie/nuova/' ) );

			$row_class  = ( $is_admin && 'pending' === get_post_status( $post_id ) ) ? ' is-pending-approval' : '';
			echo '<tr class="' . esc_attr( trim( $row_class ) ) . '" data-id="' . esc_attr( (string) $post_id ) . '" data-type="news">';
			echo '<td class="assoc-col-index">' . esc_html( (string) $row_num ) . '</td>';
			echo '<td class="assoc-col-title">' . esc_html( get_the_title( $post_id ) ) . '</td>';
			echo '<td class="assoc-col-date">' . esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $post_item->post_date ) ) ) . '</td>';
			echo '<td class="assoc-col-status"><span class="assoc-status-pill status-' . esc_attr( (string) get_post_status( $post_id ) ) . '">' . esc_html( $status_obj ? $status_obj->label : (string) get_post_status( $post_id ) ) . '</span></td>';
			echo '<td class="assoc-col-ext">' . ( $ext_url !== '' ? '<a href="' . esc_url( $ext_url ) . '" target="_blank" rel="noopener">ON</a>' : '&mdash;' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			
			// History column for News
			echo '<td class="assoc-col-history" style="font-size:11px;line-height:1.2;color:#64748b;">';
			$last_mod = culturacsi_logging_get_last_modified( 'news', $post_id );
			if ( $last_mod ) {
				echo '<strong>Modificato:</strong> ' . esc_html( date_i18n( 'd/m/y H:i', strtotime( $last_mod->created_at ) ) ) . '<br>';
				echo '<strong>Da:</strong> ' . esc_html( $last_mod->user_name );
			} else {
				$creator = culturacsi_logging_get_creator( 'news', $post_id );
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
					'context'      => 'news',
					'action'       => 'delete',
					'target_id'    => $post_id,
					'label'        => 'Elim.',
					'class'        => 'chip-delete',
					'confirm'      => true,
					'confirm_text' => 'Confermi l\'eliminazione di questa notizia?',
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			if ( $is_admin ) {
				$is_published = 'publish' === (string) get_post_status( $post_id );
				$toggle_label = $is_published ? 'Rif.' : 'Appr.';
				$toggle_action = $is_published ? 'reject' : 'approve';
				$toggle_class = $is_published ? 'chip-reject chip-toggle' : 'chip-approve chip-toggle';
				echo culturacsi_portal_action_button_form( array( 'context' => 'news', 'action' => $toggle_action, 'target_id' => $post_id, 'label' => $toggle_label, 'class' => $toggle_class ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="6">Nessuna notizia trovata.</td></tr>';
	}
	echo '</tbody></table></div>';
	return ob_get_clean();
}

function culturacsi_portal_reserved_current_page_url(): string {
	$queried_id = get_queried_object_id();
	if ( $queried_id > 0 ) {
		$link = get_permalink( $queried_id );
		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}
	}
	$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	return home_url( '/' . $path . '/' );
}

function culturacsi_portal_normalize_posts_list( array $items ): array {
	$posts = array();
	foreach ( $items as $item ) {
		if ( $item instanceof WP_Post ) {
			$posts[] = $item;
			continue;
		}
		$post_id = is_numeric( $item ) ? absint( (string) $item ) : 0;
		if ( $post_id <= 0 ) {
			continue;
		}
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			$posts[] = $post;
		}
	}
	return $posts;
}

function culturacsi_portal_admin_user_form_url( int $user_id = 0 ): string {
	$url = home_url( '/area-riservata/utenti/nuovo/' );
	if ( $user_id > 0 ) {
		$url = add_query_arg( 'user_id', (string) $user_id, $url );
	}
	return $url;
}

function culturacsi_portal_admin_association_form_url( int $association_id = 0 ): string {
	$url = home_url( '/area-riservata/associazioni/nuova/' );
	if ( $association_id > 0 ) {
		$url = add_query_arg( 'association_id', (string) $association_id, $url );
	}
	return $url;
}

function culturacsi_portal_current_query_args(): array {
	$args = array();
	foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_array( $value ) ) {
			continue;
		}
		$args[ sanitize_key( (string) $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
	}
	return $args;
}

function culturacsi_portal_get_sort_state( string $sort_param, string $dir_param, string $default_sort, string $default_dir, array $allowed_sorts ): array {
	$sort = isset( $_GET[ $sort_param ] ) ? sanitize_key( wp_unslash( $_GET[ $sort_param ] ) ) : $default_sort; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$dir  = isset( $_GET[ $dir_param ] ) ? sanitize_key( wp_unslash( $_GET[ $dir_param ] ) ) : $default_dir; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $sort, $allowed_sorts, true ) ) {
		$sort = $default_sort;
	}
	$dir = ( 'asc' === $dir ) ? 'asc' : 'desc';
	return array(
		'sort' => $sort,
		'dir'  => $dir,
	);
}

function culturacsi_portal_sortable_th( string $label, string $sort_key, string $current_sort, string $current_dir, string $sort_param, string $dir_param, string $base_url, string $class = '', array $reset_params = array() ): string {
	$query_args = culturacsi_portal_current_query_args();
	foreach ( $reset_params as $reset_param ) {
		unset( $query_args[ $reset_param ] );
	}

	$next_dir = ( $current_sort === $sort_key && 'asc' === $current_dir ) ? 'desc' : 'asc';
	$query_args[ $sort_param ] = $sort_key;
	$query_args[ $dir_param ]  = $next_dir;
	$url = add_query_arg( $query_args, $base_url );

	$is_active = ( $current_sort === $sort_key );
	$indicator = $is_active ? ( ( 'asc' === $current_dir ) ? '▲' : '▼' ) : '↕';
	$link_cls  = 'assoc-admin-sort-link' . ( $is_active ? ' is-active' : '' );

	$th_class_attr = '' !== trim( $class ) ? ' class="' . esc_attr( $class ) . '"' : '';
	return '<th' . $th_class_attr . '><a class="' . esc_attr( $link_cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '<span class="assoc-admin-sort-indicator">' . esc_html( $indicator ) . '</span></a></th>';
}

function culturacsi_portal_apply_user_role_moderation( int $user_id, string $role, string $moderation_state ): void {
	$user = get_user_by( 'id', $user_id );
	if ( ! $user instanceof WP_User ) {
		return;
	}

	$allowed_roles = array( 'administrator', 'association_manager', 'association_pending' );
	if ( ! in_array( $role, $allowed_roles, true ) ) {
		$role = 'association_manager';
	}

	if ( 'administrator' === $role ) {
		$user->set_role( 'administrator' );
		delete_user_meta( $user_id, 'assoc_pending_approval' );
		update_user_meta( $user_id, 'assoc_moderation_state', 'approved' );
		return;
	}

	if ( 'association_manager' === $role ) {
		$user->set_role( 'association_manager' );
		delete_user_meta( $user_id, 'assoc_pending_approval' );
		update_user_meta( $user_id, 'assoc_moderation_state', 'approved' );
		return;
	}

	$allowed_states = array( 'pending', 'hold', 'rejected' );
	$state          = in_array( $moderation_state, $allowed_states, true ) ? $moderation_state : 'pending';
	$user->set_role( 'association_pending' );
	update_user_meta( $user_id, 'assoc_pending_approval', '1' );
	update_user_meta( $user_id, 'assoc_moderation_state', $state );
}

function culturacsi_portal_users_filters_from_request(): array {
	return array(
		'q'      => isset( $_GET['u_q'] ) ? sanitize_text_field( wp_unslash( $_GET['u_q'] ) ) : '',
		'role'   => isset( $_GET['u_role'] ) ? sanitize_key( wp_unslash( $_GET['u_role'] ) ) : 'all',
		'status' => isset( $_GET['u_status'] ) ? sanitize_key( wp_unslash( $_GET['u_status'] ) ) : 'all',
	);
}

function culturacsi_portal_user_matches_status( WP_User $user, string $status ): bool {
	if ( 'all' === $status || '' === $status ) {
		return true;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return 'admin' === $status;
	}
	$is_pending = in_array( 'association_pending', (array) $user->roles, true ) || '1' === (string) get_user_meta( $user->ID, 'assoc_pending_approval', true );
	$state      = (string) get_user_meta( $user->ID, 'assoc_moderation_state', true );
	if ( 'pending' === $status ) {
		return $is_pending;
	}
	if ( 'approved' === $status ) {
		return ! $is_pending && in_array( 'association_manager', (array) $user->roles, true );
	}
	if ( 'hold' === $status ) {
		return $is_pending && 'hold' === $state;
	}
	if ( 'rejected' === $status ) {
		return $is_pending && 'rejected' === $state;
	}
	return false;
}

function culturacsi_users_search_shortcode( array $atts = array() ): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}
	$filters      = culturacsi_portal_users_filters_from_request();
	$is_site_admin = current_user_can( 'manage_options' );
	$base_url     = culturacsi_portal_reserved_current_page_url();
	if ( $is_site_admin ) {
		$count_users = get_users(
			array(
				'orderby' => 'registered',
				'order'   => 'DESC',
				'number'  => 1000,
			)
		);
	} else {
		$current_user = wp_get_current_user();
		$count_users  = $current_user instanceof WP_User ? array( $current_user ) : array();
	}
	$search_q = function_exists( 'mb_strtolower' ) ? mb_strtolower( $filters['q'] ) : strtolower( $filters['q'] );
	$count_users = array_values(
		array_filter(
			$count_users,
			static function( $user ) use ( $filters, $search_q, $is_site_admin ): bool {
				if ( ! $user instanceof WP_User ) {
					return false;
				}
				if ( '' !== $search_q ) {
					$haystack = trim( implode( ' ', array( (string) $user->display_name, (string) $user->user_email, (string) $user->user_login ) ) );
					$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
					if ( false === strpos( $haystack, $search_q ) ) {
						return false;
					}
				}
				if ( $is_site_admin && 'all' !== $filters['role'] && ! in_array( $filters['role'], (array) $user->roles, true ) ) {
					return false;
				}
				return culturacsi_portal_user_matches_status( $user, $filters['status'] );
			}
		)
	);
	$found_count = count( $count_users );

	ob_start();
	?>
	<div class="assoc-search-panel assoc-users-search">
		<div class="assoc-search-head">
			<div class="assoc-search-meta">
				<h3 class="assoc-search-title">Ricerca Utenti</h3>
				<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) $found_count ); ?></p>
			</div>
			<p class="assoc-search-actions">
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
			</p>
		</div>
		<form id="assoc-users-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
			<p class="assoc-search-field is-q">
				<label for="u_q">Cerca</label>
				<input type="text" id="u_q" name="u_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Nome, username o email">
			</p>
			<?php if ( $is_site_admin ) : ?>
				<p class="assoc-search-field is-role">
					<label for="u_role">Ruolo</label>
					<select id="u_role" name="u_role">
						<option value="all" <?php selected( $filters['role'], 'all' ); ?>>Tutti</option>
						<option value="administrator" <?php selected( $filters['role'], 'administrator' ); ?>>Amministratore Sito</option>
						<option value="association_manager" <?php selected( $filters['role'], 'association_manager' ); ?>>Amministratore Associazione</option>
					</select>
				</p>
				<p class="assoc-search-field is-status">
					<label for="u_status">Stato</label>
					<select id="u_status" name="u_status">
						<option value="all" <?php selected( $filters['status'], 'all' ); ?>>Tutti</option>
						<option value="approved" <?php selected( $filters['status'], 'approved' ); ?>>Approvato</option>
						<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>>In attesa</option>
						<option value="rejected" <?php selected( $filters['status'], 'rejected' ); ?>>Revocato</option>
					</select>
				</p>
			<?php endif; ?>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

function culturacsi_portal_associations_filters_from_request(): array {
	return array(
		'q'        => isset( $_GET['a_q'] ) ? sanitize_text_field( wp_unslash( $_GET['a_q'] ) ) : '',
		'status'   => isset( $_GET['a_status'] ) ? sanitize_key( wp_unslash( $_GET['a_status'] ) ) : 'all',
		'cat'      => isset( $_GET['a_cat'] ) ? absint( $_GET['a_cat'] ) : 0,
		'province' => isset( $_GET['a_province'] ) ? sanitize_text_field( wp_unslash( $_GET['a_province'] ) ) : '',
		'region'   => isset( $_GET['a_region'] ) ? sanitize_text_field( wp_unslash( $_GET['a_region'] ) ) : '',
		'city'     => isset( $_GET['a_city'] ) ? sanitize_text_field( wp_unslash( $_GET['a_city'] ) ) : '',
	);
}

function culturacsi_portal_association_collect_meta_values( array $post_ids, array $meta_keys ): array {
	$values = array();
	foreach ( $post_ids as $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			continue;
		}
		foreach ( $meta_keys as $meta_key ) {
			$raw = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
			if ( '' !== $raw ) {
				$values[ $raw ] = true;
				break;
			}
		}
	}
	$list = array_keys( $values );
	natcasesort( $list );
	return array_values( $list );
}

function culturacsi_portal_association_location_meta_query( array $filters ): array {
	$meta_query = array( 'relation' => 'AND' );
	if ( '' !== $filters['province'] ) {
		$meta_query[] = array(
			'key'     => 'province',
			'value'   => $filters['province'],
			'compare' => '=',
		);
	}
	if ( '' !== $filters['city'] ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => 'city',
				'value'   => $filters['city'],
				'compare' => '=',
			),
			array(
				'key'     => 'comune',
				'value'   => $filters['city'],
				'compare' => '=',
			),
		);
	}
	if ( '' !== $filters['region'] ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => 'region',
				'value'   => $filters['region'],
				'compare' => '=',
			),
			array(
				'key'     => 'regione',
				'value'   => $filters['region'],
				'compare' => '=',
			),
		);
	}
	return ( count( $meta_query ) > 1 ) ? $meta_query : array();
}

function culturacsi_associations_search_shortcode( array $atts = array() ): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}
	$filters       = culturacsi_portal_associations_filters_from_request();
	$is_site_admin = current_user_can( 'manage_options' );
	$base_url      = culturacsi_portal_reserved_current_page_url();
	$terms         = get_terms(
		array(
			'taxonomy'   => 'activity_category',
			'hide_empty' => false,
		)
	);
	$count_args = array(
		'post_type'      => 'association',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( '' !== $filters['q'] ) {
		$count_args['s'] = $filters['q'];
	}
	if ( $is_site_admin && 'all' !== $filters['status'] && in_array( $filters['status'], array( 'publish', 'pending', 'draft', 'private' ), true ) ) {
		$count_args['post_status'] = array( $filters['status'] );
	}
	if ( $filters['cat'] > 0 ) {
		$count_args['tax_query'] = array(
			array(
				'taxonomy' => 'activity_category',
				'field'    => 'term_id',
				'terms'    => array( (int) $filters['cat'] ),
			),
		);
	}
	if ( ! $is_site_admin ) {
		$managed_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		$count_args['post__in'] = $managed_id > 0 ? array( $managed_id ) : array( 0 );
	}
	$location_meta_query = culturacsi_portal_association_location_meta_query( $filters );
	if ( ! empty( $location_meta_query ) ) {
		$count_args['meta_query'] = $location_meta_query;
	}
	$count_query = new WP_Query( $count_args );
	$found_count = (int) $count_query->found_posts;

	$options_args = array(
		'post_type'      => 'association',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	);
	
	if ( ! $is_site_admin ) {
		$managed_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		$options_args['post__in'] = $managed_id > 0 ? array( $managed_id ) : array( 0 );
		$option_post_ids  = get_posts( $options_args );
		$province_options = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'province' ) );
		$region_options   = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'region', 'regione' ) );
		$city_options     = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'city', 'comune' ) );
	} else {
		// Site admins query the entire database, which is incredibly slow. We heavily cache this.
		$transient_key  = 'culturacsi_association_dropdowns_v1';
		$cached_options = get_transient( $transient_key );
		if ( false === $cached_options ) {
			$option_post_ids  = get_posts( $options_args );
			$province_options = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'province' ) );
			$region_options   = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'region', 'regione' ) );
			$city_options     = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'city', 'comune' ) );
			
			$cached_options = array(
				'province' => $province_options,
				'region'   => $region_options,
				'city'     => $city_options,
			);
			set_transient( $transient_key, $cached_options, 12 * HOUR_IN_SECONDS );
		} else {
			$province_options = $cached_options['province'];
			$region_options   = $cached_options['region'];
			$city_options     = $cached_options['city'];
		}
	}

	ob_start();
	?>
	<div class="assoc-search-panel assoc-associations-search">
		<div class="assoc-search-head">
			<div class="assoc-search-meta">
				<h3 class="assoc-search-title">Ricerca Associazioni</h3>
				<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) $found_count ); ?></p>
			</div>
			<p class="assoc-search-actions">
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
			</p>
		</div>
		<form id="assoc-associations-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
			<p class="assoc-search-field is-q">
				<label for="a_q">Cerca</label>
				<input type="text" id="a_q" name="a_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Nome associazione">
			</p>
			<p class="assoc-search-field is-category">
				<label for="a_cat">Categoria Attivita</label>
				<select id="a_cat" name="a_cat">
					<option value="0">Tutte</option>
					<?php
					if ( ! is_wp_error( $terms ) ) :
						foreach ( $terms as $term ) :
							?>
							<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( $filters['cat'], (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php
						endforeach;
					endif;
					?>
				</select>
			</p>
			<p class="assoc-search-field is-province">
				<label for="a_province">Provincia</label>
				<select id="a_province" name="a_province">
					<option value="">Tutte</option>
					<?php foreach ( $province_options as $province_value ) : ?>
						<option value="<?php echo esc_attr( $province_value ); ?>" <?php selected( $filters['province'], $province_value ); ?>><?php echo esc_html( $province_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="assoc-search-field is-region">
				<label for="a_region">Regione</label>
				<select id="a_region" name="a_region">
					<option value="">Tutte</option>
					<?php foreach ( $region_options as $region_value ) : ?>
						<option value="<?php echo esc_attr( $region_value ); ?>" <?php selected( $filters['region'], $region_value ); ?>><?php echo esc_html( $region_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="assoc-search-field is-city">
				<label for="a_city">Citta</label>
				<select id="a_city" name="a_city">
					<option value="">Tutte</option>
					<?php foreach ( $city_options as $city_value ) : ?>
						<option value="<?php echo esc_attr( $city_value ); ?>" <?php selected( $filters['city'], $city_value ); ?>><?php echo esc_html( $city_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ( $is_site_admin ) : ?>
				<p class="assoc-search-field is-status">
					<label for="a_status">Stato</label>
					<select id="a_status" name="a_status">
						<option value="all" <?php selected( $filters['status'], 'all' ); ?>>Tutti</option>
						<option value="publish" <?php selected( $filters['status'], 'publish' ); ?>>Pubblicato</option>
						<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>>In attesa</option>
						<option value="draft" <?php selected( $filters['status'], 'draft' ); ?>>Bozza</option>
						<option value="private" <?php selected( $filters['status'], 'private' ); ?>>Privato</option>
					</select>
				</p>
			<?php endif; ?>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

function culturacsi_portal_user_approval_label( WP_User $user ): string {
	$is_approved = culturacsi_portal_is_user_approved( $user );
	if ( $is_approved ) {
		return 'Approvato';
	}
	
	$state = (string) get_user_meta( $user->ID, 'assoc_moderation_state', true );
	if ( 'rejected' === $state ) {
		return 'Revocato';
	}
	return 'In attesa';
}

function culturacsi_portal_user_approval_class( WP_User $user ): string {
	if ( culturacsi_portal_is_user_approved( $user ) ) {
		return 'status-approved';
	}

	$state = (string) get_user_meta( $user->ID, 'assoc_moderation_state', true );
	if ( 'rejected' === $state ) {
		return 'status-rejected';
	}
	if ( 'hold' === $state ) {
		return 'status-hold';
	}

	return 'status-pending';
}

function culturacsi_portal_is_user_approved( WP_User $user ): bool {
	if ( user_can( $user, 'manage_options' ) ) {
		return true;
	}
	return in_array( 'association_manager', (array) $user->roles, true ) && '1' !== (string) get_user_meta( $user->ID, 'assoc_pending_approval', true );
}

function culturacsi_portal_process_user_row_action(): string {
	if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
		return '';
	}
	if ( ! isset( $_POST['culturacsi_row_action_submit'], $_POST['culturacsi_row_context'], $_POST['culturacsi_row_action'], $_POST['culturacsi_row_target_id'] ) ) {
		return '';
	}
	if ( 'user' !== sanitize_key( wp_unslash( $_POST['culturacsi_row_context'] ) ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return culturacsi_portal_notice( 'Permessi insufficienti.', 'error' );
	}
	if ( ! isset( $_POST['culturacsi_row_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_row_action_nonce'] ) ), 'culturacsi_row_action_user' ) ) {
		return culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
	}

	$action    = sanitize_key( wp_unslash( $_POST['culturacsi_row_action'] ) );
	$target_id = absint( wp_unslash( $_POST['culturacsi_row_target_id'] ) );
	$user      = $target_id > 0 ? get_user_by( 'id', $target_id ) : false;
	if ( ! $user instanceof WP_User ) {
		return culturacsi_portal_notice( 'Utente non trovato.', 'error' );
	}
	if ( user_can( $user, 'manage_options' ) && (int) $user->ID !== get_current_user_id() ) {
		return culturacsi_portal_notice( 'Non puoi modificare lo stato di un altro Site Admin.', 'error' );
	}

	if ( 'delete' === $action ) {
		if ( (int) $user->ID === get_current_user_id() ) {
			return culturacsi_portal_notice( 'Non puoi eliminare il tuo utente.', 'error' );
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return culturacsi_portal_notice( 'I Site Admin possono essere eliminati solo dal pannello utente standard di WordPress. Deve sempre esserci almeno un Site Admin.', 'error' );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$deleted = wp_delete_user( (int) $user->ID, get_current_user_id() );
		if ( ! $deleted ) {
			return culturacsi_portal_notice( 'Impossibile eliminare l\'utente.', 'error' );
		}
		culturacsi_log_event( 'delete_user', 'user', (int) $user->ID, "User deleted: " . $user->user_login );
		return culturacsi_portal_notice( 'Utente eliminato.', 'success' );
	}

	if ( in_array( $action, array( 'approve', 'reject', 'hold' ), true ) ) {
		if ( user_can( $user, 'manage_options' ) ) {
			return culturacsi_portal_notice( 'Azione non consentita su questo utente.', 'error' );
		}
		if ( 'approve' === $action ) {
			$user->set_role( 'association_manager' );
			delete_user_meta( (int) $user->ID, 'assoc_pending_approval' );
			update_user_meta( (int) $user->ID, 'assoc_moderation_state', 'approved' );
			culturacsi_log_event( 'approve_user', 'user', (int) $user->ID, "User approved: " . $user->user_login );
			return culturacsi_portal_notice( 'Utente approvato.', 'success' );
		}
		$user->set_role( 'association_pending' );
		update_user_meta( (int) $user->ID, 'assoc_pending_approval', '1' );
		$new_state = ( 'reject' === $action ? 'rejected' : 'hold' );
		update_user_meta( (int) $user->ID, 'assoc_moderation_state', $new_state );
		culturacsi_log_event( 'moderate_user', 'user', (int) $user->ID, "Action: $action ($new_state) for " . $user->user_login );
		return culturacsi_portal_notice( 'Stato utente aggiornato.', 'success' );
	}

	return culturacsi_portal_notice( 'Azione non riconosciuta.', 'error' );
}

function culturacsi_portal_users_list_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$message_html   = culturacsi_portal_process_user_row_action();
	$is_site_admin  = current_user_can( 'manage_options' );
	$current_user   = wp_get_current_user();
	$current_user_id = get_current_user_id();
	$filters         = culturacsi_portal_users_filters_from_request();
	$base_url        = culturacsi_portal_reserved_current_page_url();
	$sort_state      = culturacsi_portal_get_sort_state(
		'u_sort',
		'u_dir',
		'registered',
		'desc',
		array( 'index', 'name', 'email', 'role', 'status', 'registered', 'history' )
	);

	if ( $is_site_admin ) {
		$users = get_users(
			array(
				'orderby' => 'registered',
				'order'   => 'DESC',
				'number'  => 1000,
			)
		);
	} else {
		$assoc_id = culturacsi_portal_get_managed_association_id( $current_user_id );
		if ( $assoc_id > 0 ) {
			$users = get_users(
				array(
					'meta_query' => array(
						array(
							'key'     => 'association_post_id',
							'value'   => (string) $assoc_id,
							'compare' => '=',
						),
					),
					'role__not_in' => array( 'administrator' ),
					'number'       => 500,
				)
			);
		} else {
			$users = array( $current_user );
		}
	}

	$search_q = function_exists( 'mb_strtolower' ) ? mb_strtolower( $filters['q'] ) : strtolower( $filters['q'] );
	if ( ! empty( $users ) ) {
		$users = array_values(
			array_filter(
				$users,
				static function( $user ) use ( $filters, $search_q, $is_site_admin ): bool {
					if ( ! $user instanceof WP_User ) {
						return false;
					}
					// Non-admins should never see Site Admins in the list
					if ( ! $is_site_admin && user_can( $user, 'manage_options' ) ) {
						return false;
					}
					if ( '' !== $search_q ) {
						$haystack = trim( implode( ' ', array( (string) $user->display_name, (string) $user->user_email, (string) $user->user_login ) ) );
						$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
						if ( false === strpos( $haystack, $search_q ) ) {
							return false;
						}
					}
					if ( $is_site_admin && 'all' !== $filters['role'] && ! in_array( $filters['role'], (array) $user->roles, true ) ) {
						return false;
					}
					if ( ! culturacsi_portal_user_matches_status( $user, $filters['status'] ) ) {
						return false;
					}
					return true;
				}
			)
		);
	}
	if ( ! empty( $users ) ) {
		usort(
			$users,
			static function( WP_User $a, WP_User $b ) use ( $sort_state ): int {
				$cmp = 0;
				switch ( $sort_state['sort'] ) {
					case 'name':
						$cmp = strcasecmp( (string) $a->display_name, (string) $b->display_name );
						break;
					case 'email':
						$cmp = strcasecmp( (string) $a->user_email, (string) $b->user_email );
						break;
					case 'role':
						$cmp = strcasecmp( implode( ', ', (array) $a->roles ), implode( ', ', (array) $b->roles ) );
						break;
					case 'status':
						$cmp = strcasecmp( culturacsi_portal_user_approval_label( $a ), culturacsi_portal_user_approval_label( $b ) );
						break;
					case 'registered':
						$cmp = strtotime( (string) $a->user_registered ) <=> strtotime( (string) $b->user_registered );
						break;
					case 'history':
						static $h_cache_u = array();
						if(!isset($h_cache_u[$a->ID])) { $m=culturacsi_logging_get_last_modified('user',$a->ID); $c=culturacsi_logging_get_creator('user',$a->ID); $h_cache_u[$a->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($a->user_registered)); }
						if(!isset($h_cache_u[$b->ID])) { $m=culturacsi_logging_get_last_modified('user',$b->ID); $c=culturacsi_logging_get_creator('user',$b->ID); $h_cache_u[$b->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($b->user_registered)); }
						$cmp = $h_cache_u[$a->ID] <=> $h_cache_u[$b->ID];
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
	echo '<div class="assoc-portal-users-list assoc-portal-section">';
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Utenti</h2>';
	echo '<a class="button button-primary" href="' . esc_url( culturacsi_portal_admin_user_form_url() ) . '">Nuovo Utente</a>';
	echo '</div>';
	echo '<style>.assoc-admin-table tr.is-pending-approval td { background-color: #fef2f2 !important; border-top: 2px solid #ef4444 !important; border-bottom: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:first-child { border-left: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:last-child { border-right: 2px solid #ef4444 !important; }</style>';
	echo '<table class="widefat striped assoc-admin-table assoc-table-users"><colgroup><col style="width:4ch"><col style="width:24%"><col style="width:22%"><col style="width:15%"><col style="width:6rem"><col style="width:120px"><col style="width:6rem"><col style="width:110px"></colgroup><thead><tr>';
	echo culturacsi_portal_sortable_th( '#', 'index', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-index' );
	echo culturacsi_portal_sortable_th( 'Nome', 'name', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-title' );
	echo culturacsi_portal_sortable_th( 'Email', 'email', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-email' );
	echo culturacsi_portal_sortable_th( 'Ruolo', 'role', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-role' );
	echo culturacsi_portal_sortable_th( 'Stato', 'status', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-status assoc-col-status-compact' );
	$th_html_u = culturacsi_portal_sortable_th( 'Cronologia', 'history', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-history' );
	echo str_replace( '<th class="assoc-col-history">', '<th class="assoc-col-history" style="width:180px;">', $th_html_u );
	echo culturacsi_portal_sortable_th( 'Registrato', 'registered', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-date' );
	echo '<th class="assoc-col-actions">Azioni</th>';
	echo '</tr></thead><tbody>';
	if ( ! empty( $users ) ) {
		$row_num = 0;
		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}
			++$row_num;
			$roles = array_map(
				static function( string $role ): string {
					if ( 'administrator' === $role ) {
						return 'Admin Sito';
					}
					if ( 'association_manager' === $role ) {
						return 'Admin Associazione';
					}
					return ucwords( str_replace( '_', ' ', $role ) );
				},
				(array) $user->roles
			);

			$row_class  = ( current_user_can( 'manage_options' ) && ! culturacsi_portal_is_user_approved( $user ) ) ? ' is-pending-approval' : '';
			echo '<tr class="' . esc_attr( trim( $row_class ) ) . '" data-id="' . esc_attr( (string) $user->ID ) . '" data-type="user">';
			echo '<td class="assoc-col-index">' . esc_html( (string) $row_num ) . '</td>';
			echo '<td class="assoc-col-title">';
			$avatar_id = (int) get_user_meta( (int) $user->ID, 'assoc_user_avatar_id', true );
			if ( $avatar_id > 0 ) {
				echo '<span class="assoc-user-list-thumb">' . wp_get_attachment_image( $avatar_id, array( 32, 32 ) ) . '</span>';
			}
			echo '<span class="assoc-user-list-name">' . esc_html( $user->display_name ) . '</span></td>';
			echo '<td class="assoc-col-email">' . esc_html( $user->user_email ) . '</td>';
			echo '<td class="assoc-col-role">' . esc_html( implode( ', ', $roles ) ) . '</td>';
			echo '<td class="assoc-col-status assoc-col-status-compact"><span class="assoc-status-pill ' . esc_attr( culturacsi_portal_user_approval_class( $user ) ) . '">' . esc_html( culturacsi_portal_user_approval_label( $user ) ) . '</span></td>';
			// History column for Users
			echo '<td class="assoc-col-history" style="font-size:10px;line-height:1.2;color:#64748b;vertical-align:middle;white-space:nowrap;">';
			$last_mod = culturacsi_logging_get_last_modified( 'user', (int) $user->ID );
			if ( $last_mod ) {
				echo '<strong>Mod:</strong> ' . esc_html( date_i18n( 'd/m/y H:i', strtotime( $last_mod->created_at ) ) ) . '<br>';
				echo esc_html( $last_mod->user_name );
			} else {
				$reg_date = (string) $user->user_registered;
				echo '<strong>Reg:</strong> ' . esc_html( date_i18n( 'd/m/y H:i', strtotime( $reg_date ) ) ) . '<br>';
				echo 'System';
			}
			echo '</td>';

			echo '<td class="assoc-col-date" style="vertical-align:middle; line-height:1.1; white-space:nowrap;">' . esc_html( date_i18n( 'd/m/Y', strtotime( (string) $user->user_registered ) ) ) . '<br><small style="color:#64748b;">' . esc_html( date_i18n( 'H:i', strtotime( (string) $user->user_registered ) ) ) . '</small></td>';
			echo '<td class="assoc-col-actions"><div class="assoc-action-group">';
			// All managers can edit users in their association (listing logic above ensures they only see their association)
			echo '<a class="assoc-action-chip chip-edit" href="' . esc_url( culturacsi_portal_admin_user_form_url( (int) $user->ID ) ) . '">Mod.</a>';
			
			if ( $is_site_admin && (int) $user->ID !== $current_user_id ) {
				echo culturacsi_portal_action_button_form(
					array(
						'context'      => 'user',
						'action'       => 'delete',
						'target_id'    => (int) $user->ID,
						'label'        => 'Elim.',
						'class'        => 'chip-delete',
						'confirm'      => true,
						'confirm_text' => 'Confermi l\'eliminazione di questo utente?',
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				
				$is_approved = culturacsi_portal_is_user_approved( $user );
				$toggle_label = $is_approved ? 'Rev.' : 'Appr.';
				$toggle_action = $is_approved ? 'revoke' : 'approve';
				$toggle_class = $is_approved ? 'chip-reject chip-toggle' : 'chip-approve chip-toggle';
				echo culturacsi_portal_action_button_form( array( 'context' => 'user', 'action' => $toggle_action, 'target_id' => (int) $user->ID, 'label' => $toggle_label, 'class' => $toggle_class ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="7">Nessun utente trovato.</td></tr>';
	}
	echo '</tbody></table></div>';

	return ob_get_clean();
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
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Associazioni</h2>';
	if ( $is_site_admin ) {
		echo '<a class="button button-primary" href="' . esc_url( culturacsi_portal_admin_association_form_url() ) . '">Crea Associazione</a>';
	} else {
		echo '<a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/profilo/' ) ) . '">Dati Associazione</a>';
	}
	echo '</div>';
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

function culturacsi_portal_news_form_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$user_id = get_current_user_id();
	$news_id = isset( $_GET['news_id'] ) ? (int) $_GET['news_id'] : 0;
	$news = $news_id > 0 ? get_post( $news_id ) : null;

	if ( $news_id > 0 ) {
		if ( ! $news || 'news' !== $news->post_type ) {
			return culturacsi_portal_notice( 'Notizia non trovata.', 'error' );
		}
		if ( ! current_user_can( 'manage_options' ) && (int) $news->post_author !== $user_id ) {
			return culturacsi_portal_notice( 'Non hai i permessi per modificare questa notizia.', 'error' );
		}
	}

	$message_html = '';
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['culturacsi_news_submit'] ) ) {
		if ( ! isset( $_POST['culturacsi_news_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_news_nonce'] ) ), 'culturacsi_news_save' ) ) {
			$message_html = culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
		} else {
			$form_news_id = isset( $_POST['news_id'] ) ? (int) $_POST['news_id'] : 0;
			$form_news = $form_news_id > 0 ? get_post( $form_news_id ) : null;
			if ( $form_news_id > 0 && ( ! $form_news || 'news' !== $form_news->post_type ) ) {
				$message_html = culturacsi_portal_notice( 'Notizia non valida.', 'error' );
			} elseif ( $form_news_id > 0 && ! current_user_can( 'manage_options' ) && (int) $form_news->post_author !== $user_id ) {
				$message_html = culturacsi_portal_notice( 'Permessi insufficienti.', 'error' );
			} else {
				$title = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
				$content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
				$excerpt = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';
				$external_url = isset( $_POST['external_url'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['external_url'] ) ) ) : '';

				$post_data = array(
					'post_type'    => 'news',
					'post_title'   => $title,
					'post_content' => $content,
					'post_excerpt' => $excerpt,
					'post_status'  => current_user_can( 'manage_options' ) ? 'publish' : 'pending',
					'post_author'  => $form_news_id > 0 ? (int) $form_news->post_author : $user_id,
					'ID'           => $form_news_id,
				);
				$saved_id = wp_insert_post( $post_data, true );
				if ( is_wp_error( $saved_id ) ) {
					$message_html = culturacsi_portal_notice( $saved_id->get_error_message(), 'error' );
				} else {
					if ( $external_url !== '' ) {
						update_post_meta( $saved_id, '_hebeae_external_url', $external_url );
						update_post_meta( $saved_id, '_hebeae_external_enabled', '1' );
					} else {
						delete_post_meta( $saved_id, '_hebeae_external_url' );
						update_post_meta( $saved_id, '_hebeae_external_enabled', '0' );
					}

					$assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
					if ( $assoc_id > 0 ) {
						update_post_meta( $saved_id, 'organizer_association_id', $assoc_id );
					}

					if ( ! empty( $_FILES['featured_image']['name'] ) ) {
						$file = $_FILES['featured_image'];
						$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
						if ( in_array( $file['type'], $allowed_types, true ) ) {
							require_once ABSPATH . 'wp-admin/includes/image.php';
							require_once ABSPATH . 'wp-admin/includes/file.php';
							require_once ABSPATH . 'wp-admin/includes/media.php';
							$attachment_id = media_handle_upload( 'featured_image', $saved_id );
							if ( ! is_wp_error( $attachment_id ) ) {
								set_post_thumbnail( $saved_id, (int) $attachment_id );
							}
						}
					}

					if ( ! current_user_can( 'manage_options' ) ) {
						global $wpdb;
						$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $saved_id ) );
						clean_post_cache( $saved_id );
					}

					wp_safe_redirect( add_query_arg( array( 'news_id' => (int) $saved_id, 'saved' => '1' ), home_url( '/area-riservata/notizie/nuova/' ) ) );
					exit;
				}
			}
		}
	}

	if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) {
		$message_html = culturacsi_portal_notice( 'Notizia salvata correttamente.', 'success' );
	}

	$current_external = $news_id > 0 ? (string) get_post_meta( $news_id, '_hebeae_external_url', true ) : '';
	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data">
		<input type="hidden" name="news_id" value="<?php echo esc_attr( (string) $news_id ); ?>">
		<?php wp_nonce_field( 'culturacsi_news_save', 'culturacsi_news_nonce' ); ?>
		<h2><?php echo esc_html( $news_id > 0 ? 'Modifica Notizia' : 'Nuova Notizia' ); ?></h2>
		<p><label for="post_title">Titolo *</label><input type="text" id="post_title" name="post_title" required value="<?php echo esc_attr( $news_id > 0 ? (string) get_the_title( $news_id ) : '' ); ?>"></p>
		<p><label for="post_excerpt">Sommario</label><textarea id="post_excerpt" name="post_excerpt" rows="3"><?php echo esc_textarea( $news_id > 0 ? (string) get_post_field( 'post_excerpt', $news_id ) : '' ); ?></textarea></p>
		<p><label for="post_content">Contenuto</label><?php wp_editor( $news_id > 0 ? (string) get_post_field( 'post_content', $news_id ) : '', 'culturacsi_news_content', array( 'textarea_name' => 'post_content', 'media_buttons' => false, 'textarea_rows' => 8 ) ); ?></p>
		<p><label for="external_url">Original News URL</label><input type="url" id="external_url" name="external_url" placeholder="https://..." value="<?php echo esc_attr( $current_external ); ?>"></p>
		<p><label for="featured_image">Immagine</label><input type="file" id="featured_image" name="featured_image" accept="image/*"></p>
		<?php if ( $news_id > 0 && has_post_thumbnail( $news_id ) ) : ?>
			<p class="current-image"><?php echo get_the_post_thumbnail( $news_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<p><button type="submit" name="culturacsi_news_submit" class="button button-primary">Salva Notizia</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function culturacsi_portal_users_form_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$user_id = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
	$user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;
	
	$is_site_admin = current_user_can( 'manage_options' );
	$current_user_id = get_current_user_id();
	$managed_assoc_id = culturacsi_portal_get_managed_association_id( $current_user_id );

	if ( ! $is_site_admin ) {
		if ( $user_id > 0 ) {
			if ( ! $user instanceof WP_User ) {
				return culturacsi_portal_notice( 'Utente non trovato.', 'error' );
			}
			$target_assoc_id = (int) get_user_meta( $user_id, 'association_post_id', true );
			if ( $target_assoc_id !== $managed_assoc_id || $managed_assoc_id <= 0 ) {
				return culturacsi_portal_notice( 'Non hai i permessi per gestire questo utente.', 'error' );
			}
		}
		if ( $managed_assoc_id <= 0 ) {
			return culturacsi_portal_notice( 'Devi essere collegato a un\'associazione per gestire altri utenti.', 'error' );
		}
	}

	if ( $user_id > 0 && ! $user instanceof WP_User ) {
		return culturacsi_portal_notice( 'Utente non trovato.', 'error' );
	}
	
	if ( $user instanceof WP_User && user_can( $user, 'manage_options' ) && (int) $user->ID !== get_current_user_id() ) {
		return culturacsi_portal_notice( 'I profili Site Admin possono essere gestiti solo dal pannello standard di WordPress.', 'error' );
	}

	$current_avatar_id = $user instanceof WP_User ? (int) get_user_meta( (int) $user->ID, 'assoc_user_avatar_id', true ) : 0;

	$message_html = '';
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['culturacsi_users_form_submit'] ) ) {
		if ( ! isset( $_POST['culturacsi_users_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_users_form_nonce'] ) ), 'culturacsi_users_form_save' ) ) {
			$message_html = culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
		} else {
			$form_user_id = isset( $_POST['form_user_id'] ) ? absint( wp_unslash( $_POST['form_user_id'] ) ) : 0;
			$form_user    = $form_user_id > 0 ? get_user_by( 'id', $form_user_id ) : false;
			if ( $form_user_id > 0 && ! $form_user instanceof WP_User ) {
				$message_html = culturacsi_portal_notice( 'Utente non trovato.', 'error' );
			} else {
				$user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
				$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
				$first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
				$last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
				$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
				$nickname     = isset( $_POST['nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['nickname'] ) ) : '';
				$user_url     = isset( $_POST['user_url'] ) ? esc_url_raw( wp_unslash( $_POST['user_url'] ) ) : '';
				$description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
				$role          = isset( $_POST['user_role'] ) ? sanitize_key( wp_unslash( $_POST['user_role'] ) ) : 'association_manager';
				$moderation    = isset( $_POST['user_moderation_state'] ) ? sanitize_key( wp_unslash( $_POST['user_moderation_state'] ) ) : 'approved';
				
				if ( ! $is_site_admin ) {
					$association_post_id = $managed_assoc_id;
					
					// Association managers modifying users pushes them back to pending approval.
					if ( $form_user_id > 0 && $form_user instanceof WP_User ) {
						$role = 'association_pending';
						$moderation = 'pending';
					} else {
						// New users created by association managers require site admin approval.
						$role = 'association_pending';
						$moderation = 'pending';
					}
				} else {
					$association_post_id = isset( $_POST['association_post_id'] ) ? absint( wp_unslash( $_POST['association_post_id'] ) ) : 0;
				}

				$pass1 = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
				$pass2 = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';

				if ( '' === $user_email || ! is_email( $user_email ) ) {
					$message_html = culturacsi_portal_notice( 'Inserisci una email valida.', 'error' );
				} elseif ( 0 === $form_user_id && '' === $user_login ) {
					$message_html = culturacsi_portal_notice( 'Lo username e obbligatorio.', 'error' );
				} elseif ( 0 === $form_user_id && username_exists( $user_login ) ) {
					$message_html = culturacsi_portal_notice( 'Questo username e gia in uso.', 'error' );
				} else {
					$email_owner = email_exists( $user_email );
					if ( $email_owner && (int) $email_owner !== $form_user_id ) {
						$message_html = culturacsi_portal_notice( 'Questa email e gia registrata.', 'error' );
					}
				}

				if ( '' === $message_html && ( $pass1 !== '' || $pass2 !== '' ) ) {
					if ( $pass1 !== $pass2 ) {
						$message_html = culturacsi_portal_notice( 'Le password non coincidono.', 'error' );
					} elseif ( strlen( $pass1 ) < 8 ) {
						$message_html = culturacsi_portal_notice( 'La password deve avere almeno 8 caratteri.', 'error' );
					}
				}

				if ( '' === $message_html && 0 === $form_user_id && '' === $pass1 ) {
					$message_html = culturacsi_portal_notice( 'La password e obbligatoria per i nuovi utenti.', 'error' );
				}
				if ( '' === $message_html && $form_user_id === get_current_user_id() && 'administrator' !== $role ) {
					$message_html = culturacsi_portal_notice( 'Non puoi rimuovere il tuo ruolo di Site Admin.', 'error' );
				}

				if ( '' === $display_name ) {
					$display_name = trim( $first_name . ' ' . $last_name );
				}
				if ( '' === $display_name ) {
					$display_name = ( 0 === $form_user_id ) ? $user_login : (string) $form_user->display_name;
				}

				if ( '' === $message_html ) {
					$userdata = array(
						'user_email'   => $user_email,
						'first_name'   => $first_name,
						'last_name'    => $last_name,
						'display_name' => $display_name,
						'nickname'     => $nickname,
						'user_url'     => $user_url,
						'description'  => $description,
						'role'         => $role,
					);
					if ( 0 === $form_user_id ) {
						$userdata['user_login'] = $user_login;
						$userdata['user_pass']  = $pass1;
					} else {
						$userdata['ID']         = $form_user_id;
						$userdata['user_login'] = $form_user->user_login;
						if ( '' !== $pass1 ) {
							$userdata['user_pass'] = $pass1;
						}
					}

					$saved_user_id = wp_insert_user( $userdata );
					if ( is_wp_error( $saved_user_id ) ) {
						$message_html = culturacsi_portal_notice( $saved_user_id->get_error_message(), 'error' );
					} else {
						$saved_user_id = (int) $saved_user_id;
						if ( 'administrator' !== $role && in_array( $moderation, array( 'pending', 'hold', 'rejected' ), true ) ) {
							$role = 'association_pending';
						} elseif ( 'administrator' !== $role && 'approved' === $moderation && 'association_pending' === $role ) {
							$role = 'association_manager';
						}
						culturacsi_portal_apply_user_role_moderation( $saved_user_id, $role, $moderation );

						if ( $association_post_id > 0 && 'association' === get_post_type( $association_post_id ) ) {
							update_user_meta( $saved_user_id, 'association_post_id', $association_post_id );
						} else {
							delete_user_meta( $saved_user_id, 'association_post_id' );
						}
						$remove_avatar = isset( $_POST['remove_user_avatar'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['remove_user_avatar'] ) );
						if ( $remove_avatar ) {
							delete_user_meta( $saved_user_id, 'assoc_user_avatar_id' );
						}
						if ( isset( $_FILES['user_avatar'] ) && ! empty( $_FILES['user_avatar']['name'] ) ) {
							require_once ABSPATH . 'wp-admin/includes/image.php';
							require_once ABSPATH . 'wp-admin/includes/file.php';
							require_once ABSPATH . 'wp-admin/includes/media.php';
							$avatar_id = media_handle_upload( 'user_avatar', 0, array( 'post_author' => $saved_user_id ) );
							if ( is_wp_error( $avatar_id ) ) {
								$message_html = culturacsi_portal_notice( 'Utente salvato, ma il caricamento della foto non e riuscito: ' . $avatar_id->get_error_message(), 'warning' );
								$user_id      = $saved_user_id;
								$user         = get_user_by( 'id', $saved_user_id );
							} else {
								update_user_meta( $saved_user_id, 'assoc_user_avatar_id', (int) $avatar_id );
							}
						}

						if ( '' === $message_html ) {
							wp_safe_redirect(
								add_query_arg(
									array(
										'user_id' => $saved_user_id,
										'saved'   => '1',
									),
									culturacsi_portal_admin_user_form_url()
								)
							);
							exit;
						}
					}
				}
			}
		}
	}

	if ( isset( $_GET['saved'] ) && '1' === (string) $_GET['saved'] ) {
		$message_html = culturacsi_portal_notice( 'Utente salvato correttamente.', 'success' );
		$user_id      = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
		$user         = $user_id > 0 ? get_user_by( 'id', $user_id ) : false;
	}
	$current_avatar_id = $user instanceof WP_User ? (int) get_user_meta( (int) $user->ID, 'assoc_user_avatar_id', true ) : 0;

	$associations = get_posts(
		array(
			'post_type'      => 'association',
			'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
			'posts_per_page' => 1000,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$current_role       = 'association_manager';
	$current_moderation = 'approved';
	$current_assoc_id   = 0;
	if ( $user instanceof WP_User ) {
		$current_role = in_array( 'administrator', (array) $user->roles, true ) ? 'administrator' : ( in_array( 'association_pending', (array) $user->roles, true ) ? 'association_pending' : 'association_manager' );
		$current_assoc_id = (int) get_user_meta( (int) $user->ID, 'association_post_id', true );
		if ( 'association_pending' === $current_role ) {
			$state = (string) get_user_meta( (int) $user->ID, 'assoc_moderation_state', true );
			if ( in_array( $state, array( 'hold', 'rejected' ), true ) ) {
				$current_moderation = $state;
			} else {
				$current_moderation = 'pending';
			}
		}
	}

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data" autocomplete="off">
		<?php wp_nonce_field( 'culturacsi_users_form_save', 'culturacsi_users_form_nonce' ); ?>
		<input type="hidden" name="form_user_id" value="<?php echo esc_attr( (string) $user_id ); ?>">
		<h2 style="margin-bottom:0;"><?php echo esc_html( $user_id > 0 ? 'Modifica Utente' : 'Nuovo Utente' ); ?></h2>
		<?php
		if ( ! $is_site_admin && $managed_assoc_id > 0 ) {
			echo '<p style="margin-top:2px; margin-bottom:20px; font-weight:600; color:#2563eb;">' . esc_html( get_the_title( $managed_assoc_id ) ) . '</p>';
		} else if ( $is_site_admin && $current_assoc_id > 0 ) {
			echo '<p style="margin-top:2px; margin-bottom:20px; font-weight:600; color:#2563eb;">' . esc_html( get_the_title( $current_assoc_id ) ) . '</p>';
		} else {
			echo '<br>';
		}
		?>
		<?php
		$val_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : ( $user instanceof WP_User ? (string) $user->user_login : '' );
		$val_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : ( $user instanceof WP_User ? (string) $user->user_email : '' );
		$val_first = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : ( $user instanceof WP_User ? (string) $user->first_name : '' );
		$val_last  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : ( $user instanceof WP_User ? (string) $user->last_name : '' );
		$val_disp  = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : ( $user instanceof WP_User ? (string) $user->display_name : '' );
		$val_nick  = isset( $_POST['nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['nickname'] ) ) : ( $user instanceof WP_User ? (string) $user->nickname : '' );
		$val_url   = isset( $_POST['user_url'] ) ? esc_url_raw( wp_unslash( $_POST['user_url'] ) ) : ( $user instanceof WP_User ? (string) $user->user_url : '' );
		$val_desc  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : ( $user instanceof WP_User ? (string) $user->description : '' );
		?>
		<p><label for="user_login">Username *</label><input type="text" id="user_login" name="user_login" required <?php echo $user_id > 0 ? 'readonly' : ''; ?> value="<?php echo esc_attr( $val_login ); ?>"></p>
		<p><label for="user_email">Email *</label><input type="email" id="user_email" name="user_email" required value="<?php echo esc_attr( $val_email ); ?>"></p>
		<p><label for="first_name">Nome</label><input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( $val_first ); ?>"></p>
		<p><label for="last_name">Cognome</label><input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( $val_last ); ?>"></p>
		<p><label for="display_name">Nome visualizzato pubblicamente</label><input type="text" id="display_name" name="display_name" value="<?php echo esc_attr( $val_disp ); ?>"></p>
		<p><label for="nickname">Nickname</label><input type="text" id="nickname" name="nickname" value="<?php echo esc_attr( $val_nick ); ?>"></p>
		<p><label for="user_url">Sito web</label><input type="url" id="user_url" name="user_url" value="<?php echo esc_attr( $val_url ); ?>"></p>
		<p><label for="description">Biografia</label><textarea id="description" name="description" rows="4"><?php echo esc_textarea( $val_desc ); ?></textarea></p>
		<p><label for="user_avatar">Foto utente</label><input type="file" id="user_avatar" name="user_avatar" accept="image/*"></p>
		<?php if ( $current_avatar_id > 0 ) : ?>
			<p class="current-image"><?php echo wp_get_attachment_image( $current_avatar_id, array( 96, 96 ), false, array( 'class' => 'assoc-user-avatar-preview' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<p class="assoc-avatar-remove"><label><input type="checkbox" name="remove_user_avatar" value="1"> Rimuovi foto attuale</label></p>
		<?php endif; ?>
		<?php if ( $is_site_admin ) : ?>
			<p><label for="user_role">Ruolo</label>
				<select id="user_role" name="user_role">
					<option value="administrator" <?php selected( $current_role, 'administrator' ); ?>>Admin Sito</option>
					<option value="association_manager" <?php selected( $current_role, 'association_manager' ); ?>>Admin Associazione</option>
					<option value="association_pending" <?php selected( $current_role, 'association_pending' ); ?>>In Attesa</option>
				</select>
			</p>
			<p><label for="user_moderation_state">Stato approvazione</label>
				<select id="user_moderation_state" name="user_moderation_state">
					<option value="approved" <?php selected( $current_moderation, 'approved' ); ?>>Approvato</option>
					<option value="pending" <?php selected( $current_moderation, 'pending' ); ?>>In attesa</option>
					<option value="hold" <?php selected( $current_moderation, 'hold' ); ?>>Hold</option>
					<option value="rejected" <?php selected( $current_moderation, 'rejected' ); ?>>Rifiutato</option>
				</select>
			</p>
			<p><label for="association_post_id">Associazione gestita</label>
				<select id="association_post_id" name="association_post_id">
					<option value="0">-- Nessuna --</option>
					<?php foreach ( $associations as $assoc_id ) : ?>
						<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $current_assoc_id, (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		<?php else: ?>
			<?php if ( $user_id > 0 ) : ?>
				<p><label>Ruolo</label><input type="text" readonly value="<?php echo esc_attr( 'association_manager' === $current_role ? 'Admin Associazione' : 'In Attesa' ); ?>"></p>
				<p><label>Stato approvazione</label><input type="text" readonly value="<?php 
					$mod_labels = array( 'approved' => 'Approvato', 'pending' => 'In attesa', 'hold' => 'In pausa', 'rejected' => 'Rifiutato' );
					echo esc_attr( $mod_labels[$current_moderation] ?? 'In attesa' ); 
				?>"></p>
			<?php endif; ?>
		<?php endif; ?>
		<p><label for="pass1"><?php echo esc_html( $user_id > 0 ? 'Nuova password (opzionale)' : 'Password *' ); ?></label><input type="password" id="pass1" name="pass1" autocomplete="new-password"></p>
		<p><label for="pass2">Conferma password</label><input type="password" id="pass2" name="pass2" autocomplete="new-password"></p>
		<p><button type="submit" name="culturacsi_users_form_submit" class="button button-primary">Salva Utente</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function culturacsi_portal_associations_form_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return culturacsi_portal_association_form_shortcode();
	}

	$association_id = isset( $_GET['association_id'] ) ? absint( wp_unslash( $_GET['association_id'] ) ) : 0;
	$association    = $association_id > 0 ? get_post( $association_id ) : null;
	if ( $association_id > 0 && ( ! $association instanceof WP_Post || 'association' !== $association->post_type ) ) {
		return culturacsi_portal_notice( 'Associazione non trovata.', 'error' );
	}

	$message_html = '';
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['culturacsi_associations_form_submit'] ) ) {
		if ( ! isset( $_POST['culturacsi_associations_form_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_associations_form_nonce'] ) ), 'culturacsi_associations_form_save' ) ) {
			$message_html = culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
		} else {
			$form_association_id = isset( $_POST['form_association_id'] ) ? absint( wp_unslash( $_POST['form_association_id'] ) ) : 0;
			$form_association    = $form_association_id > 0 ? get_post( $form_association_id ) : null;
			if ( $form_association_id > 0 && ( ! $form_association instanceof WP_Post || 'association' !== $form_association->post_type ) ) {
				$message_html = culturacsi_portal_notice( 'Associazione non valida.', 'error' );
			} else {
				$title     = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
				$status    = isset( $_POST['post_status'] ) ? sanitize_key( wp_unslash( $_POST['post_status'] ) ) : 'publish';
				$author_id = isset( $_POST['post_author'] ) ? absint( wp_unslash( $_POST['post_author'] ) ) : get_current_user_id();
				$excerpt   = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';
				$content   = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';

				if ( '' === $title ) {
					$message_html = culturacsi_portal_notice( 'Il nome associazione e obbligatorio.', 'error' );
				}

				$allowed_status = array( 'publish', 'pending', 'draft', 'private' );
				if ( ! in_array( $status, $allowed_status, true ) ) {
					$status = 'publish';
				}
				$author_user = get_user_by( 'id', $author_id );
				if ( $author_id <= 0 || ! ( $author_user instanceof WP_User ) ) {
					$author_id = get_current_user_id();
				}

				if ( '' === $message_html ) {
					$post_data = array(
						'post_type'    => 'association',
						'post_title'   => $title,
						'post_name'    => '', // Force auto-generation from title
						'post_excerpt' => $excerpt,
						'post_content' => $content,
						'post_status'  => $status,
						'post_author'  => $author_id,
						'ID'           => $form_association_id,
					);

					$saved_id = wp_insert_post( $post_data, true );
					if ( is_wp_error( $saved_id ) ) {
						$message_html = culturacsi_portal_notice( $saved_id->get_error_message(), 'error' );
					} else {
						$saved_id = (int) $saved_id;
						$meta_fields = array( 'city', 'province', 'region', 'comune', 'address', 'phone', 'email', 'website', 'facebook', 'instagram', 'youtube', 'tiktok', 'x' );
						foreach ( $meta_fields as $key ) {
							$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
							$val = sanitize_text_field( $raw );
							if ( in_array( $key, array( 'website', 'facebook', 'instagram', 'youtube', 'tiktok', 'x' ), true ) ) {
								$val = esc_url_raw( $val );
							}
							if ( 'email' === $key ) {
								$val = sanitize_email( $val );
							}
							update_post_meta( $saved_id, $key, $val );
						}
						update_post_meta( $saved_id, 'regione', (string) get_post_meta( $saved_id, 'region', true ) );

						$term_ids = array();
						if ( isset( $_POST['tax_input']['activity_category'] ) && is_array( $_POST['tax_input']['activity_category'] ) ) {
							$term_ids = array_map( 'intval', wp_unslash( $_POST['tax_input']['activity_category'] ) );
						}
						wp_set_post_terms( $saved_id, $term_ids, 'activity_category' );

						if ( ! empty( $_FILES['featured_image']['name'] ) ) {
							require_once ABSPATH . 'wp-admin/includes/image.php';
							require_once ABSPATH . 'wp-admin/includes/file.php';
							require_once ABSPATH . 'wp-admin/includes/media.php';
							$attachment_id = media_handle_upload( 'featured_image', $saved_id );
							if ( ! is_wp_error( $attachment_id ) ) {
								set_post_thumbnail( $saved_id, (int) $attachment_id );
							}
						}

						wp_safe_redirect(
							add_query_arg(
								array(
									'association_id' => $saved_id,
									'saved'          => '1',
								),
								culturacsi_portal_admin_association_form_url()
							)
						);
						exit;
					}
				}
			}
		}
	}

	if ( isset( $_GET['saved'] ) && '1' === (string) $_GET['saved'] ) {
		$message_html   = culturacsi_portal_notice( 'Associazione salvata correttamente.', 'success' );
		$association_id = isset( $_GET['association_id'] ) ? absint( wp_unslash( $_GET['association_id'] ) ) : 0;
		$association    = $association_id > 0 ? get_post( $association_id ) : null;
	}

	$authors = get_users(
		array(
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => array( 'ID', 'display_name' ),
		)
	);
	$selected_terms = ( $association_id > 0 ) ? wp_get_post_terms( $association_id, 'activity_category', array( 'fields' => 'ids' ) ) : array();

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'culturacsi_associations_form_save', 'culturacsi_associations_form_nonce' ); ?>
		<input type="hidden" name="form_association_id" value="<?php echo esc_attr( (string) $association_id ); ?>">
		<h2><?php echo esc_html( $association_id > 0 ? 'Modifica Associazione' : 'Crea Associazione' ); ?></h2>
		<p><label for="post_title">Nome associazione *</label><input type="text" id="post_title" name="post_title" required value="<?php echo esc_attr( $association instanceof WP_Post ? (string) $association->post_title : '' ); ?>"></p>
		<p><label for="post_status">Stato</label>
			<select id="post_status" name="post_status">
				<option value="publish" <?php selected( $association instanceof WP_Post ? (string) $association->post_status : 'publish', 'publish' ); ?>>Pubblicato</option>
				<option value="pending" <?php selected( $association instanceof WP_Post ? (string) $association->post_status : '', 'pending' ); ?>>In attesa</option>
				<option value="draft" <?php selected( $association instanceof WP_Post ? (string) $association->post_status : '', 'draft' ); ?>>Bozza</option>
				<option value="private" <?php selected( $association instanceof WP_Post ? (string) $association->post_status : '', 'private' ); ?>>Privato</option>
			</select>
		</p>
		<p><label for="post_author">Autore</label>
			<select id="post_author" name="post_author">
				<?php foreach ( $authors as $author ) : ?>
					<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $association instanceof WP_Post ? (int) $association->post_author : get_current_user_id(), (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><label for="post_excerpt">Sommario</label><textarea id="post_excerpt" name="post_excerpt" rows="3"><?php echo esc_textarea( $association instanceof WP_Post ? (string) $association->post_excerpt : '' ); ?></textarea></p>
		<p><label for="post_content">Descrizione</label><?php wp_editor( $association instanceof WP_Post ? (string) $association->post_content : '', 'culturacsi_association_content', array( 'textarea_name' => 'post_content', 'media_buttons' => false, 'textarea_rows' => 8 ) ); ?></p>
		<p><label for="city">Citta</label><input type="text" id="city" name="city" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'city', true ) ); ?>"></p>
		<p><label for="province">Provincia</label><input type="text" id="province" name="province" maxlength="2" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'province', true ) ); ?>"></p>
		<p><label for="region">Regione</label><input type="text" id="region" name="region" value="<?php echo esc_attr( (string) ( get_post_meta( $association_id, 'region', true ) ?: get_post_meta( $association_id, 'regione', true ) ) ); ?>"></p>
		<p><label for="comune">Comune</label><input type="text" id="comune" name="comune" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'comune', true ) ); ?>"></p>
		<p><label for="address">Indirizzo</label><input type="text" id="address" name="address" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'address', true ) ); ?>"></p>
		<p><label for="phone">Telefono</label><input type="text" id="phone" name="phone" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'phone', true ) ); ?>"></p>
		<p><label for="email">Email</label><input type="email" id="email" name="email" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'email', true ) ); ?>"></p>
		<p><label for="website">Sito web</label><input type="url" id="website" name="website" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'website', true ) ); ?>"></p>
		<p><label for="facebook">Facebook</label><input type="url" id="facebook" name="facebook" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'facebook', true ) ); ?>"></p>
		<p><label for="instagram">Instagram</label><input type="url" id="instagram" name="instagram" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'instagram', true ) ); ?>"></p>
		<p><label for="youtube">YouTube</label><input type="url" id="youtube" name="youtube" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'youtube', true ) ); ?>"></p>
		<p><label for="tiktok">TikTok</label><input type="url" id="tiktok" name="tiktok" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'tiktok', true ) ); ?>"></p>
		<p><label for="x">X</label><input type="url" id="x" name="x" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'x', true ) ); ?>"></p>
		<fieldset>
			<legend>Categorie Attivita</legend>
			<div class="category-checklist">
				<?php
				if ( ! function_exists( 'wp_terms_checklist' ) ) {
					require_once ABSPATH . 'wp-admin/includes/template.php';
				}
				wp_terms_checklist(
					$association_id,
					array(
						'taxonomy'      => 'activity_category',
						'selected_cats' => $selected_terms,
						'checked_ontop' => false,
					)
				);
				?>
			</div>
		</fieldset>
		<p><label for="featured_image">Logo associazione</label><input type="file" id="featured_image" name="featured_image" accept="image/*"></p>
		<?php if ( $association_id > 0 && has_post_thumbnail( $association_id ) ) : ?>
			<p class="current-logo"><?php echo get_the_post_thumbnail( $association_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<p><button type="submit" name="culturacsi_associations_form_submit" class="button button-primary">Salva Associazione</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function culturacsi_portal_user_profile_shortcode(): string {
	if ( ! is_user_logged_in() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$user_id = get_current_user_id();
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return culturacsi_portal_notice( 'Utente non trovato.', 'error' );
	}

	$message_html = '';
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['culturacsi_user_profile_submit'] ) ) {
		if ( ! isset( $_POST['culturacsi_user_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_user_profile_nonce'] ) ), 'culturacsi_user_profile_save' ) ) {
			$message_html = culturacsi_portal_notice( 'Verifica di sicurezza non valida. Riprova.', 'error' );
		} else {
			$first_name   = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
			$last_name    = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
			$display_name = isset( $_POST['display_name'] ) ? sanitize_text_field( wp_unslash( $_POST['display_name'] ) ) : '';
			$nickname     = isset( $_POST['nickname'] ) ? sanitize_text_field( wp_unslash( $_POST['nickname'] ) ) : '';
			$email        = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
			$user_url     = isset( $_POST['user_url'] ) ? esc_url_raw( wp_unslash( $_POST['user_url'] ) ) : '';
			$description  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
			$pass1        = isset( $_POST['pass1'] ) ? (string) $_POST['pass1'] : '';
			$pass2        = isset( $_POST['pass2'] ) ? (string) $_POST['pass2'] : '';

			$userdata = array(
				'ID'           => $user_id,
				'user_login'   => $user->user_login,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $display_name,
				'nickname'     => $nickname,
				'user_email'   => $email,
				'user_url'     => $user_url,
				'description'  => $description,
			);

			if ( $pass1 !== '' || $pass2 !== '' ) {
				if ( $pass1 !== $pass2 ) {
					$message_html = culturacsi_portal_notice( 'Le password non coincidono.', 'error' );
				} elseif ( strlen( $pass1 ) < 8 ) {
					$message_html = culturacsi_portal_notice( 'La password deve avere almeno 8 caratteri.', 'error' );
				} else {
					$userdata['user_pass'] = $pass1;
				}
			}

			if ( '' === $message_html ) {
				$result = wp_update_user( $userdata );
				if ( is_wp_error( $result ) ) {
					$message_html = culturacsi_portal_notice( $result->get_error_message(), 'error' );
				} else {
					$avatar_msg = '';
					$remove_avatar = isset( $_POST['remove_user_avatar'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['remove_user_avatar'] ) );
					if ( $remove_avatar ) {
						delete_user_meta( $user_id, 'assoc_user_avatar_id' );
					}
					if ( isset( $_FILES['user_avatar'] ) && ! empty( $_FILES['user_avatar']['name'] ) ) {
						require_once ABSPATH . 'wp-admin/includes/image.php';
						require_once ABSPATH . 'wp-admin/includes/file.php';
						require_once ABSPATH . 'wp-admin/includes/media.php';

						$avatar_id = media_handle_upload( 'user_avatar', 0 );
						if ( ! is_wp_error( $avatar_id ) ) {
							update_user_meta( $user_id, 'assoc_user_avatar_id', (int) $avatar_id );
						} else {
							$avatar_msg = ' (Errore caricamento foto: ' . $avatar_id->get_error_message() . ')';
						}
					}

					if ( ! current_user_can( 'manage_options' ) ) {
						// For association managers, flag as saved (no pending workflow for profile).
						$message_html = culturacsi_portal_notice( 'Profilo aggiornato con successo.' . $avatar_msg, 'success' );
						$user = get_userdata( $user_id );
					} else {
						$message_html = culturacsi_portal_notice( 'Profilo utente aggiornato.' . $avatar_msg, 'success' );
						$user = get_userdata( $user_id );
					}
				}
			}
		}
	}

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'culturacsi_user_profile_save', 'culturacsi_user_profile_nonce' ); ?>
		<h2>Profilo Utente</h2>
		<p><label for="user_login">Username</label><input type="text" id="user_login" readonly value="<?php echo esc_attr( (string) $user->user_login ); ?>"></p>
		<?php 
		$tied_assoc_id = (int) get_user_meta( $user_id, 'association_post_id', true );
		if ( $tied_assoc_id > 0 ) : ?>
			<p><label>Associazione Gestita</label><input type="text" readonly value="<?php echo esc_attr( get_the_title( $tied_assoc_id ) ); ?>"></p>
		<?php endif; ?>
		<p><label for="first_name">Nome</label><input type="text" id="first_name" name="first_name" value="<?php echo esc_attr( (string) $user->first_name ); ?>"></p>
		<p><label for="last_name">Cognome</label><input type="text" id="last_name" name="last_name" value="<?php echo esc_attr( (string) $user->last_name ); ?>"></p>
		<p><label for="display_name">Nome visualizzato pubblicamente</label><input type="text" id="display_name" name="display_name" value="<?php echo esc_attr( (string) $user->display_name ); ?>"></p>
		<p><label for="nickname">Nickname</label><input type="text" id="nickname" name="nickname" value="<?php echo esc_attr( (string) $user->nickname ); ?>"></p>
		<p><label for="user_email">Email</label><input type="email" id="user_email" name="user_email" required value="<?php echo esc_attr( (string) $user->user_email ); ?>"></p>
		<p><label for="user_url">Sito web</label><input type="url" id="user_url" name="user_url" value="<?php echo esc_attr( (string) $user->user_url ); ?>"></p>
		<p><label for="description">Biografia</label><textarea id="description" name="description" rows="4"><?php echo esc_textarea( (string) $user->description ); ?></textarea></p>
		
		<?php 
		$current_avatar_id = (int) get_user_meta( $user_id, 'assoc_user_avatar_id', true );
		if ( $current_avatar_id > 0 ) : ?>
			<p class="current-image"><?php echo wp_get_attachment_image( $current_avatar_id, array( 96, 96 ), false, array( 'class' => 'assoc-user-avatar-preview' ) ); ?></p>
			<p class="assoc-avatar-remove"><label><input type="checkbox" name="remove_user_avatar" value="1"> Rimuovi foto attuale</label></p>
		<?php endif; ?>
		<p><label for="user_avatar">Carica nuova foto</label><input type="file" id="user_avatar" name="user_avatar" accept="image/*"></p>

		<p><label for="pass1">Nuova Password (opzionale)</label><input type="password" id="pass1" name="pass1" autocomplete="new-password"></p>
		<p><label for="pass2">Conferma Nuova Password</label><input type="password" id="pass2" name="pass2" autocomplete="new-password"></p>
		<p><button type="submit" name="culturacsi_user_profile_submit" class="button button-primary">Salva Profilo</button></p>
	</form>
	<?php
	return ob_get_clean();
}

function culturacsi_portal_association_form_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$user_id = get_current_user_id();
	$association_id = culturacsi_portal_get_managed_association_id( $user_id );
	$is_new = ( $association_id <= 0 );

	$message_html = '';
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['culturacsi_assoc_profile_submit'] ) ) {
		if ( ! isset( $_POST['culturacsi_assoc_profile_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_assoc_profile_nonce'] ) ), 'culturacsi_assoc_profile_save' ) ) {
			$message_html = culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
		} else {
			$title   = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
			$excerpt = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';
			$content = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';

			$post_data = array(
				'post_type'    => 'association',
				'post_title'   => $title,
				'post_name'    => '', // Force auto-generation from title
				'post_excerpt' => $excerpt,
				'post_content' => $content,
			);

			if ( $is_new ) {
				$post_data['post_status'] = 'pending';
				$post_data['post_author'] = $user_id;
				
				$new_id = wp_insert_post( $post_data, true );
				if ( is_wp_error( $new_id ) ) {
					$message_html = culturacsi_portal_notice( $new_id->get_error_message(), 'error' );
				} else {
					$association_id = (int) $new_id;
					update_user_meta( $user_id, 'association_post_id', $association_id );
					if ( ! current_user_can( 'manage_options' ) ) {
						global $wpdb;
						$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $association_id ) );
						clean_post_cache( $association_id );
					}
					$is_new = false;
					$message_html = culturacsi_portal_notice( 'Associazione registrata e inviata per approvazione.', 'success' );
				}
			} else {
				$post_data['ID'] = $association_id;
				if ( ! current_user_can( 'manage_options' ) ) {
					$post_data['post_status'] = 'pending';
				}
				wp_update_post( $post_data );
				if ( ! current_user_can( 'manage_options' ) ) {
					global $wpdb;
					$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $association_id ) );
					clean_post_cache( $association_id );
				}
				$message_html = culturacsi_portal_notice( current_user_can( 'manage_options' ) ? 'Profilo associazione aggiornato.' : 'Modifiche inviate per approvazione.', 'success' );
			}

			if ( $association_id > 0 ) {
				$meta_fields = array( 'city', 'province', 'region', 'comune', 'address', 'phone', 'email', 'website', 'facebook', 'instagram', 'youtube', 'tiktok', 'x' );
				foreach ( $meta_fields as $key ) {
					$raw = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
					$val = sanitize_text_field( $raw );
					if ( in_array( $key, array( 'website', 'facebook', 'instagram', 'youtube', 'tiktok', 'x' ), true ) ) {
						$val = esc_url_raw( $val );
					}
					if ( 'email' === $key ) {
						$val = sanitize_email( $val );
					}
					update_post_meta( $association_id, $key, $val );
				}
				update_post_meta( $association_id, 'regione', (string) get_post_meta( $association_id, 'region', true ) );

				$term_ids = array();
				if ( isset( $_POST['tax_input']['activity_category'] ) && is_array( $_POST['tax_input']['activity_category'] ) ) {
					$term_ids = array_map( 'intval', wp_unslash( $_POST['tax_input']['activity_category'] ) );
				}
				wp_set_post_terms( $association_id, $term_ids, 'activity_category' );

				if ( ! empty( $_FILES['featured_image']['name'] ) ) {
					require_once ABSPATH . 'wp-admin/includes/image.php';
					require_once ABSPATH . 'wp-admin/includes/file.php';
					require_once ABSPATH . 'wp-admin/includes/media.php';
					$attachment_id = media_handle_upload( 'featured_image', $association_id );
					if ( ! is_wp_error( $attachment_id ) ) {
						set_post_thumbnail( $association_id, (int) $attachment_id );
					}
				}

				culturacsi_log_event( $is_new ? 'create_post' : 'update_post', 'association', (int) $association_id, 'Profilo gestito via area riservata' );
			}
		}
	}

	$association = $association_id > 0 ? get_post( $association_id ) : null;
	$assoc_title = $association ? $association->post_title : '';
	$assoc_excerpt = $association ? $association->post_excerpt : '';
	$assoc_content = $association ? $association->post_content : '';

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data">
		<?php wp_nonce_field( 'culturacsi_assoc_profile_save', 'culturacsi_assoc_profile_nonce' ); ?>
		<h2><?php echo $is_new ? 'Registra la tua Associazione' : 'Dati Associazione'; ?></h2>
		
		<p><label for="post_title">Nome associazione *</label><input type="text" id="post_title" name="post_title" required value="<?php echo esc_attr( (string) $assoc_title ); ?>"></p>
		
		<p><label for="post_excerpt">Sommario (Breve presentazione)</label><textarea id="post_excerpt" name="post_excerpt" rows="3"><?php echo esc_textarea( (string) $assoc_excerpt ); ?></textarea></p>
		
		<p><label for="post_content">Descrizione completa</label>
			<?php wp_editor( (string) $assoc_content, 'culturacsi_assoc_content', array( 'textarea_name' => 'post_content', 'media_buttons' => false, 'textarea_rows' => 8 ) ); ?>
		</p>

		<p><label for="city">Citta</label><input type="text" id="city" name="city" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'city', true ) ); ?>"></p>
		<p><label for="province">Provincia</label><input type="text" id="province" name="province" maxlength="2" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'province', true ) ); ?>"></p>
		<p><label for="region">Regione</label><input type="text" id="region" name="region" value="<?php echo esc_attr( (string) ( get_post_meta( $association_id, 'region', true ) ?: get_post_meta( $association_id, 'regione', true ) ) ); ?>"></p>
		<p><label for="comune">Comune</label><input type="text" id="comune" name="comune" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'comune', true ) ); ?>"></p>
		<p><label for="address">Indirizzo</label><input type="text" id="address" name="address" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'address', true ) ); ?>"></p>
		<p><label for="phone">Telefono</label><input type="text" id="phone" name="phone" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'phone', true ) ); ?>"></p>
		<p><label for="email">Email</label><input type="email" id="email" name="email" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'email', true ) ); ?>"></p>
		<p><label for="website">Sito web</label><input type="url" id="website" name="website" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'website', true ) ); ?>"></p>
		<p><label for="facebook">Facebook</label><input type="url" id="facebook" name="facebook" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'facebook', true ) ); ?>"></p>
		<p><label for="instagram">Instagram</label><input type="url" id="instagram" name="instagram" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'instagram', true ) ); ?>"></p>
		<p><label for="youtube">YouTube</label><input type="url" id="youtube" name="youtube" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'youtube', true ) ); ?>"></p>
		<p><label for="tiktok">TikTok</label><input type="url" id="tiktok" name="tiktok" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'tiktok', true ) ); ?>"></p>
		<p><label for="x">X</label><input type="url" id="x" name="x" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'x', true ) ); ?>"></p>
		<fieldset>
			<legend>Categorie Attivita</legend>
			<div class="category-checklist">
				<?php
				if ( ! function_exists( 'wp_terms_checklist' ) ) {
					require_once ABSPATH . 'wp-admin/includes/template.php';
				}
				ob_start();
				wp_terms_checklist(
					$association_id,
					array(
						'taxonomy'      => 'activity_category',
						'selected_cats' => wp_get_post_terms( $association_id, 'activity_category', array( 'fields' => 'ids' ) ),
						'checked_ontop' => false,
					)
				);
				$checklist_html = ob_get_clean();
				// Remove disabled attribute to allow non-admins to change categories
				echo str_replace( array( " disabled='disabled'", ' disabled="disabled"' ), '', $checklist_html );
				?>
			</div>
		</fieldset>
		<p><label for="featured_image">Logo associazione</label><input type="file" id="featured_image" name="featured_image" accept="image/*"></p>
		<?php if ( $association_id > 0 && has_post_thumbnail( $association_id ) ) : ?>
			<p class="current-logo"><?php echo get_the_post_thumbnail( $association_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		<?php endif; ?>
		<p><button type="submit" name="culturacsi_assoc_profile_submit" class="button button-primary"><?php echo $is_new ? 'Invia per Approvazione' : 'Salva Profilo'; ?></button></p>
	</form>
	<?php
	return ob_get_clean();
}

function culturacsi_portal_admin_control_panel_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Permessi insufficienti.</p>';
	}
	
	$is_admin = current_user_can( 'manage_options' );
	$role_label = culturacsi_portal_panel_role_label();

	ob_start();
	echo '<div class="assoc-portal-dashboard assoc-portal-section">';
	if ( ! $is_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		$assoc_name = $assoc_id > 0 ? get_the_title( $assoc_id ) : 'Associazione non trovata';
		echo '<h2 style="margin-bottom:0;">' . esc_html( $assoc_name ) . '</h2>';
		echo '<h3 style="margin-top:5px; margin-bottom:20px; color:#64748b; font-size:1.1rem; font-weight:normal;">' . esc_html( $role_label ) . '</h3>';
	} else {
		echo '<h2>' . esc_html( $role_label ) . '</h2>';
	}
	echo '<p>Apri una sezione del portale:</p>';
	echo '<ul class="assoc-portal-nav">';
	
	if ( $is_admin ) {
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/notizie/' ) ) . '">Notizie</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/eventi/' ) ) . '">Eventi</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/utenti/' ) ) . '">Utenti</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/associazioni/' ) ) . '">Associazioni</a></li>';
	} else {
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/notizie/' ) ) . '">Le tue Notizie</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/eventi/' ) ) . '">I tuoi Eventi</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/utenti/' ) ) . '">Collaboratori (Utenti)</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/associazione/' ) ) . '">Profilo Associazione</a></li>';
	}
	
	if ( $is_admin ) {
		echo '<li><a href="' . esc_url( admin_url() ) . '">Apri Bacheca WordPress</a></li>';
	}
	
	echo '</ul></div>';
	return ob_get_clean();
}

function culturacsi_portal_force_shortcode_registry(): void {
	add_shortcode( 'assoc_reserved_nav', 'culturacsi_portal_reserved_nav_shortcode' );
	add_shortcode( 'assoc_dashboard', 'culturacsi_portal_dashboard_shortcode' );
	add_shortcode( 'culturacsi_events_search', 'culturacsi_events_search_shortcode' );
	add_shortcode( 'culturacsi_eventi_search', 'culturacsi_events_search_shortcode' );
	add_shortcode( 'culturacsi_events_panel_search', 'culturacsi_events_search_shortcode' );
	add_shortcode( 'assoc_events_list', 'culturacsi_portal_events_list_shortcode' );
	add_shortcode( 'assoc_event_form', 'culturacsi_portal_event_form_shortcode' );
	add_shortcode( 'culturacsi_news_panel_search', 'culturacsi_news_panel_search_shortcode' );
	add_shortcode( 'culturacsi_notizie_search', 'culturacsi_news_panel_search_shortcode' );
	add_shortcode( 'culturacsi_notizie_panel_search', 'culturacsi_news_panel_search_shortcode' );
	add_shortcode( 'assoc_news_list', 'culturacsi_portal_news_list_shortcode' );
	add_shortcode( 'assoc_news_form', 'culturacsi_portal_news_form_shortcode' );
	add_shortcode( 'assoc_users_form', 'culturacsi_portal_users_form_shortcode' );
	add_shortcode( 'assoc_utenti_form', 'culturacsi_portal_users_form_shortcode' );
	add_shortcode( 'assoc_associations_form', 'culturacsi_portal_associations_form_shortcode' );
	add_shortcode( 'assoc_associazioni_form', 'culturacsi_portal_associations_form_shortcode' );
	add_shortcode( 'culturacsi_users_search', 'culturacsi_users_search_shortcode' );
	add_shortcode( 'culturacsi_utenti_search', 'culturacsi_users_search_shortcode' );
	add_shortcode( 'culturacsi_users_panel_search', 'culturacsi_users_search_shortcode' );
	add_shortcode( 'culturacsi_associations_search', 'culturacsi_associations_search_shortcode' );
	add_shortcode( 'culturacsi_associazioni_search', 'culturacsi_associations_search_shortcode' );
	add_shortcode( 'culturacsi_associations_panel_search', 'culturacsi_associations_search_shortcode' );
	add_shortcode( 'assoc_users_list', 'culturacsi_portal_users_list_shortcode' );
	add_shortcode( 'assoc_associations_list', 'culturacsi_portal_associations_list_shortcode' );
	add_shortcode( 'assoc_user_profile_form', 'culturacsi_portal_user_profile_shortcode' );
	add_shortcode( 'assoc_profile_form', 'culturacsi_portal_association_form_shortcode' );
	add_shortcode( 'assoc_association_form', 'culturacsi_portal_association_form_shortcode' );
	add_shortcode( 'assoc_admin_control_panel', 'culturacsi_portal_admin_control_panel_shortcode' );
}
add_action( 'init', 'culturacsi_portal_force_shortcode_registry', 9999 );

/**
 * Force frontend portal assets on reserved-area routes.
 */
function culturacsi_portal_enqueue_reserved_assets(): void {
	if ( is_admin() ) {
		return;
	}

	$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	$is_reserved_route = ( 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' ) );
	$is_login_modal_redirect = isset( $_GET['area_riservata_login'] ) && '1' === (string) $_GET['area_riservata_login'];
	
	if ( ! $is_reserved_route && ! $is_login_modal_redirect ) {
		return;
	}

	$css_file_path = WP_PLUGIN_DIR . '/assoc-portal/assets/css/portal.css';
	if ( file_exists( $css_file_path ) ) {
		wp_enqueue_style(
			'assoc-portal-style-forced',
			plugins_url( 'assoc-portal/assets/css/portal.css' ),
			array(),
			(string) filemtime( $css_file_path )
		);
		$dynamic_css = '';
		$modified_fields_csv = '';
		if ( isset( $_GET['event_id'] ) ) {
			$modified_fields_csv = (string) get_post_meta( (int) $_GET['event_id'], '_assoc_modified_fields_list', true );
		} elseif ( isset( $_GET['news_id'] ) ) {
			$modified_fields_csv = (string) get_post_meta( (int) $_GET['news_id'], '_assoc_modified_fields_list', true );
		} elseif ( isset( $_GET['association_id'] ) ) {
			$modified_fields_csv = (string) get_post_meta( (int) $_GET['association_id'], '_assoc_modified_fields_list', true );
		} elseif ( isset( $_GET['user_id'] ) ) {
			$modified_fields_csv = (string) get_user_meta( (int) $_GET['user_id'], '_assoc_modified_fields_list', true );
		}
		if ( current_user_can( 'manage_options' ) && '' !== $modified_fields_csv ) {
			$fields = array_filter( array_unique( explode( ',', $modified_fields_csv ) ) );
			$css_rules = array();
			foreach ( $fields as $f ) {
				$css_rules[] = '[name="' . esc_attr( $f ) . '"]';
				if ( 'post_content' === $f || 'description' === $f ) {
					$css_rules[] = '.wp-editor-wrap';
				}
				if ( 'tax_input[activity_category][]' === $f ) {
					$css_rules[] = '.category-checklist';
				}
			}
			if ( ! empty( $css_rules ) ) {
				$dynamic_css = implode( ', ', $css_rules ) . ' { outline: 2px solid #ef4444 !important; outline-offset: 2px; box-shadow: 0 0 8px rgba(239, 68, 68, 0.4) !important; border-color: #ef4444 !important; background-color: #fef2f2 !important; }';
			}
		}

		wp_add_inline_style(
			'assoc-portal-style-forced',
			'body.assoc-reserved-page #masthead, body.assoc-reserved-page header.site-header {display:block !important;} body.assoc-reserved-page .assoc-reserved-nav {margin-top: 8px;} ' .
			'.assoc-admin-table tr.is-pending-approval td { background-color: #fffbeb !important; } ' .
			'.assoc-admin-table tr.is-pending-approval td:first-child { box-shadow: inset 4px 0 0 #f59e0b !important; } ' .
			$dynamic_css
		);
	}
}
add_action( 'wp_enqueue_scripts', 'culturacsi_portal_enqueue_reserved_assets', 9999 );

add_filter(
	'body_class',
	static function( array $classes ): array {
		$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
		if ( 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' ) ) {
			$classes[] = 'assoc-ui-theme';
			$classes[] = 'assoc-reserved-page';
		}
		return $classes;
	},
	20
);

/**
 * Guaranteed reserved-area UI styles in case theme/plugin enqueue stack is inconsistent.
 */
function culturacsi_portal_render_modified_fields_script(): void {
	$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	$is_reserved_route = ( 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' ) );
	if ( ! $is_reserved_route ) {
		return;
	}
	echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.assoc-portal-form');
    forms.forEach(form => {
        let hiddenInput = form.querySelector('input[name=\"_assoc_modified_fields\"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = '_assoc_modified_fields';
            hiddenInput.value = '';
            form.appendChild(hiddenInput);
        }
        let changedBoxes = new Set();
        const recordChange = function(e) {
            if (e.target && e.target.name && e.target.name !== '_assoc_modified_fields' && !e.target.name.startsWith('culturacsi_')) {
                changedBoxes.add(e.target.name);
                hiddenInput.value = Array.from(changedBoxes).join(',');
            }
        };
        form.addEventListener('input', recordChange);
        form.addEventListener('change', recordChange);
    });
});
</script>";
}
add_action( 'wp_footer', 'culturacsi_portal_render_modified_fields_script', 9999 );

/**
 * Native wp-admin highlighting Support
 */
function culturacsi_portal_native_admin_head_css(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;
	
	$modified_fields_csv = '';
	if ( isset( $_GET['post'] ) ) {
		$modified_fields_csv = (string) get_post_meta( (int) $_GET['post'], '_assoc_modified_fields_list', true );
	} elseif ( isset( $_GET['user_id'] ) ) {
		$modified_fields_csv = (string) get_user_meta( (int) $_GET['user_id'], '_assoc_modified_fields_list', true );
	}

	if ( '' !== $modified_fields_csv ) {
		$fields = array_filter( array_unique( explode( ',', $modified_fields_csv ) ) );
		$css_rules = array();
		foreach ( $fields as $f ) {
			$css_rules[] = '[name="' . esc_attr( $f ) . '"]';
			if ( 'post_content' === $f || 'description' === $f ) {
				$css_rules[] = '.wp-editor-wrap';
			}
			if ( 'tax_input[activity_category][]' === $f ) {
				$css_rules[] = '#activity_categorydiv';
			}
		}
		if ( ! empty( $css_rules ) ) {
			echo '<style>' . implode( ', ', $css_rules ) . ' { outline: 3px solid #ef4444 !important; outline-offset: 2px; box-shadow: 0 0 10px rgba(239, 68, 68, 0.4) !important; background-color: #fef2f2 !important; }</style>';
		}
	}
	
	// Add pure CSS arrows for all selects universally in WP admin that correspond to our fields
	echo '<style>
		select {
			appearance: none;
			-webkit-appearance: none;
			-moz-appearance: none;
			background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 12 8\'%3E%3Cpath d=\'M1 1l5 5 5-5\' fill=\'none\' stroke=\'%23355a86\' stroke-width=\'1.7\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3E%3C/svg%3E") !important;
			background-repeat: no-repeat !important;
			background-position: right 11px center !important;
			background-size: 12px 8px !important;
			padding-right: 34px !important;
		}
	</style>';
}
add_action( 'admin_head', 'culturacsi_portal_native_admin_head_css' );
