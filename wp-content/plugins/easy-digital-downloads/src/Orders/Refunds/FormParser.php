<?php
/**
 * Refund Form Parser.
 *
 * Normalises the serialized payload submitted from the Submit Refund form
 * into the array shapes expected by `edd_refund_order()` and the refund
 * Validator. Used by the core AJAX handler in
 * `includes/admin/payments/actions.php` and by gateway-level pre-flight
 * handlers (e.g. PayPal Commerce v3) so the same parsing rules govern
 * both paths and stay in sync.
 *
 * @package     EDD\Orders\Refunds
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Orders\Refunds;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class FormParser
 *
 * @since 3.6.9
 */
class FormParser {

	/**
	 * The refund form's nonce action, which is also the name of the field carrying it.
	 *
	 * @since 3.7.1
	 */
	const NONCE_ACTION = 'edd_process_refund';

	/**
	 * Whether the refund form payload carries a valid nonce.
	 *
	 * Both the AJAX handler and the PayPal pre-flight which runs ahead of it need this, so the
	 * field name and the action live here rather than in each caller.
	 *
	 * @since 3.7.1
	 *
	 * @param array $form_data Parsed form data from the refund submission.
	 * @return bool
	 */
	public static function verify_nonce( array $form_data ): bool {
		if ( empty( $form_data[ self::NONCE_ACTION ] ) ) {
			return false;
		}

		return (bool) wp_verify_nonce( sanitize_text_field( $form_data[ self::NONCE_ACTION ] ), self::NONCE_ACTION );
	}

	/**
	 * Parses the order-item rows out of the deserialised refund-form payload.
	 *
	 * Discards rows that have no quantity or that have neither subtotal nor
	 * tax — mirroring the gate the EDD admin uses so the resulting array can
	 * be passed straight into `edd_refund_order()` or the refund Validator.
	 *
	 * @since 3.6.9
	 *
	 * @param array $form_data Parsed form data from the refund submission.
	 * @return array
	 */
	public static function parse_order_items( array $form_data ): array {
		$order_items = array();

		if ( empty( $form_data['refund_order_item'] ) || ! is_array( $form_data['refund_order_item'] ) ) {
			return $order_items;
		}

		foreach ( $form_data['refund_order_item'] as $order_item_id => $order_item ) {
			if ( empty( $order_item['quantity'] ) || ( empty( $order_item['subtotal'] ) && empty( $order_item['tax'] ) ) ) {
				continue;
			}

			$order_items[] = array(
				'order_item_id' => absint( $order_item_id ),
				'quantity'      => intval( $order_item['quantity'] ),
				'subtotal'      => edd_sanitize_amount( $order_item['subtotal'] ),
				'tax'           => ! empty( $order_item['tax'] ) ? edd_sanitize_amount( $order_item['tax'] ) : 0.00,
			);
		}

		return $order_items;
	}

	/**
	 * Parses the adjustment rows out of the deserialised refund-form payload.
	 *
	 * @since 3.6.9
	 *
	 * @param array $form_data Parsed form data from the refund submission.
	 * @return array
	 */
	public static function parse_adjustments( array $form_data ): array {
		$adjustments = array();

		if ( empty( $form_data['refund_order_adjustment'] ) || ! is_array( $form_data['refund_order_adjustment'] ) ) {
			return $adjustments;
		}

		foreach ( $form_data['refund_order_adjustment'] as $adjustment_id => $adjustment ) {
			if ( empty( $adjustment['quantity'] ) || empty( $adjustment['subtotal'] ) ) {
				continue;
			}

			$adjustments[] = array(
				'adjustment_id' => absint( $adjustment_id ),
				'quantity'      => intval( $adjustment['quantity'] ),
				'subtotal'      => floatval( edd_sanitize_amount( $adjustment['subtotal'] ) ),
				'tax'           => ! empty( $adjustment['tax'] ) ? floatval( edd_sanitize_amount( $adjustment['tax'] ) ) : 0.00,
			);
		}

		return $adjustments;
	}
}
