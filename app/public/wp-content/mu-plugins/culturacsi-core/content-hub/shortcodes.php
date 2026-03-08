<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content hub frontend helpers and shortcodes.
 */
function culturacsi_content_hub_enqueue_search_assets(): void {
	static $enqueued = false;
	if ( $enqueued ) {
		return;
	}
	$enqueued = true;

	wp_enqueue_style(
		'culturacsi-content-hub-search',
		culturacsi_content_hub_asset_url( 'content-hub-search.css' ),
		array(),
		culturacsi_content_hub_asset_version( 'content-hub-search.css' )
	);

	wp_enqueue_script(
		'culturacsi-content-hub-search',
		culturacsi_content_hub_asset_url( 'content-hub-search.js' ),
		array(),
		culturacsi_content_hub_asset_version( 'content-hub-search.js' ),
		true
	);
}

function culturacsi_content_hub_is_truthy( $value ) {
	$normalized = strtolower( trim( (string) $value ) );
	return in_array( $normalized, array( '1', 'true', 'yes', 'on', 'si' ), true );
}

/**
 * Return all content hub sections as slug => label.
 *
 * @return array<string,string>
 */
function culturacsi_content_hub_sections_map() {
	if ( ! taxonomy_exists( CULTURACSI_CONTENT_HUB_TAXONOMY ) ) {
		return array();
	}

	$terms = get_terms(
		array(
			'taxonomy'   => CULTURACSI_CONTENT_HUB_TAXONOMY,
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'ASC',
		)
	);
	if ( is_wp_error( $terms ) || ! is_array( $terms ) || empty( $terms ) ) {
		return array();
	}

	$map = array();
	foreach ( $terms as $term ) {
		if ( ! $term instanceof WP_Term ) {
			continue;
		}
		$slug = sanitize_title( (string) $term->slug );
		if ( '' === $slug ) {
			continue;
		}
		$map[ $slug ] = sanitize_text_field( (string) $term->name );
	}
	return $map;
}

/**
 * Normalize a section identifier (e.g. section_library) to a section slug.
 *
 * @param string $raw_identifier Raw section identifier.
 * @return string
 */
function culturacsi_content_hub_section_slug_from_identifier( $raw_identifier ) {
	$value = sanitize_key( trim( (string) $raw_identifier ) );
	if ( '' === $value ) {
		return '';
	}

	if ( 0 === strpos( $value, 'csi_section_' ) ) {
		$value = substr( $value, strlen( 'csi_section_' ) );
	} elseif ( 0 === strpos( $value, 'section_' ) ) {
		$value = substr( $value, strlen( 'section_' ) );
	}

	$value = str_replace( '_', '-', $value );
	return sanitize_title( $value );
}

/**
 * Build public identifiers for each section.
 *
 * @return array<string,array<string,string>>
 */
function culturacsi_content_hub_section_identifiers() {
	$sections = culturacsi_content_hub_sections_map();
	$out      = array();
	foreach ( $sections as $slug => $label ) {
		$slug_key = sanitize_key( (string) $slug );
		if ( '' === $slug_key ) {
			continue;
		}
		$out[ $slug ] = array(
			'slug'       => $slug,
			'label'      => $label,
			'identifier' => 'section_' . $slug_key,
			'shortcode'  => 'culturacsi_section_' . str_replace( '-', '_', $slug_key ),
		);
	}
	return $out;
}

/**
 * Parse section filters from shortcode attributes.
 *
 * Supports explicit slugs via "section" and stable identifiers via
 * "identifier"/"id" (e.g. section_library, csi_section_progetti).
 *
 * @param array<string,mixed> $atts Shortcode attributes.
 * @return array<int,string>
 */
