<?php
/**
 * Ability to create an EDD customer.
 *
 * @package     EDD\Abilities\Customers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Customers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Traits\FormatsCustomers;
use EDD\Abilities\WriteAbility;

/**
 * Creates a customer record.
 *
 * @since 3.7.1
 */
class Create extends WriteAbility {

	use FormatsCustomers;

	/**
	 * Writes the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function write_data( array $input ) {
		$email = sanitize_email( $input['email'] );

		// The schema refuses a malformed address, but a filter on sanitize_email can empty a valid one.
		if ( empty( $email ) ) {
			return new \WP_Error(
				'edd_ability_invalid_email',
				__( 'That email address is not valid.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		if ( edd_get_customer_by( 'email', $email ) ) {
			return new \WP_Error(
				'edd_ability_customer_exists',
				__( 'A customer already exists with that email address.', 'easy-digital-downloads' ),
				array( 'status' => 409 )
			);
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
				__( 'The customer could not be created.', 'easy-digital-downloads' ),
				array( 'status' => 500 )
			);
		}

		$this->log_note( $customer_id, 'customer' );

		$customer = edd_get_customer( $customer_id );
		if ( empty( $customer ) ) {
			return $this->saved_but_unreadable( $customer_id );
		}

		// The caller supplied the email, so echo it back regardless of PII gating.
		$formatted          = self::format_customer( $customer );
		$formatted['email'] = (string) $customer->email;

		return $formatted;
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'customer-create';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Create Customer', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Create a new customer record from an email address and optional name. Fails if a customer already exists with that email address.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return edd_get_edit_customers_role();
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
				'email' => array(
					'type'        => 'string',
					'format'      => 'email',
					'description' => __( 'The email address for the new customer.', 'easy-digital-downloads' ),
				),
				'name'  => array(
					'type'        => 'string',
					'maxLength'   => 200,
					'description' => __( 'The customer name. Defaults to the email address.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'email' ),
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
		$schema = self::get_customer_schema();

		// The caller supplied the email, so it is always echoed back.
		$schema['properties']['email'] = array(
			'type'        => 'string',
			'description' => __( 'The primary email address of the new customer.', 'easy-digital-downloads' ),
		);

		return $schema;
	}
}
