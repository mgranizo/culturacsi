<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common UI and modal components for the portal interface.
 */

if ( ! function_exists( 'culturacsi_get_site_logo_url' ) ) {
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
}

if ( ! function_exists( 'culturacsi_portal_panel_role_label' ) ) {
	function culturacsi_portal_panel_role_label(): string {
		if ( current_user_can( 'manage_options' ) ) {
			return 'Pannello di Controllo Site Admin';
		}
		if ( current_user_can( 'association_manager' ) ) {
			return 'Pannello di Controllo Association Admin';
		}
		return 'Area Riservata';
	}
}

if ( ! function_exists( 'culturacsi_portal_nav_item_is_active' ) ) {
	function culturacsi_portal_nav_item_is_active( string $item_url, string $current_path ): bool {
		$item_path = trim( (string) wp_parse_url( $item_url, PHP_URL_PATH ), '/' );
		if ( '' === $item_path ) {
			return false;
		}
		$current_with_slash = rtrim( $current_path, '/' ) . '/';
		$item_with_slash    = rtrim( $item_path, '/' ) . '/';
		return 0 === strpos( $current_with_slash, $item_with_slash );
	}
}

if ( ! function_exists( 'culturacsi_portal_reserved_nav_shortcode' ) ) {
	function culturacsi_portal_reserved_nav_shortcode(): string {
		if ( ! culturacsi_portal_can_access() ) {
			return '';
		}

		$is_admin = current_user_can( 'manage_options' );
		$can_manage_sections = $is_admin || current_user_can( 'manage_csi_content_sections' );

		if ( $is_admin ) {
			// Site admin: unified contents hub + users + associations.
			$items = array(
				array( 'label' => 'Contenuti',    'url' => home_url( '/area-riservata/contenuti/' ) ),
				array( 'label' => 'Bacheca',      'url' => home_url( '/area-riservata/bacheca/' ) ),
				array( 'label' => 'Utenti',       'url' => home_url( '/area-riservata/utenti/' ) ),
				array( 'label' => 'Associazioni', 'url' => home_url( '/area-riservata/associazioni/' ) ),
				array( 'label' => 'Cronologia',   'url' => home_url( '/area-riservata/cronologia/' ) ),
			);
		} else {
			// Association manager: unified contents hub + colleagues + profile.
			$items = array(
				array( 'label' => 'Contenuti',    'url' => home_url( '/area-riservata/contenuti/' ) ),
				array( 'label' => 'Bacheca',      'url' => home_url( '/area-riservata/bacheca/' ) ),
				array( 'label' => 'Utenti',       'url' => home_url( '/area-riservata/utenti/' ) ),
				array( 'label' => 'Associazioni', 'url' => home_url( '/area-riservata/associazione/' ) ),
				array( 'label' => 'Cronologia',   'url' => home_url( '/area-riservata/cronologia/' ) ),
			);
		}
		if ( $can_manage_sections ) {
			$items[] = array( 'label' => 'Sezioni', 'url' => home_url( '/area-riservata/sezioni/' ) );
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

		// Note: avoid AJAX-style JSON cache in a shortcode context; render static HTML here.

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
			$item_path = trim( (string) wp_parse_url( (string) $item['url'], PHP_URL_PATH ), '/' );
			$is_dark_tab = in_array( $item_path, array( 'area-riservata/cronologia', 'area-riservata/sezioni' ), true );
			$link_class = 'assoc-reserved-nav-link'
				. ( $is_dark_tab ? ' is-dark-tab' : '' )
				. ( culturacsi_portal_nav_item_is_active( (string) $item['url'], $current_path ) ? ' is-active' : '' );
			echo '<li><a class="' . esc_attr( $link_class ) . '" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
		}
		echo '</ul></nav>';
		return ob_get_clean();
	}
}

if ( ! function_exists( 'culturacsi_portal_dashboard_shortcode' ) ) {
	function culturacsi_portal_dashboard_shortcode(): string {
		if ( ! culturacsi_portal_can_access() ) {
			return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
		}
		// Delegate to the same UI as the control panel shortcode for consistency
		if ( function_exists( 'culturacsi_portal_admin_control_panel_shortcode' ) ) {
			return culturacsi_portal_admin_control_panel_shortcode();
		}
		
		ob_start();
		echo '<div class="assoc-portal-dashboard">';
		echo '<h2>Benvenuto nell\'Area Riservata</h2>';
		echo '<p>Seleziona una sezione dal menu laterale.</p>';
		echo '</div>';
		return ob_get_clean();
	}
}

