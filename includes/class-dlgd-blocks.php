<?php
/**
 * Blocked accounts.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accounts blocked from downloading until an administrator reviews them.
 *
 * Stored as user meta, so a block survives across platforms and is removed with the user.
 */
final class DLGD_Blocks {

	const META_KEY = 'dlgd_blocked';

	/**
	 * When the user was last unblocked, so the downloads that caused the block don't block
	 * them again right away.
	 */
	const UNBLOCKED_META_KEY = 'dlgd_unblocked';

	/**
	 * Blocks a user from downloading.
	 *
	 * @param int   $user_id User ID.
	 * @param array $details Why: 'reason' ('burst'|'manual') and, for bursts, 'count' and 'minutes'.
	 */
	public static function block( $user_id, array $details ) {
		$details['time'] = time();
		update_user_meta( $user_id, self::META_KEY, $details );

		/**
		 * Fires when an account is blocked from downloading.
		 *
		 * @param int   $user_id User ID.
		 * @param array $details Why it was blocked.
		 */
		do_action( 'dlgd_account_blocked', $user_id, $details );
	}

	/**
	 * Lets a user download again.
	 *
	 * @param int $user_id User ID.
	 */
	public static function unblock( $user_id ) {
		delete_user_meta( $user_id, self::META_KEY );
		update_user_meta( $user_id, self::UNBLOCKED_META_KEY, time() );

		/**
		 * Fires when an account is unblocked.
		 *
		 * @param int $user_id User ID.
		 */
		do_action( 'dlgd_account_unblocked', $user_id );
	}

	/**
	 * Returns why a user is blocked.
	 *
	 * @param int $user_id User ID.
	 * @return array|null Block details, or null when the user isn't blocked.
	 */
	public static function get( $user_id ) {
		$details = get_user_meta( $user_id, self::META_KEY, true );
		return is_array( $details ) ? $details : null;
	}

	/**
	 * Returns when a user was last unblocked.
	 *
	 * @param int $user_id User ID.
	 * @return int Timestamp, or 0 if never.
	 */
	public static function get_unblocked_time( $user_id ) {
		return (int) get_user_meta( $user_id, self::UNBLOCKED_META_KEY, true );
	}

	/**
	 * Returns all blocked users.
	 *
	 * @return WP_User[]
	 */
	public static function get_blocked_users() {
		return get_users(
			array(
				'meta_key' => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Few users are ever blocked, and only on an admin screen.
				'orderby'  => 'login',
			)
		);
	}
}
