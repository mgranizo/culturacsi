<?php
/**
 * The template for displaying all pages
 *
 * @package CulturaCSI
 */

get_header();
?>

<main class="site-main">
    <div class="container">
        <?php
        while ( have_posts() ) :
            the_post();
            
            // Check if title should be hidden
            $hide_title = get_post_meta( get_the_ID(), '_culturacsi_hide_title', true );
            if ( ! $hide_title ) :
                ?>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>
                <?php
            endif;
            ?>
            
            <div class="entry-content">
                <?php
                the_content();
                
                wp_link_pages( array(
                    'before' => '<div class="page-links">' . esc_html__( 'Pagine:', 'culturacsi' ),
                    'after'  => '</div>',
                ) );
                ?>
            </div>
            
            <?php
            // If comments are open or we have at least one comment, load up the comment template.
            if ( comments_open() || get_comments_number() ) :
                comments_template();
            endif;
        endwhile;
        ?>
    </div>
</main>

<?php
get_footer();

