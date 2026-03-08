<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'culturacsi_ensure_default_library_image' ) ) {
	/**
	 * Ensures the default PDF icon is in the media library and returns its attachment ID.
	 */
	function culturacsi_ensure_default_library_image(): int {
		$upload_dir = wp_upload_dir();
		$default_dir = $upload_dir['basedir'] . '/culturacsi-defaults';
		
		// Ensure the default assets directory exists
		if ( ! is_dir( $default_dir ) ) {
			wp_mkdir_p( $default_dir );
		}
		
		$default_filename = 'vecteezy_pdf-file-download-icon-with-transparent-background_17178029.png';
		$default_path = $default_dir . '/' . $default_filename;
		if ( ! file_exists( $default_path ) ) {
			return 0;
		}

		$meta_key = '_csi_library_default_pdf_icon';
		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'   => $meta_key,
					'value' => '1',
				),
			),
		);
		$query = new WP_Query( $args );
		if ( ! empty( $query->posts ) ) {
			return (int) $query->posts[0]->ID;
		}

		// Not found, sideload it
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp_dir = get_temp_dir();
		$filename = basename( $default_path );
		$tmp_path = $tmp_dir . $filename;
		if ( ! copy( $default_path, $tmp_path ) ) {
			return 0;
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp_path,
		);

		$id = media_handle_sideload( $file_array, 0 );
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( (int) $id, $meta_key, '1' );
			return (int) $id;
		}

		return 0;
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_filters_from_request' ) ) {
	function culturacsi_portal_content_entries_filters_from_request(): array {
		$type = isset( $_GET['c_type'] ) ? sanitize_key( wp_unslash( $_GET['c_type'] ) ) : 'all';
		$section_slug = '';
		$allowed_base = array( 'all', 'event', 'news', 'content' );
		$section_map  = function_exists( 'culturacsi_portal_content_sections_map' ) ? culturacsi_portal_content_sections_map() : array();
		$skip_slugs   = array();
		if ( 0 === strpos( $type, 'section_' ) ) {
			$maybe_slug = sanitize_title( substr( $type, 8 ) );
			if ( '' !== $maybe_slug && isset( $section_map[ $maybe_slug ] ) ) {
				$section_slug = $maybe_slug;
			} else {
				$type = 'all';
			}
		} elseif ( ! in_array( $type, $allowed_base, true ) ) {
			$type = 'all';
		}
		return array(
			'q'          => isset( $_GET['c_q'] ) ? sanitize_text_field( wp_unslash( $_GET['c_q'] ) ) : '',
			'type'       => $type,
			'section_slug' => $section_slug,
			'date'       => isset( $_GET['c_date'] ) ? sanitize_text_field( wp_unslash( $_GET['c_date'] ) ) : '',
			'status'     => isset( $_GET['c_status'] ) ? sanitize_key( wp_unslash( $_GET['c_status'] ) ) : 'all',
			'author'     => isset( $_GET['c_author'] ) ? absint( wp_unslash( $_GET['c_author'] ) ) : 0,
			'section_id' => isset( $_GET['c_section_id'] ) ? absint( wp_unslash( $_GET['c_section_id'] ) ) : 0,
			'event_type' => isset( $_GET['c_event_type'] ) ? absint( wp_unslash( $_GET['c_event_type'] ) ) : 0,
		);
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_post_type_label' ) ) {
	function culturacsi_portal_content_entries_post_type_label( string $post_type ): string {
		if ( 'event' === $post_type ) {
			return 'Evento';
		}
		if ( 'news' === $post_type ) {
			return 'Notizia';
		}
		return 'Documento/Servizio';
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_context_for_post_type' ) ) {
	function culturacsi_portal_content_entries_context_for_post_type( string $post_type ): string {
		if ( 'event' === $post_type ) {
			return 'event';
		}
		if ( 'news' === $post_type ) {
			return 'news';
		}
		return 'content';
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_can_manage_post' ) ) {
	function culturacsi_portal_content_entries_can_manage_post( WP_Post $post, int $user_id, bool $is_admin, int $assoc_id ): bool {
		unset( $is_admin, $assoc_id );
		if ( function_exists( 'culturacsi_portal_can_manage_post' ) ) {
			return culturacsi_portal_can_manage_post( $post, $user_id );
		}
		return false;
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_secondary_label' ) ) {
	function culturacsi_portal_content_entries_secondary_label( WP_Post $post ): string {
		if ( 'event' === $post->post_type ) {
			$terms = wp_get_post_terms( (int) $post->ID, 'event_type', array( 'fields' => 'names' ) );
			return ( is_wp_error( $terms ) || empty( $terms ) ) ? '-' : implode( ', ', array_map( 'sanitize_text_field', $terms ) );
		}
		if ( 'csi_content_entry' === $post->post_type ) {
			$terms = wp_get_post_terms( (int) $post->ID, 'csi_content_section', array( 'fields' => 'names' ) );
			return ( is_wp_error( $terms ) || empty( $terms ) ) ? '-' : implode( ', ', array_map( 'sanitize_text_field', $terms ) );
		}
		return '-';
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_edit_url' ) ) {
	function culturacsi_portal_content_entries_edit_url( WP_Post $post ): string {
		if ( 'event' === $post->post_type ) {
			return add_query_arg( 'event_id', (int) $post->ID, home_url( '/area-riservata/eventi/nuovo/' ) );
		}
		if ( 'news' === $post->post_type ) {
			return add_query_arg( 'news_id', (int) $post->ID, home_url( '/area-riservata/notizie/nuova/' ) );
		}
		return add_query_arg( 'content_id', (int) $post->ID, home_url( '/area-riservata/contenuti/nuovo/' ) );
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_process_row_actions' ) ) {
	function culturacsi_portal_content_entries_process_row_actions(): string {
		$message = '';
		$rules   = array(
			array( 'context' => 'event',   'post_type' => 'event' ),
			array( 'context' => 'news',    'post_type' => 'news' ),
		);
		if ( current_user_can( 'manage_options' ) ) {
			$rules[] = array( 'context' => 'content', 'post_type' => 'csi_content_entry' );
		}
		foreach ( $rules as $rule ) {
			$notice = culturacsi_portal_process_post_row_action( (string) $rule['context'], (string) $rule['post_type'], true );
			if ( '' !== $notice ) {
				$message = $notice;
				break;
			}
		}
		return $message;
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_handle_sections_panel' ) ) {
	function culturacsi_portal_content_entries_handle_sections_panel(): string {
		if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) || ! isset( $_POST['csi_section_submit'] ) ) {
			return '';
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_csi_content_sections' ) ) {
			return culturacsi_portal_notice( 'Permessi insufficienti per gestire le sezioni.', 'error' );
		}
		if ( ! taxonomy_exists( 'csi_content_section' ) ) {
			return culturacsi_portal_notice( 'Tassonomia sezioni non disponibile.', 'error' );
		}
		if ( ! isset( $_POST['csi_section_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['csi_section_nonce'] ) ), 'csi_section_manage' ) ) {
			return culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
		}

		$action = isset( $_POST['csi_section_action'] ) ? sanitize_key( wp_unslash( $_POST['csi_section_action'] ) ) : '';
		if ( 'create' === $action ) {
			$name = isset( $_POST['csi_section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['csi_section_name'] ) ) : '';
			$slug = isset( $_POST['csi_section_slug'] ) ? sanitize_title( wp_unslash( $_POST['csi_section_slug'] ) ) : '';
			if ( '' === $name ) {
				return culturacsi_portal_notice( 'Inserisci il nome della nuova sezione.', 'error' );
			}
			$args = array();
			if ( '' !== $slug ) {
				$args['slug'] = $slug;
			}
			$created = wp_insert_term( $name, 'csi_content_section', $args );
			if ( is_wp_error( $created ) ) {
				return culturacsi_portal_notice( $created->get_error_message(), 'error' );
			}
			return culturacsi_portal_notice( 'Sezione creata correttamente.', 'success' );
		}

		if ( 'update' === $action ) {
			$term_id = isset( $_POST['csi_section_id'] ) ? absint( wp_unslash( $_POST['csi_section_id'] ) ) : 0;
			$name    = isset( $_POST['csi_section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['csi_section_name'] ) ) : '';
			$slug    = isset( $_POST['csi_section_slug'] ) ? sanitize_title( wp_unslash( $_POST['csi_section_slug'] ) ) : '';
			if ( $term_id <= 0 || '' === $name ) {
				return culturacsi_portal_notice( 'Seleziona una sezione e inserisci il nuovo nome.', 'error' );
			}
			$args = array( 'name' => $name );
			if ( '' !== $slug ) {
				$args['slug'] = $slug;
			}
			$updated = wp_update_term( $term_id, 'csi_content_section', $args );
			if ( is_wp_error( $updated ) ) {
				return culturacsi_portal_notice( $updated->get_error_message(), 'error' );
			}
			return culturacsi_portal_notice( 'Sezione aggiornata.', 'success' );
		}

		if ( 'delete' === $action ) {
			$term_id = isset( $_POST['csi_section_id'] ) ? absint( wp_unslash( $_POST['csi_section_id'] ) ) : 0;
			$term    = $term_id > 0 ? get_term( $term_id, 'csi_content_section' ) : null;
			if ( ! ( $term instanceof WP_Term ) ) {
				return culturacsi_portal_notice( 'Sezione non valida.', 'error' );
			}
			$is_site_admin = current_user_can( 'manage_options' );
			$protected = array( 'library', 'services', 'convenzioni', 'formazione', 'progetti', 'infopoint-stranieri' );
			if ( ! $is_site_admin && in_array( $term->slug, $protected, true ) ) {
				return culturacsi_portal_notice( 'Questa sezione base e protetta e non puo essere eliminata dagli Association Admin.', 'warning' );
			}
			if ( ! $is_site_admin && (int) $term->count > 0 ) {
				return culturacsi_portal_notice( 'Sposta prima i contenuti presenti in questa sezione.', 'warning' );
			}
			$deleted = wp_delete_term( $term_id, 'csi_content_section' );
			if ( false === $deleted || is_wp_error( $deleted ) ) {
				return culturacsi_portal_notice( 'Impossibile eliminare la sezione.', 'error' );
			}
			return culturacsi_portal_notice( 'Sezione eliminata.', 'success' );
		}

		return culturacsi_portal_notice( 'Azione sezione non riconosciuta.', 'error' );
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entries_list_shortcode' ) ) {
	function culturacsi_portal_content_entries_list_shortcode(): string {
		if ( ! culturacsi_portal_can_access() ) {
			return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
		}

		$messages = array();
		$row_notice = culturacsi_portal_content_entries_process_row_actions();
		if ( '' !== $row_notice ) {
			$messages[] = $row_notice;
		}

		$filters     = culturacsi_portal_content_entries_filters_from_request();
		$is_admin    = current_user_can( 'manage_options' );
		$current_uid = get_current_user_id();
		$assoc_id    = culturacsi_portal_get_managed_association_id( $current_uid );
		$base_url    = culturacsi_portal_reserved_current_page_url();
		$sort_state  = culturacsi_portal_get_sort_state(
			'c_sort',
			'c_dir',
			'date',
			'desc',
			array( 'index', 'type', 'title', 'section', 'date', 'status' )
		);

		// Association Admin can manage only Events and News.
		if ( ! $is_admin ) {
			if ( 'content' === $filters['type'] || '' !== $filters['section_slug'] ) {
				$filters['type'] = 'all';
			}
			$filters['section_slug'] = '';
			$filters['section_id']   = 0;
		}

		$post_types = $is_admin ? array( 'event', 'news', 'csi_content_entry' ) : array( 'event', 'news' );
		if ( 'event' === $filters['type'] ) {
			$post_types = array( 'event' );
		} elseif ( 'news' === $filters['type'] ) {
			$post_types = array( 'news' );
		} elseif ( $is_admin && ( 'content' === $filters['type'] || '' !== $filters['section_slug'] ) ) {
			$post_types = array( 'csi_content_entry' );
		}

		$forced_section_id = 0;
		if ( $is_admin && '' !== $filters['section_slug'] && taxonomy_exists( 'csi_content_section' ) ) {
			$forced_section_term = get_term_by( 'slug', $filters['section_slug'], 'csi_content_section' );
			if ( $forced_section_term instanceof WP_Term ) {
				$forced_section_id = (int) $forced_section_term->term_id;
			}
		}

		$args = array(
			'post_type'      => $post_types,
			'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'posts_per_page' => 250,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( '' !== $filters['q'] ) {
			$args['s'] = $filters['q'];
		}
		if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
			$args['date_query'] = array(
				array(
					'year'     => (int) $matches[1],
					'monthnum' => (int) $matches[2],
				),
			);
		}
		if ( 'all' !== $filters['status'] ) {
			$allowed_status = array( 'publish', 'pending', 'draft', 'future', 'private' );
			if ( in_array( $filters['status'], $allowed_status, true ) ) {
				$args['post_status'] = array( $filters['status'] );
			}
		}
		if ( $is_admin && $filters['author'] > 0 ) {
			$args['author'] = $filters['author'];
		}
		if ( $forced_section_id > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'csi_content_section',
					'field'    => 'term_id',
					'terms'    => array( $forced_section_id ),
				),
			);
		} elseif ( $is_admin && 'content' === $filters['type'] && $filters['section_id'] > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'csi_content_section',
					'field'    => 'term_id',
					'terms'    => array( $filters['section_id'] ),
				),
			);
		}
		if ( 'event' === $filters['type'] && $filters['event_type'] > 0 ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'event_type',
					'field'    => 'term_id',
					'terms'    => array( $filters['event_type'] ),
				),
			);
		}

		$query = new WP_Query( $args );
		$posts = culturacsi_portal_normalize_posts_list( (array) $query->posts );
		$posts = array_values(
			array_filter(
				$posts,
				static function ( $post ) use ( $current_uid, $is_admin, $assoc_id, $filters, $forced_section_id ): bool {
					if ( ! $post instanceof WP_Post ) {
						return false;
					}
					if ( ! culturacsi_portal_content_entries_can_manage_post( $post, $current_uid, $is_admin, $assoc_id ) ) {
						return false;
					}
					$active_section_id = $forced_section_id > 0 ? $forced_section_id : (int) $filters['section_id'];
					if ( $active_section_id > 0 ) {
						if ( 'csi_content_entry' !== $post->post_type ) {
							return false;
						}
						$ids = wp_get_post_terms( (int) $post->ID, 'csi_content_section', array( 'fields' => 'ids' ) );
						return ! is_wp_error( $ids ) && in_array( $active_section_id, array_map( 'intval', (array) $ids ), true );
					}
					if ( $filters['event_type'] > 0 ) {
						if ( 'event' !== $post->post_type ) {
							return false;
						}
						$ids = wp_get_post_terms( (int) $post->ID, 'event_type', array( 'fields' => 'ids' ) );
						return ! is_wp_error( $ids ) && in_array( $filters['event_type'], array_map( 'intval', (array) $ids ), true );
					}
					return true;
				}
			)
		);

		if ( ! empty( $posts ) ) {
			usort(
				$posts,
				static function ( $a, $b ) use ( $sort_state ): int {
					if ( ! $a instanceof WP_Post || ! $b instanceof WP_Post ) {
						return 0;
					}
					$cmp = 0;
					switch ( $sort_state['sort'] ) {
						case 'type':
							$cmp = strcasecmp( culturacsi_portal_content_entries_post_type_label( $a->post_type ), culturacsi_portal_content_entries_post_type_label( $b->post_type ) );
							break;
						case 'title':
							$cmp = strcasecmp( (string) $a->post_title, (string) $b->post_title );
							break;
						case 'section':
							$cmp = strcasecmp( culturacsi_portal_content_entries_secondary_label( $a ), culturacsi_portal_content_entries_secondary_label( $b ) );
							break;
						case 'status':
							$cmp = strcmp( (string) $a->post_status, (string) $b->post_status );
							break;
						case 'index':
							$cmp = (int) $a->ID <=> (int) $b->ID;
							break;
						case 'date':
						default:
							$cmp = strtotime( (string) $a->post_date ) <=> strtotime( (string) $b->post_date );
							break;
					}
					return ( 'asc' === $sort_state['dir'] ) ? $cmp : -$cmp;
				}
			);
		}

		$event_types = taxonomy_exists( 'event_type' ) ? get_terms(
			array(
				'taxonomy'   => 'event_type',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		) : array();
		$authors = $is_admin ? get_users(
			array(
				'orderby'             => 'display_name',
				'order'               => 'ASC',
				'who'                 => 'authors',
				'has_published_posts' => array( 'event', 'news', 'csi_content_entry' ),
				'fields'              => array( 'ID', 'display_name' ),
			)
		) : array();
		$search_type_sections = array();
		if ( $is_admin ) {
			$search_type_sections = function_exists( 'culturacsi_portal_content_sections_map' ) ? culturacsi_portal_content_sections_map() : array();
		}

		ob_start();
		foreach ( $messages as $msg ) {
			echo $msg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<div class="assoc-search-panel assoc-content-search">
			<div class="assoc-search-head">
				<div class="assoc-search-meta">
					<h3 class="assoc-search-title">Ricerca Contenuti</h3>
					<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) count( $posts ) ); ?></p>
				</div>
				<p class="assoc-search-actions"><a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a></p>
			</div>
			<form id="assoc-content-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
				<p class="assoc-search-field is-q">
					<label for="c_q">Cerca Titolo o contenuto</label>
					<input type="text" id="c_q" name="c_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Titolo o contenuto">
				</p>
				<p class="assoc-search-field is-type">
					<label for="c_type">Tipo contenuto <?php echo culturacsi_portal_help_tip( 'I filtri qui sotto cambiano in base al tipo selezionato.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
					<select id="c_type" name="c_type">
						<option value="all" <?php selected( $filters['type'], 'all' ); ?>>Tutti</option>
						<option value="event" <?php selected( $filters['type'], 'event' ); ?>>Eventi</option>
						<option value="news" <?php selected( $filters['type'], 'news' ); ?>>Notizie</option>
						<?php if ( $is_admin ) : ?>
							<?php foreach ( $search_type_sections as $section_slug => $section_label ) : ?>
								<?php $section_value = 'section_' . sanitize_key( (string) $section_slug ); ?>
								<option value="<?php echo esc_attr( $section_value ); ?>" <?php selected( $filters['type'], $section_value ); ?>><?php echo esc_html( $section_label ); ?></option>
							<?php endforeach; ?>
						<?php endif; ?>
					</select>
				</p>
				<p class="assoc-search-field is-date">
					<label for="c_date">Data</label>
					<input type="month" id="c_date" name="c_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
				</p>
				<p class="assoc-search-field is-event-type" data-type-field="event">
					<label for="c_event_type">Categoria evento</label>
					<select id="c_event_type" name="c_event_type">
						<option value="0">Tutte</option>
						<?php foreach ( (array) $event_types as $event_type ) : ?>
							<?php if ( ! $event_type instanceof WP_Term ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $event_type->term_id ); ?>" <?php selected( $filters['event_type'], (int) $event_type->term_id ); ?>><?php echo esc_html( $event_type->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<?php if ( $is_admin ) : ?>
					<p class="assoc-search-field is-author">
						<label for="c_author">Autore</label>
						<select id="c_author" name="c_author">
							<option value="0">Tutti</option>
							<?php foreach ( $authors as $author ) : ?>
								<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="assoc-search-field is-status">
						<label for="c_status">Stato</label>
						<select id="c_status" name="c_status">
							<option value="all" <?php selected( $filters['status'], 'all' ); ?>>Tutti</option>
							<option value="publish" <?php selected( $filters['status'], 'publish' ); ?>>Pubblicato</option>
							<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>>In attesa</option>
							<option value="draft" <?php selected( $filters['status'], 'draft' ); ?>>Bozza</option>
							<option value="future" <?php selected( $filters['status'], 'future' ); ?>>Programmato</option>
							<option value="private" <?php selected( $filters['status'], 'private' ); ?>>Privato</option>
						</select>
					</p>
				<?php endif; ?>
			</form>
		</div>
		<div class="assoc-portal-section assoc-portal-content-list">
			<div class="assoc-content-help-block">
				<?php
				if ( function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
					echo culturacsi_portal_render_process_tutorial(
						array(
							'title'   => '',
							'summary' => 'Come usare questa sezione',
							'open'    => false,
							'steps'   => array(
								array( 'text' => 'Scegli il tipo di contenuto da cercare.', 'tip' => 'Il filtro adatta automaticamente i campi disponibili.' ),
								array( 'text' => 'Usa ricerca e filtri per trovare il record corretto.' ),
								array( 'text' => 'Apri Mod. per modificare o usa i pulsanti rapidi per moderare/eliminare.' ),
							),
						)
					); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>
			<div class="assoc-page-toolbar">
				<div class="assoc-content-header-main">
					<h2 class="assoc-page-title">Contenuti</h2>
					<p class="assoc-content-header-desc">
						<?php echo esc_html( $is_admin ? 'Da qui gestisci Eventi, Notizie e Documenti/Servizi in un unico punto.' : 'Da qui gestisci Eventi e Notizie in un unico punto.' ); ?>
					</p>
				</div>
				<a class="button button-primary" href="<?php echo esc_url( home_url( '/area-riservata/contenuti/nuovo/' ) ); ?>">Nuovo Contenuto</a>
			</div>
			<table class="widefat striped assoc-admin-table assoc-table-content">
				<colgroup>
					<col style="width:4ch">
					<col style="width:9rem">
					<col style="width:38%">
					<col style="width:18%">
					<col style="width:8.5rem">
					<col style="width:7rem">
					<col style="width:11rem">
				</colgroup>
				<thead>
					<tr>
						<?php echo culturacsi_portal_sortable_th( '#', 'index', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-index' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo culturacsi_portal_sortable_th( 'Tipo', 'type', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-type' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo culturacsi_portal_sortable_th( 'Titolo', 'title', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-title' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo culturacsi_portal_sortable_th( 'Categoria/Sezione', 'section', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-section' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo culturacsi_portal_sortable_th( 'Data', 'date', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-date' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo culturacsi_portal_sortable_th( 'Stato', 'status', $sort_state['sort'], $sort_state['dir'], 'c_sort', 'c_dir', $base_url, 'assoc-col-status' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<th class="assoc-col-actions">Azioni</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( ! empty( $posts ) ) : ?>
					<?php $row_num = 0; ?>
					<?php foreach ( $posts as $post_item ) : ?>
						<?php if ( ! $post_item instanceof WP_Post ) { continue; } ?>
						<?php
						++$row_num;
						$post_id     = (int) $post_item->ID;
						$status      = (string) get_post_status( $post_id );
						$status_obj  = get_post_status_object( $status );
						$type_label  = culturacsi_portal_content_entries_post_type_label( $post_item->post_type );
						$context     = culturacsi_portal_content_entries_context_for_post_type( $post_item->post_type );
						$edit_url    = culturacsi_portal_content_entries_edit_url( $post_item );
						?>
						<tr class="<?php echo ( $is_admin && 'pending' === $status ) ? 'is-pending-approval' : ''; ?>">
							<td class="assoc-col-index"><?php echo esc_html( (string) $row_num ); ?></td>
							<td class="assoc-col-type"><?php echo esc_html( $type_label ); ?></td>
							<td class="assoc-col-title"><?php echo esc_html( get_the_title( $post_id ) ); ?></td>
							<td class="assoc-col-section"><?php echo esc_html( culturacsi_portal_content_entries_secondary_label( $post_item ) ); ?></td>
							<td class="assoc-col-date"><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $post_item->post_date ) ) ); ?></td>
							<td class="assoc-col-status"><span class="assoc-status-pill status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status_obj ? (string) $status_obj->label : $status ); ?></span></td>
							<td class="assoc-col-actions">
								<div class="assoc-action-group">
									<a class="assoc-action-chip chip-edit" href="<?php echo esc_url( $edit_url ); ?>">Mod.</a>
									<?php
									echo culturacsi_portal_action_button_form(
										array(
											'context'      => $context,
											'action'       => 'delete',
											'target_id'    => $post_id,
											'label'        => 'Elim.',
											'class'        => 'chip-delete',
											'confirm'      => true,
											'confirm_text' => 'Confermi eliminazione?',
										)
									); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									if ( $is_admin ) {
										$is_published = ( 'publish' === $status );
										$toggle_label = $is_published ? 'Rif.' : 'Appr.';
										$toggle_action = $is_published ? 'reject' : 'approve';
										$toggle_class = $is_published ? 'chip-reject chip-toggle' : 'chip-approve chip-toggle';
										echo culturacsi_portal_action_button_form(
											array(
												'context'   => $context,
												'action'    => $toggle_action,
												'target_id' => $post_id,
												'label'     => $toggle_label,
												'class'     => $toggle_class,
											)
										); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									}
									?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="7">Nessun contenuto trovato.</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'culturacsi_portal_content_entry_form_shortcode' ) ) {
	function culturacsi_portal_content_entry_form_shortcode(): string {
		if ( ! culturacsi_portal_can_access() ) {
			return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
		}

		$user_id    = get_current_user_id();
		$is_admin   = current_user_can( 'manage_options' );
		$assoc_id   = culturacsi_portal_get_managed_association_id( $user_id );
		$content_id = isset( $_GET['content_id'] ) ? absint( wp_unslash( $_GET['content_id'] ) ) : 0;
		$content    = $content_id > 0 ? get_post( $content_id ) : null;

		$selected_section_slug  = '';
		$selected_section_label = '';

		if ( ! $is_admin ) {
			ob_start();
			if ( function_exists( 'culturacsi_portal_creation_hub_switcher' ) ) {
				echo culturacsi_portal_creation_hub_switcher( '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			if ( function_exists( 'culturacsi_portal_render_creation_preselect_tutorial' ) ) {
				echo culturacsi_portal_render_creation_preselect_tutorial(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			return (string) ob_get_clean();
		}

		if ( $content_id > 0 ) {
			if ( ! ( $content instanceof WP_Post ) || 'csi_content_entry' !== $content->post_type ) {
				return culturacsi_portal_notice( 'Contenuto non trovato.', 'error' );
			}
			if ( ! culturacsi_portal_content_entries_can_manage_post( $content, $user_id, $is_admin, $assoc_id ) ) {
				return culturacsi_portal_notice( 'Non hai i permessi per modificare questo contenuto.', 'error' );
			}
		}

		$message = '';
		if ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && ( isset( $_POST['culturacsi_content_entry_submit'] ) || isset( $_REQUEST['is_portal_ajax'] ) ) ) {
			if ( ! isset( $_POST['culturacsi_content_entry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_content_entry_nonce'] ) ), 'culturacsi_content_entry_save' ) ) {
				$message = culturacsi_portal_notice( 'Verifica di sicurezza non valida.', 'error' );
			} else {
				$form_id   = isset( $_POST['content_id'] ) ? absint( wp_unslash( $_POST['content_id'] ) ) : 0;
				$form_post = $form_id > 0 ? get_post( $form_id ) : null;
				if ( $form_id > 0 && ( ! ( $form_post instanceof WP_Post ) || 'csi_content_entry' !== $form_post->post_type ) ) {
					$message = culturacsi_portal_notice( 'Contenuto non valido.', 'error' );
				} elseif ( $form_id > 0 && ! culturacsi_portal_content_entries_can_manage_post( $form_post, $user_id, $is_admin, $assoc_id ) ) {
					$message = culturacsi_portal_notice( 'Permessi insufficienti.', 'error' );
				} else {
					$title     = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
					$excerpt   = isset( $_POST['post_excerpt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['post_excerpt'] ) ) : '';
					$body      = isset( $_POST['post_content'] ) ? wp_kses_post( wp_unslash( $_POST['post_content'] ) ) : '';
					$section   = isset( $_POST['csi_content_section'] ) ? absint( wp_unslash( $_POST['csi_content_section'] ) ) : 0;
					$url       = isset( $_POST['csi_content_hub_external_url'] ) ? esc_url_raw( wp_unslash( $_POST['csi_content_hub_external_url'] ) ) : '';
					$btn_label = isset( $_POST['csi_content_hub_button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['csi_content_hub_button_label'] ) ) : '';
					$allowed_button_labels = array( 'Acquista', 'Visita', 'Scarica' );
					if ( ! in_array( $btn_label, $allowed_button_labels, true ) ) {
						$btn_label = '';
					}
					$remove    = isset( $_POST['remove_download_file'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['remove_download_file'] ) );

					if ( $section > 0 && taxonomy_exists( 'csi_content_section' ) ) {
						$form_term = get_term( $section, 'csi_content_section' );
						if ( $form_term instanceof WP_Term ) {
							$selected_section_slug  = $form_term->slug;
							$selected_section_label = $form_term->name;
						}
					}

					if ( '' === $title ) {
						$message = culturacsi_portal_notice( 'Il titolo e obbligatorio.', 'error' );
					}

					if ( '' === $message ) {
						$post_data = array(
							'post_type'    => 'csi_content_entry',
							'post_title'   => $title,
							'post_excerpt' => $excerpt,
							'post_content' => $body,
							'post_status'  => $is_admin ? 'publish' : 'pending',
							'post_author'  => $form_id > 0 ? (int) $form_post->post_author : $user_id,
							'ID'           => $form_id,
						);
						$saved_id = wp_insert_post( $post_data, true );
						if ( is_wp_error( $saved_id ) ) {
							$message = culturacsi_portal_notice( $saved_id->get_error_message(), 'error' );
						} else {
							$saved_id = (int) $saved_id;
							if ( taxonomy_exists( 'csi_content_section' ) ) {
								if ( $section > 0 ) {
									wp_set_post_terms( $saved_id, array( $section ), 'csi_content_section', false );
								} else {
									wp_set_post_terms( $saved_id, array(), 'csi_content_section', false );
								}
							}
							$post_assoc_id = isset( $_POST['organizer_association_id'] ) ? absint( wp_unslash( $_POST['organizer_association_id'] ) ) : $assoc_id;
							if ( $post_assoc_id > 0 ) {
								update_post_meta( $saved_id, 'organizer_association_id', $post_assoc_id );
							}
							if ( $remove ) {
								delete_post_meta( $saved_id, '_csi_content_hub_file_id' );
							}
							if ( isset( $_FILES['download_file'] ) && ! empty( $_FILES['download_file']['name'] ) ) {
								require_once ABSPATH . 'wp-admin/includes/image.php';
								require_once ABSPATH . 'wp-admin/includes/file.php';
								require_once ABSPATH . 'wp-admin/includes/media.php';
								$attachment_id = media_handle_upload( 'download_file', $saved_id );
								if ( ! is_wp_error( $attachment_id ) ) {
									update_post_meta( $saved_id, '_csi_content_hub_file_id', (int) $attachment_id );
								}
							}
							if ( '' !== trim( $url ) ) {
								update_post_meta( $saved_id, '_csi_content_hub_external_url', $url );
							} else {
								delete_post_meta( $saved_id, '_csi_content_hub_external_url' );
							}
							if ( '' !== trim( $btn_label ) ) {
								update_post_meta( $saved_id, '_csi_content_hub_button_label', $btn_label );
							} else {
								delete_post_meta( $saved_id, '_csi_content_hub_button_label' );
							}
							if ( isset( $_FILES['featured_image'] ) && ! empty( $_FILES['featured_image']['name'] ) ) {
								require_once ABSPATH . 'wp-admin/includes/image.php';
								require_once ABSPATH . 'wp-admin/includes/file.php';
								require_once ABSPATH . 'wp-admin/includes/media.php';
								$thumb_id = media_handle_upload( 'featured_image', $saved_id );
								if ( ! is_wp_error( $thumb_id ) ) {
									set_post_thumbnail( $saved_id, (int) $thumb_id );
								}
							} elseif ( 'library' === $selected_section_slug && ! has_post_thumbnail( $saved_id ) ) {
								$default_thumb_id = culturacsi_ensure_default_library_image();
								if ( $default_thumb_id > 0 ) {
									set_post_thumbnail( $saved_id, $default_thumb_id );
								}
							}
							if ( ! $is_admin ) {
								global $wpdb;
								$wpdb->update( $wpdb->posts, array( 'post_status' => 'pending' ), array( 'ID' => $saved_id ) );
								clean_post_cache( $saved_id );
							}

							if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
								while ( ob_get_level() > 0 ) {
									ob_end_clean();
								}
								wp_send_json_success( 'Contenuto salvato correttamente.', 200 );
							}

							$root_url = home_url( '/area-riservata/contenuti/' );
							wp_safe_redirect( add_query_arg( array( 'saved' => '1' ), $root_url ) );
							exit;
						}
					}
				}
			}

			if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) {
					ob_end_clean();
				}
				wp_send_json_error( wp_strip_all_tags( $message ) ?: 'Errore durante il salvataggio.', 400 );
			}
		}

		if ( isset( $_GET['saved'] ) && '1' === (string) $_GET['saved'] ) {
			$message = culturacsi_portal_notice( 'Contenuto salvato correttamente.', 'success' );
			$content_id = isset( $_GET['content_id'] ) ? absint( wp_unslash( $_GET['content_id'] ) ) : 0;
			$content    = $content_id > 0 ? get_post( $content_id ) : null;
		}

		$sections = taxonomy_exists( 'csi_content_section' ) ? get_terms(
			array(
				'taxonomy'   => 'csi_content_section',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		) : array();
		$selected_section = 0;
		if ( $content_id > 0 ) {
			$ids = wp_get_post_terms( $content_id, 'csi_content_section', array( 'fields' => 'ids' ) );
			$selected_section = ! is_wp_error( $ids ) && isset( $ids[0] ) ? (int) $ids[0] : 0;
		} elseif ( isset( $_GET['section'] ) ) {
			$slug = sanitize_title( wp_unslash( $_GET['section'] ) );
			$term = get_term_by( 'slug', $slug, 'csi_content_section' );
			if ( $term instanceof WP_Term ) {
				$selected_section = (int) $term->term_id;
			}
		} elseif ( 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['csi_content_section'] ) ) {
			$maybe_section = absint( wp_unslash( $_POST['csi_content_section'] ) );
			if ( $maybe_section > 0 ) {
				$term = get_term( $maybe_section, 'csi_content_section' );
				if ( $term instanceof WP_Term ) {
					$selected_section = (int) $term->term_id;
				}
			}
		}
		$selected_section_slug = '';
		$selected_section_label = '';
		if ( $selected_section > 0 ) {
			$selected_term = get_term( $selected_section, 'csi_content_section' );
			if ( $selected_term instanceof WP_Term ) {
				$selected_section_slug = sanitize_title( (string) $selected_term->slug );
				$selected_section_label = sanitize_text_field( (string) $selected_term->name );
			}
		}
		$creation_switch_current = '';
		if ( $content_id > 0 || '' !== $selected_section_slug ) {
			$creation_switch_current = ( '' !== $selected_section_slug ) ? ( 'section_' . $selected_section_slug ) : 'content';
		}
		$has_selected_type = ( $content_id > 0 || $selected_section > 0 );
		if ( ! $has_selected_type && 'POST' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) && isset( $_POST['culturacsi_content_entry_submit'] ) ) {
			$has_selected_type = true;
		}
		$form_heading = ( $content_id > 0 ) ? 'Modifica Contenuto' : 'Nuovo Contenuto';
		if ( '' !== $selected_section_label ) {
			$form_heading = ( $content_id > 0 ) ? ( 'Modifica in ' . $selected_section_label ) : ( 'Nuovo in ' . $selected_section_label );
		}
		$is_convenzioni = ( 'convenzioni' === $selected_section_slug );
		$content_checklist = array(
			array( 'label' => 'Titolo compilato', 'selectors' => array( '#post_title' ), 'mode' => 'all' ),
		);
		if ( $selected_section <= 0 ) {
			$content_checklist[] = array( 'label' => 'Sezione selezionata', 'selectors' => array( '#csi_content_section' ), 'mode' => 'all' );
		}
		$content_checklist[] = array( 'label' => ( $is_convenzioni ? 'Immagine e descrizione inserite' : 'Descrizione o media inseriti' ), 'selectors' => array( '#post_excerpt', '#culturacsi_content_entry_content', '#download_file', '#csi_content_hub_external_url', '#csi-existing-file-id', '#featured_image', '#csi-existing-thumb' ), 'mode' => 'any' );

		$content_steps = array();
		if ( $selected_section <= 0 ) {
			$content_steps[] = array( 'text' => 'Inserisci titolo e sezione.' );
		} else {
			$content_steps[] = array( 'text' => 'Inserisci il titolo.' );
		}
		$content_steps[] = array( 'text' => 'Aggiungi una descrizione semplice e leggibile.' );
		if ( $is_convenzioni ) {
			$content_steps[] = array( 'text' => 'Carica l\'immagine della convenzione (obbligatoria).' );
		}
		$content_steps[] = array( 'text' => 'Salva il contenuto.' );
		if ( $content_id <= 0 ) {
			array_unshift( $content_checklist, array( 'label' => 'Tipo contenuto selezionato', 'selectors' => array( '#csi-creation-hub-select' ), 'mode' => 'all' ) );
			array_unshift( $content_steps, array( 'text' => 'Scegli il tipo nel menu Crea nuovo contenuto.' ) );
		}

		$current_file_id   = $content_id > 0 ? (int) get_post_meta( $content_id, '_csi_content_hub_file_id', true ) : 0;
		$current_file_url  = $current_file_id > 0 ? (string) wp_get_attachment_url( $current_file_id ) : '';
		$current_file_name = $current_file_id > 0 ? (string) wp_basename( (string) get_attached_file( $current_file_id ) ) : '';
		$current_url       = $content_id > 0 ? (string) get_post_meta( $content_id, '_csi_content_hub_external_url', true ) : '';
		$current_btn       = $content_id > 0 ? trim( (string) get_post_meta( $content_id, '_csi_content_hub_button_label', true ) ) : '';
		if ( ! in_array( $current_btn, array( 'Acquista', 'Visita', 'Scarica' ), true ) ) {
			$current_btn = '';
		}

		ob_start();
		echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
					'intro'     => 'Compila i passaggi e usa la checklist per verificare che non manchi nulla.',
					'summary'   => 'Tutorial rapido',
					'checklist' => $content_checklist,
					'steps'     => $content_steps,
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( function_exists( 'culturacsi_portal_creation_hub_switcher' ) ) {
			echo culturacsi_portal_creation_hub_switcher( $creation_switch_current ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		$root_url = home_url( '/area-riservata/contenuti/' );
		?>
		<form class="assoc-portal-form" method="post" enctype="multipart/form-data" data-redirect-url="<?php echo esc_url( $root_url ); ?>">
			<?php wp_nonce_field( 'culturacsi_content_entry_save', 'culturacsi_content_entry_nonce' ); ?>
			<input type="hidden" name="content_id" value="<?php echo esc_attr( (string) $content_id ); ?>">
			<input type="hidden" id="csi-existing-file-id" value="<?php echo esc_attr( (string) $current_file_id ); ?>">
			<input type="hidden" id="csi-existing-thumb" value="<?php echo esc_attr( has_post_thumbnail( $content_id ) ? '1' : '' ); ?>">
			<h2><?php echo esc_html( $form_heading ); ?></h2>
			<p>
				<?php echo culturacsi_portal_label_with_tip( 'post_title', 'Titolo *', 'Usa un titolo breve e chiaro, facilmente riconoscibile.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="text" id="post_title" name="post_title" required value="<?php echo esc_attr( $content_id > 0 && $content instanceof WP_Post ? (string) $content->post_title : '' ); ?>">
			</p>
			<?php 
			$current_post_assoc = $content_id > 0 ? (int) get_post_meta( $content_id, 'organizer_association_id', true ) : 0;
			if ( function_exists( 'culturacsi_portal_render_association_selection_field' ) ) {
				echo culturacsi_portal_render_association_selection_field( $current_post_assoc ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			}
			?>
			<?php if ( $selected_section > 0 ) : ?>
				<input type="hidden" name="csi_content_section" value="<?php echo esc_attr( (string) $selected_section ); ?>">
			<?php else : ?>
				<p>
					<?php echo culturacsi_portal_label_with_tip( 'csi_content_section', 'Sezione', 'La sezione decide dove comparirà questo contenuto sul sito.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<select id="csi_content_section" name="csi_content_section">
						<option value="0">Seleziona sezione...</option>
						<?php foreach ( (array) $sections as $section ) : ?>
							<?php if ( ! $section instanceof WP_Term ) { continue; } ?>
							<option value="<?php echo esc_attr( (string) $section->term_id ); ?>" <?php selected( $selected_section, (int) $section->term_id ); ?>><?php echo esc_html( $section->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endif; ?>
			<p>
				<?php echo culturacsi_portal_label_with_tip( 'post_excerpt', 'Sommario', '2-3 frasi sono sufficienti: cosa e, a chi serve, cosa scaricare.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<textarea id="post_excerpt" name="post_excerpt" rows="3"><?php echo esc_textarea( $content_id > 0 && $content instanceof WP_Post ? (string) $content->post_excerpt : '' ); ?></textarea>
			</p>
			<p>
				<label for="post_content">Contenuto esteso</label>
				<?php wp_editor( $content_id > 0 && $content instanceof WP_Post ? (string) $content->post_content : '', 'culturacsi_content_entry_content', array( 'textarea_name' => 'post_content', 'media_buttons' => false, 'textarea_rows' => 8 ) ); ?>
			</p>
			<p>
				<?php 
				$file_label = $is_convenzioni ? 'Documento allegato (facoltativo)' : 'Documento da scaricare';
				$file_tip   = $is_convenzioni ? 'Usa questo campo se la convenzione ha un modulo o un PDF di dettaglio.' : 'Carica PDF, documenti o altri file da mettere a disposizione degli utenti.';
				echo culturacsi_portal_label_with_tip( 'download_file', $file_label, $file_tip ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
				<input type="file" id="download_file" name="download_file">
			</p>
			<?php if ( '' !== $current_file_url ) : ?>
				<p><a href="<?php echo esc_url( $current_file_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( '' !== $current_file_name ? $current_file_name : 'Documento attuale' ); ?></a></p>
				<p><label><input type="checkbox" name="remove_download_file" value="1"> Rimuovi documento attuale</label></p>
			<?php endif; ?>
			<p>
				<?php echo culturacsi_portal_label_with_tip( 'csi_content_hub_external_url', 'URL esterno (facoltativo)', 'Usa questo campo se il documento e su una piattaforma esterna.' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<input type="url" id="csi_content_hub_external_url" name="csi_content_hub_external_url" value="<?php echo esc_attr( $current_url ); ?>" placeholder="https://">
			</p>
			<p>
				<label for="csi_content_hub_button_label">Testo pulsante (facoltativo)</label>
				<select id="csi_content_hub_button_label" name="csi_content_hub_button_label">
					<option value="">Seleziona etichetta...</option>
					<option value="Acquista" <?php selected( $current_btn, 'Acquista' ); ?>>Acquista</option>
					<option value="Visita" <?php selected( $current_btn, 'Visita' ); ?>>Visita</option>
					<option value="Scarica" <?php selected( $current_btn, 'Scarica' ); ?>>Scarica</option>
				</select>
			</p>
			<p>
				<?php
				$is_library = ( 'library' === $selected_section_slug );
				$img_label  = $is_convenzioni ? 'Immagine Convenzione *' : ( $is_library ? 'Immagine (facoltativa)' : 'Immagine' );
				$img_tip    = $is_library ? 'Se non caricata, verr&agrave; usata un\'icona PDF predefinita.' : 'L\'immagine principale del contenuto.';
				echo culturacsi_portal_label_with_tip( 'featured_image', $img_label, $img_tip ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				<input type="file" id="featured_image" name="featured_image" accept="image/*" <?php echo ( $is_convenzioni && ! has_post_thumbnail( $content_id ) ) ? 'required' : ''; ?>>
			</p>
			<?php if ( $content_id > 0 && has_post_thumbnail( $content_id ) ) : ?>
				<p class="current-image"><?php echo get_the_post_thumbnail( $content_id, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
			<?php endif; ?>
			<p><button type="submit" name="culturacsi_content_entry_submit" class="button button-primary">Salva Contenuto</button></p>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'culturacsi_portal_content_sections_manager_shortcode' ) ) {
	function culturacsi_portal_content_sections_manager_shortcode(): string {
		if ( ! culturacsi_portal_can_access() ) {
			return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
		}
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_csi_content_sections' ) ) {
			return culturacsi_portal_notice( 'Permessi insufficienti per gestire le sezioni.', 'error' );
		}

		$is_site_admin = current_user_can( 'manage_options' );
		$notice = culturacsi_portal_content_entries_handle_sections_panel();
		$sections = taxonomy_exists( 'csi_content_section' ) ? get_terms(
			array(
				'taxonomy'   => 'csi_content_section',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		) : array();
		if ( is_wp_error( $sections ) ) {
			$sections = array();
		}
		$protected = array( 'library', 'services', 'convenzioni', 'formazione', 'progetti', 'infopoint-stranieri' );
		$protection_note = $is_site_admin
			? 'Come Site Admin puoi aggiungere, modificare o eliminare qualsiasi sezione.'
			: 'Le sezioni base sono protette e non possono essere eliminate.';
		$step_delete_note = $is_site_admin
			? 'Come Site Admin puoi eliminare qualsiasi sezione. Se contiene contenuti, i collegamenti alla sezione verranno rimossi.'
			: 'Elimina una sezione solo se vuota. Le sezioni base sono protette.';

		ob_start();
		if ( '' !== $notice ) {
			echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( function_exists( 'culturacsi_portal_render_process_tutorial' ) ) {
			echo culturacsi_portal_render_process_tutorial(
				array(
					'title'   => '',
					'summary' => 'Come usare questa sezione',
					'open'    => false,
					'steps'   => array(
						array( 'text' => 'Aggiungi una sezione solo quando serve davvero una nuova area pubblica.' ),
						array( 'text' => 'Per rinominare usa Modifica sezione e inserisci nuovo nome/slug.' ),
						array( 'text' => $step_delete_note ),
					),
				)
			); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<div class="assoc-portal-section assoc-portal-sections-manager">
			<div class="assoc-sections-header">
				<h2 class="assoc-page-title">Sezioni</h2>
				<p class="assoc-sections-desc">Area separata per la gestione avanzata delle sezioni contenuti.</p>
			</div>
			<div class="assoc-sections-shell">
				<p class="assoc-sections-title">Gestione Sezioni (avanzato)</p>
				<p class="assoc-sections-note"><?php echo esc_html( $protection_note ); ?> Questa area e separata da Contenuti per ridurre errori operativi.</p>
				<div class="assoc-sections-grid">
					<form method="post" class="assoc-portal-form assoc-sections-card">
						<?php wp_nonce_field( 'csi_section_manage', 'csi_section_nonce' ); ?>
						<input type="hidden" name="csi_section_action" value="create">
						<p><strong>Aggiungi sezione</strong></p>
						<p><label for="csi_section_name_create">Nome</label><input type="text" id="csi_section_name_create" name="csi_section_name" required></p>
						<p><label for="csi_section_slug_create">Slug (facoltativo)</label><input type="text" id="csi_section_slug_create" name="csi_section_slug"></p>
						<p><button type="submit" name="csi_section_submit" class="button">Aggiungi</button></p>
					</form>
					<form method="post" class="assoc-portal-form assoc-sections-card">
						<?php wp_nonce_field( 'csi_section_manage', 'csi_section_nonce' ); ?>
						<input type="hidden" name="csi_section_action" value="update">
						<p><strong>Modifica sezione</strong></p>
						<p><label for="csi_section_id_update">Sezione</label>
							<select id="csi_section_id_update" name="csi_section_id" required>
								<option value="">Seleziona...</option>
								<?php foreach ( (array) $sections as $section ) : ?>
									<?php if ( ! $section instanceof WP_Term ) { continue; } ?>
									<option value="<?php echo esc_attr( (string) $section->term_id ); ?>"><?php echo esc_html( $section->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p><label for="csi_section_name_update">Nuovo nome</label><input type="text" id="csi_section_name_update" name="csi_section_name" required></p>
						<p><label for="csi_section_slug_update">Nuovo slug (facoltativo)</label><input type="text" id="csi_section_slug_update" name="csi_section_slug"></p>
						<p><button type="submit" name="csi_section_submit" class="button">Aggiorna</button></p>
					</form>
					<form method="post" class="assoc-portal-form assoc-sections-card">
						<?php wp_nonce_field( 'csi_section_manage', 'csi_section_nonce' ); ?>
						<input type="hidden" name="csi_section_action" value="delete">
						<p><strong>Elimina sezione</strong></p>
						<p><label for="csi_section_id_delete">Sezione</label>
							<select id="csi_section_id_delete" name="csi_section_id" required>
								<option value="">Seleziona...</option>
								<?php foreach ( (array) $sections as $section ) : ?>
									<?php if ( ! $section instanceof WP_Term ) { continue; } ?>
									<option value="<?php echo esc_attr( (string) $section->term_id ); ?>"><?php echo esc_html( $section->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p><button type="submit" name="csi_section_submit" class="button" onclick="return confirm('Confermi eliminazione sezione?');">Elimina</button></p>
					</form>
				</div>
				<div class="assoc-sections-list">
					<p class="assoc-sections-title">Sezioni attive</p>
					<?php if ( ! empty( $sections ) ) : ?>
						<ul class="assoc-sections-chips">
							<?php foreach ( $sections as $section ) : ?>
								<?php if ( ! $section instanceof WP_Term ) { continue; } ?>
								<?php $is_protected = ( ! $is_site_admin && in_array( (string) $section->slug, $protected, true ) ); ?>
								<li class="assoc-sections-chip<?php echo $is_protected ? ' is-protected' : ''; ?>">
									<span><?php echo esc_html( $section->name ); ?></span>
									<span class="assoc-sections-count">(<?php echo esc_html( (string) (int) $section->count ); ?>)</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p>Nessuna sezione trovata.</p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
