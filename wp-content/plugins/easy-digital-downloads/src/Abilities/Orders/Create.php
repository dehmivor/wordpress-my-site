<?php
/**
 * Ability to create an EDD order.
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
 * Creates a manual order, mirroring the admin "Add Order" flow.
 *
 * Prices are read from the products themselves; the ability computes
 * subtotals, applies an optional discount code, inserts the order as pending,
 * then transitions it to the requested status so completion hooks fire.
 *
 * @since 3.7.1
 */
class Create extends WriteAbility {

	/**
	 * Writes the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function write_data( array $input ) {
		// resolve_customer() is the only step here that writes, and it runs last: a
		// refused product, price or discount code must leave no customer behind.
		$items = $this->build_items( $input['items'] );
		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$subtotal = 0.0;
		foreach ( $items as $item ) {
			$subtotal += $item['subtotal'];
		}

		$applied = $this->resolve_discount( $input, $subtotal );
		if ( is_wp_error( $applied ) ) {
			return $applied;
		}

		$discount        = $applied['discount'];
		$discount_amount = $applied['amount'];

		$customer = $this->resolve_customer( $input );
		if ( is_wp_error( $customer ) ) {
			return $customer;
		}

		$status = ! empty( $input['status'] ) ? sanitize_key( $input['status'] ) : 'complete';

		$order_id = edd_add_order(
			array(
				'status'       => 'pending',
				'user_id'      => (int) $customer->user_id,
				'customer_id'  => (int) $customer->id,
				'email'        => $customer->email,
				'gateway'      => ! empty( $input['gateway'] ) ? sanitize_key( $input['gateway'] ) : 'manual',
				'mode'         => edd_is_test_mode() ? 'test' : 'live',
				'currency'     => edd_get_currency(),
				'payment_key'  => edd_generate_order_payment_key( $customer->email ),
				'subtotal'     => $subtotal,
				'discount'     => $discount_amount,
				'tax'          => 0.00,
				'total'        => max( 0, $subtotal - $discount_amount ),
				'order_number' => edd_set_order_number(),
			)
		);

		if ( empty( $order_id ) ) {
			return new \WP_Error(
				'edd_ability_order_not_created',
				__( 'The order could not be created.', 'easy-digital-downloads' ),
				array( 'status' => 500 )
			);
		}

		$customer->attach_payment( $order_id, false );

		$this->add_order_items( $order_id, $items, $subtotal, $discount_amount );

		if ( $discount && $discount_amount > 0 ) {
			edd_add_order_adjustment(
				array(
					'object_id'   => $order_id,
					'object_type' => 'order',
					'type_id'     => (int) $discount->id,
					'type'        => 'discount',
					'description' => $discount->code,
					'subtotal'    => $discount_amount,
					'total'       => $discount_amount,
				)
			);
		}

		// The figures written on insert predate the item and discount rows, so derive them from the rows
		// before the transition reads the total for store earnings and snapshots it.
		$order = $this->read_back( $order_id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$order->recalculate();

		if ( ! empty( $input['note'] ) ) {
			$content = wp_kses( trim( $input['note'] ), edd_get_allowed_tags() );
			if ( ! empty( $content ) ) {
				edd_add_note(
					array(
						'object_id'   => $order_id,
						'object_type' => 'order',
						'user_id'     => get_current_user_id(),
						'content'     => $content,
					)
				);
			}
		}

		/*
		 * Suppress both order emails by the values their send paths read, the way
		 * WP-CLI's order generator does. An omitted send_emails writes nothing, so
		 * the schema's default of true holds: the schema check validates and
		 * sanitizes the input but does not fill in property defaults.
		 *
		 * The write has to land before the status transition: with
		 * edd_use_after_payment_actions filtered false, edd_after_order_actions
		 * fires inline from it. Writing first also wins over the legacy triggers,
		 * which append a row while the send paths read the earliest one.
		 */
		if ( array_key_exists( 'send_emails', $input ) && empty( $input['send_emails'] ) ) {
			edd_update_order_meta( $order_id, '_edd_should_send_order_receipt', false );
			edd_update_order_meta( $order_id, '_edd_should_send_admin_order_notice', false );
		}

