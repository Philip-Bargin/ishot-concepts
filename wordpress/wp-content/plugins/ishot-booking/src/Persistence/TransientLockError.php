<?php
namespace IShot\Booking\Persistence;

use RuntimeException;

/**
 * InnoDB rolled the transaction back as a deadlock victim, or a lock wait timed out.
 * The whole transaction is safe to run again; BookingRepository retries it internally.
 * (DbError::isTransient() tells whether a failed statement was one.)
 */
final class TransientLockError extends RuntimeException {}
