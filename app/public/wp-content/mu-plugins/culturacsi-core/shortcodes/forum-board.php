<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'culturacsi_portal_forum_board_shortcode' ) ) {
	/**
	 * Render the private reserved-area forum board entry point.
	 *
	 * @return string
	 */
	function culturacsi_portal_forum_board_shortcode(): string {
		if ( ! culturacsi_portal_can_access() ) {
			return '';
		}

		if ( ! function_exists( 'bbp_is_forum' ) || ! function_exists( 'bbp_user_can_view_forum' ) ) {
			return culturacsi_portal_notice( 'La bacheca non e disponibile al momento.', 'error' );
		}

		$forum = get_page_by_path( 'bacheca-area-riservata', OBJECT, 'forum' );
		if ( ! $forum instanceof WP_Post || ! bbp_is_forum( (int) $forum->ID ) ) {
			return culturacsi_portal_notice( 'La bacheca non e stata configurata correttamente.', 'error' );
		}

		if ( ! bbp_user_can_view_forum( array( 'forum_id' => (int) $forum->ID ) ) ) {
			return culturacsi_portal_notice( 'Non hai i permessi per accedere alla bacheca.', 'error' );
		}

		return do_shortcode( '[bbp-single-forum id="' . (int) $forum->ID . '"]' );
	}
}
