<?php
/**
 * Pay Later payment method descriptor.
 *
 * PayPal's Pay in 4 / PayPal Credit options plus on-checkout messaging. Not
 * capability-locked in EDD — PayPal's SDK `isEligible()` check is the
 * authoritative gate at render time, because the merchant-status response
 * doesn't reliably surface Pay Later capability across regions.
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
 * Pay Later method descriptor.
 *
 * @since 3.6.9
 */
class PayLater extends Method {

	/**
	 * The method slug.
	 *
	 * @since 3.6.9
	 *
	 * @var string
	 */
	protected static $id = 'pay_later';

	/**
	 * Returns the admin-facing label.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'Pay Later', 'easy-digital-downloads' );
	}

	/**
	 * Returns the admin-facing description.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return __( 'Show Pay in 4 / PayPal Credit options and on-checkout messaging.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the inline SVG icon.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return '<svg aria-hidden="true" height="32" width="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="4" fill="#003087"/><rect x="8" y="10" width="16" height="13" rx="1.5" fill="#fff"/><rect x="8" y="10" width="16" height="3" rx="1.5" fill="#009cde"/><rect x="10.5" y="8.5" width="1.5" height="4" rx="0.6" fill="#003087"/><rect x="20" y="8.5" width="1.5" height="4" rx="0.6" fill="#003087"/><rect x="11" y="15" width="2" height="2" fill="#003087"/><rect x="15" y="15" width="2" height="2" fill="#003087"/><rect x="19" y="15" width="2" height="2" fill="#003087"/><rect x="11" y="19" width="2" height="2" fill="#003087"/><rect x="15" y="19" width="2" height="2" fill="#003087"/></svg>';
	}

	/**
	 * Returns the SDK component used for on-checkout messaging.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_sdk_component(): ?string {
		return 'messages';
	}

	/**
	 * Returns the funding source slug.
	 *
	 * The paired `credit` funding token is handled alongside `paylater` by the
	 * registry when assembling the SDK funding lists.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_funding_source(): ?string {
		return 'paylater';
	}

	/**
	 * Pay Later renders as a Buttons funding source.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_button_funding(): bool {
		return true;
	}

	/**
	 * Returns the informational eligibility tooltip.
	 *
	 * @since 3.6.9
	 *
	 * @return array|null
	 */
	public static function get_tooltip(): ?array {
		return array(
			'content'  => __( 'Renders only when PayPal determines the buyer is eligible based on cart amount, country, and account state.', 'easy-digital-downloads' ),
			'dashicon' => 'dashicons-info',
		);
	}
}
