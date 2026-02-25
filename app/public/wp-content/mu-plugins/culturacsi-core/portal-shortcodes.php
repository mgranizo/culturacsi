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

require_once __DIR__ . '/shortcodes/events-list.php';

require_once __DIR__ . '/shortcodes/event-form.php';

require_once __DIR__ . '/shortcodes/news-list.php';

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

require_once __DIR__ . '/shortcodes/users-list.php';

require_once __DIR__ . '/shortcodes/associations-list.php';

require_once __DIR__ . '/shortcodes/news-form.php';

require_once __DIR__ . '/shortcodes/users-form.php';

require_once __DIR__ . '/shortcodes/associations-form.php';

require_once __DIR__ . '/shortcodes/user-profile-form.php';

require_once __DIR__ . '/shortcodes/association-form.php';

require_once __DIR__ . '/shortcodes/admin-control-panel.php';

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
	add_shortcode( 'assoc_cronologia_list', 'culturacsi_portal_cronologia_list_shortcode' );
}
add_action( 'init', 'culturacsi_portal_force_shortcode_registry', 20 );

require_once __DIR__ . '/shortcodes/cronologia-list.php';

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
