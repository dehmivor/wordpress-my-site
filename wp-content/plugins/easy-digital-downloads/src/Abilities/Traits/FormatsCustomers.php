<?php
/**
 * Shared customer formatting for EDD abilities.
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
 * Formats EDD customers for ability output.
 *
 * @since 3.7.1
 */
trait FormatsCustomers {

	/**
	 * Gets the JSON Schema describing one customer.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected static function get_customer_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'             => array( 'type' => 'integer' ),
				'name'           => array( 'type' => 'string' ),
				'user_id'        => array(
					'type'        => 'integer',
					'description' => __( 'The linked WordPress user ID. Zero when the customer has no user account.', 'easy-digital-downloads' ),
				),
				'status'         => array( 'type' => 'string' ),
				'purchase_count' => array( 'type' => 'integer' ),
				'purchase_value' => array( 'type' => 'number' ),
				'date_created'   => array( 'type' => 'string' ),
				'email'          => array(
					'type'        => 'string',
					'description' => __( 'The primary email address. Included only when the current user can view sensitive shop data.', 'easy-digital-downloads' ),
				),
				'emails'         => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => __( 'All email addresses on file for the customer. Included only on single-customer lookups (list results omit it), and only when the current user can view sensitive shop data.', 'easy-digital-downloads' ),
				),
				'address'        => array(
					'type'        => array( 'object', 'null' ),
					'description' => __( 'The primary address on file. Included only on single-customer lookups (list results omit it), and only when the current user can view sensitive shop data. Null when the customer has no address.', 'easy-digital-downloads' ),
					'properties'  => array(
						'name'        => array( 'type' => 'string' ),
						'address'     => array( 'type' => 'string' ),
						'address2'    => array( 'type' => 'string' ),
						'city'        => array( 'type' => 'string' ),
						'region'      => array( 'type' => 'string' ),
						'postal_code' => array( 'type' => 'string' ),
						'country'     => array( 'type' => 'string' ),
						'is_primary'  => array( 'type' => 'boolean' ),
					),
				),
			),
			'required'   => array( 'id', 'name', 'status', 'purchase_count', 'purchase_value' ),
		);
	}

	/**
	 * Formats a customer for ability output.
	 *
	 * Email addresses and the customer address are included only when the
	 * current user can view sensitive shop data.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD_Customer $customer        The customer object.
	 * @param bool          $include_address Whether to include the primary address.
	 * @return array
	 */
	protected static function format_customer( $customer, bool $include_address = false ): array {
		$formatted = array(
			'id'             => (int) $customer->id,
			'name'           => (string) $customer->name,
			'user_id'        => (int) $customer->user_id,
			'status'         => (string) $customer->status,
			'purchase_count' => (int) $customer->purchase_count,
			'purchase_value' => floatval( $customer->purchase_value ),
			'date_created'   => (string) $customer->date_created,
		);

		if ( current_user_can( 'view_shop_sensitive_data' ) ) {
			$formatted['email'] = (string) $customer->email;

			// Additional emails and the address require extra queries, so
			// they are loaded on single-customer lookups only.
			if ( $include_address ) {
				$formatted['emails']  = array_map( 'strval', array_values( (array) $customer->emails ) );
				$formatted['address'] = self::get_primary_address( $customer );
			}
		}

		return $formatted;
	}

	/**
	 * Gets the primary address for a customer, formatted for ability output.
	 *
	 * Prefers the address flagged as primary, falling back to any other
	 * address on file.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD_Customer $customer The customer object.
	 * @return array|null The formatted address, or null when none exists.
	 */
	private static function get_primary_address( $customer ): ?array {
		$address = $customer->get_address();

		if ( is_null( $address ) ) {
			// Only billing addresses are flagged primary, so a shipping-only customer has none.
			$addresses = $customer->get_addresses();
			$address   = ! empty( $addresses ) ? reset( $addresses ) : null;
		}

		if ( empty( $address ) ) {
			return null;
		}

		return array(
			'name'        => (string) $address->name,
			'address'     => (string) $address->address,
			'address2'    => (string) $address->address2,
			'city'        => (string) $address->city,
			'region'      => (string) $address->region,
			'postal_code' => (string) $address->postal_code,
			'country'     => (string) $address->country,
			'is_primary'  => (bool) $address->is_primary,
		);
	}
}
