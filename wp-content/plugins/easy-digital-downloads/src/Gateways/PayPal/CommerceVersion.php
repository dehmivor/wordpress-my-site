<?php
/**
 * PayPal Commerce Version Detection
 *
 * Handles version detection (v2 vs v3), defines constants for the Connect
 * integration, and runs the upgrade migration for existing stores.
 *
 * @package     EDD\Gateways\PayPal
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Commerce Version class.
 *
 * Provides the canonical edd_paypal_commerce_version() logic, defines
 * Connect-related PHP constants, and runs a one-time upgrade migration
 * to pin existing 1st party stores to v2.
 *
 * @since 3.6.9
 */
class CommerceVersion implements SubscriberInterface {

	/**
	 * Returns the events this subscriber wants to listen to.
	 *
	 * @since 3.6.9
	 *
	 * @return array Hook => method mappings.
	 */
	public static function get_subscribed_events() {
		return array(
			'admin_init' => 'maybe_migrate',
		);
	}

	/**
	 * Returns the PayPal Commerce version for the current mode.
	 *
	 * Version v2 = legacy direct-to-PayPal integration (1st party).
	 * Version v3 = all API calls routed through EDD Connect.
	 *
	 * New installs default to v3. Existing stores with 1st party credentials
	 * are set to v2 via the upgrade migration.
	 *
	 * @since 3.6.9
	 *
	 * @return string 'v2' or 'v3'.
	 */
	public static function get_version() {
		$mode = Gateway::get_paypal_mode();

		return get_option( "edd_paypal_{$mode}_commerce_version", 'v3' );
	}

	/**
	 * Returns the EDD Connect base URL.
	 *
	 * @since 3.6.9
	 *
	 * @return string The Connect URL.
	 */
	public static function get_connect_url() {
		return 'https://connect.easydigitaldownloads.com';
	}

	/**
	 * Upgrade migration: set commerce version to v2 for existing 1st party stores.
	 *
	 * Stores that already have PayPal client credentials are using the legacy
	 * direct-to-PayPal integration and must be pinned to v2 so they continue
	 * to work after the Connect code is introduced.
	 *
	 * @since 3.6.9
	 */
	public function maybe_migrate() {
		if ( edd_has_upgrade_completed( 'paypal_commerce_version_v3' ) ) {
			return;
		}

		foreach ( array( 'sandbox', 'live' ) as $mode ) {
			// If a commerce version is already set, skip this mode.
			if ( false !== get_option( "edd_paypal_{$mode}_commerce_version" ) ) {
				continue;
			}

			// If the store has 1st party credentials, pin to v2.
			$client_id = edd_get_option( "paypal_{$mode}_client_id" );
			if ( ! empty( $client_id ) ) {
				update_option( "edd_paypal_{$mode}_commerce_version", 'v2' );
			}
		}

		edd_set_upgrade_complete( 'paypal_commerce_version_v3' );
	}
}
