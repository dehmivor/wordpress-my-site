<?php
/**
 * Ability to read EDD tax rates.
 *
 * @package     EDD\Abilities\Store
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Store;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Pagination;
use EDD\Abilities\ReadAbility;
use EDD\Database\Queries\TaxRate;

/**
 * Reads the store tax configuration and rates.
 *
 * @since 3.7.1
 */
class TaxRates extends ReadAbility {

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function read_data( array $input ) {
		$pagination = Pagination::parse( $input );
		$query_args = array();
		if ( ! empty( $input['country'] ) ) {
			$query_args['country'] = strtoupper( sanitize_text_field( $input['country'] ) );
		}

		// A store reads its live rates far more often than its retired ones.
		$status = ! empty( $input['status'] ) ? sanitize_key( $input['status'] ) : 'active';
		if ( 'all' !== $status ) {
			$query_args['status'] = $status;
		}

		$rates = array();
		$found = edd_get_tax_rates(
			array_merge(
				$query_args,
				array(
					'number' => $pagination['limit'],
					'offset' => $pagination['offset'],
				)
			),
			OBJECT
		);

		foreach ( $found as $rate ) {
			$rates[] = array(
				'id'      => (int) $rate->id,
				'country' => (string) $rate->country,
				'region'  => (string) $rate->state,
				'rate'    => (float) $rate->amount,
				'status'  => (string) $rate->status,
				'scope'   => (string) $rate->scope,
			);
		}

		$count_query = new TaxRate( array_merge( $query_args, array( 'count' => true ) ) );

		return array_merge(
			array( 'enabled' => (bool) edd_use_taxes() ),
			Pagination::envelope( 'rates', $rates, absint( $count_query->found_items ), $pagination['limit'], $pagination['offset'] )
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
		return 'tax-rates';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Tax Rates', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List the store tax rates, along with whether taxes are enabled. Active rates are returned unless the status says otherwise, and the list can be filtered by country.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'manage_shop_settings';
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
					'country' => array(
						'type'        => 'string',
						'maxLength'   => 2,
						'description' => __( 'Filter by country, as an uppercase ISO 3166-1 alpha-2 code (e.g. US).', 'easy-digital-downloads' ),
					),
					'status'  => array(
						'type'        => 'string',
						'enum'        => array( 'active', 'inactive', 'all' ),
						'default'     => 'active',
						'description' => __( 'Filter by tax rate status, or all to include retired rates.', 'easy-digital-downloads' ),
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
		$schema = Pagination::output_schema( 'rates', self::get_rate_schema() );

		$schema['properties']['enabled'] = array(
			'type'        => 'boolean',
			'description' => __( 'Whether taxes are enabled for the store.', 'easy-digital-downloads' ),
		);
		$schema['required'][]            = 'enabled';

		return $schema;
	}

	/**
	 * Gets the JSON Schema describing one tax rate.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	private static function get_rate_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'      => array( 'type' => 'integer' ),
				'country' => array( 'type' => 'string' ),
				'region'  => array( 'type' => 'string' ),
				'rate'    => array(
					'type'        => 'number',
					'description' => __( 'The tax rate percentage.', 'easy-digital-downloads' ),
				),
				'status'  => array( 'type' => 'string' ),
				'scope'   => array( 'type' => 'string' ),
			),
			'required'   => array( 'id', 'country', 'region', 'rate', 'status', 'scope' ),
		);
	}
}
