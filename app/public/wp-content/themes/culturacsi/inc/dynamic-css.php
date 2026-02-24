<?php
/**
 * Dynamic CSS Generator
 *
 * @package CulturaCSI
 */

function culturacsi_dynamic_css() {
	?>
	<style type="text/css" id="culturacsi-dynamic-css">
		:root {
			/* Global Colors */
			<?php
			$colors = array(
				'palette_accent1'   => '#0A3B75',
				'palette_accent2'   => '#1E5BB0',
				'palette_contrast1' => '#333333',
				'palette_contrast2' => '#666666',
				'palette_contrast3' => '#999999',
				'palette_base1'     => '#f4f4f4',
				'palette_base2'     => '#ffffff',
				'palette_base3'     => '#eeeeee',
				'palette_white'     => '#ffffff',
				'palette_black'     => '#000000',
			);

			foreach ( $colors as $id => $default ) {
				$current_color = get_theme_mod( "culturacsi_colors[$id]", $default );
				$var_name = str_replace( '_', '-', $id ); // palette_accent1 -> palette-accent1
				echo "--global-{$var_name}: " . esc_attr( $current_color ) . ";\n";
			}
			
			/* Global Typography */
			$font_base = get_theme_mod( 'culturacsi_font_base_family', 'Roboto, sans-serif' );
			$font_headings = get_theme_mod( 'culturacsi_font_headings_family', 'Roboto, sans-serif' );
			$font_base_size = get_theme_mod( 'culturacsi_font_base_size', '16px' );
			
			echo "--global-font-base-family: " . html_entity_decode( esc_attr( $font_base ) ) . ";\n";
			echo "--global-font-headings-family: " . html_entity_decode( esc_attr( $font_headings ) ) . ";\n";
			echo "--global-font-base-size: " . esc_attr( $font_base_size ) . ";\n";

			$headings = array( 'h1' => '2.5rem', 'h2' => '2rem', 'h3' => '1.75rem', 'h4' => '1.5rem', 'h5' => '1.25rem', 'h6' => '1rem' );
			foreach ( $headings as $tag => $default ) {
				$size = get_theme_mod( "culturacsi_font_{$tag}_size", $default );
				echo "--global-font-{$tag}-size: " . esc_attr( $size ) . ";\n";
			}
			
			/* Layout & Containers */
			$outer_width = get_theme_mod( 'culturacsi_outer_width', '100%' );
			$outer_bg = get_theme_mod( 'culturacsi_outer_bg', '' );
			$wrapper_width = get_theme_mod( 'culturacsi_wrapper_width', '100%' );
			$wrapper_bg = get_theme_mod( 'culturacsi_wrapper_bg', '' );
			
			// Layout Variables
			$constrained_width = get_theme_mod( 'culturacsi_inner_width', '1200px' );
			$layout_mode = get_theme_mod( 'culturacsi_inner_layout_mode', 'boxed' );
			
			// If Fluid, Inner canvas is 100%. If Boxed, it's constrained.
			$inner_canvas_width = ( $layout_mode === 'fluid' ) ? '100%' : $constrained_width;

			// FIX: Get inner_bg value before using it
			$inner_bg = get_theme_mod( 'culturacsi_inner_bg', '' );

			echo "--site-outer-width: " . esc_attr( $outer_width ) . ";\n";
			echo "--site-outer-bg: " . esc_attr( $outer_bg ) . ";\n";
			echo "--site-wrapper-width: " . esc_attr( $wrapper_width ) . ";\n";
			echo "--site-wrapper-bg: " . esc_attr( $wrapper_bg ) . ";\n";
			
			echo "--site-inner-width: " . esc_attr( $inner_canvas_width ) . ";\n";
			echo "--site-constrained-width: " . esc_attr( $constrained_width ) . ";\n";
			
			echo "--site-inner-bg: " . esc_attr( $inner_bg ) . ";\n";

			// Content Spacing
			$pad_top = get_theme_mod( 'culturacsi_content_padding_top', '40' );
			$pad_bottom = get_theme_mod( 'culturacsi_content_padding_bottom', '40' );
			$pad_side = get_theme_mod( 'culturacsi_content_padding_side', '20' );

			echo "--content-padding-top: " . absint( $pad_top ) . "px;\n";
			echo "--content-padding-bottom: " . absint( $pad_bottom ) . "px;\n";
			echo "--content-padding-side: " . absint( $pad_side ) . "px;\n";

			/* Header & Navigation Options */
			$header_container_width = get_theme_mod( 'culturacsi_header_container_width', '1200px' );
			$nav_container_width = get_theme_mod( 'culturacsi_nav_container_width', '1200px' );
			
			$logo_width = get_theme_mod( 'culturacsi_logo_width', '200' );
			$left_logo_width = get_theme_mod( 'culturacsi_left_logo_width', '100' );
			
			$riservata_size = get_theme_mod( 'culturacsi_riservata_size', '0.9' );
			$riservata_pad_x = get_theme_mod( 'culturacsi_riservata_padding_x', '15' );
			$riservata_pad_y = get_theme_mod( 'culturacsi_riservata_padding_y', '5' );

			$header_bg = get_theme_mod( 'culturacsi_header_bg_color', '#ffffff' );
			$header_inherit = get_theme_mod( 'culturacsi_header_inherit_wrapper_width', false );
			$header_width_input = get_theme_mod( 'culturacsi_header_wrapper_width', '100%' );
			$header_wrapper_padding = get_theme_mod( 'culturacsi_header_wrapper_padding', '0' );
			$header_global_bottom = get_theme_mod( 'culturacsi_header_global_bottom_padding', '0' );

			$final_header_width = $header_inherit ? 'var(--site-wrapper-width)' : $header_width_input;

			$header_top_padding = get_theme_mod( 'culturacsi_header_top_padding', '15' );
			$header_bottom_padding = get_theme_mod( 'culturacsi_header_bottom_padding', '15' );
			$header_padding_x = get_theme_mod( 'culturacsi_header_padding_x', '20px' );
			
			// Force units if numeric
			if ( is_numeric( $header_padding_x ) ) {
				$header_padding_x .= 'px';
			}

			$nav_bg  = get_theme_mod( 'culturacsi_nav_bg_color', '#0A3B75' );
			$nav_link = get_theme_mod( 'culturacsi_nav_link_color', '#ffffff' );
			$nav_hover = get_theme_mod( 'culturacsi_nav_link_hover_color', '#1E5BB0' );
			$nav_font_size = get_theme_mod( 'culturacsi_nav_font_size', '0.95rem' );
			$nav_padding_y = get_theme_mod( 'culturacsi_nav_padding_y', '15px' );
			$nav_padding_x = get_theme_mod( 'culturacsi_nav_padding_x', '20px' );

			echo "--header-wrapper-width: " . esc_attr( $final_header_width ) . ";\n";
			echo "--header-wrapper-bg: " . esc_attr( $header_bg ) . ";\n";
			echo "--header-wrapper-padding: " . absint( $header_wrapper_padding ) . "px;\n";
			echo "--header-global-bottom-padding: " . absint( $header_global_bottom ) . "px;\n";

			echo "--header-container-width: " . esc_attr( $header_container_width ) . ";\n";
			echo "--nav-container-width: " . esc_attr( $nav_container_width ) . ";\n";
			
			echo "--global-header-logo-width: " . absint( $logo_width ) . "px;\n";
			echo "--header-left-logo-width: " . absint( $left_logo_width ) . "px;\n";
			
			echo "--header-btn-size: " . esc_attr( $riservata_size ) . "rem;\n";
			echo "--header-btn-padding-x: " . absint( $riservata_pad_x ) . "px;\n";
			echo "--header-btn-padding-y: " . absint( $riservata_pad_y ) . "px;\n";
			
			echo "--header-top-padding: " . absint( $header_top_padding ) . "px;\n";
			echo "--header-bottom-padding: " . absint( $header_bottom_padding ) . "px;\n";
			echo "--header-padding-x: " . esc_attr( $header_padding_x ) . ";\n";
			
			echo "--nav-bg-color: " . esc_attr( $nav_bg ) . ";\n";
			echo "--nav-link-color: " . esc_attr( $nav_link ) . ";\n";
			echo "--nav-link-hover-color: " . esc_attr( $nav_hover ) . ";\n";
			echo "--nav-font-size: " . esc_attr( $nav_font_size ) . ";\n";
			echo "--nav-padding-y: " . esc_attr( $nav_padding_y ) . ";\n";
			echo "--nav-padding-x: " . esc_attr( $nav_padding_x ) . ";\n";

			/* Footer Options */
			$footer_bg = get_theme_mod( 'culturacsi_footer_bg_color', '#0A3B75' );
			$footer_text = get_theme_mod( 'culturacsi_footer_text_color', '#ffffff' );
			echo "--global-footer-bg-color: " . esc_attr( $footer_bg ) . ";\n";
			echo "--global-footer-text-color: " . esc_attr( $footer_text ) . ";\n";
			?>
		}

		/* Apply to common elements */
		body {
			color: var(--global-palette-contrast1);
			background-color: var(--global-palette-base1);
			font-family: var(--global-font-base-family);
			font-size: var(--global-font-base-size);
		}

		/* Exterior Container */
		.site-outer {
			width: var(--site-outer-width);
			background-color: var(--site-outer-bg);
			margin: 0 auto;
		}

		/* Middle Container */
		.site-wrapper {
			width: var(--site-wrapper-width);
			background-color: var(--site-wrapper-bg);
		}

		/* Inner Container (Canvas) */
		.site-inner {
			width: var(--site-inner-width);
			background-color: var(--site-inner-bg);
		}

		/* Re-Define Generic Container to be constrained */
		.container {
			max-width: var(--site-constrained-width);
			margin: 0 auto;
			padding-left: var(--content-padding-side);
			padding-right: var(--content-padding-side);
		}

		/* Main Content Area */
		.site-main {
			padding-top: var(--content-padding-top);
			padding-bottom: var(--content-padding-bottom);
			/* Side padding is handled by internal containers or blocks if needed, 
			   but if .site-main is fluid, we might want baseline padding */
			padding-left: 0; 
			padding-right: 0;
		}
		
		/* If the user wants content padding on the fluid container itself */
		.site-main.has-padding {
			padding-left: var(--content-padding-side);
			padding-right: var(--content-padding-side);
		}
		
		h1, h2, h3, h4, h5, h6 {
			color: var(--global-palette-accent1);
			font-family: var(--global-font-headings-family);
		}
		
		h1 { font-size: var(--global-font-h1-size); }
		h2 { font-size: var(--global-font-h2-size); }
		h3 { font-size: var(--global-font-h3-size); }
		h4 { font-size: var(--global-font-h4-size); }
		h5 { font-size: var(--global-font-h5-size); }
		h6 { font-size: var(--global-font-h6-size); }
		
		a {
			color: var(--global-palette-accent2);
		}
		
		/* Header Styles */
		header {
			width: var(--header-wrapper-width) !important;
			max-width: 100%; 
			background-color: var(--header-wrapper-bg) !important;
			margin: 0 auto !important; /* Center it and remove bottom margin */
			margin-bottom: 0 !important;
			padding: var(--header-wrapper-padding);
			padding-bottom: var(--header-global-bottom-padding); /* Specific control for bottom */
			box-sizing: border-box; /* Ensure padding doesn't affect width */
			position: relative;
			z-index: 9000;
		}

		.header-container {
			width: 100%;
			max-width: var(--nav-container-width);
			margin: 0 auto;
			padding-left: var(--header-padding-x);
			padding-right: var(--header-padding-x);
			box-sizing: border-box;
		}

		.top-bar-inner {
			width: 100%;
			display: flex;
			justify-content: space-between;
			align-items: flex-start; /* Default Flex top align */
		}

		.nav-container {
			max-width: var(--nav-container-width);
			margin: 0 auto;
			padding-left: 20px;
			padding-right: 20px;
		}

		.logo-center .main-logo-img {
			max-width: var(--global-header-logo-width);
			height: auto;
		}
		
		.logo-left img {
			width: var(--header-left-logo-width);
			height: auto; 
		}
		
		<?php
		$header_layout_mode = get_theme_mod( 'culturacsi_header_layout_mode', 'absolute' );
		if ( $header_layout_mode === 'flow' ) {
			?>
			.top-bar-inner {
				display: grid !important;
				grid-template-columns: 1fr auto 1fr;
				align-items: start; /* Align tops to the tallest element (Cultura logo) */
				width: 100%;
				gap: 20px;
			}
			
			.logo-left {
				justify-self: start;
			}

			.logo-center {
				position: static !important;
				transform: none !important;
				justify-self: center;
				margin: 0 !important;
				/* Ensure center content is centered */
				display: flex;
				flex-direction: column;
				align-items: center;
			}

			.user-area {
				justify-self: end;
			}
			<?php
		} else {
			/* Enforce Absolute if needed, though style.css has it. 
			   Let's reiterate to be sure. */
			?>
			.logo-center {
				position: absolute;
				left: 50%;
				transform: translateX(-50%);
			}
			<?php
		}
		?>

		.area-riservata-btn {
			font-size: var(--header-btn-size);
			padding-left: var(--header-btn-padding-x);
			padding-right: var(--header-btn-padding-x);
			padding-top: var(--header-btn-padding-y);
			padding-bottom: var(--header-btn-padding-y);
		}

		.top-bar {
			padding-top: var(--header-top-padding);
			padding-bottom: var(--header-bottom-padding);
			background-color: transparent; /* Allow Header Wrapper BG to show */
		}
		
		/* Navigation Styles */
		<?php
		$nav_bg_selector = get_theme_mod( 'culturacsi_nav_full_width_bg', false ) ? '.main-nav' : '.nav-container';
		?>
		
		<?php echo $nav_bg_selector; ?> {
			background-color: var(--nav-bg-color);
		}
		
		/* Remove background from the other if switching */
		<?php echo ( $nav_bg_selector === '.main-nav' ) ? '.nav-container' : '.main-nav'; ?> {
			background-color: transparent;
		}

		.nav-link {
			color: var(--nav-link-color);
			font-size: var(--nav-font-size);
			padding: var(--nav-padding-y) var(--nav-padding-x);
		}
		.nav-link:hover {
			background-color: var(--nav-link-hover-color);
			color: var(--nav-link-color);
		}
		
		/* Footer Styles */
		.footer-main {
			background-color: var(--global-footer-bg-color);
			color: var(--global-footer-text-color);
		}
		.footer-main h3, 
		.footer-main i, 
		.footer-main span {
			color: var(--global-footer-text-color);
		}

		<?php
		/* Sticky Header */
		if ( get_theme_mod( 'culturacsi_sticky_header', false ) ) {
			?>
			header {
				position: sticky;
				top: 0;
				z-index: 9000;
				width: 100%;
			}
			<?php
		}

		/* Transparent Header - Check per-page first, then global */
		$transparent_header = false;
		if ( is_singular() ) {
			$transparent_header = get_post_meta( get_the_ID(), '_culturacsi_transparent_header', true );
		}
		if ( ! $transparent_header ) {
			$transparent_header = get_theme_mod( 'culturacsi_transparent_header_global', false );
		}
		
		if ( $transparent_header ) {
			?>
			header {
				background-color: transparent !important;
				position: absolute;
				width: 100%;
				left: 0;
				top: 0;
				z-index: 9000;
			}
			<?php
		}
		?>

		a:hover {
			color: var(--global-palette-accent1);
		}

	</style>
	<?php
}
add_action( 'wp_head', 'culturacsi_dynamic_css' );