		if ( 'pending' !== $status ) {
			edd_update_order_status( $order_id, $status );
		}

		$this->log_note( $order_id, 'order' );

		$order = $this->read_back( $order_id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		return array(
			'order_id'       => (int) $order_id,
			'order_number'   => (string) $order->get_number(),
			'status'         => (string) $order->status,
			'customer_id'    => (int) $customer->id,
			'subtotal'       => floatval( $order->subtotal ),
			'discount'       => floatval( $order->discount ),
			'tax'            => floatval( $order->tax ),
			'tax_calculated' => false,
			'total'          => floatval( $order->total ),
			'currency'       => (string) $order->currency,
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
		return 'order-create';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Create Order', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Create a manual order for a customer. Provide an existing customer ID or an email address (a customer record is created if the email is new). Item prices are read from the products; an optional discount code is applied to the whole order. By default the customer receipt and the store\'s new-sale notification are sent when the order completes; set send_emails to false to record the order silently. No payment is collected, taxes are not calculated or recorded, and product-level discount restrictions are not enforced.', 'easy-digital-downloads' );
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
	 * Declared destructive so clients confirm first: an order adds to store
	 * earnings and to the customer's lifetime value, and emails them by default.
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
				'customer_id'   => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'An existing customer ID. Provide this or an email address.', 'easy-digital-downloads' ),
				),
				'email'         => array(
					'type'        => 'string',
					'format'      => 'email',
					'description' => __( 'The customer email address. A new customer record is created if no customer exists with this email.', 'easy-digital-downloads' ),
				),
				'name'          => array(
					'type'        => 'string',
					'description' => __( 'Customer name, used only when creating a new customer record.', 'easy-digital-downloads' ),
				),
				'items'         => array(
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => 100,
					'description' => __( 'Products to include in the order.', 'easy-digital-downloads' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'product_id' => array(
								'type'    => 'integer',
								'minimum' => 1,
							),
							'price_id'   => array(
								'type'        => 'integer',
								'minimum'     => 0,
								'description' => __( 'The variable price ID. Defaults to the product default when the product has variable pricing.', 'easy-digital-downloads' ),
							),
							'quantity'   => array(
								'type'    => 'integer',
								'minimum' => 1,
								'maximum' => 1000,
								'default' => 1,
							),
						),
						'required'   => array( 'product_id' ),
					),
				),
				'status'        => array(
					'type'        => 'string',
					'enum'        => array( 'complete', 'pending', 'processing' ),
					'default'     => 'complete',
					'description' => __( 'The status for the new order.', 'easy-digital-downloads' ),
				),
				'gateway'       => array(
					'type'        => 'string',
					'enum'        => array_values( array_unique( array_merge( array( 'manual' ), array_keys( edd_get_payment_gateways() ) ) ) ),
					'default'     => 'manual',
					'description' => __( 'The ID of the payment gateway to record on the order. Recording a gateway does not charge anything; manual is correct for orders created without payment.', 'easy-digital-downloads' ),
				),
				'discount_code' => array(
					'type'        => 'string',
					'description' => __( 'An active discount code to apply to the order.', 'easy-digital-downloads' ),
				),
				'note'          => array(
					'type'        => 'string',
					'maxLength'   => 5000,
					'description' => __( 'An internal note to record on the new order.', 'easy-digital-downloads' ),
				),
				'send_emails'   => array(
					'type'        => 'boolean',
					'default'     => true,
					'description' => __( 'Whether to send the order emails when the order completes: the purchase receipt to the customer and the new-sale notification to the store. Set this to false to record the order silently.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'items' ),
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
				'order_id'       => array( 'type' => 'integer' ),
				'order_number'   => array( 'type' => 'string' ),
				'status'         => array( 'type' => 'string' ),
				'customer_id'    => array( 'type' => 'integer' ),
				'subtotal'       => array( 'type' => 'number' ),
				'discount'       => array( 'type' => 'number' ),
				'tax'            => array( 'type' => 'number' ),
				'tax_calculated' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether tax was calculated for this order. The ability does not calculate tax, so this is false; any tax figure comes from an adjustment an extension added while the order was being built.', 'easy-digital-downloads' ),
				),
				'total'          => array( 'type' => 'number' ),
				'currency'       => array( 'type' => 'string' ),
			),
			'required'   => array( 'order_id', 'status', 'customer_id', 'tax', 'tax_calculated', 'total', 'currency' ),
		);
	}

	/**
	 * Resolves the customer for the new order, creating one when needed.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return \EDD_Customer|\WP_Error
	 */
	private function resolve_customer( array $input ) {
		if ( ! empty( $input['customer_id'] ) ) {
			$customer = edd_get_customer( absint( $input['customer_id'] ) );
			if ( ! $customer ) {
				return new \WP_Error(
					'edd_ability_customer_not_found',
					__( 'No customer exists with that ID.', 'easy-digital-downloads' ),
					array( 'status' => 404 )
				);
			}

			return $customer;
		}

		if ( empty( $input['email'] ) ) {
			return new \WP_Error(
				'edd_ability_customer_required',
				__( 'Provide a customer_id or an email address for the order.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		$email    = sanitize_email( $input['email'] );
		$customer = edd_get_customer_by( 'email', $email );
		if ( $customer ) {
			return $customer;
		}

		$new_customer_args = array(
			'email' => $email,
			'name'  => ! empty( $input['name'] ) ? sanitize_text_field( $input['name'] ) : $email,
		);

		$user = get_user_by( 'email', $email );
		if ( $user instanceof \WP_User ) {
			$new_customer_args['user_id'] = $user->ID;
		}

		$customer_id = edd_add_customer( $new_customer_args );
		if ( empty( $customer_id ) ) {
			return new \WP_Error(
				'edd_ability_customer_not_created',
				__( 'A customer record could not be created for that email address.', 'easy-digital-downloads' ),
				array( 'status' => 500 )
			);
		}

		$customer = edd_get_customer( $customer_id );
		if ( empty( $customer ) ) {
			/*
			 * Not saved_but_unreadable(): that warns against retrying, and here
			 * no order was placed. A repeat is safe, because the email lookup
			 * above finds the customer this attempt created.
			 */
			return new \WP_Error(
				'edd_ability_customer_unreadable',
				sprintf(
					/* translators: %d: the ID of the customer record which was created. */
					__( 'A customer record was created as ID %d but could not be read back, so the order was not placed. Try the same request again.', 'easy-digital-downloads' ),
					$customer_id
				),
				array( 'status' => 500 )
			);
		}

		return $customer;
	}

	/**
	 * Resolves the discount for the new order, and the amount it takes off.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input    The validated input.
	 * @param float $subtotal The order subtotal the discount applies to.
	 * @return array|\WP_Error Keys `discount` (null when no code was given) and `amount`.
	 */
	private function resolve_discount( array $input, float $subtotal ) {
		if ( empty( $input['discount_code'] ) ) {
			return array(
				'discount' => null,
				'amount'   => 0.0,
			);
		}

		$discount = edd_get_discount_by_code( sanitize_text_field( $input['discount_code'] ) );
		if ( ! $discount || ! $discount->is_active( false, false ) ) {
			return new \WP_Error(
				'edd_ability_invalid_discount',
				__( 'That discount code does not exist or is not active.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $discount->max_uses ) && (int) $discount->use_count >= (int) $discount->max_uses ) {
			return new \WP_Error(
				'edd_ability_discount_maxed_out',
				__( 'That discount code has reached its maximum number of uses.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $discount->min_charge_amount ) && $subtotal < floatval( $discount->min_charge_amount ) ) {
			return new \WP_Error(
				'edd_ability_discount_minimum_not_met',
				__( 'The order subtotal does not meet the minimum amount required for that discount code.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'discount' => $discount,
			'amount'   => round( $subtotal - $discount->get_discounted_amount( $subtotal ), edd_currency_decimal_filter() ),
		);
	}

	/**
	 * Builds the order item rows from the ability input, reading product prices.
	 *
	 * @since 3.7.1
	 *
	 * @param array $items The items input.
	 * @return array|\WP_Error
	 */
	private function build_items( array $items ) {
		$built = array();

		foreach ( $items as $item ) {
			$product_id = absint( $item['product_id'] );
			$download   = edd_get_download( $product_id );

			if ( ! $download ) {
				return new \WP_Error(
					'edd_ability_product_not_found',
					sprintf(
						/* translators: %d: The product (download) ID that could not be found. */
						__( 'No product exists with ID %d.', 'easy-digital-downloads' ),
						$product_id
					),
					array( 'status' => 404 )
				);
			}

			$quantity = isset( $item['quantity'] ) ? max( 1, absint( $item['quantity'] ) ) : 1;
			$price_id = null;

			if ( $download->has_variable_prices() ) {
				$price_id = isset( $item['price_id'] ) && is_numeric( $item['price_id'] )
					? absint( $item['price_id'] )
					: (int) $download->get_default_price_id();

				$prices = $download->get_prices();
				if ( ! isset( $prices[ $price_id ] ) ) {
					return new \WP_Error(
						'edd_ability_invalid_price_id',
						sprintf(
							/* translators: 1: The price ID, 2: The product (download) ID. */
							__( 'Price ID %1$d does not exist on product %2$d.', 'easy-digital-downloads' ),
							$price_id,
							$product_id
						),
						array( 'status' => 400 )
					);
				}

				$amount = floatval( edd_get_price_option_amount( $product_id, $price_id ) );
			} else {
				$amount = floatval( edd_get_download_price( $product_id ) );
			}

			$built[] = array(
				'product_id'   => $product_id,
				'product_name' => edd_get_download_name( $product_id, $price_id ),
				'price_id'     => $price_id,
				'quantity'     => $quantity,
				'amount'       => $amount,
				'subtotal'     => round( $amount * $quantity, edd_currency_decimal_filter() ),
			);
		}

		return $built;
	}

	/**
	 * Reads the order back, or reports a save which cannot be read.
	 *
	 * @since 3.7.1
	 *
	 * @param int $order_id The order.
	 * @return \EDD\Orders\Order|\WP_Error
	 */
	private function read_back( int $order_id ) {
		$order = edd_get_order( $order_id );

		return empty( $order ) ? $this->saved_but_unreadable( $order_id ) : $order;
	}

	/**
	 * Inserts the order item rows, distributing the discount proportionally.
	 *
	 * The rows go in pending, whatever status the order was asked for, so that
	 * the status transition is what moves them: a row already in its final
	 * status is unchanged, and `edd_update_order_item()` bails before firing
	 * `edd_order_item_updated`, which is what recalculates the product.
	 *
	 * @since 3.7.1
	 *
	 * @param int   $order_id        The new order ID.
	 * @param array $items           The built items.
	 * @param float $subtotal        The order subtotal.
	 * @param float $discount_amount The total discount amount.
	 * @return void
	 */
	private function add_order_items( int $order_id, array $items, float $subtotal, float $discount_amount ): void {
		$remaining  = $discount_amount;
		$last_index = count( $items ) - 1;

		foreach ( $items as $index => $item ) {
			$item_discount = 0.0;
			if ( $discount_amount > 0 && $subtotal > 0 ) {
				// The last item takes the remainder so item rows always sum to the order-level discount.
				$item_discount = ( $index === $last_index )
					? round( $remaining, edd_currency_decimal_filter() )
					: round( $discount_amount * ( $item['subtotal'] / $subtotal ), edd_currency_decimal_filter() );
				$remaining     = round( $remaining - $item_discount, edd_currency_decimal_filter() );
			}

			edd_add_order_item(
				array(
					'order_id'     => $order_id,
					'product_id'   => $item['product_id'],
					'product_name' => $item['product_name'],
					'price_id'     => $item['price_id'],
					'cart_index'   => $index,
					'type'         => 'download',
					'status'       => 'pending',
					'quantity'     => $item['quantity'],
					'amount'       => $item['amount'],
					'subtotal'     => $item['subtotal'],
					'discount'     => $item_discount,
					'tax'          => 0.00,
					'total'        => max( 0, $item['subtotal'] - $item_discount ),
				)
			);
		}
	}
}
