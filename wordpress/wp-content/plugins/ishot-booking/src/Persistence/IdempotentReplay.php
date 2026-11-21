<?php
namespace IShot\Booking\Persistence;

use RuntimeException;

/** @internal Signals that a concurrent request with the same idempotency key won the race. */
final class IdempotentReplay extends RuntimeException {}
