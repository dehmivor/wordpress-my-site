<?php
/**
 * PayPal v3 Onboarding
 *
 * Handles onboarding via EDD Connect: store registration,
 * signup link retrieval, and onboarding completion. Also provides
 * reconnect logic that clears v2 credentials.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Gateways\PayPal\CommerceVersion;
use EDD\Gateways\PayPal\Gateway;
use EDD\Gateways\PayPal\V3\ConnectSync;
use EDD\Gateways\PayPal\V3\Credentials;
use EDD\Gateways\PayPal\V3\KeyRotation;
use EDD\Gateways\PayPal\V3\Merchant;
use EDD\Utils\Identifier;
use EDD\Utils\URL;
use EDD\Utils\Validators\Salts;

/**
 * Onboarding class.
 *
 * Manages the v3 (Connect) onboarding flow: registering the store,
 * retrieving the PayPal signup link, completing onboarding, and reconnecting.
 *
 * @since 3.6.9
 */
class Onboarding implements SubscriberInterface {

	/**
	 * Option key for the stored onboarding tracking ID.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	const TRACKING_ID_OPTION = 'edd_paypal_%s_tracking_id';

	/**
	 * Returns the events this subscriber wants to listen to.
	 *
	 * @since 3.6.9
	 *
	 * @return array Hook => method mappings.
	 */
	public static function get_subscribed_events() {
		return array(
			'wp_ajax_edd_paypal_v3_register_store'      => 'ajax_register_store',
			'wp_ajax_edd_paypal_v3_reconnect'           => 'ajax_reconnect',
			'wp_ajax_edd_paypal_v3_get_merchant_status' => 'ajax_get_merchant_status',
			'wp_ajax_edd_paypal_v3_rotate_hmac'         => 'ajax_rotate_hmac',
			'load-download_page_edd-settings'           => 'handle_paypal_redirect',
		);
	}

	/**
	 * Registers the store with the Connect service and retrieves a PayPal signup link.
	 *
	 * Step 1 of v3 onboarding: POST /v3/stores/register, then POST /v3/paypal/signup-link.
	 *
	 * @since 3.6.9
	 * @since 3.7.0 Blocks re-registration when home_url() looks like a
	 *        staging/local host and doesn't match a previously registered
	 *        production URL for this mode, closing the gap where disconnecting
	 *        and reconnecting from a staging clone could otherwise hijack an
	 *        established production store's Connect API registration.
	 *
	 * @return array{signup_link: string, tracking_id: string}|\WP_Error
	 */
	private static function register_store() {
		// Defense-in-depth: callers also verify nonce + capability, but guard here too.
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return new \WP_Error(
				'edd_paypal_v3_unauthorized',
				__( 'You do not have permission to perform this action.', 'easy-digital-downloads' )
			);
		}

		if ( ! Salts::are_secure() ) {
			return new \WP_Error(
				'edd_paypal_v3_insecure_salts',
				__( 'Your site is using default or missing WordPress security keys (salts). For your security, set unique salts in wp-config.php before connecting to PayPal.', 'easy-digital-downloads' )
			);
		}

		$mode     = Gateway::get_paypal_mode();
		$home_url = home_url();

		// A baseline only exists once a mode has been successfully registered
		// before. If home_url() no longer matches it and looks non-production,
		// this is a staging/local clone attempting to take over an established
		// production registration rather than a genuine first-time connection.
		$baseline = ConnectSync::get_registered_url( $mode );
		if ( '' !== $baseline && URL::normalize( $baseline ) !== URL::normalize( $home_url ) && ! URL::is_production_url( $home_url ) ) {
			edd_debug_log(
				sprintf(
					'PayPal v3: blocked store registration for %s mode — home_url() looks like a staging/local host (%s) and does not match the previously registered production URL (%s).',
					$mode,
					$home_url,
					$baseline
				)
			);
			return new \WP_Error(
				'edd_paypal_v3_non_production_register',
				__( 'This site looks like a staging or local copy of a store already connected to PayPal in live mode. To protect that connection, registering PayPal from here has been blocked. Complete this from your live production site instead.', 'easy-digital-downloads' )
			);
		}

		$api = new ConnectAPI( $mode );

		// Step 1: Register the store.
		$register_response = $api->register_store(
			array(
				// Use home_url() to match the base WP core uses for rest_url(),
				// keeping the Connect API's stored URL in sync with webhook delivery.
				'site_url'    => $home_url,
				'site_uuid'   => Identifier::get_site_uuid(),
				'license_key' => self::get_license_key(),
				'gateway'     => 'paypal',
			)
		);

		if ( is_wp_error( $register_response ) || ConnectAPI::is_error( $register_response ) ) {
			return is_wp_error( $register_response )
				? $register_response
				: new \WP_Error( 'proxy_register_failed', ConnectAPI::get_error_message( $register_response ) );
		}

