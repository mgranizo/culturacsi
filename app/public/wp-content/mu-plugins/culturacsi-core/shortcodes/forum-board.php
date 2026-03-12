<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'culturacsi_portal_forum_board_base_url' ) ) {
	/**
	 * Return the canonical reserved-area board URL.
	 */
	function culturacsi_portal_forum_board_base_url(): string {
		return (string) home_url( '/area-riservata/bacheca/' );
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_is_request' ) ) {
	/**
	 * Whether the current request is the wrapped reserved-area board route.
	 */
	function culturacsi_portal_forum_board_is_request(): bool {
		return 'area-riservata/bacheca' === culturacsi_portal_current_path();
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_root_forum' ) ) {
	/**
	 * Resolve the reserved-area root forum.
	 */
	function culturacsi_portal_forum_board_root_forum(): ?WP_Post {
		static $forum = null;
		static $loaded = false;

		if ( $loaded ) {
			return $forum instanceof WP_Post ? $forum : null;
		}

		$loaded = true;
		$forum  = get_page_by_path( 'bacheca-area-riservata', OBJECT, 'forum' );

		if ( ! $forum instanceof WP_Post ) {
			$forum = null;
		}

		return $forum instanceof WP_Post ? $forum : null;
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_slug_or_id' ) ) {
	/**
	 * Return a stable slug-or-id token for wrapped forum/topic URLs.
	 */
	function culturacsi_portal_forum_board_slug_or_id( int $post_id ): string {
		$slug = sanitize_title( (string) get_post_field( 'post_name', $post_id ) );
		return '' !== $slug ? $slug : (string) $post_id;
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_forum_is_inside_root' ) ) {
	/**
	 * Whether a forum is the root board or a descendant of it.
	 */
	function culturacsi_portal_forum_board_forum_is_inside_root( int $forum_id, int $root_forum_id ): bool {
		$forum_id      = absint( $forum_id );
		$root_forum_id = absint( $root_forum_id );

		if ( $forum_id <= 0 || $root_forum_id <= 0 ) {
			return false;
		}

		if ( $forum_id === $root_forum_id ) {
			return true;
		}

		$ancestors = array_map( 'absint', get_post_ancestors( $forum_id ) );
		return in_array( $root_forum_id, $ancestors, true );
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_topic_is_inside_root' ) ) {
	/**
	 * Whether a topic belongs to the wrapped reserved-area board.
	 */
	function culturacsi_portal_forum_board_topic_is_inside_root( int $topic_id, int $root_forum_id ): bool {
		$topic_id      = absint( $topic_id );
		$root_forum_id = absint( $root_forum_id );

		if ( $topic_id <= 0 || $root_forum_id <= 0 || ! function_exists( 'bbp_get_topic_forum_id' ) ) {
			return false;
		}

		$forum_id = (int) bbp_get_topic_forum_id( $topic_id );
		return culturacsi_portal_forum_board_forum_is_inside_root( $forum_id, $root_forum_id );
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_find_forum' ) ) {
	/**
	 * Resolve a wrapped child forum from a numeric ID or slug.
	 */
	function culturacsi_portal_forum_board_find_forum( string $forum_ref, int $root_forum_id ): ?WP_Post {
		$forum_ref = trim( $forum_ref );
		if ( '' === $forum_ref ) {
			return null;
		}

		$forum = null;
		if ( ctype_digit( $forum_ref ) ) {
			$candidate = get_post( (int) $forum_ref );
			if ( $candidate instanceof WP_Post && 'forum' === $candidate->post_type ) {
				$forum = $candidate;
			}
		} else {
			$candidates = get_posts(
				array(
					'post_type'              => 'forum',
					'name'                   => sanitize_title( $forum_ref ),
					'post_status'            => 'any',
					'posts_per_page'         => 5,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => false,
				)
			);

			foreach ( $candidates as $candidate ) {
				if ( $candidate instanceof WP_Post && culturacsi_portal_forum_board_forum_is_inside_root( (int) $candidate->ID, $root_forum_id ) ) {
					$forum = $candidate;
					break;
				}
			}
		}

		if ( ! $forum instanceof WP_Post ) {
			return null;
		}

		return culturacsi_portal_forum_board_forum_is_inside_root( (int) $forum->ID, $root_forum_id ) ? $forum : null;
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_find_topic' ) ) {
	/**
	 * Resolve a wrapped topic from a numeric ID or slug.
	 */
	function culturacsi_portal_forum_board_find_topic( string $topic_ref, int $root_forum_id ): ?WP_Post {
		$topic_ref = trim( $topic_ref );
		if ( '' === $topic_ref ) {
			return null;
		}

		$topic = null;
		if ( ctype_digit( $topic_ref ) ) {
			$candidate = get_post( (int) $topic_ref );
			if ( $candidate instanceof WP_Post && 'topic' === $candidate->post_type ) {
				$topic = $candidate;
			}
		} else {
			$candidates = get_posts(
				array(
					'post_type'              => 'topic',
					'name'                   => sanitize_title( $topic_ref ),
					'post_status'            => 'any',
					'posts_per_page'         => 5,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => false,
				)
			);

			foreach ( $candidates as $candidate ) {
				if ( $candidate instanceof WP_Post && culturacsi_portal_forum_board_topic_is_inside_root( (int) $candidate->ID, $root_forum_id ) ) {
					$topic = $candidate;
					break;
				}
			}
		}

		if ( ! $topic instanceof WP_Post ) {
			return null;
		}

		return culturacsi_portal_forum_board_topic_is_inside_root( (int) $topic->ID, $root_forum_id ) ? $topic : null;
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_forum_url' ) ) {
	/**
	 * Map a forum ID to its wrapped reserved-area URL.
	 */
	function culturacsi_portal_forum_board_forum_url( int $forum_id, int $root_forum_id = 0 ): string {
		$forum_id      = absint( $forum_id );
		$root_forum_id = absint( $root_forum_id );

		if ( $forum_id <= 0 ) {
			return '';
		}

		if ( $root_forum_id <= 0 ) {
			$root_forum = culturacsi_portal_forum_board_root_forum();
			$root_forum_id = $root_forum instanceof WP_Post ? (int) $root_forum->ID : 0;
		}

		if ( $root_forum_id <= 0 || ! culturacsi_portal_forum_board_forum_is_inside_root( $forum_id, $root_forum_id ) ) {
			return '';
		}

		$base_url = culturacsi_portal_forum_board_base_url();
		if ( $forum_id === $root_forum_id ) {
			return $base_url;
		}

		return (string) add_query_arg(
			array(
				'forum' => culturacsi_portal_forum_board_slug_or_id( $forum_id ),
			),
			$base_url
		);
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_topic_url' ) ) {
	/**
	 * Map a topic ID to its wrapped reserved-area URL.
	 */
	function culturacsi_portal_forum_board_topic_url( int $topic_id, int $root_forum_id = 0 ): string {
		$topic_id      = absint( $topic_id );
		$root_forum_id = absint( $root_forum_id );

		if ( $topic_id <= 0 || ! function_exists( 'bbp_get_topic_forum_id' ) ) {
			return '';
		}

		if ( $root_forum_id <= 0 ) {
			$root_forum = culturacsi_portal_forum_board_root_forum();
			$root_forum_id = $root_forum instanceof WP_Post ? (int) $root_forum->ID : 0;
		}

		if ( $root_forum_id <= 0 || ! culturacsi_portal_forum_board_topic_is_inside_root( $topic_id, $root_forum_id ) ) {
			return '';
		}

		$topic_forum_id = (int) bbp_get_topic_forum_id( $topic_id );
		$args           = array(
			'topic' => culturacsi_portal_forum_board_slug_or_id( $topic_id ),
		);

		if ( $topic_forum_id > 0 && $topic_forum_id !== $root_forum_id ) {
			$args['forum'] = culturacsi_portal_forum_board_slug_or_id( $topic_forum_id );
		}

		return (string) add_query_arg( $args, culturacsi_portal_forum_board_base_url() );
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_reply_url' ) ) {
	/**
	 * Map a reply ID to the wrapped topic URL plus reply anchor.
	 */
	function culturacsi_portal_forum_board_reply_url( int $reply_id, int $root_forum_id = 0 ): string {
		$reply_id = absint( $reply_id );
		if ( $reply_id <= 0 ) {
			return '';
		}

		if ( function_exists( 'bbp_get_topic_post_type' ) && bbp_get_topic_post_type() === get_post_type( $reply_id ) ) {
			$topic_url = culturacsi_portal_forum_board_topic_url( $reply_id, $root_forum_id );

			return '' !== $topic_url
				? $topic_url . '#post-' . $reply_id
				: '';
		}

		if ( ! function_exists( 'bbp_get_reply_topic_id' ) ) {
			return '';
		}

		$topic_id  = (int) bbp_get_reply_topic_id( $reply_id );
		$topic_url = culturacsi_portal_forum_board_topic_url( $topic_id, $root_forum_id );

		return '' !== $topic_url
			? $topic_url . '#post-' . $reply_id
			: '';
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_filter_forum_permalink' ) ) {
	/**
	 * Rewrite forum permalinks to stay inside the reserved-area shell.
	 */
	function culturacsi_portal_forum_board_filter_forum_permalink( string $permalink, int $forum_id ): string {
		if ( ! culturacsi_portal_forum_board_is_request() ) {
			return $permalink;
		}

		$wrapped = culturacsi_portal_forum_board_forum_url( $forum_id );
		return '' !== $wrapped ? $wrapped : $permalink;
	}
	add_filter( 'bbp_get_forum_permalink', 'culturacsi_portal_forum_board_filter_forum_permalink', 20, 2 );
}

if ( ! function_exists( 'culturacsi_portal_forum_board_filter_topic_permalink' ) ) {
	/**
	 * Rewrite topic permalinks to stay inside the reserved-area shell.
	 */
	function culturacsi_portal_forum_board_filter_topic_permalink( string $permalink, int $topic_id ): string {
		if ( ! culturacsi_portal_forum_board_is_request() ) {
			return $permalink;
		}

		$wrapped = culturacsi_portal_forum_board_topic_url( $topic_id );
		return '' !== $wrapped ? $wrapped : $permalink;
	}
	add_filter( 'bbp_get_topic_permalink', 'culturacsi_portal_forum_board_filter_topic_permalink', 20, 2 );
}

if ( ! function_exists( 'culturacsi_portal_forum_board_filter_reply_permalink' ) ) {
	/**
	 * Rewrite reply permalinks to stay inside the reserved-area shell.
	 */
	function culturacsi_portal_forum_board_filter_reply_permalink( string $permalink, int $reply_id ): string {
		if ( ! culturacsi_portal_forum_board_is_request() ) {
			return $permalink;
		}

		$wrapped = culturacsi_portal_forum_board_reply_url( $reply_id );
		return '' !== $wrapped ? $wrapped : $permalink;
	}
	add_filter( 'bbp_get_reply_permalink', 'culturacsi_portal_forum_board_filter_reply_permalink', 20, 2 );
	add_filter( 'bbp_get_reply_url', 'culturacsi_portal_forum_board_filter_reply_permalink', 20, 2 );
}

if ( ! function_exists( 'culturacsi_portal_forum_board_filter_forums_url' ) ) {
	/**
	 * Rewrite bbPress root/archive URLs to the wrapped reserved-area board route.
	 */
	function culturacsi_portal_forum_board_filter_forums_url( string $url, string $path = '/' ): string {
		if ( ! culturacsi_portal_forum_board_is_request() ) {
			return $url;
		}

		$base_url = culturacsi_portal_forum_board_base_url();
		$path     = trim( $path, '/' );

		return '' === $path ? $base_url : trailingslashit( $base_url ) . $path . '/';
	}
	add_filter( 'bbp_get_forums_url', 'culturacsi_portal_forum_board_filter_forums_url', 20, 2 );
}

if ( ! function_exists( 'culturacsi_portal_forum_board_filter_post_type_archive_link' ) ) {
	/**
	 * Rewrite the forum post-type archive link when bbPress breadcrumbs ask
	 * WordPress for the archive URL directly.
	 */
	function culturacsi_portal_forum_board_filter_post_type_archive_link( string $link, string $post_type ): string {
		if ( ! culturacsi_portal_forum_board_is_request() || ! function_exists( 'bbp_get_forum_post_type' ) ) {
			return $link;
		}

		return bbp_get_forum_post_type() === $post_type
			? culturacsi_portal_forum_board_base_url()
			: $link;
	}
	add_filter( 'post_type_archive_link', 'culturacsi_portal_forum_board_filter_post_type_archive_link', 20, 2 );
}

if ( ! function_exists( 'culturacsi_portal_forum_board_reply_form_redirect_to' ) ) {
	/**
	 * Keep reply creation redirects on the wrapped topic URL.
	 */
	function culturacsi_portal_forum_board_reply_form_redirect_to( string $redirect_to ): string {
		if ( ! culturacsi_portal_forum_board_is_request() || ! function_exists( 'bbp_get_topic_id' ) ) {
			return $redirect_to;
		}

		$wrapped = culturacsi_portal_forum_board_topic_url( (int) bbp_get_topic_id() );
		return '' !== $wrapped ? $wrapped : $redirect_to;
	}
	add_filter( 'bbp_reply_form_redirect_to', 'culturacsi_portal_forum_board_reply_form_redirect_to', 20 );
}

if ( ! function_exists( 'culturacsi_portal_forum_board_request_page_id' ) ) {
	/**
	 * Resolve the reserved-area board page ID.
	 */
	function culturacsi_portal_forum_board_request_page_id(): int {
		static $page_id = null;
		if ( null !== $page_id ) {
			return $page_id;
		}

		$page = get_page_by_path( 'area-riservata/bacheca', OBJECT, 'page' );
		$page_id = $page instanceof WP_Post ? (int) $page->ID : 0;
		return $page_id;
	}
}

if ( ! function_exists( 'culturacsi_portal_forum_board_force_page_request' ) ) {
	/**
	 * Keep wrapped forum/topic query args on the reserved-area page instead of
	 * letting bbPress treat them as native archive/single query vars.
	 */
	function culturacsi_portal_forum_board_force_page_request( array $query_vars ): array {
		if ( ! culturacsi_portal_forum_board_is_request() ) {
			return $query_vars;
		}

		if ( ! isset( $_GET['forum'] ) && ! isset( $_GET['topic'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $query_vars;
		}

		$page_id = culturacsi_portal_forum_board_request_page_id();
		if ( $page_id <= 0 ) {
			return $query_vars;
		}

		return array(
			'page_id' => $page_id,
		);
	}
	add_filter( 'request', 'culturacsi_portal_forum_board_force_page_request', 1 );
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

		$forum = culturacsi_portal_forum_board_root_forum();
		if ( ! $forum instanceof WP_Post || ! bbp_is_forum( (int) $forum->ID ) ) {
			return culturacsi_portal_notice( 'La bacheca non e stata configurata correttamente.', 'error' );
		}

		if ( ! bbp_user_can_view_forum( array( 'forum_id' => (int) $forum->ID ) ) ) {
			return culturacsi_portal_notice( 'Non hai i permessi per accedere alla bacheca.', 'error' );
		}

		$root_forum_id = (int) $forum->ID;
		$selected_forum = $forum;
		$selected_topic = null;

		if ( isset( $_GET['topic'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$selected_topic = culturacsi_portal_forum_board_find_topic(
				sanitize_text_field( wp_unslash( (string) $_GET['topic'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$root_forum_id
			);

			if ( ! $selected_topic instanceof WP_Post ) {
				return culturacsi_portal_notice( 'L\'argomento richiesto non e disponibile.', 'error' );
			}

			$topic_forum_id = function_exists( 'bbp_get_topic_forum_id' ) ? (int) bbp_get_topic_forum_id( (int) $selected_topic->ID ) : 0;
			if ( $topic_forum_id > 0 ) {
				$topic_forum = get_post( $topic_forum_id );
				if ( $topic_forum instanceof WP_Post ) {
					$selected_forum = $topic_forum;
				}
			}
		} elseif ( isset( $_GET['forum'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$selected_forum = culturacsi_portal_forum_board_find_forum(
				sanitize_text_field( wp_unslash( (string) $_GET['forum'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$root_forum_id
			);

			if ( ! $selected_forum instanceof WP_Post ) {
				return culturacsi_portal_notice( 'Il forum richiesto non e disponibile.', 'error' );
			}
		}

		if ( $selected_topic instanceof WP_Post ) {
			return do_shortcode( '[bbp-single-topic id="' . (int) $selected_topic->ID . '"]' );
		}

		return do_shortcode( '[bbp-single-forum id="' . (int) $selected_forum->ID . '"]' );
	}
}
