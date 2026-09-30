<?php
/**
 * Stripe payment method abstract class.
 *
 * @since 3.3.5
 * @package EDD\Gateways\Stripe\PaymentMethods
 */

namespace EDD\Gateways\Stripe\PaymentMethods;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Abstract class for payment methods.
 */
abstract class Method {

	/**
	 * The ID of the payment method.
	 *
	 * @since 3.3.5
	 * @var string
	 */
	protected static $id;

	/**
	 * The currencies supported by the payment method.
	 *
	 * @since 3.3.5
	 * @var array
	 */
	public static $currencies = array();

	/**
	 * The countries supported by the payment method.
	 *
	 * @since 3.3.5
	 * @var array
	 */
	public static $countries = array();

	/**
	 * Whether the payment method supports subscriptions.
	 *
	 * @since 3.3.5
	 * @var bool
	 */
	public static $subscriptions = false;

	/**
	 * Whether the payment method supports trials.
	 *
	 * @since 3.3.5
	 * @var bool
	 */
	public static $trials = false;

	/**
	 * Whether the payment method requires a billing address at checkout.
	 *
	 * @since 3.7.0
	 * @var bool
	 */
	public static $requires_billing_address = false;

	/**
	 * The scope of the payment method.
	 *
	 * @since 3.3.5
	 * @var string|null|bool
	 */
	public static $scope;

	/**
	 * Gets the label for the payment method.
	 *
	 * @since 3.3.5
	 * @return string
	 */
	abstract public static function get_label();

	/**
	 * Gets the icon for the payment method.
	 *
	 * @since 3.3.5
	 * @return string
	 */
	public static function get_icon(): string {
		return '<span class="edd-icon__placeholder"></span>';
	}

	/**
	 * Whether the payment method is available for the current checkout context.
	 *
	 * Defaults to checking that the store currency is supported and the method is
	 * enabled in the Stripe payment method configuration. Override in a child class
	 * to add method-specific eligibility rules (for example a minimum cart total).
	 *
	 * @since 3.7.0
	 * @return bool True if the payment method is available for the current checkout.
	 */
	public static function is_available(): bool {
		if ( ! empty( static::$currencies ) && ! in_array( edd_get_currency(), static::$currencies, true ) ) {
			return false;
		}

		$configuration = \EDD\Gateways\Stripe\PaymentMethods::get_base_configuration();

		return ! empty( $configuration[ static::$id ]['available'] );
	}
}
