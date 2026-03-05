<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Resolve external URL for a News post.
 *
 * Rules:
 * - only applies to post_type=news
 * - if `_hebeae_external_enabled` is explicitly "0", keep internal permalink
 * - if URL exists and is valid, prefer external URL
 */
function culturacsi_news_external_url_for_post( int $post_id ): string {
	if ( $post_id <= 0 ) {
		return '';
	}

	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'news' !== $post->post_type ) {
		return '';
	}

	$enabled = (string) get_post_meta( $post_id, '_hebeae_external_enabled', true );
	if ( '0' === $enabled ) {
		return '';
	}

	$url = trim( (string) get_post_meta( $post_id, '_hebeae_external_url', true ) );
	if ( '' === $url ) {
		return '';
	}

	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = 'https://' . ltrim( $url, '/' );
	}

	$url = esc_url_raw( $url );
	return ( '' !== $url ) ? $url : '';
}

/**
 * Ensure News links in Query Loop/reusable blocks open original external URLs.
 */
function culturacsi_news_force_external_post_type_link( string $post_link, WP_Post $post, bool $leavename, bool $sample ): string {
	unset( $leavename );
	if ( $sample || ! ( $post instanceof WP_Post ) || 'news' !== $post->post_type ) {
		return $post_link;
	}

	$external = culturacsi_news_external_url_for_post( (int) $post->ID );
	return ( '' !== $external ) ? $external : $post_link;
}
add_filter( 'post_type_link', 'culturacsi_news_force_external_post_type_link', 99, 4 );

/**
 * Fallback for contexts that use `the_permalink` directly.
 */
function culturacsi_news_force_external_the_permalink( string $permalink ): string {
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) || 'news' !== $post->post_type ) {
		return $permalink;
	}

	$external = culturacsi_news_external_url_for_post( (int) $post->ID );
	return ( '' !== $external ) ? $external : $permalink;
}
add_filter( 'the_permalink', 'culturacsi_news_force_external_the_permalink', 99 );

/**
 * Ensure Content Entries in certain sections (Formazione, Progetti, Convenzioni)
 * open their original external URLs globally.
 */
function culturacsi_content_entry_force_external_link( string $post_link, $post ): string {
	$post = get_post( $post );
	if ( ! ( $post instanceof WP_Post ) || 'csi_content_entry' !== $post->post_type ) {
		return $post_link;
	}

	// For Library posts, clicks are handled by a JS modal interceptor – but only
	// rewrite the URL in regular HTML page requests.  Feed, sitemap, REST API,
	// and other non-HTML contexts must keep the real permalink so sitemaps,
	// canonical tags, and RSS entries are not corrupted.
	if ( has_term( 'library', 'csi_content_section', $post ) || has_term( 'biblioteca', 'csi_content_section', $post ) || has_term( 'document-library', 'csi_content_section', $post ) ) {
		$is_rest    = defined( 'REST_REQUEST' ) && REST_REQUEST;
		$is_feed    = function_exists( 'is_feed' ) && is_feed();
		$is_sitemap = function_exists( 'is_sitemap' ) && is_sitemap();
		if ( ! $is_rest && ! $is_feed && ! $is_sitemap ) {
			return '#csi-modal-post-' . $post->ID;
		}
		return $post_link;
	}

	$external = trim( (string) get_post_meta( $post->ID, '_csi_content_hub_external_url', true ) );
	if ( '' !== $external ) {
		if ( ! preg_match( '#^https?://#i', $external ) ) {
			$external = 'https://' . ltrim( $external, '/' );
		}
		return esc_url( $external );
	}

	return $post_link;
}
add_filter( 'post_type_link', 'culturacsi_content_entry_force_external_link', 99, 2 );

/**
 * Fallback for the_permalink() on Content Entries.
 */
function culturacsi_content_entry_force_external_the_permalink( string $permalink ): string {
	$post = get_post();
	if ( ! ( $post instanceof WP_Post ) || 'csi_content_entry' !== $post->post_type ) {
		return $permalink;
	}

	return culturacsi_content_entry_force_external_link( $permalink, $post );
}
add_filter( 'the_permalink', 'culturacsi_content_entry_force_external_the_permalink', 99 );


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


function culturacsi_news_get_association_post_ids( int $assoc_id ): array {
	if ( $assoc_id <= 0 ) {
		return array();
	}

	// OPTIMIZATION: Cache the result for this association
	$cache_key = 'culturacsi_news_assoc_posts_' . $assoc_id;
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
	
	// Cache for 5 minutes - association/news relationships don't change often
	set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
	
	return $result;
}

