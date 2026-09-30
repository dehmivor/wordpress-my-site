<?php
/**
 * Ability to resend an EDD order receipt.
 *
 * @package     EDD\Abilities\Orders
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Orders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\WriteAbility;

/**
 * Resends the purchase receipt email for an order.
 *
 * @since 3.7.1
 */
class ResendReceipt extends WriteAbility {

	/**
	 * Writes the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function write_data( array $input ) {
		$order_id = absint( $input['order_id'] );

		$order = edd_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error(
				'edd_ability_order_not_found',
				__( 'No order exists with that ID.', 'easy-digital-downloads' ),
				array( 'status' => 404 )
			);
		}

		if ( 'sale' !== $order->type ) {
			return new \WP_Error(
				'edd_ability_order_not_a_sale',
				__( 'That ID belongs to a refund record rather than an order.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		if ( empty( $order->email ) ) {
			return new \WP_Error(
				'edd_ability_order_no_email',
				__( 'This order has no email address to send a receipt to.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		// Bail cleanly when the receipt email is missing or disabled on this store.
		$email = edd_get_email( 'order_receipt' );
		if ( ! $email || ! $email->is_enabled() ) {
			return new \WP_Error(
				'edd_ability_receipt_disabled',
				__( 'The order receipt email is not enabled on this store.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		$receipt = \EDD\Emails\Registry::get( 'order_receipt', array( $order ) );
		$sent    = $receipt->send();

		if ( $sent ) {
			$this->log_note( $order_id, 'order' );
		}

		return array(
			'sent'     => (bool) $sent,
			'order_id' => $order_id,
			'email'    => (string) $order->email,
		);
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'order-resend-receipt';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Resend Order Receipt', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Resend the purchase receipt email for an order to the email address on the order.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'edit_shop_payments';
	}

	/**
	 * Gets the input schema.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'order_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The order ID.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'order_id' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Gets the output schema.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'sent'     => array( 'type' => 'boolean' ),
				'order_id' => array( 'type' => 'integer' ),
				'email'    => array(
					'type'        => 'string',
					'description' => __( 'The email address the receipt was sent to.', 'easy-digital-downloads' ),
				),
			),
			'required'   => array( 'sent', 'order_id', 'email' ),
		);
	}
}
