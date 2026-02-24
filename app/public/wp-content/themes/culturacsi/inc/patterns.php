<?php
/**
 * Register Block Patterns
 */

function culturacsi_register_patterns() {
    
    // 1. Hero Pattern
    register_block_pattern(
        'culturacsi/hero-section',
        array(
            'title'       => __( 'Sezione Hero Slider', 'culturacsi' ),
            'categories'  => array( 'header', 'featured' ),
            'content'     => '<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"100px","bottom":"100px"}}},"backgroundColor":"light-gray","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-light-gray-background-color has-background" style="padding-top:100px;padding-bottom:100px"><!-- wp:heading {"textAlign":"center","style":{"typography":{"textTransform":"uppercase"}}} -->
<h2 class="wp-block-heading has-text-align-center" style="text-transform:uppercase">Titolo sezione Hero</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Questa e una sezione hero dinamica. Puoi sostituire immagine di sfondo e testi tramite editor.</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
        )
    );

    // 2. Services Grid Pattern
    register_block_pattern(
        'culturacsi/services-grid',
        array(
            'title'       => __( 'Griglia Servizi 5 Colonne', 'culturacsi' ),
            'categories'  => array( 'services' ),
            'content'     => '<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide"><!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">CONVENZIONI</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">FORMAZIONE</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">EVENTI</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">PROGETTI</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:image {"aspectRatio":"4/3","scale":"cover"} -->
<figure class="wp-block-image"><img alt=""/></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"align":"center","style":{"typography":{"fontSize":"0.9rem","fontWeight":"700"}},"backgroundColor":"primary-blue","textColor":"white"} -->
<p class="has-text-color has-white-color has-background has-primary-blue-background-color has-text-align-center" style="font-size:0.9rem;font-weight:700">CROWDFUNDING</p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->',
        )
    );

}
add_action( 'init', 'culturacsi_register_patterns' );
