<?php
/**
 * Origin and volume controls for front-end registration.
 *
 * @package     EDD\Users
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Users;

defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Utils\Tokenizer;
use EDD\Utils\Validators\RateLimiter;

/**
 * Controls on the front-end registration handler.
 *
 * Registration is offered independently of WordPress's own setting, which is what the shortcode
 * and block exist for, so these are origin and volume controls rather than a policy gate.
 *
 * @since 3.7.1
 */
class RegistrationGuard implements SubscriberInterface {

	/**
	 * Transient prefix the rate limiter keys its window on.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const RATE_PREFIX = 'edd_register_rate';

	/**
	 * Accounts one caller may create per window.
	 *
	 * @since 3.7.1
	 * @var int
	 */
	const MAX_ATTEMPTS = 5;

	/**
	 * Length of the rate limit window, in seconds.
	 *
	 * @since 3.7.1
	 * @var int
	 */
	const WINDOW = HOUR_IN_SECONDS;

	/**
	 * Registers the hooks this class listens on.
	 *
	 * @since 3.7.1
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'edd_register_form_fields_before_submit' => 'token_fields',
		);
	}

	/**
	 * Prints the token the handler requires.
	 *
	 * Tokenized rather than a nonce so the value survives full page caching, which is what the
	 * Tokenizer exists for, and does not move on WordPress's own nonce tick.
	 *
	 * @since 3.7.1
	 * @return void
	 */
	public static function token_fields() {
		$timestamp = time();

		printf(
			'<input type="hidden" name="edd_register_timestamp" value="%1$s" /><input type="hidden" name="edd_register_token" value="%2$s" />',
			esc_attr( $timestamp ),
			esc_attr( Tokenizer::tokenize( self::token_data( $timestamp ) ) )
		);
	}

	/**
	 * Whether a submission carries a token this store issued for this form.
	 *
	 * @since 3.7.1
	 *
	 * @param array $data The submitted form data.
	 * @return bool
	 */
	public static function has_valid_token( $data ) {
		$timestamp = isset( $data['edd_register_timestamp'] ) ? sanitize_text_field( $data['edd_register_timestamp'] ) : '';
		$token     = isset( $data['edd_register_token'] ) ? sanitize_text_field( $data['edd_register_token'] ) : '';

		if ( empty( $timestamp ) || empty( $token ) ) {
			return false;
		}

		return Tokenizer::is_token_valid( $token, self::token_data( $timestamp ) );
	}

	/**
	 * Counts this submission against the caller's window, and reports whether it is allowed.
	 *
	 * Keyed on edd_get_ip(), matching the limiter in cart recovery. That value reads
	 * HTTP_CLIENT_IP then HTTP_X_FORWARDED_FOR before REMOTE_ADDR, so a caller who sets either
	 * header lands in a fresh window and one who sends another address can spend theirs. The
	 * identifier is tracked in issue #2385 and this surface follows whatever it settles on.
	 *
	 * Each call consumes a slot, so call it only once the submission is otherwise going to
	 * create an account.
	 *
	 * @since 3.7.1
	 * @return true|\WP_Error True when allowed, WP_Error when the window is exhausted.
	 */
	public static function check_rate_limit() {
		$identifier = edd_get_ip();
		if ( empty( $identifier ) || ! is_string( $identifier ) ) {
			$identifier = 'unknown';
		}

		return self::limiter()->check( $identifier );
	}

	/**
	 * The limiter this class counts submissions on.
	 *
	 * @since 3.7.1
	 * @return RateLimiter
	 */
	public static function limiter() {
		return new RateLimiter( self::RATE_PREFIX, self::MAX_ATTEMPTS, self::WINDOW );
	}

	/**
	 * The value the token signs.
	 *
	 * Scoped to this form, so only a token issued for registration validates here.
	 *
	 * @since 3.7.1
	 *
	 * @param int|string $timestamp The timestamp the token was issued for.
	 * @return string
	 */
	private static function token_data( $timestamp ) {
		return 'edd-register-' . $timestamp;
	}
}
