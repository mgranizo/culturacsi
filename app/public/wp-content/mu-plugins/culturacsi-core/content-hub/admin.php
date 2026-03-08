<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content hub assets and admin editor experience.
 */
function culturacsi_content_hub_register_assets() {
	if ( ! file_exists( culturacsi_content_hub_asset_path( 'content-hub.css' ) ) ) {
		return;
	}

	wp_register_style(
		'culturacsi-content-hub-style',
		culturacsi_content_hub_asset_url( 'content-hub.css' ),
		array(),
		culturacsi_content_hub_asset_version( 'content-hub.css' )
	);

	wp_register_script(
		'culturacsi-content-hub-script',
		culturacsi_content_hub_asset_url( 'content-hub.js' ),
		array(),
		culturacsi_content_hub_asset_version( 'content-hub.js' ),
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

	wp_enqueue_style(
		'culturacsi-content-hub-admin',
		culturacsi_content_hub_asset_url( 'content-hub-admin.css' ),
		array(),
		culturacsi_content_hub_asset_version( 'content-hub-admin.css' )
	);

	wp_enqueue_script(
		'culturacsi-content-hub-admin',
		culturacsi_content_hub_asset_url( 'content-hub-admin.js' ),
		array( 'jquery' ),
		culturacsi_content_hub_asset_version( 'content-hub-admin.js' ),
		true
	);
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
	$button_label = trim( (string) get_post_meta( $post->ID, '_csi_content_hub_button_label', true ) );
	$allowed_labels = array( 'Acquista', 'Visita', 'Scarica' );
	if ( ! in_array( $button_label, $allowed_labels, true ) ) {
		$button_label = '';
	}

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
			<select id="csi-content-hub-button-label" name="csi_content_hub_button_label" class="regular-text">
				<option value=""><?php esc_html_e( 'Seleziona etichetta...', 'culturacsi' ); ?></option>
				<option value="Acquista" <?php selected( $button_label, 'Acquista' ); ?>>Acquista</option>
				<option value="Visita" <?php selected( $button_label, 'Visita' ); ?>>Visita</option>
				<option value="Scarica" <?php selected( $button_label, 'Scarica' ); ?>>Scarica</option>
			</select>
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
	$allowed_labels = array( 'Acquista', 'Visita', 'Scarica' );
	if ( ! in_array( $button_label, $allowed_labels, true ) ) {
		$button_label = '';
	}
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

