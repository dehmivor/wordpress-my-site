<?php
/**
 * Ability to read EDD discounts.
 *
 * @package     EDD\Abilities\Discounts
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Discounts;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Pagination;
use EDD\Abilities\ReadAbility;
use EDD\Abilities\Traits\FormatsDiscounts;

/**
 * Reads a single discount or a filtered list of discounts.
 *
 * @since 3.7.1
 */
class Read extends ReadAbility {

	use FormatsDiscounts;

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function read_data( array $input ) {
		if ( ! empty( $input['id'] ) || ! empty( $input['code'] ) ) {
			$discount = ! empty( $input['id'] )
				? edd_get_discount( absint( $input['id'] ) )
				: edd_get_discount_by_code( sanitize_text_field( $input['code'] ) );

			if ( ! $discount ) {
				return new \WP_Error(
					'edd_ability_discount_not_found',
					__( 'No discount exists with that ID or code.', 'easy-digital-downloads' ),
					array( 'status' => 404 )
				);
			}

			return Pagination::envelope( 'discounts', array( self::format_discount( $discount ) ), 1, 1, 0 );
		}

		$pagination = Pagination::parse( $input );
		$query_args = $this->build_query_args( $input );

		$discounts = edd_get_discounts(
			array_merge(
				$query_args,
				array(
					'number' => $pagination['limit'],
					'offset' => $pagination['offset'],
				)
			)
		);
		$total     = edd_get_discount_count( $query_args );

		$formatted = array();
		foreach ( $discounts as $discount ) {
			$formatted[] = self::format_discount( $discount );
		}

		return Pagination::envelope( 'discounts', $formatted, $total, $pagination['limit'], $pagination['offset'] );
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'discount-read';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Read Discounts', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Look up a single discount by ID or code, or list discounts filtered by status, type, or a search term. Archived discounts are excluded unless a status filter is provided.', 'easy-digital-downloads' );
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
			'properties'           => array_merge(
				array(
					'id'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Discount ID for a single-discount lookup. When provided, all other filters are ignored.', 'easy-digital-downloads' ),
					),
					'code'   => array(
						'type'        => 'string',
						'description' => __( 'Discount code for a single-discount lookup.', 'easy-digital-downloads' ),
					),
					'status' => array(
						'type'        => 'string',
						'enum'        => array( 'active', 'inactive', 'expired', 'archived' ),
						'description' => __( 'Filter by discount status.', 'easy-digital-downloads' ),
					),
					'type'   => array(
						'type'        => 'string',
						'enum'        => array( 'percent', 'flat' ),
						'description' => __( 'Filter by discount type: a percentage or a flat amount.', 'easy-digital-downloads' ),
					),
					'search' => array(
						'type'        => 'string',
						'description' => __( 'Search discounts by name or code.', 'easy-digital-downloads' ),
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
		return Pagination::output_schema( 'discounts', self::get_discount_schema() );
	}

	/**
	 * Builds the discount query arguments from the ability input.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array
	 */
	private function build_query_args( array $input ): array {
		$args = array();

		if ( ! empty( $input['status'] ) ) {
			$args['status'] = sanitize_key( $input['status'] );
		}

		if ( ! empty( $input['type'] ) ) {
			$args['amount_type'] = sanitize_key( $input['type'] );
		}

		if ( ! empty( $input['search'] ) ) {
			$args['search'] = sanitize_text_field( $input['search'] );
		}

		// Mirror the edd_get_discounts() default so the count query matches the list query.
		if ( empty( $args['status'] ) && ! isset( $args['search'] ) ) {
			$args['status__not_in'] = array( 'archived' );
		}

		return $args;
	}
}
