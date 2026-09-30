<?php
/**
 * Transfer Store.
 *
 * Server-side holding area for a cross-domain copy payload: a transient keyed by
 * a single-use token, written on export and read (optionally consumed) by the
 * Destination over the transfer REST route.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Transfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Transfer_Store.
 *
 * @since 4.11.76
 */
class Transfer_Store {

	/**
	 * Transient key prefix.
	 */
	const PREFIX = 'pa_xfer_';

	/**
	 * Transfer lifetime in seconds.
	 *
	 * Feeds both the transient lifetime and the signed URL's expiry, so the two
	 * can never drift apart.
	 *
	 * @return int
	 */
	public static function ttl() {

		$ttl = (int) apply_filters( 'pa_transfer_ttl', 15 * MINUTE_IN_SECONDS );

		return $ttl > 0 ? $ttl : 15 * MINUTE_IN_SECONDS;
	}

	/**
	 * Store a payload and return its token.
	 *
	 * The payload is gzipped when the extension is available; get() reverses that
	 * transparently, so callers never see the encoding.
	 *
	 * @param array $payload The transfer payload.
	 *
	 * @return string The single-use token, empty when the payload could not be encoded.
	 */
	public static function put( $payload ) {

		$encoded = wp_json_encode( $payload );

		if ( false === $encoded ) {
			return '';
		}

		$token      = bin2hex( random_bytes( 32 ) );
		$ttl        = self::ttl();
		$compressed = function_exists( 'gzencode' );

		if ( $compressed ) {
			// Transients round-trip through a text column, so the binary gzip
			// stream has to be base64'd rather than stored raw.
			$encoded = base64_encode( gzencode( $encoded ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		set_transient(
			self::PREFIX . $token,
			array(
				'payload'    => $encoded,
				'compressed' => $compressed,
			),
			$ttl
		);

		return $token;
	}

	/**
	 * Read a stored payload.
	 *
	 * @param string $token The transfer token.
	 *
	 * @return array|null The payload, or null when it is gone, expired or unreadable.
	 */
	public static function get( $token ) {

		$record = get_transient( self::PREFIX . $token );

		if ( ! is_array( $record ) || ! isset( $record['payload'] ) ) {
			return null;
		}

		$encoded = $record['payload'];

		if ( ! empty( $record['compressed'] ) ) {

			$encoded = gzdecode( base64_decode( $encoded ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

			if ( false === $encoded ) {
				return null;
			}
		}

		$payload = json_decode( $encoded, true );

		return is_array( $payload ) ? $payload : null;
	}

	/**
	 * Delete a stored payload — a transfer is single-use.
	 *
	 * @param string $token The transfer token.
	 *
	 * @return void
	 */
	public static function consume( $token ) {
		delete_transient( self::PREFIX . $token );
	}
}
