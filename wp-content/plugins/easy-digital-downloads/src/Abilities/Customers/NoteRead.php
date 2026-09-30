<?php
/**
 * Ability to read notes on an EDD customer.
 *
 * @package     EDD\Abilities\Customers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Customers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\NoteReadAbility;

/**
 * Reads the notes attached to a customer.
 *
 * @since 3.7.1
 */
class NoteRead extends NoteReadAbility {

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'customer-note-read';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Read Customer Notes', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'List the notes recorded on a customer, newest first.', 'easy-digital-downloads' );
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
	 * Gets the note object type this ability reads.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_object_type(): string {
		return 'customer';
	}

	/**
	 * Gets the input key naming the record the notes belong to.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_id_key(): string {
		return 'customer_id';
	}

	/**
	 * Finds the customer the notes belong to.
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
