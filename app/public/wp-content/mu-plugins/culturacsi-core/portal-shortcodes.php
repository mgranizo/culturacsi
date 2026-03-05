<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/shortcodes/admin-control-panel.php';
require_once __DIR__ . '/shortcodes/settori-hero-carousel.php';
require_once __DIR__ . '/shortcodes/association-form.php';
require_once __DIR__ . '/shortcodes/associations-form.php';
require_once __DIR__ . '/shortcodes/associations-list.php';
require_once __DIR__ . '/shortcodes/cronologia-list.php';
require_once __DIR__ . '/shortcodes/ux-guidance.php';
require_once __DIR__ . '/shortcodes/content-entries.php';
require_once __DIR__ . '/shortcodes/event-form.php';
require_once __DIR__ . '/shortcodes/events-list.php';
require_once __DIR__ . '/shortcodes/news-form.php';
require_once __DIR__ . '/shortcodes/news-list.php';
require_once __DIR__ . '/shortcodes/user-profile-form.php';
require_once __DIR__ . '/shortcodes/users-form.php';
require_once __DIR__ . '/shortcodes/users-list.php';

function culturacsi_portal_can_access(): bool {
	return is_user_logged_in() && ( current_user_can( 'association_manager' ) || current_user_can( 'manage_options' ) );
}

function culturacsi_portal_get_managed_association_id( int $user_id ): int {
	$assoc_id = (int) get_user_meta( $user_id, 'association_post_id', true );
	if ( $assoc_id > 0 && 'association' === get_post_type( $assoc_id ) ) {
		return $assoc_id;
	}
	return 0;
}

function culturacsi_portal_post_owner_association_id( WP_Post $post ): int {
	if ( 'association' === $post->post_type ) {
		return (int) $post->ID;
	}
	if ( in_array( $post->post_type, array( 'event', 'news', 'csi_content_entry' ), true ) ) {
		return (int) get_post_meta( (int) $post->ID, 'organizer_association_id', true );
	}
	return 0;
}

function culturacsi_portal_can_manage_post( WP_Post $post, int $user_id = 0 ): bool {
	if ( $user_id <= 0 ) {
		$user_id = get_current_user_id();
	}
	if ( $user_id <= 0 ) {
		return false;
	}

	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	if ( ! user_can( $user_id, 'association_manager' ) ) {
		return false;
	}

	$managed_assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
	if ( $managed_assoc_id <= 0 ) {
		return false;
	}

	$post_assoc_id = culturacsi_portal_post_owner_association_id( $post );
	return $post_assoc_id > 0 && $post_assoc_id === $managed_assoc_id;
}

function culturacsi_portal_can_manage_user_target( WP_User $target_user, int $actor_user_id = 0 ): bool {
	if ( $actor_user_id <= 0 ) {
		$actor_user_id = get_current_user_id();
	}
	if ( $actor_user_id <= 0 ) {
		return false;
	}
	if ( user_can( $actor_user_id, 'manage_options' ) ) {
		return true;
	}
	if ( ! user_can( $actor_user_id, 'association_manager' ) ) {
		return false;
	}
	if ( user_can( $target_user, 'manage_options' ) ) {
		return false;
	}

	$managed_assoc_id = culturacsi_portal_get_managed_association_id( $actor_user_id );
	if ( $managed_assoc_id <= 0 ) {
		return false;
	}

	$target_assoc_id = (int) get_user_meta( (int) $target_user->ID, 'association_post_id', true );
	return $target_assoc_id > 0 && $target_assoc_id === $managed_assoc_id;
}

function culturacsi_portal_notice( string $message, string $type = 'success' ): string {
	$type = in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'success';
	return '<div class="assoc-admin-notice assoc-admin-notice-' . esc_attr( $type ) . '">' . esc_html( $message ) . '</div>';
}

/**
 * Render a standardized association selection field for portal forms.
 */
function culturacsi_portal_render_association_selection_field( int $current_val, string $field_id = 'organizer_association_id', string $label = 'Associazione Referente', string $tip = 'L\'associazione che gestisce questo contenuto.' ): string {
	$is_admin = current_user_can( 'manage_options' );
	$user_id  = get_current_user_id();
	$managed_assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
	$val_to_use = ( $current_val > 0 ) ? $current_val : $managed_assoc_id;

	ob_start();
	if ( $is_admin ) {
		// Use transient cache for association list to improve performance
		$transient_key = 'culturacsi_assoc_list_all_' . get_current_blog_id();
		$association_ids = get_transient( $transient_key );

		if ( false === $association_ids ) {
			$association_ids = get_posts( array(
				'post_type'      => 'association',
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			) );
			// Cache for 5 minutes
			set_transient( $transient_key, $association_ids, MINUTE_IN_SECONDS * 5 );
		}

		$associations = $association_ids;
		?>
		<p>
			<?php 
			if ( function_exists( 'culturacsi_portal_label_with_tip' ) ) {
				echo culturacsi_portal_label_with_tip( $field_id, $label, $tip );
			} else {
				echo '<label for="' . esc_attr( $field_id ) . '">' . esc_html( $label ) . '</label>';
			}
			?>
			<select id="<?php echo esc_attr( $field_id ); ?>" name="<?php echo esc_attr( $field_id ); ?>">
				<option value="0">-- Seleziona Associazione --</option>
				<?php foreach ( $associations as $assoc_id ) : ?>
					<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $val_to_use, (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	} else {
		if ( $val_to_use > 0 ) {
			?>
			<p>
				<label><?php echo esc_html( $label ); ?></label>
				<input type="text" readonly value="<?php echo esc_attr( get_the_title( $val_to_use ) ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $field_id ); ?>" value="<?php echo esc_attr( (string) $val_to_use ); ?>">
			</p>
			<?php
		}
	}
	return (string) ob_get_clean();
}

/**
 * Force Association ID on post save if author is linked to an association.
 */
function culturacsi_auto_assign_association_to_post( $post_id, $post, $update ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! in_array( $post->post_type, array( 'csi_content_entry', 'news', 'event' ), true ) ) {
		return;
	}

	// If site admin is saving, don't overwrite if they just changed it.
	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	$author_id = (int) $post->post_author;
	$assoc_id  = culturacsi_portal_get_managed_association_id( $author_id );

	if ( $assoc_id > 0 ) {
		update_post_meta( $post_id, 'organizer_association_id', $assoc_id );
	}
}
add_action( 'save_post', 'culturacsi_auto_assign_association_to_post', 20, 3 );

/**
 * When a user is assigned to an association, update all their existing content.
 *
 * Uses a direct DB UPDATE instead of get_posts(-1) + per-row update_post_meta to
 * avoid loading an unbounded list of post objects into memory and issuing N+1 queries.
 * Existing meta rows are updated in one statement; missing meta rows are inserted in
 * a second batch INSERT … ON DUPLICATE KEY UPDATE via wpdb::replace (one row at a time
 * only for posts that don't yet have the key, keeping the query count proportional to
 * the number of NEW rows rather than ALL rows).
 */
function culturacsi_sync_all_author_content_association( $meta_id, $object_id, $meta_key, $_meta_value ) {
	// Only allow admins to trigger this sync
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( 'association_post_id' !== $meta_key ) {
		return;
	}
	$assoc_id  = (int) $_meta_value;
	$author_id = (int) $object_id;
	if ( $assoc_id <= 0 || $author_id <= 0 ) {
		return;
	}

	global $wpdb;

	$post_types_in = implode(
		', ',
		array_fill( 0, 3, '%s' )
	);

	// 1. Update rows that already have the meta key.
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 SET pm.meta_value = %d
			 WHERE pm.meta_key = 'organizer_association_id'
			   AND p.post_author = %d
			   AND p.post_type IN ({$post_types_in})
			   AND p.post_status != 'trash'",
			$assoc_id,
			$author_id,
			'csi_content_entry',
			'news',
			'event'
		)
	);

	// 2. Insert the meta key for posts that don't have it yet.
	$new_post_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm
			        ON pm.post_id = p.ID AND pm.meta_key = 'organizer_association_id'
			 WHERE p.post_author = %d
			   AND p.post_type IN ({$post_types_in})
			   AND p.post_status != 'trash'
			   AND pm.meta_id IS NULL",
			$author_id,
			'csi_content_entry',
			'news',
			'event'
		)
	);

	foreach ( (array) $new_post_ids as $p_id ) {
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => (int) $p_id,
				'meta_key'   => 'organizer_association_id',
				'meta_value' => $assoc_id,
			),
			array( '%d', '%s', '%d' )
		);
	}
}
add_action( 'added_user_meta', 'culturacsi_sync_all_author_content_association', 10, 4 );
add_action( 'updated_user_meta', 'culturacsi_sync_all_author_content_association', 10, 4 );

/**
 * Add Association Assignment metabox for backend admins.
 */
function culturacsi_add_association_metabox() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$post_types = array( 'csi_content_entry', 'news', 'event' );
	foreach ( $post_types as $pt ) {
		add_meta_box(
			'culturacsi_association_assignment',
			'Associazione Referente',
			'culturacsi_render_association_assignment_metabox',
			$pt,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'culturacsi_add_association_metabox' );

function culturacsi_render_association_assignment_metabox( $post ) {
	$current_assoc = (int) get_post_meta( $post->ID, 'organizer_association_id', true );
	$associations = get_posts( array(
		'post_type'      => 'association',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	) );
	wp_nonce_field( 'culturacsi_save_assoc_metabox', 'culturacsi_assoc_metabox_nonce' );
	?>
	<select name="organizer_association_id" style="width:100%;">
		<option value="0">-- Seleziona --</option>
		<?php foreach ( $associations as $assoc_id ) : ?>
			<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $current_assoc, (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
		<?php endforeach; ?>
	</select>
	<p class="description">Associazione che gestisce questo contenuto.</p>
	<?php
}

function culturacsi_save_association_metabox( $post_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_POST['culturacsi_assoc_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_assoc_metabox_nonce'] ) ), 'culturacsi_save_assoc_metabox' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( isset( $_POST['organizer_association_id'] ) ) {
		update_post_meta( $post_id, 'organizer_association_id', absint( wp_unslash( $_POST['organizer_association_id'] ) ) );
	}
}
add_action( 'save_post', 'culturacsi_save_association_metabox' );

