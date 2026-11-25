<?php
namespace IShot\Booking\Persistence;

use RuntimeException;

/**
 * Every attempt of a transaction lost an InnoDB deadlock (or lock wait) and the requested time
 * is still free, so nothing was saved and the caller may simply try again. Maps to HTTP 503.
 * (When the time has been taken meanwhile, BookingRepository throws SlotConflict instead.)
 */
final class CalendarBusy extends RuntimeException {}
