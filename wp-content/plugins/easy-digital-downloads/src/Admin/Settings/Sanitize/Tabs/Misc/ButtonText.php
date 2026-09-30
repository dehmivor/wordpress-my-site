<?php
/**
 * Sanitizes the Purchase Buttons section.
 *
 * @package     EDD\Admin\Settings\Sanitize\Tabs\Misc
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Admin\Settings\Sanitize\Tabs\Misc;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Admin\Settings\Sanitize\Tabs\Section;
use EDD\Settings\Sanitize\Refusal;
use EDD\Utils\Colors;

/**
 * Sanitizes the Purchase Buttons section.
 *
 * @since 3.7.1
 */
class ButtonText extends Section {

	/**
	 * Sanitizes the default button colors.
	 *
	 * A color that is neither a hex color nor cleared keeps the saved one and is reported.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed  $value The posted colors.
	 * @param string $key   The setting id.
	 * @return array {
	 *     @type string $background The background color to store.
	 *     @type string $text       The text color to store.
	 * }
	 */
	protected static function sanitize_button_colors( $value, $key ) {
		$colors = Colors::get_stored_button_colors();

		if ( ! is_array( $value ) ) {
			edd_debug_log( 'Settings: the button colors were sent a single value instead of a color for each key, so the saved colors were kept.' );
			Refusal::notice( $key );

			return $colors;
		}

		$kept = false;

		foreach ( array_keys( $colors ) as $color ) {
			// The color picker posts an empty field as the empty string, which is the owner clearing the color.
			if ( ! array_key_exists( $color, $value ) || '' === $value[ $color ] ) {
				$colors[ $color ] = '';
				continue;
			}

			$hex = is_string( $value[ $color ] ) ? sanitize_hex_color( maybe_hash_hex_color( $value[ $color ] ) ) : null;

			if ( is_null( $hex ) ) {
				edd_debug_log( sprintf( 'Settings: the %s button color was sent a value that is not a hex color, so the saved color was kept.', $color ) );
				$kept = true;
				continue;
			}

			$colors[ $color ] = $hex;
		}

		if ( $kept ) {
			Refusal::notice( $key );
		}

		return $colors;
	}
}
