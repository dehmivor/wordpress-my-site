<?php
/**
 * Ability to add a note to an EDD order.
 *
 * @package     EDD\Abilities\Orders
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Orders;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\NoteAddAbility;

/**
 * Adds a note to an order.
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
		return 'order-note-add';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Add Order Note', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Add a note to an order. Notes are internal and are not sent to the customer.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'edit_shop_payments';
	}

	/**
	 * Gets the note object type this ability writes.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_object_type(): string {
		return 'order';
	}

	/**
	 * Gets the input key naming the record the note belongs to.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_id_key(): string {
		return 'order_id';
	}

	/**
	 * Finds the order the note belongs to.
	 *
	 * @since 3.7.1
	 *
	 * @param int $object_id The order ID.
	 * @return \EDD\Orders\Order|\WP_Error
	 */
	protected function find_object( int $object_id ) {
		$order = edd_get_order( $object_id );
		if ( ! $order ) {
			return new \WP_Error(
				'edd_ability_order_not_found',
				__( 'No order exists with that ID.', 'easy-digital-downloads' ),
				array( 'status' => 404 )
			);
		}

		return $order;
	}

	/**
	 * Gets the description of the input key naming the record.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_id_description(): string {
		return __( 'The order ID.', 'easy-digital-downloads' );
	}
}
