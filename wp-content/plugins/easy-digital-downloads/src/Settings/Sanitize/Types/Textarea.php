<?php
/**
 * Sanitizes a textarea setting, keeping the markup only where the setting is registered to carry it.
 *
 * @package     EDD\Settings\Sanitize\Types
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Settings\Sanitize\Types;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Settings\Sanitize\Registry;

/**
 * Sanitizes a textarea setting, keeping the markup only where the setting is registered to carry it.
 *
 * @since 3.7.1
 */
class Textarea extends Type {

	/**
	 * Sanitize the textarea setting type.
	 *
	 * Line breaks are kept either way, since a textarea setting is often read back a line at a time.
	 *
	 * @since 3.7.1
	 *
	 * @param string|int|float|bool $value The value to sanitize.
	 * @param string                $key   The setting id.
	 * @return string
	 */
	protected static function _sanitize( $value, $key = '' ) {
		$value = (string) $value;

		// wp_kses_post() rewrites a bare ampersand as an entity, which plain text such as an address must not carry.
		return Registry::allows_html( $key ) ? wp_kses_post( $value ) : sanitize_textarea_field( $value );
	}
}
