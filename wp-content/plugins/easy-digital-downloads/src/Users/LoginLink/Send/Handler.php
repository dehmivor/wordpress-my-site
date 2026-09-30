<?php
/**
 * One-Time Login Link Send Handler
 *
 * Handles AJAX requests to send a one-time login link email.
 *
 * @package     EDD\Users\LoginLink\Send
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Users\LoginLink\Send;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Users\LoginLink\Token;
use EDD\Users\LoginLink\Utility;

/**
 * Handler class.
 *
 * Processes the front-end AJAX request to generate and email
 * a one-time login link URL to the user.
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
			'wp_ajax_nopriv_edd_send_login_link' => 'send_login_link',
		);
	}

	/**
	 * Handles the AJAX request to send a login link email.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	public function send_login_link() {
		if ( ! Utility::enabled() ) {
			wp_send_json_error(
				array(
					'message' => __( 'One-Time Login Link is not available.', 'easy-digital-downloads' ),
					'code'    => 'login_link_disabled',
				)
			);
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'edd-login-link' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Security verification failed.', 'easy-digital-downloads' ),
					'code'    => 'invalid_nonce',
				)
			);
		}

		// Early IP rate limit (before user is validated).
		$rate_limit = Validate::check_ip();
		if ( is_wp_error( $rate_limit ) ) {
			$this->send_generic_success();
			return;
		}

		// Record the IP hit immediately so every request that passes
		// nonce + IP validation counts, not just ones that send an email.
		Validate::record();

		$login = isset( $_POST['email'] ) ? sanitize_text_field( wp_unslash( $_POST['email'] ) ) : '';
		if ( empty( $login ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter your email or username first.', 'easy-digital-downloads' ),
					'code'    => 'missing_login',
				)
			);
		}

		$user = Utility::get_user_from_login( $login );
		if ( ! $user ) {
			$this->send_generic_success();
			return;
		}

		if ( ! Utility::user_allowed( $user ) ) {
			$this->send_generic_success();
			return;
		}

		// Per-user cooldown check (IP already checked and recorded above).
		$rate_limit = Validate::check_user( $user->ID );
		if ( is_wp_error( $rate_limit ) ) {
			$this->send_generic_success();
			return;
		}

		$token_data = Token::issue( $user->ID );
		if ( empty( $token_data['token'] ) ) {
			$this->send_generic_success();
			return;
		}

		$email = new \EDD\Emails\Types\LoginLink( $user->ID, $token_data );
		$email->send();

		$this->send_generic_success();
	}

	/**
	 * Sends an ambiguous success response.
	 *
	 * Always returns the same message regardless of whether the user
	 * exists or the email was actually sent, preventing user enumeration.
	 *
	 * @since 3.6.7
	 * @return void
	 */
	private function send_generic_success() {
		wp_send_json_success(
			array(
				'message' => __( 'Check your email! If you have a registered account with us, a login link is waiting in your inbox.', 'easy-digital-downloads' ),
			)
		);
	}
}