/**
 * Add Association field to user profile for admins.
 */
function culturacsi_admin_user_association_field( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$current_assoc_id = (int) get_user_meta( $user->ID, 'association_post_id', true );
	$associations = get_posts( array(
		'post_type'      => 'association',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	) );
	?>
	<h3><?php _e( 'CSI Association Management', 'culturacsi' ); ?></h3>
	<table class="form-table">
		<tr>
			<th><label for="association_post_id"><?php _e( 'Managed Association', 'culturacsi' ); ?></label></th>
			<td>
				<select name="association_post_id" id="association_post_id">
					<option value="0">-- None --</option>
					<?php foreach ( $associations as $assoc_id ) : ?>
						<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $current_assoc_id, (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php _e( 'When changed, all posts authored by this user will be synced to this association.', 'culturacsi' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'culturacsi_admin_user_association_field' );
add_action( 'edit_user_profile', 'culturacsi_admin_user_association_field' );

function culturacsi_save_admin_user_association_field( $user_id ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['association_post_id'] ) ) {
		update_user_meta( $user_id, 'association_post_id', absint( wp_unslash( $_POST['association_post_id'] ) ) );
	}
}
add_action( 'personal_options_update', 'culturacsi_save_admin_user_association_field' );
add_action( 'edit_user_profile_update', 'culturacsi_save_admin_user_association_field' );

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

function culturacsi_get_site_logo_url( string $size = 'full', bool $allow_site_icon = true ): string {
	$size = '' !== trim( $size ) ? $size : 'full';

	$logo_id = (int) get_theme_mod( 'custom_logo' );
	if ( $logo_id > 0 ) {
		$logo_url = wp_get_attachment_image_url( $logo_id, $size );
		if ( is_string( $logo_url ) && '' !== $logo_url ) {
			return $logo_url;
		}
	}

	if ( ! $allow_site_icon ) {
		return '';
	}

	$site_icon_id = (int) get_option( 'site_icon' );
	if ( $site_icon_id > 0 ) {
		$icon_url = wp_get_attachment_image_url( $site_icon_id, $size );
		if ( is_string( $icon_url ) && '' !== $icon_url ) {
			return $icon_url;
		}
	}

	return '';
}

function culturacsi_portal_panel_role_label(): string {
	if ( current_user_can( 'manage_options' ) ) {
		return 'Pannello di Controllo Site Admin';
	}
	if ( current_user_can( 'association_manager' ) ) {
		return 'Pannello di Controllo Association Admin';
	}
	return 'Area Riservata';
}

function culturacsi_portal_nav_item_is_active( string $item_url, string $current_path ): bool {
	$item_path = trim( (string) wp_parse_url( $item_url, PHP_URL_PATH ), '/' );
	if ( '' === $item_path ) {
		return false;
	}
	$current_with_slash = rtrim( $current_path, '/' ) . '/';
	$item_with_slash    = rtrim( $item_path, '/' ) . '/';
	return 0 === strpos( $current_with_slash, $item_with_slash );
}

function culturacsi_portal_reserved_nav_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}

	$is_admin = current_user_can( 'manage_options' );
	$can_manage_sections = $is_admin || current_user_can( 'manage_csi_content_sections' );

	if ( $is_admin ) {
		// Site admin: unified contents hub + users + associations.
		$items = array(
			array( 'label' => 'Contenuti',    'url' => home_url( '/area-riservata/contenuti/' ) ),
			array( 'label' => 'Utenti',       'url' => home_url( '/area-riservata/utenti/' ) ),
			array( 'label' => 'Associazioni', 'url' => home_url( '/area-riservata/associazioni/' ) ),
			array( 'label' => 'Cronologia',   'url' => home_url( '/area-riservata/cronologia/' ) ),
		);
	} else {
		// Association manager: unified contents hub + colleagues + profile.
		$items = array(
			array( 'label' => 'Contenuti',    'url' => home_url( '/area-riservata/contenuti/' ) ),
			array( 'label' => 'Utenti',       'url' => home_url( '/area-riservata/utenti/' ) ),
			array( 'label' => 'Associazioni', 'url' => home_url( '/area-riservata/associazione/' ) ),
			array( 'label' => 'Cronologia',   'url' => home_url( '/area-riservata/cronologia/' ) ),
		);
	}
	if ( $can_manage_sections ) {
		$items[] = array( 'label' => 'Sezioni', 'url' => home_url( '/area-riservata/sezioni/' ) );
	}

	$current_path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	$logo_url     = culturacsi_get_site_logo_url( 'full', false );
	$role_label   = culturacsi_portal_panel_role_label();
	$nav_title = 'Area Riservata';

	if ( ! current_user_can( 'manage_options' ) ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id > 0 ) {
			$nav_title = get_the_title( $assoc_id );
		}
	}

	// Note: avoid AJAX-style JSON cache in a shortcode context; render static HTML here.

	ob_start();
	echo '<nav class="assoc-reserved-nav">';
	echo '<div class="assoc-reserved-nav-head">';
	if ( '' !== $logo_url ) {
		echo '<div class="assoc-reserved-nav-top-gap" style="height:40px;" aria-hidden="true"></div>';
		echo '<a class="assoc-reserved-nav-brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr__( 'Home', 'culturacsi' ) . '"><img src="' . esc_url( $logo_url ) . '" alt="' . esc_attr( get_bloginfo( 'name' ) ) . '"></a>';
	}
	echo '<a class="assoc-reserved-nav-logout" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">Esci</a>';
	echo '</div>';
	echo '<div class="assoc-reserved-nav-title-wrap">';
	echo '<span class="assoc-reserved-nav-title" style="line-height:1.2;">' . esc_html( $nav_title ) . '</span>';
	if ( ! current_user_can( 'manage_options' ) ) {
		echo '<span class="assoc-reserved-nav-subtitle" style="margin-top:4px;">' . esc_html( $role_label ) . '</span>';
	} else {
		echo '<span class="assoc-reserved-nav-subtitle">' . esc_html( $role_label ) . '</span>';
	}
	echo '</div>';
	echo '<ul class="assoc-reserved-nav-list">';
	foreach ( $items as $item ) {
		$item_path = trim( (string) wp_parse_url( (string) $item['url'], PHP_URL_PATH ), '/' );
		$is_dark_tab = in_array( $item_path, array( 'area-riservata/cronologia', 'area-riservata/sezioni' ), true );
		$link_class = 'assoc-reserved-nav-link'
			. ( $is_dark_tab ? ' is-dark-tab' : '' )
			. ( culturacsi_portal_nav_item_is_active( (string) $item['url'], $current_path ) ? ' is-active' : '' );
		echo '<li><a class="' . esc_attr( $link_class ) . '" href="' . esc_url( $item['url'] ) . '">' . esc_html( $item['label'] ) . '</a></li>';
	}
	echo '</ul></nav>';
	return ob_get_clean();
}

function culturacsi_portal_dashboard_shortcode(): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '<p>Devi effettuare l\'accesso per usare questa area.</p>';
	}
	// Delegate to the same UI as the control panel shortcode for consistency
	if ( function_exists( 'culturacsi_portal_admin_control_panel_shortcode' ) ) {
		return culturacsi_portal_admin_control_panel_shortcode();
	}
	
	ob_start();
	echo '<div class="assoc-portal-dashboard">';
	echo '<h2>Benvenuto nell\'Area Riservata</h2>';
	echo '<p>Seleziona una sezione dal menu laterale.</p>';
	echo '</div>';
	return ob_get_clean();
}
add_action( 'wp_footer', 'culturacsi_portal_modal_html' );

function culturacsi_portal_modal_html(): void {
	if ( is_admin() || ! culturacsi_portal_can_access() ) return;

	// Global off by default; enable only if explicitly requested via filter.
	if ( ! apply_filters( 'culturacsi_portal_enable_modal', false ) ) return;

	// Scope: render only within the reserved area to avoid site-wide injection
	$path      = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
	$is_portal = ( 0 === strpos( rtrim( $path, '/' ) . '/', 'area-riservata/' ) );
	/**
	 * Filter to customize where the portal modal renders.
	 * Return true to force-enable, false to force-disable.
	 *
	 * @param bool   $is_portal Computed match for 'area-riservata/'.
	 * @param string $path      Current request path (no leading slash).
	 */
	$is_portal = (bool) apply_filters( 'culturacsi_portal_is_portal_request', $is_portal, $path );
	if ( ! $is_portal ) return;
	?>
	<style id="assoc-portal-modal-fallback-css">
		#assoc-portal-modal{display:none}
		#assoc-portal-modal.is-open{display:flex}
	</style>
	<div id="assoc-portal-modal" class="assoc-modal">
		<div class="assoc-modal-overlay"></div>
		<div class="assoc-modal-container">
			<header class="assoc-modal-header">
				<h2 class="assoc-modal-title" id="assoc-modal-title">Dettagli</h2>
				<button class="assoc-modal-close"></button>
			</header>
			<main class="assoc-modal-content" id="assoc-modal-content"></main>
			<footer class="assoc-modal-footer" id="assoc-modal-footer"></footer>
		</div>
	</div>
	<script>window.assocPortalNonce = "<?php echo esc_js( wp_create_nonce( 'culturacsi_portal_ajax' ) ); ?>";</script>
	<?php
}

