<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activity category and path utilities for the CulturaCSI portal.
 */

if ( ! function_exists( 'culturacsi_activity_paths_for_post' ) ) {
	/**
	 * Extracts all structured hierarchical paths for a post based on 'activity_category' terms
	 * or legacy CSV meta strings. Returns them sorted alphabetically.
	 *
	 * @param int $post_id The ID of the post.
	 * @return array A sorted array of hierarchical string paths (e.g., "Macro > Settore > Settore 2").
	 */
	function culturacsi_activity_paths_for_post( int $post_id ): array {
		if ( $post_id <= 0 ) {
			return array();
		}

		// Prefer canonical taxonomy assignments if available.
		if ( taxonomy_exists( 'activity_category' ) ) {
			$term_ids = wp_get_post_terms( $post_id, 'activity_category', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) && function_exists( 'culturacsi_activity_tree_term_index' ) ) {
				$index       = culturacsi_activity_tree_term_index();
				$path_map    = isset( $index['paths'] ) && is_array( $index['paths'] ) ? $index['paths'] : array();
				$term_id_set = array_flip( array_map( 'intval', $term_ids ) );
				$found_paths = array();
				foreach ( $path_map as $p => $tid ) {
					if ( isset( $term_id_set[ (int) $tid ] ) ) {
						$found_paths[] = (string) $p;
					}
				}
				if ( ! empty( $found_paths ) ) {
					usort(
						$found_paths,
						static function( string $a, string $b ): int {
							return strnatcasecmp( $a, $b );
						}
					);
					return $found_paths;
				}
			}
		}

		$remove_accents_safe = static function( string $str ): string {
			return function_exists( 'remove_accents' ) ? remove_accents( $str ) : $str;
		};

		$split_settore2 = static function( string $raw ) use ( $remove_accents_safe ): array {
			$raw = trim( $raw );
			if ( '' === $raw ) {
				return array();
			}
			if ( false === strpos( $raw, '/' ) ) {
				return array( $raw );
			}
			$parts = preg_split( '~/+~', $raw );
			if ( ! is_array( $parts ) ) {
				return array( $raw );
			}
			$out = array();
			foreach ( $parts as $part ) {
				$part = trim( (string) $part );
				if ( '' === $part ) {
					continue;
				}
				$key        = strtolower( $remove_accents_safe( $part ) );
				$out[ $key ] = $part;
			}
			return array_values( $out );
		};

		$macro   = trim( (string) get_post_meta( $post_id, '_ab_csv_macro', true ) );
		if ( '' === $macro ) {
			$macro = trim( (string) get_post_meta( $post_id, 'macro', true ) );
		}
		$settore = trim( (string) get_post_meta( $post_id, '_ab_csv_settore', true ) );
		if ( '' === $settore ) {
			$settore = trim( (string) get_post_meta( $post_id, 'settore', true ) );
		}
		$settore2_raw = trim( (string) get_post_meta( $post_id, '_ab_csv_settore2', true ) );
		if ( '' === $settore2_raw ) {
			$settore2_raw = trim( (string) get_post_meta( $post_id, 'settore2', true ) );
		}

		$paths_from_levels = array();
		if ( '' !== $macro || '' !== $settore || '' !== $settore2_raw ) {
			$tokens = $split_settore2( $settore2_raw );
			if ( empty( $tokens ) ) {
				$tokens = array( '' );
			}
			foreach ( $tokens as $token ) {
				$segments = array();
				if ( '' !== $macro ) {
					$segments[] = $macro;
				}
				if ( '' !== $settore ) {
					$segments[] = $settore;
				}
				if ( '' !== trim( (string) $token ) ) {
					$segments[] = trim( (string) $token );
				}
				if ( empty( $segments ) ) {
					continue;
				}
				$path      = implode( ' > ', $segments );
				$path_norm = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $path ) : $path );
				if ( '' === trim( $path_norm ) ) {
					continue;
				}
				$paths_from_levels[ $path_norm ] = $path;
			}
		}
		if ( ! empty( $paths_from_levels ) ) {
			$values = array_values( $paths_from_levels );
			usort(
				$values,
				static function( string $a, string $b ): int {
					return strnatcasecmp( $a, $b );
				}
			);
			return $values;
		}

		$csv_paths = array();
		$raw_csv   = trim( (string) get_post_meta( $post_id, '_ab_csv_all_categories', true ) );
		if ( '' === $raw_csv ) {
			$raw_csv = trim( (string) get_post_meta( $post_id, '_ab_csv_category', true ) );
		}
		if ( '' !== $raw_csv ) {
			$parts = preg_split( '/\s*\|\s*/', $raw_csv );
			if ( is_array( $parts ) ) {
				foreach ( $parts as $part_raw ) {
					$part = trim( (string) $part_raw );
					if ( '' === $part ) {
						continue;
					}
					$segments = array_values(
						array_filter(
							array_map(
								static function( string $seg ): string {
									return trim( $seg );
								},
								explode( '>', $part )
							),
							static function( string $seg ): bool {
								return '' !== $seg;
							}
						)
					);
					if ( empty( $segments ) ) {
						continue;
					}
					$path      = implode( ' > ', $segments );
					$path_norm = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $path ) : $path );
					if ( '' === trim( $path_norm ) ) {
						continue;
					}
					$csv_paths[ $path_norm ] = $path;
				}
			}
		}
		if ( ! empty( $csv_paths ) ) {
			$values = array_values( $csv_paths );
			usort(
				$values,
				static function( string $a, string $b ): int {
					return strnatcasecmp( $a, $b );
				}
			);
			return $values;
		}
		if ( ! taxonomy_exists( 'activity_category' ) ) {
			return array();
		}
		$terms = wp_get_post_terms(
			$post_id,
			'activity_category',
			array(
				'fields' => 'all',
			)
		);
		if ( is_wp_error( $terms ) || ! is_array( $terms ) || empty( $terms ) ) {
			return array();
		}

		$paths = array();
		foreach ( $terms as $term_obj ) {
			if ( ! ( $term_obj instanceof WP_Term ) ) {
				continue;
			}
			$lineage_ids   = array_reverse( get_ancestors( (int) $term_obj->term_id, 'activity_category', 'taxonomy' ) );
			$lineage_ids[] = (int) $term_obj->term_id;

			$parts = array();
			foreach ( $lineage_ids as $lineage_id ) {
				$lineage = get_term( (int) $lineage_id, 'activity_category' );
				if ( ! ( $lineage instanceof WP_Term ) ) {
					continue;
				}
				$name = trim( sanitize_text_field( (string) $lineage->name ) );
				if ( '' === $name ) {
					continue;
				}
				$parts[] = $name;
			}
			if ( empty( $parts ) ) {
				continue;
			}
			$path      = implode( ' > ', $parts );
			$path_norm = strtolower( $remove_accents_safe( $path ) );
			if ( '' === trim( $path_norm ) ) {
				continue;
			}
			$paths[ $path_norm ] = $path;
		}

		$values = array_values( $paths );
		usort(
			$values,
			static function( string $a, string $b ): int {
				return strnatcasecmp( $a, $b );
			}
		);
		return $values;
	}
}

