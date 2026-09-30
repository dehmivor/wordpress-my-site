<?php
/**
 * Sanitizes an upload setting, which stores a URL.
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
 * Sanitizes an upload setting, which stores a URL.
 *
 * @since 3.7.1
 */
class Upload extends Type {

	/**
	 * Sanitize the upload setting type.
	 *
	 * @since 3.7.1
	 *
	 * @param string|int|float|bool $value The value to sanitize.
	 * @return string
	 */
	protected static function _sanitize( $value ) {
		return sanitize_url( (string) $value );
	}
}
