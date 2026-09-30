<?php
/**
 * PayPal V3 Key Rotation
 *
 * Handles HMAC key lifecycle: ensuring a usable key exists, rotating
 * to a new key (with a grace window for in-flight webhooks), and cutting
 * over atomically when the Connect service issues a new key.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal\Gateway;
use EDD\Utils\Encryption;
use EDD\Utils\Identifier;
use EDD\Utils\Transient;
use EDD\Utils\URL;

/**
 * KeyRotation class.
 *
 * Manages HMAC key lifecycle operations for the PayPal V3 Connect integration.
 *
 * @since 3.6.9
 */
class KeyRotation {

	/**
	 * Ensures a usable HMAC key is present, recovering from the proxy when needed.
	 *
	 * Non-disruptive: recovery re-establishes the same key, so in-flight webhooks
	 * keep validating. Returns true when a usable key is present after the call.
	 *
	 * @since 3.6.9
	 * @since 3.7.0 Skips the recovery re-register when home_url() looks
	 *        like a staging/local host, to avoid hijacking the production
	 *        store's Connect API registration (e.g. a production site cloned
	 *        to staging with different WP salts, which breaks HMAC decryption
	 *        and would otherwise trigger recovery here).
	 *
	 * @param string $mode Optional. 'sandbox' or 'live'. Defaults to current mode.
	 * @return bool True when a usable key is present (already valid or recovered).
	 */
	public static function ensure( string $mode = '' ): bool {
		if ( empty( $mode ) ) {
			$mode = Gateway::get_paypal_mode();
		}

		// Already usable: fingerprint matches and the key decrypts.
		if ( Credentials::validate_hmac_key( $mode ) && '' !== Credentials::get_hmac_key( $mode ) ) {
			return true;
		}

		// Nothing to recover without a store ID; the store must onboard first.
		$store_id = Credentials::get_store_id( $mode );
		if ( '' === $store_id ) {
			return false;
		}

		$home_url = home_url();
		if ( ! URL::is_production_url( $home_url ) ) {
			edd_debug_log(
				sprintf(
					'PayPal v3: skipped HMAC recovery re-register for %s mode — home_url() looks like a staging/local host (%s). The Connect API registration was left unchanged.',
					$mode,
					$home_url
				)
			);
			return false;
		}

		// Re-register to retrieve the key.
		$api      = new ConnectAPI( $mode );
		$response = $api->register_store(
			array(
				// Use home_url() to match the base WP core uses for rest_url(),
				// keeping the Connect API's stored URL in sync with webhook delivery.
				'site_url'    => $home_url,
				'site_uuid'   => Identifier::get_site_uuid(),
				'license_key' => Onboarding::get_license_key(),
				'gateway'     => 'paypal',
			)
		);

		if ( is_wp_error( $response ) || ConnectAPI::is_error( $response ) || empty( $response['hmac_key'] ) ) {
			edd_debug_log(
				sprintf(
					'PayPal v3: HMAC recovery failed for %s mode: %s',
					$mode,
					is_wp_error( $response ) ? $response->get_error_message() : ConnectAPI::get_error_message( $response )
				),
				true
			);
			return false;
		}

		// Re-encrypt the recovered key locally.
		Credentials::store_hmac_key( $mode, (string) $response['hmac_key'] );

		// The proxy may have re-issued the store ID; keep them in sync.
		if ( ! empty( $response['store_id'] ) ) {
			Credentials::store_store_id( $mode, (string) $response['store_id'] );
		}

		return true;
	}

	/**
	 * Rotates the HMAC key: requests a new key and retires the old one.
	 *
	 * The outgoing key is kept for a grace window so webhooks already in
	 * flight keep validating during cutover.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode Optional. 'sandbox' or 'live'. Defaults to current mode.
	 * @return true|\WP_Error True on success, WP_Error otherwise.
	 */
	public static function rotate( string $mode = '' ) {
		if ( empty( $mode ) ) {
			$mode = Gateway::get_paypal_mode();
		}

		// Rotation needs the current key; without it, recovery is the path.
		if ( '' === Credentials::get_hmac_key( $mode ) ) {
			return new \WP_Error(
				'edd_paypal_v3_rotate_unavailable',
				__( 'The PayPal connection credentials are unavailable, so they cannot be rotated. Please re-establish the connection instead.', 'easy-digital-downloads' )
			);
		}

		$api      = new ConnectAPI( $mode );
		$response = $api->post( '/v3/stores/rotate-hmac', array( 'mode' => $mode ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( ConnectAPI::is_error( $response ) ) {
			return new \WP_Error( 'edd_paypal_v3_rotate_failed', ConnectAPI::get_error_message( $response ) );
		}
		if ( empty( $response['hmac_key'] ) ) {
			return new \WP_Error(
				'edd_paypal_v3_rotate_missing_key',
				__( 'The PayPal proxy did not return a new key during rotation.', 'easy-digital-downloads' )
			);
		}

		self::cut_over( $mode, (string) $response['hmac_key'] );

		return true;
	}

	/**
	 * Cuts over to a new HMAC key, retaining the outgoing key for a grace window.
	 *
	 * Shared by the store-initiated and proxy-initiated rotation paths. Public so
	 * Merchant::get_status() can call it when the Connect service piggybacks a new
	 * key onto a merchant-status response.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode    'sandbox' or 'live'.
	 * @param string $new_key The new plaintext HMAC key.
	 * @return void
	 */
	public static function cut_over( string $mode, string $new_key ): void {
		$current = Credentials::get_hmac_key( $mode );
		if ( '' !== $current ) {
			// Encrypt and store the current key as the previous (grace-window) key.
			$encrypted_current = Encryption::encrypt( $current );
			if ( null !== $encrypted_current ) {
				( new Transient( "edd_paypal_{$mode}_hmac_key_previous", '+1 day' ) )->set( $encrypted_current );
			}
		}

		Credentials::store_hmac_key( $mode, $new_key );
	}
}
