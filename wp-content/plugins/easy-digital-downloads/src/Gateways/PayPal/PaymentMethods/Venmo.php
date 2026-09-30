<?php
/**
 * Venmo payment method descriptor.
 *
 * Available to US merchants approved for Venmo by PayPal. Capability-locked when
 * the merchant account hasn't been granted Venmo processing.
 *
 * @package     EDD\Gateways\PayPal\PaymentMethods
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\PaymentMethods;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Venmo method descriptor.
 *
 * @since 3.6.9
 */
class Venmo extends Method {

	/**
	 * The method slug.
	 *
	 * @since 3.6.9
	 *
	 * @var string
	 */
	protected static $id = 'venmo';

	/**
	 * Returns the admin-facing label.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'Venmo', 'easy-digital-downloads' );
	}

	/**
	 * Returns the admin-facing description.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return __( 'Accept Venmo payments. Available to US merchants approved for Venmo by PayPal.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the inline SVG icon.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return '<svg aria-hidden="true" height="32" width="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="4" fill="#fff" stroke="#D8DEE4"/><text x="16" y="24" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif" font-size="22" font-weight="800" font-style="italic" fill="#008CFF" text-anchor="middle">v</text></svg>';
	}

	/**
	 * Returns the capability that gates the default state.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_capability(): ?string {
		return 'venmo';
	}

	/**
	 * Venmo is locked off when the merchant hasn't been granted Venmo.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_capability_locked(): bool {
		return true;
	}

	/**
	 * Returns the lock reason tooltip text.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_lock_reason(): string {
		return __( 'Venmo is not enabled on your PayPal account. PayPal grants Venmo to qualifying US merchants after a review of country, business category, and risk profile.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the funding source slug.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_funding_source(): ?string {
		return 'venmo';
	}

	/**
	 * Venmo renders as a Buttons funding source.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_button_funding(): bool {
		return true;
	}
}
