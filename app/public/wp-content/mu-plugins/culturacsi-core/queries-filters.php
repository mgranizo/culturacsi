<?php
if ( ! defined( 'ABSPATH' ) ) exit;
function culturacsi_news_filters_from_request(): array {
	static $filters = null;
	if ( null !== $filters ) {
		return $filters;
	}
	$filters = array(
		'q'      => isset( $_GET['news_q'] ) ? sanitize_text_field( wp_unslash( $_GET['news_q'] ) ) : '',
		'author' => isset( $_GET['news_author'] ) ? absint( $_GET['news_author'] ) : 0,
		'date'   => isset( $_GET['news_date'] ) ? sanitize_text_field( wp_unslash( $_GET['news_date'] ) ) : '',
		'assoc'  => isset( $_GET['news_assoc'] ) ? absint( $_GET['news_assoc'] ) : 0,
	);
	return $filters;
}

function culturacsi_news_get_association_post_ids( int $assoc_id ): array {
	if ( $assoc_id <= 0 ) {
		return array();
	}

	$ids_from_meta = get_posts(
		array(
			'post_type'      => 'news',
			'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => 'organizer_association_id',
					'value' => (string) $assoc_id,
				),
			),
		)
	);

	$user_ids = get_users(
		array(
			'fields'      => 'ID',
			'meta_key'    => 'association_post_id',
			'meta_value'  => (string) $assoc_id,
			'number'      => 500,
			'count_total' => false,
		)
	);

	$ids_from_authors = array();
	if ( ! empty( $user_ids ) ) {
		$ids_from_authors = get_posts(
			array(
				'post_type'      => 'news',
				'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'author__in'     => array_map( 'intval', (array) $user_ids ),
			)
		);
	}

	return array_values( array_unique( array_map( 'intval', array_merge( (array) $ids_from_meta, (array) $ids_from_authors ) ) ) );
}

