<?php
namespace IShot\Booking\Persistence;

use RuntimeException;

/** Another live booking already holds part of the requested time. Maps to HTTP 409. */
final class SlotConflict extends RuntimeException {}
