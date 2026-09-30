<?php
/**
 * Software Licensing Integration.
 *
 * Note: this class does not currently implement Integration, which is intentional.
 *
 * @package     EDD\Integrations
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Integrations;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Checkout\Validator;

/**
 * Software Licensing integration class.
 *
 * @since 3.7.0
 */
class SoftwareLicensing {

	/**
	 * Whether the checkout needs to reposition the renewal form itself.
	 *
	 * Software Licensing 3.9.7 places the renewal form correctly on its own, so the
	 * checkout only intervenes for older versions.
	 *
	 * @since 3.7.0
	 */
	public static function should_move_renewal_form(): bool {
		if ( ! function_exists( 'edd_sl_renewal_form' ) || ! defined( 'EDD_SL_VERSION' ) ) {
			return false;
		}

		return version_compare( EDD_SL_VERSION, '3.9.6', '<=' );
	}

	/**
	 * Moves the renewal form from the pre-form and in-cart positions to after the
	 * purchase form, so it does not nest inside (or duplicate within) the checkout.
	 *
	 * Must be called before `edd_before_purchase_form` fires and before the cart
	 * renders.
	 *
	 * @since 3.7.0
	 */
	public static function remove_renewal_form() {
		remove_action( 'edd_before_purchase_form', 'edd_sl_renewal_form', -1 );

		if ( ! self::should_move_renewal_form() ) {
			return;
		}

		remove_action( 'edd_after_checkout_cart', 'edd_sl_renewal_form' );

		// The dedicated renewal block already owns the form, so do not re-home it.
		if ( ! Validator::has_block( null, 'edd/license-renewal' ) ) {
			add_action( 'edd_after_purchase_form', 'edd_sl_renewal_form' );
		}
	}
}
