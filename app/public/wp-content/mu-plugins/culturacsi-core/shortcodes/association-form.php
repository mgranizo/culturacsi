<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function culturacsi_portal_association_form_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$user_id = get_current_user_id();
	$association_id = culturacsi_portal_get_managed_association_id( $user_id );
	$is_new = ( $association_id <= 0 );

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
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && ( isset( $_POST['culturacsi_assoc_profile_submit'] ) || isset( $_POST['is_portal_ajax'] ) ) ) {
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
				$meta_fields = array( 'city', 'province', 'region', 'address', 'phone', 'email', 'website', 'facebook', 'instagram', 'youtube', 'tiktok', 'x' );
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
					update_post_meta( $association_id, $key, $val );
				}
				update_post_meta( $association_id, 'regione', (string) get_post_meta( $association_id, 'region', true ) );
				update_post_meta( $association_id, 'comune', (string) get_post_meta( $association_id, 'city', true ) );

				$term_ids = array();
				if ( isset( $_POST['tax_input']['activity_category'] ) && is_array( $_POST['tax_input']['activity_category'] ) ) {
					$term_ids = array_map( 'intval', wp_unslash( $_POST['tax_input']['activity_category'] ) );
				}
				if ( function_exists( 'culturacsi_activity_tree_set_post_terms' ) ) {
					culturacsi_activity_tree_set_post_terms( (int) $association_id, $term_ids );
				} else {
					wp_set_post_terms( $association_id, $term_ids, 'activity_category' );
				}

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
				// Clear the site admin list dropdown cache to reflect data changes immediately.
				delete_transient( 'culturacsi_association_dropdowns_v1' );
			}
			if ( isset( $_POST['is_portal_ajax'] ) && '1' === (string) $_POST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				wp_send_json_success( 'Profilo associazione salvato correttamente.', 200 );
			}
			wp_safe_redirect( add_query_arg( array( 'saved' => '1' ), home_url( '/area-riservata/associazione/' ) ) );
			exit;
		}

		if ( isset( $_POST['is_portal_ajax'] ) && '1' === (string) $_POST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			wp_send_json_error( wp_strip_all_tags( $message_html ) ?: 'Errore durante il salvataggio.', 400 );
		}
	}

	$association = $association_id > 0 ? get_post( $association_id ) : null;
	$assoc_title = $association ? $association->post_title : '';
	$assoc_excerpt = $association ? $association->post_excerpt : '';
	$assoc_content = $association ? $association->post_content : '';

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
		echo culturacsi_portal_render_process_tutorial(
			array(
				'title'     => '',
				'intro'     => 'Questa guida aiuta a compilare il profilo in modo completo e leggibile.',
				'summary'   => 'Tutorial rapido',
				'checklist' => array(
					array( 'label' => 'Nome associazione compilato', 'selectors' => array( '#post_title' ), 'mode' => 'all' ),
					array( 'label' => 'Sommario o descrizione compilati', 'selectors' => array( '#post_excerpt', '#culturacsi_assoc_content' ), 'mode' => 'any' ),
					array( 'label' => 'Categoria attivita selezionata', 'selectors' => array( 'input[name="tax_input[activity_category][]"]:checked' ), 'mode' => 'any' ),
				),
				'steps'     => array(
					array( 'text' => 'Inserisci nome e descrizione dell\'associazione.' ),
					array( 'text' => 'Compila i contatti principali.' ),
					array( 'text' => 'Seleziona le categorie attivita corrette.' ),
					array( 'text' => 'Salva o invia per approvazione.' ),
				),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	$root_url = home_url( '/area-riservata/associazione/' );
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data" data-redirect-url="<?php echo esc_url( $root_url ); ?>">
		<?php wp_nonce_field( 'culturacsi_assoc_profile_save', 'culturacsi_assoc_profile_nonce' ); ?>
		<h2><?php echo $is_new ? 'Registra la tua Associazione' : 'Dati Associazione'; ?></h2>
		
		<p><?php echo culturacsi_portal_label_with_tip( 'post_title', 'Nome associazione *', 'Inserisci il nome ufficiale completo.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="text" id="post_title" name="post_title" required value="<?php echo esc_attr( (string) $assoc_title ); ?>"></p>
		
		<p><label for="post_excerpt">Sommario (Breve presentazione)</label><textarea id="post_excerpt" name="post_excerpt" rows="3"><?php echo esc_textarea( (string) $assoc_excerpt ); ?></textarea></p>
		
		<p><label for="post_content">Descrizione completa</label>
			<?php wp_editor( (string) $assoc_content, 'culturacsi_assoc_content', array( 'textarea_name' => 'post_content', 'media_buttons' => false, 'textarea_rows' => 8 ) ); ?>
		</p>
		<p><label for="city">Città / Comune</label><input type="text" id="city" name="city" value="<?php echo esc_attr( (string) ( get_post_meta( $association_id, 'city', true ) ?: get_post_meta( $association_id, 'comune', true ) ) ); ?>"></p>
		<p><label for="province">Provincia (Sigla, es. MI)</label><input type="text" id="province" name="province" maxlength="2" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'province', true ) ); ?>"></p>
		<p><label for="region">Regione</label><input type="text" id="region" name="region" value="<?php echo esc_attr( (string) ( get_post_meta( $association_id, 'region', true ) ?: get_post_meta( $association_id, 'regione', true ) ) ); ?>"></p>
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
				$selected_terms = wp_get_post_terms( $association_id, 'activity_category', array( 'fields' => 'ids' ) );
				if ( is_wp_error( $selected_terms ) || ! is_array( $selected_terms ) ) {
					$selected_terms = array();
				}
				if ( function_exists( 'culturacsi_activity_tree_render_checklist' ) ) {
					echo culturacsi_activity_tree_render_checklist( $selected_terms, 'tax_input[activity_category][]', (int) $association_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					if ( ! function_exists( 'wp_terms_checklist' ) ) {
						require_once ABSPATH . 'wp-admin/includes/template.php';
					}
					ob_start();
					wp_terms_checklist(
						$association_id,
						array(
							'taxonomy'      => 'activity_category',
							'selected_cats' => $selected_terms,
							'checked_ontop' => false,
						)
					);
					$checklist_html = ob_get_clean();
					// Remove disabled attribute to allow non-admins to change categories
					echo str_replace( array( " disabled='disabled'", ' disabled="disabled"' ), '', $checklist_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
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
