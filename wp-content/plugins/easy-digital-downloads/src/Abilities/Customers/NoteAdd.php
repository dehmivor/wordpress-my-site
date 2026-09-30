<?php
/**
 * Ability to add a note to an EDD customer.
 *
 * @package     EDD\Abilities\Customers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Customers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\NoteAddAbility;

/**
 * Adds a note to a customer.
 *
 * @since 3.7.1
 */
class NoteAdd extends NoteAddAbility {

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'customer-note-add';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Add Customer Note', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Add a note to a customer record. Notes are internal and are not sent to the customer.', 'easy-digital-downloads' );
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
	 * Gets the note object type this ability writes.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_object_type(): string {
		return 'customer';
	}

	/**
	 * Gets the input key naming the record the note belongs to.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_id_key(): string {
		return 'customer_id';
	}

	/**
	 * Finds the customer the note belongs to.
	 *
	 * @since 3.7.1
	 *
	 * @param int $object_id The customer ID.
	 * @return \EDD_Customer|\WP_Error
	 */
	protected function find_object( int $object_id ) {
		$customer = edd_get_customer( $object_id );
		if ( ! $customer ) {
			return new \WP_Error(
				'edd_ability_customer_not_found',
				__( 'No customer exists with that ID.', 'easy-digital-downloads' ),
				array( 'status' => 404 )
			);
		}

		return $customer;
	}

	/**
	 * Gets the description of the input key naming the record.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_id_description(): string {
		return __( 'The customer ID.', 'easy-digital-downloads' );
	}
}
