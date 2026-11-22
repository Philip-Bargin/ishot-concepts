<?php
/**
 * Title: Page title
 * Slug: ishot-concept/page-title
 * Categories: text
 * Description: Eyebrow, the page's title and an optional lead, as the prototype's PageTitle. The sample-content notice shows above them while the page is a placeholder.
 * Keywords: title, heading, eyebrow, lead
 *
 * The prototype's PageTitle (src/components/ui.jsx at 50e60f4): section.fo-section.ish-title >
 * div.fo-container.ish-stack > p.fo-eyebrow, h1.fo-h1, p.lead.fo-muted.ish-measure. The H1 is the
 * post title (core/post-title adds its own wp-block-post-title class: accepted markup exception,
 * ruling R-1.4-2). An empty eyebrow or lead is not printed (inc/templates.php). The ishot/notice
 * block prints the "Sample content." notice first in the stack while the content is a placeholder,
 * as the prototype's title blocks do, and nothing otherwise.
 *
 * @package IShot\Theme
 */

?>
<!-- wp:group {"tagName":"section","className":"fo-section ish-title","layout":{"type":"default"}} -->
<section class="wp-block-group fo-section ish-title"><!-- wp:group {"className":"fo-container ish-stack","layout":{"type":"default"}} -->
<div class="wp-block-group fo-container ish-stack"><!-- wp:ishot/notice /-->

<!-- wp:paragraph {"className":"fo-eyebrow","placeholder":"<?php echo esc_attr__( 'Eyebrow (optional)', 'ishot-concept' ); ?>"} -->
<p class="fo-eyebrow"></p>
<!-- /wp:paragraph -->

<!-- wp:post-title {"level":1,"className":"fo-h1"} /-->

<!-- wp:paragraph {"className":"lead fo-muted ish-measure","placeholder":"<?php echo esc_attr__( 'Lead (optional)', 'ishot-concept' ); ?>"} -->
<p class="lead fo-muted ish-measure"></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></section>
<!-- /wp:group -->
