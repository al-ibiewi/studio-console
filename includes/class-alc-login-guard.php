<?php
/**
 * Light login protection: per-IP lockout after repeated failures and
 * one generic error so the form never confirms which usernames exist.
 *
 * Works alongside Wordfence or Limit Login Attempts; switch it off in
 * Settings if a site already runs one of those.
 *
 * @package AlibiewiConsole
 */

defined( 'ABSPATH' ) || exit;

class ALC_Login_Guard {

	public static function init() {
		if ( alc_opt( 'login_limit' ) ) {
			add_filter( 'authenticate', array( __CLASS__, 'block_if_locked' ), 99, 1 );
			add_action( 'wp_login_failed', array( __CLASS__, 'record_failure' ), 10, 2 );
			add_action( 'wp_login', array( __CLASS__, 'clear' ) );
		}

		if ( alc_opt( 'login_generic_errors' ) ) {
			add_filter( 'authenticate', array( __CLASS__, 'generic_error' ), 100, 1 );
		}

		add_filter( 'shake_error_codes', array( __CLASS__, 'shake_codes' ) );
	}

	/**
	 * Keep the core "shake" on our own error codes.
	 *
	 * @param array $codes Codes.
	 * @return array
	 */
	public static function shake_codes( $codes ) {
		$codes[] = 'alc_locked';
		$codes[] = 'alc_invalid_credentials';
		return $codes;
	}

	/**
	 * @return string
	 */
	private static function key() {
		return 'alc_lf_' . md5( alc_client_ip() );
	}

	/**
	 * Seconds left on a lockout, or 0.
	 *
	 * @return int
	 */
	public static function seconds_left() {
		$until = (int) get_transient( self::key() . '_lock' );
		return $until > time() ? $until - time() : 0;
	}

	/**
	 * @param WP_User|WP_Error|null $user Result so far.
	 * @return WP_User|WP_Error|null
	 */
	public static function block_if_locked( $user ) {
		$left = self::seconds_left();
		if ( ! $left ) {
			return $user;
		}

		$minutes = max( 1, (int) ceil( $left / MINUTE_IN_SECONDS ) );

		return new WP_Error(
			'alc_locked',
			sprintf(
				/* translators: %d: minutes until the lockout ends. */
				_n(
					'<strong>Too many attempts.</strong> For your security, sign-in is paused. Try again in %d minute.',
					'<strong>Too many attempts.</strong> For your security, sign-in is paused. Try again in %d minutes.',
					$minutes,
					'alibiewi-console'
				),
				$minutes
			)
		);
	}

	/**
	 * @param string        $username Username tried.
	 * @param WP_Error|null $error    Error (WP 5.4+).
	 */
	public static function record_failure( $username, $error = null ) {
		if ( self::seconds_left() ) {
			return;
		}
		if ( $error instanceof WP_Error && in_array( 'alc_locked', $error->get_error_codes(), true ) ) {
			return;
		}

		$window = (int) alc_opt( 'login_lockout_minutes' ) * MINUTE_IN_SECONDS;
		$count  = (int) get_transient( self::key() ) + 1;

		if ( $count >= (int) alc_opt( 'login_max_attempts' ) ) {
			set_transient( self::key() . '_lock', time() + $window, $window );
			delete_transient( self::key() );

			/**
			 * Fires when an IP gets locked out. Hook in to notify the studio.
			 *
			 * @param string $ip       Locked IP.
			 * @param string $username Last username tried.
			 */
			do_action( 'alc_login_locked', alc_client_ip(), $username );
			return;
		}

		set_transient( self::key(), $count, $window );
	}

	public static function clear() {
		delete_transient( self::key() );
	}

	/**
	 * Replace "unknown username" and "wrong password" with one message.
	 *
	 * @param WP_User|WP_Error|null $user Result so far.
	 * @return WP_User|WP_Error|null
	 */
	public static function generic_error( $user ) {
		if ( ! is_wp_error( $user ) ) {
			return $user;
		}

		$leaky = array( 'invalid_username', 'invalid_email', 'incorrect_password' );
		if ( ! array_intersect( $leaky, $user->get_error_codes() ) ) {
			return $user;
		}

		return new WP_Error(
			'alc_invalid_credentials',
			sprintf(
				'%1$s <a href="%2$s">%3$s</a>',
				__( '<strong>That did not match.</strong> Check your details and try again.', 'alibiewi-console' ),
				esc_url( wp_lostpassword_url() ),
				__( 'Forgot your password?', 'alibiewi-console' )
			)
		);
	}
}