		if ( empty( $register_response['store_id'] ) || empty( $register_response['hmac_key'] ) ) {
			return new \WP_Error( 'proxy_register_missing_data', __( 'Invalid response from proxy during store registration.', 'easy-digital-downloads' ) );
		}

		// Persist store credentials.
		Credentials::store_store_id( $mode, (string) $register_response['store_id'] );
		Credentials::store_hmac_key( $mode, (string) $register_response['hmac_key'] );

		// Record the baseline immediately so a subsequent registration attempt
		// (e.g. from a staging clone) can be recognized as one, without waiting
		// on the next daily reconcile cron.
		ConnectSync::set_registered_url( $mode, $home_url );

		// Step 2: Get the signup link. We now have HMAC credentials.
		$api->set_store_id( $register_response['store_id'] );
		$api->set_hmac_key( $register_response['hmac_key'] );

		$signup_response = $api->post(
			'/v3/paypal/signup-link',
			array(
				'mode'         => $mode,
				'return_url'   => self::get_return_url(),
				'country_code' => edd_get_shop_country(),
			)
		);

		if ( is_wp_error( $signup_response ) || ConnectAPI::is_error( $signup_response ) ) {
			return is_wp_error( $signup_response )
				? $signup_response
				: new \WP_Error( 'proxy_signup_link_failed', ConnectAPI::get_error_message( $signup_response ) );
		}

		if ( empty( $signup_response['signup_link'] ) ) {
			return new \WP_Error( 'proxy_signup_link_missing', __( 'No signup link returned from proxy.', 'easy-digital-downloads' ) );
		}

		// Store tracking ID for later use in complete-onboarding.
		if ( ! empty( $signup_response['tracking_id'] ) ) {
			update_option( sprintf( self::TRACKING_ID_OPTION, $mode ), sanitize_text_field( $signup_response['tracking_id'] ) );
		}

		// Session marker validated on return in handle_paypal_redirect().
		set_transient(
			'edd_paypal_commerce_connect_started_' . $mode,
			wp_hash( get_current_user_id() . '_' . $mode . '_started', 'nonce' ),
			15 * MINUTE_IN_SECONDS
		);

