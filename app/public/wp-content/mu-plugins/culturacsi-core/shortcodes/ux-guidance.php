<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'culturacsi_portal_help_tip' ) ) {
	/**
	 * Renders a CSS-based help tip icon with a popup on hover/focus.
	 *
	 * @param string $text The text content to display inside the tooltip popup.
	 * @return string The generated HTML for the help tip, or an empty string if no text was provided.
	 */
	function culturacsi_portal_help_tip( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
		return '<span class="csi-help-tip" tabindex="0" role="note" aria-label="Aiuto"><span class="csi-help-tip-trigger" aria-hidden="true">?</span><span class="csi-help-tip-popup">' . esc_html( $text ) . '</span></span>';
	}
}

if ( ! function_exists( 'culturacsi_portal_label_with_tip' ) ) {
	/**
	 * Generates an HTML <label> tag containing the label text and an optional help tip.
	 *
	 * @param string $for   The "for" attribute value, referencing an input element ID.
	 * @param string $label The text of the label.
	 * @param string $tip   Optional. The tooltip text to display next to the label.
	 * @return string The generated HTML for the label element.
	 */
	function culturacsi_portal_label_with_tip( string $for, string $label, string $tip = '' ): string {
		$html  = '<label for="' . esc_attr( $for ) . '">';
		$html .= esc_html( $label );
		if ( '' !== trim( $tip ) ) {
			$html .= ' ' . culturacsi_portal_help_tip( $tip ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$html .= '</label>';
		return $html;
	}
}

if ( ! function_exists( 'culturacsi_portal_ui_guidance_assets_once' ) ) {
	/**
	 * Ensure required UI guidance assets are loaded.
	 * CSS and JS for these components are now handled globally in the reserved area bundle.
	 * 
	 * @return string Empty string as asset loading is delegated.
	 */
	function culturacsi_portal_ui_guidance_assets_once(): string {
		// Shared guidance/checklist CSS and JS now live in the reserved-area
		// asset bundle loaded by `admin-ui.php`.
		return '';
	}
}

if ( ! function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
	/**
	 * Renders a process tutorial block optionally including an introductory text, a collapsible step-by-step
	 * guide, and an interactive checklist tracked via JavaScript.
	 *
	 * @param array $args {
	 *     Configuration arguments for the tutorial.
	 *
	 *     @type string $title      Optional title of the tutorial block.
	 *     @type string $intro      Optional introductory paragraph.
	 *     @type array  $steps      Array of steps. Can be an array of strings or arrays with 'text' and 'tip'.
	 *     @type array  $checklist  Array of checklist items. Each item is an array with 'label', 'selectors', and 'mode'.
	 *     @type bool   $open       Whether the <details> accordion should be open by default. Default false.
	 *     @type bool   $show_title Whether to render the title element. Default false.
	 *     @type string $summary    The text shown on the <summary> element of the accordion. Default 'Tutorial'.
	 * }
	 * @return string The generated HTML string for the process guide.
	 */
	function culturacsi_portal_render_process_tutorial( array $args ): string {
		$title      = isset( $args['title'] ) ? sanitize_text_field( (string) $args['title'] ) : 'Guida rapida';
		$intro      = isset( $args['intro'] ) ? sanitize_text_field( (string) $args['intro'] ) : '';
		$steps      = isset( $args['steps'] ) && is_array( $args['steps'] ) ? $args['steps'] : array();
		$checklist  = isset( $args['checklist'] ) && is_array( $args['checklist'] ) ? $args['checklist'] : array();
		$open       = isset( $args['open'] ) ? (bool) $args['open'] : false;
		$show_title = isset( $args['show_title'] ) ? (bool) $args['show_title'] : false;
		$summary    = isset( $args['summary'] ) ? sanitize_text_field( (string) $args['summary'] ) : 'Tutorial';

		$html = culturacsi_portal_ui_guidance_assets_once();
		$html .= '<section class="csi-process-guide">';
		
		if ( $show_title && '' !== trim( $title ) ) {
			$html .= '<h3>' . esc_html( $title ) . '</h3>';
		}
		
		if ( '' !== $intro ) {
			$html .= '<p>' . esc_html( $intro ) . '</p>';
		}
		
		// Render step-by-step collapsible accordion
		$html .= '<details class="csi-process-tutorial"' . ( $open ? ' open' : '' ) . '>';
		$html .= '<summary>' . esc_html( $summary ) . '</summary>';
		if ( ! empty( $steps ) ) {
			$html .= '<ol>';
			foreach ( $steps as $step ) {
				$step_text = '';
				$tip_text  = '';
				
				// Handle both simple string steps or array-based steps with tooltips
				if ( is_array( $step ) ) {
					$step_text = isset( $step['text'] ) ? sanitize_text_field( (string) $step['text'] ) : '';
					$tip_text  = isset( $step['tip'] ) ? sanitize_text_field( (string) $step['tip'] ) : '';
				} else {
					$step_text = sanitize_text_field( (string) $step );
				}
				
				if ( '' === $step_text ) {
					continue;
				}
				
				$html .= '<li>' . esc_html( $step_text );
				if ( '' !== $tip_text ) {
					$html .= ' ' . culturacsi_portal_help_tip( $tip_text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				$html .= '</li>';
			}
			$html .= '</ol>';
		}
		$html .= '</details>';

		// Render interactive checklist
		if ( ! empty( $checklist ) ) {
			$html .= '<div class="csi-checklist" data-csi-checklist="1">';
			$html .= '<div class="csi-checklist-head"><span class="csi-checklist-title">Checklist</span><span class="csi-checklist-progress" data-csi-progress></span></div>';
			$html .= '<ul class="csi-checklist-list">';
			
			$step_num = 0;
			foreach ( $checklist as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				
				$label     = isset( $item['label'] ) ? sanitize_text_field( (string) $item['label'] ) : '';
				$selectors = isset( $item['selectors'] ) && is_array( $item['selectors'] ) ? $item['selectors'] : array();
				$mode      = isset( $item['mode'] ) && 'any' === $item['mode'] ? 'any' : 'all'; // 'all' requires all selectors to match, 'any' requires at least one
				
				if ( '' === $label || empty( $selectors ) ) {
					continue;
				}
				
				$selector_row = array();
				foreach ( $selectors as $selector ) {
					$selector = trim( (string) $selector );
					if ( '' !== $selector ) {
						// Escape quotes inside selectors securely for data attribute usage
						$selector = str_replace( array( '\\"', "\\'" ), array( '"', "'" ), $selector );
						$selector_row[] = $selector;
					}
				}
				if ( empty( $selector_row ) ) {
					continue;
				}
				
				++$step_num;
				// Output list item tracked by custom JS data attributes
				$html .= '<li data-csi-selectors="' . esc_attr( implode( '||', $selector_row ) ) . '" data-csi-mode="' . esc_attr( $mode ) . '" data-csi-step-num="' . esc_attr( (string) $step_num ) . '">';
				$html .= '<span class="csi-check-state">' . esc_html( (string) $step_num ) . '</span>';
				$html .= '<span>' . esc_html( $label ) . '</span>';
				$html .= '</li>';
			}
			$html .= '</ul></div>';
		}

		$html .= '</section>';
		return $html;
	}
}

if ( ! function_exists( 'culturacsi_portal_content_sections_map' ) ) {
	/**
	 * Retrieves an array of all terms within the custom 'csi_content_section' taxonomy.
	 * Used primarily to populate options to create content mapped to specific sections.
	 *
	 * @return array An associative array where keys are term slugs and values are term names.
	 */
	function culturacsi_portal_content_sections_map(): array {
		if ( ! taxonomy_exists( 'csi_content_section' ) ) {
			return array();
		}
		
		$terms = get_terms(
			array(
				'taxonomy'   => 'csi_content_section',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		
		if ( is_wp_error( $terms ) || ! is_array( $terms ) || empty( $terms ) ) {
			return array();
		}
		
		$out = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$slug = sanitize_title( (string) $term->slug );
			if ( '' === $slug ) {
				continue;
			}
			$out[ $slug ] = sanitize_text_field( (string) $term->name );
		}
		return $out;
	}
}

if ( ! function_exists( 'culturacsi_portal_render_creation_preselect_tutorial' ) ) {
	/**
	 * Renders a specific tutorial targeted for users when they first land in the content creation hub
	 * without having pre-selected an entity type.
	 *
	 * @return string The generated HTML string for the preselection tutorial.
	 */
	function culturacsi_portal_render_creation_preselect_tutorial(): string {
		if ( ! function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
			return '';
		}
		
		return culturacsi_portal_render_process_tutorial(
			array(
				'title'     => '',
				'intro'     => 'Per iniziare, scegli prima il tipo di contenuto da creare.',
				'summary'   => 'Come funziona',
				'open'      => true,
				'checklist' => array(),
				'steps'     => array(
					array( 'text' => 'Apri il menu "Crea nuovo contenuto" e seleziona il tipo.' ),
					array( 'text' => 'Compila i campi guidati e verifica la checklist.' ),
					array( 'text' => 'Salva: potrai modificare il contenuto in qualsiasi momento.' ),
				),
			)
		);
	}
}

if ( ! function_exists( 'culturacsi_portal_creation_hub_switcher' ) ) {
	/**
	 * Renders the dropdown navigation switcher allowing users to choose the type of content they want to create.
	 *
	 * @param string $current The slug defining the currently active creation flow ('event', 'news', 'section_xyz').
	 * @return string The generated HTML dropdown wrapped in its container.
	 */
	function culturacsi_portal_creation_hub_switcher( string $current = '' ): string {
		$current = sanitize_key( $current );
		$is_site_admin = current_user_can( 'manage_options' );
		$sections = culturacsi_portal_content_sections_map();
		$reset_url = function_exists( 'culturacsi_portal_reserved_current_page_url' ) ? culturacsi_portal_reserved_current_page_url() : home_url( '/' );
		
		// Check if the current context matches a know default content type
		$has_current_match = in_array( $current, array( 'event', 'news' ), true );
		
		// Map dynamic section matches if admin
		if ( $is_site_admin ) {
			foreach ( $sections as $slug => $label ) {
				if ( 'section_' . sanitize_key( $slug ) === $current ) {
					$has_current_match = true;
					break;
				}
			}
		}

		$html  = culturacsi_portal_ui_guidance_assets_once();
		$html .= '<div class="csi-creation-hub">';
		$html .= '<label for="csi-creation-hub-select">Crea nuovo contenuto ' . culturacsi_portal_help_tip( 'Scegli il tipo e verrai portato direttamente al modulo corretto.' ) . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		
		// Select box using JavaScript redirect (typically bound via assets/admin-ui.js)
		$html .= '<select id="csi-creation-hub-select" class="csi-creation-hub-select" data-reset-url="' . esc_url( $reset_url ) . '">';
		$html .= '<option value=""' . ( $has_current_match ? '' : ' selected="selected"' ) . '>Seleziona tipo...</option>';
		
		// Base content options
		$html .= '<option value="' . esc_url( add_query_arg( 'flow', 'event', home_url( '/area-riservata/eventi/nuovo/' ) ) ) . '"' . ( 'event' === $current ? ' selected="selected"' : '' ) . '>Nuovo Evento</option>';
		$html .= '<option value="' . esc_url( add_query_arg( 'flow', 'news', home_url( '/area-riservata/notizie/nuova/' ) ) ) . '"' . ( 'news' === $current ? ' selected="selected"' : '' ) . '>Nuova Notizia</option>';
		
		// Options representing sub-sections allowed for administrators
		if ( $is_site_admin ) {
			foreach ( $sections as $slug => $label ) {
				$option_key = 'section_' . sanitize_key( $slug );
				$url = add_query_arg(
					array(
						'section' => $slug,
						'flow'    => 'content',
					),
					home_url( '/area-riservata/contenuti/nuovo/' )
				);
				$html .= '<option value="' . esc_url( $url ) . '"' . ( $option_key === $current ? ' selected="selected"' : '' ) . '>Nuovo in ' . esc_html( $label ) . '</option>';
			}
		}
		
		$html .= '</select>';
		$html .= '</div>';
		return $html;
	}
}
