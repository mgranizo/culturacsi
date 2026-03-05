<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'culturacsi_moderation_target_post_types' ) ) {
	/**
	 * Post types that must pass site-admin approval when managed by associations.
	 *
	 * @return array<int,string>
	 */
	function culturacsi_moderation_target_post_types(): array {
		return array( 'event', 'news', 'association', 'csi_content_entry' );
	}
}

if ( ! function_exists( 'culturacsi_moderation_is_association_context' ) ) {
	/**
	 * True when current write action comes from non-site-admin association users.
	 */
	function culturacsi_moderation_is_association_context(): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		if ( current_user_can( 'manage_options' ) ) {
			return false;
		}
		return current_user_can( 'association_manager' ) || current_user_can( 'association_pending' );
	}
}

if ( ! function_exists( 'culturacsi_moderation_force_pending_status' ) ) {
	/**
	 * Force pending status before save for association-managed content.
	 *
	 * @param array<string,mixed> $data                Sanitized post data.
	 * @param array<string,mixed> $postarr             Raw post data.
	 * @param array<string,mixed> $unsanitized_postarr Raw unslashed post data.
	 * @param bool                $update              Whether this is an update.
	 * @return array<string,mixed>
	 */
	function culturacsi_moderation_force_pending_status( array $data, array $postarr, array $unsanitized_postarr, bool $update ): array {
		unset( $unsanitized_postarr, $update );

		if ( ! culturacsi_moderation_is_association_context() ) {
			return $data;
		}

		$post_type = '';
		if ( isset( $data['post_type'] ) ) {
			$post_type = sanitize_key( (string) $data['post_type'] );
		} elseif ( isset( $postarr['post_type'] ) ) {
			$post_type = sanitize_key( (string) $postarr['post_type'] );
		}
		if ( ! in_array( $post_type, culturacsi_moderation_target_post_types(), true ) ) {
			return $data;
		}

		$status = isset( $data['post_status'] ) ? sanitize_key( (string) $data['post_status'] ) : '';
		if ( in_array( $status, array( 'auto-draft', 'inherit', 'trash' ), true ) ) {
			return $data;
		}

		$data['post_status'] = 'pending';
		return $data;
	}
}
add_filter( 'wp_insert_post_data', 'culturacsi_moderation_force_pending_status', 999, 4 );

if ( ! function_exists( 'culturacsi_moderation_hard_enforce_pending' ) ) {
	/**
	 * Final guard after save in case another callback changed status.
	 */
	function culturacsi_moderation_hard_enforce_pending( int $post_id, WP_Post $post ): void {
		if ( ! culturacsi_moderation_is_association_context() ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, culturacsi_moderation_target_post_types(), true ) ) {
			return;
		}

		$current_status = (string) get_post_status( $post_id );
		if ( in_array( $current_status, array( 'pending', 'auto-draft', 'inherit', 'trash' ), true ) ) {
			return;
		}

		// Only update if status is not already pending to avoid unnecessary DB writes
		if ( 'pending' !== $current_status ) {
			global $wpdb;
			$wpdb->update(
				$wpdb->posts,
				array( 'post_status' => 'pending' ),
				array( 'ID' => $post_id ),
				array( '%s' ),
				array( '%d' )
			);
			clean_post_cache( $post_id );
		}
	}
}
add_action( 'save_post', 'culturacsi_moderation_hard_enforce_pending', 999, 2 );

if ( ! function_exists( 'culturacsi_moderation_is_target_user' ) ) {
	/**
	 * Whether a user should be subject to association moderation workflow.
	 */
	function culturacsi_moderation_is_target_user( WP_User $user ): bool {
		if ( user_can( $user, 'manage_options' ) ) {
			return false;
		}

		$roles = (array) $user->roles;
		if ( in_array( 'association_manager', $roles, true ) || in_array( 'association_pending', $roles, true ) ) {
			return true;
		}

		$assoc_id = (int) get_user_meta( (int) $user->ID, 'association_post_id', true );
		return $assoc_id > 0;
	}
}

if ( ! function_exists( 'culturacsi_moderation_mark_user_pending' ) ) {
	/**
	 * Mark a user account as pending site-admin approval.
	 */
	function culturacsi_moderation_mark_user_pending( int $user_id ): void {
		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user instanceof WP_User ) {
			return;
		}
		if ( ! culturacsi_moderation_is_target_user( $user ) ) {
			return;
		}

		if ( function_exists( 'culturacsi_portal_apply_user_role_moderation' ) ) {
			culturacsi_portal_apply_user_role_moderation( $user_id, 'association_pending', 'pending' );
			return;
		}

		$user->set_role( 'association_pending' );
		update_user_meta( $user_id, 'assoc_pending_approval', '1' );
		update_user_meta( $user_id, 'assoc_moderation_state', 'pending' );
	}
}

if ( ! function_exists( 'culturacsi_moderation_user_register_pending' ) ) {
	/**
	 * New users created by non-site-admin flows require site-admin approval.
	 */
	function culturacsi_moderation_user_register_pending( int $user_id ): void {
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}
		culturacsi_moderation_mark_user_pending( $user_id );
	}
}
add_action( 'user_register', 'culturacsi_moderation_user_register_pending', 999, 1 );

if ( ! function_exists( 'culturacsi_moderation_profile_update_pending' ) ) {
	/**
	 * Any non-site-admin user update must be approved by site admins.
	 *
	 * @param int   $user_id       Updated user id.
	 * @param array $old_user_data Previous user object data.
	 * @param array $userdata      Incoming update payload.
	 */
	function culturacsi_moderation_profile_update_pending( int $user_id, $old_user_data, array $userdata ): void {
		unset( $old_user_data, $userdata );

		if ( ! is_user_logged_in() || current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! current_user_can( 'association_manager' ) && ! current_user_can( 'association_pending' ) ) {
			return;
		}

		culturacsi_moderation_mark_user_pending( $user_id );
	}
}
add_action( 'profile_update', 'culturacsi_moderation_profile_update_pending', 999, 3 );
