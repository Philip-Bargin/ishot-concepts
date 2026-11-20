<?php
namespace IShot\Booking\Persistence;

use RuntimeException;

/** The booking changed since the caller read it (optimistic version check). Maps to HTTP 409. */
final class StaleBooking extends RuntimeException {}