if ( ! function_exists( 'culturacsi_portal_modal_html' ) ) {
	function culturacsi_portal_modal_html(): void {
		if ( is_admin() || ! culturacsi_portal_can_access() ) return;

		// Global off by default; enable only if explicitly requested via filter.
		if ( ! apply_filters( 'culturacsi_portal_enable_modal', false ) ) return;

		// Scope: render only within the reserved area to avoid site-wide injection
		$path      = culturacsi_portal_current_path();
		$is_portal = ( 0 === strpos( rtrim( $path, '/' ) . '/', 'area-riservata/' ) );
		/**
		 * Filter to customize where the portal modal renders.
		 * Return true to force-enable, false to force-disable.
		 *
		 * @param bool   $is_portal Computed match for 'area-riservata/'.
		 * @param string $path      Current request path (no leading slash).
		 */
		$is_portal = (bool) apply_filters( 'culturacsi_portal_is_portal_request', $is_portal, $path );
		if ( ! $is_portal ) return;
		?>
		<style id="assoc-portal-modal-fallback-css">
			#assoc-portal-modal{display:none}
			#assoc-portal-modal.is-open{display:flex}
		</style>
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
		<?php
	}
	add_action( 'wp_footer', 'culturacsi_portal_modal_html' );
}

if ( ! function_exists( 'culturacsi_ajax_get_modal_data' ) ) {
	function culturacsi_ajax_get_modal_data(): void {
		if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'culturacsi_portal_ajax' ) ) {
			wp_send_json_error( 'Sessione scaduta, ricarica la pagina.' );
		}
		if ( ! culturacsi_portal_can_access() ) {
			wp_send_json_error( 'Accesso negato.' );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
		if ( ! $id || ! $type ) wp_send_json_error( 'Parametri mancanti.' );

		ob_start();
		$footer = '';
		if ( 'user' === $type ) {
			$user = get_user_by( 'id', $id );
			if ( $user ) {
				if ( ! culturacsi_portal_can_manage_user_target( $user, get_current_user_id() ) ) {
					wp_send_json_error( 'Permessi insufficienti.' );
				}
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
				echo '<div class="assoc-details-item"><span class="assoc-details-label">Registrato</span><span class="assoc-details-value">' . esc_html( mysql2date( 'd/m/Y H:i', $user->user_registered ) ) . '</span></div>';
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
				if ( ! culturacsi_portal_can_manage_post( $post, get_current_user_id() ) ) {
					wp_send_json_error( 'Permessi insufficienti.' );
				}
				echo '<div class="assoc-details-grid">';
				echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Titolo:</span> <span class="assoc-details-value" style="font-size:1.1rem;font-weight:700;">' . esc_html( $post->post_title ) . '</span></div>';
				// Associations: show Attività and a curated set of key fields (avoid dumping AB CSV fields)
				if ( 'association' === $type ) {
					$activity_labels = culturacsi_activity_labels_for_post( $id );
					if ( ! empty( $activity_labels ) ) {
						echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Attività:</span> <span class="assoc-details-value"><strong>' . esc_html( implode( ', ', $activity_labels ) ) . '</strong></span></div>';
					}
					$activity_paths = culturacsi_activity_paths_for_post( $id );
					if ( ! empty( $activity_paths ) ) {
						echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Macro > Settore > Settore 2:</span> <span class="assoc-details-value">' . esc_html( implode( ' | ', $activity_paths ) ) . '</span></div>';
					}
				}
				echo '<div class="assoc-details-item"><span class="assoc-details-label">Data:</span> <span class="assoc-details-value">' . esc_html( get_the_date( 'd/m/Y H:i', $post ) ) . '</span></div>';
				echo '<div class="assoc-details-item"><span class="assoc-details-label">Stato:</span> <span class="assoc-details-value">' . esc_html( get_post_status_object( $post->post_status )->label ) . '</span></div>';

				// Metadata rendering
				$all_meta = get_post_meta( $id );
				if ( 'association' === $type ) {
					$curated = array(
						'email'          => 'Email',
						'phone'          => 'Telefono',
						'address'        => 'Indirizzo',
						'city'           => 'Città / Comune',
						'comune'         => 'Città / Comune',
						'province'       => 'Provincia',
						'region'         => 'Regione',
						'regione'        => 'Regione',
						'cap'            => 'CAP',
						'website'        => 'Sito Web',
						'sito'           => 'Sito Web',
						'sito_web'       => 'Sito Web',
						'web'            => 'Sito Web',
						'url'            => 'Sito Web',
						'facebook'       => 'Facebook',
						'facebook_url'   => 'Facebook',
						'instagram'      => 'Instagram',
						'instagram_url'  => 'Instagram',
						'youtube'        => 'Youtube',
						'tiktok'         => 'TikTok',
						'x'              => 'X (Twitter)',
						'codice_fiscale' => 'Codice Fiscale',
						'piva'           => 'Partita IVA',
					);
					$seen_meta = array();
					foreach ( $curated as $mkey => $mlabel ) {
						$val = isset( $all_meta[ $mkey ][0] ) ? (string) $all_meta[ $mkey ][0] : '';
						if ( '' === trim( $val ) ) continue;
						$norm_val = strtolower( function_exists( 'remove_accents' ) ? remove_accents( trim( preg_replace( '/\s+/u', ' ', (string) $val ) ) ) : trim( preg_replace( '/\s+/u', ' ', (string) $val ) ) );
						$seen_key = strtolower( function_exists( 'remove_accents' ) ? remove_accents( (string) $mlabel ) : (string) $mlabel ) . '|' . $norm_val;
						if ( '' !== $seen_key && isset( $seen_meta[ $seen_key ] ) ) {
							continue;
						}
						$seen_meta[ $seen_key ] = true;
						echo '<div class="assoc-details-item"><span class="assoc-details-label">' . esc_html( $mlabel ) . ':</span> <span class="assoc-details-value">' . esc_html( $val ) . '</span></div>';
					}
				} else {
					// Generic renderer for non-association post types
					$skip_keys = array( '_edit_lock', '_edit_last', '_thumbnail_id', '_assoc_user_avatar_id', 'comune', 'regione', '_comune', '_regione' );
					$translations = array(
						'city' => 'Città / Comune',
						'province' => 'Provincia',
						'region' => 'Regione',
						'address' => 'Indirizzo',
						'phone' => 'Telefono',
						'email' => 'Email',
						'website' => 'Sito Web',
						'codice_fiscale' => 'Codice Fiscale',
						'piva' => 'Partita IVA',
						'start_date' => 'Data Inizio',
						'end_date' => 'Data Fine',
						'venue_name' => 'Nome Luogo',
						'cap' => 'CAP',
						'organizer_association_id' => 'Associazione Organizzatrice',
						'facebook' => 'Facebook',
						'instagram' => 'Instagram',
						'youtube' => 'Youtube',
						'tiktok' => 'TikTok',
						'x' => 'X (Twitter)',
					);
					foreach ( $all_meta as $key => $values ) {
						if ( 0 === strpos( $key, '_wp_' ) || in_array( $key, $skip_keys, true ) ) continue;
						$val = $values[0];
						if ( is_serialized( $val ) || '' === trim( (string)$val ) ) continue;
						$display_key = ltrim( $key, '_' );
						if ( in_array( $display_key, $skip_keys, true ) ) continue;
						$label = isset( $translations[ $display_key ] ) ? $translations[ $display_key ] : str_replace( array('_', '-'), ' ', $display_key );
						echo '<div class="assoc-details-item"><span class="assoc-details-label">' . esc_html( strtoupper( $label ) ) . ':</span> <span class="assoc-details-value">' . esc_html( (string)$val ) . '</span></div>';
					}
				}

				if ( ! empty( $post->post_excerpt ) ) {
					echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Sommario:</span> <span class="assoc-details-value">' . wp_kses_post( $post->post_excerpt ) . '</span></div>';
				}
				echo '</div>';

				ob_start();
				echo '<div class="assoc-action-group">';
				$edit_url = '';
				if ( 'association' === $type ) {
					$edit_url = current_user_can( 'manage_options' )
						? culturacsi_portal_admin_association_form_url( $id )
						: home_url( '/area-riservata/profilo/' );
				} elseif ( 'news' === $type ) {
					$edit_url = add_query_arg( array( 'news_id' => $id ), home_url( '/area-riservata/notizie/nuova/' ) );
				} elseif ( 'event' === $type ) {
					$edit_url = add_query_arg( array( 'event_id' => $id ), home_url( '/area-riservata/eventi/nuovo/' ) );
				} else {
					$edit_url = get_edit_post_link( $id, '' );
				}
				echo '<a class="assoc-action-chip chip-edit" href="' . esc_url( (string) $edit_url ) . '">Modifica</a>';
				if ( current_user_can( 'manage_options' ) ) {
					echo culturacsi_portal_action_button_form( array( 'context' => $type, 'action' => 'delete', 'target_id' => $id, 'label' => 'Elimina', 'class' => 'chip-delete', 'confirm' => true, 'confirm_text' => 'Confermi eliminazione?' ) );
				}
				echo '</div>';
				$footer = ob_get_clean();
			}
		}
		$content = ob_get_clean();
		if ( empty( $content ) ) wp_send_json_error( 'Dati non disponibili.' );
		$payload = array( 'html' => $content, 'footer' => $footer );
		wp_send_json_success( $payload );
	}
	add_action( 'wp_ajax_culturacsi_get_modal_data', 'culturacsi_ajax_get_modal_data' );
}
