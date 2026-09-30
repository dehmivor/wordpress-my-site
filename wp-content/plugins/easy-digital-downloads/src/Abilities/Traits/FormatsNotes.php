<?php
/**
 * Shared note formatting for EDD abilities.
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
 * Formats EDD notes for ability output.
 *
 * @since 3.7.1
 */
trait FormatsNotes {

	/**
	 * Gets the JSON Schema describing one note.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected static function get_note_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'           => array( 'type' => 'integer' ),
				'content'      => array( 'type' => 'string' ),
				'user_id'      => array(
					'type'        => 'integer',
					'description' => __( 'The WordPress user who added the note. Zero for system notes.', 'easy-digital-downloads' ),
				),
				'date_created' => array( 'type' => 'string' ),
			),
			'required'   => array( 'id', 'content', 'date_created' ),
		);
	}

	/**
	 * Formats a note for ability output.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD\Notes\Note $note The note object.
	 * @return array
	 */
	protected static function format_note( $note ): array {
		return array(
			'id'           => (int) $note->id,
			'content'      => (string) $note->content,
			'user_id'      => (int) $note->user_id,
			'date_created' => (string) $note->date_created,
		);
	}
}
