<?php
/**
 * Night mode toggle: the prototype's `ThemeToggle` button (`Footer.jsx`).
 *
 * The server cannot know the stored choice, so it always prints the cache-safe default
 * (`aria-pressed="false"`); view.js syncs it from the document on hydration.
 *
 * @package IShot\Theme
 */

defined( 'ABSPATH' ) || exit;

$ishot_context = wp_interactivity_data_wp_context(
	array(
		'storageKey' => ishot_theme_storage_key(),
		'pressed'    => false,
	)
);
?>
<button type="button" class="ish-linkbtn" aria-pressed="false" data-wp-interactive="ishot/theme" <?php echo $ishot_context; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by core. ?> data-wp-init="callbacks.sync" data-wp-on--click="actions.toggle" data-wp-bind--aria-pressed="context.pressed"><?php echo esc_html__( 'Night mode', 'ishot-concept' ); ?></button>
