<?php
namespace IShot\Booking\Rest;

use IShot\Booking\Contact\Enquiries;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * POST /contact: the site's enquiry form (replaces Web3Forms, so no third-party form key is
 * published in the page). Validated by schema, consent required, honeypot, per-address rate
 * limit. The enquiry is stored, then delivered by Contact\Enquiries.
 */
final class ContactController {

	public function register(): void {
		$text = static fn( int $min, int $max, bool $required, string $sanitize = 'sanitize_text_field' ): array => array(
			'type'              => 'string',
			'minLength'         => $min,
			'maxLength'         => $max,
			'required'          => $required,
			'sanitize_callback' => $sanitize,
			'validate_callback' => 'rest_validate_request_arg', // a custom sanitizer otherwise skips schema checks.
		) + ( $required ? array() : array( 'default' => '' ) );

		register_rest_route(
			PublicController::NAMESPACE,
			'/contact',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'name'    => $text( 1, 100, true ),
					'email'   => array(
						'type'      => 'string',
						'format'    => 'email',
						'maxLength' => 254,
						'required'  => true,
					),
					'phone'   => $text( 0, 30, false ),
					'enquiry' => $text( 0, 100, false ),
					'message' => $text( 10, 2000, true, 'sanitize_textarea_field' ),
					'consent' => array(
						'description' => 'The person agreed to the privacy notice.',
						'type'        => 'boolean',
						'enum'        => array( true ),
						'required'    => true,
					),
					// Honeypot: hidden from people, filled in by bots.
					'website' => array(
						'type'      => 'string',
						'maxLength' => 500,
						'default'   => '',
					),
				),
			)
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$limiter = new RateLimiter( 'contact', (int) apply_filters( 'ishot_booking_contact_rate_limit', 5 ), 30 * MINUTE_IN_SECONDS );
		if ( ! $limiter->hit() ) {
			return new WP_Error( 'ishot_rate_limited', __( 'Too many messages. Please wait a little and try again, or email us directly.', 'ishot-booking' ), array( 'status' => 429 ) );
		}

		$accepted = new WP_REST_Response( array( 'status' => 'received' ), 202 );
		if ( '' !== trim( (string) $request['website'] ) ) {
			return $accepted; // looks accepted to the bot; nothing is stored or sent
		}

		( new Enquiries() )->store(
			array(
				'name'         => (string) $request['name'],
				'email'        => sanitize_email( (string) $request['email'] ),
				'phone'        => (string) $request['phone'],
				'enquiry_type' => (string) $request['enquiry'],
				'message'      => (string) $request['message'],
			)
		);
		return $accepted;
	}
}
