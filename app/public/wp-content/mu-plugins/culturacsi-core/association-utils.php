<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Association-related utilities and data assignments.
 */

if ( ! function_exists( 'culturacsi_portal_render_association_selection_field' ) ) {
	/**
	 * Render a standardized association selection field for portal forms.
	 *
	 * @param int    $current_val The currently selected association ID.
	 * @param string $field_id    The HTML ID and name attribute for the input.
	 * @param string $label       The label text.
	 * @param string $tip         Tooltip text.
	 * @return string The rendered HTML for the selection field.
	 */
	function culturacsi_portal_render_association_selection_field( int $current_val, string $field_id = 'organizer_association_id', string $label = 'Associazione Referente', string $tip = 'L\'associazione che gestisce questo contenuto.' ): string {
		$is_admin         = current_user_can( 'manage_options' );
		$user_id          = get_current_user_id();
		$managed_assoc_id = culturacsi_portal_get_managed_association_id( $user_id );
		$val_to_use       = ( $current_val > 0 ) ? $current_val : $managed_assoc_id;

		ob_start();
		if ( $is_admin ) {
			// Use transient cache for association list to improve performance
			$transient_key   = 'culturacsi_assoc_list_all_' . get_current_blog_id();
			$association_ids = get_transient( $transient_key );

			if ( false === $association_ids ) {
				$association_ids = get_posts(
					array(
						'post_type'      => 'association',
						'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
						'posts_per_page' => -1,
						'orderby'        => 'title',
						'order'          => 'ASC',
						'fields'         => 'ids',
					)
				);
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
}

if ( ! function_exists( 'culturacsi_auto_assign_association_to_post' ) ) {
	/**
	 * Force Association ID on post save if author is linked to an association.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
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
}

if ( ! function_exists( 'culturacsi_sync_all_author_content_association' ) ) {
	/**
	 * When a user is assigned to an association, update all their existing content.
	 *
	 * Uses a direct DB UPDATE instead of get_posts(-1) + per-row update_post_meta to
	 * avoid loading an unbounded list of post objects into memory and issuing N+1 queries.
	 * Existing meta rows are updated in one statement; missing meta rows are inserted in
	 * a second batch INSERT … ON DUPLICATE KEY UPDATE via wpdb::replace (one row at a time
	 * only for posts that don't yet have the key, keeping the query count proportional to
	 * the number of NEW rows rather than ALL rows).
	 *
	 * @param int    $meta_id     ID of the meta entry.
	 * @param int    $object_id   Object ID.
	 * @param string $meta_key    Meta key.
	 * @param mixed  $_meta_value Meta value.
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
}

if ( ! function_exists( 'culturacsi_add_association_metabox' ) ) {
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
}

if ( ! function_exists( 'culturacsi_render_association_assignment_metabox' ) ) {
	/**
	 * Renders the HTML for the association assignment metabox.
	 *
	 * @param WP_Post $post The post object being edited.
	 * @return void
	 */
	function culturacsi_render_association_assignment_metabox( $post ) {
		$current_assoc = (int) get_post_meta( $post->ID, 'organizer_association_id', true );
		$associations  = get_posts(
			array(
				'post_type'      => 'association',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
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
}

if ( ! function_exists( 'culturacsi_save_association_metabox' ) ) {
	/**
	 * Saves the association assignment from the metabox.
	 *
	 * @param int $post_id The ID of the post being saved.
	 * @return void
	 */
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
}

if ( ! function_exists( 'culturacsi_admin_user_association_field' ) ) {
	/**
	 * Add Association field to user profile for admins.
	 *
	 * @param WP_User $user The user object being edited.
	 * @return void
	 */
	function culturacsi_admin_user_association_field( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$current_assoc_id = (int) get_user_meta( $user->ID, 'association_post_id', true );
		$associations     = get_posts(
			array(
				'post_type'      => 'association',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'fields'         => 'ids',
			)
		);
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
}

if ( ! function_exists( 'culturacsi_save_admin_user_association_field' ) ) {
	/**
	 * Saves the association field from the user profile.
	 *
	 * @param int $user_id The ID of the user being saved.
	 * @return void
	 */
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
}
