<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Audit Logging for CulturaCSI Portal
 * Tracks user logins and content changes.
 */

function culturacsi_logging_get_table_name(): string {
	global $wpdb;
	return $wpdb->prefix . 'culturacsi_audit_log';
}

/**
 * Ensure the audit log table exists.
 */
function culturacsi_logging_ensure_table(): void {
	if ( get_transient( 'culturacsi_audit_table_checked' ) ) {
		return;
	}

	global $wpdb;
	$table_name = culturacsi_logging_get_table_name();
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		user_id bigint(20) NOT NULL,
		action varchar(50) NOT NULL,
		object_type varchar(50) DEFAULT '' NOT NULL,
		object_id bigint(20) DEFAULT 0 NOT NULL,
		details text DEFAULT '' NOT NULL,
		ip_address varchar(45) DEFAULT '' NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY action (action),
		KEY object (object_type, object_id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	set_transient( 'culturacsi_audit_table_checked', 1, DAY_IN_SECONDS );
}
add_action( 'init', 'culturacsi_logging_ensure_table' );

/**
 * Core function to record an event in the audit log.
 */
function culturacsi_log_event( string $action, string $object_type = '', int $object_id = 0, string $details = '' ): void {
	global $wpdb;
	$table_name = culturacsi_logging_get_table_name();
	
	$user_id = get_current_user_id();
	$ip = $_SERVER['REMOTE_ADDR'] ?? '';

	$wpdb->insert(
		$table_name,
		array(
			'user_id'     => $user_id,
			'action'      => sanitize_key( $action ),
			'object_type' => sanitize_key( $object_type ),
			'object_id'   => $object_id,
			'details'     => sanitize_textarea_field( $details ),
			'ip_address'  => sanitize_text_field( $ip ),
			'created_at'  => current_time( 'mysql' ),
		),
		array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
	);
}

/**
 * Track Login Events.
 */
add_action( 'wp_login', function( $user_login, $user ) {
	if ( $user instanceof WP_User ) {
		culturacsi_log_event( 'login', 'user', (int) $user->ID, "User logged in: $user_login" );
	}
}, 10, 2 );

/**
 * Track Post Changes (News, Events, Associations).
 */
add_action( 'wp_insert_post', function( $post_id, $post, $update ) {
	if ( wp_is_post_revision( $post_id ) ) return;
	
	$tracked_types = array( 'news', 'event', 'association' );
	if ( ! in_array( $post->post_type, $tracked_types, true ) ) return;

	$action = $update ? 'update_post' : 'create_post';
	$title  = get_the_title( $post_id );
	culturacsi_log_event( $action, $post->post_type, (int) $post_id, "Title: $title" );
	
	// Track Modified Fields
	if ( current_user_can( 'manage_options' ) ) {
		delete_post_meta( $post_id, '_assoc_modified_fields_list' );
	} elseif ( isset( $_POST['_assoc_modified_fields'] ) && ! empty( $_POST['_assoc_modified_fields'] ) ) {
		$new_mod = explode( ',', sanitize_text_field( wp_unslash( $_POST['_assoc_modified_fields'] ) ) );
		$old_mod = explode( ',', (string) get_post_meta( $post_id, '_assoc_modified_fields_list', true ) );
		$merged  = array_filter( array_unique( array_merge( $old_mod, $new_mod ) ) );
		if ( ! empty( $merged ) ) {
			update_post_meta( $post_id, '_assoc_modified_fields_list', implode( ',', $merged ) );
		}
	}
}, 10, 3 );

/**
 * Track Post Deletions.
 */
add_action( 'wp_trash_post', function( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) return;
	culturacsi_log_event( 'trash_post', $post->post_type, (int) $post_id, "Title: " . $post->post_title );
});

/**
 * Track User profile updates and moderation.
 */
