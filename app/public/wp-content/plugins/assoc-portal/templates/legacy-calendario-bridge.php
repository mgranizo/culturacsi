<?php
/**
 * Legacy calendar bridge template.
 * Renders the new [events_calendar] output when legacy TEC archive is requested.
 *
 * @package assoc-portal
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <header class="page-header">
            <h1 class="page-title"><?php esc_html_e( 'Calendario', 'assoc-portal' ); ?></h1>
        </header>
        <?php echo do_shortcode( '[events_calendar]' ); ?>
    </main>
</div>

<?php
get_footer();
