<?php
/**
 * Ability to create an EDD discount.
 *
 * @package     EDD\Abilities\Discounts
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Discounts;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Traits\FormatsDates;
use EDD\Abilities\Traits\FormatsDiscounts;
use EDD\Abilities\WriteAbility;

/**
 * Creates a discount code, mirroring the admin "Add Discount" flow.
 *
 * @since 3.7.1
 */
class Create extends WriteAbility {

	use FormatsDates;
	use FormatsDiscounts;

	/**
	 * Writes the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function write_data( array $input ) {
		$code = sanitize_text_field( $input['code'] );

		if ( edd_get_discount_by_code( $code ) ) {
			return new \WP_Error(
				'edd_ability_discount_code_exists',
				__( 'A discount already exists with that code.', 'easy-digital-downloads' ),
				array( 'status' => 409 )
			);
		}

		$type   = sanitize_key( $input['type'] );
		$amount = edd_sanitize_amount( $input['amount'] );
		if ( 'percent' === $type && 100 < (float) $amount ) {
			return new \WP_Error(
				'edd_ability_invalid_amount',
				__( 'Percentage discounts cannot exceed 100.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		$args = array(
			'name'              => sanitize_text_field( $input['name'] ),
			'code'              => $code,
			'status'            => 'active' === ( $input['status'] ?? '' ) ? 'active' : 'inactive',
			'amount'            => $amount,
			'amount_type'       => $type,
			'scope'             => ! empty( $input['scope'] ) ? sanitize_key( $input['scope'] ) : 'global',
			'max_uses'          => ! empty( $input['max_uses'] ) ? absint( $input['max_uses'] ) : 0,
			'min_charge_amount' => ! empty( $input['min_charge_amount'] ) ? edd_sanitize_amount( $input['min_charge_amount'] ) : 0,
			'once_per_customer' => ! empty( $input['once_per_customer'] ) ? 1 : 0,
		);

		if ( ! empty( $input['start_date'] ) ) {
			$start_date = $this->format_date_input( 'start_date', $input['start_date'] );
			if ( is_wp_error( $start_date ) ) {
				return $start_date;
			}

			$args['start_date'] = $start_date;
		}

		if ( ! empty( $input['end_date'] ) ) {
			$end_date = $this->format_date_input( 'end_date', $input['end_date'], true );
			if ( is_wp_error( $end_date ) ) {
				return $end_date;
			}

			$args['end_date'] = $end_date;
		}

		if ( ! empty( $input['product_requirements'] ) ) {
			$args['product_reqs'] = array_map( 'absint', $input['product_requirements'] );
		}

		if ( ! empty( $input['excluded_products'] ) ) {
			$args['excluded_products'] = array_map( 'absint', $input['excluded_products'] );
		}

		if ( ! empty( $input['product_condition'] ) ) {
			$args['product_condition'] = sanitize_key( $input['product_condition'] );
		}

		$discount_id = edd_add_discount( $args );
		if ( empty( $discount_id ) ) {
			return new \WP_Error(
				'edd_ability_discount_not_created',
				__( 'The discount could not be created.', 'easy-digital-downloads' ),
				array( 'status' => 500 )
			);
		}

		$discount = edd_get_discount( $discount_id );
		if ( empty( $discount ) ) {
			return $this->saved_but_unreadable( (int) $discount_id );
		}

		return self::format_discount( $discount );
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'discount-create';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Create Discount', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Create a new discount code. Supports percentage or flat amounts, start and end dates, usage limits, a minimum order amount, and product requirements or exclusions. New discounts are inactive unless the status says otherwise, so a code can be reviewed before customers can use it.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'manage_shop_discounts';
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
				'code'                 => array(
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 100,
					'description' => __( 'The discount code customers enter at checkout. Must be unique.', 'easy-digital-downloads' ),
				),
				'name'                 => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => __( 'The internal name for the discount.', 'easy-digital-downloads' ),
				),
				'amount'               => array(
					'type'             => 'number',
					'exclusiveMinimum' => 0,
					'description'      => __( 'The discount amount: a percentage (max 100) or a flat amount, depending on the type.', 'easy-digital-downloads' ),
				),
				'type'                 => array(
					'type'        => 'string',
					'enum'        => array( 'percent', 'flat' ),
					'description' => __( 'The discount type: a percentage or a flat amount.', 'easy-digital-downloads' ),
				),
				'status'               => array(
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive' ),
					'default'     => 'inactive',
					'description' => __( 'Whether customers can use the code straight away. Defaults to inactive.', 'easy-digital-downloads' ),
				),
				'start_date'           => array(
					'type'        => 'string',
					'format'      => 'date',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
					'description' => __( 'The date the discount becomes usable (YYYY-MM-DD, store timezone).', 'easy-digital-downloads' ),
				),
				'end_date'             => array(
					'type'        => 'string',
					'format'      => 'date',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
					'description' => __( 'The last day the discount is usable (YYYY-MM-DD, store timezone). Expires at the end of that day.', 'easy-digital-downloads' ),
				),
				'max_uses'             => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'Maximum number of times the discount may be used. Omit for unlimited.', 'easy-digital-downloads' ),
				),
				'min_charge_amount'    => array(
					'type'        => 'number',
					'minimum'     => 0,
					'description' => __( 'Minimum order amount required to use the discount.', 'easy-digital-downloads' ),
				),
				'once_per_customer'    => array(
					'type'        => 'boolean',
					'description' => __( 'Whether each customer may only use the discount once.', 'easy-digital-downloads' ),
				),
				'product_requirements' => array(
					'type'        => 'array',
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'description' => __( 'Product (download) IDs required in the cart for the discount to apply.', 'easy-digital-downloads' ),
				),
				'excluded_products'    => array(
					'type'        => 'array',
					'items'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'description' => __( 'Product (download) IDs the discount never applies to.', 'easy-digital-downloads' ),
				),
				'product_condition'    => array(
					'type'        => 'string',
					'enum'        => array( 'all', 'any' ),
					'description' => __( 'Whether all required products, or any one of them, must be in the cart.', 'easy-digital-downloads' ),
				),
				'scope'                => array(
					'type'        => 'string',
					'enum'        => self::get_scope_values(),
					'description' => __( 'Whether the discount applies to the entire purchase (global) or only to the selected products (not_global).', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'code', 'name', 'amount', 'type' ),
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
		return self::get_discount_schema();
	}
}
