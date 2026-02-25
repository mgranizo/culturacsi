<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function culturacsi_portal_users_list_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}

	$message_html   = culturacsi_portal_process_user_row_action();
	$is_site_admin  = current_user_can( 'manage_options' );
	$current_user   = wp_get_current_user();
	$current_user_id = get_current_user_id();
	$filters         = culturacsi_portal_users_filters_from_request();
	$base_url        = culturacsi_portal_reserved_current_page_url();
	$sort_state      = culturacsi_portal_get_sort_state(
		'u_sort',
		'u_dir',
		'registered',
		'desc',
		array( 'index', 'name', 'email', 'role', 'status', 'registered', 'history' )
	);

	if ( $is_site_admin ) {
		$users = get_users(
			array(
				'orderby' => 'registered',
				'order'   => 'DESC',
				'number'  => 1000,
			)
		);
	} else {
		$assoc_id = culturacsi_portal_get_managed_association_id( $current_user_id );
		if ( $assoc_id > 0 ) {
			$users = get_users(
				array(
					'meta_query' => array(
						array(
							'key'     => 'association_post_id',
							'value'   => (string) $assoc_id,
							'compare' => '=',
						),
					),
					'role__not_in' => array( 'administrator' ),
					'number'       => 500,
				)
			);
		} else {
			$users = array( $current_user );
		}
	}

	$search_q = function_exists( 'mb_strtolower' ) ? mb_strtolower( $filters['q'] ) : strtolower( $filters['q'] );
	if ( ! empty( $users ) ) {
		$users = array_values(
			array_filter(
				$users,
				static function( $user ) use ( $filters, $search_q, $is_site_admin ): bool {
					if ( ! $user instanceof WP_User ) {
						return false;
					}
					// Non-admins should never see Site Admins in the list
					if ( ! $is_site_admin && user_can( $user, 'manage_options' ) ) {
						return false;
					}
					if ( '' !== $search_q ) {
						$haystack = trim( implode( ' ', array( (string) $user->display_name, (string) $user->user_email, (string) $user->user_login ) ) );
						$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
						if ( false === strpos( $haystack, $search_q ) ) {
							return false;
						}
					}
					if ( $is_site_admin && 'all' !== $filters['role'] && ! in_array( $filters['role'], (array) $user->roles, true ) ) {
						return false;
					}
					if ( ! culturacsi_portal_user_matches_status( $user, $filters['status'] ) ) {
						return false;
					}
					return true;
				}
			)
		);
	}
	if ( ! empty( $users ) ) {
		usort(
			$users,
			static function( WP_User $a, WP_User $b ) use ( $sort_state ): int {
				$cmp = 0;
				switch ( $sort_state['sort'] ) {
					case 'name':
						$cmp = strcasecmp( (string) $a->display_name, (string) $b->display_name );
						break;
					case 'email':
						$cmp = strcasecmp( (string) $a->user_email, (string) $b->user_email );
						break;
					case 'role':
						$cmp = strcasecmp( implode( ', ', (array) $a->roles ), implode( ', ', (array) $b->roles ) );
						break;
					case 'status':
						$cmp = strcasecmp( culturacsi_portal_user_approval_label( $a ), culturacsi_portal_user_approval_label( $b ) );
						break;
					case 'registered':
						$cmp = strtotime( (string) $a->user_registered ) <=> strtotime( (string) $b->user_registered );
						break;
					case 'history':
						static $h_cache_u = array();
						if(!isset($h_cache_u[$a->ID])) { $m=culturacsi_logging_get_last_modified('user',$a->ID); $c=culturacsi_logging_get_creator('user',$a->ID); $h_cache_u[$a->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($a->user_registered)); }
						if(!isset($h_cache_u[$b->ID])) { $m=culturacsi_logging_get_last_modified('user',$b->ID); $c=culturacsi_logging_get_creator('user',$b->ID); $h_cache_u[$b->ID]=$m?strtotime($m->created_at):($c?strtotime($c->created_at):strtotime($b->user_registered)); }
						$cmp = $h_cache_u[$a->ID] <=> $h_cache_u[$b->ID];
						break;
					case 'index':
					default:
						$cmp = (int) $a->ID <=> (int) $b->ID;
						break;
				}
				return ( 'asc' === $sort_state['dir'] ) ? $cmp : -$cmp;
			}
		);
	}

	ob_start();
	echo $message_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo '<div class="assoc-portal-users-list assoc-portal-section">';
	echo '<div class="assoc-page-toolbar"><h2 class="assoc-page-title">Utenti</h2><div style="display:flex;gap:10px;"><a class="button" style="background-color: #22c55e; color: white; border-color: #16a34a;" href="' . esc_url( add_query_arg( 'culturacsi_export', 'user', $_SERVER['REQUEST_URI'] ?? '' ) ) . '">Esporta CSV</a> <a class="button button-primary" href="' . esc_url( culturacsi_portal_admin_user_form_url() ) . '">Nuovo Utente</a></div></div>';
	echo '<style>.assoc-admin-table tr.is-pending-approval td { background-color: #fef2f2 !important; border-top: 2px solid #ef4444 !important; border-bottom: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:first-child { border-left: 2px solid #ef4444 !important; } .assoc-admin-table tr.is-pending-approval td:last-child { border-right: 2px solid #ef4444 !important; }</style>';
	echo '<table class="widefat striped assoc-admin-table assoc-table-users"><colgroup><col style="width:4ch"><col style="width:24%"><col style="width:22%"><col style="width:15%"><col style="width:6rem"><col style="width:120px"><col style="width:6rem"><col style="width:110px"></colgroup><thead><tr>';
	echo culturacsi_portal_sortable_th( '#', 'index', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-index' );
	echo culturacsi_portal_sortable_th( 'Nome', 'name', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-title' );
	echo culturacsi_portal_sortable_th( 'Email', 'email', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-email' );
	echo culturacsi_portal_sortable_th( 'Ruolo', 'role', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-role' );
	echo culturacsi_portal_sortable_th( 'Stato', 'status', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-status assoc-col-status-compact' );
	$th_html_u = culturacsi_portal_sortable_th( 'Cronologia', 'history', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-history' );
	echo str_replace( '<th class="assoc-col-history">', '<th class="assoc-col-history" style="width:180px;">', $th_html_u );
	echo culturacsi_portal_sortable_th( 'Registrato', 'registered', $sort_state['sort'], $sort_state['dir'], 'u_sort', 'u_dir', $base_url, 'assoc-col-date' );
	echo '<th class="assoc-col-actions">Azioni</th>';
	echo '</tr></thead><tbody>';
	if ( ! empty( $users ) ) {
		$row_num = 0;
		foreach ( $users as $user ) {
			if ( ! $user instanceof WP_User ) {
				continue;
			}
			++$row_num;
			$roles = array_map(
				static function( string $role ): string {
					if ( 'administrator' === $role ) {
						return 'Admin Sito';
					}
					if ( 'association_manager' === $role ) {
						return 'Admin Associazione';
					}
					return ucwords( str_replace( '_', ' ', $role ) );
				},
				(array) $user->roles
			);

			$row_class  = ( current_user_can( 'manage_options' ) && ! culturacsi_portal_is_user_approved( $user ) ) ? ' is-pending-approval' : '';
			echo '<tr class="' . esc_attr( trim( $row_class ) ) . '" data-id="' . esc_attr( (string) $user->ID ) . '" data-type="user">';
			echo '<td class="assoc-col-index">' . esc_html( (string) $row_num ) . '</td>';
			echo '<td class="assoc-col-title">';
			$avatar_id = (int) get_user_meta( (int) $user->ID, 'assoc_user_avatar_id', true );
			if ( $avatar_id > 0 ) {
				echo '<span class="assoc-user-list-thumb">' . wp_get_attachment_image( $avatar_id, array( 32, 32 ) ) . '</span>';
			}
			echo '<span class="assoc-user-list-name">' . esc_html( $user->display_name ) . '</span></td>';
			echo '<td class="assoc-col-email">' . esc_html( $user->user_email ) . '</td>';
			echo '<td class="assoc-col-role">' . esc_html( implode( ', ', $roles ) ) . '</td>';
			echo '<td class="assoc-col-status assoc-col-status-compact"><span class="assoc-status-pill ' . esc_attr( culturacsi_portal_user_approval_class( $user ) ) . '">' . esc_html( culturacsi_portal_user_approval_label( $user ) ) . '</span></td>';
			// History column for Users
			echo '<td class="assoc-col-history" style="font-size:10px;line-height:1.2;color:#64748b;vertical-align:middle;white-space:nowrap;">';
			$last_mod = culturacsi_logging_get_last_modified( 'user', (int) $user->ID );
			if ( $last_mod ) {
				echo '<strong>Mod:</strong> ' . esc_html( date_i18n( 'd/m/y H:i', strtotime( $last_mod->created_at ) ) ) . '<br>';
				echo esc_html( $last_mod->user_name );
			} else {
				$reg_date = (string) $user->user_registered;
				echo '<strong>Reg:</strong> ' . esc_html( date_i18n( 'd/m/y H:i', strtotime( $reg_date ) ) ) . '<br>';
				echo 'System';
			}
			echo '</td>';

			echo '<td class="assoc-col-date" style="vertical-align:middle; line-height:1.1; white-space:nowrap;">' . esc_html( date_i18n( 'd/m/Y', strtotime( (string) $user->user_registered ) ) ) . '<br><small style="color:#64748b;">' . esc_html( date_i18n( 'H:i', strtotime( (string) $user->user_registered ) ) ) . '</small></td>';
			echo '<td class="assoc-col-actions"><div class="assoc-action-group">';
			// All managers can edit users in their association (listing logic above ensures they only see their association)
			echo '<a class="assoc-action-chip chip-edit" href="' . esc_url( culturacsi_portal_admin_user_form_url( (int) $user->ID ) ) . '">Mod.</a>';
			
			if ( $is_site_admin && (int) $user->ID !== $current_user_id ) {
				echo culturacsi_portal_action_button_form(
					array(
						'context'      => 'user',
						'action'       => 'delete',
						'target_id'    => (int) $user->ID,
						'label'        => 'Elim.',
						'class'        => 'chip-delete',
						'confirm'      => true,
						'confirm_text' => 'Confermi l\'eliminazione di questo utente?',
					)
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				
				$is_approved = culturacsi_portal_is_user_approved( $user );
				$toggle_label = $is_approved ? 'Rev.' : 'Appr.';
				$toggle_action = $is_approved ? 'revoke' : 'approve';
				$toggle_class = $is_approved ? 'chip-reject chip-toggle' : 'chip-approve chip-toggle';
				echo culturacsi_portal_action_button_form( array( 'context' => 'user', 'action' => $toggle_action, 'target_id' => (int) $user->ID, 'label' => $toggle_label, 'class' => $toggle_class ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			echo '</div></td>';
			echo '</tr>';
		}
	} else {
		echo '<tr><td colspan="7">Nessun utente trovato.</td></tr>';
	}
	echo '</tbody></table></div>';

	return ob_get_clean();
}