add_action( 'wp_ajax_culturacsi_get_modal_data', 'culturacsi_ajax_get_modal_data' );
function culturacsi_ajax_get_modal_data(): void {
	if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'culturacsi_portal_ajax' ) ) {
		wp_send_json_error( 'Sessione scaduta, ricarica la pagina.' );
	}
	if ( ! culturacsi_portal_can_access() ) {
		wp_send_json_error( 'Accesso negato.' );
	}
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	$type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : '';
	if ( ! $id || ! $type ) wp_send_json_error( 'Parametri mancanti.' );

	ob_start();
	$footer = '';
	if ( 'user' === $type ) {
		$user = get_user_by( 'id', $id );
		if ( $user ) {
			if ( ! culturacsi_portal_can_manage_user_target( $user, get_current_user_id() ) ) {
				wp_send_json_error( 'Permessi insufficienti.' );
			}
			$avatar_id = (int) get_user_meta( $id, 'assoc_user_avatar_id', true );
			$assoc_id = (int) get_user_meta( $id, 'association_post_id', true );
			$assoc_name = $assoc_id > 0 ? get_the_title( $assoc_id ) : '';
			
			echo '<div class="assoc-details-header" style="text-align:center;margin-bottom:20px;">';
			if ( $avatar_id ) echo wp_get_attachment_image( $avatar_id, array( 100, 100 ), false, array( 'class' => 'assoc-modal-avatar' ) );
			echo '<h3 style="margin-bottom:0;">' . esc_html( $user->display_name ) . '</h3>';
			if ( $assoc_name ) {
				echo '<p style="color:#2563eb; font-weight:600; margin:5px 0 0 0;">' . esc_html( $assoc_name ) . '</p>';
			}
			echo '<p style="color:#64748b;font-size:0.9rem; margin-top:5px;">@' . esc_html( $user->user_login ) . '</p>';
			echo '</div>';
			echo '<div class="assoc-details-grid">';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Email</span><span class="assoc-details-value">' . esc_html( $user->user_email ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Nome</span><span class="assoc-details-value">' . esc_html( $user->first_name ?: '-' ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Cognome</span><span class="assoc-details-value">' . esc_html( $user->last_name ?: '-' ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Ruolo</span><span class="assoc-details-value">' . esc_html( implode( ', ', $user->roles ) ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Registrato</span><span class="assoc-details-value">' . esc_html( mysql2date( 'd/m/Y H:i', $user->user_registered ) ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Stato</span><span class="assoc-details-value">' . esc_html( culturacsi_portal_user_approval_label( $user ) ) . '</span></div>';
			
			// Show all other user meta
			$all_meta = get_user_meta( $id );
			$skip_keys = array( 'session_tokens', 'wp_capabilities', 'wp_user_level', 'dismissed_wp_pointers', 'assoc_user_avatar_id', 'first_name', 'last_name', 'use_ssl', 'comment_shortcuts', 'rich_editing', 'admin_color', 'show_admin_bar_front', 'wp_user_settings', 'wp_user_settings_time' );
			foreach ( $all_meta as $key => $values ) {
				if ( 0 === strpos( $key, 'wp_' ) || in_array( $key, $skip_keys, true ) ) continue;
				$val = $values[0];
				if ( is_serialized( $val ) ) continue;
				echo '<div class="assoc-details-item"><span class="assoc-details-label">' . esc_html( str_replace( '_', ' ', $key ) ) . '</span><span class="assoc-details-value">' . esc_html( (string)$val ) . '</span></div>';
			}
			echo '</div>';
			
			ob_start();
			echo '<div class="assoc-action-group">';
			if ( current_user_can( 'manage_options' ) ) {
				echo '<a class="assoc-action-chip chip-edit" href="' . esc_url( culturacsi_portal_admin_user_form_url( $id ) ) . '">Modifica</a>';
				$is_approved = culturacsi_portal_is_user_approved( $user );
				$toggle_label = $is_approved ? 'Sospendi' : 'Approva';
				$toggle_action = $is_approved ? 'hold' : 'approve';
				$toggle_class = $is_approved ? 'chip-reject' : 'chip-approve';
				echo culturacsi_portal_action_button_form( array( 'context' => 'user', 'action' => $toggle_action, 'target_id' => $id, 'label' => $toggle_label, 'class' => $toggle_class ) );
			}
			echo '</div>';
			$footer = ob_get_clean();
		}
	} else {
		$post = get_post( $id );
		if ( $post && $post->post_type === $type ) {
			if ( ! culturacsi_portal_can_manage_post( $post, get_current_user_id() ) ) {
				wp_send_json_error( 'Permessi insufficienti.' );
			}
			echo '<div class="assoc-details-grid">';
			echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Titolo:</span> <span class="assoc-details-value" style="font-size:1.1rem;font-weight:700;">' . esc_html( $post->post_title ) . '</span></div>';
			// Associations: show Attività and a curated set of key fields (avoid dumping AB CSV fields)
			if ( 'association' === $type ) {
				$activity_labels = culturacsi_activity_labels_for_post( $id );
				if ( ! empty( $activity_labels ) ) {
					echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Attività:</span> <span class="assoc-details-value"><strong>' . esc_html( implode( ', ', $activity_labels ) ) . '</strong></span></div>';
				}
				$activity_paths = culturacsi_activity_paths_for_post( $id );
				if ( ! empty( $activity_paths ) ) {
					echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Macro > Settore > Settore 2:</span> <span class="assoc-details-value">' . esc_html( implode( ' | ', $activity_paths ) ) . '</span></div>';
				}
			}
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Data:</span> <span class="assoc-details-value">' . esc_html( get_the_date( 'd/m/Y H:i', $post ) ) . '</span></div>';
			echo '<div class="assoc-details-item"><span class="assoc-details-label">Stato:</span> <span class="assoc-details-value">' . esc_html( get_post_status_object( $post->post_status )->label ) . '</span></div>';

			// Metadata rendering
			$all_meta = get_post_meta( $id );
			if ( 'association' === $type ) {
				$curated = array(
					'email'          => 'Email',
					'phone'          => 'Telefono',
					'address'        => 'Indirizzo',
					'city'           => 'Città / Comune',
					'comune'         => 'Città / Comune',
					'province'       => 'Provincia',
					'region'         => 'Regione',
					'regione'        => 'Regione',
					'cap'            => 'CAP',
					'website'        => 'Sito Web',
					'sito'           => 'Sito Web',
					'sito_web'       => 'Sito Web',
					'web'            => 'Sito Web',
					'url'            => 'Sito Web',
					'facebook'       => 'Facebook',
					'facebook_url'   => 'Facebook',
					'instagram'      => 'Instagram',
					'instagram_url'  => 'Instagram',
					'youtube'        => 'Youtube',
					'tiktok'         => 'TikTok',
					'x'              => 'X (Twitter)',
					'codice_fiscale' => 'Codice Fiscale',
					'piva'           => 'Partita IVA',
				);
				$seen_meta = array();
				foreach ( $curated as $mkey => $mlabel ) {
					$val = isset( $all_meta[ $mkey ][0] ) ? (string) $all_meta[ $mkey ][0] : '';
					if ( '' === trim( $val ) ) continue;
					$norm_val = strtolower( function_exists( 'remove_accents' ) ? remove_accents( trim( preg_replace( '/\s+/u', ' ', (string) $val ) ) ) : trim( preg_replace( '/\s+/u', ' ', (string) $val ) ) );
					$seen_key = strtolower( function_exists( 'remove_accents' ) ? remove_accents( (string) $mlabel ) : (string) $mlabel ) . '|' . $norm_val;
					if ( '' !== $seen_key && isset( $seen_meta[ $seen_key ] ) ) {
						continue;
					}
					$seen_meta[ $seen_key ] = true;
					echo '<div class="assoc-details-item"><span class="assoc-details-label">' . esc_html( $mlabel ) . ':</span> <span class="assoc-details-value">' . esc_html( $val ) . '</span></div>';
				}
			} else {
				// Generic renderer for non-association post types
				$skip_keys = array( '_edit_lock', '_edit_last', '_thumbnail_id', '_assoc_user_avatar_id', 'comune', 'regione', '_comune', '_regione' );
				$translations = array(
					'city' => 'Città / Comune',
					'province' => 'Provincia',
					'region' => 'Regione',
					'address' => 'Indirizzo',
					'phone' => 'Telefono',
					'email' => 'Email',
					'website' => 'Sito Web',
					'codice_fiscale' => 'Codice Fiscale',
					'piva' => 'Partita IVA',
					'start_date' => 'Data Inizio',
					'end_date' => 'Data Fine',
					'venue_name' => 'Nome Luogo',
					'cap' => 'CAP',
					'organizer_association_id' => 'Associazione Organizzatrice',
					'facebook' => 'Facebook',
					'instagram' => 'Instagram',
					'youtube' => 'Youtube',
					'tiktok' => 'TikTok',
					'x' => 'X (Twitter)',
				);
				foreach ( $all_meta as $key => $values ) {
					if ( 0 === strpos( $key, '_wp_' ) || in_array( $key, $skip_keys, true ) ) continue;
					$val = $values[0];
					if ( is_serialized( $val ) || '' === trim( (string)$val ) ) continue;
					$display_key = ltrim( $key, '_' );
					if ( in_array( $display_key, $skip_keys, true ) ) continue;
					$label = isset( $translations[ $display_key ] ) ? $translations[ $display_key ] : str_replace( array('_', '-'), ' ', $display_key );
					echo '<div class="assoc-details-item"><span class="assoc-details-label">' . esc_html( strtoupper( $label ) ) . ':</span> <span class="assoc-details-value">' . esc_html( (string)$val ) . '</span></div>';
				}
			}

			if ( ! empty( $post->post_excerpt ) ) {
				echo '<div class="assoc-details-item assoc-details-full"><span class="assoc-details-label">Sommario:</span> <span class="assoc-details-value">' . wp_kses_post( $post->post_excerpt ) . '</span></div>';
			}
			echo '</div>';

			ob_start();
			echo '<div class="assoc-action-group">';
			$edit_url = '';
			if ( 'association' === $type ) {
				$edit_url = current_user_can( 'manage_options' )
					? culturacsi_portal_admin_association_form_url( $id )
					: home_url( '/area-riservata/profilo/' );
			} elseif ( 'news' === $type ) {
				$edit_url = add_query_arg( array( 'news_id' => $id ), home_url( '/area-riservata/notizie/nuova/' ) );
			} elseif ( 'event' === $type ) {
				$edit_url = add_query_arg( array( 'event_id' => $id ), home_url( '/area-riservata/eventi/nuovo/' ) );
			} else {
				$edit_url = get_edit_post_link( $id, '' );
			}
			echo '<a class="assoc-action-chip chip-edit" href="' . esc_url( (string) $edit_url ) . '">Modifica</a>';
			if ( current_user_can( 'manage_options' ) ) {
				echo culturacsi_portal_action_button_form( array( 'context' => $type, 'action' => 'delete', 'target_id' => $id, 'label' => 'Elimina', 'class' => 'chip-delete', 'confirm' => true, 'confirm_text' => 'Confermi eliminazione?' ) );
			}
			echo '</div>';
			$footer = ob_get_clean();
		}
	}
	$content = ob_get_clean();
	if ( empty( $content ) ) wp_send_json_error( 'Dati non disponibili.' );
	$payload = array( 'html' => $content, 'footer' => $footer );
	wp_send_json_success( $payload );
}

function culturacsi_portal_action_button_form( array $args ): string {
	$context       = isset( $args['context'] ) ? sanitize_key( (string) $args['context'] ) : '';
	$action        = isset( $args['action'] ) ? sanitize_key( (string) $args['action'] ) : '';
	$target_id     = isset( $args['target_id'] ) ? (int) $args['target_id'] : 0;
	$label         = isset( $args['label'] ) ? (string) $args['label'] : '';
	$class         = isset( $args['class'] ) ? (string) $args['class'] : '';
	$confirm       = ! empty( $args['confirm'] );
	$confirm_text  = isset( $args['confirm_text'] ) ? (string) $args['confirm_text'] : 'Confermi questa azione?';
	$nonce_action  = 'culturacsi_row_action_' . $context;
	$button_class  = 'assoc-action-chip ' . $class;
	$onclick_attr  = $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm_text ) . '\');"' : '';
	$form_class    = 'assoc-row-action-form';
	if ( false !== strpos( $class, 'chip-toggle' ) ) {
		$form_class .= ' is-toggle';
	}

	if ( '' === $context || '' === $action || $target_id <= 0 || '' === $label ) {
		return '';
	}

	$route_map = array(
		'user'              => 'utenti',
		'event'             => 'contenuti',
		'news'              => 'contenuti',
		'csi_content_entry' => 'contenuti',
		'content'           => 'contenuti',
		'association'       => current_user_can( 'manage_options' ) ? 'associazioni' : 'associazione',
	);
	$root_path = isset( $route_map[ $context ] ) ? $route_map[ $context ] : 'contenuti';
	$root_url  = home_url( '/area-riservata/' . $root_path . '/' );

	$html  = '<form method="post" class="' . esc_attr( $form_class ) . ' assoc-portal-form" data-redirect-url="' . esc_url( $root_url ) . '">';
	$html .= wp_nonce_field( $nonce_action, 'culturacsi_row_action_nonce', true, false );
	$html .= '<input type="hidden" name="culturacsi_row_context" value="' . esc_attr( $context ) . '">';
	$html .= '<input type="hidden" name="culturacsi_row_action" value="' . esc_attr( $action ) . '">';
	$html .= '<input type="hidden" name="culturacsi_row_target_id" value="' . esc_attr( (string) $target_id ) . '">';
	$html .= '<button type="submit" name="culturacsi_row_action_submit" class="' . esc_attr( trim( $button_class ) ) . '"' . $onclick_attr . '>' . esc_html( $label ) . '</button>';
	$html .= '</form>';
	return $html;
}

