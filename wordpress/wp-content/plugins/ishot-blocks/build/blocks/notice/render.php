<?php
/**
 * Sample content notice: the prototype's PrototypeNotice (src/components/ui.jsx) for the current
 * post while its content_status is placeholder, otherwise nothing. The post comes from the block
 * context (singular templates, the editor preview), else the loop.
 *
 * @package IShot\Blocks
 *
 * @var WP_Block $block The block instance.
 */

defined( 'ABSPATH' ) || exit;

$ishot_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();

echo \IShot\Blocks\Notice::forPost( $ishot_post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every value is escaped in Notice::render().
