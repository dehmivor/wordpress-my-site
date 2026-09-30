<?php
/**
 * PayPal payment methods registry.
 *
 * The single source of truth for the PayPal V3 payment methods. Resolves method
 * slugs to their descriptor classes and assembles the SDK components, funding
 * lists, and client-token requirements from those descriptors, so the checkout
 * SDK loader, the admin settings UI, and the localized JS config all read one
 * definition instead of each re-deriving it.
 *
 * Funding assembly here centralizes two rules that were previously duplicated
 * and drifted: the shared `card` funding source is only disabled when neither
 * card method is active (branded button + on-checkout fields share it), and the
 * client-token requirement is a per-method fact rather than inferred from token
 * presence.
 *
 * @package     EDD\Gateways\PayPal
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal\PaymentMethods\ApplePay;
use EDD\Gateways\PayPal\PaymentMethods\Card;
use EDD\Gateways\PayPal\PaymentMethods\Fastlane;
use EDD\Gateways\PayPal\PaymentMethods\GooglePay;
use EDD\Gateways\PayPal\PaymentMethods\Method;
use EDD\Gateways\PayPal\PaymentMethods\PayLater;
use EDD\Gateways\PayPal\PaymentMethods\PayPal;
use EDD\Gateways\PayPal\PaymentMethods\UnbrandedCard;
use EDD\Gateways\PayPal\PaymentMethods\Venmo;

/**
 * PayPal payment methods registry.
 *
 * @since 3.6.9
 */
class PaymentMethods {

	/**
	 * Setting key holding the per-method toggle state.
	 *
	 * @since 3.6.9
	 */
	const OPTION_KEY = 'paypal_payment_methods';

	/**
	 * Funding tokens that can be toggled on or off in the SDK URL.
	 *
	 * `credit` is intentionally excluded: PayPal pairs it with `paylater`, so it
	 * is handled alongside Pay Later rather than claimed by a method directly.
	 *
	 * @since 3.6.9
	 *
	 * @var string[]
	 */
	const TOGGLEABLE_FUNDING = array( 'venmo', 'card', 'paylater' );

	/**
	 * Maps method slugs to their descriptor classes, in admin display order.
	 *
	 * @since 3.6.9
	 *
	 * @return array<string,string>
	 */
	public static function get_registered_methods(): array {
		return array(
			'paypal'         => PayPal::class,
			'card'           => Card::class,
			'unbranded_card' => UnbrandedCard::class,
			'pay_later'      => PayLater::class,
			'venmo'          => Venmo::class,
			'apple_pay'      => ApplePay::class,
			'google_pay'     => GooglePay::class,
			'fastlane'       => Fastlane::class,
		);
	}

	/**
	 * Resolves a method slug to its descriptor class name.
	 *
	 * @since 3.6.9
	 *
	 * @param string $slug Method slug.
	 * @return string|null Fully-qualified class name, or null when unknown.
	 */
	public static function get_method( string $slug ): ?string {
		$methods = self::get_registered_methods();

		if ( ! isset( $methods[ $slug ] ) || ! is_subclass_of( $methods[ $slug ], Method::class, true ) ) {
			return null;
		}

		return $methods[ $slug ];
	}

	/**
	 * Returns whether a payment method is enabled.
	 *
	 * If the `paypal_payment_methods` setting has an explicit value for this
	 * method, that is authoritative; otherwise the supplied default applies
	 * (usually whether PayPal has granted the underlying capability).
	 *
	 * @since 3.6.9
	 *
	 * @param string $method     Method slug.
	 * @param bool   $default_on Default when no stored value exists.
	 * @return bool
	 */
	public static function is_enabled( string $method, bool $default_on = true ): bool {
		$saved = edd_get_option( self::OPTION_KEY, null );

		if ( is_array( $saved ) && array_key_exists( $method, $saved ) ) {
			return ! empty( $saved[ $method ] );
		}

		return $default_on;
	}

	/**
	 * Computes the default on/off state for a method when no value is stored.
	 *
	 * Resolves the method's capability from the stored onboarding decision so
	 * the checkout runtime never makes a per-pageload proxy call, then defers to
	 * the descriptor for how that grant maps to a default.
	 *
	 * @since 3.6.9
	 *
	 * @param string $method Method slug.
	 * @return bool
	 */
	public static function default_state( string $method ): bool {
		$class = self::get_method( $method );
		if ( ! $class ) {
			return true;
		}

		return $class::default_state( self::is_capability_granted( $class::get_capability() ) );
	}

