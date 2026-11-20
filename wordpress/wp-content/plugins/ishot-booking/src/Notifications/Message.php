<?php
namespace IShot\Booking\Notifications;

/** A rendered email, ready for the Mailer. */
final class Message {

	/**
	 * @param array<int, array{content: string, filename: string, type: string}> $attachments
	 * @param string|null $link_hash Hash of the manage-link token issued for this email (revoked if it is never delivered).
	 */
	public function __construct(
		public readonly string $to,
		public readonly string $subject,
		public readonly string $html,
		public readonly string $text,
		public readonly array $attachments = array(),
		public readonly ?string $link_hash = null
	) {}
}
