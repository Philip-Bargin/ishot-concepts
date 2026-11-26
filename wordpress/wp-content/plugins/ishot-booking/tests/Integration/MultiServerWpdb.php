<?php
namespace IShot\Booking\Tests\Integration;

use wpdb;

/**
 * Stands in for HyperDB / LudicrousDB, which keep one handle per server in $dbhs and point $dbh
 * at whichever server a query is routed to. Only its shape matters (never connected).
 */
final class MultiServerWpdb extends wpdb {

	/** @var array<string, mixed> */
	public array $dbhs = array();
}