		// Return only what the browser needs — never the store credentials.
		return array(
			'signup_link' => $signup_response['signup_link'],
			'tracking_id' => isset( $signup_response['tracking_id'] ) ? $signup_response['tracking_id'] : '',
		);
	}

	/**
	 * Completes the v3 onboarding after the merchant returns from PayPal.
	 *
	 * Sends the merchant ID to the Connect service, which uses Auth-Assertion JWT
	 * with EDD's partner credentials to register the webhook and return
	 * capabilities. No OAuth auth_code is needed for the 3rd party model.
	 *
	 * @since 3.6.9
	 *
	 * @param string $merchant_id PayPal merchant/seller ID.
	 * @return array|\WP_Error Onboarding result with capabilities.
	 */
	public static function complete_onboarding( $merchant_id ) {
		// Defense-in-depth: callers also verify nonce + capability, but guard here too.
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return new \WP_Error(
				'edd_paypal_v3_unauthorized',
				__( 'You do not have permission to perform this action.', 'easy-digital-downloads' )
			);
		}

		$mode = Gateway::get_paypal_mode();
		$api  = new ConnectAPI( $mode );

		$body = array(
			'merchant_id' => $merchant_id,
			'mode'        => $mode,
		);

		// Include tracking ID if we have one.
		$tracking_id = get_option( sprintf( self::TRACKING_ID_OPTION, $mode ), '' );
		if ( ! empty( $tracking_id ) ) {
			$body['tracking_id'] = $tracking_id;
		}

		$response = $api->post( '/v3/paypal/complete-onboarding', $body );

		if ( is_wp_error( $response ) || ConnectAPI::is_error( $response ) ) {
			return is_wp_error( $response )
				? $response
				: new \WP_Error( 'proxy_complete_failed', ConnectAPI::get_error_message( $response ) );
		}

		// Set the commerce version to v3.
		update_option( "edd_paypal_{$mode}_commerce_version", 'v3' );

		// Persist all merchant profile fields from the response.
		Merchant::save( $response, $mode );

		// Enable Fastlane by default for new onboarding when vaulting is available.
		if ( ! empty( $response['vaulting_available'] ) ) {
			edd_update_option( 'paypal_fastlane', 1 );
		}

		// Clean up the tracking ID.
		delete_option( sprintf( self::TRACKING_ID_OPTION, $mode ) );

		// Bust any stale cached merchant-status so the next admin page load
		// reflects the freshly-onboarded capabilities + email.
		Merchant::clear_status_cache( $mode );

		return $response;
	}

	/**
	 * Reconnects by clearing v2 credentials and resetting for v3.
	 *
	 * @since 3.6.9
	 * @since 3.7.0 No longer clears the URL-sync baseline: keeping it
	 *        lets register_store()'s guard recognize a subsequent registration
	 *        attempt from a staging/local host as a takeover of this mode's
	 *        established production registration, rather than a fresh connection.
	 *
	 * @param string $mode Optional. 'sandbox' or 'live'. Defaults to current mode.
	 */
	public static function reconnect( $mode = '' ) {
		if ( empty( $mode ) ) {
			$mode = Gateway::get_paypal_mode();
		}

		// Record that this mode had a v2 (1st party) connection before we clear
		// it. After the switch to v3 the store's existing v2 subscriptions keep
		// billing through PayPal and their direct webhook is abandoned, so the
		// IPN listener uses this breadcrumb to keep processing those legacy
		// events. A store that only ever used v3 never sets it.
		if ( edd_get_option( "paypal_{$mode}_client_id" ) ) {
			update_option( "edd_paypal_{$mode}_had_v2_connection", true, false );
		}

		// Clear v2 (1st party) credentials.
		edd_delete_option( "paypal_{$mode}_client_id" );
		edd_delete_option( "paypal_{$mode}_client_secret" );

		// Clear legacy connect details.
		delete_option( "edd_paypal_commerce_connect_details_{$mode}" );
		delete_option( "edd_paypal_commerce_webhook_id_{$mode}" );

		// Clear v3 Connect credentials so they can be re-created.
		Credentials::forget( $mode );
		Merchant::forget( $mode );

		// Drop any cached merchant-status so the next read after reconnect
		// pulls fresh data for the new (or re-onboarded) merchant.
		Merchant::clear_status_cache( $mode );

		// Advance the commerce version to v3 so the settings UI shows the v3
		// onboarding flow after disconnect, even when the store was previously
		// pinned to v2 by the migration.
		update_option( "edd_paypal_{$mode}_commerce_version", 'v3' );
	}

	/**
	 * Checks if the store is fully onboarded for v3.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode Optional. 'sandbox' or 'live'. Defaults to current mode.
	 * @return bool True if all required v3 options are present.
	 */
	public static function is_v3_onboarded( $mode = '' ) {
		if ( empty( $mode ) ) {
			$mode = Gateway::get_paypal_mode();
		}

		return ! empty( get_option( "edd_paypal_{$mode}_store_id" ) )
			&& ! empty( get_option( "edd_paypal_{$mode}_hmac_key" ) )
			&& ! empty( get_option( "edd_paypal_{$mode}_merchant_id" ) );
	}

	/**
	 * AJAX: Register store and get signup link.
	 *
	 * @since 3.6.9
	 */
	public function ajax_register_store() {
		check_ajax_referer( 'edd_paypal_v3_onboarding' );

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
		}

		$result = self::register_store();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Handles the PayPal redirect after onboarding completes.
	 *
	 * PayPal redirects the parent browser window back to the admin settings
	 * page with ?merchantIdInPayPal=XXX. This handler picks it up on the
	 * `load-download_page_edd-settings` hook and completes onboarding.
	 *
	 * @since 3.6.9
	 */
	public function handle_paypal_redirect() {
		if ( ! isset( $_GET['merchantIdInPayPal'] ) || ! edd_is_admin_page( 'settings' ) ) {
			return;
		}

		// Only handle v3 redirects here. v2 has its own handler.
		if ( 'v3' !== CommerceVersion::get_version() ) {
			return;
		}

		// Validate the onboarding session stamped by register_store().
		$mode            = Gateway::get_paypal_mode();
		$connect_process = get_transient( 'edd_paypal_commerce_connect_started_' . $mode );
		if ( empty( $connect_process ) ) {
			return;
		}

		$expected = wp_hash( get_current_user_id() . '_' . $mode . '_started', 'nonce' );
		if ( ! hash_equals( $connect_process, $expected ) ) {
			wp_die(
				esc_html__( 'There was an error processing the connection to PayPal. Please attempt to connect again.', 'easy-digital-downloads' ),
				esc_html__( 'Error', 'easy-digital-downloads' ),
				array(
					'response'  => 403,
					'link_text' => __( 'Return to settings', 'easy-digital-downloads' ),
					'link_url'  => self::get_return_url(),
				)
			);
		}

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return;
		}

		$merchant_id = sanitize_text_field( wp_unslash( urldecode( $_GET['merchantIdInPayPal'] ) ) );

		if ( empty( $merchant_id ) ) {
			return;
		}

		edd_debug_log( sprintf( 'PayPal v3 Connect - Completing onboarding for merchant: %s', $merchant_id ) );

		$result = self::complete_onboarding( $merchant_id );

		// Consume the session — onboarding is finished (or has surfaced an
		// error). Do not wait for the transient to expire naturally.
		delete_transient( 'edd_paypal_commerce_connect_started_' . $mode );

		if ( is_wp_error( $result ) ) {
			$code    = $result->get_error_code() ? $result->get_error_code() : 'unknown';
			$message = $result->get_error_message();
			edd_debug_log( sprintf( 'PayPal v3 Connect - Onboarding failed: [%s] %s', $code, $message ) );

			// Surface the failure on the settings page so the admin can act on it
			// instead of seeing a silent redirect that looks like nothing happened.
			set_transient( 'edd_paypal_v3_onboarding_error_' . get_current_user_id(), $message, 5 * MINUTE_IN_SECONDS );
			edd_redirect( self::get_return_url( array( 'edd_paypal_onboarding_error' => $code ) ) );
		}

		edd_redirect( self::get_return_url() );
	}

	/**
	 * AJAX: Reconnect by clearing old credentials.
	 *
	 * @since 3.6.9
	 */
	public function ajax_reconnect() {
		check_ajax_referer( 'edd_paypal_v3_onboarding' );

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
		}

		self::reconnect();

		wp_send_json_success( __( 'Credentials cleared. You can now reconnect to PayPal.', 'easy-digital-downloads' ) );
	}

	/**
	 * AJAX: Refresh merchant status.
	 *
	 * @since 3.6.9
	 */
	public function ajax_get_merchant_status() {
		check_ajax_referer( 'edd_paypal_v3_onboarding' );

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
		}

		$mode        = Gateway::get_paypal_mode();
		$merchant_id = get_option( "edd_paypal_{$mode}_merchant_id", '' );

		if ( empty( $merchant_id ) ) {
			wp_send_json_error( __( 'No merchant ID found. Please complete onboarding first.', 'easy-digital-downloads' ) );
		}

		// Force-refresh: the admin explicitly clicked "Re-Check Payment Status"
		// so we always pull fresh data and bust the cache.
		// Merchant::get_status() calls Merchant::save() internally, persisting all
		// profile fields (capabilities, vaulting, advanced_card, seller_email, etc.).
		$result = Merchant::get_status( $merchant_id, $mode, true );

		if ( is_wp_error( $result ) || ConnectAPI::is_error( $result ) ) {
			$message = is_wp_error( $result ) ? $result->get_error_message() : ConnectAPI::get_error_message( $result );
			wp_send_json_error( $message );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Rotate the store's HMAC credentials.
	 *
	 * @since 3.6.9
	 */
	public function ajax_rotate_hmac() {
		check_ajax_referer( 'edd_paypal_v3_onboarding' );

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
		}

		$result = KeyRotation::rotate();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( __( 'PayPal credentials rotated successfully.', 'easy-digital-downloads' ) );
	}

	/**
	 * Gets the return URL for PayPal onboarding redirect.
	 *
	 * PayPal redirects the parent browser window here after onboarding.
	 * The admin settings page handler picks up the merchantIdInPayPal param.
	 *
	 * @since 3.6.9
	 *
	 * @return string The admin settings page URL.
	 */
	private static function get_return_url( $args = array() ) {
		return edd_get_admin_url(
			array_merge(
				array(
					'page'    => 'edd-settings',
					'tab'     => 'gateways',
					'section' => 'paypal_commerce',
				),
				$args
			)
		);
	}

	/**
	 * Gets the EDD license key for store registration.
	 *
	 * Resolves the key through \EDD\Licensing\License so multisite installs
	 * (where the Pro key is a network option) work correctly, and falls back
	 * to the highest active pass license when no Pro key is entered — that
	 * covers customers running on a pass who never explicitly saved the Pro
	 * key but are still licensed.
	 *
	 * @since 3.6.9
	 *
	 * @return string The license key, or empty string if no license is active.
	 */
	public static function get_license_key() {
		$pro_license = new \EDD\Licensing\License( 'pro' );
		$key         = $pro_license->get_license_key();
		if ( ! empty( $key ) ) {
			return (string) $key;
		}

		$pass_manager = new \EDD\Admin\Pass_Manager();
		if ( ! empty( $pass_manager->highest_license_key ) ) {
			return (string) $pass_manager->highest_license_key;
		}

		return '';
	}

	/**
	 * Forwards the store's current Pro license whenever it's saved or removed, keeping the connection in sync.
	 *
	 * @since 3.6.9
	 * @deprecated 3.7.0 Use ConnectSync::sync_license() instead.
	 *
	 * @return void
	 */
	public static function sync_license_to_connect() {
		_edd_deprecated_function( __METHOD__, '3.7.0', 'EDD\\Gateways\\PayPal\\V3\\ConnectSync::sync_license()' );

		( new ConnectSync() )->sync_license();
	}
}
