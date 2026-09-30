<?php
/**
 * Ability to update an EDD discount.
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
 * Updates an existing discount.
 *
 * Only the fields provided in the input are changed; omitted fields are left
 * as they are.
 *
 * @since 3.7.1
 */
class Update extends WriteAbility {

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
		$discount = edd_get_discount( absint( $input['discount_id'] ) );
		if ( ! $discount ) {
			return new \WP_Error(
				'edd_ability_discount_not_found',
				__( 'No discount exists with that ID.', 'easy-digital-downloads' ),
				array( 'status' => 404 )
			);
		}

		$data = array();

		if ( isset( $input['amount'] ) ) {
			$amount = edd_sanitize_amount( $input['amount'] );
			if ( 'percent' === $discount->amount_type && 100 < (float) $amount ) {
				return new \WP_Error(
					'edd_ability_invalid_amount',
					__( 'Percentage discounts cannot exceed 100.', 'easy-digital-downloads' ),
					array( 'status' => 400 )
				);
			}

			$data['amount'] = $amount;
		}

		if ( isset( $input['name'] ) ) {
			$data['name'] = sanitize_text_field( $input['name'] );
		}

		if ( isset( $input['status'] ) ) {
			$data['status'] = sanitize_key( $input['status'] );
		}

		if ( isset( $input['max_uses'] ) ) {
			$data['max_uses'] = absint( $input['max_uses'] );
		}

		if ( isset( $input['min_charge_amount'] ) ) {
			$data['min_charge_amount'] = edd_sanitize_amount( $input['min_charge_amount'] );
		}

		if ( isset( $input['once_per_customer'] ) ) {
			$data['once_per_customer'] = ! empty( $input['once_per_customer'] ) ? 1 : 0;
		}

		if ( ! empty( $input['start_date'] ) ) {
			$start_date = $this->format_date_input( 'start_date', $input['start_date'] );
			if ( is_wp_error( $start_date ) ) {
				return $start_date;
			}

			$data['start_date'] = $start_date;
		}

		if ( ! empty( $input['end_date'] ) ) {
			$end_date = $this->format_date_input( 'end_date', $input['end_date'], true );
			if ( is_wp_error( $end_date ) ) {
				return $end_date;
			}

			$data['end_date'] = $end_date;
		}

		if ( empty( $data ) ) {
			return new \WP_Error(
				'edd_ability_no_updates',
				__( 'Provide at least one field to update.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		if ( false === edd_update_discount( $discount->id, $data ) ) {
			return new \WP_Error(
				'edd_ability_discount_not_updated',
				__( 'The discount could not be updated.', 'easy-digital-downloads' ),
				array( 'status' => 500 )
			);
		}

		$updated = edd_get_discount( $discount->id );
		if ( empty( $updated ) ) {
			return $this->saved_but_unreadable( (int) $discount->id );
		}

		return self::format_discount( $updated );
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'discount-update';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Update Discount', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Update an existing discount. Only the provided fields are changed: name, amount, status, usage limit, dates, minimum order amount, or once-per-customer setting.', 'easy-digital-downloads' );
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
				'discount_id'       => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The ID of the discount to update.', 'easy-digital-downloads' ),
				),
				'name'              => array(
					'type'        => 'string',
					'minLength'   => 1,
					'description' => __( 'The internal name for the discount.', 'easy-digital-downloads' ),
				),
				'amount'            => array(
					'type'             => 'number',
					'exclusiveMinimum' => 0,
					'description'      => __( 'The discount amount. For percentage discounts this cannot exceed 100.', 'easy-digital-downloads' ),
				),
				'status'            => array(
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive', 'archived' ),
					'description' => __( 'The discount status.', 'easy-digital-downloads' ),
				),
				'max_uses'          => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => __( 'Maximum number of times the discount may be used. Use 0 to clear the limit.', 'easy-digital-downloads' ),
				),
				'start_date'        => array(
					'type'        => 'string',
					'format'      => 'date',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
					'description' => __( 'The date the discount becomes usable (YYYY-MM-DD, store timezone).', 'easy-digital-downloads' ),
				),
				'end_date'          => array(
					'type'        => 'string',
					'format'      => 'date',
					'pattern'     => '^\d{4}-\d{2}-\d{2}$',
					'description' => __( 'The last day the discount is usable (YYYY-MM-DD, store timezone). Expires at the end of that day.', 'easy-digital-downloads' ),
				),
				'min_charge_amount' => array(
					'type'        => 'number',
					'minimum'     => 0,
					'description' => __( 'Minimum order amount required to use the discount.', 'easy-digital-downloads' ),
				),
				'once_per_customer' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether each customer may only use the discount once.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'discount_id' ),
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