function culturacsi_portal_process_post_row_action( string $context, string $post_type, bool $allow_non_admin_delete = true ): string {
	if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
		return '';
	}
	if ( ! isset( $_POST['culturacsi_row_action_submit'], $_POST['culturacsi_row_context'], $_POST['culturacsi_row_action'], $_POST['culturacsi_row_target_id'] ) ) {
		return '';
	}

	$posted_context = sanitize_key( wp_unslash( $_POST['culturacsi_row_context'] ) );
	if ( $posted_context !== $context ) {
		return '';
	}

	$nonce_action = 'culturacsi_row_action_' . $context;
	if ( ! isset( $_POST['culturacsi_row_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_row_action_nonce'] ) ), $nonce_action ) ) {
		$error_msg = 'Verifica di sicurezza non valida.';
		if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) ob_end_clean();
			wp_send_json_error( $error_msg, 403 );
		}
		return culturacsi_portal_notice( $error_msg, 'error' );
	}

	$action    = sanitize_key( wp_unslash( $_POST['culturacsi_row_action'] ) );
	$target_id = absint( wp_unslash( $_POST['culturacsi_row_target_id'] ) );
	$post      = $target_id > 0 ? get_post( $target_id ) : null;
	if ( ! $post instanceof WP_Post || $post->post_type !== $post_type ) {
		return culturacsi_portal_notice( 'Elemento non trovato.', 'error' );
	}

	$user_id        = get_current_user_id();
	$is_site_admin  = current_user_can( 'manage_options' );
	$can_manage_item = culturacsi_portal_can_manage_post( $post, $user_id );

	if ( 'delete' === $action ) {
		if ( ! $is_site_admin && ( ! $allow_non_admin_delete || ! $can_manage_item ) ) {
			return culturacsi_portal_notice( 'Permessi insufficienti per eliminare.', 'error' );
		}
		$deleted = wp_trash_post( $target_id );
		if ( false === $deleted || null === $deleted ) {
			return culturacsi_portal_notice( 'Impossibile eliminare l\'elemento.', 'error' );
		}
		$success_msg = 'Elemento spostato nel cestino.';
		if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) ob_end_clean();
			wp_send_json_success( $success_msg, 200 );
		}
		return culturacsi_portal_notice( $success_msg, 'success' );
	}

	if ( ! $can_manage_item ) {
		return culturacsi_portal_notice( 'Permessi insufficienti.', 'error' );
	}

	if ( in_array( $action, array( 'approve', 'reject', 'hold' ), true ) ) {
		if ( ! $is_site_admin ) {
			return culturacsi_portal_notice( 'Solo i Site Admin possono moderare lo stato.', 'error' );
		}
		$status_map = array(
			'approve' => 'publish',
			'reject'  => 'draft',
			'hold'    => 'pending',
		);
		$new_status = isset( $status_map[ $action ] ) ? $status_map[ $action ] : '';
		if ( '' === $new_status ) {
			return culturacsi_portal_notice( 'Azione non valida.', 'error' );
		}

		global $wpdb;
		$updated = $wpdb->update(
			$wpdb->posts,
			array( 'post_status' => $new_status ),
			array( 'ID' => $target_id ),
			array( '%s' ),
			array( '%d' )
		);
		
		if ( false === $updated ) {
			return culturacsi_portal_notice( 'Errore durante l\'aggiornamento dello stato.', 'error' );
		}
		
		clean_post_cache( $target_id );
		wp_transition_post_status( $new_status, $post->post_status, $post );

		$labels = array(
			'approve' => 'Elemento approvato.',
			'reject'  => 'Elemento rifiutato.',
			'hold'    => 'Elemento messo in attesa.',
		);
		$success_msg = $labels[ $action ];
		if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) ob_end_clean();
			wp_send_json_success( $success_msg, 200 );
		}
		return culturacsi_portal_notice( $success_msg, 'success' );
	}

	return culturacsi_portal_notice( 'Azione non riconosciuta.', 'error' );
}

function culturacsi_portal_events_filters_from_request(): array {
	return array(
		'q'      => isset( $_GET['e_q'] ) ? sanitize_text_field( wp_unslash( $_GET['e_q'] ) ) : '',
		'date'   => isset( $_GET['e_date'] ) ? sanitize_text_field( wp_unslash( $_GET['e_date'] ) ) : '',
		'status' => isset( $_GET['e_status'] ) ? sanitize_key( wp_unslash( $_GET['e_status'] ) ) : 'all',
		'author' => isset( $_GET['e_author'] ) ? absint( $_GET['e_author'] ) : 0,
	);
}

function culturacsi_events_search_shortcode( array $atts = array() ): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}
	$filters      = culturacsi_portal_events_filters_from_request();
	$is_site_admin = current_user_can( 'manage_options' );
	$base_url     = culturacsi_portal_reserved_current_page_url();
	$authors      = get_users(
		array(
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'who'                 => 'authors',
			'has_published_posts' => array( 'event' ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	);
	$count_args = array(
		'post_type'      => 'event',
		'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( ! $is_site_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id > 0 ) {
			$count_args['meta_query'] = array(
				array(
					'key'     => 'organizer_association_id',
					'value'   => $assoc_id,
					'compare' => '=',
				),
			);
		} else {
			$count_args['author'] = get_current_user_id();
		}
	} elseif ( $filters['author'] > 0 ) {
		$count_args['author'] = $filters['author'];
	}
	if ( '' !== $filters['q'] ) {
		$count_args['s'] = $filters['q'];
	}
	if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
		$count_args['date_query'] = array(
			array(
				'year'     => (int) $matches[1],
				'monthnum' => (int) $matches[2],
			),
		);
	}
	if ( 'all' !== $filters['status'] ) {
		$allowed_status = array( 'publish', 'pending', 'draft', 'future', 'private' );
		if ( in_array( $filters['status'], $allowed_status, true ) ) {
			$count_args['post_status'] = array( $filters['status'] );
		}
	}
	$count_query = new WP_Query( $count_args );
	$found_count = (int) $count_query->found_posts;

	ob_start();
	?>
	<div class="assoc-search-panel assoc-events-search">
		<div class="assoc-search-head">
			<div class="assoc-search-meta">
				<h3 class="assoc-search-title">Ricerca Eventi</h3>
				<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) $found_count ); ?></p>
			</div>
			<p class="assoc-search-actions">
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
			</p>
		</div>
		<form id="assoc-events-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
			<p class="assoc-search-field is-q">
				<label for="e_q">Cerca</label>
				<input type="text" id="e_q" name="e_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Titolo o contenuto">
			</p>
			<p class="assoc-search-field is-date">
				<label for="e_date">Data</label>
				<input type="month" id="e_date" name="e_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
			</p>
			<?php if ( $is_site_admin ) : ?>
				<p class="assoc-search-field is-author">
					<label for="e_author">Autore</label>
					<select id="e_author" name="e_author">
						<option value="0">Tutti</option>
						<?php foreach ( $authors as $author ) : ?>
							<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="assoc-search-field is-status">
					<label for="e_status">Stato</label>
					<select id="e_status" name="e_status">
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
	<?php
	return (string) ob_get_clean();
}

