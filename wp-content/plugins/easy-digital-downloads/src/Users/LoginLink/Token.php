<?php
/**
 * One-Time Login Link Token Class
 *
 * Handles generation, hashing, storage, and invalidation of
 * one-time login link tokens.
 *
 * @package     EDD\Users\LoginLink
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Users\LoginLink;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Token class.
 *
 * Provides static helpers for issuing, hashing, resolving, and
 * invalidating login link tokens.
 *
 * @since 3.6.7
 */
class Token {

	/**
	 * Token time-to-live in seconds.
	 *
	 * @since 3.6.7
	 * @var int
	 */
	const TTL = 10 * MINUTE_IN_SECONDS;

	/**
	 * User meta key for login link token data.
	 *
	 * @since 3.6.7
	 * @var string
	 */
	const META_KEY = 'edd_login_link';

	/**
	 * Issues a new login link token for a user.
	 *
	 * Generates a random token, stores its hash and associated metadata
	 * in user meta, and creates a transient index for fast token-to-user
	 * lookups. Only one active token per user is stored; a new request
	 * overwrites the previous token.
	 *
	 * @since 3.6.7
	 *
	 * @param int $user_id User ID.
	 * @return array {
	 *     Token data on success, empty array on failure.
	 *
	 *     @type string $token      Raw token string.
	 *     @type int    $expires_at Unix timestamp when the token expires.
	 * }
	 */
	public static function issue( int $user_id ): array {
		$token = self::generate();
		if ( empty( $token ) ) {
			return array();
		}

		// Clear any stale replay-protection flag from a previous token so
		// the new token is not rejected by verify_token_meta().
		delete_transient( 'edd_login_link_used_' . $user_id );

		$now     = time();
		$ttl     = self::TTL;
		$expires = $now + $ttl;

		update_user_meta(
			$user_id,
			self::META_KEY,
			array(
				'hash'       => self::hash( $token ),
				'created_at' => $now,
				'expires_at' => $expires,
				'request_ip' => edd_get_ip(),
			)
		);

		set_transient( self::get_index_key( $token ), $user_id, $ttl );

		return array(
			'token'      => $token,
			'expires_at' => $expires,
		);
	}

	/**
	 * Generates a cryptographically secure random token string.
	 *
	 * @since 3.6.7
	 *
	 * @return string 64-character hex string.
	 */
	public static function generate(): string {
		return bin2hex( random_bytes( 32 ) );
	}

	/**
	 * Hashes a token for secure storage and comparison.
	 *
	 * @since 3.6.7
	 *
	 * @param string $token Raw token value.
	 * @return string HMAC-SHA256 hash of the token.
	 */
	public static function hash( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'edd_login_link' ) );
	}

	/**
	 * Resolves a user ID from a token via the transient index.
	 *
	 * @since 3.6.7
	 *
	 * @param string $token Raw login link token.
	 * @return int User ID, or 0 if not found.
	 */
	public static function resolve_user_id( string $token ): int {
		if ( '' === $token ) {
			return 0;
		}

		return absint( get_transient( self::get_index_key( $token ) ) );
	}

	/**
	 * Generates a login link URL for a user.
	 *
	 * @since 3.6.7
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Raw login link token.
	 * @return string|false URL, or false on invalid input.
	 */
	public static function generate_url( int $user_id, string $token ) {
		$user_id = absint( $user_id );
		$token   = (string) $token;

		if ( empty( $user_id ) || empty( $token ) ) {
			return false;
		}

		return add_query_arg(
			array(
				'edd_action' => 'login_link_verify',
				'token'      => rawurlencode( $token ),
			),
			home_url( '/' )
		);
	}

	/**
	 * Invalidates a token by marking it as used and cleaning up.
	 *
	 * Sets the used_at timestamp in user meta, deletes the transient
	 * index, and removes the user meta entry.
	 *
	 * @since 3.6.7
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Raw login link token.
	 * @return void
	 */
	public static function invalidate( int $user_id, string $token ): void {
		// Short-lived flag to block replay attempts within the expiry window.
		set_transient( 'edd_login_link_used_' . $user_id, time(), self::TTL );

		// Clean up token index and meta.
		delete_transient( self::get_index_key( $token ) );
		delete_user_meta( $user_id, self::META_KEY );
	}

	/**
	 * Gets the transient key used to map a token to a user ID.
	 *
	 * @since 3.6.7
	 *
	 * @param string $token Raw login link token.
	 * @return string Transient key.
	 */
	private static function get_index_key( string $token ): string {
		return 'edd_login_link_index_' . hash( 'sha256', (string) $token );
	}
}
