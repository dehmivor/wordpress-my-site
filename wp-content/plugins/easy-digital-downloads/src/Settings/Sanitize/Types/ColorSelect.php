<?php
/**
 * Sanitizes a color select setting to one of the button colors it offers.
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
 * Sanitizes a color select setting to one of the button colors it offers.
 *
 * @since 3.7.1
 */
class ColorSelect extends Type {

	/**
	 * Sanitize the color select setting type.
	 *
	 * The value is printed as a CSS class, so it is reduced to a key and must be one the dropdown offers.
	 *
	 * @since 3.7.1
	 *
	 * @param string|int|float|bool $value The value to sanitize.
	 * @return string The button color key, or an empty string.
	 */
	protected static function _sanitize( $value ) {
		$value = sanitize_key( (string) $value );

		return array_key_exists( $value, edd_get_button_colors() ) ? $value : '';
	}
}
