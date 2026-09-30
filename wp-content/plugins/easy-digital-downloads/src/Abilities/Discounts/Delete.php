<?php
/**
 * Ability to delete an EDD discount.
 *
 * @package     EDD\Abilities\Discounts
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Discounts;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\WriteAbility;

/**
 * Deletes an unused discount.
 *
 * Discounts that have been used on orders cannot be deleted; they should be
 * archived instead so order history stays intact.
 *
 * @since 3.7.1
 */
class Delete extends WriteAbility {

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

		if ( 0 < $discount->use_count ) {
			return new \WP_Error(
				'edd_ability_discount_in_use',
				__( 'This discount has been used on orders and cannot be deleted. Set its status to archived instead to retire it while preserving order history.', 'easy-digital-downloads' ),
				array( 'status' => 409 )
			);
		}

		$deleted = edd_delete_discount( $discount->id );

		return array(
			'deleted'     => (bool) $deleted,
			'discount_id' => (int) $discount->id,
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
		return 'discount-delete';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Delete Discount', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Permanently delete a discount that has never been used. Discounts with recorded uses cannot be deleted; set their status to archived instead.', 'easy-digital-downloads' );
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
	 * Gets the behavior annotations.
	 *
	 * Declared destructive: the discount record is removed rather than changed.
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
				'discount_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The ID of the discount to delete.', 'easy-digital-downloads' ),
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
		return array(
			'type'       => 'object',
			'properties' => array(
				'deleted'     => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the discount was deleted.', 'easy-digital-downloads' ),
				),
				'discount_id' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'deleted', 'discount_id' ),
		);
	}
}
