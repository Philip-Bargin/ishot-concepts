<?php
/**
 * Menu items for the site header's menu sheet: from a core wp_navigation entity, or the prototype's
 * eight `navItems` (src/data.js) when there is none. The current item is worked out from the request
 * URL only, never from the viewer, so the markup stays page-cache safe.
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * The prototype's navItems with their WordPress URLs. Site structure, not business content.
 *
 * @return list<array{label: string, url: string}>
 */
function ishot_theme_default_nav_items(): array {
	$items = array(
		'/'                    => __( 'Home', 'ishot-concept' ),
		'/about/'              => __( 'About', 'ishot-concept' ),
		'/services/'           => __( 'Services', 'ishot-concept' ),
		'/portfolio/'          => __( 'Portfolio', 'ishot-concept' ),
		'/creative-direction/' => __( 'Creative direction', 'ishot-concept' ),
		'/journal/'            => __( 'Journal', 'ishot-concept' ),
		'/contact/'            => __( 'Contact', 'ishot-concept' ),
		'/book/'               => __( 'Book a session', 'ishot-concept' ),
	);
	$out   = array();
	foreach ( $items as $path => $label ) {
		$out[] = array(
			'label' => $label,
			'url'   => home_url( $path ),
		);
	}
	return $out;
}

/**
 * The published wp_navigation to read: `$ref` when it is a published navigation; otherwise the
 * published one titled "Primary" (the menu `wp ishot import-prototype` creates and the owner edits,
 * preferred over any older menu core may have left behind); otherwise the first published one
 * (oldest first); otherwise null.
 *
 * @param int $ref Post ID from the block's `ref` attribute (0 = none).
 */
function ishot_theme_find_navigation( int $ref ): ?WP_Post {
	if ( $ref > 0 ) {
		$post = get_post( $ref );
		if ( $post instanceof WP_Post && 'wp_navigation' === $post->post_type && 'publish' === $post->post_status ) {
			return $post;
		}
	}
	$query = array(
		'post_type'      => 'wp_navigation',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	);
	$posts = get_posts( array_merge( $query, array( 'title' => 'Primary' ) ) );
	if ( array() === $posts ) {
		$posts = get_posts( $query );
	}
	return $posts[0] ?? null;
}

/**
 * Menu items: the top-level `core/navigation-link` blocks of the navigation (label and url; every
 * other block type is ignored). Falls back to the prototype items when there is no published
 * navigation or it holds no usable link.
 *
 * @param int $ref Post ID from the block's `ref` attribute (0 = none).
 * @return list<array{label: string, url: string}>
 */
function ishot_theme_nav_items( int $ref = 0 ): array {
	$nav   = ishot_theme_find_navigation( $ref );
	$items = array();
	foreach ( null === $nav ? array() : parse_blocks( $nav->post_content ) as $block ) {
		if ( 'core/navigation-link' !== $block['blockName'] ) {
			continue;
		}
		$label = trim( wp_strip_all_tags( (string) ( $block['attrs']['label'] ?? '' ) ) );
		$url   = esc_url_raw( (string) ( $block['attrs']['url'] ?? '' ) );
		if ( '' !== $label && '' !== $url ) {
			$items[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}
	}
	return array() === $items ? ishot_theme_default_nav_items() : $items;
}

/** The path of the current request, with a trailing slash ('/' when unknown). */
function ishot_theme_request_path(): string {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_url() is applied.
	$path = wp_parse_url( $uri, PHP_URL_PATH );
	return trailingslashit( is_string( $path ) ? $path : '/' );
}

/**
 * Whether a menu URL is the current page, as the prototype's NavLink decides: the home link only on
 * an exact match (`end`), any other link on its own path or below it (a journal post marks Journal).
 * Links to another host are never current.
 *
 * @param string $url          Menu link URL (absolute, or a path).
 * @param string $request_path Current request path with a trailing slash.
 */
function ishot_theme_is_current_url( string $url, string $request_path ): bool {
	$parts = wp_parse_url( $url );
	if ( ! is_array( $parts ) ) {
		return false;
	}
	$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( isset( $parts['host'] ) && strtolower( $parts['host'] ) !== strtolower( (string) $home_host ) ) {
		return false;
	}
	$path      = trailingslashit( (string) ( $parts['path'] ?? '/' ) );
	$home_path = trailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	if ( $path === $home_path ) {
		return $request_path === $home_path;
	}
	return str_starts_with( $request_path, $path );
}
