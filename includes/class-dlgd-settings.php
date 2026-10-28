<?php
/**
 * Plugin settings.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and cleans the settings, stored in a single option.
 */
final class DLGD_Settings {

	const OPTION = 'dlgd_settings';

	/**
	 * Per-request cache.
	 *
	 * @var array|null
	 */
	private static $settings = null;

	/**
	 * Returns the settings with defaults applied.
	 *
	 * @return array {
	 *     @type int   $new_member_days Length of the new-member period (0 disables it).
	 *     @type array $roles           Role => day, month, new_day, new_month (0 = no limit).
	 *     @type array $burst           enabled, count, minutes, notify, email, block.
	 *     @type array $signup          numeric_usernames, disposable_emails, check_mx.
	 * }
	 */
	public static function get() {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, null );
			self::$settings = is_array( $saved ) ? self::sanitize( $saved ) : self::defaults();
		}
		return self::$settings;
	}

	/**
	 * Default settings, used until the settings screen is saved for the first time.
	 *
	 * Limits are off until configured; bulk-download alerts and sign-up filtering are on,
	 * because they don't get in the way of legitimate members.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'new_member_days' => 30,
			'roles'           => array(),
			'burst'           => array(
				'enabled' => true,
				'count'   => 20,
				'minutes' => 10,
				'notify'  => true,
				'email'   => '',
				'block'   => false,
			),
			'signup'          => array(
				'numeric_usernames' => true,
				'disposable_emails' => true,
				'check_mx'          => true,
			),
		);
	}

	/**
	 * Cleans the settings. Roles without any limit are dropped.
	 *
	 * Unchecked checkboxes are not posted, so a missing key means "off" here; defaults
	 * only apply while nothing has been saved yet.
	 *
	 * @param mixed $input Raw settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$burst  = isset( $input['burst'] ) && is_array( $input['burst'] ) ? $input['burst'] : array();
		$signup = isset( $input['signup'] ) && is_array( $input['signup'] ) ? $input['signup'] : array();

		$clean = array(
			'new_member_days' => isset( $input['new_member_days'] ) ? absint( $input['new_member_days'] ) : 30,
			'roles'           => array(),
			'burst'           => array(
				'enabled' => ! empty( $burst['enabled'] ),
				'count'   => isset( $burst['count'] ) ? max( 1, absint( $burst['count'] ) ) : 20,
				// Capped at one day: bursts are about minutes, and only 30 days of history are read.
				'minutes' => isset( $burst['minutes'] ) ? min( DAY_IN_SECONDS / MINUTE_IN_SECONDS, max( 1, absint( $burst['minutes'] ) ) ) : 10,
				'notify'  => ! empty( $burst['notify'] ),
				'email'   => isset( $burst['email'] ) ? sanitize_email( $burst['email'] ) : '',
				'block'   => ! empty( $burst['block'] ),
			),
			'signup'          => array(
				'numeric_usernames' => ! empty( $signup['numeric_usernames'] ),
				'disposable_emails' => ! empty( $signup['disposable_emails'] ),
				'check_mx'          => ! empty( $signup['check_mx'] ),
			),
		);

		if ( ! empty( $input['roles'] ) && is_array( $input['roles'] ) ) {
			foreach ( $input['roles'] as $role => $rule ) {
				$role = sanitize_key( $role );
				if ( '' === $role || ! is_array( $rule ) ) {
					continue;
				}
				$rule = array(
					'day'       => isset( $rule['day'] ) ? absint( $rule['day'] ) : 0,
					'month'     => isset( $rule['month'] ) ? absint( $rule['month'] ) : 0,
					'new_day'   => isset( $rule['new_day'] ) ? absint( $rule['new_day'] ) : 0,
					'new_month' => isset( $rule['new_month'] ) ? absint( $rule['new_month'] ) : 0,
				);
				if ( array_sum( $rule ) > 0 ) {
					$clean['roles'][ $role ] = $rule;
				}
			}
		}

		return $clean;
	}

	/**
	 * Forgets the cached settings after they are saved.
	 */
	public static function flush() {
		self::$settings = null;
	}
}
