<?php
/**
 * Base Section class for sanitization of a settings section.
 *
 * @since 3.3.3
 * @package EDD\Admin\Settings\Sanitize\Tabs
 */

namespace EDD\Admin\Settings\Sanitize\Tabs;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Settings\Sanitize\Refusal;
use EDD\Settings\Sanitize\Registry;

/**
 * Base Section class for sanitization.
 *
 * In each class that extends this class you can define a method called sanitize_{setting_key} to sanitize the value of that setting.
 *
 * Example:
 * To sanitize a setting named 'currency', you can define a method called sanitize_currency.
 *
 * If you need to do anything more complex, you can register a method called 'additional_processing' and do your custom processing there.
 *
 * A value is checked against the shape its setting stores before a sanitize_{setting_key} method or additional_processing() sees it.
 *
 * @since 3.3.3
 */
abstract class Section {
	/**
	 * Sanitize the section.
	 *
	 * @since 3.3.3
	 * @since 3.7.1 Each key passes through sanitize_field().
	 * @param array $input The array of settings being saved for this section.
	 * @return array
	 */
	public static function sanitize( $input ) {
		foreach ( $input as $key => $value ) {
			$input[ $key ] = static::sanitize_field( $key, $value );
		}

		// Handle any additional processing for the section.
		if ( method_exists( static::class, 'additional_processing' ) ) {
			$processed_input = static::additional_processing( $input );

			$input = is_array( $processed_input )
				? $processed_input
				: $input;
		}

		return $input;
	}

	/**
	 * Sanitizes one setting at the section boundary.
	 *
	 * A section reads the shapes its own fields render, in the method that names a
	 * key and in additional_processing(), so every key it receives is checked here
	 * and a value of another shape leaves the stored value in place. The key is passed to
	 * sanitize_{setting_key}() after the value, so a method declaring only the value is unaffected.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key   The setting id.
	 * @param mixed  $value The submitted value.
	 * @return mixed The empty list when nothing was checked, the kept value when the shape is wrong, the value unchanged when no method names the key, otherwise the method's result.
	 */
	public static function sanitize_field( $key, $value ) {
		if ( Registry::is_empty_list( $key, $value ) ) {
			return array();
		}

		if ( ! Registry::has_expected_shape( $key, $value ) ) {
			return Refusal::keep( $key, $value );
		}

		$method = 'sanitize_' . $key;

		return method_exists( static::class, $method ) ? static::$method( $value, $key ) : $value;
	}
}