function culturacsi_portal_news_panel_filters_from_request(): array {
	return array(
		'q'      => isset( $_GET['n_q'] ) ? sanitize_text_field( wp_unslash( $_GET['n_q'] ) ) : '',
		'date'   => isset( $_GET['n_date'] ) ? sanitize_text_field( wp_unslash( $_GET['n_date'] ) ) : '',
		'status' => isset( $_GET['n_status'] ) ? sanitize_key( wp_unslash( $_GET['n_status'] ) ) : 'all',
		'author' => isset( $_GET['n_author'] ) ? absint( $_GET['n_author'] ) : 0,
		'assoc'  => isset( $_GET['n_assoc'] ) ? absint( $_GET['n_assoc'] ) : 0,
	);
}

function culturacsi_news_panel_search_shortcode( array $atts = array() ): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}
	$filters       = culturacsi_portal_news_panel_filters_from_request();
	$is_site_admin = current_user_can( 'manage_options' );
	$base_url      = culturacsi_portal_reserved_current_page_url();
	$authors       = get_users(
		array(
			'orderby'             => 'display_name',
			'order'               => 'ASC',
			'who'                 => 'authors',
			'has_published_posts' => array( 'news' ),
			'fields'              => array( 'ID', 'display_name' ),
		)
	);
	$associations = get_transient( 'culturacsi_assoc_dropdown_ids' );
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
		set_transient( 'culturacsi_assoc_dropdown_ids', $associations, HOUR_IN_SECONDS );
	}
	$count_args = array(
		'post_type'      => 'news',
		'post_status'    => array( 'publish', 'pending', 'draft', 'future', 'private' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( ! $is_site_admin ) {
		$assoc_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		if ( $assoc_id > 0 ) {
			$count_args['meta_query'] = array(
				array(
					'key'     => 'organizer_association_id',
					'value'   => $assoc_id,
					'compare' => '=',
				),
			);
		} else {
			$count_args['author'] = get_current_user_id();
		}
	} elseif ( $filters['author'] > 0 ) {
		$count_args['author'] = $filters['author'];
	}
	if ( '' !== $filters['q'] ) {
		$count_args['s'] = $filters['q'];
	}
	if ( preg_match( '/^(\d{4})-(\d{2})$/', $filters['date'], $matches ) ) {
		$count_args['date_query'] = array(
			array(
				'year'     => (int) $matches[1],
				'monthnum' => (int) $matches[2],
			),
		);
	}
	if ( $filters['assoc'] > 0 ) {
		$allowed_ids = culturacsi_news_get_association_post_ids( $filters['assoc'] );
		$count_args['post__in'] = ! empty( $allowed_ids ) ? $allowed_ids : array( 0 );
	}
	if ( 'all' !== $filters['status'] ) {
		$allowed_status = array( 'publish', 'pending', 'draft', 'future', 'private' );
		if ( in_array( $filters['status'], $allowed_status, true ) ) {
			$count_args['post_status'] = array( $filters['status'] );
		}
	}
	$count_query = new WP_Query( $count_args );
	$found_count = (int) $count_query->found_posts;

	ob_start();
	?>
	<div class="assoc-search-panel assoc-news-search">
		<style>
		/* Inline guard to match Calendar layout even if global CSS cache lags */
		.assoc-news-search .assoc-search-form{display:grid;grid-auto-flow:row;gap:10px 10px;align-items:end;grid-template-columns:minmax(0,2fr) repeat(3,minmax(0,1fr))}
		.assoc-news-search .assoc-search-field{margin:0;grid-row:1;min-width:0}
		.assoc-news-search .assoc-search-field.is-q{grid-column:1}
		.assoc-news-search .assoc-search-field.is-date{grid-column:2}
		.assoc-news-search .assoc-search-field.is-author{grid-column:3}
		.assoc-news-search .assoc-search-field.is-status{grid-column:4}
		.assoc-news-search .assoc-search-field.is-association{grid-row:2;grid-column:1 / span 2}
		@media (max-width: 719px){
		  .assoc-news-search .assoc-search-form{grid-template-columns:minmax(0,1fr)}
		  .assoc-news-search .assoc-search-field{grid-column:1 / -1;grid-row:auto}
		}
		</style>
		<div class="assoc-search-head">
			<div class="assoc-search-meta">
				<h3 class="assoc-search-title">Ricerca Notizie</h3>
				<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) $found_count ); ?></p>
			</div>
			<p class="assoc-search-actions">
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
			</p>
		</div>
		<form id="assoc-news-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
			<p class="assoc-search-field is-q">
				<label for="n_q">Cerca</label>
				<input type="text" id="n_q" name="n_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Titolo o contenuto">
			</p>
			<p class="assoc-search-field is-date">
				<label for="n_date">Data</label>
				<input type="month" id="n_date" name="n_date" value="<?php echo esc_attr( $filters['date'] ); ?>">
			</p>
			<?php if ( $is_site_admin ) : ?>
				<p class="assoc-search-field is-author">
					<label for="n_author">Autore</label>
					<select id="n_author" name="n_author">
						<option value="0">Tutti</option>
						<?php foreach ( $authors as $author ) : ?>
							<option value="<?php echo esc_attr( (string) $author->ID ); ?>" <?php selected( $filters['author'], (int) $author->ID ); ?>><?php echo esc_html( $author->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="assoc-search-field is-association">
					<label for="n_assoc">Associazione</label>
					<select id="n_assoc" name="n_assoc">
						<option value="0">Tutte</option>
						<?php foreach ( $associations as $assoc_id ) : ?>
							<option value="<?php echo esc_attr( (string) $assoc_id ); ?>" <?php selected( $filters['assoc'], (int) $assoc_id ); ?>><?php echo esc_html( get_the_title( $assoc_id ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p class="assoc-search-field is-status">
					<label for="n_status">Stato</label>
					<select id="n_status" name="n_status">
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
	<?php
	return (string) ob_get_clean();
}

function culturacsi_portal_reserved_current_page_url(): string {
	$queried_id = get_queried_object_id();
	if ( $queried_id > 0 ) {
		$link = get_permalink( $queried_id );
		if ( is_string( $link ) && '' !== $link ) {
			return $link;
		}
	}
	$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	return home_url( '/' . $path . '/' );
}

function culturacsi_portal_normalize_posts_list( array $items ): array {
	$posts = array();
	foreach ( $items as $item ) {
		if ( $item instanceof WP_Post ) {
			$posts[] = $item;
			continue;
		}
		$post_id = is_numeric( $item ) ? absint( (string) $item ) : 0;
		if ( $post_id <= 0 ) {
			continue;
		}
		$post = get_post( $post_id );
		if ( $post instanceof WP_Post ) {
			$posts[] = $post;
		}
	}
	return $posts;
}

function culturacsi_portal_admin_user_form_url( int $user_id = 0 ): string {
	$url = home_url( '/area-riservata/utenti/nuovo/' );
	if ( $user_id > 0 ) {
		$url = add_query_arg( 'user_id', (string) $user_id, $url );
	}
	return $url;
}

function culturacsi_portal_admin_association_form_url( int $association_id = 0 ): string {
	$url = home_url( '/area-riservata/associazioni/nuova/' );
	if ( $association_id > 0 ) {
		$url = add_query_arg( 'association_id', (string) $association_id, $url );
	}
	return $url;
}

function culturacsi_portal_current_query_args(): array {
	$args = array();
	foreach ( $_GET as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( is_array( $value ) ) {
			continue;
		}
		$args[ sanitize_key( (string) $key ) ] = sanitize_text_field( wp_unslash( (string) $value ) );
	}
	return $args;
}

function culturacsi_portal_get_sort_state( string $sort_param, string $dir_param, string $default_sort, string $default_dir, array $allowed_sorts ): array {
	$sort = isset( $_GET[ $sort_param ] ) ? sanitize_key( wp_unslash( $_GET[ $sort_param ] ) ) : $default_sort; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$dir  = isset( $_GET[ $dir_param ] ) ? sanitize_key( wp_unslash( $_GET[ $dir_param ] ) ) : $default_dir; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! in_array( $sort, $allowed_sorts, true ) ) {
		$sort = $default_sort;
	}
	$dir = ( 'asc' === $dir ) ? 'asc' : 'desc';
	return array(
		'sort' => $sort,
		'dir'  => $dir,
	);
}

function culturacsi_portal_sortable_th( string $label, string $sort_key, string $current_sort, string $current_dir, string $sort_param, string $dir_param, string $base_url, string $class = '', array $reset_params = array() ): string {
	$query_args = culturacsi_portal_current_query_args();
	foreach ( $reset_params as $reset_param ) {
		unset( $query_args[ $reset_param ] );
	}

	$next_dir = ( $current_sort === $sort_key && 'asc' === $current_dir ) ? 'desc' : 'asc';
	$query_args[ $sort_param ] = $sort_key;
	$query_args[ $dir_param ]  = $next_dir;
	$url = add_query_arg( $query_args, $base_url );

	$is_active = ( $current_sort === $sort_key );
	$indicator = $is_active ? ( ( 'asc' === $current_dir ) ? '▲' : '▼' ) : '↕';
	$link_cls  = 'assoc-admin-sort-link' . ( $is_active ? ' is-active' : '' );

	$th_class_attr = '' !== trim( $class ) ? ' class="' . esc_attr( $class ) . '"' : '';
	return '<th' . $th_class_attr . '><a class="' . esc_attr( $link_cls ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '<span class="assoc-admin-sort-indicator">' . esc_html( $indicator ) . '</span></a></th>';
}

function culturacsi_portal_apply_user_role_moderation( int $user_id, string $role, string $moderation_state ): void {
	$user = get_user_by( 'id', $user_id );
	if ( ! $user instanceof WP_User ) {
		return;
	}

	$allowed_roles = array( 'administrator', 'association_manager', 'association_pending' );
	if ( ! in_array( $role, $allowed_roles, true ) ) {
		$role = 'association_manager';
	}

	if ( 'administrator' === $role ) {
		$user->set_role( 'administrator' );
		delete_user_meta( $user_id, 'assoc_pending_approval' );
		update_user_meta( $user_id, 'assoc_moderation_state', 'approved' );
		return;
	}

	if ( 'association_manager' === $role ) {
		$user->set_role( 'association_manager' );
		delete_user_meta( $user_id, 'assoc_pending_approval' );
		update_user_meta( $user_id, 'assoc_moderation_state', 'approved' );
		return;
	}

	$allowed_states = array( 'pending', 'hold', 'rejected' );
	$state          = in_array( $moderation_state, $allowed_states, true ) ? $moderation_state : 'pending';
	$user->set_role( 'association_pending' );
	update_user_meta( $user_id, 'assoc_pending_approval', '1' );
	update_user_meta( $user_id, 'assoc_moderation_state', $state );
}

function culturacsi_portal_users_filters_from_request(): array {
	return array(
		'q'      => isset( $_GET['u_q'] ) ? sanitize_text_field( wp_unslash( $_GET['u_q'] ) ) : '',
		'role'   => isset( $_GET['u_role'] ) ? sanitize_key( wp_unslash( $_GET['u_role'] ) ) : 'all',
		'status' => isset( $_GET['u_status'] ) ? sanitize_key( wp_unslash( $_GET['u_status'] ) ) : 'all',
	);
}

function culturacsi_portal_user_matches_status( WP_User $user, string $status ): bool {
	if ( 'all' === $status || '' === $status ) {
		return true;
	}
	if ( user_can( $user, 'manage_options' ) ) {
		return 'admin' === $status;
	}
	$is_pending = in_array( 'association_pending', (array) $user->roles, true ) || '1' === (string) get_user_meta( $user->ID, 'assoc_pending_approval', true );
	$state      = (string) get_user_meta( $user->ID, 'assoc_moderation_state', true );
	if ( 'pending' === $status ) {
		return $is_pending;
	}
	if ( 'approved' === $status ) {
		return ! $is_pending && in_array( 'association_manager', (array) $user->roles, true );
	}
	if ( 'hold' === $status ) {
		return $is_pending && 'hold' === $state;
	}
	if ( 'rejected' === $status ) {
		return $is_pending && 'rejected' === $state;
	}
	return false;
}

function culturacsi_users_search_shortcode( array $atts = array() ): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}
	$filters      = culturacsi_portal_users_filters_from_request();
	$is_site_admin = current_user_can( 'manage_options' );
	$base_url     = culturacsi_portal_reserved_current_page_url();
	if ( $is_site_admin ) {
		$count_users = get_users(
			array(
				'orderby' => 'registered',
				'order'   => 'DESC',
				'number'  => 1000,
			)
		);
	} else {
		$current_user = wp_get_current_user();
		$count_users  = $current_user instanceof WP_User ? array( $current_user ) : array();
	}
	$search_q = function_exists( 'mb_strtolower' ) ? mb_strtolower( $filters['q'] ) : strtolower( $filters['q'] );
	$count_users = array_values(
		array_filter(
			$count_users,
			static function( $user ) use ( $filters, $search_q, $is_site_admin ): bool {
				if ( ! $user instanceof WP_User ) {
					return false;
				}
				if ( '' !== $search_q ) {
					$haystack = trim( implode( ' ', array( (string) $user->display_name, (string) $user->user_email, (string) $user->user_login ) ) );
					$haystack = function_exists( 'mb_strtolower' ) ? mb_strtolower( $haystack ) : strtolower( $haystack );
					if ( false === strpos( $haystack, $search_q ) ) {
						return false;
					}
				}
				if ( $is_site_admin && 'all' !== $filters['role'] && ! in_array( $filters['role'], (array) $user->roles, true ) ) {
					return false;
				}
				return culturacsi_portal_user_matches_status( $user, $filters['status'] );
			}
		)
	);
	$found_count = count( $count_users );

	ob_start();
	?>
	<div class="assoc-search-panel assoc-users-search">
		<div class="assoc-search-head">
			<div class="assoc-search-meta">
				<h3 class="assoc-search-title">Ricerca Utenti</h3>
				<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) $found_count ); ?></p>
			</div>
			<p class="assoc-search-actions">
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
			</p>
		</div>
		<form id="assoc-users-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
			<p class="assoc-search-field is-q">
				<label for="u_q">Cerca</label>
				<input type="text" id="u_q" name="u_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Nome, username o email">
			</p>
			<?php if ( $is_site_admin ) : ?>
				<p class="assoc-search-field is-role">
					<label for="u_role">Ruolo</label>
					<select id="u_role" name="u_role">
						<option value="all" <?php selected( $filters['role'], 'all' ); ?>>Tutti</option>
						<option value="administrator" <?php selected( $filters['role'], 'administrator' ); ?>>Amministratore Sito</option>
						<option value="association_manager" <?php selected( $filters['role'], 'association_manager' ); ?>>Amministratore Associazione</option>
					</select>
				</p>
				<p class="assoc-search-field is-status">
					<label for="u_status">Stato</label>
					<select id="u_status" name="u_status">
						<option value="all" <?php selected( $filters['status'], 'all' ); ?>>Tutti</option>
						<option value="approved" <?php selected( $filters['status'], 'approved' ); ?>>Approvato</option>
						<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>>In attesa</option>
						<option value="rejected" <?php selected( $filters['status'], 'rejected' ); ?>>Revocato</option>
					</select>
				</p>
			<?php endif; ?>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

