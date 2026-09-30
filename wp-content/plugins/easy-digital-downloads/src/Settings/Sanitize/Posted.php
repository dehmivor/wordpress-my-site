<?php
/**
 * Sanitizes each setting a save posted by the type it is declared with.
 *
 * @package     EDD\Settings\Sanitize
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Settings\Sanitize;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Sanitizes each setting a save posted by the type it is declared with.
 *
 * @since 3.7.1
 */
class Posted {

	/**
	 * Sanitizes every posted setting into the values on their way to the store.
	 *
	 * @since 3.7.1
	 *
	 * @global array $edd_options Array of all the EDD Options.
	 *
	 * @param array  $input         The posted settings.
	 * @param string $section_class The section class this save has already been through, if any.
	 * @return array The settings to store.
	 */
	public static function sanitize( array $input, $section_class = '' ) {
		global $edd_options;

		$index             = Registry::get_index();
		$non_setting_types = edd_get_non_setting_types();
		$stored            = is_array( $edd_options ) ? $edd_options : array();
		$output            = $stored;

		foreach ( $input as $key => $value ) {
			// Assigned rather than merged, so a key that is an integer keeps the key it was posted with.
			$output[ $key ] = $value;

			// A value identical to what is stored was sanitized when it was stored.
			if ( array_key_exists( $key, $stored ) && $stored[ $key ] === $value ) {
				continue;
			}

			// A list posted with nothing checked is the empty list, since that is what the form sends to say so.
			if ( Registry::is_empty_list( $key, $value ) ) {
				$output[ $key ] = array();
				continue;
			}

			// A submitted value that does not have the shape the setting stores leaves the stored value in place.
			if ( ! Registry::has_expected_shape( $key, $value ) ) {
				$output[ $key ] = Refusal::keep( $key, $value );
				continue;
			}

			$type = Registry::get_type( $key );

			// A header and a descriptive text render no input, so nothing legitimate is posted under them.
			if ( in_array( $type, Registry::NO_INPUT_TYPES, true ) ) {
				if ( array_key_exists( $key, $stored ) ) {
					$output[ $key ] = $stored[ $key ];
					continue;
				}

				unset( $output[ $key ] );
				continue;
			}

			// A credential must reach its integration character for character, and a hook field's value is read by fixed keys and truthiness, never printed.
			if ( in_array( $type, Registry::VERBATIM_TYPES, true ) || in_array( $type, $non_setting_types, true ) ) {
				continue;
			}

			// class_exists() is true for the abstract base class, which declares no _sanitize() to reach.
			$type_class     = Registry::get_type_class( $type );
			$output[ $key ] = class_exists( $type_class ) && method_exists( $type_class, '_sanitize' )
				? $type_class::sanitize( $value, $key )
				: Fallback::sanitize( $value );

			// A section sanitizer that names the key refines it further when a different form posted it.
			if ( isset( $index['sanitizers'][ $key ] ) && $index['sanitizers'][ $key ] !== $section_class ) {
				$output[ $key ] = $index['sanitizers'][ $key ]::sanitize_field( $key, $output[ $key ] );
			}
		}

		return $output;
	}
}
