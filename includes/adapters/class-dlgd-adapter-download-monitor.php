<?php
/**
 * Download Monitor support.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download Monitor (5.x).
 *
 * Downloads are read from Download Monitor's log table, whose dates are stored in the
 * site's timezone. Only completed and redirected downloads count.
 */
final class DLGD_Adapter_Download_Monitor extends DLGD_Adapter {

	/**
	 * Download statuses that count as a download.
	 *
	 * @var string[]
	 */
	const COUNTED_STATUSES = array( 'completed', 'redirected' );

	/**
	 * {@inheritDoc}
	 */
	public static function is_available() {
		return function_exists( 'download_monitor' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'download-monitor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'Download Monitor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		// After Download Monitor's own checks, its Members Lock and Shop (30) and Advanced
		// Access Manager (100): the guard only ever narrows access, never grants it.
		add_filter( 'dlm_can_download', array( $this, 'filter_can_download' ), 110 );
		add_filter( 'option_dlm_no_access_error', array( $this, 'filter_no_access_message' ) );
	}

	/**
	 * Denies the download when the guard says so.
	 *
	 * @param bool $can_download Whether the download is allowed so far.
	 * @return bool
	 */
	public function filter_can_download( $can_download ) {
		if ( true !== $can_download || ! is_user_logged_in() ) {
			return $can_download;
		}

		$decision = DLGD_Guard::check_download( get_current_user_id(), $this->get_id() );
		return $decision['allowed'] ? $can_download : false;
	}

	/**
	 * Replaces Download Monitor's "no access" message for users the guard stopped.
	 *
	 * Download Monitor shows that message on its "no access" page or modal, usually on a
	 * later request, so the decision is worked out again without side effects. The default
	 * message asks to log in or subscribe, which is misleading for a member over a limit.
	 *
	 * @param mixed $message Configured message.
	 * @return mixed
	 */
	public function filter_no_access_message( $message ) {
		// Options can be read before the current user is known.
		if ( ! did_action( 'init' ) || ! is_user_logged_in() ) {
			return $message;
		}

		$decision = DLGD_Guard::get_decision( get_current_user_id() );
		return $decision['allowed'] ? $message : esc_html( DLGD_Guard::get_message( $decision ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_download_times( $user_id, $since ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Download Monitor's log table has no API for this query, and limits need live data.
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT download_date FROM %i WHERE user_id = %d AND download_date > %s AND download_status IN ( %s, %s ) ORDER BY download_date ASC',
				self::table(),
				$user_id,
				self::to_datetime( $since, wp_timezone() ),
				self::COUNTED_STATUSES[0],
				self::COUNTED_STATUSES[1]
			)
		);

		return array_values( array_filter( array_map( array( $this, 'local_to_timestamp' ), (array) $dates ) ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_top_downloaders( $since, $limit ) {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See get_download_times().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT user_id, COUNT(*) AS downloads FROM %i WHERE user_id > 0 AND download_date > %s AND download_status IN ( %s, %s ) GROUP BY user_id ORDER BY downloads DESC LIMIT %d',
				self::table(),
				self::to_datetime( $since, wp_timezone() ),
				self::COUNTED_STATUSES[0],
				self::COUNTED_STATUSES[1],
				$limit
			)
		);

		return wp_list_pluck( (array) $rows, 'downloads', 'user_id' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_admin_warnings() {
		if ( ! self::table_exists() ) {
			return array( __( 'Download Monitor\'s download log table is missing, so its downloads are not counted. Deactivating and reactivating Download Monitor usually creates it.', 'download-guard' ) );
		}
		if ( class_exists( 'DLM_Logging' ) && method_exists( 'DLM_Logging', 'is_logging_enabled' ) && ! DLM_Logging::is_logging_enabled() ) {
			return array( __( 'Download Monitor logging is disabled, so its downloads are not counted and no limit applies to them.', 'download-guard' ) );
		}
		return array();
	}

	/**
	 * Whether Download Monitor's log table exists (it can be missing after an incomplete install).
	 *
	 * @return bool
	 */
	private static function table_exists() {
		global $wpdb;
		static $exists = null;

		if ( null === $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checked once per request.
			$exists = self::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( self::table() ) ) );
		}
		return $exists;
	}

	/**
	 * Converts a site-timezone datetime to a timestamp.
	 *
	 * @param string $datetime Y-m-d H:i:s.
	 * @return int|null
	 */
	private function local_to_timestamp( $datetime ) {
		return self::to_timestamp( $datetime, wp_timezone() );
	}

	/**
	 * Download Monitor's log table.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return ! empty( $wpdb->download_log ) ? $wpdb->download_log : $wpdb->prefix . 'download_log';
	}
}