function culturacsi_portal_associations_filters_from_request(): array {
	return array(
		'q'        => isset( $_GET['a_q'] ) ? sanitize_text_field( wp_unslash( $_GET['a_q'] ) ) : '',
		'status'   => isset( $_GET['a_status'] ) ? sanitize_key( wp_unslash( $_GET['a_status'] ) ) : 'all',
		'cat'      => isset( $_GET['a_cat'] ) ? absint( $_GET['a_cat'] ) : 0,
		'province' => isset( $_GET['a_province'] ) ? sanitize_text_field( wp_unslash( $_GET['a_province'] ) ) : '',
		'region'   => isset( $_GET['a_region'] ) ? sanitize_text_field( wp_unslash( $_GET['a_region'] ) ) : '',
		'city'     => isset( $_GET['a_city'] ) ? sanitize_text_field( wp_unslash( $_GET['a_city'] ) ) : '',
	);
}

function culturacsi_portal_association_collect_meta_values( array $post_ids, array $meta_keys ): array {
	$values = array();
	foreach ( $post_ids as $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			continue;
		}
		foreach ( $meta_keys as $meta_key ) {
			$raw = trim( (string) get_post_meta( $post_id, $meta_key, true ) );
			if ( '' !== $raw ) {
				$values[ $raw ] = true;
				break;
			}
		}
	}
	$list = array_keys( $values );
	natcasesort( $list );
	return array_values( $list );
}

function culturacsi_portal_association_location_meta_query( array $filters ): array {
	$meta_query = array( 'relation' => 'AND' );
	if ( '' !== $filters['province'] ) {
		$meta_query[] = array(
			'key'     => 'province',
			'value'   => $filters['province'],
			'compare' => '=',
		);
	}
	if ( '' !== $filters['city'] ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => 'city',
				'value'   => $filters['city'],
				'compare' => '=',
			),
			array(
				'key'     => 'comune',
				'value'   => $filters['city'],
				'compare' => '=',
			),
		);
	}
	if ( '' !== $filters['region'] ) {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => 'region',
				'value'   => $filters['region'],
				'compare' => '=',
			),
			array(
				'key'     => 'regione',
				'value'   => $filters['region'],
				'compare' => '=',
			),
		);
	}
	return ( count( $meta_query ) > 1 ) ? $meta_query : array();
}

