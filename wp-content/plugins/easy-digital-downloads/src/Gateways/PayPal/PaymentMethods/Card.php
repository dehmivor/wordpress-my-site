<?php
/**
 * Branded card payment method descriptor.
 *
 * The "Credit/Debit Cards via PayPal" button, which rides on the `card` funding
 * source and works on basic PPCP. Defaults on only when the merchant does NOT
 * have advanced-card vetting (otherwise the on-checkout unbranded card fields
 * are preferred), but is never capability-locked.
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
 * Branded card method descriptor.
 *
 * @since 3.6.9
 */
class Card extends Method {

	/**
	 * The method slug.
	 *
	 * @since 3.6.9
	 *
	 * @var string
	 */
	protected static $id = 'card';

	/**
	 * Returns the admin-facing label.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'Credit/Debit Cards via PayPal', 'easy-digital-downloads' );
	}

	/**
	 * Returns the admin-facing description.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return __( 'Allow users to pay with credit or debit cards via PayPal.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the inline SVG icon.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return '<svg aria-hidden="true" height="32" width="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill="#D8DEE4" d="M0 0h32v32H0z"/><path fill-rule="evenodd" clip-rule="evenodd" d="M6 10.375C6 9.339 6.84 8.5 7.875 8.5h16.25C25.16 8.5 26 9.34 26 10.375v11.25c0 1.035-.84 1.875-1.875 1.875H7.875A1.875 1.875 0 0 1 6 21.625v-11.25Zm1.875 0h16.25v1.875H7.875v-1.875Zm16.25 3.75v7.5H7.875v-7.5h16.25Z" fill="#474E5A"/><path fill-rule="evenodd" clip-rule="evenodd" d="M14.75 18.813c0-.518.42-.938.938-.938h5.624a.937.937 0 1 1 0 1.875h-5.625a.937.937 0 0 1-.937-.938Z" fill="#474E5A"/></svg>';
	}

	/**
	 * Returns the capability that gates the default state.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_capability(): ?string {
		return 'advanced_card';
	}

	/**
	 * Branded card defaults on only when advanced-card vetting is NOT granted.
	 *
	 * When the merchant has advanced card, the on-checkout unbranded fields are
	 * preferred as the default, so a fresh store doesn't show two card UIs.
	 *
	 * @since 3.6.9
	 *
	 * @param bool $capability_granted Whether advanced-card vetting is granted.
	 * @return bool
	 */
	public static function default_state( bool $capability_granted ): bool {
		return ! $capability_granted;
	}

	/**
	 * Returns the funding source slug.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_funding_source(): ?string {
		return 'card';
	}

	/**
	 * Branded card renders as a Buttons funding source.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_button_funding(): bool {
		return true;
	}
}
