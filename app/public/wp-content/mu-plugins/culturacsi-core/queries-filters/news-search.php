<?php
/**
 * Public News search system.
 *
 * Responsibilities:
 * - read public filter values from the request,
 * - cache supporting datasets used by the search form,
 * - render the public search shortcode,
 * - apply filters to the main query and Query Loop blocks,
 * - maintain association→news cache invalidation.
 *
 * Debugging guide:
 * - "form shows wrong values" -> check request parsing below.
 * - "results are stale" -> check cache busting hooks below.
 * - "search form renders wrong" -> check shortcode output and assets module.
 * - "URL filters not affecting archive" -> check main-query/query-loop hooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read public News search filters from the current request.
 *
 * Multiple query key aliases are supported because the same filtering logic is
 * reused across public archives, content-hub-style pages and older URLs.
 */
function culturacsi_news_filters_from_request(): array {
	static $filters = null;
	if ( null !== $filters ) {
		return $filters;
	}

	$filters = array(
		'q'      => isset( $_GET['news_q'] ) ? sanitize_text_field( wp_unslash( $_GET['news_q'] ) ) : ( isset( $_GET['ch_q'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_q'] ) ) : ( isset( $_GET['a_q'] ) ? sanitize_text_field( wp_unslash( $_GET['a_q'] ) ) : '' ) ),
		'author' => isset( $_GET['news_author'] ) ? absint( $_GET['news_author'] ) : ( isset( $_GET['ch_author'] ) ? absint( $_GET['ch_author'] ) : 0 ),
		'date'   => isset( $_GET['news_date'] ) ? sanitize_text_field( wp_unslash( $_GET['news_date'] ) ) : ( isset( $_GET['ch_date'] ) ? sanitize_text_field( wp_unslash( $_GET['ch_date'] ) ) : '' ),
		'assoc'  => isset( $_GET['news_assoc'] ) ? absint( $_GET['news_assoc'] ) : ( isset( $_GET['ch_assoc'] ) ? absint( $_GET['ch_assoc'] ) : 0 ),
	);

	return $filters;
}

/**
 * Resolve all News post IDs linked to a specific association.
 *
 * Cached because this is a relationship lookup used repeatedly by public filters
 * and the underlying association/news mapping changes relatively infrequently.
 */
function culturacsi_news_get_association_post_ids( int $assoc_id ): array {
	if ( $assoc_id <= 0 ) {
		return array();
	}

	$cache_key  = 'culturacsi_news_assoc_posts_' . $assoc_id;
	$cached_ids = get_transient( $cache_key );
	if ( is_array( $cached_ids ) ) {
		return $cached_ids;
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

	$result = array_values( array_unique( array_map( 'intval', array_merge( (array) $ids_from_meta, (array) $ids_from_authors ) ) ) );

	set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

	return $result;
}

/**
 * Clear cached association→news relationships when a News post is saved/deleted.
 */
function culturacsi_news_assoc_posts_bust_cache( int $post_id ): void {
	$assoc_id = (int) get_post_meta( $post_id, 'organizer_association_id', true );
	if ( $assoc_id > 0 ) {
		delete_transient( 'culturacsi_news_assoc_posts_' . $assoc_id );
	}
}
add_action( 'save_post_news', 'culturacsi_news_assoc_posts_bust_cache' );
add_action( 'before_delete_post', 'culturacsi_news_assoc_posts_bust_cache' );

/**
 * Capture the old association before organizer_association_id changes so the old
 * cache bucket can be invalidated after the update completes.
 */
function culturacsi_news_assoc_posts_capture_old_meta( $meta_id, int $post_id, string $meta_key ): void {
	unset( $meta_id );

	if ( 'organizer_association_id' !== $meta_key ) {
		return;
	}

	$old_assoc = (int) get_post_meta( $post_id, 'organizer_association_id', true );
	if ( $old_assoc > 0 ) {
		$GLOBALS['_culturacsi_old_assoc_id'][ $post_id ] = $old_assoc;
	}
}
add_action( 'pre_update_post_meta', 'culturacsi_news_assoc_posts_capture_old_meta', 10, 3 );

/**
 * Clear both the old and new association cache buckets after organizer_association_id changes.
 */
function culturacsi_news_assoc_posts_bust_cache_on_meta( $meta_id, int $post_id, string $meta_key, $new_value ): void {
	unset( $meta_id );

	if ( 'organizer_association_id' !== $meta_key ) {
		return;
	}

	$old_assoc = isset( $GLOBALS['_culturacsi_old_assoc_id'][ $post_id ] )
		? (int) $GLOBALS['_culturacsi_old_assoc_id'][ $post_id ]
		: 0;

	if ( $old_assoc > 0 ) {
		delete_transient( 'culturacsi_news_assoc_posts_' . $old_assoc );
		unset( $GLOBALS['_culturacsi_old_assoc_id'][ $post_id ] );
	}

	$new_assoc = (int) $new_value;
	if ( $new_assoc > 0 && $new_assoc !== $old_assoc ) {
		delete_transient( 'culturacsi_news_assoc_posts_' . $new_assoc );
	}
}
add_action( 'updated_post_meta', 'culturacsi_news_assoc_posts_bust_cache_on_meta', 10, 4 );
add_action( 'added_post_meta', 'culturacsi_news_assoc_posts_bust_cache_on_meta', 10, 4 );

/**
 * Cached author list for the public News search form.
 *
 * Kept as a helper to make the shortcode renderer easier to scan.
 *
 * @return array<int,WP_User>
 */
function culturacsi_news_search_authors(): array {
	$cache_key = 'culturacsi_news_authors';
	$authors   = get_transient( $cache_key );
	if ( false !== $authors ) {
		return is_array( $authors ) ? $authors : array();
	}

	$authors = get_users(
		array(
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'who'                 => 'authors',
			'has_published_posts' => array( 'news' ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	);

	set_transient( $cache_key, $authors, HOUR_IN_SECONDS );

	return is_array( $authors ) ? $authors : array();
}

/**
 * Cached association list for the public News search form.
 *
 * @return array<int,int> Association post IDs.
 */
function culturacsi_news_search_associations(): array {
	$cache_key    = 'culturacsi_associations_list';
	$associations = get_transient( $cache_key );
	if ( false !== $associations ) {
		return is_array( $associations ) ? array_map( 'intval', $associations ) : array();
	}

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

	set_transient( $cache_key, $associations, HOUR_IN_SECONDS );

	return is_array( $associations ) ? array_map( 'intval', $associations ) : array();
}

/**
 * Public News search shortcode.
 *
 * Reserved-area requests are delegated to the portal implementation so the public
 * and private search UIs can evolve independently.
 */
function culturacsi_news_search_shortcode( array $atts = array() ): string {
	$path = function_exists( 'culturacsi_routing_current_path' )
		? culturacsi_routing_current_path()
		: trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
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

	$filters      = culturacsi_news_filters_from_request();
	$authors      = culturacsi_news_search_authors();
	$associations = culturacsi_news_search_associations();
	$base_url     = get_permalink( get_queried_object_id() );
	$wrap_class   = trim( (string) $atts['wrap_class'] );

	ob_start();
	?>
	<div class="culturacsi-news-search <?php echo esc_attr( $wrap_class ); ?>">
		<div class="assoc-search-panel assoc-news-search">
			<div class="assoc-search-head">
				<div class="assoc-search-meta">
					<?php if ( '' !== trim( (string) $atts['title'] ) ) : ?>
						<h3 class="assoc-search-title"><?php echo esc_html( (string) $atts['title'] ); ?></h3>
					<?php endif; ?>
				</div>
				<p class="assoc-search-actions"><a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a></p>
			</div>
			<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form" data-csi-news-autosubmit="1">
				<p class="assoc-search-field is-q">
					<label for="news_q">Cerca</label>
					<input type="text" id="news_q" name="news_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Titolo o contenuto">
				</p>
				<p class="assoc-search-field is-date">
					<label for="news_date">Data</label>
					<input type="month" id="news_date" name="news_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
				</p>
				<p class="assoc-search-field is-author">
					<label for="news_author">Autore</label>
					<select id="news_author" name="news_author">
						<option value="0">Tutti</option>
						<?php foreach ( $authors as $author ) : ?>
							<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="assoc-search-field is-association">
					<label for="news_assoc">Associazione</label>
					<select id="news_assoc" name="news_assoc">
						<option value="0">Tutte</option>
						<?php foreach ( $associations as $assoc_id ) : ?>
							<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $filters['assoc'], (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</form>
		</div>
	</div>
	<?php

	return (string) ob_get_clean();
}
add_shortcode( 'culturacsi_news_search', 'culturacsi_news_search_shortcode' );

/**
 * Apply request filters to a News query vars array.
 *
 * This helper is shared by both main-query and query-loop integration points so
 * the filter semantics stay identical in both contexts.
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
		$allowed_ids            = culturacsi_news_get_association_post_ids( (int) $filters['assoc'] );
		$query_vars['post__in'] = ! empty( $allowed_ids ) ? array_map( 'intval', $allowed_ids ) : array( 0 );
	}

	return $query_vars;
}

/**
 * Constrain the main public News archive when public filter query vars are present.
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
 * Constrain Query Loop / Kadence blocks set to post_type=news.
 */
function culturacsi_news_search_filter_query_loop_vars( array $query, $block = null, $page = null ): array {
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
		( is_array( $post_type ) && in_array( 'news', (array) $post_type, true ) );

	if ( ! $is_news_query ) {
		return $query;
	}

	return culturacsi_news_apply_public_filters_to_query_vars( $query, $filters );
}
add_filter( 'query_loop_block_query_vars', 'culturacsi_news_search_filter_query_loop_vars', 20 );
add_filter( 'kadence_blocks_posts_query_args', 'culturacsi_news_search_filter_query_loop_vars', 20 );
add_filter( 'kadence_blocks_pro_posts_grid_query_args', 'culturacsi_news_search_filter_query_loop_vars', 20 );
add_filter( 'kadence_blocks_post_grid_query_args', 'culturacsi_news_search_filter_query_loop_vars', 20 );