function culturacsi_content_hub_parse_sections_from_atts( $atts ) {
	$sections_map = culturacsi_content_hub_sections_map();
	$label_lookup = array();
	foreach ( $sections_map as $slug => $label ) {
		$label_lookup[ sanitize_title( (string) $label ) ] = $slug;
	}

	$raw_values = array();
	foreach ( array( 'section', 'identifier', 'id' ) as $key ) {
		if ( empty( $atts[ $key ] ) ) {
			continue;
		}
		$raw_values = array_merge(
			$raw_values,
			array_map(
				'trim',
				explode( ',', (string) $atts[ $key ] )
			)
		);
	}

	$resolved = array();
	foreach ( $raw_values as $raw_item ) {
		$item = trim( (string) $raw_item );
		if ( '' === $item ) {
			continue;
		}

		$slug = '';
		$by_identifier = culturacsi_content_hub_section_slug_from_identifier( $item );
		if ( '' !== $by_identifier ) {
			$slug = isset( $sections_map[ $by_identifier ] ) ? $by_identifier : $by_identifier;
		}

		if ( '' === $slug ) {
			$normalized = sanitize_title( $item );
			if ( isset( $sections_map[ $normalized ] ) ) {
				$slug = $normalized;
			} elseif ( isset( $label_lookup[ $normalized ] ) ) {
				$slug = $label_lookup[ $normalized ];
			}
		}

		if ( '' === $slug ) {
			continue;
		}
		$resolved[ $slug ] = $slug;
	}

	return array_values( $resolved );
}

/**
 * Render reusable content hub listing.
 *
 * @param array<string,mixed> $atts Shortcode attributes.
 * @return string
 */
