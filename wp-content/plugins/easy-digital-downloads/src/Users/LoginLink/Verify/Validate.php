<?php
/**
 * One-Time Login Link Verification Rate Limiting
 *
 * Provides rate limiting for failed token validation attempts,
 * throttling brute-force token guessing by IP address.
 *
 * @package     EDD\Users\LoginLink\Verify
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Users\LoginLink\Verify;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Validate class.
 *
 * @since 3.6.7
 */
class Validate {

	/**
	 * Maximum number of failed token validation attempts per IP.
	 *
	 * @since 3.6.7
	 * @var int
	 */
	const MAX_FAILURES = 5;

	/**
	 * Window in seconds for counting validation failures.
	 *
	 * @since 3.6.7
	 * @var int
	 */
	const FAILURE_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Checks whether the current IP has exceeded the maximum number
	 * of failed token validation attempts.
	 *
	 * @since 3.6.7
	 *
	 * @return bool True if the IP is rate-limited.
	 */
	public static function is_limited(): bool {
		$client_ip = edd_get_ip();
		if ( empty( $client_ip ) ) {
			return true;
		}

		$key      = 'edd_login_link_fail_' . md5( $client_ip );
		$failures = (int) get_transient( $key );

		return $failures >= self::MAX_FAILURES;
	}

	/**
	 * Records a failed token validation attempt for the current IP.
	 *
	 * @since 3.6.7
	 *
	 * @return void
	 */
	public static function record_failure(): void {
		$client_ip = edd_get_ip();
		if ( empty( $client_ip ) ) {
			return;
		}

		$key      = 'edd_login_link_fail_' . md5( $client_ip );
		$failures = (int) get_transient( $key );
		set_transient( $key, $failures + 1, self::FAILURE_WINDOW );
	}
}
