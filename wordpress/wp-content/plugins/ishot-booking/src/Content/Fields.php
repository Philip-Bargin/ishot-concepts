<?php
namespace IShot\Booking\Content;

use IShot\Booking\Catalog\ServiceCatalog;

/**
 * The one definition of every content field: registered meta (REST schema validation), the wp-admin
 * details box and the importer all read from here, so a rule can't differ between them.
 */
final class Fields {

	/** Imported prototype copy that the owner has not approved yet. */
	public const PLACEHOLDER = 'placeholder';
	public const APPROVED    = 'approved';

	/** Lucide icon names the theme ships (service tiles). */
	public const ICONS = array( 'camera', 'heart', 'briefcase', 'sparkles', 'video', 'sun', 'image', 'users', 'film', 'star' );

	/**
	 * @return array<string, array{type: string, default: mixed, label: string, input: string, schema?: array<string, mixed>, help?: string}>
	 */
	public static function for( string $post_type ): array {
		$status = array(
			'content_status' => array(
				'type'    => 'string',
				'default' => self::APPROVED,
				'label'   => __( 'Content status', 'ishot-booking' ),
				'input'   => 'select',
				'schema'  => array( 'enum' => array( self::APPROVED, self::PLACEHOLDER ) ),
				'help'    => __( 'Placeholder marks prototype copy that still needs your approval. Choose Approved once the text and images are final.', 'ishot-booking' ),
			),
		);

		return match ( $post_type ) {
			ServiceCatalog::POST_TYPE => array(
				'short_title'       => self::text( __( 'Short title', 'ishot-booking' ), 60, __( 'Used on service tiles and menus.', 'ishot-booking' ) ),
				'tag'               => self::text( __( 'Tag', 'ishot-booking' ), 60, __( 'Small label above the title, for example "Portraits".', 'ishot-booking' ) ),
				'icon'              => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Icon', 'ishot-booking' ),
					'input'   => 'select',
					'schema'  => array( 'enum' => array( '', ...self::ICONS ) ),
				),
				'inclusions'        => array(
					'type'    => 'array',
					'default' => array(),
					'label'   => __( 'What’s included', 'ishot-booking' ),
					'input'   => 'lines',
					'schema'  => array(
						'items'    => array(
							'type'      => 'string',
							'maxLength' => 200,
						),
						'maxItems' => 12,
					),
					'help'    => __( 'One item per line.', 'ishot-booking' ),
				),
				'duration_min'      => self::integer( __( 'Session length (minutes)', 'ishot-booking' ), 60, 1, 1440 ),
				'buffer_before_min' => self::integer( __( 'Buffer before (minutes)', 'ishot-booking' ), 0, 0, 480, __( 'Preparation or travel time kept free before the session.', 'ishot-booking' ) ),
				'buffer_after_min'  => self::integer( __( 'Buffer after (minutes)', 'ishot-booking' ), 0, 0, 480 ),
				'price_from'        => self::money( __( 'From price', 'ishot-booking' ) ),
				'deposit_amount'    => self::money( __( 'Deposit', 'ishot-booking' ) ),
				'bookable'          => self::boolean( __( 'Clients can book this online', 'ishot-booking' ) ),
			) + $status,
			ContentTypes::PROJECT     => array(
				'client'     => self::text( __( 'Client', 'ishot-booking' ), 100, __( 'Leave empty unless the client agreed to be named.', 'ishot-booking' ) ),
				'shoot_date' => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Shoot date', 'ishot-booking' ),
					'input'   => 'date',
					'schema'  => array( 'pattern' => '^(\d{4}-\d{2}-\d{2})?$' ),
				),
				'location'   => self::text( __( 'Location', 'ishot-booking' ), 120 ),
				'gallery'    => array(
					'type'    => 'array',
					'default' => array(),
					'label'   => __( 'Gallery', 'ishot-booking' ),
					'input'   => 'gallery',
					'schema'  => array(
						'items'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'maxItems' => 60,
					),
					'help'    => __( 'Images shown on the project page, in order. The featured image is the cover.', 'ishot-booking' ),
				),
			) + $status,
			ContentTypes::TESTIMONIAL => array(
				'quote'        => array(
					'type'    => 'string',
					'default' => '',
					'label'   => __( 'Quote', 'ishot-booking' ),
					'input'   => 'textarea',
					'schema'  => array( 'maxLength' => 600 ),
					'help'    => __( 'The client’s own words, 15–40 words.', 'ishot-booking' ),
				),
				'client_name'  => self::text( __( 'Client name or initials', 'ishot-booking' ), 100 ),
				'session_type' => self::text( __( 'Session type', 'ishot-booking' ), 100 ),
				'rating'       => self::integer( __( 'Rating (1–5, 0 for none)', 'ishot-booking' ), 0, 0, 5 ),
				'consent'      => self::boolean( __( 'The client agreed to publication', 'ishot-booking' ), __( 'Required: a testimonial without consent stays a draft.', 'ishot-booking' ) ),
			) + $status,
			default                   => array(),
		};
	}

	/** JSON schema for one field, as register_post_meta and rest_validate_value_from_schema expect it. */
	public static function schema( array $field ): array {
		return array( 'type' => $field['type'] ) + ( $field['schema'] ?? array() );
	}

	/**
	 * Converts a value typed into the admin form into the stored type, then validates it.
	 *
	 * @return mixed|\WP_Error
	 */
	public static function fromInput( array $field, mixed $raw ): mixed {
		$value = match ( $field['input'] ) {
			'checkbox' => ! empty( $raw ),
			'lines'    => self::lines( (string) $raw ),
			'gallery'  => self::galleryIds( (string) $raw ),
			'textarea' => sanitize_textarea_field( (string) $raw ),
			default    => match ( $field['type'] ) {
				'integer' => is_numeric( $raw ) && trim( (string) $raw ) === (string) (int) $raw ? (int) $raw : $raw,
				'number'  => is_numeric( $raw ) ? (float) $raw : $raw,
				default   => sanitize_text_field( (string) $raw ),
			},
		};
		if ( 'array' === $field['type'] && 'string' === ( $field['schema']['items']['type'] ?? '' ) ) {
			$value = array_map( 'sanitize_text_field', $value );
		}
		$valid = rest_validate_value_from_schema( $value, self::schema( $field ), $field['label'] );
		return is_wp_error( $valid ) ? $valid : $value;
	}

	/** @return string[] Non-empty, trimmed lines of a textarea. */
	private static function lines( string $raw ): array {
		$lines = preg_split( '/\R/', $raw );
		return array_values( array_filter( array_map( 'trim', is_array( $lines ) ? $lines : array() ), static fn( string $line ): bool => '' !== $line ) );
	}

	/** @return int[] Unique ids of existing images, in the order given. */
	private static function galleryIds( string $raw ): array {
		$ids = array_unique( array_filter( array_map( 'absint', explode( ',', $raw ) ) ) );
		return array_values( array_filter( $ids, static fn( int $id ) => 'attachment' === get_post_type( $id ) && str_starts_with( (string) get_post_mime_type( $id ), 'image/' ) ) );
	}

	private static function text( string $label, int $max, string $help = '' ): array {
		return array(
			'type'    => 'string',
			'default' => '',
			'label'   => $label,
			'input'   => 'text',
			'schema'  => array( 'maxLength' => $max ),
		) + ( '' !== $help ? array( 'help' => $help ) : array() );
	}

	private static function integer( string $label, int $fallback, int $min, int $max, string $help = '' ): array {
		return array(
			'type'    => 'integer',
			'default' => $fallback,
			'label'   => $label,
			'input'   => 'number',
			'schema'  => array(
				'minimum' => $min,
				'maximum' => $max,
			),
		) + ( '' !== $help ? array( 'help' => $help ) : array() );
	}

	private static function money( string $label ): array {
		return array(
			'type'    => 'number',
			'default' => 0,
			'label'   => $label,
			'input'   => 'number',
			'schema'  => array(
				'minimum' => 0,
				'maximum' => 1000000,
			),
		);
	}

	private static function boolean( string $label, string $help = '' ): array {
		return array(
			'type'    => 'boolean',
			'default' => false,
			'label'   => $label,
			'input'   => 'checkbox',
		) + ( '' !== $help ? array( 'help' => $help ) : array() );
	}
}
