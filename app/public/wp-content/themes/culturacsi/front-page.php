<?php
/**
 * Template Name: Pagina iniziale
 */
get_header();
?>

    <!-- Main Content Area -->
    <main class="site-main">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                the_content();
            endwhile;
        else :
            // Fallback content if no page content is found
            if ( current_user_can( 'publish_posts' ) ) :
                echo '<div class="container" style="padding: 50px 0; text-align: center;">';
                echo '<h2>Benvenuto nella tua nuova pagina iniziale</h2>';
                echo '<p>Questa area contenuti e attualmente vuota. Modifica questa pagina dalla Bacheca per aggiungere i tuoi contenuti.</p>';
                echo '</div>';
            endif;
        endif;
        ?>
    </main>

<?php get_footer(); ?>
