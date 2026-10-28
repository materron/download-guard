<?php
/**
 * Easy Digital Downloads support.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Easy Digital Downloads (3.x).
 *
 * File downloads are logged in their own table with the customer, not the user, and
 * dates in UTC. A customer belongs to a user through the customers table.
 *
 * Download links work without logging in, so the account is the one that owns the
 * order, falling back to the logged-in user. Guest orders are not limited.
 */
final class DLGD_Adapter_EDD extends DLGD_Adapter {

	/**
	 * Decision for the download being processed, when it was denied.
	 *
	 * @var array|null
	 */
	private $denied = null;

	/**
	 * {@inheritDoc}
	 */
	public static function is_available() {
		// EDD 3 introduced orders and the file download log table.
		return function_exists( 'edd_get_order' ) && function_exists( 'edd_add_file_download_log' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'edd';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Easy Digital Downloads';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		// Late, so it runs after other access checks; it only ever narrows access.
		add_filter( 'edd_file_download_has_access', array( $this, 'filter_has_access' ), 110, 2 );
		add_filter( 'edd_deny_download_message', array( $this, 'filter_deny_message' ), 110 );
	}

	/**
	 * Denies the download when the guard says so.
	 *
	 * @param bool $has_access Whether the download is allowed so far.
	 * @param int  $order_id   Order the download link belongs to.
	 * @return bool
	 */
	public function filter_has_access( $has_access, $order_id ) {
		if ( ! $has_access ) {
			return $has_access;
		}

		$user_id = $this->get_order_user_id( $order_id );
		if ( ! $user_id ) {
			return $has_access;
		}

		$decision = DLGD_Guard::check_download( $user_id, $this->get_id() );
		if ( $decision['allowed'] ) {
			return $has_access;
		}

		$this->denied = $decision;
		return false;
	}

	/**
	 * Replaces EDD's "no permission" message when the guard denied the download.
	 *
	 * @param string $message EDD's message.
	 * @return string
	 */
	public function filter_deny_message( $message ) {
		return null === $this->denied ? $message : esc_html( DLGD_Guard::get_message( $this->denied ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_download_times( $user_id, $since ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- EDD's log query API can't filter by user; limits need live data.
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT l.date_created FROM %i AS l INNER JOIN %i AS c ON c.id = l.customer_id WHERE c.user_id = %d AND l.date_created > %s ORDER BY l.date_created ASC',
				self::table( 'edd_logs_file_downloads' ),
				self::table( 'edd_customers' ),
				$user_id,
				self::to_datetime( $since, new DateTimeZone( 'UTC' ) )
			)
		);

		return array_values( array_filter( array_map( array( $this, 'utc_to_timestamp' ), (array) $dates ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_top_downloaders( $since, $limit ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See get_download_times().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT c.user_id, COUNT(*) AS downloads FROM %i AS l INNER JOIN %i AS c ON c.id = l.customer_id WHERE c.user_id > 0 AND l.date_created > %s GROUP BY c.user_id ORDER BY downloads DESC LIMIT %d',
				self::table( 'edd_logs_file_downloads' ),
				self::table( 'edd_customers' ),
				self::to_datetime( $since, new DateTimeZone( 'UTC' ) ),
				$limit
			)
		);

		return wp_list_pluck( (array) $rows, 'downloads', 'user_id' );
	}

	/**
	 * Returns the account behind a download link.
	 *
	 * @param int $order_id Order ID.
	 * @return int User ID, or 0 for a guest.
	 */
	private function get_order_user_id( $order_id ) {
		$order = $order_id ? edd_get_order( $order_id ) : false;
		if ( $order && ! empty( $order->user_id ) ) {
			return (int) $order->user_id;
		}
		return get_current_user_id();
	}

	/**
	 * Converts a UTC datetime to a timestamp.
	 *
	 * @param string $datetime Y-m-d H:i:s.
	 * @return int|null
	 */
	private function utc_to_timestamp( $datetime ) {
		return self::to_timestamp( $datetime, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Name of one of EDD's tables.
	 *
	 * @param string $name Table name without prefix.
	 * @return string
	 */
	private static function table( $name ) {
		global $wpdb;
		return ! empty( $wpdb->$name ) ? $wpdb->$name : $wpdb->prefix . $name;
	}
}
