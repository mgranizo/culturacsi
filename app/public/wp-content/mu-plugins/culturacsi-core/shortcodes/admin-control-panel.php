<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function culturacsi_portal_admin_control_panel_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Permessi insufficienti.</p>';
	}
	
	$is_admin = current_user_can( 'manage_options' );
	$role_label = culturacsi_portal_panel_role_label();

	ob_start();
	echo '<div class="assoc-portal-dashboard assoc-portal-section">';
	if ( ! $is_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		$assoc_name = $assoc_id > 0 ? get_the_title( $assoc_id ) : 'Associazione non trovata';
		echo '<h2 style="margin-bottom:0;">' . esc_html( $assoc_name ) . '</h2>';
		echo '<h3 style="margin-top:5px; margin-bottom:20px; color:#64748b; font-size:1.1rem; font-weight:normal;">' . esc_html( $role_label ) . '</h3>';
	} else {
		echo '<h2>' . esc_html( $role_label ) . '</h2>';
	}
	echo '<p>Apri una sezione del portale:</p>';
	echo '<ul class="assoc-portal-nav">';
	
	if ( $is_admin ) {
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/notizie/' ) ) . '">Notizie</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/eventi/' ) ) . '">Eventi</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/utenti/' ) ) . '">Utenti</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/associazioni/' ) ) . '">Associazioni</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/cronologia/' ) ) . '">Cronologia (Audit Log)</a></li>';
	} else {
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/notizie/' ) ) . '">Le tue Notizie</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/eventi/' ) ) . '">I tuoi Eventi</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/utenti/' ) ) . '">Collaboratori (Utenti)</a></li>';
		echo '<li><a href="' . esc_url( home_url( '/area-riservata/associazione/' ) ) . '">Profilo Associazione</a></li>';
	}
	
	if ( $is_admin ) {
		echo '<li><a href="' . esc_url( admin_url() ) . '">Apri Bacheca WordPress</a></li>';
	}
	
	echo '</ul></div>';
	return ob_get_clean();
}
