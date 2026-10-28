<?php
/**
 * Platform-independent rules.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Decides whether a user may download, whatever the platform.
 *
 * Downloads are counted across all active platforms. Both limit windows are rolling (last
 * 24 hours and last 30 days), so nobody gets a fresh quota by waiting for midnight or the
 * first of the month.
 */
final class DLGD_Guard {

	/**
	 * Limit windows, in seconds.
	 *
	 * @var int[]
	 */
	const WINDOWS = array(
		'day'   => DAY_IN_SECONDS,
		'month' => 30 * DAY_IN_SECONDS,
	);

	/**
	 * Active platforms, keyed by ID.
	 *
	 * @var DLGD_Adapter[]
	 */
	private static $adapters = array();

	/**
	 * Per-request cache of download times, keyed by user ID.
	 *
	 * @var array
	 */
	private static $times = array();

	/**
	 * Detects the installed platforms and hooks into them.
	 */
	public static function init() {
		$classes = array( 'DLGD_Adapter_Download_Monitor', 'DLGD_Adapter_EDD', 'DLGD_Adapter_WooCommerce' );

		/**
		 * Filters the adapter classes. Each must extend DLGD_Adapter.
		 *
		 * @param string[] $classes Class names.
		 */
		$classes = (array) apply_filters( 'dlgd_adapter_classes', $classes );

		foreach ( $classes as $class ) {
			if ( ! is_string( $class ) || ! is_subclass_of( $class, 'DLGD_Adapter' ) || ! $class::is_available() ) {
				continue;
			}
			$adapter = new $class();
			$adapter->register_hooks();
			self::$adapters[ $adapter->get_id() ] = $adapter;
		}
	}

	/**
	 * Active platforms.
	 *
	 * @return DLGD_Adapter[]
	 */
	public static function get_adapters() {
		return self::$adapters;
	}

	/**
	 * Decides on a download that is about to happen.
	 *
	 * Unlike get_decision(), this also detects bulk downloading: it alerts the
	 * administrator and, when configured, blocks the account.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $platform Adapter ID.
	 * @return array See get_decision().
	 */
	public static function check_download( $user_id, $platform ) {
		$user_id = (int) $user_id;
		if ( self::is_exempt( $user_id ) ) {
			return array( 'allowed' => true );
		}

		if ( null === DLGD_Blocks::get( $user_id ) ) {
			self::detect_burst( $user_id );
		}

		$decision = self::get_decision( $user_id );

		if ( ! $decision['allowed'] ) {
			/**
			 * Fires when a download is denied.
			 *
			 * @param int    $user_id  User ID.
			 * @param array  $decision Why.
			 * @param string $platform Adapter ID.
			 */
			do_action( 'dlgd_download_denied', $user_id, $decision, $platform );
		}

		return $decision;
	}

	/**
	 * Decides whether a user may download now, without side effects.
	 *
	 * @param int $user_id User ID.
	 * @return array {
	 *     @type bool   $allowed Whether the user may download.
	 *     @type string $reason  When denied: 'blocked', 'day' or 'month'.
	 *     @type int    $limit   For 'day' and 'month': the limit reached.
	 *     @type int    $until   For 'day' and 'month': when a download slot frees up.
	 * }
	 */
	public static function get_decision( $user_id ) {
		$user_id = (int) $user_id;
		if ( self::is_exempt( $user_id ) ) {
			return array( 'allowed' => true );
		}

		if ( null !== DLGD_Blocks::get( $user_id ) ) {
			return array(
				'allowed' => false,
				'reason'  => 'blocked',
			);
		}

		$user   = get_userdata( $user_id );
		$limits = $user ? self::get_user_limits( $user ) : null;
		if ( null === $limits ) {
			return array( 'allowed' => true );
		}

		$now      = time();
		$decision = array( 'allowed' => true );

		foreach ( self::WINDOWS as $window => $length ) {
			$limit = isset( $limits[ $window ] ) ? (int) $limits[ $window ] : 0;
			if ( $limit < 1 ) {
				continue;
			}

			$times = self::get_download_times( $user_id, $now - $length );
			$count = count( $times );
			if ( $count < $limit ) {
				continue;
			}

			// A slot frees up when the download that put the user at the limit leaves the window.
			$until = $times[ $count - $limit ] + $length;

			// When both windows are exceeded, the later date is the real wait.
			if ( $decision['allowed'] || $until > $decision['until'] ) {
				$decision = array(
					'allowed' => false,
					'reason'  => $window,
					'limit'   => $limit,
					'until'   => $until,
				);
			}
		}

		return $decision;
	}

