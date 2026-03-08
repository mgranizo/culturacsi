<?php
/**
 * Portal Actions
 *
 * Handles row-level CRUD/moderation actions for posts and users inside the
 * reserved-area portal. Also houses the small status-label helpers that the
 * list shortcodes depend on.
 *
 * @package CulturaCsi\Core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ---------------------------------------------------------------------------
// Action button form
// ---------------------------------------------------------------------------

if ( ! function_exists( 'culturacsi_portal_action_button_form' ) ) {
	/**
	 * Generate an HTML form that submits a row action via POST.
	 *
	 * @param array{
	 *   context:      string,
	 *   action:       string,
	 *   target_id:    int,
	 *   label:        string,
	 *   class?:       string,
	 *   confirm?:     bool,
	 *   confirm_text?: string
	 * } $args Action configuration.
	 * @return string HTML form markup, or empty string on invalid args.
	 */
	function culturacsi_portal_action_button_form( array $args ): string {
		$context      = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : '';
		$action       = isset( $args['action'] ) ? sanitize_key( (string) $args['action'] ) : '';
		$target_id    = isset( $args['target_id'] ) ? (int) $args['target_id'] : 0;
		$label        = isset( $args['label'] ) ? (string) $args['label'] : '';
		$class        = isset( $args['class'] ) ? (string) $args['class'] : '';
		$confirm      = ! empty( $args['confirm'] );
		$confirm_text = isset( $args['confirm_text'] ) ? (string) $args['confirm_text'] : 'Confermi questa azione?';

		if ( '' === $context || '' === $action || $target_id <= 0 || '' === $label ) {
			return '';
		}

		$nonce_action = 'culturacsi_row_action_' . $context;
		$button_class = 'assoc-action-chip ' . $class;
		$onclick_attr = $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm_text ) . '\');"' : '';
		$form_class   = 'assoc-row-action-form';

		if ( false !== strpos( $class, 'chip-toggle' ) ) {
			$form_class .= ' is-toggle';
		}

		// Map context to the correct area-riservata route segment.
		$route_map = array(
			'user'              => 'utenti',
			'event'             => 'contenuti',
			'news'              => 'contenuti',
			'csi_content_entry' => 'contenuti',
			'content'           => 'contenuti',
			'association'       => current_user_can( 'manage_options' ) ? 'associazioni' : 'associazione',
		);
		$root_path = isset( $route_map[ $context ] ) ? $route_map[ $context ] : 'contenuti';
		$root_url  = home_url( '/area-riservata/' . $root_path . '/' );

		$html  = '<form method="post" class="' . esc_attr( $form_class ) . ' assoc-portal-form" data-redirect-url="' . esc_url( $root_url ) . '">';
		$html .= wp_nonce_field( $nonce_action, 'culturacsi_row_action_nonce', true, false );
		$html .= '<input type="hidden" name="culturacsi_row_context" value="' . esc_attr( $context ) . '">';
		$html .= '<input type="hidden" name="culturacsi_row_action" value="' . esc_attr( $action ) . '">';
		$html .= '<input type="hidden" name="culturacsi_row_target_id" value="' . esc_attr( (string) $target_id ) . '">';
		$html .= '<button type="submit" name="culturacsi_row_action_submit" class="' . esc_attr( trim( $button_class ) ) . '"' . $onclick_attr . '>' . esc_html( $label ) . '</button>';
		$html .= '</form>';
		return $html;
	}
}

// ---------------------------------------------------------------------------
// Post row-action processor
// ---------------------------------------------------------------------------

if ( ! function_exists( 'culturacsi_portal_process_post_row_action' ) ) {
	/**
	 * Handle a submitted row action form for a post-type entity.
	 *
	 * Reads POST data, verifies the nonce, confirms the current user can
	 * manage the target post, and performs the requested action (delete /
	 * approve / reject / hold).
	 *
	 * @param string $context            The action context key (e.g. 'event', 'news').
	 * @param string $post_type          WordPress post-type slug.
	 * @param bool   $allow_non_admin_delete Whether association managers can delete their own content.
	 * @return string HTML notice string, or empty string when the request should not be processed.
	 */
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

		// Nonce verification.
		$nonce_action = 'culturacsi_row_action_' . $context;
		if ( ! isset( $_POST['culturacsi_row_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_row_action_nonce'] ) ), $nonce_action ) ) {
			$error_msg = 'Verifica di sicurezza non valida.';
			if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				wp_send_json_error( $error_msg, 403 );
			}
			return culturacsi_portal_notice( $error_msg, 'error' );
		}

		$action    = sanitize_key( wp_unslash( $_POST['culturacsi_row_action'] ) );
		$target_id = absint( wp_unslash( $_POST['culturacsi_row_target_id'] ) );
		$post      = $target_id > 0 ? get_post( $target_id ) : null;

		if ( ! $post instanceof WP_Post || $post->post_type !== $post_type ) {
			return culturacsi_portal_notice( 'Elemento non trovato.', 'error' );
		}

		$user_id         = get_current_user_id();
		$is_site_admin   = current_user_can( 'manage_options' );
		$can_manage_item = culturacsi_portal_can_manage_post( $post, $user_id );

		// ---- Delete ----
		if ( 'delete' === $action ) {
			if ( ! $is_site_admin && ( ! $allow_non_admin_delete || ! $can_manage_item ) ) {
				return culturacsi_portal_notice( 'Permessi insufficienti per eliminare.', 'error' );
			}
			$deleted = wp_trash_post( $target_id );
			if ( false === $deleted || null === $deleted ) {
				return culturacsi_portal_notice( 'Impossibile eliminare l\'elemento.', 'error' );
			}
			$success_msg = 'Elemento spostato nel cestino.';
			if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				wp_send_json_success( $success_msg, 200 );
			}
			return culturacsi_portal_notice( $success_msg, 'success' );
		}

		if ( ! $can_manage_item ) {
			return culturacsi_portal_notice( 'Permessi insufficienti.', 'error' );
		}

		// ---- Moderation ----
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
			$success_msg = $labels[ $action ];
			if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				wp_send_json_success( $success_msg, 200 );
			}
			return culturacsi_portal_notice( $success_msg, 'success' );
		}

		return culturacsi_portal_notice( 'Azione non riconosciuta.', 'error' );
	}
}

