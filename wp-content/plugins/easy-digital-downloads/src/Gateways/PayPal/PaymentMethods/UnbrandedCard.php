<?php
/**
 * Unbranded card payment method descriptor.
 *
 * The on-checkout "Credit/Debit Cards on Checkout" fields (Advanced Card
 * Processing). Renders via the `card-fields` SDK component, requires the buyer
 * client token, and shares the `card` funding source with the branded button —
 * so the shared funding is only disabled when neither card method is enabled.
 *
 * @package     EDD\Gateways\PayPal\PaymentMethods
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\PaymentMethods;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Unbranded card method descriptor.
 *
 * @since 3.6.9
 */
class UnbrandedCard extends Method {

	/**
	 * The method slug.
	 *
	 * @since 3.6.9
	 *
	 * @var string
	 */
	protected static $id = 'unbranded_card';

	/**
	 * Returns the admin-facing label.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'Credit/Debit Cards on Checkout', 'easy-digital-downloads' );
	}

	/**
	 * Returns the admin-facing description.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return __( 'Allow users to pay with credit or debit cards on your checkout page.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the inline SVG icon.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return Card::get_icon();
	}

	/**
	 * Returns the capability that gates the default state.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_capability(): ?string {
		return 'advanced_card';
	}

	/**
	 * Unbranded card is locked off when advanced-card processing isn't granted.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_capability_locked(): bool {
		return true;
	}

	/**
	 * Returns the lock reason tooltip text.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_lock_reason(): string {
		return __( 'On-checkout card fields require PayPal\'s Advanced Card Processing, which has not been granted to your account.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the SDK component.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_sdk_component(): ?string {
		return 'card-fields';
	}

	/**
	 * Returns the funding source slug.
	 *
	 * Shares the `card` funding source with the branded button, but renders as a
	 * component rather than a Buttons funding source.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_funding_source(): ?string {
		return 'card';
	}

	/**
	 * Requires the buyer client token to initialize the card fields.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function requires_client_token(): bool {
		return true;
	}
}
