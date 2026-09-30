<?php
/**
 * Base class for EDD abilities that modify store data.
 *
 * @package     EDD\Abilities
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Base class for write abilities.
 *
 * Write abilities default to additive (not destructive) and not idempotent,
 * which maps to POST over REST. Destructive operations, such as deleting a
 * discount, override get_annotations() to declare themselves.
 *
 * @since 3.7.1
 */
abstract class WriteAbility extends Ability {

	/**
	 * Executes the ability.
	 *
	 * Checking the input is the same for every write, so a subclass implements
	 * write_data() and this stays the single entry point. Unlike a read, the
	 * result is not filtered: it reports what was saved, so a callback which
	 * reshaped it would describe a write which did not happen.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $input The input, or null when the ability accepts no input.
	 * @return array|\WP_Error
	 */
	final public function execute( $input = null ) {
		$input = $this->require_input( $input );
		if ( is_wp_error( $input ) ) {
			return $input;
		}

		return $this->write_data( $input );
	}

	/**
	 * Checks whether the current user may run this write ability.
	 *
	 * Write abilities carry a second gate on top of the capability check: the
	 * store must have opted in to letting abilities change its data. A store
	 * can therefore expose its whole read surface without allowing edits.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $input The validated input, or null when the ability accepts no input.
	 * @return bool
	 */
	public function check_permissions( $input = null ): bool {
		if ( ! Loader::writes_enabled() ) {
			return false;
		}

		return parent::check_permissions( $input );
	}

	/**
	 * Writes the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	abstract protected function write_data( array $input );

	/**
	 * Gets the behavior annotations for a write ability.
	 *
	 * Destructive is false and idempotent is false by default, which routes a
	 * write over POST. A destructive and idempotent ability is routed over
	 * DELETE instead, and the REST controller reads DELETE input from the
	 * `input` query string rather than a body, which some MCP clients cannot
	 * send and which writes record IDs into server access logs. An ability which
	 * is idempotent in the plain sense, such as deleting a record which is
	 * already gone, therefore still declares itself not idempotent.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_annotations(): array {
		return array(
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => false,
		);
	}

	/**
	 * Records who made a change, on the record that changed.
	 *
	 * Only orders and customers get one: they are the two objects whose notes a
	 * store owner can read back. The note-add abilities are left alone, since the
	 * note they write is already the record of the change.
	 *
	 * @since 3.7.1
	 *
	 * @param int    $object_id   The order or customer ID.
	 * @param string $object_type Either `order` or `customer`.
	 * @return void
	 */
	protected function log_note( int $object_id, string $object_type ): void {
		$user  = wp_get_current_user();
		$actor = $user->user_login ? $user->user_login : (string) $user->ID;

		edd_add_note(
			array(
				'object_id'   => $object_id,
				'object_type' => $object_type,
				'user_id'     => get_current_user_id(),
				'content'     => sprintf(
					/* translators: 1: the ability which made the change, 2: the user account it acted as. */
					__( 'Changed by an AI assistant using %1$s, signed in as %2$s.', 'easy-digital-downloads' ),
					$this->get_name(),
					$actor
				),
			)
		);
	}

	/**
	 * The error for a record which saved but could not be read back.
	 *
	 * The write landed, so the message has to say so: a client told only that the
	 * request failed will try again, and a second attempt would save it twice.
	 *
	 * @since 3.7.1
	 *
	 * @param int $object_id The ID the write returned.
	 * @return \WP_Error
	 */
	protected function saved_but_unreadable( int $object_id ): \WP_Error {
		return new \WP_Error(
			'edd_ability_saved_not_readable',
			sprintf(
				/* translators: %d: the ID of the record which was saved. */
				__( 'The change was saved as ID %d but could not be read back, so there is nothing to report on it. Check that record before trying again: repeating this request would save the change twice.', 'easy-digital-downloads' ),
				$object_id
			),
			array( 'status' => 500 )
		);
	}
}
