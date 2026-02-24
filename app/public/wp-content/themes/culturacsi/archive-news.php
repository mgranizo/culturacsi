<?php
/**
 * The template for displaying the News archive.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Culturacsi
 */

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

        <header class="page-header">
            <h1 class="page-title"><?php _e( 'Notizie', 'culturacsi' ); ?></h1>
        </header><!-- .page-header -->

        <div class="news-grid">
            <?php
            if ( have_posts() ) :
                while ( have_posts() ) :
                    the_post();
                    ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'news-item' ); ?>>
                        <div class="news-item-inner">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <div class="news-item-image">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail( 'medium_large' ); ?>
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="news-item-content">
                                <header class="entry-header">
                                    <?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
                                </header><!-- .entry-header -->

                                <div class="entry-summary">
                                    <?php the_excerpt(); ?>
                                </div><!-- .entry-summary -->
                                 <a href="<?php the_permalink(); ?>" class="read-more"><?php _e('Leggi di piu', 'culturacsi'); ?></a>
                            </div><!-- .news-item-content -->
                        </div><!-- .news-item-inner -->
                    </article><!-- #post-<?php the_ID(); ?> -->
                    <?php
                endwhile;
            else :
                ?>
                <p><?php esc_html_e( 'Nessuna notizia trovata.', 'culturacsi' ); ?></p>
                <?php
            endif;
            ?>
        </div><!-- .news-grid -->

        <?php the_posts_pagination(); ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();

