<?php
/**
 * Fastlane payment method descriptor.
 *
 * Accelerated card checkout for returning buyers, with one-tap authentication.
 * Requires PayPal's vaulting capability and the buyer client token, and loads
 * via the `fastlane` SDK component.
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
 * Fastlane method descriptor.
 *
 * @since 3.6.9
 */
class Fastlane extends Method {

	/**
	 * The method slug.
	 *
	 * @since 3.6.9
	 *
	 * @var string
	 */
	protected static $id = 'fastlane';

	/**
	 * Returns the admin-facing label.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'Fastlane', 'easy-digital-downloads' );
	}

	/**
	 * Returns the admin-facing description.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return __( 'Accelerated card checkout for returning buyers, with one-tap authentication.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the inline SVG icon.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return '<svg aria-hidden="true" height="32" width="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="4" fill="#142C8E"/><text x="16" y="23" font-family="-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif" font-size="18" font-weight="800" font-style="italic" fill="#fff" text-anchor="middle">F</text></svg>';
	}

	/**
	 * Returns the capability that gates the default state.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_capability(): ?string {
		return 'vaulting';
	}

	/**
	 * Fastlane is locked off when vaulting hasn't been granted.
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
		return __( 'Fastlane requires PayPal\'s vaulting capability, which has not been granted to your account.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the SDK component.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_sdk_component(): ?string {
		return 'fastlane';
	}

	/**
	 * Requires the buyer client token to initialize.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function requires_client_token(): bool {
		return true;
	}
}