	/**
	 * Message for a denied download.
	 *
	 * @param array $decision See get_decision().
	 * @return string Plain text.
	 */
	public static function get_message( array $decision ) {
		$reason = isset( $decision['reason'] ) ? $decision['reason'] : '';

		if ( 'day' === $reason || 'month' === $reason ) {
			$date = wp_date( get_option( 'date_format' ), $decision['until'] );
			$time = wp_date( get_option( 'time_format' ), $decision['until'] );

			if ( 'day' === $reason ) {
				$text = sprintf(
					/* translators: 1: number of downloads, 2: date, 3: time. */
					_n(
						'You have reached the limit of %1$d download per day. You can download again from %2$s at %3$s.',
						'You have reached the limit of %1$d downloads per day. You can download again from %2$s at %3$s.',
						$decision['limit'],
						'download-guard'
					),
					$decision['limit'],
					$date,
					$time
				);
			} else {
				$text = sprintf(
					/* translators: 1: number of downloads, 2: date, 3: time. */
					_n(
						'You have reached the limit of %1$d download in 30 days. You can download again from %2$s at %3$s.',
						'You have reached the limit of %1$d downloads in 30 days. You can download again from %2$s at %3$s.',
						$decision['limit'],
						'download-guard'
					),
					$decision['limit'],
					$date,
					$time
				);
			}
		} else {
			$text = __( 'Downloads are paused on your account because of unusual activity. Please contact us to restore them.', 'download-guard' );
		}

		/**
		 * Filters the message shown when a download is denied.
		 *
		 * @param string $text     Plain-text message.
		 * @param array  $decision Why the download was denied.
		 */
		return (string) apply_filters( 'dlgd_denied_message', $text, $decision );
	}

	/**
	 * Returns the limits that apply to a user.
	 *
	 * When the user has several limited roles, the most generous value wins for each
	 * window. Roles without any limit configured are ignored.
	 *
	 * @param WP_User $user User.
	 * @return array|null 'day' and 'month' (0 = no limit), or null when the user isn't limited.
	 */
	public static function get_user_limits( WP_User $user ) {
		$settings = DLGD_Settings::get();
		$is_new   = self::is_new_member( $user, $settings['new_member_days'] );
		$result   = null;

		foreach ( (array) $user->roles as $role ) {
			if ( empty( $settings['roles'][ $role ] ) ) {
				continue;
			}
			$rule   = $settings['roles'][ $role ];
			$limits = array(
				'day'   => ( $is_new && $rule['new_day'] > 0 ) ? $rule['new_day'] : $rule['day'],
				'month' => ( $is_new && $rule['new_month'] > 0 ) ? $rule['new_month'] : $rule['month'],
			);

			if ( null === $result ) {
				$result = $limits;
				continue;
			}
			foreach ( $limits as $window => $value ) {
				$result[ $window ] = ( 0 === $result[ $window ] || 0 === $value ) ? 0 : max( $result[ $window ], $value );
			}
		}

		/**
		 * Filters the limits for a user.
		 *
		 * @param array|null $result 'day' and 'month' (0 = no limit), or null for no limits.
		 * @param WP_User    $user   User.
		 * @param bool       $is_new Whether the user is in the new-member period.
		 */
		return apply_filters( 'dlgd_user_limits', $result, $user, $is_new );
	}

	/**
	 * Returns when the user became a member.
	 *
	 * Defaults to the registration date. With Restrict Content Pro, uses the oldest
	 * membership that was ever activated, so an abandoned checkout doesn't count and a
	 * returning member isn't new again.
	 *
	 * @param WP_User $user User.
	 * @return int Timestamp.
	 */
	public static function get_member_since( WP_User $user ) {
		$since = strtotime( $user->user_registered . ' UTC' );

		if ( function_exists( 'rcp_get_customer_by_user_id' ) && function_exists( 'rcp_get_memberships' ) ) {
			$customer = rcp_get_customer_by_user_id( $user->ID );
			if ( $customer ) {
				$first = rcp_get_memberships(
					array(
						'customer_id' => $customer->get_id(),
						'status__in'  => array( 'active', 'cancelled', 'expired' ),
						'orderby'     => 'created_date',
						'order'       => 'ASC',
						'number'      => 1,
					)
				);
				if ( ! empty( $first ) ) {
					// Restrict Content Pro stores dates in the site's timezone.
					$created = date_create_immutable( $first[0]->get_created_date( false ), wp_timezone() );
					if ( $created ) {
						$since = $created->getTimestamp();
					}
				}
			}
		}

		/**
		 * Filters when a user became a member, for membership plugins other than Restrict
		 * Content Pro.
		 *
		 * @param int     $since Timestamp.
		 * @param WP_User $user  User.
		 */
		return (int) apply_filters( 'dlgd_member_since', $since, $user );
	}

	/**
	 * Returns the users with the most downloads, across platforms.
	 *
	 * @param int $since Timestamp.
	 * @param int $limit Maximum number of users.
	 * @return int[] User ID => downloads, highest first.
	 */
	public static function get_top_downloaders( $since, $limit ) {
		$totals = array();
		foreach ( self::$adapters as $adapter ) {
			foreach ( $adapter->get_top_downloaders( $since, $limit ) as $user_id => $count ) {
				$totals[ $user_id ] = ( isset( $totals[ $user_id ] ) ? $totals[ $user_id ] : 0 ) + (int) $count;
			}
		}
		arsort( $totals );
		return array_slice( $totals, 0, $limit, true );
	}

