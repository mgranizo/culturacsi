<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Stylesheets and scripts enqueued via functions.php -->
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="site-outer">

    <!-- Header Section -->
    <?php
    $hide_header = false;
    $transparent_header = false;
    if ( is_singular() ) {
        $hide_header = get_post_meta( get_the_ID(), '_culturacsi_hide_header', true );
        $transparent_header = get_post_meta( get_the_ID(), '_culturacsi_transparent_header', true );
    }
    if ( ! $transparent_header ) {
        $transparent_header = get_theme_mod( 'culturacsi_transparent_header_global', false );
    }
    if ( ! $hide_header ) :
    ?>
    <header<?php echo $transparent_header ? ' class="header-transparent"' : ''; ?>>
        <div class="top-bar">
            <div class="header-container">
                <div class="top-bar-inner">
                    <div class="logo-left">
                        <img src="<?php echo get_template_directory_uri(); ?>/images/CONI.png" alt="CONI">
                    </div>
                    <div class="logo-center">
                        <img src="<?php echo get_template_directory_uri(); ?>/images/CULTURA.png" alt="CULTURACSI Logo" class="main-logo-img">
                        <div class="site-tagline">Associazione di Cultura Sport e Tempo Libero</div>
                    </div>
                    <div class="user-area">
                        <a href="<?php echo esc_url( home_url( '/area-riservata/' ) ); ?>" class="area-riservata-btn">
                            AREA RISERVATA <i class="fas fa-lock"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <nav class="main-nav">
            <div class="nav-container">
                <?php
                wp_nav_menu( array(
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'nav-list',
                    'fallback_cb'    => 'culturacsi_fallback_menu',
                    'items_wrap'     => '<ul class="%2$s">%3$s</ul>',
                    'walker'         => new Culturacsi_Walker_Nav_Menu(),
                ) );
                ?>
            </div>
        </nav>
    </header>
    <?php endif; ?>

    <div class="site-wrapper">
        <div class="site-inner">