function culturacsi_news_search_shortcode( array $atts = array() ): string {
	$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	if ( 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' ) ) {
		return culturacsi_news_panel_search_shortcode( $atts );
	}

	$atts = shortcode_atts(
		array(
			'title'      => 'Ricerca Notizie',
			'wrap_class' => '',
		),
		$atts,
		'culturacsi_news_search'
	);

	$filters = culturacsi_news_filters_from_request();

	$authors = get_users(
		array(
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'who'                 => 'authors',
			'has_published_posts' => array( 'news' ),
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
	$base_url      = get_permalink( get_queried_object_id() );
	$wrap_class    = trim( (string) $atts['wrap_class'] );

	ob_start();
	?>
	<div class="culturacsi-news-search <?php echo esc_attr( $wrap_class ); ?>">
		<div class="culturacsi-news-search-panel">
			<?php if ( '' !== trim( (string) $atts['title'] ) ) : ?>
				<h3 class="culturacsi-news-search-title"><?php echo esc_html( (string) $atts['title'] ); ?></h3>
			<?php endif; ?>
			<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="culturacsi-news-search-form">
				<p class="culturacsi-news-field">
					<label for="news_q">Cerca</label>
					<input type="text" id="news_q" name="news_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Titolo o contenuto">
				</p>
				<p class="culturacsi-news-field">
					<label for="news_date">Data</label>
					<input type="month" id="news_date" name="news_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
				</p>
				<p class="culturacsi-news-field">
					<label for="news_author">Autore</label>
					<select id="news_author" name="news_author">
						<option value="0">Tutti</option>
						<?php foreach ( $authors as $author ) : ?>
							<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="culturacsi-news-field">
					<label for="news_assoc">Associazione</label>
					<select id="news_assoc" name="news_assoc">
						<option value="0">Tutte</option>
						<?php foreach ( $associations as $assoc_id ) : ?>
							<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $filters['assoc'], (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
					<p class="culturacsi-news-actions">
						<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
					</p>
				</form>
			</div>
		</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'culturacsi_news_search', 'culturacsi_news_search_shortcode' );

/**
 * Apply public news-search filters to a query vars array.
 */
function culturacsi_news_apply_public_filters_to_query_vars( array $query_vars, array $filters ): array {
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
		$allowed_ids = culturacsi_news_get_association_post_ids( (int) $filters['assoc'] );
		$query_vars['post__in'] = ! empty( $allowed_ids ) ? array_map( 'intval', $allowed_ids ) : array( 0 );
	}

	return $query_vars;
}

/**
 * Make URL filters from [culturacsi_news_search] constrain the main public news query.
 */
function culturacsi_news_search_filter_main_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$filters = culturacsi_news_filters_from_request();
	$has_filters = ( '' !== $filters['q'] ) || ( $filters['author'] > 0 ) || ( '' !== $filters['date'] ) || ( $filters['assoc'] > 0 );
	if ( ! $has_filters ) {
		return;
	}

	$post_type = $query->get( 'post_type' );
	$is_news_query =
		$query->is_post_type_archive( 'news' ) ||
		( is_string( $post_type ) && 'news' === $post_type ) ||
		( is_array( $post_type ) && in_array( 'news', $post_type, true ) );

	if ( ! $is_news_query ) {
		return;
	}

	$query_vars = culturacsi_news_apply_public_filters_to_query_vars( $query->query_vars, $filters );
	foreach ( $query_vars as $key => $value ) {
		$query->set( $key, $value );
	}
}
add_action( 'pre_get_posts', 'culturacsi_news_search_filter_main_query', 20 );

/**
 * Make URL filters from [culturacsi_news_search] constrain Query Loop blocks set to post_type=news.
 */
function culturacsi_news_search_filter_query_loop_vars( array $query, WP_Block $block, int $page ): array {
	unset( $block, $page );

	if ( is_admin() ) {
		return $query;
	}

	$filters = culturacsi_news_filters_from_request();
	$has_filters = ( '' !== $filters['q'] ) || ( $filters['author'] > 0 ) || ( '' !== $filters['date'] ) || ( $filters['assoc'] > 0 );
	if ( ! $has_filters ) {
		return $query;
	}

	$post_type = $query['post_type'] ?? 'post';
	$is_news_query =
		( is_string( $post_type ) && 'news' === $post_type ) ||
		( is_array( $post_type ) && in_array( 'news', $post_type, true ) );

	if ( ! $is_news_query ) {
		return $query;
	}

	return culturacsi_news_apply_public_filters_to_query_vars( $query, $filters );
}
add_filter( 'query_loop_block_query_vars', 'culturacsi_news_search_filter_query_loop_vars', 20, 3 );

function culturacsi_news_search_default_styles(): void {
	if ( is_admin() ) {
		return;
	}
	?>
	<style id="culturacsi-news-search-default">
		.culturacsi-news-search-panel{margin:0 0 1.25rem;padding:14px 16px;border:1px solid #d9e2ec;border-radius:12px;background:#f8fbff}
		.culturacsi-news-search-title{margin:0 0 10px}
		.culturacsi-news-search-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;align-items:end}
		.culturacsi-news-field{margin:0}
		.culturacsi-news-field label{display:block;font-weight:600;margin:0 0 4px}
		.culturacsi-news-field input,.culturacsi-news-field select{width:100%}
		.culturacsi-news-actions{margin:0;grid-column:1/-1;display:flex;gap:8px;justify-content:flex-start}
		.culturacsi-news-results{margin:0 0 1.5rem}
		.culturacsi-news-count{margin:0 0 10px;font-weight:700}
		.culturacsi-news-results-grid{display:grid;gap:12px}
		.culturacsi-news-item{padding:12px;border:1px solid #d9e2ec;border-radius:10px;background:#fff}
		.culturacsi-news-item-title{margin:0 0 6px;font-size:1.1rem;line-height:1.3}
		.culturacsi-news-item-meta{margin:0 0 8px;font-size:.9rem;color:#475569}
		.culturacsi-news-item-excerpt{margin:0}
		.culturacsi-news-empty{margin:0}
		@media (max-width: 920px){.culturacsi-news-search-form{grid-template-columns:repeat(2,minmax(0,1fr));}}
		@media (max-width: 640px){.culturacsi-news-search-form{grid-template-columns:minmax(0,1fr);}}
	</style>
	<script id="culturacsi-news-search-autosubmit">
		document.addEventListener('DOMContentLoaded', function() {
			const publicSearch = document.querySelector('.culturacsi-news-search-form');
			if (publicSearch) {
				const inputs = publicSearch.querySelectorAll('input, select');
				let debounceTimer;
				inputs.forEach(input => {
					input.addEventListener('change', () => publicSearch.submit());
					if (input.type === 'text') {
						input.addEventListener('input', () => {
							clearTimeout(debounceTimer);
							debounceTimer = setTimeout(() => publicSearch.submit(), 600);
						});
					}
				});
			}
		});
	</script>
	<?php
}
add_action( 'wp_head', 'culturacsi_news_search_default_styles', 9999 );

/**
 * Improve readability on News admin list with clearer zebra striping.
 */
function culturacsi_news_admin_zebra_rows(): void {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! ( $screen instanceof WP_Screen ) ) {
		return;
	}
	if ( 'edit-news' !== $screen->id ) {
		return;
	}
	?>
	<style id="culturacsi-news-admin-zebra">
		.post-type-news .wp-list-table.widefat tbody tr:nth-child(odd) > * {
			background-color: #f7fbff;
		}
		.post-type-news .wp-list-table.widefat tbody tr:nth-child(even) > * {
			background-color: #ffffff;
		}
		.post-type-news .wp-list-table.widefat tbody tr:hover > * {
			background-color: #eaf3ff !important;
		}
	</style>
	<?php
}
add_action( 'admin_head', 'culturacsi_news_admin_zebra_rows' );