// ---------------------------------------------------------------------------
// User approval status helpers
// ---------------------------------------------------------------------------

if ( ! function_exists( 'culturacsi_portal_user_approval_label' ) ) {
	/**
	 * Get a human-readable approval status label for a user.
	 *
	 * @param WP_User $user The user to inspect.
	 * @return string Italian label: 'Approvato', 'Revocato', or 'In attesa'.
	 */
	function culturacsi_portal_user_approval_label( WP_User $user ): string {
		if ( culturacsi_portal_is_user_approved( $user ) ) {
			return 'Approvato';
		}
		$state = (string) get_user_meta( $user->ID, 'assoc_moderation_state', true );
		if ( 'rejected' === $state ) {
			return 'Revocato';
		}
		return 'In attesa';
	}
}

if ( ! function_exists( 'culturacsi_portal_user_approval_class' ) ) {
	/**
	 * Get a CSS status class for a user based on their approval state.
	 *
	 * @param WP_User $user The user to inspect.
	 * @return string CSS class string.
	 */
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
}

if ( ! function_exists( 'culturacsi_portal_is_user_approved' ) ) {
	/**
	 * Check whether a user is fully approved (not pending moderation).
	 *
	 * Site admins are always considered approved. Association managers are
	 * approved if they do not have the `assoc_pending_approval` meta flag.
	 *
	 * @param WP_User $user The user to inspect.
	 * @return bool True if the user is approved.
	 */
	function culturacsi_portal_is_user_approved( WP_User $user ): bool {
		if ( user_can( $user, 'manage_options' ) ) {
			return true;
		}
		return in_array( 'association_manager', (array) $user->roles, true )
			&& '1' !== (string) get_user_meta( $user->ID, 'assoc_pending_approval', true );
	}
}

// ---------------------------------------------------------------------------
// User row-action processor
// ---------------------------------------------------------------------------

if ( ! function_exists( 'culturacsi_portal_process_user_row_action' ) ) {
	/**
	 * Handle a submitted row action form for a user entity.
	 *
	 * Only site admins can perform user moderation actions. Reads POST data,
	 * verifies the nonce and permissions, then performs approve / reject / hold / delete.
	 *
	 * @return string HTML notice string, or empty string when the request should not be processed.
	 */
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

		// Nonce verification.
		if ( ! isset( $_POST['culturacsi_row_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_row_action_nonce'] ) ), 'culturacsi_row_action_user' ) ) {
			$error_msg = 'Verifica di sicurezza non valida.';
			if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				wp_send_json_error( $error_msg, 403 );
			}
			return culturacsi_portal_notice( $error_msg, 'error' );
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

		// ---- Delete ----
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
			if ( function_exists( 'culturacsi_log_event' ) ) {
				culturacsi_log_event( 'delete_user', 'user', (int) $user->ID, 'User deleted: ' . $user->user_login );
			}
			$success_msg = 'Utente eliminato.';
			if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				wp_send_json_success( $success_msg, 200 );
			}
			return culturacsi_portal_notice( $success_msg, 'success' );
		}

		// ---- Approve / Reject / Hold ----
		if ( in_array( $action, array( 'approve', 'reject', 'hold' ), true ) ) {
			if ( user_can( $user, 'manage_options' ) ) {
				return culturacsi_portal_notice( 'Azione non consentita su questo utente.', 'error' );
			}
			if ( 'approve' === $action ) {
				$user->set_role( 'association_manager' );
				delete_user_meta( (int) $user->ID, 'assoc_pending_approval' );
				update_user_meta( (int) $user->ID, 'assoc_moderation_state', 'approved' );
				if ( function_exists( 'culturacsi_log_event' ) ) {
					culturacsi_log_event( 'approve_user', 'user', (int) $user->ID, 'User approved: ' . $user->user_login );
				}
				$success_msg = 'Utente approvato.';
				if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
					while ( ob_get_level() > 0 ) {
						ob_end_clean();
					}
					wp_send_json_success( $success_msg, 200 );
				}
				return culturacsi_portal_notice( $success_msg, 'success' );
			}

			// Hold or reject.
			$user->set_role( 'association_pending' );
			update_user_meta( (int) $user->ID, 'assoc_pending_approval', '1' );
			$new_state = ( 'reject' === $action ? 'rejected' : 'hold' );
			update_user_meta( (int) $user->ID, 'assoc_moderation_state', $new_state );
			if ( function_exists( 'culturacsi_log_event' ) ) {
				culturacsi_log_event( 'moderate_user', 'user', (int) $user->ID, "Action: $action ($new_state) for " . $user->user_login );
			}
			$success_msg = 'Stato utente aggiornato.';
			if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				wp_send_json_success( $success_msg, 200 );
			}
			return culturacsi_portal_notice( $success_msg, 'success' );
		}

		return culturacsi_portal_notice( 'Azione non riconosciuta.', 'error' );
	}
}
