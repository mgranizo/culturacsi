<?php
get_header();
?>

<main class="site-main">
    <div class="container">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                the_content();
            endwhile;
    else :
        ?>
        <div class="container">
            <p><?php esc_html_e( 'Nessun contenuto trovato', 'culturacsi' ); ?></p>
        </div>
        <?php
        endif;
        ?>
    </div>
</main>

<?php
get_footer();

