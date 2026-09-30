<?php
/**
 * Email Provider Interface.
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
 * Email Provider Interface.
 *
 * @since 3.6.6
 */
interface ProviderInterface {

	/**
	 * Get the provider ID.
	 *
	 * @since 3.6.6
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Get the provider name.
	 *
	 * @since 3.6.6
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Detect whether a payload belongs to this provider's bounce format.
	 *
	 * @since 3.6.6
	 * @param array $payload The webhook payload.
	 * @return bool
	 */
	public function can_handle_bounce( array $payload ): bool;

	/**
	 * Parse a bounce payload into a standardized format.
	 *
	 * @since 3.6.6
	 * @param array $payload The webhook payload.
	 * @return array|null Array with 'email_id' and 'reason' keys, or null on failure.
	 */
	public function parse_bounce( array $payload ): ?array;
}