function culturacsi_associations_search_shortcode( array $atts = array() ): string {
	if ( ! culturacsi_portal_can_access() ) {
		return '';
	}
	$filters       = culturacsi_portal_associations_filters_from_request();
	$is_site_admin = current_user_can( 'manage_options' );
	$base_url      = culturacsi_portal_reserved_current_page_url();
	$term_args = array(
		'taxonomy'   => 'activity_category',
		'hide_empty' => false,
	);
	if ( function_exists( 'culturacsi_activity_tree_term_ids' ) ) {
		$tree_term_ids = culturacsi_activity_tree_term_ids();
		if ( ! empty( $tree_term_ids ) ) {
			$term_args['include'] = $tree_term_ids;
		}
	}
	$terms = get_terms( $term_args );
	$count_args = array(
		'post_type'      => 'association',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => 1,
		'fields'         => 'ids',
	);
	if ( '' !== $filters['q'] ) {
		$count_args['s'] = $filters['q'];
	}
	if ( $is_site_admin && 'all' !== $filters['status'] && in_array( $filters['status'], array( 'publish', 'pending', 'draft', 'private' ), true ) ) {
		$count_args['post_status'] = array( $filters['status'] );
	}
	if ( $filters['cat'] > 0 ) {
		$count_args['tax_query'] = array(
			array(
				'taxonomy' => 'activity_category',
				'field'    => 'term_id',
				'terms'    => array( (int) $filters['cat'] ),
			),
		);
	}
	if ( ! $is_site_admin ) {
		$managed_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		$count_args['post__in'] = $managed_id > 0 ? array( $managed_id ) : array( 0 );
	}
	$location_meta_query = culturacsi_portal_association_location_meta_query( $filters );
	if ( ! empty( $location_meta_query ) ) {
		$count_args['meta_query'] = $location_meta_query;
	}
	$count_query = new WP_Query( $count_args );
	$found_count = (int) $count_query->found_posts;

	$options_args = array(
		'post_type'      => 'association',
		'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'fields'         => 'ids',
	);
	
	if ( ! $is_site_admin ) {
		$managed_id = culturacsi_portal_get_managed_association_id( get_current_user_id() );
		$options_args['post__in'] = $managed_id > 0 ? array( $managed_id ) : array( 0 );
		$option_post_ids  = get_posts( $options_args );
		$province_options = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'province' ) );
		$region_options   = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'region', 'regione' ) );
		$city_options     = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'city', 'comune' ) );
	} else {
		// Site admins query the entire database, which is incredibly slow. We heavily cache this.
		$transient_key  = 'culturacsi_association_dropdowns_v1';
		$cached_options = get_transient( $transient_key );
		if ( false === $cached_options ) {
			$option_post_ids  = get_posts( $options_args );
			$province_options = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'province' ) );
			$region_options   = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'region', 'regione' ) );
			$city_options     = culturacsi_portal_association_collect_meta_values( $option_post_ids, array( 'city', 'comune' ) );
			
			$cached_options = array(
				'province' => $province_options,
				'region'   => $region_options,
				'city'     => $city_options,
			);
			set_transient( $transient_key, $cached_options, 12 * HOUR_IN_SECONDS );
		} else {
			$province_options = $cached_options['province'];
			$region_options   = $cached_options['region'];
			$city_options     = $cached_options['city'];
		}
	}

	ob_start();
	?>
	<div class="assoc-search-panel assoc-associations-search">
		<div class="assoc-search-head">
			<div class="assoc-search-meta">
				<h3 class="assoc-search-title">Ricerca Associazioni</h3>
				<p class="assoc-search-count">Elementi trovati: <?php echo esc_html( (string) $found_count ); ?></p>
			</div>
			<p class="assoc-search-actions">
				<a class="button" href="<?php echo esc_url( $base_url ); ?>">Azzera</a>
			</p>
		</div>
		<form id="assoc-associations-search-form" method="get" action="<?php echo esc_url( $base_url ); ?>" class="assoc-search-form">
			<p class="assoc-search-field is-q">
				<label for="a_q">Cerca</label>
				<input type="text" id="a_q" name="a_q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Nome associazione">
			</p>
			<p class="assoc-search-field is-category">
				<label for="a_cat">Categoria Attivita</label>
				<select id="a_cat" name="a_cat">
					<option value="0">Tutte</option>
					<?php
					if ( ! is_wp_error( $terms ) ) :
						foreach ( $terms as $term ) :
							?>
							<option value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php selected( $filters['cat'], (int) $term->term_id ); ?>><?php echo esc_html( $term->name ); ?></option>
							<?php
						endforeach;
					endif;
					?>
				</select>
			</p>
			<p class="assoc-search-field is-province">
				<label for="a_province">Provincia</label>
				<select id="a_province" name="a_province">
					<option value="">Tutte</option>
					<?php foreach ( $province_options as $province_value ) : ?>
						<option value="<?php echo esc_attr( $province_value ); ?>" <?php selected( $filters['province'], $province_value ); ?>><?php echo esc_html( $province_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="assoc-search-field is-region">
				<label for="a_region">Regione</label>
				<select id="a_region" name="a_region">
					<option value="">Tutte</option>
					<?php foreach ( $region_options as $region_value ) : ?>
						<option value="<?php echo esc_attr( $region_value ); ?>" <?php selected( $filters['region'], $region_value ); ?>><?php echo esc_html( $region_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="assoc-search-field is-city">
				<label for="a_city">Citta</label>
				<select id="a_city" name="a_city">
					<option value="">Tutte</option>
					<?php foreach ( $city_options as $city_value ) : ?>
						<option value="<?php echo esc_attr( $city_value ); ?>" <?php selected( $filters['city'], $city_value ); ?>><?php echo esc_html( $city_value ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php if ( $is_site_admin ) : ?>
				<p class="assoc-search-field is-status">
					<label for="a_status">Stato</label>
					<select id="a_status" name="a_status">
						<option value="all" <?php selected( $filters['status'], 'all' ); ?>>Tutti</option>
						<option value="publish" <?php selected( $filters['status'], 'publish' ); ?>>Pubblicato</option>
						<option value="pending" <?php selected( $filters['status'], 'pending' ); ?>>In attesa</option>
						<option value="draft" <?php selected( $filters['status'], 'draft' ); ?>>Bozza</option>
						<option value="private" <?php selected( $filters['status'], 'private' ); ?>>Privato</option>
					</select>
				</p>
			<?php endif; ?>
		</form>
	</div>
	<?php
	return (string) ob_get_clean();
}

function culturacsi_portal_user_approval_label( WP_User $user ): string {
	$is_approved = culturacsi_portal_is_user_approved( $user );
	if ( $is_approved ) {
		return 'Approvato';
	}
	
	$state = (string) get_user_meta( $user->ID, 'assoc_moderation_state', true );
	if ( 'rejected' === $state ) {
		return 'Revocato';
	}
	return 'In attesa';
}

function culturacsi_portal_user_approval_class( WP_User $user ): string {
	if ( culturacsi_portal_is_user_approved( $user ) ) {
		return 'status-approved';
	}

	$state = (string) get_user_meta( $user->ID, 'assoc_moderation_state', true );
	if ( 'rejected' === $state ) {
		return 'status-rejected';
	}
	if ( 'hold' === $state ) {
		return 'status-hold';
	}

	return 'status-pending';
}

function culturacsi_portal_is_user_approved( WP_User $user ): bool {
	if ( user_can( $user, 'manage_options' ) ) {
		return true;
	}
	return in_array( 'association_manager', (array) $user->roles, true ) && '1' !== (string) get_user_meta( $user->ID, 'assoc_pending_approval', true );
}

function culturacsi_portal_process_user_row_action(): string {
	if ( 'POST' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
		return '';
	}
	if ( ! isset( $_POST['culturacsi_row_action_submit'], $_POST['culturacsi_row_context'], $_POST['culturacsi_row_action'], $_POST['culturacsi_row_target_id'] ) ) {
		return '';
	}
	if ( 'user' !== sanitize_key( wp_unslash( $_POST['culturacsi_row_context'] ) ) ) {
		return '';
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return culturacsi_portal_notice( 'Permessi insufficienti.', 'error' );
	}
	if ( ! isset( $_POST['culturacsi_row_action_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['culturacsi_row_action_nonce'] ) ), 'culturacsi_row_action_user' ) ) {
		$error_msg = 'Verifica di sicurezza non valida.';
		if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) ob_end_clean();
			wp_send_json_error( $error_msg, 403 );
		}
		return culturacsi_portal_notice( $error_msg, 'error' );
	}

	$action    = sanitize_key( wp_unslash( $_POST['culturacsi_row_action'] ) );
	$target_id = absint( wp_unslash( $_POST['culturacsi_row_target_id'] ) );
	$user      = $target_id > 0 ? get_user_by( 'id', $target_id ) : false;
	if ( ! $user instanceof WP_User ) {
		return culturacsi_portal_notice( 'Utente non trovato.', 'error' );
	}
	if ( user_can( $user, 'manage_options' ) && (int) $user->ID !== get_current_user_id() ) {
		return culturacsi_portal_notice( 'Non puoi modificare lo stato di un altro Site Admin.', 'error' );
	}

	if ( 'delete' === $action ) {
		if ( (int) $user->ID === get_current_user_id() ) {
			return culturacsi_portal_notice( 'Non puoi eliminare il tuo utente.', 'error' );
		}
		if ( user_can( $user, 'manage_options' ) ) {
			return culturacsi_portal_notice( 'I Site Admin possono essere eliminati solo dal pannello utente standard di WordPress. Deve sempre esserci almeno un Site Admin.', 'error' );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$deleted = wp_delete_user( (int) $user->ID, get_current_user_id() );
		if ( ! $deleted ) {
			return culturacsi_portal_notice( 'Impossibile eliminare l\'utente.', 'error' );
		}
		culturacsi_log_event( 'delete_user', 'user', (int) $user->ID, "User deleted: " . $user->user_login );
		$success_msg = 'Utente eliminato.';
		if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) ob_end_clean();
			wp_send_json_success( $success_msg, 200 );
		}
		return culturacsi_portal_notice( $success_msg, 'success' );
	}

	if ( in_array( $action, array( 'approve', 'reject', 'hold' ), true ) ) {
		if ( user_can( $user, 'manage_options' ) ) {
			return culturacsi_portal_notice( 'Azione non consentita su questo utente.', 'error' );
		}
		if ( 'approve' === $action ) {
			$user->set_role( 'association_manager' );
			delete_user_meta( (int) $user->ID, 'assoc_pending_approval' );
			update_user_meta( (int) $user->ID, 'assoc_moderation_state', 'approved' );
			culturacsi_log_event( 'approve_user', 'user', (int) $user->ID, "User approved: " . $user->user_login );
			$success_msg = 'Utente approvato.';
			if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
				while ( ob_get_level() > 0 ) ob_end_clean();
				wp_send_json_success( $success_msg, 200 );
			}
			return culturacsi_portal_notice( $success_msg, 'success' );
		}
		$user->set_role( 'association_pending' );
		update_user_meta( (int) $user->ID, 'assoc_pending_approval', '1' );
		$new_state = ( 'reject' === $action ? 'rejected' : 'hold' );
		update_user_meta( (int) $user->ID, 'assoc_moderation_state', $new_state );
		culturacsi_log_event( 'moderate_user', 'user', (int) $user->ID, "Action: $action ($new_state) for " . $user->user_login );
		$success_msg = 'Stato utente aggiornato.';
		if ( isset( $_REQUEST['is_portal_ajax'] ) && '1' === (string) $_REQUEST['is_portal_ajax'] ) {
			while ( ob_get_level() > 0 ) ob_end_clean();
			wp_send_json_success( $success_msg, 200 );
		}
		return culturacsi_portal_notice( $success_msg, 'success' );
	}

	return culturacsi_portal_notice( 'Azione non riconosciuta.', 'error' );
}

