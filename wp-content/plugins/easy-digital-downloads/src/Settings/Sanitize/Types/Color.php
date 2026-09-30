<?php
/**
 * Sanitizes a color setting to a hex color.
 *
 * @package     EDD\Settings\Sanitize\Types
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Settings\Sanitize\Types;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Sanitizes a color setting to a hex color.
 *
 * @since 3.7.1
 */
class Color extends Type {

	/**
	 * Sanitize the color setting type.
	 *
	 * The value is printed into CSS, so one that is not a hex color is stored as unset.
	 *
	 * @since 3.7.1
	 *
	 * @param string|int|float|bool $value The value to sanitize.
	 * @return string The hex color, or an empty string.
	 */
	protected static function _sanitize( $value ) {
		return (string) sanitize_hex_color( maybe_hash_hex_color( trim( (string) $value ) ) );
	}
}
