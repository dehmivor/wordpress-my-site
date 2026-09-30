<?php
/**
 * PayPal V3 customer ID storage.
 *
 * Maps EDD customers to their PayPal vault customer IDs (one per mode),
 * stored on the EDD customer record as meta.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal\Gateway;

/**
 * Customer class.
 *
 * @since 3.6.9
 */
class Customer {

	/**
	 * Customer meta key for the PayPal vault customer ID (live).
	 *
	 * @since 3.6.9
	 */
	const META_KEY_LIVE = '_edd_paypal_vault_customer_id';

	/**
	 * Customer meta key for the PayPal vault customer ID (sandbox).
	 *
	 * @since 3.6.9
	 */
	const META_KEY_TEST = '_edd_paypal_vault_customer_id_test';

	/**
	 * Gets the PayPal vault customer ID for an EDD customer in the active mode.
	 *
	 * @since 3.6.9
	 *
	 * @param int $customer_id EDD customer ID.
	 * @return string|false The PayPal customer ID or false.
	 */
	public static function get_id( $customer_id ) {
		return edd_get_customer_meta( $customer_id, self::get_meta_key(), true );
	}

	/**
	 * Saves the PayPal vault customer ID for an EDD customer if not already stored.
	 *
	 * @since 3.6.9
	 *
	 * @param int    $customer_id        EDD customer ID.
	 * @param string $paypal_customer_id PayPal vault customer ID.
	 * @return void
	 */
	public static function save_id( $customer_id, $paypal_customer_id ) {
		$meta_key = self::get_meta_key();
		$existing = edd_get_customer_meta( $customer_id, $meta_key, true );

		if ( empty( $existing ) ) {
			edd_add_customer_meta( $customer_id, $meta_key, $paypal_customer_id, true );
		}
	}

	/**
	 * Returns the meta key for the active PayPal mode.
	 *
	 * @since 3.6.9
	 *
	 * @return string The customer meta key for the active mode.
	 */
	public static function get_meta_key(): string {
		return 'sandbox' === Gateway::get_paypal_mode() ? self::META_KEY_TEST : self::META_KEY_LIVE;
	}
}
