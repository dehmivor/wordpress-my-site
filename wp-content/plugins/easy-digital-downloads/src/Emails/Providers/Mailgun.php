<?php
/**
 * Mailgun Email Provider.
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
 * Mailgun Email Provider class.
 *
 * @since 3.6.6
 */
class Mailgun extends Provider {

	/**
	 * Get the provider ID.
	 *
	 * @since 3.6.6
	 * @return string
	 */
	public function get_id(): string {
		return 'mailgun';
	}

	/**
	 * Get the provider name.
	 *
	 * @since 3.6.6
	 * @return string
	 */
	public function get_name(): string {
		return 'Mailgun';
	}

	/**
	 * Detect whether a payload belongs to Mailgun's bounce format.
	 *
	 * @since 3.6.6
	 * @param array $payload The webhook payload.
	 * @return bool
	 */
	public function can_handle_bounce( array $payload ): bool {
		return isset( $payload['event'] ) && 'failed' === $payload['event'] && isset( $payload['recipient'] );
	}

	/**
	 * Parse a Mailgun bounce payload.
	 *
	 * @since 3.6.6
	 * @param array $payload The webhook payload.
	 * @return array|null Array with 'email_id' and 'reason' keys, or null on failure.
	 */
	public function parse_bounce( array $payload ): ?array {
		$email  = $payload['recipient'] ?? '';
		$reason = $payload['error'] ?? 'Unknown bounce reason';

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
