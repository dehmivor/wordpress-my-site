<?php
/**
 * Sanitizes the comma separated key list a sortable setting posts.
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
 * Sanitizes the comma separated key list a sortable setting posts.
 *
 * @since 3.7.1
 */
class SortOrder extends Type {

	/**
	 * Sanitize the sort order setting type.
	 *
	 * The whole value is one comma separated list, so it is not walked entry by entry.
	 *
	 * @since 3.7.1
	 *
	 * @param string $value Comma separated list of keys.
	 * @param string $key   The setting id.
	 * @return string
	 */
	public static function sanitize( $value, $key = '' ) {
		return static::_sanitize( $value, $key );
	}

	/**
	 * Reduce the comma separated list to the keys it names.
	 *
	 * @since 3.7.1
	 *
	 * @param string $value Comma separated list of keys.
	 * @return string
	 */
	protected static function _sanitize( $value ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		// edd_sanitize_key(), not sanitize_key(): gateway and icon keys may carry case and dots,
		// and this is the helper the settings UI already applies when it renders them.
		$keys = array_filter( array_map( 'edd_sanitize_key', explode( ',', $value ) ) );

		return implode( ',', $keys );
	}
}