if ( ! function_exists( 'culturacsi_activity_labels_for_post' ) ) {
	/**
	 * Resolves the specific leaf "activities" for a post directly from its paths.
	 * Settore 2 is preferred; Settore is used as a fallback if no Settore 2 exists.
	 *
	 * @param int $post_id The ID of the post.
	 * @return array A sorted array of activity labels (Settore/Settore 2 values).
	 */
	function culturacsi_activity_labels_for_post( int $post_id ): array {
		$remove_accents_safe = static function( string $str ): string {
			return function_exists( 'remove_accents' ) ? remove_accents( $str ) : $str;
		};

		$paths     = culturacsi_activity_paths_for_post( $post_id );
		$path_rows = array();
		foreach ( $paths as $path ) {
			$parts = array_values(
				array_filter(
					array_map(
						static function( string $part ): string {
							return trim( $part );
						},
						explode( '>', (string) $path )
					),
					static function( string $part ): bool {
						return '' !== $part;
					}
				)
			);
			if ( empty( $parts ) ) {
				continue;
			}
			$normalized_segments = array();
			foreach ( $parts as $segment ) {
				$normalized_segments[] = strtolower( $remove_accents_safe( trim( (string) $segment ) ) );
			}
			$key = implode( '>', $normalized_segments );
			if ( '' === trim( $key ) ) {
				continue;
			}
			$path_rows[ $key ] = array(
				'parts' => $parts,
				'norm'  => $normalized_segments,
			);
		}

		// Keep only deepest selections so checked macro/settore ancestors do not
		// appear as activities when a settore2 child is selected.
		$deepest_rows = array();
		foreach ( $path_rows as $path_key => $row ) {
			$is_ancestor = false;
			foreach ( $path_rows as $other_key => $other_row ) {
				if ( $path_key === $other_key ) {
					continue;
				}
				$current_norm = (array) ( $row['norm'] ?? array() );
				$other_norm   = (array) ( $other_row['norm'] ?? array() );
				if ( count( $current_norm ) >= count( $other_norm ) ) {
					continue;
				}
				$is_prefix = true;
				foreach ( $current_norm as $idx => $segment_key ) {
					if ( ! isset( $other_norm[ $idx ] ) || $other_norm[ $idx ] !== $segment_key ) {
						$is_prefix = false;
						break;
					}
				}
				if ( $is_prefix ) {
					$is_ancestor = true;
					break;
				}
			}
			if ( ! $is_ancestor ) {
				$deepest_rows[ $path_key ] = $row;
			}
		}

		$labels_s2 = array();
		$labels_s1 = array();
		foreach ( $deepest_rows as $row ) {
			$parts = (array) ( $row['parts'] ?? array() );
			if ( count( $parts ) >= 3 ) {
				$activity = trim( (string) $parts[2] ); // Settore 2.
				if ( '' !== $activity ) {
					$key = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $activity ) : $activity );
					if ( '' !== trim( $key ) ) {
						$labels_s2[ $key ] = $activity;
					}
				}
			} elseif ( count( $parts ) >= 2 ) {
				$activity = trim( (string) $parts[1] ); // Fallback to Settore.
				if ( '' !== $activity ) {
					$key = strtolower( function_exists( 'remove_accents' ) ? remove_accents( $activity ) : $activity );
					if ( '' !== trim( $key ) ) {
						$labels_s1[ $key ] = $activity;
					}
				}
			}
		}

		// Settore is a fallback for Settore 2. Only show Settore if no Settore 2 entries exist.
		$final_labels = ! empty( $labels_s2 ) ? $labels_s2 : $labels_s1;
		$values = array_values( $final_labels );
		usort(
			$values,
			static function( string $a, string $b ): int {
				return strnatcasecmp( $a, $b );
			}
		);
		return $values;
	}
}
