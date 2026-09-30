<?php
/**
 * Refuses a submitted setting value and reports that the saved one was kept.
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
 * Refuses a submitted setting value and reports that the saved one was kept.
 *
 * @since 3.7.1
 */
class Refusal {

	/**
	 * Refuses a submitted value, says so in the log and on the screen, and answers with what the setting keeps.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key   The setting id.
	 * @param mixed  $value The submitted value.
	 * @return mixed The value the setting keeps.
	 */
	public static function keep( $key, $value ) {
		$shapes = array(
			'array'  => 'a list of values',
			'either' => 'a value it can store',
			'scalar' => 'a single value',
		);

		edd_debug_log(
			sprintf(
				'Settings: the %1$s setting expected %2$s and was sent %3$s, so the saved value was kept.',
				$key,
				$shapes[ Registry::get_shape( $key ) ],
				$shapes[ is_array( $value ) ? 'array' : 'scalar' ]
			)
		);

		self::notice( $key );

		return Registry::kept_value( $key );
	}

	/**
	 * Says on the screen that a submitted value could not be used.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key The setting id the notice is coded with.
	 * @return void
	 */
	public static function notice( $key ) {
		// add_settings_error() lives in an admin include, so it is absent on a front-end save.
		if ( ! function_exists( 'add_settings_error' ) ) {
			return;
		}

		$name = Registry::get_name( $key );

		add_settings_error(
			'edd-notices',
			'edd-setting-kept-' . $key,
			sprintf(
				/* translators: %s: The setting label, or its id when the registry carries no label for it. */
				__( 'The value for the "%s" setting could not be used, so the saved value was kept.', 'easy-digital-downloads' ),
				esc_html( $name ? $name : $key )
			),
			'error'
		);
	}
}
