<?php
namespace IShot\Booking\Admin;

use DateTimeImmutable;
use DateTimeZone;

/** Small rendering helpers shared by the admin screens. All output is escaped by the caller's context. */
final class View {

	public static function statusLabel( string $status ): string {
		$labels = array(
			'requested'       => __( 'Requested', 'ishot-booking' ),
			'pending_deposit' => __( 'Awaiting deposit', 'ishot-booking' ),
			'confirmed'       => __( 'Confirmed', 'ishot-booking' ),
			'completed'       => __( 'Completed', 'ishot-booking' ),
			'cancelled'       => __( 'Cancelled', 'ishot-booking' ),
			'expired'         => __( 'Expired', 'ishot-booking' ),
			'no_show'         => __( 'No-show', 'ishot-booking' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/** Status badge; the class maps to design-system status colours in admin.css. */
	public static function badge( string $status ): string {
		return sprintf( '<span class="ishot-badge ishot-badge--%1$s">%2$s</span>', esc_attr( $status ), esc_html( self::statusLabel( $status ) ) );
	}

	/** UTC 'Y-m-d H:i:s' → site date and time format, in studio time. */
	public static function when( ?string $utc, string $format = '' ): string {
		if ( null === $utc || '' === $utc ) {
			return '—';
		}
		$format = '' !== $format ? $format : get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		return (string) wp_date( $format, ( new DateTimeImmutable( $utc, new DateTimeZone( 'UTC' ) ) )->getTimestamp() );
	}

	public static function detailUrl( int $id ): string {
		return add_query_arg(
			array(
				'page'    => Menu::LIST_SLUG,
				'booking' => $id,
			),
			admin_url( 'admin.php' )
		);
	}

	public static function serviceTitle( ?string $service_id ): string {
		if ( null === $service_id ) {
			return '—';
		}
		$post = get_post( (int) $service_id );
		return null === $post ? '—' : get_the_title( $post );
	}

	/** Opening tag of an admin-post form with its action and nonce. */
	public static function formOpen( string $post_action, string $nonce_action, string $css_class = '' ): string {
		return sprintf(
			'<form method="post" action="%1$s" class="%2$s"><input type="hidden" name="action" value="%3$s">%4$s',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $css_class ),
			esc_attr( $post_action ),
			wp_nonce_field( $nonce_action, '_wpnonce', true, false )
		);
	}
}
