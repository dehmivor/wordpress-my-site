<?php
/**
 * PayPal V3 Credentials
 *
 * Manages per-mode HMAC key and store ID storage for the PayPal V3
 * Connect integration. Intentionally has no ConnectAPI dependency so
 * ConnectAPI can call into it without creating a circular reference.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Utils\Encryption;
use EDD\Utils\Transient;

/**
 * Credentials class.
 *
 * Handles HMAC key and store ID persistence. All methods are static so any
 * collaborator can read/write credentials without needing an instance.
 *
 * @since 3.6.9
 */
class Credentials {

	/**
	 * Returns the decrypted HMAC key for a mode.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return string The HMAC key, or empty string when none is stored.
	 */
	public static function get_hmac_key( string $mode ): string {
		$stored = (string) get_option( "edd_paypal_{$mode}_hmac_key", '' );
		if ( '' === $stored ) {
			return '';
		}

		return (string) Encryption::decrypt( $stored );
	}

	/**
	 * Returns the previous (outgoing) HMAC key while its grace window is open.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return string The previous HMAC key, or empty string when none/expired.
	 */
	public static function get_previous_hmac_key( string $mode ): string {
		$stored = ( new Transient( "edd_paypal_{$mode}_hmac_key_previous" ) )->get();
		if ( ! $stored ) {
			return '';
		}

		return (string) Encryption::decrypt( (string) $stored );
	}

	/**
	 * Encrypts and stores the HMAC key for a mode, alongside a fingerprint.
	 *
	 * The fingerprint lets validate_hmac_key() detect encryption-key (salt) changes
	 * that would leave the ciphertext undecryptable.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @param string $key  The plaintext HMAC key.
	 * @return void
	 */
	public static function store_hmac_key( string $mode, string $key ): void {
		$encrypted = Encryption::encrypt( $key );
		if ( null === $encrypted ) {
			return;
		}

		update_option( "edd_paypal_{$mode}_hmac_key", $encrypted );

		// Store a fingerprint so validate_hmac_key() can detect salt changes.
		$fingerprint = Encryption::key_fingerprint();
		if ( null !== $fingerprint ) {
			update_option( "edd_paypal_{$mode}_hmac_key_fingerprint", $fingerprint );
		}
	}

	/**
	 * Detects whether the locally-stored HMAC key is still usable.
	 *
	 * Side-effect-free check comparing the stored key fingerprint against the
	 * current one. Returns false when they no longer match.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return bool True when the key is usable (or there is nothing to validate).
	 */
	public static function validate_hmac_key( string $mode ): bool {
		// No key stored — nothing to validate.
		$stored = (string) get_option( "edd_paypal_{$mode}_hmac_key", '' );
		if ( '' === $stored ) {
			return true;
		}

		// No stored fingerprint to compare against; treat as valid.
		$stored_fingerprint = (string) get_option( "edd_paypal_{$mode}_hmac_key_fingerprint", '' );
		if ( '' === $stored_fingerprint ) {
			return true;
		}

		$current_fingerprint = Encryption::key_fingerprint();
		if ( null === $current_fingerprint ) {
			return false;
		}

		return hash_equals( $stored_fingerprint, $current_fingerprint );
	}

	/**
	 * Returns the store ID for a mode.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return string The store ID, or empty string when none is stored.
	 */
	public static function get_store_id( string $mode ): string {
		return (string) get_option( "edd_paypal_{$mode}_store_id", '' );
	}

	/**
	 * Stores the store ID for a mode.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode     'sandbox' or 'live'.
	 * @param string $store_id The store UUID.
	 * @return void
	 */
	public static function store_store_id( string $mode, string $store_id ): void {
		update_option( "edd_paypal_{$mode}_store_id", sanitize_text_field( $store_id ) );
	}

	/**
	 * Deletes all credential options for a mode.
	 *
	 * Removes the HMAC key (active and previous), its fingerprint and expiry,
	 * and the store ID. Called on reconnect so a clean re-registration can begin.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return void
	 */
	public static function forget( string $mode ): void {
		delete_option( "edd_paypal_{$mode}_hmac_key" );
		delete_option( "edd_paypal_{$mode}_hmac_key_fingerprint" );
		( new Transient( "edd_paypal_{$mode}_hmac_key_previous" ) )->delete();
		delete_option( "edd_paypal_{$mode}_store_id" );
	}
}