function culturacsi_portal_force_shortcode_registry(): void {
	add_shortcode( 'assoc_reserved_nav', 'culturacsi_portal_reserved_nav_shortcode' );
	add_shortcode( 'assoc_dashboard', 'culturacsi_portal_dashboard_shortcode' );
	add_shortcode( 'culturacsi_events_search', 'culturacsi_events_search_shortcode' );
	add_shortcode( 'culturacsi_eventi_search', 'culturacsi_events_search_shortcode' );
	add_shortcode( 'culturacsi_events_panel_search', 'culturacsi_events_search_shortcode' );
	add_shortcode( 'assoc_events_list', 'culturacsi_portal_events_list_shortcode' );
	add_shortcode( 'assoc_event_form', 'culturacsi_portal_event_form_shortcode' );
	add_shortcode( 'assoc_content_entries_list', 'culturacsi_portal_content_entries_list_shortcode' );
	add_shortcode( 'assoc_content_entry_form', 'culturacsi_portal_content_entry_form_shortcode' );
	add_shortcode( 'assoc_content_sections_manager', 'culturacsi_portal_content_sections_manager_shortcode' );
	add_shortcode( 'assoc_sezioni_manager', 'culturacsi_portal_content_sections_manager_shortcode' );
	add_shortcode( 'assoc_contenuti_list', 'culturacsi_portal_content_entries_list_shortcode' );
	add_shortcode( 'assoc_contenuto_form', 'culturacsi_portal_content_entry_form_shortcode' );
	add_shortcode( 'culturacsi_news_panel_search', 'culturacsi_news_panel_search_shortcode' );
	add_shortcode( 'culturacsi_notizie_search', 'culturacsi_news_panel_search_shortcode' );
	add_shortcode( 'culturacsi_notizie_panel_search', 'culturacsi_news_panel_search_shortcode' );
	add_shortcode( 'assoc_news_list', 'culturacsi_portal_news_list_shortcode' );
	add_shortcode( 'assoc_news_form', 'culturacsi_portal_news_form_shortcode' );
	add_shortcode( 'assoc_users_form', 'culturacsi_portal_users_form_shortcode' );
	add_shortcode( 'assoc_utenti_form', 'culturacsi_portal_users_form_shortcode' );
	add_shortcode( 'assoc_associations_form', 'culturacsi_portal_associations_form_shortcode' );
	add_shortcode( 'assoc_associazioni_form', 'culturacsi_portal_associations_form_shortcode' );
	add_shortcode( 'culturacsi_users_search', 'culturacsi_users_search_shortcode' );
	add_shortcode( 'culturacsi_utenti_search', 'culturacsi_users_search_shortcode' );
	add_shortcode( 'culturacsi_users_panel_search', 'culturacsi_users_search_shortcode' );
	add_shortcode( 'culturacsi_associations_search', 'culturacsi_associations_search_shortcode' );
	add_shortcode( 'culturacsi_associazioni_search', 'culturacsi_associations_search_shortcode' );
	add_shortcode( 'culturacsi_associations_panel_search', 'culturacsi_associations_search_shortcode' );
	add_shortcode( 'assoc_users_list', 'culturacsi_portal_users_list_shortcode' );
	add_shortcode( 'assoc_associations_list', 'culturacsi_portal_associations_list_shortcode' );
	add_shortcode( 'assoc_user_profile_form', 'culturacsi_portal_user_profile_shortcode' );
	add_shortcode( 'assoc_profile_form', 'culturacsi_portal_association_form_shortcode' );
	add_shortcode( 'assoc_association_form', 'culturacsi_portal_association_form_shortcode' );
	add_shortcode( 'assoc_admin_control_panel', 'culturacsi_portal_admin_control_panel_shortcode' );
	add_shortcode( 'assoc_cronologia_list', 'culturacsi_portal_cronologia_list_shortcode' );
}
add_action( 'init', 'culturacsi_portal_force_shortcode_registry', 20 );

/**
 * Force frontend portal assets on reserved-area routes.
 */
function culturacsi_portal_enqueue_reserved_assets(): void {
	if ( is_admin() ) {
		return;
	}

	$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	$is_reserved_route = ( 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' ) );
	$is_login_modal_redirect = isset( $_GET['area_riservata_login'] ) && '1' === (string) $_GET['area_riservata_login'];
	
	if ( ! $is_reserved_route && ! $is_login_modal_redirect ) {
		return;
	}

	$css_file_path = WP_PLUGIN_DIR . '/assoc-portal/assets/css/portal.css';
	if ( file_exists( $css_file_path ) ) {
		wp_enqueue_style(
			'assoc-portal-style-forced',
			plugins_url( 'assoc-portal/assets/css/portal.css' ),
			array(),
			(string) filemtime( $css_file_path )
		);
		$dynamic_css = '';
		$modified_fields_csv = '';
		if ( isset( $_GET['event_id'] ) ) {
			$modified_fields_csv = (string) get_post_meta( (int) $_GET['event_id'], '_assoc_modified_fields_list', true );
		} elseif ( isset( $_GET['news_id'] ) ) {
			$modified_fields_csv = (string) get_post_meta( (int) $_GET['news_id'], '_assoc_modified_fields_list', true );
		} elseif ( isset( $_GET['association_id'] ) ) {
			$modified_fields_csv = (string) get_post_meta( (int) $_GET['association_id'], '_assoc_modified_fields_list', true );
		} elseif ( isset( $_GET['user_id'] ) ) {
			$modified_fields_csv = (string) get_user_meta( (int) $_GET['user_id'], '_assoc_modified_fields_list', true );
		}
		if ( current_user_can( 'manage_options' ) && '' !== $modified_fields_csv ) {
			$fields = array_filter( array_unique( explode( ',', $modified_fields_csv ) ) );
			$css_rules = array();
			foreach ( $fields as $f ) {
				$css_rules[] = '[name="' . esc_attr( $f ) . '"]';
				if ( 'post_content' === $f || 'description' === $f ) {
					$css_rules[] = '.wp-editor-wrap';
				}
				if ( 'tax_input[activity_category][]' === $f ) {
					$css_rules[] = '.category-checklist';
				}
			}
			if ( ! empty( $css_rules ) ) {
				$dynamic_css = implode( ', ', $css_rules ) . ' { outline: 2px solid #ef4444 !important; outline-offset: 2px; box-shadow: 0 0 8px rgba(239, 68, 68, 0.4) !important; border-color: #ef4444 !important; background-color: #fef2f2 !important; }';
			}
		}

		wp_add_inline_style(
			'assoc-portal-style-forced',
			'body.assoc-reserved-page #masthead, body.assoc-reserved-page header.site-header {display:block !important;} body.assoc-reserved-page .assoc-reserved-nav {margin-top: 8px;} ' .
			'.assoc-admin-table tr.is-pending-approval td { background-color: #fffbeb !important; } ' .
			'.assoc-admin-table tr.is-pending-approval td:first-child { box-shadow: inset 4px 0 0 #f59e0b !important; } ' .
			$dynamic_css
		);
	}
}
add_action( 'wp_enqueue_scripts', 'culturacsi_portal_enqueue_reserved_assets', 9999 );

add_filter(
	'body_class',
	static function( array $classes ): array {
		$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
		if ( 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' ) ) {
			$classes[] = 'assoc-ui-theme';
			$classes[] = 'assoc-reserved-page';
		}
		return $classes;
	},
	20
);

/**
 * Guaranteed reserved-area UI styles in case theme/plugin enqueue stack is inconsistent.
 */
function culturacsi_portal_render_modified_fields_script(): void {
	$path = trim( (string) wp_parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );
	$is_reserved_route = ( 'area-riservata' === $path || 0 === strpos( $path, 'area-riservata/' ) );
	if ( ! $is_reserved_route ) {
		return;
	}
	echo "<script>
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.assoc-portal-form');
    forms.forEach(form => {
        let hiddenInput = form.querySelector('input[name=\"_assoc_modified_fields\"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = '_assoc_modified_fields';
            hiddenInput.value = '';
            form.appendChild(hiddenInput);
        }
        let changedBoxes = new Set();
        const recordChange = function(e) {
            if (e.target && e.target.name && e.target.name !== '_assoc_modified_fields' && !e.target.name.startsWith('culturacsi_')) {
                changedBoxes.add(e.target.name);
                hiddenInput.value = Array.from(changedBoxes).join(',');
            }
        };
        form.addEventListener('input', recordChange);
        form.addEventListener('change', recordChange);
    });
});
</script>";
}
add_action( 'wp_footer', 'culturacsi_portal_render_modified_fields_script', 9999 );

/**
 * Native wp-admin highlighting Support
 */
function culturacsi_portal_native_admin_head_css(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;
	
	$modified_fields_csv = '';
	if ( isset( $_GET['post'] ) ) {
		$modified_fields_csv = (string) get_post_meta( (int) $_GET['post'], '_assoc_modified_fields_list', true );
	} elseif ( isset( $_GET['user_id'] ) ) {
		$modified_fields_csv = (string) get_user_meta( (int) $_GET['user_id'], '_assoc_modified_fields_list', true );
	}

	if ( '' !== $modified_fields_csv ) {
		$fields = array_filter( array_unique( explode( ',', $modified_fields_csv ) ) );
		$css_rules = array();
		foreach ( $fields as $f ) {
			$css_rules[] = '[name="' . esc_attr( $f ) . '"]';
			if ( 'post_content' === $f || 'description' === $f ) {
				$css_rules[] = '.wp-editor-wrap';
			}
			if ( 'tax_input[activity_category][]' === $f ) {
				$css_rules[] = '#activity_categorydiv';
			}
		}
		if ( ! empty( $css_rules ) ) {
			echo '<style>' . implode( ', ', $css_rules ) . ' { outline: 3px solid #ef4444 !important; outline-offset: 2px; box-shadow: 0 0 10px rgba(239, 68, 68, 0.4) !important; background-color: #fef2f2 !important; }</style>';
		}
	}
	
	// Add pure CSS arrows for all selects universally in WP admin that correspond to our fields
	echo '<style>
		select {
			appearance: none;
			-webkit-appearance: none;
			-moz-appearance: none;
			background-image: url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 12 8\'%3E%3Cpath d=\'M1 1l5 5 5-5\' fill=\'none\' stroke=\'%23355a86\' stroke-width=\'1.7\' stroke-linecap=\'round\' stroke-linejoin=\'round\'/%3E%3C/svg%3E") !important;
			background-repeat: no-repeat !important;
			background-position: right 11px center !important;
			background-size: 12px 8px !important;
			padding-right: 34px !important;
		}
	</style>';
}
add_action( 'admin_head', 'culturacsi_portal_native_admin_head_css' );
