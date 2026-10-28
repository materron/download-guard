<?php
/**
 * Sign-up filtering.
 *
 * @package DownloadGuard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rejects throwaway accounts at sign-up.
 *
 * Bulk downloaders tend to sign up with throwaway accounts: usernames made only of digits,
 * disposable email addresses or domains that can't receive mail. Covers the WordPress
 * registration form, Restrict Content Pro, Easy Digital Downloads (registration form and
 * checkout) and WooCommerce.
 */
final class DLGD_Signup {

	/**
	 * Hooks into the registration forms.
	 */
	public static function init() {
		add_filter( 'registration_errors', array( __CLASS__, 'filter_wp_errors' ), 10, 3 );
		add_filter( 'woocommerce_registration_errors', array( __CLASS__, 'filter_wp_errors' ), 10, 3 );
		add_action( 'rcp_form_errors', array( __CLASS__, 'validate_rcp' ) );
		add_action( 'edd_pre_process_register_form', array( __CLASS__, 'validate_edd_register' ) );
		add_action( 'edd_checkout_error_checks', array( __CLASS__, 'validate_edd_checkout' ) );
	}

	/**
	 * Returns why a sign-up must be rejected.
	 *
	 * The DNS check fails open when lookups are unavailable.
	 *
	 * @param string $login Username (may be empty when the form only asks for an email).
	 * @param string $email Email address.
	 * @return string Plain-text reason, or an empty string when the sign-up is fine.
	 */
	public static function get_rejection( $login, $email ) {
		$settings = DLGD_Settings::get()['signup'];
		$reason   = '';

		$login = (string) $login;
		$email = (string) $email;
		$at    = strrpos( $email, '@' );

		if ( $settings['numeric_usernames'] && '' !== $login && ctype_digit( $login ) ) {
			$reason = __( 'The username cannot contain only numbers.', 'download-guard' );
		} elseif ( false !== $at ) {
			$domain = strtolower( substr( $email, $at + 1 ) );

			if ( $settings['disposable_emails'] && in_array( $domain, self::get_disposable_domains(), true ) ) {
				$reason = __( 'Temporary email addresses are not accepted. Please use your usual email address.', 'download-guard' );
			} elseif ( $settings['check_mx'] && function_exists( 'checkdnsrr' ) && ! checkdnsrr( $domain, 'MX' ) && ! checkdnsrr( $domain, 'A' ) ) {
				$reason = __( 'This email address cannot receive mail. Please check it.', 'download-guard' );
			}
		}

		/**
		 * Filters why a sign-up is rejected.
		 *
		 * @param string $reason Plain-text reason, or an empty string to accept it.
		 * @param string $login  Username.
		 * @param string $email  Email address.
		 */
		return (string) apply_filters( 'dlgd_signup_rejection', $reason, $login, $email );
	}

	/**
	 * Disposable email domains.
	 *
	 * @return string[]
	 */
	public static function get_disposable_domains() {
		/**
		 * Filters the disposable email domains that are rejected at sign-up.
		 *
		 * @param string[] $domains Lowercase domains.
		 */
		return (array) apply_filters(
			'dlgd_disposable_email_domains',
			array(
				'10minutemail.com',
				'1secmail.com',
				'1secmail.net',
				'1secmail.org',
				'burnermail.io',
				'dispostable.com',
				'emailondeck.com',
				'fakeinbox.com',
				'getnada.com',
				'grr.la',
				'guerrillamail.com',
				'guerrillamail.info',
				'inboxkitten.com',
				'mail.gw',
				'mail.tm',
				'maildrop.cc',
				'mailinator.com',
				'mailnesia.com',
				'mintemail.com',
				'moakt.com',
				'mohmal.com',
				'nada.email',
				'sharklasers.com',
				'spamgourmet.com',
				'temp-mail.org',
				'tempmail.com',
				'tempmail.net',
				'tempr.email',
				'throwawaymail.com',
				'tmail.ws',
				'tmpmail.org',
				'trashmail.com',
				'yopmail.com',
				'yopmail.fr',
			)
		);
	}

	/**
	 * Validates the WordPress and WooCommerce registration forms.
	 *
	 * @param WP_Error $errors Registration errors.
	 * @param string   $login  Username.
	 * @param string   $email  Email address.
	 * @return WP_Error
	 */
	public static function filter_wp_errors( $errors, $login, $email ) {
		$reason = self::get_rejection( $login, $email );
		if ( '' !== $reason && $errors instanceof WP_Error ) {
			$errors->add( 'dlgd_signup_rejected', esc_html( $reason ) );
		}
		return $errors;
	}

	/**
	 * Validates the Restrict Content Pro registration form.
	 *
	 * @param array $posted Posted form data (Restrict Content Pro has verified its nonce).
	 */
	public static function validate_rcp( $posted ) {
		if ( is_user_logged_in() || ! function_exists( 'rcp_errors' ) ) {
			return;
		}

		$login  = isset( $posted['rcp_user_login'] ) ? sanitize_user( wp_unslash( $posted['rcp_user_login'] ) ) : '';
		$email  = isset( $posted['rcp_user_email'] ) ? sanitize_email( wp_unslash( $posted['rcp_user_email'] ) ) : '';
		$reason = self::get_rejection( $login, $email );

		if ( '' !== $reason ) {
			rcp_errors()->add( 'dlgd_signup_rejected', esc_html( $reason ), 'register' );
		}
	}

	/**
	 * Validates the Easy Digital Downloads registration form.
	 */
	public static function validate_edd_register() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Runs inside EDD's form handler, which has already verified the form token.
		$login = isset( $_POST['edd_user_login'] ) ? sanitize_user( wp_unslash( $_POST['edd_user_login'] ) ) : '';
		$email = isset( $_POST['edd_user_email'] ) ? sanitize_email( wp_unslash( $_POST['edd_user_email'] ) ) : '';
		// phpcs:enable

		$reason = self::get_rejection( $login, $email );
		if ( '' !== $reason && function_exists( 'edd_set_error' ) ) {
			edd_set_error( 'dlgd_signup_rejected', esc_html( $reason ) );
		}
	}

	/**
	 * Validates accounts created at the Easy Digital Downloads checkout.
	 *
	 * @param array $valid_data Checkout data validated by EDD.
	 */
	public static function validate_edd_checkout( $valid_data ) {
		if ( empty( $valid_data['new_user_data'] ) || ! is_array( $valid_data['new_user_data'] ) ) {
			return;
		}

		$new_user = $valid_data['new_user_data'];
		$login    = isset( $new_user['user_login'] ) ? (string) $new_user['user_login'] : '';
		$email    = isset( $new_user['user_email'] ) ? (string) $new_user['user_email'] : '';

		$reason = self::get_rejection( $login, $email );
		if ( '' !== $reason && function_exists( 'edd_set_error' ) ) {
			edd_set_error( 'dlgd_signup_rejected', esc_html( $reason ) );
		}
	}
}
