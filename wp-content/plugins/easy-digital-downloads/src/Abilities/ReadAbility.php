<?php
/**
 * Base class for read-only EDD abilities.
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
 * Base class for read-only abilities.
 *
 * Read abilities never modify state, so they are annotated readonly and
 * idempotent, and are exposed over REST as GET requests.
 *
 * @since 3.7.1
 */
abstract class ReadAbility extends Ability {

	/**
	 * Executes the ability.
	 *
	 * Checking the input and filtering the result are the same for every read, so
	 * a subclass implements read_data() and this stays the single entry point.
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

		$output = $this->read_data( $input );
		if ( is_wp_error( $output ) ) {
			return $output;
		}

		/**
		 * Filters the data a read ability returns.
		 *
		 * A callback must return an array. Anything else is discarded and the
		 * unfiltered output is returned instead, because casting it would hand the
		 * caller a response which no longer matches the ability's output schema.
		 *
		 * @since 3.7.1
		 *
		 * @param array  $output The data the ability formatted for its caller.
		 * @param string $name   The fully qualified ability name, such as `edd/order-read`.
		 * @param array  $input  The validated input the ability read.
		 */
		$filtered = apply_filters( 'edd/abilities/read_output', $output, $this->get_name(), $input );

		if ( ! is_array( $filtered ) ) {
			edd_debug_log( 'A callback on edd/abilities/read_output returned a non-array for ' . $this->get_name() . '. The unfiltered output was returned instead.' );

			return $output;
		}

		return $filtered;
	}

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	abstract protected function read_data( array $input );

	/**
	 * Gets the behavior annotations for a read-only ability.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_annotations(): array {
		return array(
			'readonly'    => true,
			'destructive' => false,
			'idempotent'  => true,
		);
	}
}
