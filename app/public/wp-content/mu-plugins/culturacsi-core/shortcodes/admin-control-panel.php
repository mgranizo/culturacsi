<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function culturacsi_portal_admin_control_panel_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Permessi insufficienti.</p>';
	}

	$is_admin = current_user_can( 'manage_options' );
	$can_manage_sections = $is_admin || current_user_can( 'manage_csi_content_sections' );
	$role_label = culturacsi_portal_panel_role_label();
	$user_id = get_current_user_id();

	ob_start();
	echo '<div class="assoc-portal-dashboard assoc-portal-section">';
	echo '<h2 style="margin-bottom:8px;">Benvenuto nell\'Area Admin</h2>';
	echo '<p style="margin-top:0; margin-bottom:14px;">Questa area riservata serve per gestire il sito in modo semplice: contenuti, utenti, associazioni, cronologia e sezioni.</p>';

	if ( ! $is_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
		$assoc_name = $assoc_id > 0 ? get_the_title( $assoc_id ) : 'Associazione non trovata';
		echo '<p style="margin:0 0 14px; color:#475569;"><strong>Profilo attivo:</strong> ' . esc_html( $assoc_name ) . ' - ' . esc_html( $role_label ) . '</p>';
	} else {
		echo '<p style="margin:0 0 14px; color:#475569;"><strong>Profilo attivo:</strong> ' . esc_html( $role_label ) . '</p>';
	}

	if ( function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
		echo culturacsi_portal_render_process_tutorial(
			array(
				'title'   => '',
				'intro'   => 'Inizia da Contenuti per pubblicare o modificare elementi del sito.',
				'summary' => 'Come funziona questa area',
				'open'    => true,
				'steps'   => array(
					array( 'text' => 'Apri la sezione da gestire tramite i pulsanti qui sotto.' ),
					array( 'text' => 'Per nuovi inserimenti, usa il pulsante Nuovo contenuto dentro la sezione Contenuti.' ),
					array( 'text' => 'Per operazioni tecniche avanzate, usa il link WP-Admin (solo Site Admin).' ),
				),
			)
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo '<div class="assoc-admin-welcome-actions" style="display:flex; flex-wrap:wrap; gap:10px; margin:16px 0 12px;">';
	if ( $is_admin ) {
		echo '<a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/contenuti/' ) ) . '">Apri Contenuti</a>';
		echo '<a class="button" href="' . esc_url( home_url( '/area-riservata/utenti/' ) ) . '">Apri Utenti</a>';
		echo '<a class="button" href="' . esc_url( home_url( '/area-riservata/associazioni/' ) ) . '">Apri Associazioni</a>';
		echo '<a class="button" href="' . esc_url( home_url( '/area-riservata/cronologia/' ) ) . '">Apri Cronologia</a>';
		if ( $can_manage_sections ) {
			echo '<a class="button" href="' . esc_url( home_url( '/area-riservata/sezioni/' ) ) . '">Apri Sezioni</a>';
		}
	} else {
		echo '<a class="button button-primary" href="' . esc_url( home_url( '/area-riservata/contenuti/' ) ) . '">Apri Contenuti</a>';
		echo '<a class="button" href="' . esc_url( home_url( '/area-riservata/utenti/' ) ) . '">Apri Utenti</a>';
		echo '<a class="button" href="' . esc_url( home_url( '/area-riservata/associazione/' ) ) . '">Apri Associazioni</a>';
		echo '<a class="button" href="' . esc_url( home_url( '/area-riservata/cronologia/' ) ) . '">Apri Cronologia</a>';
		if ( $can_manage_sections ) {
			echo '<a class="button" href="' . esc_url( home_url( '/area-riservata/sezioni/' ) ) . '">Apri Sezioni</a>';
		}
	}
	echo '</div>';

	if ( $is_admin ) {
		echo '<div style="margin-top:14px; padding:12px; border:1px solid #d7e2f1; border-radius:10px; background:#f8fbff;">';
		echo '<strong>Accesso avanzato</strong>';
		echo '<p style="margin:6px 0 10px;">Se ti servono strumenti tecnici di WordPress, puoi aprire la bacheca classica.</p>';
		echo '<a class="button" href="' . esc_url( admin_url() ) . '">Apri WP-Admin</a>';
		echo '</div>';
	}
	echo '</div>';
	return ob_get_clean();
}
