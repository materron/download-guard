<?php
/**
 * WooCommerce support.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce downloadable products.
 *
 * Downloads are logged with the download permission they used, and dates in UTC. The
 * account is the one that owns the permission (the customer of the order), because
 * download links work without logging in. Guest orders are not limited.
 *
 * WooCommerce has no filter to deny a download, so the guard stops the request on the
 * action fired right before the download is logged and served.
 */
final class DLGD_Adapter_WooCommerce extends DLGD_Adapter {

	/**
	 * {@inheritDoc}
	 */
	public static function is_available() {
		return class_exists( 'WooCommerce' ) && class_exists( 'WC_Download_Handler' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_id() {
		return 'woocommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_label() {
		return 'WooCommerce';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks() {
		// Late, after WooCommerce's own checks (order status, downloads remaining, expiry).
		add_action( 'woocommerce_download_product', array( $this, 'check_download' ), 110, 4 );
	}

	/**
	 * Stops the download when the guard says so.
	 *
	 * @param string $email      Customer email.
	 * @param string $order_key  Order key.
	 * @param int    $product_id Product ID.
	 * @param int    $user_id    Owner of the download permission (0 for guests).
	 */
	public function check_download( $email, $order_key, $product_id, $user_id ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$decision = DLGD_Guard::check_download( $user_id, $this->get_id() );
		if ( ! $decision['allowed'] ) {
			wp_die(
				esc_html( DLGD_Guard::get_message( $decision ) ),
				esc_html__( 'Download not available', 'download-guard' ),
				array( 'response' => 403 )
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_download_times( $user_id, $since ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- WooCommerce's log data store can't filter by the permission owner; limits need live data.
		$dates = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT l.timestamp FROM %i AS l INNER JOIN %i AS p ON p.permission_id = l.permission_id WHERE p.user_id = %d AND l.timestamp > %s ORDER BY l.timestamp ASC',
				$wpdb->prefix . 'wc_download_log',
				$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
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
				'SELECT p.user_id, COUNT(*) AS downloads FROM %i AS l INNER JOIN %i AS p ON p.permission_id = l.permission_id WHERE p.user_id > 0 AND l.timestamp > %s GROUP BY p.user_id ORDER BY downloads DESC LIMIT %d',
				$wpdb->prefix . 'wc_download_log',
				$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
				self::to_datetime( $since, new DateTimeZone( 'UTC' ) ),
				$limit
			)
		);

		return wp_list_pluck( (array) $rows, 'downloads', 'user_id' );
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
}
