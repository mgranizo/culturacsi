<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content hub admin guide screen.
 */

/**
 * Register a quick guide page in the content hub admin menu.
 *
 * @return void
 */
function culturacsi_content_hub_register_guide_page() {
	add_submenu_page(
		'edit.php?post_type=' . CULTURACSI_CONTENT_HUB_POST_TYPE,
		__( 'Guida Hub Contenuti', 'culturacsi' ),
		__( 'Guida Rapida', 'culturacsi' ),
		'edit_csi_content_entries',
		'csi-content-hub-guide',
		'culturacsi_content_hub_render_guide_page'
	);
}
add_action( 'admin_menu', 'culturacsi_content_hub_register_guide_page' );

/**
 * Render admin guide content.
 *
 * @return void
 */
function culturacsi_content_hub_render_guide_page() {
	if ( ! current_user_can( 'edit_csi_content_entries' ) ) {
		return;
	}

	$shortcodes = array(
		'[culturacsi_content_hub section="library" downloads_only="yes" search="yes"]',
		'[culturacsi_section_feed identifier="section_library"]',
	);
	$section_identifiers = culturacsi_content_hub_section_identifiers();
	foreach ( $section_identifiers as $section_data ) {
		$shortcode_tag = isset( $section_data['shortcode'] ) ? sanitize_key( (string) $section_data['shortcode'] ) : '';
		if ( '' === $shortcode_tag ) {
			continue;
		}
		$shortcodes[] = '[' . $shortcode_tag . ']';
	}
	$shortcodes = array_values( array_unique( $shortcodes ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Guida Rapida: Hub Contenuti Riutilizzabili', 'culturacsi' ); ?></h1>
		<p><?php esc_html_e( 'Usa questo flusso per Biblioteca, Servizi, Convenzioni, Formazione, Progetti e future sezioni.', 'culturacsi' ); ?></p>

		<h2><?php esc_html_e( 'Workflow consigliato', 'culturacsi' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Vai su Contenuti Riutilizzabili > Aggiungi Nuovo.', 'culturacsi' ); ?></li>
			<li><?php esc_html_e( 'Inserisci titolo, testo breve (riassunto) e immagine in evidenza se necessaria.', 'culturacsi' ); ?></li>
			<li><?php esc_html_e( 'Assegna la Sezione corretta (Biblioteca, Servizi, Convenzioni, Formazione, Progetti, Infopoint Stranieri).', 'culturacsi' ); ?></li>
			<li><?php esc_html_e( 'Nel box Download e Link allega un file oppure inserisci un URL esterno.', 'culturacsi' ); ?></li>
			<li><?php esc_html_e( 'Pubblica il contenuto: comparira automaticamente nella pagina che usa lo shortcode della sezione.', 'culturacsi' ); ?></li>
		</ol>

		<h2><?php esc_html_e( 'Shortcode disponibili', 'culturacsi' ); ?></h2>
		<ul>
			<?php foreach ( $shortcodes as $shortcode ) : ?>
				<li><code><?php echo esc_html( $shortcode ); ?></code></li>
			<?php endforeach; ?>
		</ul>

		<h2><?php esc_html_e( 'Nota operativa', 'culturacsi' ); ?></h2>
		<p><?php esc_html_e( 'Il sistema e progettato per evitare personalizzazioni su plugin di terze parti: tutta la logica vive nei MU plugin del progetto.', 'culturacsi' ); ?></p>
	</div>
	<?php
}
