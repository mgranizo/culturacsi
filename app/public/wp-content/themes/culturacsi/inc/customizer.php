<?php
/**
 * Cultura ACSI Theme Customizer
 *
 * @package CulturaCSI
 */

function culturacsi_customize_register( $wp_customize ) {

	/**
	 * 1. Global Colors
	 */
	$wp_customize->add_section( 'culturacsi_global_colors', array(
		'title'       => __( 'Global Colors', 'culturacsi' ),
		'priority'    => 20,
		'description' => __( 'Define your global color palette.', 'culturacsi' ),
	) );

	// Define the 9-color palette + Backgrounds
	$colors = array(
		'palette_accent1'   => array( 'label' => __( 'Accent 1', 'culturacsi' ), 'default' => '#0A3B75' ),
		'palette_accent2'   => array( 'label' => __( 'Accent 2', 'culturacsi' ), 'default' => '#1E5BB0' ),
		'palette_contrast1' => array( 'label' => __( 'Contrast 1', 'culturacsi' ), 'default' => '#333333' ),
		'palette_contrast2' => array( 'label' => __( 'Contrast 2', 'culturacsi' ), 'default' => '#666666' ),
		'palette_contrast3' => array( 'label' => __( 'Contrast 3', 'culturacsi' ), 'default' => '#999999' ),
		'palette_base1'     => array( 'label' => __( 'Base 1 (Background)', 'culturacsi' ), 'default' => '#f4f4f4' ),
		'palette_base2'     => array( 'label' => __( 'Base 2 (Content)', 'culturacsi' ), 'default' => '#ffffff' ),
		'palette_base3'     => array( 'label' => __( 'Base 3', 'culturacsi' ), 'default' => '#eeeeee' ),
		'palette_white'     => array( 'label' => __( 'White', 'culturacsi' ), 'default' => '#ffffff' ),
		'palette_black'     => array( 'label' => __( 'Black', 'culturacsi' ), 'default' => '#000000' ),
	);

	foreach ( $colors as $id => $data ) {
		$wp_customize->add_setting( "culturacsi_colors[$id]", array(
			'default'           => $data['default'],
			'sanitize_callback' => 'sanitize_hex_color',
			'transport'         => 'refresh',
		) );

		$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, "culturacsi_colors[$id]", array(
			'label'    => $data['label'],
			'section'  => 'culturacsi_global_colors',
		) ) );
	}

	/**
	 * 2. Layout & Containers
	 */
	$wp_customize->add_section( 'culturacsi_layout_containers', array(
		'title'       => __( 'Layout & Containers', 'culturacsi' ),
		'priority'    => 22,
		'description' => __( 'Control the width of the main layout containers. You can use any valid CSS unit (px, %, vw, etc).', 'culturacsi' ),
	) );

	// Exterior Container (.site-outer) Width
	$wp_customize->add_setting( 'culturacsi_outer_width', array(
		'default'           => '100%',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_outer_width', array(
		'label'    => __( 'Exterior Container Width', 'culturacsi' ),
		'description' => __( 'e.g. 100%, 1920px', 'culturacsi' ),
		'section'  => 'culturacsi_layout_containers',
		'type'     => 'text',
		'priority' => 10,
	) );

	// Exterior Container Background
	$wp_customize->add_setting( 'culturacsi_outer_bg', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_outer_bg', array(
		'label'    => __( 'Exterior Container Background', 'culturacsi' ),
		'section'  => 'culturacsi_layout_containers',
		'priority' => 11,
	) ) );

	// Middle Container (.site-wrapper) Width
	$wp_customize->add_setting( 'culturacsi_wrapper_width', array(
		'default'           => '100%',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_wrapper_width', array(
		'label'    => __( 'Middle Container Width', 'culturacsi' ),
		'description' => __( 'e.g. 100%, 1400px, 90vw', 'culturacsi' ),
		'section'  => 'culturacsi_layout_containers',
		'type'     => 'text',
	) );

	// Middle Container Background
	$wp_customize->add_setting( 'culturacsi_wrapper_bg', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_wrapper_bg', array(
		'label'    => __( 'Middle Container Background', 'culturacsi' ),
		'section'  => 'culturacsi_layout_containers',
	) ) );

	// Inner Container Layout Mode
	$wp_customize->add_setting( 'culturacsi_inner_layout_mode', array(
		'default'           => 'boxed',
		'sanitize_callback' => 'sanitize_key',
	) );
	$wp_customize->add_control( 'culturacsi_inner_layout_mode', array(
		'label'    => __( 'Inner Container Layout Mode', 'culturacsi' ),
		'description' => __( 'Choose "Fluid" to let content stretch to the edges of the Wrapper. Choose "Boxed" to constrain it to a specific width.', 'culturacsi' ),
		'section'  => 'culturacsi_layout_containers',
		'type'     => 'radio',
		'choices'  => array(
			'boxed' => __( 'Boxed (Fixed Width)', 'culturacsi' ),
			'fluid' => __( 'Fluid (100% of Wrapper)', 'culturacsi' ),
		),
	) );

	// Inner Container (.site-inner) Width
	$wp_customize->add_setting( 'culturacsi_inner_width', array(
		'default'           => '1200px',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_inner_width', array(
		'label'    => __( 'Inner Container Width', 'culturacsi' ),
		'description' => __( 'e.g. 1200px, 90%', 'culturacsi' ),
		'section'  => 'culturacsi_layout_containers',
		'type'     => 'text',
	) );

	// Inner Container Background
	$wp_customize->add_setting( 'culturacsi_inner_bg', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_inner_bg', array(
		'label'    => __( 'Inner Container Background', 'culturacsi' ),
		'section'  => 'culturacsi_layout_containers',
	) ) );


	/**
	 * 3. Global Typography
	 */
	$wp_customize->add_section( 'culturacsi_typography', array(
		'title'       => __( 'Global Typography', 'culturacsi' ),
		'priority'    => 25,
	) );

	// Font Families
	$font_choices = array(
		'Roboto, sans-serif' => 'Roboto (Sans-serif)',
		'Open Sans, sans-serif' => 'Open Sans',
		'Montserrat, sans-serif' => 'Montserrat',
		'Playfair Display, serif' => 'Playfair Display (Serif)',
		'Arial, Helvetica, sans-serif' => 'Arial / Helvetica',
		'Georgia, serif' => 'Georgia',
	);

	$wp_customize->add_setting( 'culturacsi_font_base_family', array(
		'default'           => 'Roboto, sans-serif',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_font_base_family', array(
		'label'    => __( 'Base Font Family', 'culturacsi' ),
		'section'  => 'culturacsi_typography',
		'type'     => 'select',
		'choices'  => $font_choices,
	) );

	$wp_customize->add_setting( 'culturacsi_font_headings_family', array(
		'default'           => 'Roboto, sans-serif',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_font_headings_family', array(
		'label'    => __( 'Headings Font Family', 'culturacsi' ),
		'section'  => 'culturacsi_typography',
		'type'     => 'select',
		'choices'  => $font_choices,
	) );

	$wp_customize->add_setting( 'culturacsi_font_base_size', array(
		'default'           => '16px',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_font_base_size', array(
		'label'    => __( 'Base Font Size', 'culturacsi' ),
		'section'  => 'culturacsi_typography',
		'type'     => 'text',
	) );

	// Heading Sizes
	$headings = array( 'h1' => '2.5rem', 'h2' => '2rem', 'h3' => '1.75rem', 'h4' => '1.5rem', 'h5' => '1.25rem', 'h6' => '1rem' );
	foreach ( $headings as $tag => $default_size ) {
		$wp_customize->add_setting( "culturacsi_font_{$tag}_size", array(
			'default'           => $default_size,
			'sanitize_callback' => 'sanitize_text_field',
		) );
		$wp_customize->add_control( "culturacsi_font_{$tag}_size", array(
			'label'    => sprintf( __( '%s Font Size', 'culturacsi' ), strtoupper( $tag ) ),
			'section'  => 'culturacsi_typography',
			'type'     => 'text',
		) );
	}

	/**
	 * 4. Header Options
	 */
	$wp_customize->add_section( 'culturacsi_header', array(
		'title'       => __( 'Header Options', 'culturacsi' ),
		'priority'    => 30,
	) );

	// Header Background Color
	$wp_customize->add_setting( 'culturacsi_header_bg_color', array(
		'default'           => '#ffffff',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_header_bg_color', array(
		'label'    => __( 'Header Background Color', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'priority' => 1,
	) ) );

	// Inherit Middle Container Width
	$wp_customize->add_setting( 'culturacsi_header_inherit_wrapper_width', array(
		'default'           => false,
		'sanitize_callback' => 'culturacsi_sanitize_checkbox',
	) );
	$wp_customize->add_control( 'culturacsi_header_inherit_wrapper_width', array(
		'label'    => __( 'Inherit Width from Middle Container', 'culturacsi' ),
		'description' => __( 'If checked, Header will match the width of the Middle Container.', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'checkbox',
		'priority' => 2,
	) );

	// Header Wrapper Width (Control if not inherited)
	$wp_customize->add_setting( 'culturacsi_header_wrapper_width', array(
		'default'           => '100%',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_header_wrapper_width', array(
		'label'    => __( 'Header Wrapper Width', 'culturacsi' ),
		'description' => __( 'Width of the header container (if not inheriting).', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'text',
		'priority' => 3,
	) );

	// Header Wrapper Padding
	$wp_customize->add_setting( 'culturacsi_header_wrapper_padding', array(
		'default'           => '0',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_header_wrapper_padding', array(
		'label'    => __( 'Header Wrapper Padding (px)', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'text',
		'priority' => 4,
	) );

	// Header Container Width - FIX: Added missing setting
	$wp_customize->add_setting( 'culturacsi_header_container_width', array(
		'default'           => '1200px',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_header_container_width', array(
		'label'    => __( 'Header Logos Container Width', 'culturacsi' ),
		'description' => __( 'e.g. 1200px, 100%, 95vw. Set to same as Middle Container for alignment.', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'text',
		'priority' => 5,
	) );

	// Header Container Padding X
	$wp_customize->add_setting( 'culturacsi_header_padding_x', array(
		'default'           => '20px',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_header_padding_x', array(
		'label'    => __( 'Header Horizontal Padding', 'culturacsi' ),
		'description' => __( 'Left/Right padding for the header logos area.', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'text',
		'priority' => 6,
	) );

	// Navbar Container Width
	$wp_customize->add_setting( 'culturacsi_nav_container_width', array(
		'default'           => '1200px',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_nav_container_width', array(
		'label'    => __( 'Navbar Container Width', 'culturacsi' ),
		'description' => __( 'e.g. 1200px, 100%', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'text',
		'priority' => 7,
	) );

	// Logo Layout Mode
	$wp_customize->add_setting( 'culturacsi_header_layout_mode', array(
		'default'           => 'absolute',
		'sanitize_callback' => 'sanitize_key',
	) );
	$wp_customize->add_control( 'culturacsi_header_layout_mode', array(
		'label'    => __( 'Logo Layout Mode', 'culturacsi' ),
		'description' => __( 'Absolute: Center logo floats (good for wide headers). Flow: Logos push each other (avoids overlap).', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'radio',
		'choices'  => array(
			'absolute' => __( 'Absolute Center', 'culturacsi' ),
			'flow'     => __( 'Flex Flow (Space Between)', 'culturacsi' ),
		),
		'priority' => 8,
	) );

	// Center Logo Max Width (Existing)
	$wp_customize->add_setting( 'culturacsi_logo_width', array(
		'default'           => '200',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'culturacsi_logo_width', array(
		'label'       => __( 'Center Logo Max Width (px)', 'culturacsi' ),
		'section'     => 'culturacsi_header',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 50, 'max' => 500, 'step' => 5 ),
		'priority'    => 10,
	) );

	// Left Logo Width (CONI)
	$wp_customize->add_setting( 'culturacsi_left_logo_width', array(
		'default'           => '100',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'culturacsi_left_logo_width', array(
		'label'       => __( 'Left Logo Width (px)', 'culturacsi' ),
		'section'     => 'culturacsi_header',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 20, 'max' => 300, 'step' => 5 ),
		'priority'    => 11,
	) );

	// Area Riservata Button Size (Font Scale)
	$wp_customize->add_setting( 'culturacsi_riservata_size', array(
		'default'           => '0.9', // rem
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_riservata_size', array(
		'label'       => __( 'Area Riservata Button (Font Size rem)', 'culturacsi' ),
		'section'     => 'culturacsi_header',
		'type'        => 'number',
		'input_attrs' => array( 'min' => 0.5, 'max' => 2.0, 'step' => 0.05 ),
		'priority'    => 12,
	) );

	// Area Riservata Button Padding X
	$wp_customize->add_setting( 'culturacsi_riservata_padding_x', array(
		'default'           => '15',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'culturacsi_riservata_padding_x', array(
		'label'       => __( 'Button Padding Horizontal (px)', 'culturacsi' ),
		'section'     => 'culturacsi_header',
		'type'        => 'number',
		'priority'    => 13,
	) );

	// Area Riservata Button Padding Y
	$wp_customize->add_setting( 'culturacsi_riservata_padding_y', array(
		'default'           => '5',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'culturacsi_riservata_padding_y', array(
		'label'       => __( 'Button Padding Vertical (px)', 'culturacsi' ),
		'section'     => 'culturacsi_header',
		'type'        => 'number',
		'priority'    => 14,
	) );

	$wp_customize->add_setting( 'culturacsi_sticky_header', array(
		'default'           => false,
		'sanitize_callback' => 'culturacsi_sanitize_checkbox',
	) );
	$wp_customize->add_control( 'culturacsi_sticky_header', array(
		'label'    => __( 'Enable Sticky Header', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'checkbox',
	) );

	$wp_customize->add_setting( 'culturacsi_header_top_padding', array(
		'default'           => '15',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'culturacsi_header_top_padding', array(
		'label'       => __( 'Logos Area Top Padding (px)', 'culturacsi' ),
		'section'     => 'culturacsi_header',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
		'priority'    => 20,
	) );

	$wp_customize->add_setting( 'culturacsi_header_bottom_padding', array(
		'default'           => '15',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'culturacsi_header_bottom_padding', array(
		'label'       => __( 'Logos Area Bottom Padding (px)', 'culturacsi' ),
		'section'     => 'culturacsi_header',
		'type'        => 'range',
		'input_attrs' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
		'priority'    => 21,
	) );

	// NEW: Global Header Bottom Margin/Padding
	$wp_customize->add_setting( 'culturacsi_header_global_bottom_padding', array(
		'default'           => '0',
		'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( 'culturacsi_header_global_bottom_padding', array(
		'label'       => __( 'Header Total Bottom Padding (px)', 'culturacsi' ),
		'description' => __( 'Space below the entire header (Navbar).', 'culturacsi' ),
		'section'     => 'culturacsi_header',
		'type'        => 'number',
		'priority'    => 22,
	) );

	$wp_customize->add_setting( 'culturacsi_transparent_header_global', array(
		'default'           => false,
		'sanitize_callback' => 'culturacsi_sanitize_checkbox',
	) );
	$wp_customize->add_control( 'culturacsi_transparent_header_global', array(
		'label'    => __( 'Enable Transparent Header Globally', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'checkbox',
	) );

	// Area Riservata URL
	$wp_customize->add_setting( 'header_area_riservata_url', array(
		'default'           => '#',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'header_area_riservata_url', array(
		'label'    => __( 'Area Riservata URL', 'culturacsi' ),
		'section'  => 'culturacsi_header',
		'type'     => 'url',
		'priority' => 15,
	) );

	/**
	 * 5. Navigation Options
	 */
	$wp_customize->add_section( 'culturacsi_navigation', array(
		'title'       => __( 'Navigation Options', 'culturacsi' ),
		'priority'    => 31,
	) );

	$wp_customize->add_setting( 'culturacsi_nav_bg_color', array(
		'default'           => '#0A3B75',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_nav_bg_color', array(
		'label'    => __( 'Nav Background Color', 'culturacsi' ),
		'section'  => 'culturacsi_navigation',
	) ) );

	$wp_customize->add_setting( 'culturacsi_nav_link_color', array(
		'default'           => '#ffffff',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_nav_link_color', array(
		'label'    => __( 'Nav Link Color', 'culturacsi' ),
		'section'  => 'culturacsi_navigation',
	) ) );

	$wp_customize->add_setting( 'culturacsi_nav_link_hover_color', array(
		'default'           => '#1E5BB0',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_nav_link_hover_color', array(
		'label'    => __( 'Nav Link Hover Color', 'culturacsi' ),
		'section'  => 'culturacsi_navigation',
	) ) );

	$wp_customize->add_setting( 'culturacsi_nav_font_size', array(
		'default'           => '0.95rem',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_nav_font_size', array(
		'label'    => __( 'Nav Font Size', 'culturacsi' ),
		'section'  => 'culturacsi_navigation',
		'type'     => 'text',
	) );

	$wp_customize->add_setting( 'culturacsi_nav_padding_y', array(
		'default'           => '15px',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_nav_padding_y', array(
		'label'    => __( 'Navbar Vertical Padding', 'culturacsi' ),
		'section'  => 'culturacsi_navigation',
		'type'     => 'text',
	) );

	// FIX: Added missing control for nav_padding_x
	$wp_customize->add_setting( 'culturacsi_nav_padding_x', array(
		'default'           => '20px',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'culturacsi_nav_padding_x', array(
		'label'    => __( 'Navbar Horizontal Padding', 'culturacsi' ),
		'section'  => 'culturacsi_navigation',
		'type'     => 'text',
	) );

	$wp_customize->add_setting( 'culturacsi_nav_full_width_bg', array(
		'default'           => false,
		'sanitize_callback' => 'culturacsi_sanitize_checkbox',
	) );
	$wp_customize->add_control( 'culturacsi_nav_full_width_bg', array(
		'label'    => __( 'Full Width Navbar Background', 'culturacsi' ),
		'description' => __( 'If checked, the background color will stretch to the full width of the screen. If unchecked, it will be contained within the Navbar width.', 'culturacsi' ),
		'section'  => 'culturacsi_navigation',
		'type'     => 'checkbox',
	) );


	/**
	 * 6. Footer Options
	 */
	$wp_customize->add_section( 'culturacsi_footer', array(
		'title'       => __( 'Footer Options', 'culturacsi' ),
		'priority'    => 100,
	) );

	$wp_customize->add_setting( 'culturacsi_copyright_text', array(
		'default'           => '© 2023 CulturaCSI. All rights reserved.',
		'sanitize_callback' => 'wp_kses_post',
	) );
	$wp_customize->add_control( 'culturacsi_copyright_text', array(
		'label'    => __( 'Copyright Text', 'culturacsi' ),
		'section'  => 'culturacsi_footer',
		'type'     => 'textarea',
	) );

	$wp_customize->add_setting( 'culturacsi_footer_bg_color', array(
		'default'           => '#0A3B75',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_footer_bg_color', array(
		'label'    => __( 'Footer Background Color', 'culturacsi' ),
		'section'  => 'culturacsi_footer',
	) ) );

	$wp_customize->add_setting( 'culturacsi_footer_text_color', array(
		'default'           => '#ffffff',
		'sanitize_callback' => 'sanitize_hex_color',
	) );
	$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'culturacsi_footer_text_color', array(
		'label'    => __( 'Footer Text Color', 'culturacsi' ),
		'section'  => 'culturacsi_footer',
	) ) );

	// Footer Contact Information
	$wp_customize->add_setting( 'footer_phone', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_text_field',
	) );
	$wp_customize->add_control( 'footer_phone', array(
		'label'    => __( 'Footer Phone Number', 'culturacsi' ),
		'section'  => 'culturacsi_footer',
		'type'     => 'text',
	) );

	$wp_customize->add_setting( 'footer_email', array(
		'default'           => '',
		'sanitize_callback' => 'sanitize_email',
	) );
	$wp_customize->add_control( 'footer_email', array(
		'label'    => __( 'Footer Email Address', 'culturacsi' ),
		'section'  => 'culturacsi_footer',
		'type'     => 'email',
	) );

	$wp_customize->add_setting( 'footer_facebook', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'footer_facebook', array(
		'label'    => __( 'Facebook URL', 'culturacsi' ),
		'section'  => 'culturacsi_footer',
		'type'     => 'url',
	) );

	$wp_customize->add_setting( 'footer_instagram', array(
		'default'           => '',
		'sanitize_callback' => 'esc_url_raw',
	) );
	$wp_customize->add_control( 'footer_instagram', array(
		'label'    => __( 'Instagram URL', 'culturacsi' ),
		'section'  => 'culturacsi_footer',
		'type'     => 'url',
	) );

}

/**
 * Sanitize Checkbox
 */
function culturacsi_sanitize_checkbox( $checked ) {
	return ( ( isset( $checked ) && true == $checked ) ? true : false );
}

add_action( 'customize_register', 'culturacsi_customize_register' );
