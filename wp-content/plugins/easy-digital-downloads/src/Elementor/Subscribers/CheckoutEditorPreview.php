<?php
/**
 * EDD Elementor Checkout Editor Preview Subscriber.
 *
 * Makes the checkout form's controls non-interactive inside the Elementor editor
 * preview.
 *
 * @package     EDD\Elementor\Subscribers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Subscribers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Blocks\Checkout\Elements\PersonalInfo;

/**
 * Class CheckoutEditorPreview
 *
 * @since 3.7.0
 */
class CheckoutEditorPreview implements SubscriberInterface {

	/**
	 * The inline-only style handle for the editor-preview inert rule.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const HANDLE = 'edd-elementor-checkout-editor-preview';

	/**
	 * Get the subscribed events.
	 *
	 * `elementor/preview/enqueue_styles` fires only inside the editor preview iframe,
	 * never on the front end, so the rule is inherently editor-scoped.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'elementor/preview/enqueue_styles' => 'enqueue_inert_styles',
		);
	}

	/**
	 * Add the inline style that neutralizes the checkout form's controls in the preview.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function enqueue_inert_styles() {
		wp_register_style( self::HANDLE, false, array(), edd_admin_get_script_version() );
		wp_enqueue_style( self::HANDLE );
		wp_add_inline_style( self::HANDLE, $this->get_inert_css() . $this->get_fidelity_css() );
	}

	/**
	 * Build the CSS that disables pointer events on the checkout form's controls.
	 *
	 * Only the interactive controls are disabled, not the whole form: a click on a
	 * control then falls through to its containing widget, so Elementor still selects
	 * the section (its edit overlay carries pointer-events: none, so selection relies on
	 * the click reaching the widget), while the control itself never fires. Disabling the
	 * whole form would inherit down to the widget wrappers and break click-to-select.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	private function get_inert_css(): string {
		$controls = array( 'a', 'button', 'input', 'select', 'textarea', 'label' );

		$selectors = array_map(
			static function ( $control ) {
				return '#edd_purchase_form ' . $control;
			},
			$controls
		);

		return implode( ',', $selectors ) . '{pointer-events:none;}';
	}

	/**
	 * Build the CSS that makes the canvas reflect form-scoped controls the editor cannot see.
	 *
	 * Some checkout styling is scoped to `#edd_purchase_form`, which the front end injects around the
	 * box but the editor canvas lacks, so those rules never apply and a toggle looks ignored. Supplying
	 * the missing pieces, scoped to the same classes, lets the existing rules work in the editor too.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	private function get_fidelity_css(): string {
		$single_line_name = '.' . PersonalInfo::CLASS_NAME_SINGLE_LINE . ' #edd_checkout_user_info{display:grid;}';

		// The discount row-gap control sets the field row to display:flex. On the front end EDD's
		// script writes an inline display:none that collapses it behind the "Enter a discount code"
		// link; the editor never runs that script, so the flex rule wins and the field shows beside
		// the link. Re-hide it so "On Click" previews collapsed. The Show Discount Field control's own
		// rule carries the extra .edd-cart-adjustment class, so "Always" still outranks this and shows it.
		$discount_collapsed = '#edd_discount_code #edd-discount-code-wrap{display:none!important;}';

		return $single_line_name . $discount_collapsed;
	}
}
