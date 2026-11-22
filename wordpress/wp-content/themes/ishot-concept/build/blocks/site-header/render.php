<?php
/**
 * Site header: the prototype's Header and MenuSheet (src/components/Header.jsx, MenuSheet.jsx).
 *
 * The header and the sheet are siblings, as in the prototype, so the sticky header is not wrapped.
 * Each is its own Interactivity API region on the shared `ishot/site-header` store. The sheet is
 * printed `hidden` and shown by view.js. Nothing here depends on the viewer (page-cache safe):
 * aria-current comes from the request URL.
 *
 * @package IShot\Theme
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

wp_interactivity_state( 'ishot/site-header', array( 'isOpen' => false ) );

$ishot_social = WP_Block_Type_Registry::get_instance()->is_registered( 'ishot/social-links' )
	? render_block(
		array(
			'blockName'    => 'ishot/social-links',
			'attrs'        => array(),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	)
	: '';

$ishot_icons = class_exists( 'IShot\Blocks\Icons' );
$ishot_home  = esc_url( home_url( '/' ) );
$ishot_book  = esc_url( home_url( '/book/' ) );
$ishot_path  = ishot_theme_request_path();
$ishot_list  = '';
foreach ( ishot_theme_nav_items( (int) ( $attributes['ref'] ?? 0 ) ) as $ishot_i => $ishot_item ) {
	$ishot_list .= sprintf(
		'<li style="--i:%1$d"><a href="%2$s"%3$s%4$s data-wp-on--click="actions.close"><span>%5$s</span>%6$s</a></li>',
		(int) $ishot_i,
		esc_url( $ishot_item['url'] ),
		ishot_theme_is_current_url( $ishot_item['url'], $ishot_path ) ? ' aria-current="page" class="active"' : '',
		0 === $ishot_i ? ' data-autofocus=""' : '',
		esc_html( sprintf( '%02d', $ishot_i + 1 ) ),
		esc_html( $ishot_item['label'] )
	);
}

$ishot_contact = '';
if ( class_exists( 'IShot\Booking\Studio' ) ) {
	$ishot_details = \IShot\Booking\Studio::details();
	$ishot_parts   = array();
	if ( '' !== $ishot_details['address'] ) {
		$ishot_parts[] = esc_html( $ishot_details['address'] );
	}
	if ( '' !== $ishot_details['phone'] ) {
		$ishot_parts[] = null !== $ishot_details['phone_href']
			? '<a href="' . esc_url( $ishot_details['phone_href'], array( 'tel' ) ) . '">' . esc_html( $ishot_details['phone'] ) . '</a>'
			: esc_html( $ishot_details['phone'] );
	}
	$ishot_contact = implode( ' · ', $ishot_parts );
}
?>
<header class="fo-header" data-wp-interactive="ishot/site-header"><div class="fo-container ish-header__inner"><a class="fo-logo" href="<?php echo $ishot_home; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url() above. ?>"><?php echo ishot_theme_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?></a><nav class="fo-header__social" aria-label="<?php echo esc_attr__( 'Social', 'ishot-concept' ); ?>"><?php echo $ishot_social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output, escaped by the block. ?></nav><div class="fo-header__actions"><a class="fo-navlink ish-signin" href="<?php echo esc_url( home_url( '/account/' ) ); ?>"><?php echo esc_html__( 'My bookings', 'ishot-concept' ); ?></a><a class="fo-btn fo-btn--primary fo-btn--sm" href="<?php echo $ishot_book; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url() above. ?>"><?php echo esc_html__( 'Book now', 'ishot-concept' ); ?></a><button type="button" class="fo-menu-toggle" aria-expanded="false" aria-controls="site-menu" data-wp-bind--aria-expanded="state.isOpen" data-wp-on--click="actions.open"><?php echo esc_html__( 'Menu', 'ishot-concept' ); ?> <?php echo $ishot_icons ? \IShot\Blocks\Icons::svg( 'menu', '', 20 ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></button></div></div></header><div id="site-menu" class="fo-menu fo-band" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr__( 'Site menu', 'ishot-concept' ); ?>" hidden data-wp-interactive="ishot/site-header" data-wp-bind--hidden="!state.isOpen" data-wp-watch="callbacks.trap"><div class="ish-menu__top"><a class="fo-logo" href="<?php echo $ishot_home; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url() above. ?>" data-wp-on--click="actions.close"><?php echo ishot_theme_logo_html( 'reversed' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the helper. ?></a><button type="button" class="fo-icon-btn" aria-label="<?php echo esc_attr__( 'Close menu', 'ishot-concept' ); ?>" data-wp-on--click="actions.close"><?php echo $ishot_icons ? \IShot\Blocks\Icons::svg( 'x' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static SVG. ?></button></div><nav aria-label="<?php echo esc_attr__( 'Main', 'ishot-concept' ); ?>"><ol class="fo-menu__list"><?php echo $ishot_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped values above. ?></ol></nav><div class="ish-menu__foot"><a class="fo-btn fo-btn--primary" href="<?php echo $ishot_book; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url() above. ?>" data-wp-on--click="actions.close"><?php echo esc_html__( 'Book a session', 'ishot-concept' ); ?></a><?php echo '' !== $ishot_contact ? '<p class="small">' . $ishot_contact . '</p>' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Parts escaped above. ?><div class="ish-social"><?php echo $ishot_social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block output, escaped by the block. ?></div></div></div><?php
