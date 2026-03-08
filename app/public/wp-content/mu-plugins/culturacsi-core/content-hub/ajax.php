<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Content hub AJAX endpoints.
 */

/**
 * Handle AJAX request for library modal data globally.
 */
function culturacsi_ajax_get_library_modal() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['nonce'] ) ) : '';
	if ( is_user_logged_in() ) {
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'csi_library_modal' ) ) {
			wp_send_json_error( array( 'message' => 'Nonce non valido' ), 403 );
		}
	}

	$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
	if ( $post_id <= 0 ) {
		wp_send_json_error( array( 'message' => 'Invalid post ID' ), 400 );
	}

	$post = get_post( $post_id );
	if ( ! ( $post instanceof WP_Post ) || 'csi_content_entry' !== $post->post_type ) {
		wp_send_json_error( array( 'message' => 'Post non trovato' ), 404 );
	}

	$post_status = get_post_status( $post );
	if ( ! is_user_logged_in() ) {
		if ( 'publish' !== $post_status ) {
			wp_send_json_error( array( 'message' => 'Contenuto non disponibile' ), 403 );
		}
	} else {
		$can_read_private = current_user_can( 'read_post', $post_id );
		if ( 'publish' !== $post_status && ! $can_read_private ) {
			wp_send_json_error( array( 'message' => 'Permessi insufficienti' ), 403 );
		}
	}

	$file_id = (int) get_post_meta( $post_id, '_csi_content_hub_file_id', true );
	$attachment_url = $file_id > 0 ? (string) wp_get_attachment_url( $file_id ) : '';
	$external_url = trim( (string) get_post_meta( $post_id, '_csi_content_hub_external_url', true ) );
	$button_label = trim( (string) get_post_meta( $post_id, '_csi_content_hub_button_label', true ) );
	if ( ! in_array( $button_label, array( 'Acquista', 'Visita', 'Scarica' ), true ) ) {
		$button_label = '';
	}
	if ( '' !== $external_url && ! preg_match( '#^https?://#i', $external_url ) ) {
		$external_url = 'https://' . ltrim( $external_url, '/' );
	}
	$external_url = esc_url_raw( $external_url );
	$file_note = '';
	if ( $file_id > 0 && '' !== $attachment_url ) {
		$file_path = (string) get_attached_file( $file_id );
		if ( '' !== trim( $file_path ) && file_exists( $file_path ) ) {
			$file_note = size_format( (float) filesize( $file_path ) );
		}
	}

	$modal_data = array(
		'title'    => get_the_title( $post ),
		'excerpt'  => get_the_excerpt( $post ),
		'content'  => apply_filters( 'the_content', $post->post_content ),
		'fileUrl'  => $attachment_url,
		'externalUrl' => $external_url,
		'buttonLabel' => $button_label,
		'fileNote' => $file_note,
		'imageUrl' => has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'medium_large' ) : '',
	);

	wp_send_json_success( $modal_data );
}
add_action( 'wp_ajax_csi_get_library_modal', 'culturacsi_ajax_get_library_modal' );
add_action( 'wp_ajax_nopriv_csi_get_library_modal', 'culturacsi_ajax_get_library_modal' );
