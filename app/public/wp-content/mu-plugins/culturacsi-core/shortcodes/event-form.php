<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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
		if ( ! culturacsi_portal_can_manage_post( $event, $user_id ) ) {
			return culturacsi_portal_notice( 'Non hai i permessi per modificare questo evento.', 'error' );
		}
	}

	$message_html = '';
	if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && ( isset( $_POST['culturacsi_event_submit'] ) || isset( $_POST['is_portal_ajax'] ) ) ) {
		if ( ! isset( $_POST['culturacsi_event_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_event_nonce'] ) ), 'culturacsi_event_save' ) ) {
			$message_html = culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
		} else {
			$form_event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
			$form_event    = $form_event_id > 0 ? get_post( $form_event_id ) : null;
			if ( $form_event_id > 0 && ( ! $form_event || 'event' !== $form_event->post_type ) ) {
				$message_html = culturacsi_portal_notice( 'Evento non valido.', 'error' );
			} elseif ( $form_event_id > 0 && ! culturacsi_portal_can_manage_post( $form_event, $user_id ) ) {
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

					$post_assoc_id = isset( $_POST['organizer_association_id'] ) ? absint( wp_unslash( $_POST['organizer_association_id'] ) ) : 0;
					// Authorization: admin can set any, others can only set their managed association
					$allowed_assoc_id = current_user_can( 'manage_options' ) ? $post_assoc_id : (int) culturacsi_portal_get_managed_association_id( $user_id );
					if ( $allowed_assoc_id > 0 ) {
						update_post_meta( $saved_id, 'organizer_association_id', $allowed_assoc_id );
					}

					$event_type = isset( $_POST['event_type'] ) ? (int) $_POST['event_type'] : 0;
					if ( $event_type > 0 ) {
						wp_set_post_terms( $saved_id, array( $event_type ), 'event_type' );
					} else {
						wp_set_post_terms( $saved_id, array(), 'event_type' );
					}

					$activity_term_ids = array();
					if ( isset( $_POST['tax_input']['activity_category'] ) && is_array( $_POST['tax_input']['activity_category'] ) ) {
						$activity_term_ids = array_values(
							array_filter(
								array_map(
									'intval',
									array_map(
										'wp_unslash',
										(array) $_POST['tax_input']['activity_category']
									)
								),
								static function( int $term_id ): bool {
									return $term_id > 0;
								}
							)
						);
					}
					if ( function_exists( 'culturacsi_activity_tree_set_post_terms' ) ) {
						culturacsi_activity_tree_set_post_terms( (int) $saved_id, $activity_term_ids );
					} else {
						wp_set_post_terms( $saved_id, $activity_term_ids, 'activity_category', false );
					}

					if ( ! empty( $_FILES['featured_image']['name'] ) && ! empty( $_FILES['featured_image']['tmp_name'] ) ) {
							$allowed_types = array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' );
							// Use server-side detection instead of the client-supplied MIME type.
							$finfo       = function_exists( 'finfo_open' ) ? new finfo( FILEINFO_MIME_TYPE ) : null;
							$actual_type = $finfo ? $finfo->file( $_FILES['featured_image']['tmp_name'] ) : '';
							if ( $actual_type && in_array( $actual_type, $allowed_types, true ) ) {
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

					if ( isset( $_POST['is_portal_ajax'] ) && '1' === (string) $_POST['is_portal_ajax'] ) {
						while ( ob_get_level() > 0 ) {
							ob_end_clean();
						}
						wp_send_json_success( 'Evento salvato correttamente.', 200 );
					}
					wp_safe_redirect( add_query_arg( array( 'event_id' => (int) $saved_id, 'saved' => '1' ), home_url( '/area-riservata/eventi/nuovo/' ) ) );
					exit;
				}
			}
		}

		if ( isset( $_POST['is_portal_ajax'] ) && '1' === (string) $_POST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) {
				ob_end_clean();
			}
			wp_send_json_error( wp_strip_all_tags( $message_html ) ?: 'Errore durante il salvataggio.', 400 );
		}
	}

	if ( isset( $_GET['saved'] ) && '1' === $_GET['saved'] ) {
		$message_html = culturacsi_portal_notice(
			current_user_can( 'manage_options' ) ? 'Evento salvato correttamente.' : 'Modifiche evento inviate per approvazione.',
			'success'
		);
	}

	$has_selected_type = ( $event_id > 0 );
	if ( ! $has_selected_type && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['culturacsi_event_submit'] ) ) {
		$has_selected_type = true;
	}
	if ( ! $has_selected_type && isset( $_GET['flow'] ) ) {
		$has_selected_type = ( 'event' === sanitize_key( wp_unslash( $_GET['flow'] ) ) );
	}

	$meta = $event_id > 0 ? get_post_meta( $event_id ) : array();
	$get_meta = static function( string $key ) use ( $meta ): string {
		return isset( $meta[ $key ][0] ) ? (string) $meta[ $key ][0] : '';
	};
	$selected_event_type = $event_id > 0 ? wp_get_post_terms( $event_id, 'event_type', array( 'fields' => 'ids' ) ) : array();
	$selected_activity_terms = ( $event_id > 0 && taxonomy_exists( 'activity_category' ) )
		? wp_get_post_terms( $event_id, 'activity_category', array( 'fields' => 'ids' ) )
		: array();
	if ( is_wp_error( $selected_activity_terms ) ) {
		$selected_activity_terms = array();
	}

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( ! $has_selected_type ) {
		if ( function_exists( 'culturacsi_portal_creation_hub_switcher' ) ) {
			echo culturacsi_portal_creation_hub_switcher( '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( function_exists( 'culturacsi_portal_render_creation_preselect_tutorial' ) ) {
			echo culturacsi_portal_render_creation_preselect_tutorial(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		return (string) ob_get_clean();
	}
	if ( function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
		echo culturacsi_portal_render_process_tutorial(
			array(
				'title'     => '',
				'intro'     => 'Segui i passaggi per inserire un evento completo e chiaro.',
				'summary'   => 'Tutorial rapido',
				'checklist' => array(
					array( 'label' => 'Tipo contenuto selezionato', 'selectors' => array( '#csi-creation-hub-select' ), 'mode' => 'all' ),
					array( 'label' => 'Titolo compilato', 'selectors' => array( '#post_title' ), 'mode' => 'all' ),
					array( 'label' => 'Data di inizio compilata', 'selectors' => array( '#start_date' ), 'mode' => 'all' ),
					array( 'label' => 'Attivita correlata selezionata', 'selectors' => array( 'input[name="tax_input[activity_category][]"]:checked' ), 'mode' => 'any' ),
					array( 'label' => 'Dettagli evento inseriti', 'selectors' => array( '#culturacsi_event_content', '#venue_name' ), 'mode' => 'any' ),
				),
				'steps'     => array(
					array( 'text' => 'Scegli il tipo nel menu Crea nuovo contenuto.' ),
					array( 'text' => 'Inserisci titolo e data di inizio.' ),
					array( 'text' => 'Aggiungi descrizione, luogo e immagine.' ),
					array( 'text' => 'Salva l\'evento.' ),
				),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	if ( function_exists( 'culturacsi_portal_creation_hub_switcher' ) ) {
		echo culturacsi_portal_creation_hub_switcher( 'event' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
	$root_url = home_url( '/area-riservata/contenuti/' );
	?>
	<form class="assoc-portal-form" method="post" enctype="multipart/form-data" data-redirect-url="<?php echo esc_url( $root_url ); ?>">
		<input type="hidden" name="event_id" value="<?php echo esc_attr( (string) $event_id ); ?>">
		<?php wp_nonce_field( 'culturacsi_event_save', 'culturacsi_event_nonce' ); ?>
		<h2><?php echo esc_html( $event_id > 0 ? 'Modifica Evento' : 'Nuovo Evento' ); ?></h2>
		<p><?php echo culturacsi_portal_label_with_tip( 'post_title', 'Nome dell\'evento visibile pubblicamente.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="text" id="post_title" name="post_title" required value="<?php echo esc_attr( $event_id > 0 ? (string) get_the_title( $event_id ) : '' ); ?>"></p>
		<?php 
		$current_event_assoc = $event_id > 0 ? (int) get_post_meta( $event_id, 'organizer_association_id', true ) : 0;
		if ( function_exists( 'culturacsi_portal_render_association_selection_field' ) ) {
			echo culturacsi_portal_render_association_selection_field( $current_event_assoc ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<p><?php echo culturacsi_portal_label_with_tip( 'start_date', 'Data e ora inizio *', 'Usa sempre data e ora locali reali di inizio.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><input type="datetime-local" id="start_date" name="start_date" required value="<?php echo esc_attr( $get_meta( 'start_date' ) ); ?>"></p>
		<p><label for="end_date">Data e ora fine</label><input type="datetime-local" id="end_date" name="end_date" value="<?php echo esc_attr( $get_meta( 'end_date' ) ); ?>"></p>
		<p><?php echo culturacsi_portal_label_with_tip( 'event_type', 'Categoria evento (facoltativa)', 'Serve a organizzare la ricerca degli eventi.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
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
			<span class="description">Usa questo campo solo per classificare l'evento.</span>
		</p>
		<fieldset>
			<legend>Attivita correlate (albero settori)</legend>
			<input type="hidden" name="tax_input[activity_category][]" value="0" />
			<div class="category-checklist">
				<?php
				if ( function_exists( 'culturacsi_activity_tree_render_checklist' ) ) {
					echo culturacsi_activity_tree_render_checklist(
						array_map( 'intval', (array) $selected_activity_terms ),
						'tax_input[activity_category][]'
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					wp_terms_checklist(
						$event_id,
						array(
							'taxonomy'      => 'activity_category',
							'selected_cats' => array_map( 'intval', (array) $selected_activity_terms ),
						)
					);
				}
				?>
			</div>
		</fieldset>
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
