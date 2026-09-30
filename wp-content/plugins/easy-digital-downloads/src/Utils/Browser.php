<?php
/**
 * Browser utility.
 *
 * Helpers for working with the current request's user agent.
 *
 * @package     EDD\Utils
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Utils;

defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Browser utility.
 *
 * @since 3.7.0
 */
class Browser {

	/**
	 * Gets the sanitized user agent string for the current request.
	 *
	 * Reads, unslashes, and sanitizes the raw user agent from the request
	 * headers, optionally truncating it to a maximum length.
	 *
	 * @since 3.7.0
	 *
	 * @param int $max_length Optional. Maximum length to truncate the user agent to. Default 0 (no truncation).
	 * @return string The sanitized user agent string, or an empty string if it is not set.
	 */
	public static function get_user_agent( $max_length = 0 ) {
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return '';
		}

		$user_agent = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );

		if ( $max_length > 0 ) {
			$user_agent = substr( $user_agent, 0, $max_length );
		}

		return $user_agent;
	}
}
