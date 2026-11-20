<?php
// Dev only: creates/updates a "Style check" page exercising core blocks under the theme.
// wp-env run cli wp eval-file wp-content/ishot-dev/style-check.php
$content = <<<'HTML'
<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">Heading one</h1><!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"lead"} --><p class="has-lead-font-size">Lead paragraph: an editorial photography studio in Monrovia, telling stories with light.</p><!-- /wp:paragraph -->
<!-- wp:heading --><h2 class="wp-block-heading">Heading two</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p>Body copy with <a href="#">an inline link</a>. Space Grotesk at sixteen pixels on paper, ink colour.</p><!-- /wp:paragraph -->
<!-- wp:heading {"level":3} --><h3 class="wp-block-heading">Heading three</h3><!-- /wp:heading -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Book a session</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#">View portfolio</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:separator --><hr class="wp-block-separator has-alpha-channel-opacity"/><!-- /wp:separator -->
<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>They captured our wedding exactly as it felt.</p><!-- /wp:paragraph --><cite>A client</cite></blockquote><!-- /wp:quote -->
<!-- wp:group {"backgroundColor":"band","textColor":"on-band","style":{"spacing":{"padding":{"top":"var:preset|spacing|6","bottom":"var:preset|spacing|6","left":"var:preset|spacing|4","right":"var:preset|spacing|4"}}}} --><div class="wp-block-group has-on-band-color has-band-background-color has-text-color has-background" style="padding-top:var(--wp--preset--spacing--6);padding-right:var(--wp--preset--spacing--4);padding-bottom:var(--wp--preset--spacing--6);padding-left:var(--wp--preset--spacing--4)"><!-- wp:heading {"textColor":"on-band"} --><h2 class="wp-block-heading has-on-band-color has-text-color">On the band</h2><!-- /wp:heading --><!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">Book now</a></div><!-- /wp:button --></div><!-- /wp:buttons --></div><!-- /wp:group -->
HTML;
$existing = get_page_by_path( 'style-check' );
$id = wp_insert_post( array( 'ID' => $existing ? $existing->ID : 0, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Style check', 'post_name' => 'style-check', 'post_content' => $content ) );
echo get_permalink( $id ), PHP_EOL;
