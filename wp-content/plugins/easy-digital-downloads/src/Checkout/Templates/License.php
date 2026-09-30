<?php
/**
 * Checkout Templates License
 *
 * Handles license validation for the Checkout Template Imports feature.
 *
 * @package     EDD\Checkout\Templates
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Checkout\Templates;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Admin\Pass_Manager;
use EDD\Licensing\License as EDD_License;

/**
 * License class
 *
 * Provides static methods for checking license status and permissions
 * for the Checkout Template Imports feature.
 *
 * @since 3.7.0
 */
class License {

	/**
	 * Check if the current site can import templates.
	 *
	 * Requires any active Pro license to import templates.
	 * Free users can browse and preview, but cannot import.
	 *
	 * @since 3.7.0
	 * @return bool True if the site has an active Pro license.
	 */
	public static function can_import(): bool {
		if ( ! edd_is_pro() ) {
			return false;
		}

		$pass_manager = new Pass_Manager();
		$can_import   = ! empty( $pass_manager->highest_pass_id ) && 'valid' === self::get_status();

		return (bool) $can_import;
	}

	/**
	 * Get the current license status.
	 *
	 * Returns a simplified status string that can be used by the
	 * frontend to determine what UI to show.
	 *
	 * @since 3.7.0
	 * @return string One of: 'valid', 'inactive', 'expired', 'missing', or 'invalid'.
	 */
	public static function get_status(): string {
		$license = new EDD_License( 'pro' );

		// No license key entered.
		if ( empty( $license->key ) ) {
			$status = 'missing';
		} elseif ( empty( $license->license ) ) {
			// License key exists but not activated or no status.
			$status = 'inactive';
		} elseif ( 'expired' === $license->license ) {
			$status = 'expired';
		} elseif ( 'valid' === $license->license ) {
			// The import gate is expiry-aware: importing is a destructive, entitlement-gated
			// action, so a license whose expiry date has lapsed must not import even if its
			// cached status label is still 'valid'.
			if (
				! empty( $license->expires )
				&& 'lifetime' !== $license->expires
				&& ( $expires_ts = strtotime( $license->expires ) ) !== false
				&& $expires_ts < time()
			) {
				$status = 'expired';
			} else {
				$status = 'valid';
			}
		} else {
			// Any other status is considered invalid.
			$status = 'invalid';
		}

		return $status;
	}

	/**
	 * Get the license key.
	 *
	 * Returns the Pro license key for sending with API requests.
	 *
	 * @since 3.7.0
	 * @return string The license key, or empty string if not set.
	 */
	public static function get_key(): string {
		$license = new EDD_License( 'pro' );
		return ! empty( $license->key ) ? $license->key : '';
	}
}
