<?php
/**
 * Base class for download platforms.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A download platform (Download Monitor, Easy Digital Downloads…).
 *
 * The guard doesn't know how each platform stores or serves downloads: an adapter reads
 * the platform's own download log, hooks into its access check to deny a download and
 * shows the guard's message instead of the platform's.
 *
 * All times are Unix timestamps, whatever timezone the platform stores them in.
 */
abstract class DLGD_Adapter {

	/**
	 * Whether the platform is installed and supported.
	 *
	 * @return bool
	 */
	abstract public static function is_available();

	/**
	 * Unique ID, e.g. 'download-monitor'.
	 *
	 * @return string
	 */
	abstract public function get_id();

	/**
	 * Platform name shown in the admin.
	 *
	 * @return string
	 */
	abstract public function get_label();

	/**
	 * Hooks into the platform's download process.
	 */
	abstract public function register_hooks();

	/**
	 * Returns when the user downloaded something since a given time, oldest first.
	 *
	 * @param int $user_id User ID.
	 * @param int $since   Timestamp.
	 * @return int[] Timestamps.
	 */
	abstract public function get_download_times( $user_id, $since );

	/**
	 * Returns the users with the most downloads since a given time.
	 *
	 * @param int $since Timestamp.
	 * @param int $limit Maximum number of users.
	 * @return int[] User ID => number of downloads, highest first.
	 */
	abstract public function get_top_downloaders( $since, $limit );

	/**
	 * Problems the site owner should know about (e.g. the download log is disabled).
	 *
	 * @return string[]
	 */
	public function get_admin_warnings() {
		return array();
	}

	/**
	 * Converts a MySQL datetime to a timestamp.
	 *
	 * @param string       $datetime Y-m-d H:i:s.
	 * @param DateTimeZone $timezone Timezone it was stored in.
	 * @return int|null
	 */
	protected static function to_timestamp( $datetime, DateTimeZone $timezone ) {
		$date = date_create_immutable( (string) $datetime, $timezone );
		return $date ? $date->getTimestamp() : null;
	}

	/**
	 * Converts a timestamp to a MySQL datetime in the given timezone.
	 *
	 * @param int          $timestamp Timestamp.
	 * @param DateTimeZone $timezone  Timezone the platform stores dates in.
	 * @return string
	 */
	protected static function to_datetime( $timestamp, DateTimeZone $timezone ) {
		return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( $timezone )->format( 'Y-m-d H:i:s' );
	}
}
