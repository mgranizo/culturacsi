<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Common utilities for the CulturaCSI portal.
 */

if ( ! function_exists( 'culturacsi_portal_current_path' ) ) {
	/**
	 * Retrieves the current request path.
	 * 
	 * @return string The current path string, without leading/trailing slashes.
	 */
	function culturacsi_portal_current_path(): string {
		if ( function_exists( 'culturacsi_routing_current_path' ) ) {
			return culturacsi_routing_current_path();
		}

		return trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
	}
}

if ( ! function_exists( 'culturacsi_portal_modified_fields_css_rules' ) ) {
	/**
	 * Generates CSS rules to highlight specific form fields that were recently modified.
	 *
	 * @param string $modified_fields_csv Comma-separated list of field names that were modified.
	 * @param string $activity_selector   CSS selector used to specifically highlight taxonomy inputs.
	 * @return string The generated inline CSS rules.
	 */
	function culturacsi_portal_modified_fields_css_rules( string $modified_fields_csv, string $activity_selector ): string {
		$modified_fields_csv = trim( $modified_fields_csv );
		if ( '' === $modified_fields_csv ) {
			return '';
		}

		$fields = array_filter( array_unique( explode( ',', $modified_fields_csv ) ) );
		if ( empty( $fields ) ) {
			return '';
		}

		$css_rules = array();
		foreach ( $fields as $field_name ) {
			$field_name = trim( (string) $field_name );
			if ( '' === $field_name ) {
				continue;
			}

			$css_rules[] = '[name="' . esc_attr( $field_name ) . '"]';
			if ( 'post_content' === $field_name || 'description' === $field_name ) {
				$css_rules[] = '.wp-editor-wrap';
			}
			if ( 'tax_input[activity_category][]' === $field_name ) {
				$css_rules[] = $activity_selector;
			}
		}

		if ( empty( $css_rules ) ) {
			return '';
		}

		return implode( ', ', $css_rules ) . ' { outline: 3px solid #ef4444 !important; outline-offset: 2px; box-shadow: 0 0 10px rgba(239, 68, 68, 0.4) !important; background-color: #fef2f2 !important; }';
	}
}

if ( ! function_exists( 'culturacsi_portal_can_access' ) ) {
	/**
	 * Determines if the current user has the basic permission to access the portal.
	 * 
	 * @return bool True if logged in AND has 'association_manager' or 'manage_options' capabilities.
	 */
	function culturacsi_portal_can_access(): bool {
		return is_user_logged_in() && ( current_user_can( 'association_manager' ) || current_user_can( 'manage_options' ) );
	}
}

if ( ! function_exists( 'culturacsi_portal_get_managed_association_id' ) ) {
	/**
	 * Looks up the specific 'association' post ID that a given user manages.
	 * 
	 * @param int $user_id The WordPress user ID.
	 * @return int The ID of the association post they manage, or 0 if none.
	 */
	function culturacsi_portal_get_managed_association_id( int $user_id ): int {
		$assoc_id = (int) get_user_meta( $user_id, 'association_post_id', true );
		if ( $assoc_id > 0 && 'association' === get_post_type( $assoc_id ) ) {
			return $assoc_id;
		}
		return 0;
	}
}

if ( ! function_exists( 'culturacsi_portal_post_owner_association_id' ) ) {
	/**
	 * Determines the parent association ID for a given post.
	 * If the post IS an association, returns its own ID.
	 * 
	 * @param WP_Post $post The post object.
	 * @return int The ID of the owning association, or 0.
	 */
	function culturacsi_portal_post_owner_association_id( WP_Post $post ): int {
		if ( 'association' === $post->post_type ) {
			return (int) $post->ID;
		}
		if ( in_array( $post->post_type, array( 'event', 'news', 'csi_content_entry' ), true ) ) {
			return (int) get_post_meta( (int) $post->ID, 'organizer_association_id', true );
		}
		return 0;
	}
}

if ( ! function_exists( 'culturacsi_portal_can_manage_post' ) ) {
	/**
	 * Checks if a specific user has permission to manage (edit/delete) a specific post.
	 * 
	 * @param WP_Post $post    The target post object.
	 * @param int     $user_id Optional. The user ID to query. Defaults to current user.
	 * @return bool True if the user is an admin OR manages the association that owns the post.
	 */
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
}

if ( ! function_exists( 'culturacsi_portal_can_manage_user_target' ) ) {
	/**
	 * Checks if an actor user has permission to manage a target user.
	 * 
	 * @param WP_User $target_user   The user being managed.
	 * @param int     $actor_user_id Optional. The managing user's ID. Defaults to current user.
	 * @return bool True if actor is admin OR shares the same managed association (and target is not an admin).
	 */
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
			return false; // Association managers cannot manage site admins.
		}

		$managed_assoc_id = culturacsi_portal_get_managed_association_id( $actor_user_id );
		if ( $managed_assoc_id <= 0 ) {
			return false;
		}

		$target_assoc_id = (int) get_user_meta( (int) $target_user->ID, 'association_post_id', true );
		return $target_assoc_id > 0 && $target_assoc_id === $managed_assoc_id;
	}
}

if ( ! function_exists( 'culturacsi_portal_notice' ) ) {
	/**
	 * Renders a standardized alert notice block for the portal interface.
	 * 
	 * @param string $message The message text.
	 * @param string $type    Optional. 'success', 'warning', or 'error'. Default 'success'.
	 * @return string The rendered HTML.
	 */
	function culturacsi_portal_notice( string $message, string $type = 'success' ): string {
		$type = in_array( $type, array( 'success', 'warning', 'error' ), true ) ? $type : 'success';
		return '<div class="assoc-admin-notice assoc-admin-notice-' . esc_attr( $type ) . '">' . esc_html( $message ) . '</div>';
	}
}
