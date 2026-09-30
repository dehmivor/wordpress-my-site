<?php
/**
 * PayPal Payment Methods Settings Field
 *
 * Renders the per-method toggle UI shown under the PayPal Commerce gateway
 * settings. Mirrors the Stripe Payment Methods pattern: each row is a brand
 * SVG icon + label + toggle, with capability-locked methods (Venmo, Fastlane,
 * on-checkout card fields) shown disabled with an explanatory tooltip when the
 * merchant's PayPal account hasn't been granted the underlying capability.
 *
 * The method definitions (labels, icons, capabilities, defaults) come from the
 * PaymentMethods registry; this class is only the admin UI.
 *
 * @package     EDD\Gateways\PayPal\Admin\Settings
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\Admin\Settings;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal;
use EDD\Gateways\PayPal\PaymentMethods;
use EDD\Gateways\PayPal\V3\Merchant;
use EDD\Gateways\PayPal\V3\MerchantStatus;
use EDD\Gateways\PayPal\V3\Onboarding;
use EDD\Gateways\PayPal\V3\ConnectAPI;
use EDD\HTML\Multicheck;

/**
 * PaymentMethodsField class.
 *
 * @since 3.6.9
 */
class PaymentMethodsField {

	/**
	 * Renders the payment methods multicheck.
	 *
	 * Wired to the `edd_paypal_payment_methods` action via a `type: hook`
	 * setting in `register_gateway_settings()`.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	public static function render() {
		$options = self::get_options();
		if ( empty( $options ) ) {
			esc_html_e( 'Connect your PayPal account to configure payment methods.', 'easy-digital-downloads' );
			return;
		}
		?>
		<div class="edd-paypal-payment-methods__description">
			<p>
				<?php esc_html_e( 'The methods actually displayed to your customers will vary based on currency, country, and what\'s in their cart. Methods PayPal hasn\'t enabled on your account cannot be enabled in EDD.', 'easy-digital-downloads' ); ?>
			</p>
		</div>
		<?php

		$multicheck = new Multicheck(
			array(
				'name'    => 'edd_settings[' . PaymentMethods::OPTION_KEY . ']',
				'options' => $options,
				'toggle'  => true,
			)
		);
		$multicheck->output();
	}

	/**
	 * Builds the per-method options array consumed by Multicheck.
	 *
	 * Pulls each method's label, description, icon, and capability facts from
	 * the registry, resolves the live merchant-status grants, and applies the
	 * capability locks. The checkbox default uses the live grant so the toggle
	 * reflects what PayPal actually granted.
	 *
	 * @since 3.6.9
	 *
	 * @return array Keyed by method slug.
	 */
	private static function get_options(): array {
		if ( ! PayPal\ready_to_accept_payments() ) {
			return array();
		}

		$mode = PayPal\Gateway::get_paypal_mode();
		if ( 'v3' !== PayPal\CommerceVersion::get_version() ) {
			return array();
		}

		$capabilities = self::fetch_capabilities( $mode );
		$grants       = array(
			'venmo'         => self::is_venmo_granted( $capabilities ),
			'advanced_card' => self::is_advanced_card_granted( $capabilities ),
			'vaulting'      => (bool) get_option( "edd_paypal_{$mode}_vaulting_available", false ),
		);

		$options = array();
		foreach ( PaymentMethods::get_registered_methods() as $slug => $class ) {
			$capability = $class::get_capability();
			$granted    = null === $capability ? true : ! empty( $grants[ $capability ] );

			$option = array(
				'label'   => $class::get_label(),
				'desc'    => $class::get_description(),
				'icon'    => $class::get_icon(),
				'checked' => PaymentMethods::is_enabled( $slug, $class::default_state( $granted ) ),
			);

			// The primary PayPal button is always on and not toggleable.
			if ( $class::is_required() ) {
				$option['checked']  = true;
				$option['disabled'] = true;
				$option['readonly'] = true;
			}

			// Informational tooltip shown regardless of lock state (e.g. Pay Later eligibility).
			$tooltip = $class::get_tooltip();
			if ( ! empty( $tooltip ) ) {
				$option['tooltip'] = $tooltip;
			}

			// Lock capability-gated methods off when PayPal hasn't granted the capability.
			if ( $class::is_capability_locked() && ! $granted ) {
				$option['checked']  = false;
				$option['disabled'] = true;
				$option['tooltip']  = array(
					'content'  => $class::get_lock_reason(),
					'dashicon' => 'dashicons-info',
				);
			}

			$options[ $slug ] = $option;
		}

		return $options;
	}

	/**
	 * Fetches the merchant-status response from the proxy (cached for the request).
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode Sandbox or live.
	 * @return array Merchant-status array, or empty array on failure.
	 */
	private static function fetch_capabilities( string $mode ): array {
		static $cache = array();

		if ( isset( $cache[ $mode ] ) ) {
			return $cache[ $mode ];
		}

		$merchant_id = get_option( "edd_paypal_{$mode}_merchant_id", '' );
		if ( empty( $merchant_id ) ) {
			$cache[ $mode ] = array();
			return $cache[ $mode ];
		}

		// Route through Onboarding so we benefit from the 4-hour transient
		// cache and don't hit PayPal on every payment-methods page render.
		$response = Merchant::get_status( $merchant_id, $mode );

		if ( is_wp_error( $response ) || ConnectAPI::is_error( $response ) ) {
			$cache[ $mode ] = array();
			return $cache[ $mode ];
		}

		$cache[ $mode ] = (array) $response;

		return $cache[ $mode ];
	}

	/**
	 * Determines whether Venmo has been granted to the merchant.
	 *
	 * @since 3.6.9
	 *
	 * @param array $merchant_status Merchant-status response.
	 * @return bool
	 */
	private static function is_venmo_granted( array $merchant_status ): bool {
		return MerchantStatus::has_capability(
			$merchant_status,
			array( 'RECEIVE_VENMO_PAYMENTS', 'VENMO_PAY_PROCESSING' )
		);
	}

	/**
	 * Determines whether advanced card / wallet acceptance has been granted.
	 *
	 * Apple Pay and Google Pay both ride on the merchant's ACDC (advanced
	 * card) vetting, so a single PPCP_CUSTOM check covers them along with
	 * the standalone Card button.
	 *
	 * @since 3.6.9
	 *
	 * @param array $merchant_status Merchant-status response.
	 * @return bool
	 */
	private static function is_advanced_card_granted( array $merchant_status ): bool {
		$vetting = MerchantStatus::get_product_vetting_status( $merchant_status, 'PPCP_CUSTOM' );
		if ( 'SUBSCRIBED' === $vetting ) {
			return true;
		}

		// Fall back to the legacy PPCP product name.
		$vetting = MerchantStatus::get_product_vetting_status( $merchant_status, 'PPCP' );

		return 'SUBSCRIBED' === $vetting;
	}
}
