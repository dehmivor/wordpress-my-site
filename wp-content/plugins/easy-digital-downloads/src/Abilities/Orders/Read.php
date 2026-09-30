<?php
/**
 * Ability to read EDD orders.
 *
 * @package     EDD\Abilities\Orders
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Orders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Pagination;
use EDD\Abilities\ReadAbility;
use EDD\Abilities\Traits\FormatsDates;

/**
 * Reads a single order or a filtered list of orders.
 *
 * @since 3.7.1
 */
class Read extends ReadAbility {

	use FormatsDates;

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function read_data( array $input ) {
		if ( ! empty( $input['id'] ) ) {
			$order = edd_get_order( absint( $input['id'] ) );
			if ( ! $order ) {
				return new \WP_Error(
					'edd_ability_order_not_found',
					__( 'No order exists with that ID.', 'easy-digital-downloads' ),
					array( 'status' => 404 )
				);
			}

			return Pagination::envelope( 'orders', array( self::format_order( $order, true ) ), 1, 1, 0 );
		}

		$pagination = Pagination::parse( $input );
		$query_args = $this->build_query_args( $input );
		if ( is_wp_error( $query_args ) ) {
			return $query_args;
		}

		$orders = edd_get_orders(
			array_merge(
				$query_args,
				array(
					'number' => $pagination['limit'],
					'offset' => $pagination['offset'],
				)
			)
		);
		$total  = edd_count_orders( $query_args );

		$formatted = array();
		foreach ( $orders as $order ) {
			$formatted[] = self::format_order( $order );
		}

		return Pagination::envelope( 'orders', $formatted, $total, $pagination['limit'], $pagination['offset'] );
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'order-read';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Read Orders', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Look up a single order by ID (including its items), or list orders filtered by status, customer, product, email, or date range. Lists contain sales unless the type is set to refund; a single-order lookup returns either, identified by its type.', 'easy-digital-downloads' );
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
			'properties'           => array_merge(
				array(
					'id'          => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Order ID for a single-order lookup. When provided, all other filters are ignored.', 'easy-digital-downloads' ),
					),
					'status'      => array(
						'type'        => 'string',
						'enum'        => edd_get_payment_status_keys(),
						'description' => __( 'Filter by order status.', 'easy-digital-downloads' ),
					),
					'type'        => array(
						'type'        => 'string',
						'enum'        => array_keys( edd_get_order_types() ),
						'default'     => 'sale',
						'description' => __( 'The type of order record to list.', 'easy-digital-downloads' ),
					),
					'customer_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Filter by customer ID.', 'easy-digital-downloads' ),
					),
					'product_id'  => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Filter to orders containing this product (download) ID.', 'easy-digital-downloads' ),
					),
					'email'       => array(
						'type'        => 'string',
						'format'      => 'email',
						'description' => __( 'Filter by the email address on the order.', 'easy-digital-downloads' ),
					),
					'date_start'  => array(
						'type'        => 'string',
						'format'      => 'date',
						'pattern'     => '^\d{4}-\d{2}-\d{2}$',
						'description' => __( 'Include orders created on or after this date (YYYY-MM-DD, store timezone).', 'easy-digital-downloads' ),
					),
					'date_end'    => array(
						'type'        => 'string',
						'format'      => 'date',
						'pattern'     => '^\d{4}-\d{2}-\d{2}$',
						'description' => __( 'Include orders created on or before this date (YYYY-MM-DD, store timezone).', 'easy-digital-downloads' ),
					),
				),
				Pagination::input_properties()
			),
			'additionalProperties' => false,
			'default'              => array(),
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
		return Pagination::output_schema( 'orders', self::get_order_schema() );
	}

	/**
	 * Gets the JSON Schema describing one order.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	private static function get_order_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'             => array( 'type' => 'integer' ),
				'order_number'   => array( 'type' => 'string' ),
				'status'         => array( 'type' => 'string' ),
				'type'           => array(
					'type'        => 'string',
					'description' => __( 'The record type: sale or refund.', 'easy-digital-downloads' ),
				),
				'mode'           => array( 'type' => 'string' ),
				'date_created'   => array( 'type' => 'string' ),
				'date_completed' => array( 'type' => array( 'string', 'null' ) ),
				'email'          => array(
					'type'        => 'string',
					'description' => __( 'The email address on the order. Included only when the current user can view sensitive shop data.', 'easy-digital-downloads' ),
				),
				'customer_id'    => array( 'type' => 'integer' ),
				'user_id'        => array( 'type' => 'integer' ),
				'gateway'        => array( 'type' => 'string' ),
				'currency'       => array( 'type' => 'string' ),
				'subtotal'       => array( 'type' => 'number' ),
				'discount'       => array( 'type' => 'number' ),
				'tax'            => array( 'type' => 'number' ),
				'total'          => array( 'type' => 'number' ),
				'items'          => array(
					'type'        => 'array',
					'description' => __( 'Order line items. Included on single-order lookups only.', 'easy-digital-downloads' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'product_id'   => array( 'type' => 'integer' ),
							'product_name' => array( 'type' => 'string' ),
							'price_id'     => array( 'type' => array( 'integer', 'null' ) ),
							'quantity'     => array( 'type' => 'integer' ),
							'subtotal'     => array( 'type' => 'number' ),
							'discount'     => array( 'type' => 'number' ),
							'tax'          => array( 'type' => 'number' ),
							'total'        => array( 'type' => 'number' ),
							'status'       => array( 'type' => 'string' ),
						),
					),
				),
			),
			'required'   => array( 'id', 'status', 'total', 'currency' ),
		);
	}

	/**
	 * Formats an order for ability output.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD\Orders\Order $order         The order object.
	 * @param bool              $include_items Whether to include line items.
	 * @return array
	 */
	private static function format_order( $order, bool $include_items = false ): array {
		$formatted = array(
			'id'             => (int) $order->id,
			'order_number'   => (string) $order->get_number(),
			'status'         => (string) $order->status,
			'type'           => (string) $order->type,
			'mode'           => (string) $order->mode,
			'date_created'   => (string) $order->date_created,
			'date_completed' => $order->date_completed ? (string) $order->date_completed : null,
			'customer_id'    => (int) $order->customer_id,
			'user_id'        => (int) $order->user_id,
			'gateway'        => (string) $order->gateway,
			'currency'       => (string) $order->currency,
			'subtotal'       => floatval( $order->subtotal ),
			'discount'       => floatval( $order->discount ),
			'tax'            => floatval( $order->tax ),
			'total'          => floatval( $order->total ),
		);

		// The order email is customer PII; customer-read gates the same data on
		// this capability, and EDD's own API gates its customers endpoint on it.
		if ( current_user_can( 'view_shop_sensitive_data' ) ) {
			$formatted['email'] = (string) $order->email;
		}

		if ( $include_items ) {
			$items = array();
			foreach ( $order->get_items() as $item ) {
				$items[] = array(
					'product_id'   => (int) $item->product_id,
					'product_name' => (string) $item->product_name,
					'price_id'     => is_numeric( $item->price_id ) ? (int) $item->price_id : null,
					'quantity'     => (int) $item->quantity,
					'subtotal'     => floatval( $item->subtotal ),
					'discount'     => floatval( $item->discount ),
					'tax'          => floatval( $item->tax ),
					'total'        => floatval( $item->total ),
					'status'       => (string) $item->status,
				);
			}

			$formatted['items'] = $items;
		}

		return $formatted;
	}

	/**
	 * Builds the order query arguments from the ability input.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error The query arguments, or an error naming a bad date input.
	 */
	private function build_query_args( array $input ) {
		$args = array(
			'type' => ! empty( $input['type'] ) ? sanitize_key( $input['type'] ) : 'sale',
		);

		if ( ! empty( $input['status'] ) ) {
			$args['status'] = sanitize_key( $input['status'] );
		}

		if ( ! empty( $input['customer_id'] ) ) {
			$args['customer_id'] = absint( $input['customer_id'] );
		}

		if ( ! empty( $input['product_id'] ) ) {
			$args['product_id'] = absint( $input['product_id'] );
		}

		if ( ! empty( $input['email'] ) ) {
			$args['email'] = sanitize_email( $input['email'] );
		}

		$date_query = $this->build_date_query( $input );
		if ( is_wp_error( $date_query ) ) {
			return $date_query;
		}
		if ( ! empty( $date_query ) ) {
			$args['date_created_query'] = $date_query;
		}

		return $args;
	}
}
