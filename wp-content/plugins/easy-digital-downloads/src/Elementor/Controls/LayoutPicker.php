<?php
/**
 * EDD Checkout box layout-picker Elementor control.
 *
 * A UI-only (data-less) control registered so the checkout box's options panel
 * can host the five block-parity layout thumbnails and the box-scoped re-add
 * affordance IN the panel, as its own dedicated "Checkout Layout" section
 * alongside the native Container controls (which stay visible at their defaults).
 * The control stores no value: its entire interactive UI — the SVG thumbnails,
 * the passive "Switching layouts replaces all inner blocks." text, and an ON/OFF
 * switch per non-required section (Cart, Discount Form) — is built by the editor JS
 * control view registered against this type (assets/src/js/elementor/checkout-box.js),
 * which wires each thumbnail to the layout switch and each toggle to the re-add /
 * remove command logic. Its server template is intentionally empty: the JS view
 * renders everything.
 *
 * @package     EDD\Elementor\Controls
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Controls;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * EDD Checkout box layout-picker control — a data-less panel mount point.
 *
 * @since 3.7.0
 */
class LayoutPicker extends \Elementor\Base_UI_Control {

	/**
	 * Control type.
	 *
	 * Must equal the type passed to add_control() in CheckoutBox::register_controls()
	 * and the type the JS view is registered against via elementor.addControlView().
	 *
	 * @since 3.7.0
	 *
	 * @return string
	 */
	public function get_type() {
		return 'edd-layout-picker';
	}

	/**
	 * Render the control template in the editor.
	 *
	 * Intentionally empty: the registered JS control view builds the picker UI in
	 * onRender, so no server-side Underscore template is needed. The method is
	 * required because Base_Control declares content_template() abstract.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function content_template() {}
}
