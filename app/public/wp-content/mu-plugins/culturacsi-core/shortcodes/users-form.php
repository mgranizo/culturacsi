<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
