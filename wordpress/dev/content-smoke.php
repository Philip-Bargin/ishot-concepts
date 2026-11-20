<?php
// Dev only: checks the imported content on the dev site. wp eval-file wp-content/ishot-dev/content-smoke.php
use IShot\Booking\Content\ContentTypes;
wp_set_current_user( 1 );
$t   = wp_insert_post( array( 'post_type' => ContentTypes::TESTIMONIAL, 'post_title' => 'smoke' ) );
$req = new WP_REST_Request( 'POST', "/wp/v2/ishot-testimonials/{$t}" );
$req->set_param( 'meta', array( 'rating' => 7 ) );
echo 'REST rating 7 -> ', rest_do_request( $req )->get_status(), PHP_EOL;
wp_delete_post( $t, true );
$p = get_page_by_path( 'birthday-call-concept', OBJECT, ContentTypes::PROJECT );
echo 'project URL: ', get_permalink( $p ), ' (', $p->post_status, ', ', get_post_meta( $p->ID, 'content_status', true ), ')', PHP_EOL;
echo 'gallery: ', count( get_post_meta( $p->ID, 'gallery', true ) ), ' images; cover alt: ', get_post_meta( get_post_thumbnail_id( $p ), '_wp_attachment_image_alt', true ), PHP_EOL;
echo 'category URL: ', get_term_link( 'portraits', ContentTypes::CATEGORY ), PHP_EOL;
$img  = get_post_thumbnail_id( $p );
$meta = wp_get_attachment_metadata( $img );
echo 'cover: ', $meta['width'], 'x', $meta['height'], ', sizes: ', implode( ',', array_keys( $meta['sizes'] ) ), PHP_EOL;
foreach ( get_posts( array( 'post_type' => 'ishot_service', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $s ) {
	printf( "service %-24s %-6s %-11s bookable=%d price=%s\n", $s->post_name, $s->post_status, get_post_meta( $s->ID, 'content_status', true ), (int) get_post_meta( $s->ID, 'bookable', true ), get_post_meta( $s->ID, 'price_from', true ) );
}
