<?php
/**
 * PayPal V3 Merchant
 *
 * Owns all merchant profile persistence and status fetching for the PayPal V3
 * Connect integration. The key correctness property is that save() writes every
 * profile field present in a response in one call, so no subset of fields goes
 * stale regardless of which code path triggered the fetch.
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

/**
 * Merchant class.
 *
 * Handles merchant profile storage (save/forget) and merchant-status fetching
 * with a per-mode transient cache.
 *
 * @since 3.6.9
 */
class Merchant {

	/**
	 * Transient TTL for cached merchant-status responses (4 hours).
	 *
	 * Long enough that idle admin page loads don't repeatedly hit PayPal's
	 * partner-integration endpoint; short enough that vetting or capability
	 * changes show up within a workday without a manual refresh.
	 *
	 * @since 3.6.9
	 */
	const STATUS_TTL = 4 * HOUR_IN_SECONDS;

	/**
	 * Persists every merchant profile field present in a response.
	 *
	 * This is the canonical write path for merchant profile data. Calling it
	 * from both onboarding completion and status refresh ensures all fields stay
	 * in sync regardless of which response path triggered the update.
	 *
	 * Fields written when present in $response:
	 *   - merchant_id        → edd_paypal_{mode}_merchant_id
	 *   - primary_email      → edd_paypal_{mode}_seller_email (deleted when absent/empty)
	 *   - capabilities       → edd_paypal_{mode}_capabilities
	 *   - vaulting_available → edd_paypal_{mode}_vaulting_available
	 *   - partner_client_id  → edd_paypal_{mode}_partner_client_id
	 *   - advanced_card_available (derived from product_details vetting status)
	 *
	 * @since 3.6.9
	 *
	 * @param array  $response The response array from the Connect service.
	 * @param string $mode     'sandbox' or 'live'.
	 * @return void
	 */
	public static function save( array $response, string $mode ): void {
		if ( ! empty( $response['merchant_id'] ) ) {
			update_option( "edd_paypal_{$mode}_merchant_id", sanitize_text_field( $response['merchant_id'] ) );
		}

		// seller_email is deleted when primary_email is absent or empty so the
		// admin UI doesn't display a stale address after email changes.
		if ( ! empty( $response['primary_email'] ) ) {
			update_option( "edd_paypal_{$mode}_seller_email", sanitize_email( $response['primary_email'] ) );
		} else {
			delete_option( "edd_paypal_{$mode}_seller_email" );
		}

		if ( ! empty( $response['capabilities'] ) && is_array( $response['capabilities'] ) ) {
			update_option( "edd_paypal_{$mode}_capabilities", array_map( 'sanitize_text_field', $response['capabilities'] ) );
		}

		if ( isset( $response['vaulting_available'] ) ) {
			update_option( "edd_paypal_{$mode}_vaulting_available", (bool) $response['vaulting_available'] );
		}

		if ( ! empty( $response['partner_client_id'] ) ) {
			update_option( "edd_paypal_{$mode}_partner_client_id", sanitize_text_field( $response['partner_client_id'] ) );
		}

		// Derive and persist advanced-card availability when the response contains
		// product vetting data. Only write when at least one product status is
		// available so an onboarding response (which lacks product_details) does
		// not accidentally overwrite a value set by a previous status fetch.
		$ppcp_custom_status = MerchantStatus::get_product_vetting_status( $response, 'PPCP_CUSTOM' );
		$ppcp_status        = MerchantStatus::get_product_vetting_status( $response, 'PPCP' );

		if ( null !== $ppcp_custom_status || null !== $ppcp_status ) {
			$advanced_card = 'SUBSCRIBED' === $ppcp_custom_status || 'SUBSCRIBED' === $ppcp_status;
			update_option( "edd_paypal_{$mode}_advanced_card_available", (bool) $advanced_card );
		}
	}

	/**
	 * Gets the merchant status from the Connect service, with a per-mode transient cache.
	 *
	 * Pass $force_refresh = true to bypass the cache (used by the admin
	 * "Re-Check Payment Status" button and onboarding-completion paths where
	 * we always want the freshest values).
	 *
	 * After a successful fetch, all profile fields in the response are persisted
	 * via save() so every field stays in sync on every poll.
	 *
	 * @since 3.6.9
	 *
	 * @param string $merchant_id   PayPal merchant ID.
	 * @param string $mode          Optional. 'sandbox' or 'live'. Defaults to current mode.
	 * @param bool   $force_refresh Optional. Bypass the cache. Default false.
	 * @return array|\WP_Error
	 */
	public static function get_status( string $merchant_id, string $mode = '', bool $force_refresh = false ) {
		if ( empty( $mode ) ) {
			$mode = Gateway::get_paypal_mode();
		}

		$cache_key = self::get_status_cache_key( $mode );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		$api      = new ConnectAPI( $mode );
		$response = $api->post(
			'/v3/paypal/merchant-status',
			array(
				'merchant_id' => $merchant_id,
				'mode'        => $mode,
			)
		);

		// Only cache successful responses — error responses should be retried on
		// the next request rather than stuck in the cache.
		if ( ! is_wp_error( $response ) && ! ConnectAPI::is_error( $response ) ) {
			// Adopt a key handed down on this poll, then drop it before caching or saving.
			if ( ! empty( $response['rotated_hmac_key'] ) ) {
				KeyRotation::cut_over( $mode, (string) $response['rotated_hmac_key'] );
				unset( $response['rotated_hmac_key'] );
			}

			set_transient( $cache_key, $response, self::STATUS_TTL );

			// Persist all profile fields present in the status response.
			self::save( $response, $mode );
		}

		return $response;
	}

	/**
	 * Returns the transient key used to cache the merchant-status response for a given mode.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return string
	 */
	public static function get_status_cache_key( string $mode ): string {
		return "edd_paypal_v3_merchant_status_{$mode}";
	}

	/**
	 * Clears the cached merchant-status response for a given mode.
	 *
	 * Called when onboarding completes, when a store reconnects, and from the
	 * admin "Re-Check Payment Status" handler so the next read pulls fresh data.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return void
	 */
	public static function clear_status_cache( string $mode ): void {
		delete_transient( self::get_status_cache_key( $mode ) );
	}

	/**
	 * Deletes all merchant profile options for a mode.
	 *
	 * Called on reconnect so stale merchant data doesn't persist into the next
	 * onboarding session.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return void
	 */
	public static function forget( string $mode ): void {
		delete_option( "edd_paypal_{$mode}_merchant_id" );
		delete_option( "edd_paypal_{$mode}_seller_email" );
		delete_option( "edd_paypal_{$mode}_capabilities" );
		delete_option( "edd_paypal_{$mode}_vaulting_available" );
		delete_option( "edd_paypal_{$mode}_partner_client_id" );
		delete_option( "edd_paypal_{$mode}_advanced_card_available" );
	}
}
