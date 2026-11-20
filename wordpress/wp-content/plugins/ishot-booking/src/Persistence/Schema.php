<?php
namespace IShot\Booking\Persistence;

/**
 * Database tables. All tables are InnoDB: booking creation relies on transactions and on the
 * slot-lock primary key to reject double bookings atomically.
 */
final class Schema {

	public const VERSION     = '5';
	public const VERSION_KEY = 'ishot_booking_db_version';

	public static function bookings(): string {
		global $wpdb;
		return $wpdb->prefix . 'ishot_bookings';
	}

	public static function locks(): string {
		global $wpdb;
		return $wpdb->prefix . 'ishot_slot_locks';
	}

	public static function events(): string {
		global $wpdb;
		return $wpdb->prefix . 'ishot_booking_events';
	}

	/** Notification outbox: written in the booking's transaction, delivered by the queue. */
	public static function notifications(): string {
		global $wpdb;
		return $wpdb->prefix . 'ishot_notifications';
	}

	/** Manage-link token hashes issued in emails (the raw tokens are never stored). */
	public static function links(): string {
		global $wpdb;
		return $wpdb->prefix . 'ishot_booking_links';
	}

	/** Contact-form enquiries, kept until delivered to the studio (and purged by retention). */
	public static function enquiries(): string {
		global $wpdb;
		return $wpdb->prefix . 'ishot_enquiries';
	}

	/** @return string[] */
	public static function tables(): array {
		return array( self::bookings(), self::locks(), self::events(), self::notifications(), self::links(), self::enquiries() );
	}

	/** Creates or upgrades the tables (dbDelta is idempotent). */
	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset  = $wpdb->get_charset_collate();
		$bookings = self::bookings();
		$locks    = self::locks();
		$events   = self::events();
		$outbox   = self::notifications();
		$links    = self::links();
		$enquiry  = self::enquiries();

		dbDelta(
			"CREATE TABLE {$bookings} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  resource_id int(10) unsigned NOT NULL DEFAULT 1,
  kind varchar(10) NOT NULL DEFAULT 'booking',
  service_id bigint(20) unsigned NULL,
  status varchar(20) NOT NULL,
  start_utc datetime NOT NULL,
  end_utc datetime NOT NULL,
  block_start_utc datetime NOT NULL,
  block_end_utc datetime NOT NULL,
  client_user_id bigint(20) unsigned NULL,
  client_name varchar(100) NOT NULL DEFAULT '',
  client_email varchar(254) NOT NULL DEFAULT '',
  client_phone varchar(30) NOT NULL DEFAULT '',
  location varchar(120) NOT NULL DEFAULT '',
  notes text NULL,
  deposit_amount decimal(10,2) NULL,
  deposit_status varchar(20) NOT NULL DEFAULT 'none',
  deposit_gateway varchar(20) NULL,
  deposit_reference varchar(100) NOT NULL DEFAULT '',
  hold_expires_at datetime NULL,
  public_token_hash char(64) NULL,
  idempotency_key varchar(64) NULL,
  version int(10) unsigned NOT NULL DEFAULT 1,
  anonymised_at datetime NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY idempotency_key (idempotency_key),
  UNIQUE KEY public_token_hash (public_token_hash),
  KEY resource_block (resource_id,block_start_utc),
  KEY status (status),
  KEY hold_expiry (status,hold_expires_at),
  KEY client_email (client_email(100)),
  KEY retention (anonymised_at,end_utc)
) ENGINE=InnoDB {$charset};
CREATE TABLE {$locks} (
  resource_id int(10) unsigned NOT NULL,
  unit_start_utc datetime NOT NULL,
  booking_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (resource_id,unit_start_utc),
  KEY booking_id (booking_id)
) ENGINE=InnoDB {$charset};
CREATE TABLE {$events} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  booking_id bigint(20) unsigned NOT NULL,
  actor_user_id bigint(20) unsigned NULL,
  action varchar(40) NOT NULL,
  from_status varchar(20) NULL,
  to_status varchar(20) NULL,
  payload longtext NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY booking_id (booking_id)
) ENGINE=InnoDB {$charset};
CREATE TABLE {$outbox} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  booking_id bigint(20) unsigned NOT NULL,
  event_id bigint(20) unsigned NULL,
  template varchar(40) NOT NULL,
  recipient varchar(10) NOT NULL,
  dedupe_key varchar(191) NOT NULL,
  calendar_method varchar(10) NULL,
  snapshot longtext NOT NULL,
  status varchar(12) NOT NULL DEFAULT 'pending',
  attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
  next_attempt_at datetime NOT NULL,
  claimed_at datetime NULL,
  sent_at datetime NULL,
  last_error varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY dedupe_key (dedupe_key),
  KEY due (status,next_attempt_at),
  KEY booking_id (booking_id)
) ENGINE=InnoDB {$charset};
CREATE TABLE {$links} (
  token_hash char(64) NOT NULL,
  booking_id bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (token_hash),
  KEY booking_id (booking_id)
) ENGINE=InnoDB {$charset};
CREATE TABLE {$enquiry} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL,
  email varchar(254) NOT NULL,
  phone varchar(30) NOT NULL DEFAULT '',
  enquiry_type varchar(100) NOT NULL DEFAULT '',
  message text NOT NULL,
  consent_at datetime NOT NULL,
  status varchar(12) NOT NULL DEFAULT 'pending',
  attempts tinyint(3) unsigned NOT NULL DEFAULT 0,
  next_attempt_at datetime NOT NULL,
  claimed_at datetime NULL,
  sent_at datetime NULL,
  last_error varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY due (status,next_attempt_at),
  KEY email (email(100)),
  KEY created_at (created_at)
) ENGINE=InnoDB {$charset};"
		);

		update_option( self::VERSION_KEY, self::VERSION, false );
	}
}