	/**
	 * Returns the admin-facing label for a method.
	 *
	 * @since 3.6.9
	 *
	 * @param string $slug Method slug.
	 * @return string
	 */
	public static function get_label( string $slug ): string {
		$method = self::get_method( $slug );

		return $method ? $method::get_label() : '';
	}

	/**
	 * Returns the inline SVG icon for a method.
	 *
	 * @since 3.6.9
	 *
	 * @param string $slug Method slug.
	 * @return string
	 */
	public static function get_icon( string $slug ): string {
		$method = self::get_method( $slug );

		return $method ? $method::get_icon() : '';
	}

	/**
	 * Assembles the SDK components needed for a set of active methods.
	 *
	 * Always includes the base `buttons` component. A method's component is only
	 * added when it is available in the current environment and, for methods that
	 * require it, the buyer client token is present.
	 *
	 * @since 3.6.9
	 *
	 * @param string[] $active_slugs     Slugs of the active methods.
	 * @param bool     $has_client_token Whether the buyer client token is available.
	 * @return string[] Ordered list of SDK component names.
	 */
	public static function get_sdk_components( array $active_slugs, bool $has_client_token = false ): array {
		$components = array( 'buttons' );

		foreach ( $active_slugs as $slug ) {
			$method = self::get_method( $slug );
			if ( ! $method ) {
				continue;
			}

			$component = $method::get_sdk_component();
			if ( empty( $component ) ) {
				continue;
			}

			if ( ! $method::is_available() ) {
				continue;
			}

			if ( $method::requires_client_token() && ! $has_client_token ) {
				continue;
			}

			$components[] = $component;
		}

		return array_values( array_unique( $components ) );
	}

	/**
	 * Returns the funding tokens to add to `enable-funding` for the active methods.
	 *
	 * Only Venmo and Pay Later are explicitly enabled; the primary PayPal button
	 * and the card funding are always available without being enabled.
	 *
	 * @since 3.6.9
	 *
	 * @param string[] $active_slugs Slugs of the active methods.
	 * @return string[]
	 */
	public static function get_enable_funding( array $active_slugs ): array {
		$enable = array();

		foreach ( $active_slugs as $slug ) {
			$method = self::get_method( $slug );
			if ( ! $method ) {
				continue;
			}

			$funding = $method::get_funding_source();
			if ( in_array( $funding, array( 'venmo', 'paylater' ), true ) ) {
				$enable[] = $funding;
			}
		}

		return array_values( array_unique( $enable ) );
	}

	/**
	 * Returns the funding tokens to add to `disable-funding` for the active methods.
	 *
	 * A toggleable funding token is disabled only when no active method claims it.
	 * Because the branded card button and the on-checkout card fields both claim
	 * the `card` token, it is disabled only when neither is active — the single
	 * rule that previously drifted between the SDK loader and the JS gate. PayPal
	 * pairs `credit` with `paylater`, so it is disabled alongside Pay Later.
	 *
	 * @since 3.6.9
	 *
	 * @param string[] $active_slugs Slugs of the active methods.
	 * @return string[]
	 */
	public static function get_disable_funding( array $active_slugs ): array {
		$claimed = self::get_claimed_funding_sources( $active_slugs );
		$disable = array();

		foreach ( self::TOGGLEABLE_FUNDING as $token ) {
			if ( ! in_array( $token, $claimed, true ) ) {
				$disable[] = $token;
			}
		}

		// PayPal pairs the `credit` funding with `paylater`; disable it whenever
		// Pay Later is disabled.
		if ( in_array( 'paylater', $disable, true ) ) {
			$disable[] = 'credit';
		}

		return array_values( $disable );
	}

