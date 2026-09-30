<?php
/**
 * PayPal V3 Connect Sync
 *
 * Reconciles local store state with the EDD Connect API: forwards the
 * current Pro license and keeps the Connect API's stored site URL in sync with the
 * site's home URL so webhook delivery never silently breaks after a domain
 * move. Consolidates the license-sync and URL-sync logic into a single
 * subscriber with a daily cron reconciliation pass.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Cron\Events\SingleEvent;
use EDD\Cron\EventManager as CronEventManager;
use EDD\Utils\Identifier;
use EDD\Utils\URL;

/**
 * ConnectSync class.
 *
 * Owns all "reconcile local state to the Connect API" logic: license sync
 * (on immediate license events) and URL sync (on home-URL change and via a
 * daily reconciliation cron).
 *
 * @since 3.7.0
 */
class ConnectSync implements SubscriberInterface {

	/**
	 * Option key that persists the last URL successfully registered per mode.
	 *
	 * Used as a cheap steady-state drift baseline when the Connect API status GET is
	 * unavailable.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const REGISTERED_URL_OPTION = 'edd_paypal_%s_connect_url';

	/**
	 * The hook fired by the daily reconciliation cron and the deferred one-off.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const SYNC_HOOK = 'edd_paypal_v3_sync_connect';

	/**
	 * Returns the events this subscriber wants to listen to.
	 *
	 * @since 3.7.0
	 *
	 * @return array Hook => method mappings.
	 */
	public static function get_subscribed_events(): array {
		return array(
			'edd/license/saved'   => 'sync_license',
			'edd/license/deleted' => 'sync_license',
			'update_option_home'  => array( 'on_home_url_changed', 10, 2 ),
			self::SYNC_HOOK       => 'reconcile_on_cron',
		);
	}

	/**
	 * Forwards the store's current Pro license to the Connect API for each onboarded mode.
	 *
	 * Fires on immediate license save/delete events. Relocated unchanged from
	 * the former Onboarding::sync_license_to_connect().
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function sync_license(): void {
		foreach ( array( 'sandbox', 'live' ) as $mode ) {
			if ( ! Onboarding::is_v3_onboarded( $mode ) ) {
				continue;
			}

			$response = $this->refresh_license( $mode );
			if ( null === $response ) {
				continue;
			}

			edd_debug_log(
				sprintf(
					'PayPal v3: license synced to Connect API for %s mode. status=%s fee_rate=%s',
					$mode,
					$response['license_status'] ?? '(unknown)',
					$response['platform_fee_rate'] ?? '(unknown)'
				)
			);
		}
	}

	/**
	 * Schedules a deferred reconciliation when the site's home URL changes.
	 *
	 * Fires when WP Settings -> General saves a new Site Address (home option).
	 * The Connect API round-trip is deferred to a one-off cron so it never blocks the
	 * admin Settings save.
	 *
	 * @since 3.7.0
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public function on_home_url_changed( $old_value, $new_value ): void {
		// Skip if no PayPal v3 mode is onboarded.
		if ( ! Onboarding::is_v3_onboarded( 'sandbox' ) && ! Onboarding::is_v3_onboarded( 'live' ) ) {
			return;
		}

		// Normalize both sides; skip scheduling if nothing actually changed.
		if ( URL::normalize( (string) $old_value ) === URL::normalize( (string) $new_value ) ) {
			return;
		}

		// Pass a distinguishing arg so SingleEvent::validate() treats this as
		// distinct from the daily recurring hook (which has no args).
		SingleEvent::add(
			time() + MINUTE_IN_SECONDS,
			self::SYNC_HOOK,
			array( 'src' => 'home' )
		);
	}

	/**
	 * Runs the reconcile job when it arrives as a scheduled event.
	 *
	 * ConnectSync is a SubscriberInterface listener rather than a Cron\Components\Component, so
	 * the guard in Component::subscribe() does not reach it and this entry point carries its own.
	 * reconcile() stays directly callable, so an admin action or WP-CLI can still run one.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	public function reconcile_on_cron(): void {
		if ( ! CronEventManager::is_cron_context() ) {
			return;
		}

		$this->reconcile();
	}

	/**
	 * Reconciles each onboarded mode's URL and license with the Connect API.
	 *
	 * Daily cron worker and deferred one-off handler. For each onboarded mode it
	 * compares the Connect API's stored site URL with home_url() and either re-registers
	 * (URL drifted, refreshing URL + license + credentials) or refreshes the
	 * license only. Each mode is isolated so one failure does not skip the other.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function reconcile(): void {
		foreach ( array( 'sandbox', 'live' ) as $mode ) {
			if ( ! Onboarding::is_v3_onboarded( $mode ) ) {
				continue;
			}

			$this->reconcile_mode( $mode );
		}
	}

	/**
	 * Reconciles a single mode with the Connect API.
	 *
	 * Skips the re-register when home_url() looks like a staging/local host,
	 * even if the URL has drifted from the Connect API's stored value. This
	 * guards against a production site cloned to staging (without switching
	 * to Sandbox mode) from hijacking the production store's Connect API
	 * registration.
	 *
	 * @since 3.7.0
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return void
	 */
	private function reconcile_mode( string $mode ): void {
		$home_url = home_url();

		if ( $this->has_url_drifted( $mode, $home_url ) ) {
			if ( ! URL::is_production_url( $home_url ) ) {
				edd_debug_log(
					sprintf(
						'PayPal v3: skipped reconcile re-register for %s mode — home_url() looks like a staging/local host (%s). The Connect API registration was left unchanged.',
						$mode,
						$home_url
					)
				);
				return;
			}

			$this->register_store( $mode, $home_url );
			return;
		}

		// URL is in sync; refresh the license only.
		if ( null === $this->refresh_license( $mode ) ) {
			return;
		}

		// The Connect API already has the right URL; record it as the baseline.
		self::set_registered_url( $mode, $home_url );
	}