	/**
	 * Whether a user is never limited (administrators by default).
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_exempt( $user_id ) {
		/**
		 * Filters whether a user is exempt from all limits and alerts.
		 *
		 * @param bool $exempt  Whether the user is exempt.
		 * @param int  $user_id User ID.
		 */
		return (bool) apply_filters( 'dlgd_is_exempt', user_can( $user_id, 'manage_options' ), $user_id );
	}

	/**
	 * Alerts about and optionally blocks an account that downloads too much too fast.
	 *
	 * Runs before the current download is logged, so it fires on the download that would
	 * go over the threshold.
	 *
	 * @param int $user_id User ID.
	 */
	private static function detect_burst( $user_id ) {
		$burst = DLGD_Settings::get()['burst'];
		if ( ! $burst['enabled'] ) {
			return;
		}

		// Downloads before an administrator unblocked the account were already reviewed.
		$since = max( time() - $burst['minutes'] * MINUTE_IN_SECONDS, DLGD_Blocks::get_unblocked_time( $user_id ) );
		$count = count( self::get_download_times( $user_id, $since ) );
		if ( $count < $burst['count'] ) {
			return;
		}

		if ( $burst['block'] ) {
			DLGD_Blocks::block(
				$user_id,
				array(
					'reason'  => 'burst',
					'count'   => $count,
					'minutes' => $burst['minutes'],
				)
			);
		}

		// One alert per account and period, not one per extra download.
		$transient = 'dlgd_burst_alert_' . $user_id;
		if ( get_transient( $transient ) ) {
			return;
		}
		set_transient( $transient, 1, $burst['minutes'] * MINUTE_IN_SECONDS );

		/**
		 * Fires when an account downloads too much too fast.
		 *
		 * @param int   $user_id User ID.
		 * @param int   $count   Downloads in the period.
		 * @param array $burst   Bulk-download settings.
		 */
		do_action( 'dlgd_burst_detected', $user_id, $count, $burst );

		if ( $burst['notify'] ) {
			self::send_burst_alert( $user_id, $count, $burst );
		}
	}

	/**
	 * Emails the administrator about bulk downloading.
	 *
	 * @param int   $user_id User ID.
	 * @param int   $count   Downloads in the period.
	 * @param array $burst   Bulk-download settings.
	 */
	private static function send_burst_alert( $user_id, $count, array $burst ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$to   = '' !== $burst['email'] ? $burst['email'] : get_option( 'admin_email' );
		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$subject = sprintf(
			/* translators: 1: site name, 2: username. */
			__( '[%1$s] Bulk downloading by %2$s', 'download-guard' ),
			$site,
			$user->user_login
		);

		$lines = array(
			sprintf(
				/* translators: 1: username, 2: email address, 3: number of downloads, 4: number of minutes. */
				__( 'The account %1$s (%2$s) has made %3$d downloads in the last %4$d minutes.', 'download-guard' ),
				$user->user_login,
				$user->user_email,
				$count,
				$burst['minutes']
			),
			'',
			$burst['block']
				? __( 'The account has been blocked from downloading until you unblock it.', 'download-guard' )
				: __( 'The account has not been blocked. You can block it from the activity screen.', 'download-guard' ),
			'',
			__( 'Activity:', 'download-guard' ) . ' ' . DLGD_Admin::activity_url(),
			// Not get_edit_user_link(): it depends on the current user, who here is the downloader.
			__( 'User profile:', 'download-guard' ) . ' ' . add_query_arg( 'user_id', $user_id, self_admin_url( 'user-edit.php' ) ),
		);

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Whether the user is still within the new-member period.
	 *
	 * @param WP_User $user User.
	 * @param int     $days Length of the period in days (0 disables it).
	 * @return bool
	 */
	private static function is_new_member( WP_User $user, $days ) {
		return $days > 0 && ( time() - self::get_member_since( $user ) ) < $days * DAY_IN_SECONDS;
	}

	/**
	 * Returns when the user downloaded something since a given time, across platforms.
	 *
	 * Reads the last 30 days once per request and filters in memory.
	 *
	 * @param int $user_id User ID.
	 * @param int $since   Timestamp.
	 * @return int[] Timestamps, oldest first.
	 */
	private static function get_download_times( $user_id, $since ) {
		if ( ! isset( self::$times[ $user_id ] ) ) {
			$from  = time() - max( self::WINDOWS );
			$times = array();
			foreach ( self::$adapters as $adapter ) {
				$times = array_merge( $times, $adapter->get_download_times( $user_id, $from ) );
			}
			sort( $times );
			self::$times[ $user_id ] = $times;
		}

		$times = array();
		foreach ( self::$times[ $user_id ] as $time ) {
			if ( $time > $since ) {
				$times[] = $time;
			}
		}
		return $times;
	}
}