add_action( 'profile_update', function( $user_id, $old_user_data ) {
	$user = get_user_by( 'id', $user_id );
	culturacsi_log_event( 'update_user', 'user', (int) $user_id, "User profile updated for " . $user->user_login );

	// Track Modified Fields
	if ( current_user_can( 'manage_options' ) ) {
		delete_user_meta( $user_id, '_assoc_modified_fields_list' );
	} elseif ( isset( $_POST['_assoc_modified_fields'] ) && ! empty( $_POST['_assoc_modified_fields'] ) ) {
		$new_mod = explode( ',', sanitize_text_field( wp_unslash( $_POST['_assoc_modified_fields'] ) ) );
		$old_mod = explode( ',', (string) get_user_meta( $user_id, '_assoc_modified_fields_list', true ) );
		$merged  = array_filter( array_unique( array_merge( $old_mod, $new_mod ) ) );
		if ( ! empty( $merged ) ) {
			update_user_meta( $user_id, '_assoc_modified_fields_list', implode( ',', $merged ) );
		}
	}
}, 10, 2 );

/**
 * Track user registration.
 */
add_action( 'user_register', function( $user_id ) {
	$user = get_user_by( 'id', $user_id );
	if ( $user instanceof WP_User ) {
		culturacsi_log_event( 'wp_insert_user', 'user', (int) $user->ID, "New user registered: " . $user->user_login );
	}
	// Track Modified Fields
	if ( current_user_can( 'manage_options' ) ) {
		delete_user_meta( $user_id, '_assoc_modified_fields_list' );
	} elseif ( isset( $_POST['_assoc_modified_fields'] ) && ! empty( $_POST['_assoc_modified_fields'] ) ) {
		update_user_meta( $user_id, '_assoc_modified_fields_list', sanitize_text_field( wp_unslash( $_POST['_assoc_modified_fields'] ) ) );
	}
});

/**
 * Track Post Status Transitions (Approvals, Rejections, etc.)
 */
add_action( 'transition_post_status', function( $new_status, $old_status, $post ) {
	if ( $new_status === $old_status ) return;
	
	$tracked_types = array( 'news', 'event', 'association' );
	if ( ! in_array( $post->post_type, $tracked_types, true ) ) return;

	$action = '';
	if ( 'publish' === $new_status ) {
		$action = 'approve';
		delete_post_meta( $post->ID, '_assoc_modified_fields_list' );
	} elseif ( 'draft' === $new_status && 'publish' === $old_status ) {
		$action = 'reject';
	} elseif ( 'pending' === $new_status ) {
		$action = 'hold';
	}

	if ( $action ) {
		culturacsi_log_event( $action, $post->post_type, (int) $post->ID, "Status changed from $old_status to $new_status" );
	}
}, 10, 3 );

/**
 * Get the last modification record for a specific object.
 */
function culturacsi_logging_get_last_modified( string $object_type, int $object_id ): ?object {
	global $wpdb;
	$table_name = culturacsi_logging_get_table_name();
	
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT l.*, u.display_name as user_name 
			 FROM $table_name l 
			 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
			 WHERE l.object_type = %s AND l.object_id = %d AND l.action IN ('update_post', 'update_user', 'approve_user', 'moderate_user', 'approve', 'reject', 'hold')
			 ORDER BY l.created_at DESC LIMIT 1",
			$object_type,
			$object_id
		)
	);
}

/**
 * Get the creation record for a specific object.
 */
function culturacsi_logging_get_creator( string $object_type, int $object_id ): ?object {
	global $wpdb;
	$table_name = culturacsi_logging_get_table_name();
	
	return $wpdb->get_row(
		$wpdb->prepare(
			"SELECT l.*, u.display_name as user_name 
			 FROM $table_name l 
			 LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
			 WHERE l.object_type = %s AND l.object_id = %d AND l.action IN ('create_post', 'wp_insert_user')
			 ORDER BY l.created_at ASC LIMIT 1",
			$object_type,
			$object_id
		)
	);
}