	/**
	 * Returns the funding sources reported to the frontend as enabled buttons.
	 *
	 * Mirrors `disable-funding` from the buyer's perspective: the PayPal button
	 * is always present, and Venmo, Pay Later (with its paired `credit`), and the
	 * branded card button are listed when active. Read by the JS gate to decide
	 * which Buttons funding sources to render.
	 *
	 * @since 3.6.9
	 *
	 * @param string[] $active_slugs Slugs of the active methods.
	 * @return string[]
	 */
	public static function get_button_funding_sources( array $active_slugs ): array {
		$sources = array( 'paypal' );

		if ( in_array( 'venmo', $active_slugs, true ) ) {
			$sources[] = 'venmo';
		}

		if ( in_array( 'pay_later', $active_slugs, true ) ) {
			$sources[] = 'paylater';
			$sources[] = 'credit';
		}

		if ( in_array( 'card', $active_slugs, true ) ) {
			$sources[] = 'card';
		}

		return $sources;
	}

	/**
	 * Maps PayPal funding-source tokens to the EDD method slug that rides on them.
	 *
	 * Derived from the button-funding descriptors so the frontend resolves the
	 * funding source a rendered button belongs to without re-deriving the
	 * mapping. PayPal pairs the `credit` funding with Pay Later, so it resolves
	 * to the same slug. Keyed by funding token (matching the SDK `FUNDING.*`
	 * string values) for direct lookup on the client.
	 *
	 * @since 3.6.9
	 *
	 * @return array<string,string> Funding token => method slug.
	 */
	public static function get_funding_slug_map(): array {
		$map = array();

		foreach ( self::get_registered_methods() as $slug => $class ) {
			if ( ! is_subclass_of( $class, Method::class, true ) || ! $class::is_button_funding() ) {
				continue;
			}

			$funding = $class::get_funding_source();
			if ( ! empty( $funding ) ) {
				$map[ $funding ] = $slug;
			}
		}

		// PayPal pairs the `credit` funding with Pay Later.
		if ( isset( $map['paylater'] ) ) {
			$map['credit'] = $map['paylater'];
		}

		return $map;
	}

	/**
	 * Returns the active methods that require the buyer client token to load.
	 *
	 * The SDK loader uses this to decide whether to request a client token at
	 * all, instead of inferring the requirement from token presence.
	 *
	 * @since 3.6.9
	 *
	 * @param string[] $active_slugs Slugs of the active methods.
	 * @return string[]
	 */
	public static function methods_requiring_client_token( array $active_slugs ): array {
		$requiring = array();

		foreach ( $active_slugs as $slug ) {
			$method = self::get_method( $slug );
			if ( $method && $method::requires_client_token() ) {
				$requiring[] = $slug;
			}
		}

		return $requiring;
	}

	/**
	 * Returns the funding sources claimed by the active methods.
	 *
	 * A funding source is claimed when at least one active method rides on it.
	 * The branded card button and the on-checkout card fields both claim `card`.
	 *
	 * @since 3.6.9
	 *
	 * @param string[] $active_slugs Slugs of the active methods.
	 * @return string[]
	 */
	private static function get_claimed_funding_sources( array $active_slugs ): array {
		$claimed = array();

		foreach ( $active_slugs as $slug ) {
			$method = self::get_method( $slug );
			if ( ! $method ) {
				continue;
			}

			$funding = $method::get_funding_source();
			if ( ! empty( $funding ) ) {
				$claimed[] = $funding;
			}
		}

		return array_values( array_unique( $claimed ) );
	}

	/**
	 * Resolves whether a capability is granted, from the stored onboarding decision.
	 *
	 * Uses the options written at onboarding / merchant-status refresh rather
	 * than a live proxy call, so default resolution stays cheap on the checkout
	 * runtime. The admin settings UI resolves grants from live merchant status
	 * for its capability locks; this is only the default-state source.
	 *
	 * @since 3.6.9
	 *
	 * @param string|null $capability Capability key, or null for an un-gated method.
	 * @return bool
	 */
	private static function is_capability_granted( ?string $capability ): bool {
		if ( null === $capability ) {
			return true;
		}

		$mode = Gateway::get_paypal_mode();
		switch ( $capability ) {
			case 'advanced_card':
				return (bool) get_option( "edd_paypal_{$mode}_advanced_card_available", false );
			case 'vaulting':
				return (bool) get_option( "edd_paypal_{$mode}_vaulting_available", false );
			case 'venmo':
				return (bool) get_option( "edd_paypal_{$mode}_venmo_available", false );
		}

		return false;
	}
}
