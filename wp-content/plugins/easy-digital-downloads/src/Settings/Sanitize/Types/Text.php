<?php
/**
 * Sanitizes the text setting type.
 *
 * @since 3.3.3
 * @package EDD\Settings\Sanitize\Types
 */

namespace EDD\Settings\Sanitize\Types;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Sanitizes the text setting type.
 *
 * @since 3.3.3
 */
class Text extends Type {
	/**
	 * Sanitize the text setting type.
	 *
	 * @since 3.3.3
	 *
	 * @param string|int|float|bool $value The value to sanitize.
	 *
	 * @return string|int|float|bool
	 */
	protected static function _sanitize( $value ) {
		/**
		 * Some of our text fields allow empty strings, like the thousands separator.
		 *
		 * If the value consists of all spaces, we should allow it.
		 */
		if ( '' === trim( $value ) ) {
			return $value;
		}

		// There is content in the field, so sanitize it further.
		$allowed_tags = edd_get_allowed_tags();
		return trim( wp_kses( $value, $allowed_tags ) );
	}
}