function culturacsi_content_hub_shortcode( $atts = array() ) {
	if ( ! post_type_exists( CULTURACSI_CONTENT_HUB_POST_TYPE ) ) {
		return '';
	}

	$atts = shortcode_atts(
		array(
			'section'        => '',
			'identifier'     => '',
			'id'             => '',
			'title'          => '',
			'per_page'       => 12,
			'search'         => 'yes',
			'downloads_only' => 'no',
			'orderby'        => 'date',
			'order'          => 'DESC',
			'show_image'     => 'yes',
			'empty_message'  => __( 'Nessun contenuto trovato.', 'culturacsi' ),
			'instance'       => '',
			'is_library'     => 'no',
		),
		$atts,
		'culturacsi_content_hub'
	);

	$theme_news_css = get_template_directory() . '/css/news.css';
	if ( file_exists( $theme_news_css ) ) {
		wp_enqueue_style(
			'culturacsi-news-style',
			get_template_directory_uri() . '/css/news.css',
			array(),
			(string) filemtime( $theme_news_css )
		);
	}
	wp_enqueue_style( 'culturacsi-content-hub-style' );
	wp_enqueue_script( 'culturacsi-content-hub-script' );

	$sections = culturacsi_content_hub_parse_sections_from_atts( $atts );

	$instance_raw = trim( (string) $atts['instance'] );
	$instance_seed = 'hub';
	if ( '' !== $instance_raw ) {
		$instance_seed = $instance_raw;
	} elseif ( ! empty( $sections ) ) {
		$instance_seed = implode( '-', $sections );
	} elseif ( '' !== trim( (string) $atts['identifier'] ) ) {
		$instance_seed = (string) $atts['identifier'];
	} elseif ( '' !== trim( (string) $atts['id'] ) ) {
		$instance_seed = (string) $atts['id'];
	}
	$instance = sanitize_key( $instance_seed );
	if ( '' === $instance ) {
		$instance = 'hub';
	}

	$query_var_q    = 'hub_q_' . $instance;
	$query_var_page = 'hub_page_' . $instance;
	$query_text     = isset( $_GET[ $query_var_q ] ) ? sanitize_text_field( wp_unslash( $_GET[ $query_var_q ] ) ) : ( isset( $_GET['ch_q'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_q'] ) ) : ( isset( $_GET['news_q'] ) ? sanitize_text_field( wp_unslash( $_GET['news_q'] ) ) : '' ) );
	$paged          = isset( $_GET[ $query_var_page ] ) ? max( 1, absint( wp_unslash( $_GET[ $query_var_page ] ) ) ) : 1;


	$per_page = max( 1, absint( $atts['per_page'] ) );
	$orderby  = sanitize_key( (string) $atts['orderby'] );
	if ( ! in_array( $orderby, array( 'date', 'title', 'modified', 'menu_order', 'rand' ), true ) ) {
		$orderby = 'date';
	}

	$order = strtoupper( sanitize_key( (string) $atts['order'] ) );
	if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
		$order = 'DESC';
	}

	$args = array(
		'post_type'      => CULTURACSI_CONTENT_HUB_POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => $orderby,
		'order'          => $order,
	);

	if ( '' !== trim( $query_text ) ) {
		$args['s'] = $query_text;
	}

	$filters            = culturacsi_content_hub_filters_from_request();
	$has_global_filters = ( '' !== $filters['q'] || '' !== $filters['date'] || $filters['author'] > 0 || $filters['assoc'] > 0 || '' !== $filters['doc_type'] );
	if ( $has_global_filters ) {
		$global_section = $filters['section'];
		$match_section  = ( '' === $global_section || in_array( $global_section, $sections, true ) );

		if ( $match_section ) {
			$args = culturacsi_content_hub_apply_filters_to_query_vars( $args, $filters );
		}
	}


	if ( ! empty( $sections ) ) {
		if ( ! isset( $args['tax_query'] ) || ! is_array( $args['tax_query'] ) ) {
			$args['tax_query'] = array();
		}
		$args['tax_query'][] = array(
			'taxonomy' => CULTURACSI_CONTENT_HUB_TAXONOMY,
			'field'    => 'slug',
			'terms'    => array_values( $sections ),
		);
	}

	if ( culturacsi_content_hub_is_truthy( $atts['downloads_only'] ) ) {
		if ( ! isset( $args['meta_query'] ) || ! is_array( $args['meta_query'] ) ) {
			$args['meta_query'] = array();
		}
		$args['meta_query'][] = array(
			'relation' => 'OR',
			array(
				'key'     => '_csi_content_hub_file_id',
				'value'   => 0,
				'type'    => 'NUMERIC',
				'compare' => '>',
			),
			array(
				'key'     => '_csi_content_hub_external_url',
				'value'   => '',
				'compare' => '!=',
			),
		);
	}

	$query = new WP_Query( $args );

	$explicit_title = trim( (string) $atts['title'] );
	$title          = $explicit_title;
	if ( '' === $title && 1 === count( $sections ) ) {
		$term = get_term_by( 'slug', reset( $sections ), CULTURACSI_CONTENT_HUB_TAXONOMY );
		if ( $term instanceof WP_Term ) {
			$title = (string) $term->name;
		}
	}

	$show_search = culturacsi_content_hub_is_truthy( $atts['search'] );
	if ( $show_search ) {
		// Reuse the extracted search bundle for the public hub search form too.
		culturacsi_content_hub_enqueue_search_assets();
	}
	$show_image  = culturacsi_content_hub_is_truthy( $atts['show_image'] );

	$preserved = array();
	foreach ( $_GET as $key => $value ) {
		if ( $query_var_q === $key || $query_var_page === $key ) {
			continue;
		}
		if ( is_array( $value ) ) {
			continue;
		}
		$preserved[ (string) $key ] = (string) $value;
	}

	ob_start();
	?>
	<section class="csi-content-hub csi-content-hub-<?php echo esc_attr( $instance ); ?>">
		<?php if ( '' !== $title || $show_search ) : ?>
			<header class="csi-content-hub-header page-header">
				<?php if ( '' !== $title ) : ?>
					<h2 class="csi-content-hub-title page-title"><?php echo esc_html( $title ); ?></h2>
				<?php endif; ?>
				<?php if ( $show_search ) : ?>
					<form method="get" class="csi-content-hub-search" action="<?php echo esc_url( remove_query_arg( array( $query_var_q, $query_var_page ) ) ); ?>">
						<?php foreach ( $preserved as $key => $value ) : ?>
							<input type="hidden" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>">
						<?php endforeach; ?>
						<label class="screen-reader-text" for="<?php echo esc_attr( $query_var_q ); ?>"><?php esc_html_e( 'Cerca', 'culturacsi' ); ?></label>
						<input type="search" id="<?php echo esc_attr( $query_var_q ); ?>" name="<?php echo esc_attr( $query_var_q ); ?>" value="<?php echo esc_attr( $query_text ); ?>" placeholder="<?php esc_attr_e( 'Cerca contenuti...', 'culturacsi' ); ?>">
						<button type="submit"><?php esc_html_e( 'Cerca', 'culturacsi' ); ?></button>
					</form>
				<?php endif; ?>
			</header>
		<?php endif; ?>

		<div class="csi-content-hub-grid news-grid">
			<?php if ( $query->have_posts() ) : ?>
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$post_id      = get_the_ID();
					$file_id      = (int) get_post_meta( $post_id, '_csi_content_hub_file_id', true );
					$external_url = (string) get_post_meta( $post_id, '_csi_content_hub_external_url', true );
					$button_label = trim( (string) get_post_meta( $post_id, '_csi_content_hub_button_label', true ) );

					$link_url   = get_permalink( $post_id );
					$link_class = 'read-more';
					$link_attrs = '';
					$file_note  = '';

					$is_library_entry = ( culturacsi_content_hub_is_truthy( $atts['is_library'] ) ) || has_term( 'library', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id ) || has_term( 'biblioteca', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id ) || has_term( 'document-library', CULTURACSI_CONTENT_HUB_TAXONOMY, $post_id );
					$has_external_url = '' !== trim( $external_url );

					$attachment_url = $file_id > 0 ? (string) wp_get_attachment_url( $file_id ) : '';
					$has_file_link  = ( $file_id > 0 && '' !== $attachment_url );
					if ( $file_id > 0 && '' !== $attachment_url ) {
						$file_path = (string) get_attached_file( $file_id );
						if ( '' !== trim( $file_path ) && file_exists( $file_path ) ) {
							$file_note = size_format( (float) filesize( $file_path ) );
						}
					}

					$use_modal_for_non_library_entry = ! $is_library_entry && ( $has_file_link || ! $has_external_url );

					if ( $is_library_entry ) {
						// Biblioteca always opens modal; modal CTA(s) decide file/link actions.
						$link_url = '#csi-modal-post-' . $post_id;
						if ( '' === $button_label ) {
							$button_label = __( 'Dettagli', 'culturacsi' );
						}
					} elseif ( $use_modal_for_non_library_entry ) {
						// Non-library entries: if file is linked OR external URL is missing,
						// open entry details in modal.
						$link_url = '#csi-modal-post-' . $post_id;
						if ( '' === $button_label ) {
							$button_label = __( 'Dettagli', 'culturacsi' );
						}
					} elseif ( $has_external_url ) {
						// Non-library entries with external URL: open external resource.
						$link_url   = $external_url;
						$link_attrs = ' target="_blank" rel="noopener noreferrer"';
						if ( '' === $button_label ) {
							$button_label = __( 'Apri Risorsa', 'culturacsi' );
						}
					} elseif ( $file_id > 0 && '' !== $attachment_url ) {
						// Fallback to File if no external URL or not external hub
						$link_url   = $attachment_url;
						$link_attrs = ' download';
						if ( '' === $button_label ) {
							$button_label = __( 'Scarica Documento', 'culturacsi' );
						}
					} elseif ( '' !== trim( $external_url ) ) {
						// Final fallback to external URL
						$link_url   = $external_url;
						$link_attrs = ' target="_blank" rel="noopener noreferrer"';
						if ( '' === $button_label ) {
							$button_label = __( 'Apri Risorsa', 'culturacsi' );
						}
					} elseif ( '' === $button_label ) {
						$button_label = __( 'Leggi di piu', 'culturacsi' );
					}
					?>
					<article id="post-<?php the_ID(); ?>" <?php post_class( 'csi-content-hub-item news-item' ); ?>>
						<div class="news-item-inner">
							<?php if ( $show_image && has_post_thumbnail() ) : ?>
								<div class="news-item-image csi-content-hub-image">
									<a href="<?php echo esc_url( $link_url ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
										<?php the_post_thumbnail( 'medium_large' ); ?>
									</a>
								</div>
							<?php endif; ?>

							<div class="news-item-content csi-content-hub-content">
								<header class="entry-header">
									<h3 class="entry-title">
										<a href="<?php echo esc_url( $link_url ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
											<?php the_title(); ?>
										</a>
									</h3>
								</header>
								<div class="entry-summary">
									<?php echo wp_kses_post( wpautop( get_the_excerpt() ) ); ?>
								</div>
								<?php if ( '' !== $file_note ) : ?>
									<p class="csi-content-hub-file-note"><?php echo esc_html( $file_note ); ?></p>
								<?php endif; ?>
								<a class="<?php echo esc_attr( $link_class ); ?>" href="<?php echo esc_url( $link_url ); ?>"<?php echo $link_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo esc_html( $button_label ); ?></a>
							</div>
						</div>
					</article>
				<?php endwhile; ?>
				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p class="csi-content-hub-empty"><?php echo esc_html( (string) $atts['empty_message'] ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $query->max_num_pages > 1 ) : ?>
			<?php
			$pagination = paginate_links(
				array(
					'base'      => add_query_arg( $query_var_page, '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => $query->max_num_pages,
					'type'      => 'list',
					'prev_text' => __( 'Prec', 'culturacsi' ),
					'next_text' => __( 'Succ', 'culturacsi' ),
				)
			);
			?>
			<?php if ( is_string( $pagination ) && '' !== trim( $pagination ) ) : ?>
				<nav class="csi-content-hub-pagination the-posts-pagination" aria-label="<?php esc_attr_e( 'Paginazione contenuti', 'culturacsi' ); ?>">
					<?php echo wp_kses_post( $pagination ); ?>
				</nav>
			<?php endif; ?>
		<?php endif; ?>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'culturacsi_content_hub', 'culturacsi_content_hub_shortcode' );

/**
 * Get content hub search filters from the request.
 *
 * @return array
 */
function culturacsi_content_hub_filters_from_request() {
	static $filters = null;
	if ( null !== $filters ) {
		return $filters;
	}
	$filters = array(
		'q'          => isset( $_GET['ch_q'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_q'] ) ) : ( isset( $_GET['news_q'] ) ? sanitize_text_field( wp_unslash( $_GET['news_q'] ) ) : ( isset( $_GET['a_q'] ) ? sanitize_text_field( wp_unslash( $_GET['a_q'] ) ) : '' ) ),
		'date'       => isset( $_GET['ch_date'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_date'] ) ) : ( isset( $_GET['news_date'] ) ? sanitize_text_field( wp_unslash( $_GET['news_date'] ) ) : '' ),
		'author'     => isset( $_GET['ch_author'] ) ? absint( $_GET['ch_author'] ) : ( isset( $_GET['news_author'] ) ? absint( $_GET['news_author'] ) : 0 ),
		'assoc'      => isset( $_GET['ch_assoc'] ) ? absint( $_GET['ch_assoc'] ) : ( isset( $_GET['news_assoc'] ) ? absint( $_GET['news_assoc'] ) : 0 ),
		'section'    => isset( $_GET['ch_section'] ) ? sanitize_title( wp_unslash( $_GET['ch_section'] ) ) : '',
		'doc_type'   => isset( $_GET['ch_doc_type'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_doc_type'] ) ) : '',
	);
	return $filters;
}

/**
 * Apply content-hub filters to a query vars array.
 *
 * @param array $query_vars
 * @param array $filters
 * @return array
 */
function culturacsi_content_hub_apply_filters_to_query_vars( $query_vars, $filters ) {
	if ( '' !== $filters['q'] ) {
		$query_vars['s'] = $filters['q'];
	}

	if ( $filters['author'] > 0 ) {
		$query_vars['author'] = (int) $filters['author'];
	}

	if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
		$query_vars['date_query'] = array(
			array(
				'year'     => (int) $matches[1],
				'monthnum' => (int) $matches[2],
			),
		);
	}

	if ( $filters['assoc'] > 0 ) {
		$query_vars['meta_query'][] = array(
			'key'   => 'organizer_association_id',
			'value' => (string) $filters['assoc'],
		);
	}

	if ( '' !== $filters['section'] ) {
		$query_vars['tax_query'][] = array(
			'taxonomy' => 'csi_content_section',
			'field'    => 'slug',
			'terms'    => $filters['section'],
		);
	}

	if ( '' !== $filters['doc_type'] ) {
		$query_vars['meta_query'][] = array(
			'key'   => '_csi_content_hub_file_ext',
			'value' => (string) $filters['doc_type'],
		);
	}

	return $query_vars;
}

/**
 * Filter the main query for content entries based on URL parameters.
 *
 * @param WP_Query $query
 * @return void
 */
function culturacsi_content_hub_search_filter_main_query( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$filters = culturacsi_content_hub_filters_from_request();
	$has_filters = ( '' !== $filters['q'] ) || ( '' !== $filters['date'] ) || ( $filters['author'] > 0 ) || ( $filters['assoc'] > 0 ) || ( '' !== $filters['section'] ) || ( '' !== $filters['doc_type'] );
	if ( ! $has_filters ) {
		return;
	}

	$post_type = $query->get( 'post_type' );
	$is_target =
		( is_string( $post_type ) && CULTURACSI_CONTENT_HUB_POST_TYPE === $post_type ) ||
		( is_array( $post_type ) && in_array( CULTURACSI_CONTENT_HUB_POST_TYPE, $post_type, true ) );

	if ( ! $is_target ) {
		return;
	}

	$query_vars = culturacsi_content_hub_apply_filters_to_query_vars( $query->query_vars, $filters );
	foreach ( $query_vars as $key => $value ) {
		$query->set( $key, $value );
	}
}
add_action( 'pre_get_posts', 'culturacsi_content_hub_search_filter_main_query', 20 );

/**
 * Filter Query Loop blocks for content entries.
 *
 * @param array    $query
 * @param WP_Block $block
 * @param int      $page
 * @return array
 */
function culturacsi_content_hub_search_filter_query_loop_vars( $query, $block = null, $page = null ) {
	if ( is_admin() ) {
		return $query;
	}

	$filters = culturacsi_content_hub_filters_from_request();
	$has_filters = ( '' !== $filters['q'] ) || ( '' !== $filters['date'] ) || ( $filters['author'] > 0 ) || ( $filters['assoc'] > 0 ) || ( '' !== $filters['section'] ) || ( '' !== $filters['doc_type'] );
	if ( ! $has_filters ) {
		return $query;
	}

	$post_type = isset( $query['post_type'] ) ? $query['post_type'] : 'post';
	$is_target = false;
	if ( is_string( $post_type ) ) {
		$is_target = ( CULTURACSI_CONTENT_HUB_POST_TYPE === $post_type || 0 === strpos( $post_type, 'ch_s_' ) );
	} elseif ( is_array( $post_type ) ) {
		foreach ( (array) $post_type as $pt ) {
			if ( CULTURACSI_CONTENT_HUB_POST_TYPE === $pt || 0 === strpos( (string) $pt, 'ch_s_' ) ) {
				$is_target = true;
				break;
			}
		}
	}

	if ( ! $is_target ) {
		return $query;
	}

	return culturacsi_content_hub_apply_filters_to_query_vars( (array) $query, $filters );
}


add_filter( 'query_loop_block_query_vars', 'culturacsi_content_hub_search_filter_query_loop_vars', 20, 3 );
add_filter( 'kadence_blocks_post_grid_query_args', 'culturacsi_content_hub_search_filter_query_loop_vars', 20, 2 );
add_filter( 'kadence_blocks_posts_query_args', 'culturacsi_content_hub_search_filter_query_loop_vars', 20, 2 );
add_filter( 'kadence_blocks_pro_posts_grid_query_args', 'culturacsi_content_hub_search_filter_query_loop_vars', 20, 2 );


/**
 * Shortcode to render a search form for content hub entries.
 *
 * @param array $atts
 * @return string
 */
function culturacsi_content_hub_search_shortcode( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'title'      => '',
			'section'    => '',
			'placeholder' => __( 'Cerca...', 'culturacsi' ),
			'wrap_class' => '',
			'variant'    => '',
		),
		$atts,
		'culturacsi_content_search'
	);

	$filters    = culturacsi_content_hub_filters_from_request();
	$base_url   = get_permalink( get_queried_object_id() );
	$wrap_class = trim( (string) $atts['wrap_class'] );
	$section    = trim( (string) $atts['section'] );

	// Resolve title if empty and section provided.
	if ( '' === (string) $atts['title'] && '' !== $section ) {
		$term = get_term_by( 'slug', $section, 'csi_content_section' );
		if ( $term instanceof WP_Term ) {
			$atts['title'] = sprintf( __( 'Ricerca in %s', 'culturacsi' ), $term->name );
		}
	}

	$authors = get_users(
		array(
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'who'                 => 'authors',
			'has_published_posts' => array( CULTURACSI_CONTENT_HUB_POST_TYPE ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	);

	$associations = get_posts(
		array(
			'post_type'      => 'association',
			'post_status'    => array( 'publish', 'private' ),
			'posts_per_page' => 1000,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);

	$is_library     = ( 'library' === $atts['variant'] );
	$is_convenzioni = ( 'convenzioni' === $atts['variant'] );
	culturacsi_content_hub_enqueue_search_assets();

	ob_start();
	?>
	<div class="culturacsi-content-search <?php echo esc_attr( $wrap_class ); ?> <?php echo $is_library ? 'is-variant-library' : ''; ?> <?php echo $is_convenzioni ? 'is-variant-convenzioni' : ''; ?>">
		<div class="assoc-search-panel assoc-content-search">
			<div class="assoc-search-head">
				<div class="assoc-search-meta">
					<?php if ( '' !== trim( (string) $atts['title'] ) ) : ?>
						<h3 class="assoc-search-title"><?php echo esc_html( (string) $atts['title'] ); ?></h3>
					<?php endif; ?>
				</div>
				<p class="assoc-search-actions"><a class="button" href="<?php echo esc_url( $base_url ); ?>"><?php _e( 'Azzera', 'culturacsi' ); ?></a></p>
			</div>
			<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
				<p class="assoc-search-field is-q">
					<label for="ch_q"><?php _e( 'Cerca', 'culturacsi' ); ?></label>
					<input type="text" id="ch_q" name="ch_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>">
				</p>
				<p class="assoc-search-field is-date">
					<label for="ch_date"><?php _e( 'Data', 'culturacsi' ); ?></label>
					<input type="month" id="ch_date" name="ch_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
				</p>
				<?php if ( $is_library ) : ?>
					<p class="assoc-search-field is-doc-type">
						<label for="ch_doc_type"><?php _e( 'Tipo documento', 'culturacsi' ); ?></label>
						<select id="ch_doc_type" name="ch_doc_type">
							<option value=""><?php _e( 'Tutti', 'culturacsi' ); ?></option>
							<?php foreach ( culturacsi_content_hub_get_available_doc_types() as $type ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $filters['doc_type'], $type ); ?>><?php echo esc_html( $type ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php elseif ( ! $is_convenzioni ) : ?>
					<p class="assoc-search-field is-author">
						<label for="ch_author"><?php _e( 'Autore', 'culturacsi' ); ?></label>
						<select id="ch_author" name="ch_author">
							<option value="0"><?php _e( 'Tutti', 'culturacsi' ); ?></option>
							<?php foreach ( $authors as $author ) : ?>
								<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
					<p class="assoc-search-field is-association">
						<label for="ch_assoc"><?php _e( 'Associazione', 'culturacsi' ); ?></label>
						<select id="ch_assoc" name="ch_assoc">
							<option value="0"><?php _e( 'Tutte', 'culturacsi' ); ?></option>
							<?php foreach ( $associations as $assoc_id ) : ?>
								<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $filters['assoc'], (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php endif; ?>
				<?php if ( ! $is_library && ! $is_convenzioni && '' !== $section ) : ?>
					<input type="hidden" name="ch_section" value="<?php echo esc_attr( $section ); ?>">
				<?php endif; ?>
				<button type="submit" style="display:none;"></button>
			</form>
		</div>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'culturacsi_content_search', 'culturacsi_content_hub_search_shortcode' );

/**
 * Register convenience shortcodes for common content sections.
 *
 * @return void
 */
function culturacsi_content_hub_register_alias_shortcodes() {
	$aliases = array(
		'culturacsi_library'             => array(
			'section'        => 'library',
			'title'          => __( 'Biblioteca', 'culturacsi' ),
			'downloads_only' => 'yes',
			'is_library'     => 'yes',
		),
		'culturacsi_services'            => array(
			'section' => 'services',
			'title'   => __( 'Servizi CulturaCSI', 'culturacsi' ),
		),
		'culturacsi_convenzioni'         => array(
			'section' => 'convenzioni',
			'title'   => __( 'Convenzioni', 'culturacsi' ),
		),
		'culturacsi_formazione'          => array(
			'section' => 'formazione',
			'title'   => __( 'Formazione', 'culturacsi' ),
		),
		'culturacsi_progetti'            => array(
			'section' => 'progetti',
			'title'   => __( 'Progetti', 'culturacsi' ),
		),
		'culturacsi_infopoint_stranieri' => array(
			'section' => 'infopoint-stranieri',
			'title'   => __( 'Infopoint Stranieri', 'culturacsi' ),
		),
	);

	$generic_tags = array(
		'culturacsi_section_feed',
		'culturacsi_sezione_feed',
		'culturacsi_sezione_contenuti',
	);
	foreach ( $generic_tags as $tag ) {
		add_shortcode(
			$tag,
			static function( $atts ) {
				$atts = is_array( $atts ) ? $atts : array();
				return culturacsi_content_hub_shortcode( $atts );
			}
		);
	}

	foreach ( $aliases as $tag => $defaults ) {
		add_shortcode(
			$tag,
			static function( $atts ) use ( $defaults ) {
				$atts = is_array( $atts ) ? $atts : array();
				return culturacsi_content_hub_shortcode( array_merge( $defaults, $atts ) );
			}
		);

		// Also register a search alias: [culturacsi_XXX_search]
		$search_tag = $tag . '_search';
		if ( ! shortcode_exists( $search_tag ) ) {
			add_shortcode(
				$search_tag,
				static function( $atts ) use ( $defaults, $tag ) {
					$atts = is_array( $atts ) ? $atts : array();
					$slug = isset( $defaults['section'] ) ? (string) $defaults['section'] : '';
					$atts['section'] = $slug;
					if ( 'culturacsi_library' === $tag ) {
						$atts['variant'] = 'library';
					} elseif ( 'culturacsi_convenzioni' === $tag ) {
						$atts['variant'] = 'convenzioni';
					}
					return culturacsi_content_hub_search_shortcode( $atts );
				}
			);
		}
	}

	// Dynamic aliases for every current and future section.
	$section_identifiers = culturacsi_content_hub_section_identifiers();
	foreach ( $section_identifiers as $section_data ) {
		$tag     = isset( $section_data['shortcode'] ) ? sanitize_key( (string) $section_data['shortcode'] ) : '';
		$slug    = isset( $section_data['slug'] ) ? sanitize_title( (string) $section_data['slug'] ) : '';
		$label   = isset( $section_data['label'] ) ? sanitize_text_field( (string) $section_data['label'] ) : '';
		if ( '' === $tag || '' === $slug || shortcode_exists( $tag ) ) {
			continue;
		}

		$defaults = array(
			'section' => $slug,
			'title'   => $label,
		);
		add_shortcode(
			$tag,
			static function( $atts ) use ( $defaults ) {
				$atts = is_array( $atts ) ? $atts : array();
				return culturacsi_content_hub_shortcode( array_merge( $defaults, $atts ) );
			}
		);

		// Also register a search alias: [culturacsi_section_XXX_search]
		$search_tag = $tag . '_search';
		if ( ! shortcode_exists( $search_tag ) ) {
			add_shortcode(
				$search_tag,
				static function( $atts ) use ( $slug ) {
					$atts = is_array( $atts ) ? $atts : array();
					$atts['section'] = $slug;
					if ( 'library' === $slug ) {
						$atts['variant'] = 'library';
					} elseif ( 'convenzioni' === $slug ) {
						$atts['variant'] = 'convenzioni';
					}
					return culturacsi_content_hub_search_shortcode( $atts );
				}
			);
		}
	}
}
add_action( 'init', 'culturacsi_content_hub_register_alias_shortcodes', 30 );

