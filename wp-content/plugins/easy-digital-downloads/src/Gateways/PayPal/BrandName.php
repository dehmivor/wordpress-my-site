<?php
/**
 * PayPal Brand Name Helper
 *
 * @package     EDD\Gateways\PayPal
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Gateways\PayPal;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Utils\URL;

/**
 * Provides the brand name to display on PayPal approval screens.
 *
 * @since 3.7.0
 */
class BrandName {

	/**
	 * Returns the brand name to send to PayPal.
	 *
	 * PayPal requires a non-empty brand_name with a maximum length of 127 characters.
	 * Prefers the EDD business name (Entity Name), falling back to the WordPress Site
	 * Title, and finally to the normalized host of home_url() when both are blank, so
	 * checkout never fails with INVALID_STRING_LENGTH.
	 *
	 * @since 3.7.0
	 *
	 * @return string Non-empty brand name, truncated to 127 characters (mb-safe).
	 */
	public static function get(): string {
		$max_length = 127;
		$substr     = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';

		// Prefer the EDD business name (Entity Name); fall back to the WordPress Site Title.
		$name = edd_get_option( 'entity_name', get_bloginfo( 'name' ) );
		$name = trim( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );

		if ( '' !== $name ) {
			return $substr( $name, 0, $max_length );
		}

		// Neither is set — fall back to the normalized domain of home_url().
		$host = URL::host( home_url() );

		return $substr( '' !== $host ? $host : home_url(), 0, $max_length );
	}
}
