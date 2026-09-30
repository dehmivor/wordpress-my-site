<?php
/**
 * Shared discount formatting for EDD abilities.
 *
 * @package     EDD\Abilities\Traits
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Traits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Formats EDD discounts for ability output.
 *
 * @since 3.7.1
 */
trait FormatsDiscounts {

	/**
	 * Gets the JSON Schema describing one discount.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected static function get_discount_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'                   => array( 'type' => 'integer' ),
				'name'                 => array( 'type' => 'string' ),
				'code'                 => array( 'type' => 'string' ),
				'status'               => array( 'type' => 'string' ),
				'type'                 => array(
					'type'        => 'string',
					'description' => __( 'The discount type: percent or flat.', 'easy-digital-downloads' ),
				),
				'amount'               => array( 'type' => 'number' ),
				'scope'                => array(
					'type'        => 'string',
					'enum'        => self::get_scope_values(),
					'description' => __( 'Whether the discount applies to the entire purchase (global) or only to the selected products (not_global).', 'easy-digital-downloads' ),
				),
				'use_count'            => array( 'type' => 'integer' ),
				'max_uses'             => array(
					'type'        => array( 'integer', 'null' ),
					'description' => __( 'Maximum number of uses, or null when unlimited.', 'easy-digital-downloads' ),
				),
				'is_maxed_out'         => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the discount has reached its usage limit and can no longer be applied.', 'easy-digital-downloads' ),
				),
				'min_charge_amount'    => array( 'type' => 'number' ),
				'once_per_customer'    => array( 'type' => 'boolean' ),
				'start_date'           => array(
					'type'        => array( 'string', 'null' ),
					'description' => __( 'The stored start datetime, in UTC.', 'easy-digital-downloads' ),
				),
				'end_date'             => array(
					'type'        => array( 'string', 'null' ),
					'description' => __( 'The stored end datetime, in UTC.', 'easy-digital-downloads' ),
				),
				'product_requirements' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'excluded_products'    => array(
					'type'  => 'array',
					'items' => array( 'type' => 'integer' ),
				),
				'product_condition'    => array( 'type' => array( 'string', 'null' ) ),
			),
			'required'   => array( 'id', 'code', 'status', 'type', 'amount' ),
		);
	}

	/**
	 * Formats a discount for ability output.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD_Discount $discount The discount object.
	 * @return array
	 */
	protected static function format_discount( $discount ): array {
		$max_uses = $discount->get_max_uses();

		return array(
			'id'                   => (int) $discount->id,
			'name'                 => (string) $discount->name,
			'code'                 => (string) $discount->code,
			'status'               => (string) $discount->status,
			'type'                 => (string) $discount->amount_type,
			'amount'               => floatval( $discount->amount ),
			'scope'                => self::format_scope( $discount->scope ),
			'use_count'            => $discount->get_uses(),
			'max_uses'             => $max_uses ? $max_uses : null,
			// False suppresses the customer-facing session error the check sets by default.
			'is_maxed_out'         => $discount->is_maxed_out( false ),
			'min_charge_amount'    => floatval( $discount->min_charge_amount ),
			'once_per_customer'    => (bool) $discount->once_per_customer,
			'start_date'           => ! empty( $discount->start_date ) ? (string) $discount->start_date : null,
			'end_date'             => ! empty( $discount->end_date ) ? (string) $discount->end_date : null,
			'product_requirements' => array_values( array_map( 'absint', (array) $discount->product_reqs ) ),
			'excluded_products'    => array_values( array_map( 'absint', (array) $discount->excluded_products ) ),
			'product_condition'    => ! empty( $discount->product_condition ) ? (string) $discount->product_condition : null,
		);
	}

	/**
	 * Gets the two values a discount scope may take.
	 *
	 * @since 3.7.1
	 *
	 * @return string[]
	 */
	protected static function get_scope_values(): array {
		return array( 'global', 'not_global' );
	}

	/**
	 * Formats a stored discount scope as one of the two values the schema declares.
	 *
	 * The column defaults to an empty string, so a discount saved without the
	 * scope radio carries neither value. `edd_is_discount_not_global()` reads
	 * anything but `not_global` as applying to the whole order.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $scope The stored scope.
	 * @return string Either `global` or `not_global`.
	 */
	private static function format_scope( $scope ): string {
		return 'not_global' === $scope ? 'not_global' : 'global';
	}
}