/**
 * Bust the association→news post IDs transient cache when a news post is saved or deleted.
 * This prevents stale filter results after an admin publishes, removes, or re-assigns a news post.
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
 * Capture the old organizer_association_id before a meta update so we can bust its cache too.
 */
function culturacsi_news_assoc_posts_capture_old_meta( $meta_id, int $post_id, string $meta_key ): void {
	if ( 'organizer_association_id' !== $meta_key ) {
		return;
	}
	$old_assoc = (int) get_post_meta( $post_id, 'organizer_association_id', true );
	if ( $old_assoc > 0 ) {
		// Store keyed by post_id so the post-update hook can read it.
		$GLOBALS['_culturacsi_old_assoc_id'][ $post_id ] = $old_assoc;
	}
}
add_action( 'pre_update_post_meta',  'culturacsi_news_assoc_posts_capture_old_meta', 10, 3 );

/**
 * Bust the cache for both the old and new association when organizer_association_id is updated.
 */
function culturacsi_news_assoc_posts_bust_cache_on_meta( $meta_id, int $post_id, string $meta_key, $new_value ): void {
	if ( 'organizer_association_id' !== $meta_key ) {
		return;
	}
	// Bust old association cache (captured before the update).
	$old_assoc = isset( $GLOBALS['_culturacsi_old_assoc_id'][ $post_id ] )
		? (int) $GLOBALS['_culturacsi_old_assoc_id'][ $post_id ]
		: 0;
	if ( $old_assoc > 0 ) {
		delete_transient( 'culturacsi_news_assoc_posts_' . $old_assoc );
		unset( $GLOBALS['_culturacsi_old_assoc_id'][ $post_id ] );
	}
	// Bust new association cache.
	$new_assoc = (int) $new_value;
	if ( $new_assoc > 0 && $new_assoc !== $old_assoc ) {
		delete_transient( 'culturacsi_news_assoc_posts_' . $new_assoc );
	}
}
add_action( 'updated_post_meta', 'culturacsi_news_assoc_posts_bust_cache_on_meta', 10, 4 );
add_action( 'added_post_meta',   'culturacsi_news_assoc_posts_bust_cache_on_meta', 10, 4 );

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

	// OPTIMIZATION: Cache authors and associations for search form
	$authors_cache_key = 'culturacsi_news_authors';
	$authors = get_transient( $authors_cache_key );
	if ( false === $authors ) {
		$authors = get_users(
			array(
				'orderby'             => 'display_name',
				'order'               => 'ASC',
				'who'                 => 'authors',
				'has_published_posts' => array( 'news' ),
				'fields'              => array( 'ID', 'display_name' ),
			)
		);
		// Cache for 1 hour - authors don't change often
		set_transient( $authors_cache_key, $authors, HOUR_IN_SECONDS );
	}

	$associations_cache_key = 'culturacsi_associations_list';
	$associations = get_transient( $associations_cache_key );
	if ( false === $associations ) {
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
		// Cache for 1 hour - associations don't change often
		set_transient( $associations_cache_key, $associations, HOUR_IN_SECONDS );
	}
	$base_url      = get_permalink( get_queried_object_id() );
	$wrap_class    = trim( (string) $atts['wrap_class'] );

	ob_start();
	?>
	<div class="culturacsi-news-search <?php echo esc_attr( $wrap_class ); ?>">
		<div class="assoc-search-panel assoc-news-search">
			<style>
			/* Ensure public Notizie search matches Calendar layout */
						.assoc-news-search .assoc-search-form{display:grid;grid-auto-flow:row;gap:10px 10px;align-items:end;grid-template-columns:repeat(4,minmax(0,1fr))}
			.assoc-news-search .assoc-search-field{margin:0;grid-row:1;min-width:0}
						.assoc-news-search .assoc-search-field input,
						.assoc-news-search .assoc-search-field select{
							width:100%;
							min-height:44px;
							padding:7px 10px;
							border:1px solid #c7d3e4;
							border-radius:8px; /* match other pages */
							background:#fff;
						}
			.assoc-news-search .assoc-search-field.is-q{grid-column:1}
			.assoc-news-search .assoc-search-field.is-date{grid-column:2}
						.assoc-news-search .assoc-search-field.is-author{grid-column:3}
						.assoc-news-search .assoc-search-field.is-association{grid-column:4}
			@media (max-width: 719px){
			  .assoc-news-search .assoc-search-form{grid-template-columns:minmax(0,1fr)}
			  .assoc-news-search .assoc-search-field{grid-column:1 / -1;grid-row:auto}
			}
			</style>
			<div class="assoc-search-head">
				<div class="assoc-search-meta">
					<?php if ( '' !== trim( (string) $atts['title'] ) ) : ?>
						<h3 class="assoc-search-title"><?php echo esc_html( (string) $atts['title'] ); ?></h3>
					<?php endif; ?>
				</div>
				<p class="assoc-search-actions"><a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a></p>
			</div>
			<form method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
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
function culturacsi_news_search_filter_query_loop_vars( array $query, $block = null, $page = null ): array {
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

/**
 * Read Settori activity filters from URL query args (macro/settore/settore2).
 */
function culturacsi_settori_activity_filters_from_request(): array {
	static $filters = null;
	if ( null !== $filters ) {
		return $filters;
	}

	$qkey_macro   = function_exists( 'ab_qkey' ) ? (string) ab_qkey( 'settori', 'macro' ) : 'settori_macro';
	$qkey_settore = function_exists( 'ab_qkey' ) ? (string) ab_qkey( 'settori', 'settore' ) : 'settori_settore';
	$qkey_settore2 = function_exists( 'ab_qkey' ) ? (string) ab_qkey( 'settori', 'settore2' ) : 'settori_settore2';

	$read_first = static function( array $keys ): string {
		foreach ( $keys as $key ) {
			$key = trim( (string) $key );
			if ( '' === $key || ! isset( $_GET[ $key ] ) ) {
				continue;
			}
			$value = sanitize_text_field( wp_unslash( $_GET[ $key ] ) );
			if ( '' !== $value ) {
				return $value;
			}
		}
		return '';
	};

	$macro = $read_first(
		array_unique(
			array_filter(
				array( $qkey_macro, 'settori_macro', 'a_macro', 'ch_macro' )
			)
		)
	);
	$settore = $read_first(
		array_unique(
			array_filter(
				array( $qkey_settore, 'settori_settore', 'a_settore', 'ch_settore', 'ch_section' )
			)
		)
	);
	$settore2 = $read_first(
		array_unique(
			array_filter(
				array( $qkey_settore2, 'settori_settore2', 'a_settore2', 'ch_settore2' )
			)
		)
	);

	if ( function_exists( 'ab_sync_canonical_tree_label' ) ) {
		$macro   = (string) ab_sync_canonical_tree_label( 'macro', $macro );
		$settore = (string) ab_sync_canonical_tree_label( 'settore', $settore );
		$settore2 = (string) ab_sync_canonical_tree_label( 'settore2', $settore2 );
	}
	if ( function_exists( 'ab_sync_resolve_levels_from_tree' ) ) {
		$resolved = (array) ab_sync_resolve_levels_from_tree( $macro, $settore, $settore2 );
		$macro    = (string) ( $resolved[0] ?? $macro );
		$settore  = (string) ( $resolved[1] ?? $settore );
		$settore2 = (string) ( $resolved[2] ?? $settore2 );
	}
	if ( function_exists( 'abf_apply_tree_rules_to_segments' ) ) {
		$resolved = (array) abf_apply_tree_rules_to_segments( $macro, $settore, $settore2 );
		$macro    = (string) ( $resolved[0] ?? $macro );
		$settore  = (string) ( $resolved[1] ?? $settore );
		$settore2 = (string) ( $resolved[2] ?? $settore2 );
	}

	$filters = array(
		'macro'    => trim( $macro ),
		'settore'  => trim( $settore ),
		'settore2' => trim( $settore2 ),
	);
	return $filters;
}

/**
 * Resolve filtered activity term IDs from selected Settori activity path.
 */
function culturacsi_settori_filtered_activity_term_ids( array $filters ): array {
	$has_activity_filter = ( '' !== (string) ( $filters['macro'] ?? '' ) )
		|| ( '' !== (string) ( $filters['settore'] ?? '' ) )
		|| ( '' !== (string) ( $filters['settore2'] ?? '' ) );

	if ( ! $has_activity_filter || ! taxonomy_exists( 'activity_category' ) ) {
		return array();
	}

	$segments = array();
	if ( '' !== (string) $filters['macro'] ) {
		$segments[] = (string) $filters['macro'];
	}
	if ( '' !== (string) $filters['settore'] ) {
		$segments[] = (string) $filters['settore'];
	}
	if ( '' !== (string) $filters['settore2'] ) {
		$segments[] = (string) $filters['settore2'];
	}

	if ( empty( $segments ) ) {
		return array();
	}

	$path = implode( ' > ', $segments );
	if ( '' === trim( $path ) ) {
		return array();
	}

	$term_ids = array();
	if ( function_exists( 'culturacsi_activity_tree_term_ids_from_paths' ) ) {
		$tree_term_ids = culturacsi_activity_tree_term_ids_from_paths( array( $path ), true );
		if ( is_array( $tree_term_ids ) ) {
			$term_ids = $tree_term_ids;
		}
	}

	$term_ids = array_values(
		array_unique(
			array_filter(
				array_map( 'intval', (array) $term_ids ),
				static function( int $id ): bool {
					return $id > 0;
				}
			)
		)
	);

	return ! empty( $term_ids ) ? $term_ids : array( 0 );
}

/**
 * Apply Settori-selected activity filters to Event queries.
 */
function culturacsi_settori_apply_event_filters_to_query_vars( array $query_vars ): array {
	$filters = culturacsi_settori_activity_filters_from_request();
	$has_activity_filter = ( '' !== $filters['macro'] ) || ( '' !== $filters['settore'] ) || ( '' !== $filters['settore2'] );
	if ( ! $has_activity_filter ) {
		return $query_vars;
	}

	$post_type = $query_vars['post_type'] ?? 'post';
	$is_event_query =
		( is_string( $post_type ) && 'event' === $post_type ) ||
		( is_array( $post_type ) && in_array( 'event', array_map( 'strval', $post_type ), true ) );

	if ( ! $is_event_query ) {
		return $query_vars;
	}

	$term_ids = culturacsi_settori_filtered_activity_term_ids( $filters );
	if ( empty( $term_ids ) ) {
		return $query_vars;
	}

	$tax_clause = array(
		'taxonomy'         => 'activity_category',
		'field'            => 'term_id',
		'terms'            => array_values( array_map( 'intval', $term_ids ) ),
		'include_children' => true,
		'operator'         => 'IN',
	);

	$tax_query = $query_vars['tax_query'] ?? array();
	if ( ! is_array( $tax_query ) ) {
		$tax_query = array();
	}
	$tax_query[] = $tax_clause;
	$query_vars['tax_query'] = $tax_query;

	return $query_vars;
}

/**
 * Constrain main Event archives when Settori filters are in URL.
 */
function culturacsi_settori_event_filter_main_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	$post_type = $query->get( 'post_type' );
	$is_event_query =
		$query->is_post_type_archive( 'event' ) ||
		( is_string( $post_type ) && 'event' === $post_type ) ||
		( is_array( $post_type ) && in_array( 'event', $post_type, true ) );

	if ( ! $is_event_query ) {
		return;
	}

	$next_vars = culturacsi_settori_apply_event_filters_to_query_vars( $query->query_vars );
	foreach ( $next_vars as $key => $value ) {
		$query->set( $key, $value );
	}
}
add_action( 'pre_get_posts', 'culturacsi_settori_event_filter_main_query', 21 );

