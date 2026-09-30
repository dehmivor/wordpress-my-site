<?php
/**
 * PayPal payment method descriptor base.
 *
 * Each PayPal V3 payment method (PayPal button, branded card, on-checkout card
 * fields, Pay Later, Venmo, Apple Pay, Google Pay, Fastlane) is described by a
 * subclass. This is the single source of truth for the structural facts each
 * method needs — its capability gate, default state, SDK component, funding
 * source, client-token requirement, and payment_source key — so the admin
 * settings UI, the checkout SDK loader, and the localized JS config all read
 * the same definition instead of each re-deriving it.
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
 * Abstract base for a PayPal payment method descriptor.
 *
 * @since 3.6.9
 */
abstract class Method {

	/**
	 * The method slug, matching the key stored in the `paypal_payment_methods` setting.
	 *
	 * @since 3.6.9
	 *
	 * @var string
	 */
	protected static $id = '';

	/**
	 * Returns the method slug.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return static::$id;
	}

	/**
	 * Returns the admin-facing label for the method.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	abstract public static function get_label(): string;

	/**
	 * Returns the admin-facing description for the method.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return '';
	}

	/**
	 * Returns the inline SVG icon for the method.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return '<span class="edd-icon__placeholder"></span>';
	}

	/**
	 * Returns the merchant capability that gates this method's default state.
	 *
	 * One of `advanced_card`, `vaulting`, `venmo`, or null when the method has
	 * no capability gate (PayPal and Pay Later).
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_capability(): ?string {
		return null;
	}

	/**
	 * Computes the default on/off state when the merchant has no saved value.
	 *
	 * Methods with a capability gate default to whether that capability is
	 * granted; un-gated methods default on.
	 *
	 * @since 3.6.9
	 *
	 * @param bool $capability_granted Whether this method's capability is granted.
	 * @return bool
	 */
	public static function default_state( bool $capability_granted ): bool {
		return null === static::get_capability() ? true : $capability_granted;
	}

	/**
	 * Whether the admin toggle is hard-locked off when the capability isn't granted.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_capability_locked(): bool {
		return false;
	}

	/**
	 * Returns the tooltip text shown when the method is locked for lack of capability.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_lock_reason(): string {
		return '';
	}

	/**
	 * Returns an informational tooltip shown regardless of lock state, or null.
	 *
	 * @since 3.6.9
	 *
	 * @return array|null
	 */
	public static function get_tooltip(): ?array {
		return null;
	}

	/**
	 * Whether the method is required and cannot be toggled off (PayPal).
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_required(): bool {
		return false;
	}

	/**
	 * Returns the PayPal SDK component the method needs, or null.
	 *
	 * One of `applepay`, `googlepay`, `card-fields`, `fastlane`, `messages`.
	 * The base `buttons` component is always loaded and is not returned here.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_sdk_component(): ?string {
		return null;
	}

	/**
	 * Returns the PayPal funding source slug this method rides on, or null.
	 *
	 * One of `paypal`, `card`, `venmo`, `paylater`. Wallet and component-based
	 * methods (Apple Pay, Google Pay, Fastlane) return null.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_funding_source(): ?string {
		return null;
	}

	/**
	 * Whether the method renders as a PayPal Buttons funding source.
	 *
	 * Distinguishes button-funding methods (which appear in the SDK funding
	 * list and `enabledFundingSources`) from standalone components and wallets.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_button_funding(): bool {
		return false;
	}

	/**
	 * Whether the buyer client token must be present for this method to load.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function requires_client_token(): bool {
		return false;
	}

	/**
	 * Whether the method is available in the current runtime environment.
	 *
	 * Defaults to true; Apple Pay overrides this to suppress itself in dev and
	 * test environments where domain verification isn't meaningful.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return true;
	}

	/**
	 * Returns the `payment_source` key recorded for orders paid with this method.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_payment_source_key(): string {
		return static::$id;
	}
}
