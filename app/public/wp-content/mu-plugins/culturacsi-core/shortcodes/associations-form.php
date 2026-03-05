<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
	$normalize_multivalue_text = static function ( $raw ): string {
		$parts = preg_split( '/[\r\n,;|]+/', (string) $raw );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$values = array();
		foreach ( $parts as $part ) {
			$val = trim( sanitize_text_field( (string) $part ) );
			if ( '' !== $val ) {
				$values[] = $val;
			}
		}
		return implode( ', ', array_values( array_unique( $values ) ) );
	};
	$normalize_multivalue_email = static function ( $raw ): string {
		$parts = preg_split( '/[\r\n,;|\s]+/', (string) $raw );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		$emails = array();
		foreach ( $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			if ( '' !== $email ) {
				$emails[ strtolower( $email ) ] = $email;
			}
		}
		return implode( ', ', array_values( $emails ) );
	};
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && ( isset( $_POST['culturacsi_associations_form_submit'] ) || isset( $_REQUEST['is_portal_ajax'] ) ) ) {
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
							if ( 'phone' === $key ) {
								$val = $normalize_multivalue_text( $raw );
							}
							if ( in_array( $key, array( 'website', 'facebook', 'instagram', 'youtube', 'tiktok', 'x' ), true ) ) {
								$val = esc_url_raw( $val );
							}
							if ( 'email' === $key ) {
								$val = $normalize_multivalue_email( $raw );
							}
							update_post_meta( $saved_id, $key, $val );
						}
						update_post_meta( $saved_id, 'regione', (string) get_post_meta( $saved_id, 'region', true ) );

						$term_ids = array();
						if ( isset( $_POST['tax_input']['activity_category'] ) && is_array( $_POST['tax_input']['activity_category'] ) ) {
							$term_ids = array_map( 'intval', wp_unslash( $_POST['tax_input']['activity_category'] ) );
						}
						if ( function_exists( 'culturacsi_activity_tree_set_post_terms' ) ) {
							culturacsi_activity_tree_set_post_terms( (int) $saved_id, $term_ids );
						} else {
							wp_set_post_terms( $saved_id, $term_ids, 'activity_category' );
						}

						if ( ! empty( $_FILES['featured_image']['name'] ) ) {
							require_once ABSPATH . 'wp-admin/includes/image.php';
							require_once ABSPATH . 'wp-admin/includes/file.php';
							require_once ABSPATH . 'wp-admin/includes/media.php';
							$attachment_id = media_handle_upload( 'featured_image', $saved_id );
							if ( ! is_wp_error( $attachment_id ) ) {
								set_post_thumbnail( $saved_id, (int) $attachment_id );
							}
						}



						if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
							while ( ob_get_level() > 0 ) {
								ob_end_clean();
							}
							wp_send_json_success( 'Associazione salvata correttamente.', 200 );
						}
						wp_safe_redirect( add_query_arg( array( 'saved' => '1' ), home_url( '/area-riservata/associazioni/' ) ) );
						exit;
					}
				}
			}
		}

		if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			wp_send_json_error( wp_strip_all_tags( $message_html ) ?: 'Errore durante il salvataggio.', 400 );
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
	if ( function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
		echo culturacsi_portal_render_process_tutorial(
			array(
				'title'     => '',
				'intro'     => 'Completa i dati essenziali dell\'associazione in modo semplice e ordinato.',
				'summary'   => 'Tutorial rapido',
				'checklist' => array(
					array( 'label' => 'Nome associazione compilato', 'selectors' => array( '#post_title' ), 'mode' => 'all' ),
					array( 'label' => 'Descrizione o sommario compilati', 'selectors' => array( '#post_excerpt', '#culturacsi_association_content' ), 'mode' => 'any' ),
					array( 'label' => 'Categoria attivita selezionata', 'selectors' => array( 'input[name="tax_input[activity_category][]"]:checked' ), 'mode' => 'any' ),
				),
				'steps'     => array(
					array( 'text' => 'Inserisci nome e stato dell\'associazione.' ),
					array( 'text' => 'Compila descrizione, contatti e social.' ),
					array( 'text' => 'Seleziona almeno una categoria attivita.' ),
					array( 'text' => 'Salva l\'associazione.' ),
				),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	$root_url = home_url( '/area-riservata/associazioni/' );
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data" data-redirect-url="<?php echo esc_url( $root_url ); ?>">
		<?php wp_nonce_field( 'culturacsi_associations_form_save', 'culturacsi_associations_form_nonce' ); ?>
		<input type="hidden" name="form_association_id" value="<?php echo esc_attr( (string) $association_id ); ?>">
		<h2><?php echo esc_html( $association_id > 0 ? 'Modifica Associazione' : 'Crea Associazione' ); ?></h2>
		<p><?php echo culturacsi_portal_label_with_tip( 'post_title', 'Nome associazione *', 'Inserisci il nome completo ufficiale.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="text" id="post_title" name="post_title" required value="<?php echo esc_attr( $association instanceof WP_Post ? (string) $association->post_title : '' ); ?>"></p>
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
		<p><label for="phone">Telefono</label><textarea id="phone" name="phone" rows="2" placeholder="Es. +39 333 1234567, 02 1234567"><?php echo esc_textarea( (string) get_post_meta( $association_id, 'phone', true ) ); ?></textarea><small>Puoi inserire piu numeri separati da virgola o una per riga.</small></p>
		<p><label for="email">Email</label><textarea id="email" name="email" rows="2" placeholder="Es. info@dominio.it, segreteria@dominio.it"><?php echo esc_textarea( (string) get_post_meta( $association_id, 'email', true ) ); ?></textarea><small>Puoi inserire piu email separate da virgola o una per riga.</small></p>
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
				if ( function_exists( 'culturacsi_activity_tree_render_checklist' ) ) {
					echo culturacsi_activity_tree_render_checklist( $selected_terms, 'tax_input[activity_category][]', (int) $association_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
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
				}
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
