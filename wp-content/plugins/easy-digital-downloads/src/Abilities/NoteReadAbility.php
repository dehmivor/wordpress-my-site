<?php
/**
 * Base class for EDD abilities that read the notes on a record.
 *
 * @package     EDD\Abilities
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Traits\FormatsNotes;

/**
 * Base class for note-read abilities.
 *
 * Listing notes is the same operation whichever record they hang off, so beyond
 * the naming methods every ability implements, a subclass supplies only the
 * note's object type, the input key naming the record and its description, how
 * to look that record up, and the capability which gates it.
 *
 * @since 3.7.1
 */
abstract class NoteReadAbility extends ReadAbility {

	use FormatsNotes;

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function read_data( array $input ) {
		$object_id = absint( $input[ $this->get_id_key() ] );

		$object = $this->find_object( $object_id );
		if ( is_wp_error( $object ) ) {
			return $object;
		}

		// EDD's getters return false rather than an error, so a subclass which
		// forwards that would act on a record which does not exist.
		if ( ! $object ) {
			return new \WP_Error(
				'edd_ability_object_not_found',
				__( 'No record exists with that ID.', 'easy-digital-downloads' ),
				array( 'status' => 404 )
			);
		}

		$pagination = Pagination::parse( $input );

		$query_args = array(
			'object_id'   => $object_id,
			'object_type' => $this->get_object_type(),
		);

		$notes = edd_get_notes(
			array_merge(
				$query_args,
				array(
					'number' => $pagination['limit'],
					'offset' => $pagination['offset'],
				)
			)
		);
		$total = edd_count_notes( $query_args );

		$formatted = array();
		foreach ( $notes as $note ) {
			$formatted[] = self::format_note( $note );
		}

		return Pagination::envelope( 'notes', $formatted, $total, $pagination['limit'], $pagination['offset'] );
	}

	/**
	 * Gets the note object type this ability reads.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_object_type(): string;

	/**
	 * Gets the input key naming the record the notes belong to.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_id_key(): string;

	/**
	 * Finds the record the notes belong to.
	 *
	 * @since 3.7.1
	 *
	 * @param int $object_id The record ID.
	 * @return object|\WP_Error The record, or the ability's not-found error.
	 */
	abstract protected function find_object( int $object_id );

	/**
	 * Gets the description of the input key naming the record.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_id_description(): string;

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
					$this->get_id_key() => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => $this->get_id_description(),
					),
				),
				Pagination::input_properties()
			),
			'required'             => array( $this->get_id_key() ),
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
		return Pagination::output_schema( 'notes', self::get_note_schema() );
	}
}
