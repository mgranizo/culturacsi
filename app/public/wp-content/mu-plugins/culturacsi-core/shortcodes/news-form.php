<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
