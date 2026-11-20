<?php
namespace IShot\Booking\Content;

use IShot\Booking\Catalog\ServiceCatalog;
use WP_Post;

/**
 * wp-admin editing for services, projects, testimonials and categories: a "Details" box with a
 * labelled field for every Content\Fields entry (no raw custom fields), a colour picker on the
 * category screens, and a visible Placeholder flag on imported prototype copy.
 */
final class ContentAdmin {

	public const NONCE      = 'ishot_content_nonce';
	public const TERM_NONCE = 'ishot_term_nonce';

	public const TYPES = array( ServiceCatalog::POST_TYPE, ContentTypes::PROJECT, ContentTypes::TESTIMONIAL );

	public static function register(): void {
		add_action( 'add_meta_boxes', array( self::class, 'addBoxes' ) );
		foreach ( self::TYPES as $type ) {
			add_action( "save_post_{$type}", array( self::class, 'save' ), 10, 2 );
			add_filter( "manage_{$type}_posts_columns", array( self::class, 'columns' ) );
			add_action( "manage_{$type}_posts_custom_column", array( self::class, 'column' ), 10, 2 );
		}
		add_action( ContentTypes::CATEGORY . '_add_form_fields', array( self::class, 'termFieldAdd' ) );
		add_action( ContentTypes::CATEGORY . '_edit_form_fields', array( self::class, 'termFieldEdit' ) );
		add_action( 'created_' . ContentTypes::CATEGORY, array( self::class, 'saveTerm' ) );
		add_action( 'edited_' . ContentTypes::CATEGORY, array( self::class, 'saveTerm' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	public static function addBoxes(): void {
		foreach ( self::TYPES as $type ) {
			add_meta_box( 'ishot-details', __( 'Details', 'ishot-booking' ), array( self::class, 'renderBox' ), $type, 'normal', 'high' );
		}
	}

	public static function renderBox( WP_Post $post ): void {
		wp_nonce_field( self::NONCE . $post->ID, self::NONCE );
		echo '<table class="form-table ishot-details" role="presentation"><tbody>';
		foreach ( Fields::for( $post->post_type ) as $key => $field ) {
			$id    = 'ishot-' . $key;
			$name  = 'ishot[' . $key . ']';
			$value = get_post_meta( $post->ID, $key, true );
			$help  = isset( $field['help'] ) ? '<p class="description" id="' . esc_attr( $id ) . '-help">' . esc_html( $field['help'] ) . '</p>' : '';
			$aria  = isset( $field['help'] ) ? ' aria-describedby="' . esc_attr( $id ) . '-help"' : '';
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
			echo self::input( $field, $id, $name, $value, $aria ) . $help; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- input() escapes; $help is escaped above.
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	private static function input( array $field, string $id, string $name, mixed $value, string $aria ): string {
		$attrs = 'id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $aria;
		switch ( $field['input'] ) {
			case 'checkbox':
				return '<input type="checkbox" value="1" ' . $attrs . checked( (bool) $value, true, false ) . '>';
			case 'textarea':
				return '<textarea rows="4" class="large-text" ' . $attrs . ' maxlength="' . (int) ( $field['schema']['maxLength'] ?? 1000 ) . '">' . esc_textarea( (string) $value ) . '</textarea>';
			case 'lines':
				return '<textarea rows="5" class="large-text" ' . $attrs . '>' . esc_textarea( implode( "\n", (array) $value ) ) . '</textarea>';
			case 'select':
				$options = '';
				foreach ( $field['schema']['enum'] as $option ) {
					$label    = '' === $option ? __( '— None —', 'ishot-booking' ) : ucfirst( (string) $option );
					$options .= '<option value="' . esc_attr( $option ) . '"' . selected( (string) $value, (string) $option, false ) . '>' . esc_html( $label ) . '</option>';
				}
				return '<select ' . $attrs . '>' . $options . '</select>';
			case 'number':
				$step = 'integer' === $field['type'] ? '1' : '0.01';
				return sprintf( '<input type="number" class="small-text" step="%s" min="%s" max="%s" value="%s" %s>', $step, esc_attr( (string) $field['schema']['minimum'] ), esc_attr( (string) $field['schema']['maximum'] ), esc_attr( (string) $value ), $attrs );
			case 'date':
				return '<input type="date" value="' . esc_attr( (string) $value ) . '" ' . $attrs . '>';
			case 'gallery':
				$ids    = array_map( 'intval', (array) $value );
				$thumbs = implode( '', array_map( static fn( int $i ) => '<li>' . wp_get_attachment_image( $i, 'thumbnail' ) . '</li>', $ids ) );
				return '<ul class="ishot-gallery__preview">' . $thumbs . '</ul>'
					. '<input type="text" class="regular-text ishot-gallery__ids" value="' . esc_attr( implode( ',', $ids ) ) . '" ' . $attrs . '>'
					. ' <button type="button" class="button ishot-gallery__pick" data-target="' . esc_attr( $id ) . '">' . esc_html__( 'Choose images', 'ishot-booking' ) . '</button>';
			default:
				return '<input type="text" class="regular-text" value="' . esc_attr( (string) $value ) . '" maxlength="' . (int) ( $field['schema']['maxLength'] ?? 200 ) . '" ' . $attrs . '>';
		}
	}

	/** Saves the Details box. Invalid values are left unchanged and reported on the next screen. */
	public static function save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$nonce = isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE . $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input  = isset( $_POST['ishot'] ) && is_array( $_POST['ishot'] ) ? wp_unslash( $_POST['ishot'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each field is sanitised by Fields::fromInput().
		$errors = array();
		foreach ( Fields::for( $post->post_type ) as $key => $field ) {
			if ( ! array_key_exists( $key, $input ) && 'checkbox' !== $field['input'] ) {
				continue;
			}
			$value = Fields::fromInput( $field, $input[ $key ] ?? null );
			if ( is_wp_error( $value ) ) {
				$errors[] = $field['label'] . ': ' . $value->get_error_message();
				continue;
			}
			update_post_meta( $post_id, $key, $value );
		}
		if ( array() !== $errors ) {
			set_transient( self::errorsKey( $post_id ), $errors, 5 * MINUTE_IN_SECONDS );
		}
		// Consent is enforced afterwards on wp_after_insert_post (ContentTypes::afterInsert).
	}

	/** @return string[] */
	public static function takeErrors( int $post_id ): array {
		$errors = get_transient( self::errorsKey( $post_id ) );
		delete_transient( self::errorsKey( $post_id ) );
		return is_array( $errors ) ? $errors : array();
	}

	private static function errorsKey( int $post_id ): string {
		return 'ishot_content_errors_' . get_current_user_id() . '_' . $post_id;
	}

	public static function notices(): void {
		$screen = get_current_screen();
		$post   = get_post();
		if ( null === $screen || 'post' !== $screen->base || ! $post instanceof WP_Post || ! in_array( $post->post_type, self::TYPES, true ) ) {
			return;
		}
		foreach ( self::takeErrors( $post->ID ) as $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $error ) . '</p></div>';
		}
		if ( Fields::PLACEHOLDER === get_post_meta( $post->ID, 'content_status', true ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Placeholder content: this text and its images came from the design prototype. Replace or confirm them, then set Content status to Approved.', 'ishot-booking' ) . '</p></div>';
		}
		if ( ContentTypes::TESTIMONIAL === $post->post_type && 'publish' !== $post->post_status && ! get_post_meta( $post->ID, 'consent', true ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'This testimonial can be published only after you tick "The client agreed to publication".', 'ishot-booking' ) . '</p></div>';
		}
	}

	/** @param array<string, string> $columns */
	public static function columns( array $columns ): array {
		$columns['ishot_content'] = __( 'Content', 'ishot-booking' );
		return $columns;
	}

	public static function column( string $column, int $post_id ): void {
		if ( 'ishot_content' !== $column ) {
			return;
		}
		echo Fields::PLACEHOLDER === get_post_meta( $post_id, 'content_status', true )
			? '<span class="ishot-flag ishot-flag--placeholder">' . esc_html__( 'Placeholder', 'ishot-booking' ) . '</span>'
			: esc_html__( 'Approved', 'ishot-booking' );
	}

	public static function termFieldAdd(): void {
		wp_nonce_field( self::TERM_NONCE, self::TERM_NONCE );
		echo '<div class="form-field"><label for="ishot-aperture">' . esc_html__( 'Colour', 'ishot-booking' ) . '</label>' . self::apertureSelect( 0 ) . '<p>' . esc_html__( 'Which logo aperture colour marks this category.', 'ishot-booking' ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- apertureSelect() escapes.
	}

	public static function termFieldEdit( \WP_Term $term ): void {
		wp_nonce_field( self::TERM_NONCE, self::TERM_NONCE );
		echo '<tr class="form-field"><th scope="row"><label for="ishot-aperture">' . esc_html__( 'Colour', 'ishot-booking' ) . '</label></th><td>' . self::apertureSelect( ContentTypes::aperture( $term->term_id ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- apertureSelect() escapes.
	}

	private static function apertureSelect( int $current ): string {
		$names = array( __( 'Orange', 'ishot-booking' ), __( 'Green', 'ishot-booking' ), __( 'Teal', 'ishot-booking' ), __( 'Blue', 'ishot-booking' ) );
		$html  = '<select id="ishot-aperture" name="ishot_aperture">';
		foreach ( $names as $i => $name ) {
			$html .= '<option value="' . $i . '"' . selected( $current, $i, false ) . '>' . esc_html( $name ) . '</option>';
		}
		return $html . '</select>';
	}

	public static function saveTerm( int $term_id ): void {
		$nonce = isset( $_POST[ self::TERM_NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TERM_NONCE ] ) ) : '';
		if ( ! isset( $_POST['ishot_aperture'] ) || ! wp_verify_nonce( $nonce, self::TERM_NONCE ) || ! current_user_can( ContentTypes::CAP ) ) {
			return;
		}
		$aperture = absint( $_POST['ishot_aperture'] );
		if ( $aperture < ContentTypes::APERTURES ) {
			update_term_meta( $term_id, 'aperture', $aperture );
		}
	}

	public static function enqueue( string $hook ): void {
		$screen = get_current_screen();
		if ( null === $screen || ContentTypes::PROJECT !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_media();
		$file = 'assets/js/gallery-picker.js';
		wp_enqueue_script( 'ishot-gallery-picker', plugins_url( $file, ISHOT_BOOKING_FILE ), array( 'media-editor' ), (string) filemtime( plugin_dir_path( ISHOT_BOOKING_FILE ) . $file ), true );
	}
}
