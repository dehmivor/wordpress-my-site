<?php
/**
 * Ability to read EDD customers.
 *
 * @package     EDD\Abilities\Customers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Customers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Pagination;
use EDD\Abilities\ReadAbility;
use EDD\Abilities\Traits\FormatsCustomers;

/**
 * Reads a single customer or a filtered list of customers.
 *
 * Email addresses and address data are personally identifiable information,
 * so they are included only when the current user can view sensitive shop data.
 *
 * @since 3.7.1
 */
class Read extends ReadAbility {

	use FormatsCustomers;

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
			return $this->lookup_single( edd_get_customer( absint( $input['id'] ) ) );
		}

		if ( ! empty( $input['email'] ) ) {
			$email    = sanitize_email( $input['email'] );
			$customer = ! empty( $email ) ? edd_get_customer_by( 'email', $email ) : false;

			return $this->lookup_single( $customer );
		}

		$pagination = Pagination::parse( $input );
		$query_args = array();

		if ( ! empty( $input['search'] ) ) {
			$query_args['search'] = sanitize_text_field( $input['search'] );
		}

		$customers = edd_get_customers(
			array_merge(
				$query_args,
				array(
					'number' => $pagination['limit'],
					'offset' => $pagination['offset'],
				)
			)
		);
		$total     = edd_count_customers( $query_args );

		$formatted = array();
		foreach ( $customers as $customer ) {
			$formatted[] = self::format_customer( $customer );
		}

		return Pagination::envelope( 'customers', $formatted, $total, $pagination['limit'], $pagination['offset'] );
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'customer-read';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Read Customers', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Look up a single customer by ID or email address, or search and list customers. Email addresses and address data are included only for users who can view sensitive shop data.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return edd_get_view_customers_role();
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
						'description' => __( 'Customer ID for a single-customer lookup. When provided, all other filters are ignored.', 'easy-digital-downloads' ),
					),
					'email'  => array(
						'type'        => 'string',
						'format'      => 'email',
						'description' => __( 'Email address for a single-customer lookup.', 'easy-digital-downloads' ),
					),
					'search' => array(
						'type'        => 'string',
						'description' => __( 'Search customers by name or email address.', 'easy-digital-downloads' ),
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
		return Pagination::output_schema( 'customers', self::get_customer_schema() );
	}

	/**
	 * Builds the single-customer response envelope.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD_Customer|false $customer The customer, or false when not found.
	 * @return array|\WP_Error
	 */
	private function lookup_single( $customer ) {
		if ( ! $customer ) {
			return new \WP_Error(
				'edd_ability_customer_not_found',
				__( 'No customer exists with that ID or email address.', 'easy-digital-downloads' ),
				array( 'status' => 404 )
			);
		}

		return Pagination::envelope( 'customers', array( self::format_customer( $customer, true ) ), 1, 1, 0 );
	}
}
