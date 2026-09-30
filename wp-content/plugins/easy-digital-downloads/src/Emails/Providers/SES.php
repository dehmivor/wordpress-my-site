<?php
/**
 * AWS SES Email Provider.
 *
 * @package EDD\Emails\Providers
 * @copyright Copyright (c) 2026, Sandhills Development, LLC
 * @license https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 3.6.6
 */

namespace EDD\Emails\Providers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * AWS SES Email Provider class.
 *
 * @since 3.6.6
 */
class SES extends Provider {

	/**
	 * Get the provider ID.
	 *
	 * @since 3.6.6
	 * @return string
	 */
	public function get_id(): string {
		return 'ses';
	}

	/**
	 * Get the provider name.
	 *
	 * @since 3.6.6
	 * @return string
	 */
	public function get_name(): string {
		return 'Amazon SES';
	}

	/**
	 * Detect whether a payload belongs to AWS SES's bounce format (via SNS).
	 *
	 * @since 3.6.6
	 * @param array $payload The webhook payload.
	 * @return bool
	 */
	public function can_handle_bounce( array $payload ): bool {
		return isset( $payload['Type'], $payload['Message'] ) && 'Notification' === $payload['Type'];
	}

	/**
	 * Parse an AWS SES bounce payload.
	 *
	 * @since 3.6.6
	 * @param array $payload The webhook payload.
	 * @return array|null Array with 'email_id' and 'reason' keys, or null on failure.
	 */
	public function parse_bounce( array $payload ): ?array {
		if ( ! isset( $payload['Message'] ) ) {
			return null;
		}

		$message = json_decode( $payload['Message'], true );

		if ( ! $message || ! isset( $message['bounce'] ) ) {
			return null;
		}

		$bounce     = $message['bounce'];
		$recipients = $bounce['bouncedRecipients'] ?? array();

		if ( empty( $recipients ) ) {
			return null;
		}

		$email  = $recipients[0]['emailAddress'] ?? '';
		$reason = $bounce['bounceType'] ?? 'Unknown bounce reason';

		$email_id = $this->find_email_by_recipient( $email );

		if ( ! $email_id ) {
			return null;
		}

		return array(
			'email_id' => $email_id,
			'reason'   => $reason,
		);
	}
}
