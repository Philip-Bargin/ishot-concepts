<?php
namespace IShot\Booking\Notifications;

use IShot\Booking\Settings;

/**
 * Email wording. The studio can switch a template off and rewrite its subject and opening
 * paragraph (Booking settings → Emails); the booking details, link and footer stay fixed so
 * an edit can never drop the information a client needs.
 *
 * Placeholders: {client_name} {service} {date} {time} {previous_date} {previous_time}
 * {deposit_amount} {hold_expires} {studio_name}
 */
final class Templates {

	/** Where the main button points: the client's manage link, the wp-admin booking, or nowhere. */
	public const CTA_MANAGE = 'manage';
	public const CTA_ADMIN  = 'admin';
	public const CTA_NONE   = 'none';

	public const PLACEHOLDERS = array( '{client_name}', '{service}', '{date}', '{time}', '{previous_date}', '{previous_time}', '{deposit_amount}', '{hold_expires}', '{studio_name}' );

	/** @return array<string, array{label: string, subject: string, heading: string, intro: string, cta: string}> */
	public static function defaults(): array {
		return array(
			'request_received'    => array(
				'label'   => __( 'Request received (client)', 'ishot-booking' ),
				'subject' => __( 'We received your booking request', 'ishot-booking' ),
				'heading' => __( 'Request received', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, thank you for your {service} request for {date} at {time}. We will review it and come back to you shortly. We are holding this time for you until {hold_expires}.', 'ishot-booking' ),
				'cta'     => self::CTA_MANAGE,
			),
			'new_request'         => array(
				'label'   => __( 'New request (studio)', 'ishot-booking' ),
				'subject' => __( 'New booking request: {client_name}, {date}', 'ishot-booking' ),
				'heading' => __( 'New booking request', 'ishot-booking' ),
				'intro'   => __( '{client_name} asked for {service} on {date} at {time}. Confirm it or request a deposit before {hold_expires}, or the hold lapses and the time is released.', 'ishot-booking' ),
				'cta'     => self::CTA_ADMIN,
			),
			'deposit_requested'   => array(
				'label'   => __( 'Deposit instructions (client)', 'ishot-booking' ),
				'subject' => __( 'Your deposit instructions for {date}', 'ishot-booking' ),
				'heading' => __( 'A deposit confirms your session', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, to confirm your {service} on {date} at {time}, please pay a deposit of {deposit_amount}. We are holding this time for you until {hold_expires}. We will email you again once we have recorded your payment.', 'ishot-booking' ),
				'cta'     => self::CTA_MANAGE,
			),
			'deposit_reminder'    => array(
				'label'   => __( 'Deposit reminder (client)', 'ishot-booking' ),
				'subject' => __( 'Reminder: your deposit for {date}', 'ishot-booking' ),
				'heading' => __( 'Your deposit is still due', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, we have not yet recorded your deposit of {deposit_amount} for {service} on {date} at {time}. We are holding this time until {hold_expires}; after that it is released. If you have already paid, thank you, and there is no need to do anything.', 'ishot-booking' ),
				'cta'     => self::CTA_MANAGE,
			),
			'hold_expiring'       => array(
				'label'   => __( 'Request about to lapse (studio)', 'ishot-booking' ),
				'subject' => __( 'Request from {client_name} lapses soon', 'ishot-booking' ),
				'heading' => __( 'A request is about to lapse', 'ishot-booking' ),
				'intro'   => __( '{client_name}\'s {service} request for {date} at {time} will lapse at {hold_expires} unless you confirm it or request a deposit.', 'ishot-booking' ),
				'cta'     => self::CTA_ADMIN,
			),
			'confirmed'           => array(
				'label'   => __( 'Booking confirmed (client)', 'ishot-booking' ),
				'subject' => __( 'Your session is confirmed: {date}', 'ishot-booking' ),
				'heading' => __( 'Your session is confirmed', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, your {service} is confirmed for {date} at {time}. A calendar invitation is attached.', 'ishot-booking' ),
				'cta'     => self::CTA_MANAGE,
			),
			'rescheduled'         => array(
				'label'   => __( 'Booking moved (client)', 'ishot-booking' ),
				'subject' => __( 'Your session has moved to {date}', 'ishot-booking' ),
				'heading' => __( 'Your session has moved', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, your {service} has moved from {previous_date} at {previous_time} to {date} at {time}.', 'ishot-booking' ),
				'cta'     => self::CTA_MANAGE,
			),
			'client_rescheduled'  => array(
				'label'   => __( 'Client moved a booking (studio)', 'ishot-booking' ),
				'subject' => __( '{client_name} moved their session to {date}', 'ishot-booking' ),
				'heading' => __( 'A client moved their session', 'ishot-booking' ),
				'intro'   => __( '{client_name} moved their {service} from {previous_date} at {previous_time} to {date} at {time}.', 'ishot-booking' ),
				'cta'     => self::CTA_ADMIN,
			),
			'cancelled'           => array(
				'label'   => __( 'Booking cancelled (client)', 'ishot-booking' ),
				'subject' => __( 'Your booking for {date} is cancelled', 'ishot-booking' ),
				'heading' => __( 'Your booking is cancelled', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, your {service} on {date} at {time} has been cancelled. If you did not expect this, reply to this email and we will help.', 'ishot-booking' ),
				'cta'     => self::CTA_NONE,
			),
			'client_cancelled'    => array(
				'label'   => __( 'Client cancelled (studio)', 'ishot-booking' ),
				'subject' => __( '{client_name} cancelled their session on {date}', 'ishot-booking' ),
				'heading' => __( 'A client cancelled', 'ishot-booking' ),
				'intro'   => __( '{client_name} cancelled their {service} on {date} at {time}. The time is free again.', 'ishot-booking' ),
				'cta'     => self::CTA_ADMIN,
			),
			'hold_expired'        => array(
				'label'   => __( 'Request lapsed (client)', 'ishot-booking' ),
				'subject' => __( 'Your booking request for {date} has lapsed', 'ishot-booking' ),
				'heading' => __( 'Your request has lapsed', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, your request for {service} on {date} at {time} was not confirmed in time, so the time has been released. You are welcome to book again.', 'ishot-booking' ),
				'cta'     => self::CTA_NONE,
			),
			'hold_expired_studio' => array(
				'label'   => __( 'Request lapsed (studio)', 'ishot-booking' ),
				'subject' => __( 'Request from {client_name} lapsed', 'ishot-booking' ),
				'heading' => __( 'A request lapsed', 'ishot-booking' ),
				'intro'   => __( '{client_name}\'s {service} request for {date} at {time} lapsed and the time was released.', 'ishot-booking' ),
				'cta'     => self::CTA_ADMIN,
			),
			'reminder'            => array(
				'label'   => __( 'Session reminder, 24 hours before (client)', 'ishot-booking' ),
				'subject' => __( 'See you soon: your session on {date}', 'ishot-booking' ),
				'heading' => __( 'See you soon', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, this is a reminder of your {service} on {date} at {time}.', 'ishot-booking' ),
				'cta'     => self::CTA_MANAGE,
			),
			'thank_you'           => array(
				'label'   => __( 'Thank you, after the session (client)', 'ishot-booking' ),
				'subject' => __( 'Thank you from {studio_name}', 'ishot-booking' ),
				'heading' => __( 'Thank you', 'ishot-booking' ),
				'intro'   => __( 'Hi {client_name}, thank you for your {service} with us. We will be in touch about your photographs.', 'ishot-booking' ),
				'cta'     => self::CTA_NONE,
			),
		);
	}

	/** @return array{label: string, subject: string, heading: string, intro: string, cta: string, enabled: bool} */
	public static function get( string $template ): array {
		$default  = self::defaults()[ $template ];
		$override = Settings::all()['templates'][ $template ] ?? array();
		$text     = static fn( string $key ) => isset( $override[ $key ] ) && '' !== trim( (string) $override[ $key ] ) ? (string) $override[ $key ] : $default[ $key ];
		return array(
			'subject' => $text( 'subject' ),
			'intro'   => $text( 'intro' ),
			'enabled' => (bool) ( $override['enabled'] ?? true ),
		) + $default;
	}
}
