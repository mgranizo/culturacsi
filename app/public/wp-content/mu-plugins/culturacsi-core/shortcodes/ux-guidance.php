<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'culturacsi_portal_help_tip' ) ) {
	function culturacsi_portal_help_tip( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return '';
		}
		return '<span class="csi-help-tip" tabindex="0" role="note" aria-label="Aiuto"><span class="csi-help-tip-trigger" aria-hidden="true">?</span><span class="csi-help-tip-popup">' . esc_html( $text ) . '</span></span>';
	}
}

if ( ! function_exists( 'culturacsi_portal_label_with_tip' ) ) {
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
	function culturacsi_portal_ui_guidance_assets_once(): string {
		static $rendered = false;
		if ( $rendered ) {
			return '';
		}
		$rendered = true;

		ob_start();
		?>
		<style>
		.csi-process-guide {
			background: #f8fbff;
			border: 1px solid #d6e6f8;
			border-radius: 12px;
			margin: 0 0 16px;
			padding: 12px 14px;
		}
		.csi-process-guide h3 {
			margin: 0 0 8px;
			font-size: 1.05rem;
		}
		.csi-process-guide p {
			margin: 0 0 10px;
		}
		.csi-process-tutorial summary {
			cursor: pointer;
			font-weight: 700;
			margin-bottom: 8px;
		}
		.csi-process-tutorial ol {
			margin: 0;
			padding-left: 20px;
		}
		.csi-process-tutorial li {
			margin: 0 0 8px;
			line-height: 1.45;
		}
		.csi-checklist {
			background: #fff;
			border: 1px solid #d9e2ee;
			border-radius: 10px;
			margin-top: 12px;
			padding: 10px 12px;
		}
		.csi-checklist-head {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			justify-content: space-between;
			margin-bottom: 8px;
		}
		.csi-checklist-title {
			font-weight: 700;
		}
		.csi-checklist-progress {
			color: #345;
			font-size: 0.95rem;
		}
		.csi-checklist-list {
			list-style: none;
			margin: 0;
			padding: 0;
		}
		.csi-checklist-list li {
			align-items: center;
			display: flex;
			gap: 10px;
			margin: 0 0 8px;
		}
		.csi-checklist-list li:last-child {
			margin-bottom: 0;
		}
		.csi-check-state {
			align-items: center;
			background: #dde5ef;
			border-radius: 999px;
			display: inline-flex;
			font-size: 0.9rem;
			font-weight: 700;
			height: 26px;
			justify-content: center;
			min-width: 26px;
			padding: 0 8px;
		}
		.csi-checklist-list li.is-done .csi-check-state {
			background: #15803d;
			color: #fff;
		}
		.csi-checklist-list li.is-current .csi-check-state {
			background: #0b3d91;
			color: #fff;
		}
		.csi-checklist.is-sticky-active {
			box-shadow: 0 10px 24px rgba(15, 23, 42, 0.15);
			max-height: calc(100vh - 24px);
			overflow: auto;
			z-index: 120;
		}
		.csi-checklist-placeholder {
			display: none;
		}
		.csi-help-tip {
			display: inline-block;
			position: relative;
			vertical-align: middle;
		}
		.csi-help-tip-trigger {
			align-items: center;
			background: #dbeafe;
			border: 1px solid #93c5fd;
			border-radius: 50%;
			color: #1e3a8a;
			cursor: help;
			display: inline-flex;
			font-size: 0.72rem;
			font-weight: 700;
			height: 18px;
			justify-content: center;
			width: 18px;
		}
		.csi-help-tip-popup {
			background: #0f172a;
			border-radius: 8px;
			bottom: 140%;
			color: #fff;
			display: none;
			font-size: 0.82rem;
			left: 50%;
			line-height: 1.35;
			max-width: 260px;
			padding: 8px 10px;
			position: absolute;
			transform: translateX(-50%);
			width: max-content;
			z-index: 50;
		}
		.csi-help-tip:focus .csi-help-tip-popup,
		.csi-help-tip:hover .csi-help-tip-popup {
			display: block;
		}
		.csi-creation-hub {
			background: #f7fafc;
			border: 1px solid #d9e2ee;
			border-radius: 10px;
			margin: 0 0 16px;
			padding: 10px 12px;
		}
		.csi-creation-hub label {
			display: block;
			font-weight: 700;
			margin: 0 0 6px;
		}
		.csi-creation-hub select {
			max-width: 520px;
			min-height: 42px;
			width: 100%;
		}
		</style>
		<script>
		(function(){
			if (window.__csiPortalUxBound) { return; }
			window.__csiPortalUxBound = true;

			var isValueFilled = function(node) {
				if (!node) { return false; }
				var tag = (node.tagName || '').toLowerCase();
				var type = (node.type || '').toLowerCase();
				if (type === 'checkbox' || type === 'radio') {
					return !!node.checked;
				}
				if (tag === 'select') {
					var val = (node.value || '').trim();
					return val !== '' && val !== '0' && val !== 'all';
				}
				if (tag === 'input' || tag === 'textarea') {
					return ((node.value || '').trim() !== '');
				}
				return ((node.textContent || '').trim() !== '');
			};

			var selectorDone = function(scope, selector) {
				if (!scope || !selector) { return false; }
				var nodes = scope.querySelectorAll(selector);
				if (!nodes || !nodes.length) { return false; }
				for (var i = 0; i < nodes.length; i++) {
					if (isValueFilled(nodes[i])) {
						return true;
					}
				}
				return false;
			};

			var findChecklistForm = function(checklist) {
				if (!checklist) { return null; }
				var insideForm = checklist.closest('form');
				if (insideForm) { return insideForm; }
				var anchor = checklist.closest('.csi-process-guide') || checklist;
				var node = anchor.nextElementSibling;
				while (node) {
					if ((node.tagName || '').toLowerCase() === 'form') {
						return node;
					}
					if (node.querySelector) {
						var nested = node.querySelector('form');
						if (nested) { return nested; }
					}
					node = node.nextElementSibling;
				}
				return null;
			};

			var toggleFormSubmitState = function(form, enabled) {
				if (!form) { return; }
				var submits = form.querySelectorAll('button[type="submit"], input[type="submit"]');
				for (var i = 0; i < submits.length; i++) {
					var submit = submits[i];
					submit.disabled = !enabled;
					submit.setAttribute('aria-disabled', enabled ? 'false' : 'true');
					submit.classList.toggle('is-disabled-by-checklist', !enabled);
				}
				form.setAttribute('data-csi-checklist-required', '1');
				form.setAttribute('data-csi-checklist-complete', enabled ? '1' : '0');
			};

			var stickyInstances = [];
			var stickyTicking = false;

			var stickyTopOffset = function() {
				var offset = 12;
				var adminBar = document.getElementById('wpadminbar');
				if (adminBar && adminBar.offsetHeight > 0) {
					offset += adminBar.offsetHeight + 6;
				}
				var header = document.querySelector('body.assoc-reserved-page #masthead, body.assoc-reserved-page header.site-header');
				if (header) {
					var style = window.getComputedStyle(header);
					if (style && (style.position === 'fixed' || style.position === 'sticky')) {
						offset += header.offsetHeight;
					}
				}
				return offset;
			};

			var updateStickyInstance = function(instance) {
				if (!instance || !instance.node || !instance.placeholder) { return; }
				var checklist = instance.node;
				var placeholder = instance.placeholder;
				if (window.innerWidth < 960) {
					checklist.classList.remove('is-sticky-active');
					checklist.style.position = '';
					checklist.style.top = '';
					checklist.style.left = '';
					checklist.style.right = '';
					checklist.style.width = '';
					placeholder.style.display = 'none';
					placeholder.style.height = '';
					return;
				}
				var topOffset = stickyTopOffset();
				var startTop = instance.startTop || (checklist.getBoundingClientRect().top + window.scrollY);
				var shouldStick = window.scrollY > (startTop - topOffset);

				if (!shouldStick) {
					checklist.classList.remove('is-sticky-active');
					checklist.style.position = '';
					checklist.style.top = '';
					checklist.style.left = '';
					checklist.style.right = '';
					checklist.style.width = '';
					placeholder.style.display = 'none';
					placeholder.style.height = '';
					instance.startTop = checklist.getBoundingClientRect().top + window.scrollY;
					return;
				}

				var content = checklist.closest('.entry-content') || document.querySelector('body.assoc-reserved-page .entry-content');
				var contentRect = content ? content.getBoundingClientRect() : document.body.getBoundingClientRect();
				var stickyWidth = Math.min(360, Math.max(260, Math.floor(contentRect.width * 0.33)));
				var left = Math.max(12, Math.floor(contentRect.left + contentRect.width - stickyWidth - 8));
				placeholder.style.display = 'block';
				placeholder.style.height = checklist.offsetHeight + 'px';
				checklist.classList.add('is-sticky-active');
				checklist.style.position = 'fixed';
				checklist.style.top = topOffset + 'px';
				checklist.style.left = left + 'px';
				checklist.style.right = '';
				checklist.style.width = stickyWidth + 'px';
			};

			var refreshSticky = function() {
				stickyTicking = false;
				for (var i = 0; i < stickyInstances.length; i++) {
					updateStickyInstance(stickyInstances[i]);
				}
			};

			var queueStickyRefresh = function() {
				if (stickyTicking) { return; }
				stickyTicking = true;
				window.requestAnimationFrame(refreshSticky);
			};

			var initChecklistSticky = function(checklist) {
				if (!checklist || checklist.__csiStickyBound) { return; }
				checklist.__csiStickyBound = true;
				var placeholder = document.createElement('div');
				placeholder.className = 'csi-checklist-placeholder';
				checklist.parentNode.insertBefore(placeholder, checklist);
				stickyInstances.push({
					node: checklist,
					placeholder: placeholder,
					startTop: checklist.getBoundingClientRect().top + window.scrollY
				});
			};

			var refreshChecklist = function(checklist) {
				if (!checklist) { return; }
				var form = checklist.closest('form') || document;
				var linkedForm = findChecklistForm(checklist);
				var items = checklist.querySelectorAll('li[data-csi-selectors]');
				var total = 0;
				var done = 0;
				for (var i = 0; i < items.length; i++) {
					var item = items[i];
					var raw = (item.getAttribute('data-csi-selectors') || '').trim();
					if (!raw) { continue; }
					item.classList.remove('is-current');
					var selectors = raw.split('||');
					var mode = (item.getAttribute('data-csi-mode') || 'all').toLowerCase();
					var state = (mode === 'any') ? false : true;
					for (var j = 0; j < selectors.length; j++) {
						var selector = (selectors[j] || '').trim();
						if (!selector) { continue; }
						var ok = selectorDone(form, selector);
						if (mode === 'any') {
							state = state || ok;
						} else {
							state = state && ok;
						}
					}
					total++;
					if (state) { done++; }
					item.classList.toggle('is-done', state);
					var mark = item.querySelector('.csi-check-state');
					if (mark) {
						var stepNum = (item.getAttribute('data-csi-step-num') || (total + '')).trim();
						mark.textContent = state ? 'OK' : stepNum;
					}
				}
				if (done < total) {
					for (var k = 0; k < items.length; k++) {
						if (!items[k].classList.contains('is-done')) {
							items[k].classList.add('is-current');
							break;
						}
					}
				}
				var progress = checklist.querySelector('[data-csi-progress]');
				if (progress) {
					progress.textContent = 'Completati ' + done + ' di ' + total + ' passaggi';
				}
				if (linkedForm && total > 0) {
					toggleFormSubmitState(linkedForm, done >= total);
				}
			};

			var refreshAll = function() {
				var lists = document.querySelectorAll('[data-csi-checklist="1"]');
				for (var i = 0; i < lists.length; i++) {
					initChecklistSticky(lists[i]);
					refreshChecklist(lists[i]);
				}
				queueStickyRefresh();
			};

			document.addEventListener('change', function(evt){
				var target = evt.target;
				if (target && target.classList && target.classList.contains('csi-creation-hub-select')) {
					var selectedUrl = (target.value || '').trim();
					if (selectedUrl) {
						window.location.href = selectedUrl;
						return;
					}
					var resetUrl = (target.getAttribute('data-reset-url') || '').trim();
					if (resetUrl) {
						window.location.href = resetUrl;
						return;
					}
					if (window.location && window.location.pathname) {
						window.location.href = window.location.pathname;
						return;
					}
				}
				refreshAll();
			});
			var bindTinyMce = function() {
				if (!window.tinymce || !window.tinymce.editors) { return; }
				for (var i = 0; i < window.tinymce.editors.length; i++) {
					var editor = window.tinymce.editors[i];
					if (!editor || editor.__csiChecklistBound) { continue; }
					editor.__csiChecklistBound = true;
					var handler = function() {
						try { editor.save(); } catch (err) {}
						refreshAll();
					};
					editor.on('change keyup input SetContent Undo Redo', handler);
				}
			};
			setInterval(bindTinyMce, 1200);
			document.addEventListener('focusin', bindTinyMce);
			document.addEventListener('input', refreshAll);
			document.addEventListener('submit', function(evt){
				var form = evt.target;
				if (!form || !form.getAttribute) { return; }
				if (form.getAttribute('data-csi-checklist-required') !== '1') { return; }
				if (form.getAttribute('data-csi-checklist-complete') === '1') { return; }
				evt.preventDefault();
				refreshAll();
				var firstChecklist = document.querySelector('[data-csi-checklist="1"]');
				if (firstChecklist && firstChecklist.scrollIntoView) {
					firstChecklist.scrollIntoView({ behavior: 'smooth', block: 'center' });
				}
			}, true);
			window.addEventListener('scroll', queueStickyRefresh, { passive: true });
			window.addEventListener('resize', queueStickyRefresh);
			document.addEventListener('DOMContentLoaded', refreshAll);
		})();
		</script>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
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
		$html .= '<details class="csi-process-tutorial"' . ( $open ? ' open' : '' ) . '>';
		$html .= '<summary>' . esc_html( $summary ) . '</summary>';
		if ( ! empty( $steps ) ) {
			$html .= '<ol>';
			foreach ( $steps as $step ) {
				$step_text = '';
				$tip_text  = '';
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
				$mode      = isset( $item['mode'] ) && 'any' === $item['mode'] ? 'any' : 'all';
				if ( '' === $label || empty( $selectors ) ) {
					continue;
				}
				$selector_row = array();
				foreach ( $selectors as $selector ) {
					$selector = trim( (string) $selector );
					if ( '' !== $selector ) {
						$selector = str_replace( array( '\\"', "\\'" ), array( '"', "'" ), $selector );
						$selector_row[] = $selector;
					}
				}
				if ( empty( $selector_row ) ) {
					continue;
				}
				++$step_num;
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
	function culturacsi_portal_creation_hub_switcher( string $current = '' ): string {
		$current = sanitize_key( $current );
		$is_site_admin = current_user_can( 'manage_options' );
		$sections = culturacsi_portal_content_sections_map();
		$reset_url = function_exists( 'culturacsi_portal_reserved_current_page_url' ) ? culturacsi_portal_reserved_current_page_url() : home_url( '/' );
		$has_current_match = in_array( $current, array( 'event', 'news' ), true );
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
		$html .= '<select id="csi-creation-hub-select" class="csi-creation-hub-select" data-reset-url="' . esc_url( $reset_url ) . '">';
		$html .= '<option value=""' . ( $has_current_match ? '' : ' selected="selected"' ) . '>Seleziona tipo...</option>';
		$html .= '<option value="' . esc_url( add_query_arg( 'flow', 'event', home_url( '/area-riservata/eventi/nuovo/' ) ) ) . '"' . ( 'event' === $current ? ' selected="selected"' : '' ) . '>Nuovo Evento</option>';
		$html .= '<option value="' . esc_url( add_query_arg( 'flow', 'news', home_url( '/area-riservata/notizie/nuova/' ) ) ) . '"' . ( 'news' === $current ? ' selected="selected"' : '' ) . '>Nuova Notizia</option>';
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
