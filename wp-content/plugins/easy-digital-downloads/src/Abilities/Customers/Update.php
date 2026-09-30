<?php
/**
 * Ability to update an EDD customer.
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
 * Updates the name, primary email address, or status of a customer.
 *
 * @since 3.7.1
 */
class Update extends WriteAbility {

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
		$customer = edd_get_customer( absint( $input['customer_id'] ) );
		if ( ! $customer ) {
			return new \WP_Error(
				'edd_ability_customer_not_found',
				__( 'No customer exists with that ID.', 'easy-digital-downloads' ),
				array( 'status' => 404 )
			);
		}

		$data          = array();
		$has_field     = false;
		$email_changed = false;

		// Only a value which differs goes into the write, so a request restating
		// what the record already holds reports no change and records no note.
		if ( ! empty( $input['name'] ) ) {
			$has_field = true;
			$name      = sanitize_text_field( $input['name'] );

			if ( $name !== $customer->name ) {
				$data['name'] = $name;
			}
		}

		if ( ! empty( $input['status'] ) ) {
			$has_field = true;
			$status    = sanitize_key( $input['status'] );

			if ( $status !== $customer->status ) {
				$data['status'] = $status;
			}
		}

		if ( ! empty( $input['email'] ) ) {
			$email = sanitize_email( $input['email'] );

			// The schema refuses a malformed address, but a filter on sanitize_email can empty a valid one.
			if ( empty( $email ) ) {
				return new \WP_Error(
					'edd_ability_invalid_email',
					__( 'That email address is not valid.', 'easy-digital-downloads' ),
					array( 'status' => 400 )
				);
			}

			$existing = edd_get_customer_by( 'email', $email );
			if ( $existing && (int) $existing->id !== (int) $customer->id ) {
				return new \WP_Error(
					'edd_ability_email_in_use',
					__( 'That email address already belongs to another customer.', 'easy-digital-downloads' ),
					array( 'status' => 409 )
				);
			}

			/*
			 * EDD_Customer::add_email() also refuses an address held by a
			 * WordPress user, which the lookup above does not cover, so say
			 * which conflict it is rather than failing opaquely.
			 */
			if ( ! in_array( $email, (array) $customer->emails, true ) && $customer->email_exists( $email ) ) {
				return new \WP_Error(
					'edd_ability_email_in_use',
					__( 'That email address is already in use by another customer or a WordPress user account.', 'easy-digital-downloads' ),
					array( 'status' => 409 )
				);
			}

			// Route email changes through the customer object so the email
			// addresses table stays in sync, mirroring the admin flow.
			if ( $email !== $customer->email ) {
				$emails  = (array) $customer->emails;
				$changed = in_array( $email, $emails, true )
					? $customer->set_primary_email( $email )
					: $customer->add_email( $email, true );

				if ( false === $changed ) {
					return new \WP_Error(
						'edd_ability_customer_not_updated',
						__( 'The email address could not be updated.', 'easy-digital-downloads' ),
						array( 'status' => 500 )
					);
				}

				$email_changed = true;
			}

			$has_field = true;
		}

		if ( ! $has_field ) {
			return new \WP_Error(
				'edd_ability_no_updates',
				__( 'Provide at least one field to update: name, email, or status.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $data ) ) {
			$updated = edd_update_customer( $customer->id, $data );
			if ( false === $updated ) {
				return new \WP_Error(
					'edd_ability_customer_not_updated',
					__( 'The customer could not be updated.', 'easy-digital-downloads' ),
					array( 'status' => 500 )
				);
			}
		}

		$was_updated = ! empty( $data ) || $email_changed;

		// A request whose only field already held its requested value changed
		// nothing, so it does not get a note saying an assistant changed it.
		if ( $was_updated ) {
			$this->log_note( (int) $customer->id, 'customer' );
		}

		$updated_customer = edd_get_customer( $customer->id );
		if ( empty( $updated_customer ) ) {
			return $this->saved_but_unreadable( (int) $customer->id );
		}

		$formatted            = self::format_customer( $updated_customer );
		$formatted['updated'] = $was_updated;

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
		return 'customer-update';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Update Customer', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Update the name, primary email address, or status of an existing customer.', 'easy-digital-downloads' );
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
				'customer_id' => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The customer ID.', 'easy-digital-downloads' ),
				),
				'name'        => array(
					'type'        => 'string',
					'maxLength'   => 200,
					'description' => __( 'The new customer name.', 'easy-digital-downloads' ),
				),
				'email'       => array(
					'type'        => 'string',
					'format'      => 'email',
					'description' => __( 'The new primary email address. Must not belong to another customer.', 'easy-digital-downloads' ),
				),
				'status'      => array(
					'type'        => 'string',
					'enum'        => array( 'active', 'inactive' ),
					'description' => __( 'The new customer status.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'customer_id' ),
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

		$schema['properties']['updated'] = array(
			'type'        => 'boolean',
			'description' => __( 'Whether anything actually changed. False when every field provided already held the value requested.', 'easy-digital-downloads' ),
		);

		$schema['required'][] = 'updated';

		return $schema;
	}
}
