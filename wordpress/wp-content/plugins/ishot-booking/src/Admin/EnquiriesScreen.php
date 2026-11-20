<?php
namespace IShot\Booking\Admin;

use IShot\Booking\Contact\Enquiries;

/**
 * Bookings → Enquiries: every contact-form message with its delivery state, so nothing is lost
 * when email fails. Holds personal data, so it needs manage_ishot_bookings (managers, admins).
 */
final class EnquiriesScreen {

	public const CAP = 'manage_ishot_bookings';

	public static function page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			echo '<div class="wrap"><p>' . esc_html__( 'You do not have permission to read enquiries.', 'ishot-booking' ) . '</p></div>';
			return;
		}
		$enquiries = new Enquiries();
		$rows      = $enquiries->recent();
		$labels    = array(
			'pending' => __( 'Sending', 'ishot-booking' ),
			'sending' => __( 'Sending', 'ishot-booking' ),
			'sent'    => __( 'Emailed', 'ishot-booking' ),
			'failed'  => __( 'Email failed: read it here', 'ishot-booking' ),
		);

		echo '<div class="wrap ishot-admin"><h1>' . esc_html__( 'Enquiries', 'ishot-booking' ) . '</h1>';
		$failed = $enquiries->countFailed();
		if ( $failed > 0 ) {
			/* translators: %d: number of enquiries */
			echo '<div class="notice notice-error"><p>' . esc_html( sprintf( _n( '%d enquiry could not be emailed. Read it below and reply directly.', '%d enquiries could not be emailed. Read them below and reply directly.', $failed, 'ishot-booking' ), $failed ) ) . '</p></div>';
		}
		if ( array() === $rows ) {
			echo '<p>' . esc_html__( 'No enquiries yet.', 'ishot-booking' ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Received', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'From', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Enquiry', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Message', 'ishot-booking' ) . '</th><th scope="col">' . esc_html__( 'Delivery', 'ishot-booking' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s<br><a href="mailto:%3$s">%4$s</a>%5$s</td><td>%6$s</td><td>%7$s</td><td>%8$s</td></tr>',
				esc_html( View::when( $row['created_at'] ) ),
				esc_html( $row['name'] ),
				esc_attr( sanitize_email( $row['email'] ) ),
				esc_html( $row['email'] ),
				'' !== $row['phone'] ? '<br>' . esc_html( $row['phone'] ) : '',
				esc_html( $row['enquiry_type'] ),
				nl2br( esc_html( $row['message'] ) ),
				esc_html( $labels[ $row['status'] ] ?? $row['status'] )
			);
		}
		echo '</tbody></table></div>';
	}
}