/**
 * Constrain Query Loop/Kadence Event blocks with Settori activity selection.
 */
function culturacsi_settori_event_filter_query_loop_vars( array $query, $block = null, $page = null ): array {
	unset( $block, $page );
	if ( is_admin() ) {
		return $query;
	}
	return culturacsi_settori_apply_event_filters_to_query_vars( $query );
}
add_filter( 'query_loop_block_query_vars', 'culturacsi_settori_event_filter_query_loop_vars', 21, 3 );
add_filter( 'kadence_blocks_posts_query_args', 'culturacsi_settori_event_filter_query_loop_vars', 21, 2 );
add_filter( 'kadence_blocks_pro_posts_grid_query_args', 'culturacsi_settori_event_filter_query_loop_vars', 21, 2 );
add_filter( 'kadence_blocks_post_grid_query_args', 'culturacsi_settori_event_filter_query_loop_vars', 21, 2 );




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
			const publicSearch = document.querySelector('.culturacsi-news-search .assoc-search-form');
			if (publicSearch) {
				const inputs = publicSearch.querySelectorAll('input, select');
				let debounceTimer;
				inputs.forEach(input => {
					if (input.type === 'text' || input.type === 'month') {
						input.addEventListener('input', () => {
							clearTimeout(debounceTimer);
							debounceTimer = setTimeout(() => publicSearch.submit(), 600);
						});
					} else {
						input.addEventListener('change', () => publicSearch.submit());
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
