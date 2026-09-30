<?php
/**
 * Sanitizes a setting whose declared type has no type class of its own.
 *
 * @package     EDD\Settings\Sanitize
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Settings\Sanitize;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Settings\Sanitize\Types\Type;

/**
 * Sanitizes a setting whose declared type has no type class of its own.
 *
 * The class sits outside Types/ so no declared type can resolve to it.
 *
 * @since 3.7.1
 */
class Fallback extends Type {

	/**
	 * Sanitize a value of an otherwise unmapped type.
	 *
	 * Newlines are preserved: several settings are documented as taking one value
	 * per line, so collapsing them would change what the store reads back.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $value The value to sanitize.
	 * @return mixed
	 */
	protected static function _sanitize( $value ) {
		// Numbers and booleans have no string form to sanitize, and casting them would change the stored type.
		if ( is_int( $value ) || is_float( $value ) || is_bool( $value ) ) {
			return $value;
		}

		if ( ! is_scalar( $value ) ) {
			return '';
		}

		return sanitize_textarea_field( (string) $value );
	}
}
