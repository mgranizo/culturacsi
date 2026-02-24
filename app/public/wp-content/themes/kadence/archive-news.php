<?php
/**
 * The template for displaying the News archive
 *
 * @package kadence
 */

namespace Kadence;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<div id="primary" class="content-area">
	<main id="main" class="site-main" role="main">
		<div class="archive-container" style="padding: 2rem 0;">
			<header class="page-header">
				<h1 class="page-title" style="text-align: center; margin-bottom: 2rem;"><?php echo esc_html__( 'Notizie', 'kadence' ); ?></h1>
			</header><!-- .page-header -->

			<?php if ( have_posts() ) : ?>
				<div class="news-grid-container">
					<?php
					while ( have_posts() ) :
						the_post();
						?>
						<article id="post-<?php the_ID(); ?>" <?php post_class( 'news-grid-item' ); ?>>
							<a href="<?php the_permalink(); ?>" class="news-grid-item-link">
								<?php if ( has_post_thumbnail() ) : ?>
									<div class="news-grid-item-image">
										<?php the_post_thumbnail( 'medium_large' ); ?>
									</div>
								<?php else : ?>
									<div class="news-grid-item-image placeholder"></div>
								<?php endif; ?>
								<div class="news-grid-item-content">
									<?php the_title( '<h2 class="entry-title">', '</h2>' ); ?>
									<div class="entry-meta">
										<span class="posted-on"><?php echo get_the_date(); ?></span>
									</div>
									<div class="entry-summary">
										<?php the_excerpt(); ?>
									</div>
								</div>
							</a>
						</article><!-- #post-<?php the_ID(); ?> -->
					<?php endwhile; ?>
				</div><!-- .news-grid -->

				<?php 
                // Styled pagination
                the_posts_pagination( array(
                    'mid_size'  => 2,
                    'prev_text' => __( '‹ Prec', 'kadence' ),
                    'next_text' => __( 'Succ ›', 'kadence' ),
                ) ); 
                ?>

			<?php else : ?>

				<section class="no-results not-found">
					<p><?php esc_html_e( 'Nessuna notizia trovata.', 'kadence' ); ?></p>
				</section><!-- .no-results -->

			<?php endif; ?>
		</div>
	</main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
