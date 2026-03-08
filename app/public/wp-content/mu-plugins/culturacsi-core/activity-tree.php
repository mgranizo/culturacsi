<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'culturacsi_activity_tree_asset_path' ) ) {
	function culturacsi_activity_tree_asset_path( string $relative_path ): string {
		return __DIR__ . '/assets/' . ltrim( $relative_path, '/' );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_asset_url' ) ) {
	function culturacsi_activity_tree_asset_url( string $relative_path ): string {
		return content_url( 'mu-plugins/culturacsi-core/assets/' . ltrim( $relative_path, '/' ) );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_asset_version' ) ) {
	function culturacsi_activity_tree_asset_version( string $relative_path ): ?string {
		$asset_path = culturacsi_activity_tree_asset_path( $relative_path );

		if ( ! file_exists( $asset_path ) ) {
			return null;
		}

		return (string) filemtime( $asset_path );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_enqueue_assets' ) ) {
	/**
	 * Load the shared activity-tree checklist assets where the checklist can appear.
	 *
	 * The script is event-delegated and self-guarding, so it is safe to load on
	 * the reserved frontend and on admin edit screens for the supported post types.
	 */
	function culturacsi_activity_tree_enqueue_assets(): void {
		$should_enqueue = false;

		if ( is_admin() ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			$should_enqueue = $screen instanceof WP_Screen
				&& in_array( (string) $screen->post_type, array( 'association', 'event' ), true );
		} else {
			$path = function_exists( 'culturacsi_routing_current_path' )
				? culturacsi_routing_current_path()
				: trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
			$should_enqueue = ( 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' ) );
		}

		if ( ! $should_enqueue ) {
			return;
		}

		wp_enqueue_style(
			'culturacsi-activity-tree',
			culturacsi_activity_tree_asset_url( 'activity-tree.css' ),
			array(),
			culturacsi_activity_tree_asset_version( 'activity-tree.css' )
		);

		wp_enqueue_script(
			'culturacsi-activity-tree',
			culturacsi_activity_tree_asset_url( 'activity-tree.js' ),
			array(),
			culturacsi_activity_tree_asset_version( 'activity-tree.js' ),
			true
		);
	}
	add_action( 'wp_enqueue_scripts', 'culturacsi_activity_tree_enqueue_assets', 45 );
	add_action( 'admin_enqueue_scripts', 'culturacsi_activity_tree_enqueue_assets', 45 );
}

if ( ! function_exists( 'culturacsi_activity_tree_option_name' ) ) {
	function culturacsi_activity_tree_option_name(): string {
		if ( defined( 'AB_SETTORI_TREE_OPTION' ) ) {
			$name = (string) constant( 'AB_SETTORI_TREE_OPTION' );
			if ( '' !== trim( $name ) ) {
				return $name;
			}
		}
		return 'ab_settori_tree_settings';
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_normalize_key' ) ) {
	function culturacsi_activity_tree_normalize_key( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		if ( function_exists( 'remove_accents' ) ) {
			$value = remove_accents( $value );
		}
		$value = strtolower( $value );
		$value = preg_replace( '/\s+/u', ' ', $value );
		$value = preg_replace( '/[^a-z0-9]+/', '-', (string) $value );
		return trim( (string) $value, '-' );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_sanitize_nodes' ) ) {
	function culturacsi_activity_tree_sanitize_nodes( array $nodes ): array {
		$clean = array();
		foreach ( $nodes as $macro => $settori_map ) {
			$macro_label = trim( sanitize_text_field( (string) $macro ) );
			if ( '' === $macro_label ) {
				continue;
			}
			if ( ! isset( $clean[ $macro_label ] ) ) {
				$clean[ $macro_label ] = array();
			}
			if ( ! is_array( $settori_map ) ) {
				continue;
			}

			foreach ( $settori_map as $settore => $settore2_list ) {
				$settore_label = trim( sanitize_text_field( (string) $settore ) );
				if ( '' === $settore_label ) {
					continue;
				}
				$set = array();
				if ( is_array( $settore2_list ) ) {
					foreach ( $settore2_list as $settore2 ) {
						$settore2_label = trim( sanitize_text_field( (string) $settore2 ) );
						if ( '' === $settore2_label ) {
							continue;
						}
						$set[ $settore2_label ] = true;
					}
				}
				$leaf_values = array_keys( $set );
				sort( $leaf_values, SORT_NATURAL | SORT_FLAG_CASE );
				$clean[ $macro_label ][ $settore_label ] = $leaf_values;
			}

			uksort( $clean[ $macro_label ], 'strnatcasecmp' );
		}

		uksort( $clean, 'strnatcasecmp' );
		return $clean;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_nodes_from_taxonomy' ) ) {
	function culturacsi_activity_tree_nodes_from_taxonomy(): array {
		// OPTIMIZATION: Use transient to cache taxonomy tree
		$cache_key = 'culturacsi_activity_tree_taxonomy_nodes';
		$cached_tree = get_transient( $cache_key );
		if ( false !== $cached_tree && is_array( $cached_tree ) ) {
			return $cached_tree;
		}

		if ( ! taxonomy_exists( 'activity_category' ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => 'activity_category',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$by_parent = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof WP_Term ) {
				continue;
			}
			$parent = (int) $term->parent;
			if ( ! isset( $by_parent[ $parent ] ) ) {
				$by_parent[ $parent ] = array();
			}
			$by_parent[ $parent ][] = $term;
		}
		foreach ( $by_parent as $parent_id => $children ) {
			usort(
				$children,
				static function( WP_Term $a, WP_Term $b ): int {
					return strnatcasecmp( (string) $a->name, (string) $b->name );
				}
			);
			$by_parent[ $parent_id ] = $children;
		}

		$tree = array();
		foreach ( $by_parent[0] ?? array() as $macro_term ) {
			$macro_label = trim( (string) $macro_term->name );
			if ( '' === $macro_label ) {
				continue;
			}
			$tree[ $macro_label ] = array();
			foreach ( $by_parent[ (int) $macro_term->term_id ] ?? array() as $settore_term ) {
				$settore_label = trim( (string) $settore_term->name );
				if ( '' === $settore_label ) {
					continue;
				}
				$tree[ $macro_label ][ $settore_label ] = array();
				foreach ( $by_parent[ (int) $settore_term->term_id ] ?? array() as $leaf_term ) {
					$leaf_label = trim( (string) $leaf_term->name );
					if ( '' === $leaf_label ) {
						continue;
					}
					$tree[ $macro_label ][ $settore_label ][] = $leaf_label;
				}
				$tree[ $macro_label ][ $settore_label ] = array_values(
					array_unique( array_filter( array_map( 'strval', (array) $tree[ $macro_label ][ $settore_label ] ) ) )
				);
				sort( $tree[ $macro_label ][ $settore_label ], SORT_NATURAL | SORT_FLAG_CASE );
			}
		}

		$result = culturacsi_activity_tree_sanitize_nodes( $tree );
		
		// Cache for 1 hour - taxonomy changes are infrequent
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );
		
		return $result;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_get_nodes' ) ) {
	function culturacsi_activity_tree_get_nodes(): array {
		// OPTIMIZATION: Use transient to cache nodes across requests
		$cache_key = 'culturacsi_activity_tree_nodes';
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$nodes = array();
		if ( function_exists( 'abf_get_manual_nodes' ) ) {
			$nodes = (array) abf_get_manual_nodes();
		}

		// Single source of truth: Struttura Settori manual tree only.
		if ( empty( $nodes ) ) {
			$settings = get_option( culturacsi_activity_tree_option_name(), array() );
			if ( is_array( $settings ) && isset( $settings['manual_nodes'] ) && is_array( $settings['manual_nodes'] ) ) {
				$nodes = (array) $settings['manual_nodes'];
			}
		}

		// Fallback: derive from DB-stored categories if no manual tree configured
		if ( empty( $nodes ) && function_exists( 'abf_collect_editor_tree_nodes' ) ) {
			$nodes = (array) abf_collect_editor_tree_nodes();
		}

		$cached = culturacsi_activity_tree_sanitize_nodes( (array) $nodes );
		
		// Cache for 1 hour
		set_transient( $cache_key, $cached, HOUR_IN_SECONDS );
		
		return $cached;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_entry_key' ) ) {
	function culturacsi_activity_tree_entry_key( string $label ): string {
		$label = trim( $label );
		if ( '' === $label ) {
			return '';
		}
		if ( function_exists( 'ab_assoc_normalize_key' ) ) {
			return (string) ab_assoc_normalize_key( $label );
		}
		return culturacsi_activity_tree_normalize_key( $label );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_display_label' ) ) {
	/**
	 * Display-only translation for activity labels.
	 * Canonical values must remain unchanged for taxonomy/query behavior.
	 */
	function culturacsi_activity_tree_display_label( string $label ): string {
		$label = trim( $label );
		if ( '' === $label ) {
			return '';
		}
		if ( function_exists( 'culturacsi_translate_visual_label' ) ) {
			$translated = culturacsi_translate_visual_label( $label );
			if ( '' !== trim( $translated ) ) {
				return $translated;
			}
		}
		return $label;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_flat_entries' ) ) {
	/**
	 * Flatten the activity tree as an ordered list of entries across all levels:
	 * macro categoria, settore, settore 2.
	 *
	 * @return array<int, array<string,string>>
	 */
	function culturacsi_activity_tree_flat_entries(): array {
		$nodes = culturacsi_activity_tree_get_nodes();
		if ( empty( $nodes ) ) {
			return array();
		}

		$entries = array();
		foreach ( $nodes as $macro => $settori_map ) {
			$macro_label = trim( (string) $macro );
			if ( '' === $macro_label ) {
				continue;
			}

			$entries[] = array(
				'level'    => 'macro',
				'label'    => $macro_label,
				'key'      => culturacsi_activity_tree_entry_key( $macro_label ),
				'macro'    => $macro_label,
				'settore'  => '',
				'settore2' => '',
			);

			foreach ( (array) $settori_map as $settore => $settore2_list ) {
				$settore_label = trim( (string) $settore );
				if ( '' === $settore_label ) {
					continue;
				}

				$entries[] = array(
					'level'    => 'settore',
					'label'    => $settore_label,
					'key'      => culturacsi_activity_tree_entry_key( $settore_label ),
					'macro'    => $macro_label,
					'settore'  => $settore_label,
					'settore2' => '',
				);

				foreach ( (array) $settore2_list as $settore2 ) {
					$settore2_label = trim( (string) $settore2 );
					if ( '' === $settore2_label ) {
						continue;
					}

					$entries[] = array(
						'level'    => 'settore2',
						'label'    => $settore2_label,
						'key'      => culturacsi_activity_tree_entry_key( $settore2_label ),
						'macro'    => $macro_label,
						'settore'  => $settore_label,
						'settore2' => $settore2_label,
					);
				}
			}
		}

		return $entries;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_entry_link' ) ) {
	/**
	 * Build a Settori page URL from a tree entry using canonical query args,
	 * so all consumers (hero, patterns, reusable blocks) share the same links.
	 */
	function culturacsi_activity_tree_entry_link( array $entry ): string {
		$base_page = get_page_by_path( 'settori' );
		$base_url  = $base_page instanceof WP_Post
			? (string) get_permalink( (int) $base_page->ID )
			: (string) home_url( '/settori/' );

		$macro   = trim( (string) ( $entry['macro'] ?? '' ) );
		$settore = trim( (string) ( $entry['settore'] ?? '' ) );
		$settore2 = trim( (string) ( $entry['settore2'] ?? '' ) );
		$key     = trim( (string) ( $entry['key'] ?? '' ) );

		$macro_key   = function_exists( 'ab_qkey' ) ? (string) ab_qkey( 'settori', 'macro' ) : 'a_macro';
		$settore_key = function_exists( 'ab_qkey' ) ? (string) ab_qkey( 'settori', 'settore' ) : 'a_settore';
		$settore2_key = function_exists( 'ab_qkey' ) ? (string) ab_qkey( 'settori', 'settore2' ) : 'a_settore2';

		$args = array(
			$macro_key   => '' !== $macro ? $macro : null,
			$settore_key => '' !== $settore ? $settore : null,
			$settore2_key => '' !== $settore2 ? $settore2 : null,
			'abf_hero'   => '' !== $key ? $key : null,
		);

		return (string) add_query_arg( $args, $base_url );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_entry_image_url' ) ) {
	/**
	 * Resolve image URL for any tree entry.
	 *
	 * Priority:
	 * 1) Exact entry key (macro/settore/settore2)
	 * 2) Macro key fallback (ensures settore/settore2 inherit macro hero image)
	 */
	function culturacsi_activity_tree_entry_image_url( array $entry, array $hero_map ): string {
		$resolve_attachment_url = static function( string $label ): string {
			static $cache = array();
			$label = trim( $label );
			if ( '' === $label ) {
				return '';
			}
			if ( isset( $cache[ $label ] ) ) {
				return (string) $cache[ $label ];
			}

			$url = '';
			if ( function_exists( 'attachment_url_to_postid' ) ) {
				// No-op branch; keep function_exists explicit for older envs.
			}

			global $wpdb;
			if ( $wpdb instanceof wpdb ) {
				$attachment_id = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%%' AND post_title=%s ORDER BY ID DESC LIMIT 1",
						$label
					)
				);

				if ( $attachment_id <= 0 ) {
					$slug = sanitize_title( $label );
					if ( '' !== $slug ) {
						$attachment_id = (int) $wpdb->get_var(
							$wpdb->prepare(
								"SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment' AND post_status='inherit' AND post_mime_type LIKE 'image/%%' AND post_name=%s ORDER BY ID DESC LIMIT 1",
								$slug
							)
						);
					}
				}

				if ( $attachment_id > 0 ) {
					$candidate = wp_get_attachment_image_url( $attachment_id, 'full' );
					if ( is_string( $candidate ) && '' !== trim( $candidate ) ) {
						$url = trim( $candidate );
					}
				}
			}

			$cache[ $label ] = $url;
			return $url;
		};

		$key = trim( (string) ( $entry['key'] ?? '' ) );
		if ( '' !== $key && isset( $hero_map[ $key ] ) ) {
			$url = trim( (string) $hero_map[ $key ] );
			if ( '' !== $url ) {
				return $url;
			}
		}

		$label = trim( (string) ( $entry['label'] ?? '' ) );
		if ( '' !== $label ) {
			$fallback_label_url = $resolve_attachment_url( $label );
			if ( '' !== $fallback_label_url ) {
				return $fallback_label_url;
			}
		}

		$macro = trim( (string) ( $entry['macro'] ?? '' ) );
		if ( '' !== $macro ) {
			$macro_key = culturacsi_activity_tree_entry_key( $macro );
			if ( '' !== $macro_key && isset( $hero_map[ $macro_key ] ) ) {
				$url = trim( (string) $hero_map[ $macro_key ] );
				if ( '' !== $url ) {
					return $url;
				}
			}
			$fallback_macro_url = $resolve_attachment_url( $macro );
			if ( '' !== $fallback_macro_url ) {
				return $fallback_macro_url;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_ensure_term' ) ) {
	function culturacsi_activity_tree_ensure_term( string $label, int $parent_id ): int {
		if ( ! taxonomy_exists( 'activity_category' ) ) {
			return 0;
		}
		$label = trim( $label );
		if ( '' === $label ) {
			return 0;
		}

		$exists = term_exists( $label, 'activity_category', $parent_id );
		if ( is_wp_error( $exists ) ) {
			return 0;
		}
		if ( is_array( $exists ) && isset( $exists['term_id'] ) ) {
			return (int) $exists['term_id'];
		}
		if ( is_numeric( $exists ) ) {
			return (int) $exists;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return 0;
		}

		$created = wp_insert_term(
			$label,
			'activity_category',
			array(
				'parent' => $parent_id,
			)
		);
		if ( is_wp_error( $created ) || ! isset( $created['term_id'] ) ) {
			return 0;
		}
		return (int) $created['term_id'];
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_term_index' ) ) {
	function culturacsi_activity_tree_term_index(): array {
		static $index = null;
		if ( is_array( $index ) ) {
			return $index;
		}

		$nodes = culturacsi_activity_tree_get_nodes();
		$path_to_term = array();
		$term_ids = array();

		foreach ( $nodes as $macro => $settori_map ) {
			$macro = trim( (string) $macro );
			if ( '' === $macro ) {
				continue;
			}
			$macro_term_id = culturacsi_activity_tree_ensure_term( $macro, 0 );
			if ( $macro_term_id > 0 ) {
				$path_to_term[ $macro ] = $macro_term_id;
				$term_ids[ $macro_term_id ] = true;
			}

			foreach ( (array) $settori_map as $settore => $settore2_list ) {
				$settore = trim( (string) $settore );
				if ( '' === $settore || $macro_term_id <= 0 ) {
					continue;
				}
				$settore_term_id = culturacsi_activity_tree_ensure_term( $settore, $macro_term_id );
				if ( $settore_term_id > 0 ) {
					$path_to_term[ $macro . ' > ' . $settore ] = $settore_term_id;
					$term_ids[ $settore_term_id ] = true;
				}

				foreach ( (array) $settore2_list as $settore2 ) {
					$settore2 = trim( (string) $settore2 );
					if ( '' === $settore2 || $settore_term_id <= 0 ) {
						continue;
					}
					$settore2_term_id = culturacsi_activity_tree_ensure_term( $settore2, $settore_term_id );
					if ( $settore2_term_id > 0 ) {
						$path_to_term[ $macro . ' > ' . $settore . ' > ' . $settore2 ] = $settore2_term_id;
						$term_ids[ $settore2_term_id ] = true;
					}
				}
			}
		}

		$index = array(
			'paths' => $path_to_term,
			'ids'   => array_keys( $term_ids ),
		);
		return $index;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_term_ids' ) ) {
	function culturacsi_activity_tree_term_ids(): array {
		$index = culturacsi_activity_tree_term_index();
		$ids = array_values( array_filter( array_map( 'intval', (array) ( $index['ids'] ?? array() ) ) ) );
		sort( $ids, SORT_NUMERIC );
		return $ids;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_normalize_path' ) ) {
	function culturacsi_activity_tree_normalize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}
		$segments = preg_split( '/\s*>\s*/', $path );
		if ( ! is_array( $segments ) ) {
			return '';
		}
		$normalized_segments = array();
		foreach ( $segments as $segment ) {
			$key = culturacsi_activity_tree_normalize_key( (string) $segment );
			if ( '' === $key ) {
				continue;
			}
			$normalized_segments[] = $key;
		}
		return implode( '>', $normalized_segments );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_path_maps' ) ) {
	function culturacsi_activity_tree_path_maps(): array {
		static $maps = null;
		if ( is_array( $maps ) ) {
			return $maps;
		}

		$maps = array(
			'exact' => array(),
			'norm'  => array(),
		);
		$register = static function( string $path ) use ( &$maps ): void {
			$path = trim( $path );
			if ( '' === $path ) {
				return;
			}
			$maps['exact'][ $path ] = $path;
			$norm = culturacsi_activity_tree_normalize_path( $path );
			if ( '' !== $norm && ! isset( $maps['norm'][ $norm ] ) ) {
				$maps['norm'][ $norm ] = $path;
			}
		};

		$nodes = culturacsi_activity_tree_get_nodes();
		foreach ( $nodes as $macro => $settori_map ) {
			$macro_label = trim( (string) $macro );
			if ( '' === $macro_label ) {
				continue;
			}
			$register( $macro_label );
			foreach ( (array) $settori_map as $settore => $settore2_list ) {
				$settore_label = trim( (string) $settore );
				if ( '' === $settore_label ) {
					continue;
				}
				$base_path = $macro_label . ' > ' . $settore_label;
				$register( $base_path );
				foreach ( (array) $settore2_list as $settore2 ) {
					$settore2_label = trim( (string) $settore2 );
					if ( '' === $settore2_label ) {
						continue;
					}
					$register( $base_path . ' > ' . $settore2_label );
				}
			}
		}

		return $maps;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_canonical_path' ) ) {
	function culturacsi_activity_tree_canonical_path( string $path, bool $allow_parent_fallback = true ): string {
		$segments = preg_split( '/\s*>\s*/', trim( $path ) );
		if ( ! is_array( $segments ) ) {
			return '';
		}
		$segments = array_values(
			array_filter(
				array_map(
					static function( string $segment ): string {
						return trim( $segment );
					},
					$segments
				),
				static function( string $segment ): bool {
					return '' !== $segment;
				}
			)
		);
		if ( empty( $segments ) ) {
			return '';
		}

		$maps = culturacsi_activity_tree_path_maps();
		while ( ! empty( $segments ) ) {
			$candidate = implode( ' > ', $segments );
			if ( isset( $maps['exact'][ $candidate ] ) ) {
				return (string) $maps['exact'][ $candidate ];
			}
			$candidate_norm = culturacsi_activity_tree_normalize_path( $candidate );
			if ( '' !== $candidate_norm && isset( $maps['norm'][ $candidate_norm ] ) ) {
				return (string) $maps['norm'][ $candidate_norm ];
			}
			if ( ! $allow_parent_fallback ) {
				break;
			}
			array_pop( $segments );
		}

		return '';
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_filter_paths' ) ) {
	function culturacsi_activity_tree_filter_paths( array $paths, bool $allow_parent_fallback = true ): array {
		$filtered = array();
		foreach ( $paths as $path_raw ) {
			$canonical = culturacsi_activity_tree_canonical_path( (string) $path_raw, $allow_parent_fallback );
			if ( '' === $canonical ) {
				continue;
			}
			$filtered[ $canonical ] = true;
		}
		$out = array_keys( $filtered );
		sort( $out, SORT_NATURAL | SORT_FLAG_CASE );
		return $out;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_term_ids_from_paths' ) ) {
	function culturacsi_activity_tree_term_ids_from_paths( array $paths, bool $allow_parent_fallback = true ): array {
		$canonical_paths = culturacsi_activity_tree_filter_paths( $paths, $allow_parent_fallback );
		if ( empty( $canonical_paths ) ) {
			return array();
		}

		$index = culturacsi_activity_tree_term_index();
		$path_map = isset( $index['paths'] ) && is_array( $index['paths'] ) ? $index['paths'] : array();
		$term_ids = array();
		foreach ( $canonical_paths as $path ) {
			$term_id = isset( $path_map[ $path ] ) ? (int) $path_map[ $path ] : 0;
			if ( $term_id > 0 ) {
				$term_ids[ $term_id ] = true;
			}
		}
		$out = array_map( 'intval', array_keys( $term_ids ) );
		sort( $out, SORT_NUMERIC );
		return $out;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_filter_term_ids' ) ) {
	function culturacsi_activity_tree_filter_term_ids( array $term_ids ): array {
		$term_ids = array_values( array_unique( array_filter( array_map( 'intval', $term_ids ) ) ) );
		if ( empty( $term_ids ) ) {
			return array();
		}

		$allowed_ids = culturacsi_activity_tree_term_ids();
		if ( empty( $allowed_ids ) ) {
			return array();
		}
		$allowed_map = array_fill_keys( array_map( 'intval', $allowed_ids ), true );
		$filtered = array();
		foreach ( $term_ids as $term_id ) {
			$term_id = (int) $term_id;
			if ( $term_id > 0 && isset( $allowed_map[ $term_id ] ) ) {
				$filtered[ $term_id ] = true;
			}
		}
		$out = array_map( 'intval', array_keys( $filtered ) );
		sort( $out, SORT_NUMERIC );
		return $out;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_set_post_terms' ) ) {
	function culturacsi_activity_tree_set_post_terms( int $post_id, array $term_ids ): void {
		$filtered = culturacsi_activity_tree_filter_term_ids( $term_ids );
		wp_set_post_terms( $post_id, $filtered, 'activity_category', false );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_filter_pre_set_object_terms' ) ) {
	function culturacsi_activity_tree_filter_pre_set_object_terms( $terms, int $object_id, string $taxonomy ) {
		if ( 'activity_category' !== $taxonomy ) {
			return $terms;
		}

		$raw_terms = is_array( $terms ) ? $terms : array( $terms );
		$term_ids = array();
		foreach ( $raw_terms as $raw_term ) {
			if ( is_numeric( $raw_term ) ) {
				$term_id = (int) $raw_term;
				if ( $term_id > 0 ) {
					$term_ids[] = $term_id;
				}
				continue;
			}
			$label = trim( sanitize_text_field( (string) $raw_term ) );
			if ( '' === $label ) {
				continue;
			}
			$exists = term_exists( $label, 'activity_category' );
			if ( is_array( $exists ) && isset( $exists['term_id'] ) ) {
				$term_ids[] = (int) $exists['term_id'];
			} elseif ( is_numeric( $exists ) ) {
				$term_ids[] = (int) $exists;
			}
		}

		return culturacsi_activity_tree_filter_term_ids( $term_ids );
	}
	add_filter( 'pre_set_object_terms', 'culturacsi_activity_tree_filter_pre_set_object_terms', 10, 3 );
}

if ( ! function_exists( 'culturacsi_activity_tree_render_checkbox' ) ) {
	function culturacsi_activity_tree_render_checkbox( string $field_name, int $term_id, string $label, array $selected_map, string $level ): string {
		$label = trim( $label );
		if ( '' === $label ) {
			return '';
		}
		$display_label = culturacsi_activity_tree_display_label( $label );
		$level_class = in_array( $level, array( 'macro', 'settore', 'settore2' ), true ) ? $level : 'settore2';
		$is_checked = $term_id > 0 && isset( $selected_map[ $term_id ] );
		$out = '<label class="csi-activity-tree-label level-' . esc_attr( $level_class ) . ( $is_checked ? ' is-checked' : '' ) . '">';
		if ( $term_id > 0 ) {
			$out .= '<input type="checkbox" name="' . esc_attr( $field_name ) . '" value="' . esc_attr( (string) $term_id ) . '"' . checked( $is_checked, true, false ) . ' />';
		}
		$out .= '<span>' . esc_html( $display_label ) . '</span>';
		$out .= '</label>';
		return $out;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_selected_ids_from_post_meta' ) ) {
	function culturacsi_activity_tree_selected_ids_from_post_meta( int $post_id, array $path_map ): array {
		if ( $post_id <= 0 ) {
			return array();
		}
		$ids = array();

		$normalize_path = static function( string $path ): string {
			$parts = array_values(
				array_filter(
					array_map(
						static function( string $segment ): string {
							return trim( $segment );
						},
						explode( '>', $path )
					),
					static function( string $segment ): bool {
						return '' !== $segment;
					}
				)
			);
			return implode( ' > ', $parts );
		};
		$add_path = static function( string $path ) use ( &$ids, $path_map, $normalize_path ): void {
			$path = $normalize_path( $path );
			if ( '' === $path ) {
				return;
			}
			$term_id = isset( $path_map[ $path ] ) ? (int) $path_map[ $path ] : 0;
			if ( $term_id > 0 ) {
				$ids[ $term_id ] = true;
			}
		};

		$raw_all_categories = trim( (string) get_post_meta( $post_id, '_ab_csv_all_categories', true ) );
		if ( '' !== $raw_all_categories ) {
			$paths = preg_split( '/\s*\|\s*/', $raw_all_categories );
			if ( is_array( $paths ) ) {
				foreach ( $paths as $path ) {
					$add_path( (string) $path );
				}
			}
		}

		if ( empty( $ids ) ) {
			$macro = trim( (string) get_post_meta( $post_id, '_ab_csv_macro', true ) );
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

			$tokens = array();
			if ( '' !== $settore2_raw ) {
				$tmp = preg_split( '~/+~', $settore2_raw );
				if ( is_array( $tmp ) ) {
					foreach ( $tmp as $token ) {
						$token = trim( (string) $token );
						if ( '' === $token ) {
							continue;
						}
						$tokens[] = $token;
					}
				}
			}
			if ( empty( $tokens ) ) {
				$tokens[] = '';
			}

			foreach ( $tokens as $settore2 ) {
				$segments = array();
				if ( '' !== $macro ) {
					$segments[] = $macro;
				}
				if ( '' !== $settore ) {
					$segments[] = $settore;
				}
				if ( '' !== $settore2 ) {
					$segments[] = $settore2;
				}
				if ( ! empty( $segments ) ) {
					$add_path( implode( ' > ', $segments ) );
				}
			}
		}

		return array_map( 'intval', array_keys( $ids ) );
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_render_checklist' ) ) {
	function culturacsi_activity_tree_render_checklist( array $selected_term_ids = array(), string $field_name = 'tax_input[activity_category][]', int $post_id = 0 ): string {
		$selected_map = array();
		foreach ( $selected_term_ids as $term_id ) {
			$term_id = (int) $term_id;
			if ( $term_id > 0 ) {
				$selected_map[ $term_id ] = true;
			}
		}

		$nodes = culturacsi_activity_tree_get_nodes();
		$index = culturacsi_activity_tree_term_index();
		$path_map = isset( $index['paths'] ) && is_array( $index['paths'] ) ? $index['paths'] : array();
		if ( $post_id > 0 ) {
			$meta_ids = culturacsi_activity_tree_selected_ids_from_post_meta( $post_id, $path_map );
			foreach ( $meta_ids as $meta_id ) {
				$meta_id = (int) $meta_id;
				if ( $meta_id > 0 ) {
					$selected_map[ $meta_id ] = true;
				}
			}
		}
		$selected_ids = array_keys( $selected_map );
		foreach ( $selected_ids as $selected_id ) {
			$selected_id = (int) $selected_id;
			if ( $selected_id <= 0 ) {
				continue;
			}
			$ancestors = get_ancestors( $selected_id, 'activity_category', 'taxonomy' );
			if ( ! is_array( $ancestors ) ) {
				continue;
			}
			foreach ( $ancestors as $ancestor_id ) {
				$ancestor_id = (int) $ancestor_id;
				if ( $ancestor_id > 0 ) {
					$selected_map[ $ancestor_id ] = true;
				}
			}
		}

		if ( empty( $nodes ) ) {
			if ( ! function_exists( 'wp_terms_checklist' ) ) {
				require_once ABSPATH . 'wp-admin/includes/template.php';
			}
			ob_start();
			wp_terms_checklist(
				0,
				array(
					'taxonomy'      => 'activity_category',
					'selected_cats' => array_keys( $selected_map ),
					'checked_ontop' => false,
				)
			);
			return (string) ob_get_clean();
		}

		if ( ! is_admin() ) {
			culturacsi_activity_tree_enqueue_assets();
		}

		$out = '<ul class="csi-activity-tree csi-activity-tree-macro">';
		foreach ( $nodes as $macro => $settori_map ) {
			$macro_path = trim( (string) $macro );
			if ( '' === $macro_path ) {
				continue;
			}
			$macro_term_id = (int) ( $path_map[ $macro_path ] ?? 0 );
			$out .= '<li class="csi-activity-tree-item level-macro">';
			$out .= culturacsi_activity_tree_render_checkbox( $field_name, $macro_term_id, $macro_path, $selected_map, 'macro' );

			if ( ! empty( $settori_map ) ) {
				$out .= '<ul class="children level-settore">';
				foreach ( (array) $settori_map as $settore => $settore2_list ) {
					$settore_label = trim( (string) $settore );
					if ( '' === $settore_label ) {
						continue;
					}
					$settore_path = $macro_path . ' > ' . $settore_label;
					$settore_term_id = (int) ( $path_map[ $settore_path ] ?? 0 );
					$out .= '<li class="csi-activity-tree-item level-settore">';
					$out .= culturacsi_activity_tree_render_checkbox( $field_name, $settore_term_id, $settore_label, $selected_map, 'settore' );

					if ( ! empty( $settore2_list ) ) {
						$out .= '<ul class="children level-settore2">';
						foreach ( (array) $settore2_list as $settore2 ) {
							$settore2_label = trim( (string) $settore2 );
							if ( '' === $settore2_label ) {
								continue;
							}
							$leaf_path = $settore_path . ' > ' . $settore2_label;
							$leaf_term_id = (int) ( $path_map[ $leaf_path ] ?? 0 );
							$out .= '<li class="csi-activity-tree-item level-settore2">';
							$out .= culturacsi_activity_tree_render_checkbox( $field_name, $leaf_term_id, $settore2_label, $selected_map, 'settore2' );
							$out .= '</li>';
						}
						$out .= '</ul>';
					}
					$out .= '</li>';
				}
				$out .= '</ul>';
			}

			$out .= '</li>';
		}
		$out .= '</ul>';

		return $out;
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_render_admin_metabox' ) ) {
	/**
	 * Render canonical activity tree in wp-admin association edit screen.
	 */
	function culturacsi_activity_tree_render_admin_metabox( WP_Post $post ): void {
		$selected = wp_get_post_terms( (int) $post->ID, 'activity_category', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $selected ) ) {
			$selected = array();
		}

		echo '<div id="taxonomy-activity_category" class="categorydiv">';
		// Keep compatibility with wp-admin taxonomy saving when all boxes are unchecked.
		echo '<input type="hidden" name="tax_input[activity_category][]" value="0" />';
		echo '<div id="activity_category-all" class="tabs-panel">';
		echo culturacsi_activity_tree_render_checklist( array_map( 'intval', (array) $selected ), 'tax_input[activity_category][]', (int) $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</div>';
		echo '</div>';
	}
}

if ( ! function_exists( 'culturacsi_activity_tree_override_admin_taxonomy_metabox' ) ) {
	/**
	 * Replace default taxonomy metabox with canonical tree-based checklist.
	 */
	function culturacsi_activity_tree_override_admin_taxonomy_metabox( string $post_type ): void {
		if ( 'association' !== $post_type || ! taxonomy_exists( 'activity_category' ) ) {
			return;
		}

		remove_meta_box( 'activity_categorydiv', 'association', 'side' );
		remove_meta_box( 'tagsdiv-activity_category', 'association', 'side' );

		$taxonomy = get_taxonomy( 'activity_category' );
		$title    = ( $taxonomy && isset( $taxonomy->labels->name ) ) ? (string) $taxonomy->labels->name : 'Activity Categories';
		add_meta_box(
			'activity_categorydiv',
			esc_html( $title ),
			'culturacsi_activity_tree_render_admin_metabox',
			'association',
			'side',
			'default'
		);
	}
	add_action( 'add_meta_boxes', 'culturacsi_activity_tree_override_admin_taxonomy_metabox', 100 );
}

if ( ! function_exists( 'culturacsi_activity_tree_attach_taxonomy_to_events' ) ) {
	/**
	 * Ensure event posts can store activity tree terms.
	 */
	function culturacsi_activity_tree_attach_taxonomy_to_events(): void {
		if ( ! taxonomy_exists( 'activity_category' ) || ! post_type_exists( 'event' ) ) {
			return;
		}
		register_taxonomy_for_object_type( 'activity_category', 'event' );
	}
	add_action( 'init', 'culturacsi_activity_tree_attach_taxonomy_to_events', 20 );
}
