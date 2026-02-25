<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
