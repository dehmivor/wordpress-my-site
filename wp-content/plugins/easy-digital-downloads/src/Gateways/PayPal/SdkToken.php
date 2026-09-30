<?php
/**
 * PayPal SDK Client Token
 *
 * @package     EDD\Gateways\PayPal
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Gateways\PayPal;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal\V3\ConnectAPI;

/**
 * Fetches and caches the PayPal SDK client token.
 *
 * @since 3.7.0
 */
class SdkToken {

	/**
	 * Per-request in-memory cache keyed by request tuple hash.
	 *
	 * Stored as an array (rather than a single string) because the key is derived
	 * from (mode, customer_id, domain) — inputs that are stable within a typical
	 * page request but not hard-coded to a single value. This mirrors the wp_cache
	 * key structure and correctly handles edge cases where those inputs differ
	 * across calls (e.g. guest vs. vaulted customer) without any code change.
	 *
	 * @since 3.7.0
	 *
	 * @var array<string, string>
	 */
	private static array $cache = array();

	/**
	 * Fetches the PayPal SDK client token, with static and object-cache memoization.
	 *
	 * The token is keyed on (mode, customer_id, domain). Those inputs are stable
	 * within a typical page request, so in practice only one token is fetched per
	 * request. The array cache mirrors the wp_cache key structure and handles edge
	 * cases where inputs differ across calls without requiring a code change.
	 *
	 * @since 3.7.0
	 *
	 * @param string $mode        PayPal mode ('live' or 'sandbox').
	 * @param string $customer_id Vaulted PayPal customer ID, or empty string.
	 * @param string $domain      Publicly-resolvable root domain.
	 * @return string Client token string, or empty string on failure.
	 */
	public static function fetch( string $mode, string $customer_id, string $domain ): string {
		// Cache key built from the non-secret request tuple. md5() is used purely
		// for cache namespacing, not for any security purpose.
		$cache_key = md5( $mode . '_' . $customer_id . '_' . $domain );

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$object_cache_key = 'edd_paypal_sdk_token_' . $cache_key;
		$cached           = wp_cache_get( $object_cache_key, 'edd_paypal' );

		if ( false !== $cached ) {
			self::$cache[ $cache_key ] = $cached;
			return $cached;
		}

		$api            = new ConnectAPI( $mode );
		$token_response = $api->post(
			'/v3/paypal/sdk-token',
			array_filter(
				array(
					'mode'        => $mode,
					'customer_id' => $customer_id,
					'domains'     => array( $domain ),
				)
			)
		);

		if ( ! is_wp_error( $token_response ) && ! empty( $token_response['client_token'] ) && is_string( $token_response['client_token'] ) ) {
			$token = $token_response['client_token'];

			// Cap at 5 minutes — the Connect API edge-caches tokens for up to
			// 3000 s and returns the original expires_in (3600) on cache hits, so
			// the remaining validity can be as little as ~600 s. A fixed 5-minute
			// cap guarantees at least 5 minutes of remaining validity in the worst case.
			wp_cache_set( $object_cache_key, $token, 'edd_paypal', 5 * MINUTE_IN_SECONDS );
			self::$cache[ $cache_key ] = $token;
			edd_debug_log( 'Fastlane: client token retrieved (' . strlen( $token ) . ' chars)' );

			return $token;
		}

		$last_code = $api->get_last_response_code();

		// Capture the 422 reason for diagnostics.
		if ( 422 === $last_code ) {
			edd_record_gateway_error(
				__( 'PayPal Fastlane SDK token request rejected', 'easy-digital-downloads' ),
				sprintf(
					/* translators: 1: HTTP response code, 2: domain sent, 3: JSON-encoded Connect response */
					__( 'Connect API returned %1$d. Domain sent: %2$s. Response: %3$s', 'easy-digital-downloads' ),
					$last_code,
					$domain,
					wp_json_encode( $token_response )
				)
			);
		}

		edd_debug_log( 'Fastlane: no client token received from Connect API. Response: ' . wp_json_encode( $token_response ) );

		return '';
	}
}
