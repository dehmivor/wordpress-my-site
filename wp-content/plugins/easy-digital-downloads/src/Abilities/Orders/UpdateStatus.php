<?php
/**
 * Ability to update an EDD order's status.
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
 * Updates the status of an order.
 *
 * @since 3.7.1
 */
class UpdateStatus extends WriteAbility {

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
		$status   = sanitize_key( $input['status'] );

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

		$previous_status = $order->status;

		if ( $previous_status === $status ) {
			return array(
				'order_id'        => $order_id,
				'previous_status' => $previous_status,
				'new_status'      => $status,
				'updated'         => false,
			);
		}

		$updated = edd_update_order_status( $order_id, $status );

		if ( $updated ) {
			$this->log_note( $order_id, 'order' );
		}

		return array(
			'order_id'        => $order_id,
			'previous_status' => $previous_status,
			'new_status'      => $status,
			'updated'         => (bool) $updated,
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
		return 'order-update-status';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Update Order Status', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Change the status of an order. Status transitions fire the same hooks as changing the status in the admin, so downstream integrations react normally: completing an order emails the customer a purchase receipt and grants download access, and moving a completed order back to pending or processing subtracts it from store earnings and from the customer totals. Refunds cannot be issued here; use the order screen in the admin.', 'easy-digital-downloads' );
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
	 * Gets the behavior annotations.
	 *
	 * Declared destructive: moving a completed order back to pending or processing
	 * subtracts it from store earnings and from the customer's lifetime value.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => true,
			'idempotent'  => false,
		);
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
				'status'   => array(
					'type'        => 'string',
					'enum'        => array_values( array_diff( edd_get_payment_status_keys(), array( 'refunded', 'partially_refunded' ) ) ),
					'description' => __( 'The new status. Refund statuses are not allowed here; refunds have to be issued from the order screen in the admin.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'order_id', 'status' ),
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
				'order_id'        => array( 'type' => 'integer' ),
				'previous_status' => array( 'type' => 'string' ),
				'new_status'      => array( 'type' => 'string' ),
				'updated'         => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'order_id', 'previous_status', 'new_status', 'updated' ),
		);
	}
}
