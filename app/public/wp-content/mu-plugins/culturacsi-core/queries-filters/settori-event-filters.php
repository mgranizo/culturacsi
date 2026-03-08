<?php
/**
 * Settori-driven Event query filtering.
 *
 * Responsibilities:
 * - Read the selected Settori activity path from request query vars.
 * - Resolve that path to activity_category term IDs.
 * - Apply the resulting taxonomy constraint to Event archives and Query Loops.
 *
 * Why separate:
 * this is taxonomy/query logic only. It should stay independent from public News
 * search logic and permalink behavior.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read Settori activity filters from the request.
 *
 * Multiple query key aliases are supported because different templates and
 * legacy URLs feed the same filtering system.
 */
function culturacsi_settori_activity_filters_from_request(): array {
	static $filters = null;
	if ( null !== $filters ) {
		return $filters;
	}

	$qkey_macro    = function_exists( 'ab_qkey' ) ? (string) ab_qkey( 'settori', 'macro' ) : 'settori_macro';
	$qkey_settore  = function_exists( 'ab_qkey' ) ? (string) ab_qkey( 'settori', 'settore' ) : 'settori_settore';
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

	// Normalize labels through the activity-tree helpers when available so
	// different label variants resolve to one canonical path.
	if ( function_exists( 'ab_sync_canonical_tree_label' ) ) {
		$macro    = (string) ab_sync_canonical_tree_label( 'macro', $macro );
		$settore  = (string) ab_sync_canonical_tree_label( 'settore', $settore );
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
 * Convert a selected Settori path into activity_category term IDs.
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
 * Apply Settori-selected activity filters to Event query vars.
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
 * Constrain the main Event archive when Settori activity filters are present.
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
 * Constrain Query Loop / Kadence Event blocks with the same Settori selection.
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