	/**
	 * Sends the store's current Pro license to the Connect API for a single mode.
	 *
	 * @since 3.7.0
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return array|null The Connect API response on success, null on failure.
	 */
	private function refresh_license( string $mode ): ?array {
		$api      = new ConnectAPI( $mode );
		$response = $api->post(
			'/v3/stores/refresh-license',
			array( 'license_key' => Onboarding::get_license_key() )
		);

		if ( is_wp_error( $response ) || ConnectAPI::is_error( $response ) ) {
			edd_debug_log(
				sprintf(
					'PayPal v3: refresh-license failed for %s mode: %s',
					$mode,
					is_wp_error( $response ) ? $response->get_error_message() : ConnectAPI::get_error_message( $response )
				)
			);
			return null;
		}

		return is_array( $response ) ? $response : array();
	}

	/**
	 * Determines whether the Connect API's stored site URL no longer matches home_url().
	 *
	 * Prefers the live Connect API status response (which returns site_url); falls back
	 * to the locally-persisted baseline when the status GET is unavailable. With
	 * no baseline and no status, treats the URL as drifted so a re-register
	 * backfills the Connect API.
	 *
	 * @since 3.7.0
	 *
	 * @param string $mode     'sandbox' or 'live'.
	 * @param string $home_url The current home URL.
	 * @return bool True when the proxy URL is stale (or unknown).
	 */
	private function has_url_drifted( string $mode, string $home_url ): bool {
		$store_id = Credentials::get_store_id( $mode );
		if ( '' !== $store_id ) {
			$api    = new ConnectAPI( $mode );
			$status = $api->get( '/v3/stores/' . $store_id . '/status' );

			if ( is_array( $status ) && ! ConnectAPI::is_error( $status ) && isset( $status['site_url'] ) ) {
				return URL::normalize( (string) $status['site_url'] ) !== URL::normalize( $home_url );
			}
		}

		// Status unavailable — fall back to the locally-persisted baseline.
		$baseline = self::get_registered_url( $mode );
		if ( '' === $baseline ) {
			return true;
		}

		return URL::normalize( $baseline ) !== URL::normalize( $home_url );
	}

	/**
	 * Re-registers the store with the proxy to push the new URL and license.
	 *
	 * Persists any re-issued credentials (store_id + hmac_key) so local state
	 * stays in sync, mirroring KeyRotation::ensure(). Records the new URL as the
	 * drift baseline on success.
	 *
	 * @since 3.7.0
	 *
	 * @param string $mode     'sandbox' or 'live'.
	 * @param string $home_url The current home URL to register.
	 * @return void
	 */
	private function register_store( string $mode, string $home_url ): void {
		$api      = new ConnectAPI( $mode );
		$response = $api->register_store(
			array(
				'site_url'    => $home_url,
				'site_uuid'   => Identifier::get_site_uuid(),
				'license_key' => Onboarding::get_license_key(),
				'gateway'     => 'paypal',
			)
		);

		if ( is_wp_error( $response ) || ConnectAPI::is_error( $response ) ) {
			edd_debug_log(
				sprintf(
					'PayPal v3: reconcile re-register failed for %s mode: %s',
					$mode,
					is_wp_error( $response ) ? $response->get_error_message() : ConnectAPI::get_error_message( $response )
				)
			);
			return;
		}

		// The proxy may have re-issued credentials; keep local state in sync.
		if ( ! empty( $response['hmac_key'] ) ) {
			Credentials::store_hmac_key( $mode, (string) $response['hmac_key'] );
		}
		if ( ! empty( $response['store_id'] ) ) {
			Credentials::store_store_id( $mode, (string) $response['store_id'] );
		}

		self::set_registered_url( $mode, $home_url );

		edd_debug_log( sprintf( 'PayPal v3: reconcile re-registered store URL for %s mode.', $mode ) );
	}

	/**
	 * Returns the last registered URL for a mode, or empty string when none.
	 *
	 * @since 3.7.0
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @return string
	 */
	public static function get_registered_url( string $mode ): string {
		return (string) get_option( sprintf( self::REGISTERED_URL_OPTION, $mode ), '' );
	}

	/**
	 * Persists the registered URL baseline for a mode.
	 *
	 * Public so Onboarding::register_store() can record the baseline
	 * immediately on a fresh registration, and can read it back (via
	 * get_registered_url()) to guard against re-registering over an
	 * established production URL from a staging/local host.
	 *
	 * @since 3.7.0
	 *
	 * @param string $mode 'sandbox' or 'live'.
	 * @param string $url  The URL to persist.
	 * @return void
	 */
	public static function set_registered_url( string $mode, string $url ): void {
		update_option( sprintf( self::REGISTERED_URL_OPTION, $mode ), esc_url_raw( $url ) );
	}
}
