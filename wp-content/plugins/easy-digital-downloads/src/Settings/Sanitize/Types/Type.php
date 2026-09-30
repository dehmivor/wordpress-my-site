<?php
/**
 * Base Type class for sanitizing a EDD setting type.
 *
 * @since 3.3.3
 * @package EDD\Settings\Sanitize\Types
 */

namespace EDD\Settings\Sanitize\Types;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Base Type class for sanitizing a EDD setting type.
 *
 * @since 3.3.3
 */
abstract class Type {
	/**
	 * Sanitize the value.
	 *
	 * Several settings are stored as one entry per line or one entry per selection, so array
	 * keys are left as they are.
	 *
	 * @since 3.3.3
	 * @since 3.7.1 An array value is sanitized entry by entry, and the setting id reaches _sanitize() for a type whose rule is per setting. A subclass that takes the whole array overrides sanitize(), which now takes the key alongside the value.
	 *
	 * @param mixed  $value The value to sanitize.
	 * @param string $key   The setting id.
	 * @return mixed
	 */
	public static function sanitize( $value, $key = '' ) {
		if ( ! is_array( $value ) ) {
			return static::_sanitize( $value, $key );
		}

		$sanitized = array();
		foreach ( $value as $entry_key => $entry ) {
			$sanitized[ $entry_key ] = static::sanitize( $entry, $key );
		}

		return $sanitized;
	}
}
