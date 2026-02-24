<?php
/**
 * Custom Page Options (Meta Box)
 *
 * Provides per-page/post options for hiding header/footer and transparent header.
 *
 * @package CulturaCSI
 */

/**
 * Add Meta Box to Posts and Pages
 */
function culturacsi_add_meta_box() {
	$screens = array( 'post', 'page' );
	foreach ( $screens as $screen ) {
		add_meta_box(
			'culturacsi_page_options',
			__( 'Opzioni visualizzazione pagina', 'culturacsi' ),
			'culturacsi_page_options_html',
			$screen,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'culturacsi_add_meta_box' );

/**
 * Render Meta Box HTML
 *
 * @param WP_Post $post The post object.
 */
function culturacsi_page_options_html( $post ) {
	// Add nonce for security
	wp_nonce_field( 'culturacsi_page_options_nonce', 'culturacsi_page_options_nonce' );

	// Get current values
	$hide_header = get_post_meta( $post->ID, '_culturacsi_hide_header', true );
	$transparent_header = get_post_meta( $post->ID, '_culturacsi_transparent_header', true );
	$hide_footer = get_post_meta( $post->ID, '_culturacsi_hide_footer', true );
	$hide_title = get_post_meta( $post->ID, '_culturacsi_hide_title', true );
	?>
	<p class="description"><?php _e( 'Controlla la visualizzazione di header e footer per questa pagina.', 'culturacsi' ); ?></p>

	<div style="margin-top: 15px;">
		<label style="display: block; margin-bottom: 8px;">
			<input type="checkbox" name="culturacsi_hide_header" value="1" <?php checked( $hide_header, 1 ); ?> />
			<?php _e( 'Nascondi header', 'culturacsi' ); ?>
		</label>
	</div>

	<div style="margin-top: 8px;">
		<label style="display: block; margin-bottom: 8px;">
			<input type="checkbox" name="culturacsi_transparent_header" value="1" <?php checked( $transparent_header, 1 ); ?> />
			<?php _e( 'Header trasparente', 'culturacsi' ); ?>
		</label>
	</div>

	<div style="margin-top: 8px;">
		<label style="display: block; margin-bottom: 8px;">
			<input type="checkbox" name="culturacsi_hide_footer" value="1" <?php checked( $hide_footer, 1 ); ?> />
			<?php _e( 'Nascondi footer', 'culturacsi' ); ?>
		</label>
	</div>

	<div style="margin-top: 8px;">
		<label style="display: block; margin-bottom: 8px;">
			<input type="checkbox" name="culturacsi_hide_title" value="1" <?php checked( $hide_title, 1 ); ?> />
			<?php _e( 'Nascondi titolo pagina', 'culturacsi' ); ?>
		</label>
	</div>
	<?php
}

/**
 * Save Meta Box Data
 *
 * @param int $post_id The post ID.
 */
function culturacsi_save_postdata( $post_id ) {
	// Security checks

	// 1. Verify nonce
	if ( ! isset( $_POST['culturacsi_page_options_nonce'] ) ||
	     ! wp_verify_nonce( $_POST['culturacsi_page_options_nonce'], 'culturacsi_page_options_nonce' ) ) {
		return;
	}

	// 2. Check for autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// 3. Check user permissions
	$post_type = get_post_type( $post_id );
	if ( 'page' === $post_type ) {
		if ( ! current_user_can( 'edit_page', $post_id ) ) {
			return;
		}
	} else {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
	}

	// Save the meta values
	$meta_keys = array(
		'culturacsi_hide_header',
		'culturacsi_transparent_header',
		'culturacsi_hide_footer',
		'culturacsi_hide_title',
	);

	foreach ( $meta_keys as $key ) {
		$meta_key = '_' . $key;
		if ( isset( $_POST[ $key ] ) && $_POST[ $key ] === '1' ) {
			update_post_meta( $post_id, $meta_key, 1 );
		} else {
			update_post_meta( $post_id, $meta_key, 0 );
		}
	}
}
add_action( 'save_post', 'culturacsi_save_postdata' );

/**
 * Add body classes based on page options
 *
 * @param array $classes Existing body classes.
 * @return array Modified body classes.
 */
function culturacsi_body_classes( $classes ) {
	if ( is_singular() ) {
		$post_id = get_the_ID();

		// Transparent Header
		$transparent = get_post_meta( $post_id, '_culturacsi_transparent_header', true );
		if ( $transparent ) {
			$classes[] = 'header-transparent';
		}

		// Hide Title
		$hide_title = get_post_meta( $post_id, '_culturacsi_hide_title', true );
		if ( $hide_title ) {
			$classes[] = 'hide-page-title';
		}

		// Hide Header
		$hide_header = get_post_meta( $post_id, '_culturacsi_hide_header', true );
		if ( $hide_header ) {
			$classes[] = 'hide-header';
		}

		// Hide Footer
		$hide_footer = get_post_meta( $post_id, '_culturacsi_hide_footer', true );
		if ( $hide_footer ) {
			$classes[] = 'hide-footer';
		}
	}

	return $classes;
}
add_filter( 'body_class', 'culturacsi_body_classes' );
