<?php
/**
 * PayPal payment method descriptor.
 *
 * The primary PayPal checkout button. Always enabled and not toggleable.
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
 * PayPal method descriptor.
 *
 * @since 3.6.9
 */
class PayPal extends Method {

	/**
	 * The method slug.
	 *
	 * @since 3.6.9
	 *
	 * @var string
	 */
	protected static $id = 'paypal';

	/**
	 * Returns the admin-facing label.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_label(): string {
		return __( 'PayPal', 'easy-digital-downloads' );
	}

	/**
	 * Returns the admin-facing description.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_description(): string {
		return __( 'Required. The primary PayPal checkout button.', 'easy-digital-downloads' );
	}

	/**
	 * Returns the inline SVG icon.
	 *
	 * Canonical three-color PayPal monogram sourced verbatim from PayPal's brand
	 * CDN at `https://www.paypalobjects.com/paypal-ui/logos/svg/paypal-mark-color.svg`.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_icon(): string {
		return '<svg aria-hidden="true" height="32" width="32" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill="#002991" d="M38.914 13.35c0 5.574-5.144 12.15-12.927 12.15H18.49l-.368 2.322L16.373 39H7.056l5.605-36h15.095c5.083 0 9.082 2.833 10.555 6.77a9.687 9.687 0 0 1 .603 3.58z"/><path fill="#60CDFF" d="M44.284 23.7A12.894 12.894 0 0 1 31.53 34.5h-5.206L24.157 48H14.89l1.483-9 1.75-11.178.367-2.322h7.497c7.773 0 12.927-6.576 12.927-12.15 3.825 1.974 6.055 5.963 5.37 10.35z"/><path fill="#008CFF" d="M38.914 13.35C37.31 12.511 35.365 12 33.248 12h-12.64L18.49 25.5h7.497c7.773 0 12.927-6.576 12.927-12.15z"/></svg>';
	}

	/**
	 * PayPal is required and cannot be toggled off.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_required(): bool {
		return true;
	}

	/**
	 * Returns the funding source slug.
	 *
	 * @since 3.6.9
	 *
	 * @return string|null
	 */
	public static function get_funding_source(): ?string {
		return 'paypal';
	}

	/**
	 * PayPal renders as a Buttons funding source.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_button_funding(): bool {
		return true;
	}
}
