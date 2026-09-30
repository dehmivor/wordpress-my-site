<?php
/**
 * One-Time Login Link Verify Handler
 *
 * Handles token validation for one-time login link URLs.
 *
 * @package     EDD\Users\LoginLink\Verify
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Users\LoginLink\Verify;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Users\LoginLink\Token;
use EDD\Users\LoginLink\Utility;

/**
 * Handler class.
 *
 * Processes incoming one-time login link requests by validating
 * the token and logging the user in.
 *
 * @since 3.6.7
 */
class Handler implements SubscriberInterface {

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'edd_login_link_verify' => 'handle_verify',
		);
	}

	/**
	 * Handles an incoming login link verify request via edd_action.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public function handle_verify() {
		\EDD\Cache\NoCache::set_headers();

		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $token ) ) {
			$this->redirect_with_error( 'missing_token' );
			return;
		}

		$this->process_login( $token );
	}

	/**
	 * Processes a one-time login link request.
	 *
	 * Delegates validation to validate_token() and, on success,
	 * logs the user in and redirects to checkout.
	 *
	 * @since 3.6.7
	 * @param string $token Raw login link token.
	 * @return void
	 */
	private function process_login( string $token ) {
		$result = $this->validate_token( $token );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $result->get_error_code() );
			return;
		}

		Token::invalidate( $result['user_id'], $token );

		$this->log_user_in( $result['user'] );

		Utility::cleanup_rate_limits();

		edd_redirect( edd_get_checkout_uri() );
	}

	/**
	 * Validates a login link token through all required checks.
	 *
	 * Runs feature-enabled, rate-limit, and user-resolution checks,
	 * then delegates stored-token verification to verify_token_meta().
	 *
	 * @since 3.6.7
	 * @param string $token Raw login link token.
	 * @return array|\WP_Error {
	 *     Validated data on success, WP_Error on failure.
	 *
	 *     @type int      $user_id The resolved user ID.
	 *     @type \WP_User $user    The resolved user object.
	 * }
	 */
	private function validate_token( string $token ) {
		if ( ! Utility::enabled() ) {
			return new \WP_Error( 'login_link_disabled' );
		}

		if ( Validate::is_limited() ) {
			return new \WP_Error( 'rate_limited' );
		}

		$user_id = Token::resolve_user_id( $token );
		if ( empty( $user_id ) || empty( $token ) ) {
			return new \WP_Error( 'token_invalid' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! Utility::user_allowed( $user ) ) {
			return new \WP_Error( 'invalid_user' );
		}

		return $this->verify_token_meta( $user_id, $user, $token );
	}

	/**
	 * Verifies the stored token metadata against the incoming token.
	 *
	 * Checks that the user meta exists, the token has not already been
	 * used, has not expired, and that the hash matches.
	 *
	 * @since 3.6.7
	 * @param int      $user_id User ID.
	 * @param \WP_User $user    User object.
	 * @param string   $token   Raw login link token.
	 * @return array|\WP_Error Validated data on success, WP_Error on failure.
	 */
	private function verify_token_meta( int $user_id, \WP_User $user, string $token ) {
		$meta = get_user_meta( $user_id, Token::META_KEY, true );
		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return new \WP_Error( 'missing_token' );
		}

		if ( get_transient( 'edd_login_link_used_' . $user_id ) ) {
			return new \WP_Error( 'token_used' );
		}

		$expires_at = ! empty( $meta['expires_at'] ) ? absint( $meta['expires_at'] ) : 0;
		if ( empty( $expires_at ) || time() > $expires_at ) {
			return new \WP_Error( 'token_expired' );
		}

		if ( empty( $meta['hash'] ) || ! hash_equals( $meta['hash'], Token::hash( $token ) ) ) {
			return new \WP_Error( 'token_invalid' );
		}

		return array(
			'user_id' => $user_id,
			'user'    => $user,
		);
	}

	/**
	 * Logs the user in without a password.
	 *
	 * @since 3.6.7
	 * @param \WP_User $user User object.
	 * @return void
	 */
	private function log_user_in( $user ) {
		do_action( 'edd_pre_log_user_in', $user->ID, $user->user_login );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, false, is_ssl() );

		do_action( 'wp_login', $user->user_login, $user );
		do_action( 'edd_log_user_in', $user->ID, $user->user_login, '' );

		// Show a success message to the user.
		\EDD\Utils\Messages::add( 'success', 'edd_login_link_success', __( 'You have been logged in successfully.', 'easy-digital-downloads' ) );
	}

	/**
	 * Redirects to checkout with a generic error message.
	 *
	 * Records a validation failure for rate-limiting purposes before
	 * redirecting.
	 *
	 * @since 3.6.7
	 * @param string $reason Failure reason identifier.
	 * @return void
	 */
	private function redirect_with_error( $reason ) {
		$skip_recording = array( 'rate_limited', 'login_link_disabled' );
		if ( ! in_array( $reason, $skip_recording, true ) ) {
			Validate::record_failure();
		}

		$messages = array(
			'rate_limited'        => __( 'Too many attempts. Please try again later.', 'easy-digital-downloads' ),
			'token_expired'       => __( 'This login link has expired. Please request a new one.', 'easy-digital-downloads' ),
			'token_used'          => __( 'This login link has already been used. Please request a new one.', 'easy-digital-downloads' ),
			'login_link_disabled' => __( 'Login links are currently disabled.', 'easy-digital-downloads' ),
		);

		$message = ! empty( $messages[ $reason ] )
			? $messages[ $reason ]
			: __( 'This login link is invalid or has expired.', 'easy-digital-downloads' );

		\EDD\Utils\Messages::add( 'warn', 'edd_login_link_' . $reason, $message );
		edd_redirect( edd_get_checkout_uri() );
	}
}
