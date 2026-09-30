<?php
/**
 * REST API controller for Bounce Webhook handling.
 *
 * Handles bounce webhook notifications from email service providers
 * (SendGrid, Mailgun, AWS SES, SendLayer, and generic formats) for EDD emails.
 *
 * @package EDD\REST\Controllers
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license https://opensource.org/licenses/GPL-3.0 GNU General Public License v3.0
 * @since 3.6.5
 */

namespace EDD\REST\Controllers;

use EDD\Emails\Providers\Provider;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Bounce Webhook controller class.
 *
 * @since 3.6.5
 */
final class BounceWebhook {

	/**
	 * Handles bounce webhook notifications.
	 *
	 * @since 3.6.5
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function handle_bounce( \WP_REST_Request $request ): \WP_REST_Response {
		$body = $request->get_json_params();

		if ( empty( $body ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Invalid payload' ),
				400
			);
		}

		// Parse the webhook payload based on provider.
		$parsed = $this->parse_bounce_webhook( $body, $request );

		if ( ! $parsed || empty( $parsed['email_id'] ) || empty( $parsed['reason'] ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Unable to parse bounce data' ),
				400
			);
		}

		// Record the bounce.
		$recorded = add_metadata( 'edd_logs_email', $parsed['email_id'], 'bounce', $parsed['reason'] );

		if ( $recorded ) {
			/**
			 * Fires after a bounce is successfully recorded on an email log entry.
			 *
			 * @since 3.6.5
			 * @param int    $email_log_id The email log ID (from edd_logs_emails).
			 * @param string $reason       The bounce reason.
			 */
			do_action( 'edd_email_bounced', $parsed['email_id'], $parsed['reason'] );

			return new \WP_REST_Response(
				array(
					'success' => true,
					'message' => 'Bounce recorded',
				),
				200
			);
		}

		return new \WP_REST_Response(
			array( 'error' => 'Failed to record bounce' ),
			500
		);
	}

	/**
	 * Verifies webhook permission via secret key.
	 *
	 * @since 3.6.5
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if authorized.
	 */
	public function verify_webhook_permission( \WP_REST_Request $request ): bool {
		// Check for secret key in header or query param.
		$provided_key = $request->get_header( 'X-EDD-Webhook-Secret' );

		if ( empty( $provided_key ) ) {
			$provided_key = $request->get_param( 'secret' );
		}

		if ( empty( $provided_key ) ) {
			edd_debug_log( '[EDD Bounce Webhook] Webhook rejected: no secret provided', true );
			return false;
		}

		// Get the expected webhook secret.
		$expected_secret = self::generate_webhook_secret();

		// Verify the key matches.
		if ( ! hash_equals( $expected_secret, $provided_key ) ) {
			edd_debug_log( '[EDD Bounce Webhook] Webhook rejected: invalid secret', true );
			return false;
		}

		return true;
	}

	/**
	 * Generates the webhook secret.
	 *
	 * Static method to allow access from settings/admin screens.
	 *
	 * @since 3.6.5
	 * @return string Webhook secret key.
	 */
	public static function generate_webhook_secret(): string {
		return hash_hmac( 'sha256', 'edd_bounce_webhook', wp_salt( 'nonce' ) );
	}

	/**
	 * Parses bounce webhook payload from various email service providers.
	 *
	 * @since 3.6.5
	 * @param array            $body    The webhook payload.
	 * @param \WP_REST_Request $request The request object.
	 * @return array|null Parsed bounce data with email_id and reason, or null on failure.
	 */
	private function parse_bounce_webhook( array $body, \WP_REST_Request $request ): ?array {
		// Try registered email service providers first.
		$provider = Provider::get_provider_for_bounce( $body );
		if ( $provider ) {
			return $provider->parse_bounce( $body );
		}

		// Generic fallback: accepts raw email_id + reason.
		if ( isset( $body['email_id'] ) && isset( $body['reason'] ) ) {
			return array(
				'email_id' => absint( $body['email_id'] ),
				'reason'   => sanitize_text_field( $body['reason'] ),
			);
		}

		edd_debug_log( '[EDD Bounce Webhook] Unknown bounce webhook format: ' . wp_json_encode( $body ), true );
		return null;
	}
}
