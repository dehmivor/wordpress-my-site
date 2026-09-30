<?php
/**
 * Blik payment method class.
 *
 * @package EDD\Gateways\Stripe\PaymentMethods
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since 3.6.8
 */

namespace EDD\Gateways\Stripe\PaymentMethods;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Blik class.
 */
class Blik extends Method {

	/**
	 * The ID of the payment method.
	 *
	 * @since 3.6.8
	 * @var string
	 */
	protected static $id = 'blik';

	/**
	 * The supported currencies for the payment method.
	 *
	 * @since 3.6.8
	 * @var array
	 */
	public static $currencies = array( 'PLN' );

	/**
	 * The supported countries for the payment method.
	 *
	 * @since 3.6.8
	 * @var array
	 */
	public static $countries = array(
		'at',
		'be',
		'bg',
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
		'is',
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
		'sk',
		'si',
		'es',
		'se',
	);

	/**
	 * Gets the label for the payment method.
	 *
	 * @since 3.6.8
	 * @return string
	 */
	public static function get_label() {
		return __( 'BLIK', 'easy-digital-downloads' );
	}

	/**
	 * Gets the icon for the payment method.
	 *
	 * @since 3.6.8
	 * @return string
	 */
	public static function get_icon(): string {
		return '<svg aria-hidden="true" width="32" height="32" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg"><path fill="#010101" d="M0 0h32v32H0z"></path><path d="M16.276 13.305a6.268 6.268 0 0 0-2.968.745v-7.1H10v12.629a6.277 6.277 0 0 0 12.553 0 6.275 6.275 0 0 0-6.277-6.274Zm0 9.295a3.021 3.021 0 1 1 0-6.042 3.021 3.021 0 0 1 0 6.042Z" fill="#fff"></path><path d="M19.592 11.921a2.96 2.96 0 1 0 0-5.921 2.96 2.96 0 0 0 0 5.921Z" fill="url(#bi_blik__a_:rkm:)"></path><defs><linearGradient id="bi_blik__a_:rkm:" x1="103.397" y1="92.918" x2="521.926" y2="511.447" gradientUnits="userSpaceOnUse"><stop stop-color="#E52F08"></stop><stop offset="1" stop-color="#E94F96"></stop></linearGradient></defs></svg>';
	}
}
