<?php
/**
 * Stripe payment method class.
 *
 * @since 3.7.0
 * @package EDD\Gateways\Stripe\PaymentMethods
 */

namespace EDD\Gateways\Stripe\PaymentMethods;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * UPI class.
 */
class Upi extends Method {

	/**
	 * The ID of the payment method.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	protected static $id = 'upi';

	/**
	 * The supported currencies for the payment method.
	 *
	 * @since 3.7.0
	 * @var array
	 */
	public static $currencies = array( 'INR' );

	/**
	 * Whether the payment method supports subscriptions.
	 *
	 * @since 3.7.0
	 * @var bool
	 */
	public static $subscriptions = true;

	/**
	 * Whether the payment method requires a billing address at checkout.
	 *
	 * @since 3.7.0
	 * @var bool
	 */
	public static $requires_billing_address = true;

	/**
	 * The supported countries for the payment method.
	 *
	 * @since 3.7.0
	 * @var array
	 */
	public static $countries = array(
		'au',
		'at',
		'be',
		'bg',
		'ca',
		'hr',
		'cy',
		'cz',
		'dk',
		'ee',
		'fi',
		'fr',
		'de',
		'gr',
		'hu',
		'ie',
		'it',
		'lv',
		'li',
		'lt',
		'lu',
		'mt',
		'nl',
		'no',
		'pl',
		'pt',
		'ro',
		'sg',
		'sk',
		'si',
		'es',
		'se',
		'ch',
		'gb',
		'us',
	);

	/**
	 * Gets the label for the payment method.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	public static function get_label() {
		return __( 'UPI', 'easy-digital-downloads' );
	}

	/**
	 * Gets the icon for the payment method.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	public static function get_icon(): string {
		return '<svg aria-hidden="true" width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path fill="#F6F8FA" d="M0 0h32v32H0z"></path><path d="m18.658 2.5 6.645 13.25L11.33 29l7.328-26.5Z" fill="#098041"></path><path d="m13.988 2.5 6.646 13.25L6.648 29l7.34-26.5Z" fill="#E97626"></path></svg>';
	}
}
