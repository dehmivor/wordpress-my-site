<?php
/**
 * Base class for EDD abilities that add a note to a record.
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
 * Base class for note-add abilities.
 *
 * Adding a note is the same operation whichever record it hangs off, so beyond
 * the naming methods every ability implements, a subclass supplies only the
 * note's object type, the input key naming the record and its description, how
 * to look that record up, and the capability which gates it.
 *
 * @since 3.7.1
 */
abstract class NoteAddAbility extends WriteAbility {

	use FormatsNotes;

	/**
	 * Writes the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function write_data( array $input ) {
		$id_key = $this->get_id_key();

		$object_id = absint( $input[ $id_key ] );

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

		$content = wp_kses( trim( $input['note'] ), edd_get_allowed_tags() );
		if ( empty( $content ) ) {
			return new \WP_Error(
				'edd_ability_note_empty',
				__( 'The note is empty once disallowed content is removed. Provide plain text or allowed HTML.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		$note_id = edd_add_note(
			array(
				'object_id'   => $object_id,
				'object_type' => $this->get_object_type(),
				'user_id'     => get_current_user_id(),
				'content'     => $content,
			)
		);

		if ( empty( $note_id ) ) {
			return new \WP_Error(
				'edd_ability_note_not_added',
				__( 'The note could not be added.', 'easy-digital-downloads' ),
				array( 'status' => 500 )
			);
		}

		$note = edd_get_note( $note_id );
		if ( empty( $note ) ) {
			return $this->saved_but_unreadable( (int) $note_id );
		}

		return array(
			'note'  => self::format_note( $note ),
			$id_key => $object_id,
		);
	}

	/**
	 * Gets the note object type this ability writes.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_object_type(): string;

	/**
	 * Gets the input key naming the record the note belongs to.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	abstract protected function get_id_key(): string;

	/**
	 * Finds the record the note belongs to.
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
			'properties'           => array(
				$this->get_id_key() => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => $this->get_id_description(),
				),
				'note'              => array(
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 5000,
					'description' => __( 'The note content.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( $this->get_id_key(), 'note' ),
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
		return array(
			'type'       => 'object',
			'properties' => array(
				'note'              => self::get_note_schema(),
				$this->get_id_key() => array( 'type' => 'integer' ),
			),
			'required'   => array( 'note', $this->get_id_key() ),
		);
	}
}
