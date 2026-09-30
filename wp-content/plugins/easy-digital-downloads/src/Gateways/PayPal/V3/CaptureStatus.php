<?php
/**
 * PayPal V3 capture status mapping.
 *
 * Maps a PayPal capture status to the EDD order status it should produce. This
 * table is identical across every v3 capture path (smart-button checkout,
 * Fastlane, and the on-checkout card fields), which each carried their own copy;
 * centralizing it keeps them from drifting — most importantly so PENDING
 * captures (fraud review / e-check holds) always route to on_hold rather than
 * silently completing, and unknown statuses are treated as failures.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * CaptureStatus class.
 *
 * @since 3.6.9
 */
class CaptureStatus {

	/**
	 * Maps a PayPal capture status to an EDD order status.
	 *
	 * Returns an empty string for an empty or unrecognized status; callers
	 * treat that as a failure rather than a silent success.
	 *
	 * @since 3.6.9
	 *
	 * @param string $paypal_status The capture `status` from the PayPal response.
	 * @return string The EDD order status, or '' when the status is unknown.
	 */
	public static function to_edd_status( $paypal_status ) {
		switch ( strtoupper( (string) $paypal_status ) ) {
			case 'COMPLETED':
				return 'complete';
			case 'PENDING':
				return 'on_hold';
			case 'DECLINED':
			case 'FAILED':
				return 'failed';
		}

		return '';
	}
}
