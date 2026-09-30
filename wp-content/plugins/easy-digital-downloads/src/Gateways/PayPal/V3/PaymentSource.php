<?php
/**
 * PayPal V3 payment source shaping.
 *
 * Builds the shared pieces of the `payment_source` block sent on v3 PayPal
 * order requests, so the vault-with-purchase flows (recurring PayPal-source and
 * card-source orders, and Pro's own save-method path) construct the same shape
 * from one place instead of each hand-building it.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * PaymentSource class.
 *
 * @since 3.6.9
 */
class PaymentSource {

	/**
	 * Builds the `attributes` block for a vault-with-purchase payment source.
	 *
	 * Returned verbatim under `payment_source.{paypal|card}.attributes` on a v3
	 * order request. The attribute shape is identical for both source keys; the
	 * caller is responsible for placing it under the correct key and for any
	 * source-specific siblings (e.g. the PayPal source's `experience_context`
	 * and `billing_plan`).
	 *
	 * The `usage_type` of MERCHANT designates the token for merchant-initiated
	 * recurring charges; `store_in_vault` ON_SUCCESS only vaults once the capture
	 * succeeds.
	 *
	 * @since 3.6.9
	 *
	 * @param string $paypal_customer_id Existing PayPal vault customer ID for a
	 *                                   returning buyer, or empty for a new buyer.
	 * @return array The `attributes` block.
	 */
	public static function vault_attributes( string $paypal_customer_id = '' ): array {
		$attributes = array(
			'vault' => array(
				'store_in_vault' => 'ON_SUCCESS',
				'usage_type'     => 'MERCHANT',
				'usage_pattern'  => 'SUBSCRIPTION_PREPAID',
			),
		);

		// Attach the existing PayPal vault customer ID so the new token is saved
		// against the buyer's existing customer profile (PayPal IWT requirement).
		// `customer` is a sibling of `vault` under `attributes` — PayPal silently
		// drops it when nested inside `vault`.
		if ( '' !== $paypal_customer_id ) {
			$attributes['customer'] = array( 'id' => $paypal_customer_id );
		}

		return $attributes;
	}
}
