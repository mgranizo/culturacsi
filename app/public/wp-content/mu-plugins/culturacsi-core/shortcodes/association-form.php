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
				$meta_fields = array( 'city', 'province', 'region', 'address', 'phone', 'email', 'website', 'facebook', 'instagram', 'youtube', 'tiktok', 'x' );
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
				update_post_meta( $association_id, 'comune', (string) get_post_meta( $association_id, 'city', true ) );

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
		<p><label for="city">Città / Comune</label><input type="text" id="city" name="city" value="<?php echo esc_attr( (string) ( get_post_meta( $association_id, 'city', true ) ?: get_post_meta( $association_id, 'comune', true ) ) ); ?>"></p>
		<p><label for="province">Provincia (Sigla, es. MI)</label><input type="text" id="province" name="province" maxlength="2" value="<?php echo esc_attr( (string) get_post_meta( $association_id, 'province', true ) ); ?>"></p>
		<p><label for="region">Regione</label><input type="text" id="region" name="region" value="<?php echo esc_attr( (string) ( get_post_meta( $association_id, 'region', true ) ?: get_post_meta( $association_id, 'regione', true ) ) ); ?>"></p>
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
