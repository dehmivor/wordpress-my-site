<?php
/**
 * Checkout Payment Info Elementor Widget
 *
 * Thin child section widget that renders the EDD checkout payment info section
 * by delegating to the shared static payment details render class. This widget
 * owns the #edd_purchase_form_wrap element; the nonce and hidden fields are NOT
 * emitted here, as they arrive with the gateway-loaded content.
 *
 * @package     EDD\Elementor\Widgets\CheckoutInner
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Widgets\CheckoutInner;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Widgets\Base;
use EDD\Elementor\Widgets\Config\CheckoutInner\Controls;
use EDD\Elementor\Utils\Page;
use EDD\Elementor\Widgets\CheckoutInner\Concerns\EditorPreview;
use EDD\Elementor\Widgets\CheckoutInner\Concerns\SectionGuard;

/**
 * EDD Checkout Payment Info Widget for Elementor.
 *
 * @since 3.7.0
 */
class PaymentInfo extends Base {

	use \EDD\Elementor\Widgets\Traits\ConfigurableControls;
	use EditorPreview;
	use SectionGuard;

	/**
	 * Enqueue the checkout style.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public static function enqueue_style() {
		if ( ! wp_style_is( 'edd-checkout-style', 'enqueued' ) ) {
			wp_enqueue_style(
				'edd-checkout-style',
				EDD_BLOCKS_URL . 'build/checkout/style-index.css',
				array(),
				EDD_VERSION
			);
		}

		if ( ! wp_style_is( 'edd-checkout-payment-info-style', 'enqueued' ) ) {
			wp_enqueue_style(
				'edd-checkout-payment-info-style',
				EDD_BLOCKS_URL . 'build/checkout-payment-info/style-index.css',
				array(),
				EDD_VERSION
			);
		}
	}

	/**
	 * Get widget name.
	 *
	 * @since 3.7.0
	 * @return string Widget name.
	 */
	public function get_name(): string {
		return 'edd-checkout-payment-info';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.7.0
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'EDD Checkout Payment Info', 'easy-digital-downloads' );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.7.0
	 * @return string Widget icon.
	 */
	public function get_icon(): string {
		return 'dashicons dashicons-money-alt';
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 3.7.0
	 * @return array Widget keywords.
	 */
	public function get_keywords(): array {
		return array( 'edd', 'checkout', 'payment', 'gateway' );
	}

	/**
	 * Get the style dependencies for the widget.
	 *
	 * @since 3.7.0
	 * @return array The style dependencies for the widget.
	 */
	public function get_style_depends() {
		return array( 'edd-checkout-style', 'edd-checkout-payment-info-style' );
	}

	/**
	 * Get the script dependencies for the widget.
	 *
	 * Returns the checkout global and AJAX scripts needed by the gateway reload
	 * mechanism that targets #edd_purchase_form_wrap. The guard suppresses these
	 * scripts in the editor preview so front-end checkout JS is not loaded there.
	 *
	 * @since 3.7.0
	 * @return array The script dependencies, or an empty array in edit mode.
	 */
	public function get_script_depends() {
		if ( Page::is_edit_mode() ) {
			return array();
		}

		return array( 'edd-checkout-global', 'edd-ajax' );
	}

	/**
	 * Hide the checkout section widget from the Add-widget panel.
	 *
	 * The composable checkout sections are seeded into the edd-checkout-box, not
	 * placed individually; hiding them from the panel prevents a loose section
	 * being dropped onto a page where it cannot render safely: a loose section has
	 * no purchase form wrapping it, and gateway JS that does
	 * getElementById( 'edd_purchase_form' ) then scopes into it would find nothing
	 * to scope to. Existing
	 * saved instances continue to register, render, and edit; this only removes the
	 * widget from the panel so it is not chosen for new layouts.
	 *
	 * @since 3.7.0
	 * @return bool
	 */
	public function show_in_panel(): bool {
		return false;
	}

	/**
	 * Register widget controls.
	 *
	 * Owns the payment-method and card title toggles, the purchase button styling
	 * group, and the payment-method and card section styling. The personal-info
	 * and billing-details titles are excluded (they live on the Personal Info
	 * widget, which now owns the billing address).
	 *
	 * @since 3.7.0
	 * @return void
	 */
	protected function register_controls() {
		$this->register_controls_from_config( Controls::get_payment_info_controls() );
	}

	/**
	 * Get the selector prefix for this widget.
	 *
	 * This widget owns the gateway content wrapper (#edd_purchase_form_wrap),
	 * which contains the payment-method and card fieldsets and the purchase
	 * button, so {{WRAPPER}} is the correct prefix. The source selectors' `form `
	 * and `form#edd_purchase_form ` ancestors are mapped away below since the form
	 * element lives on the parent Container. The billing-address (#edd_cc_address)
	 * selector lives on the Personal Info widget, which now owns that fieldset.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	protected function get_selector_prefix(): string {
		return '{{WRAPPER}}';
	}

	/**
	 * Get the selector mappings for this widget.
	 *
	 * Swaps the form ancestors (owned by the parent Container) for this widget's own
	 * `#edd_purchase_form_wrap`. A bare id would drop the selector to one id and lose to EDD's own
	 * two-id rules in edd.min.css; keeping a second id restores those controls.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	protected function get_selector_mappings(): array {
		return array(
			// The mode selector renders outside #edd_purchase_form_wrap, so it has no second id
			// here to anchor on: repeating its own matches EDD's two-id rule without !important.
			'form#edd_purchase_form #edd_payment_mode_select' => '#edd_payment_mode_select#edd_payment_mode_select',
			'form#edd_purchase_form #edd_cc_fields' => '#edd_purchase_form_wrap #edd_cc_fields',
			'form #edd-purchase-button'             => '#edd_purchase_form_wrap #edd-purchase-button',
		);
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Delegates to the shared payment details render class, which owns the
	 * #edd_purchase_form_wrap element (empty on initial load; the gateway content
	 * fills it via AJAX). The nonce and hidden fields are intentionally NOT
	 * emitted here. The buffered output is already escaped by the underlying
	 * template.
	 *
	 * @since 3.7.0
	 */
	protected function render() {
		// A section rendered outside an engaged checkout box renders nothing
		// (editor/preview exempt) — the render-inert backstop. A loose payment-info
		// widget must not emit its gateway selector / #edd_purchase_form_wrap with
		// no purchase form around it — gateway JS that does
		// getElementById( 'edd_purchase_form' ) then scopes into it would crash. See
		// SectionGuard.
		if ( $this->is_render_inert() ) {
			return;
		}

		// First instance of this section type wins; a duplicate inside the engaged
		// box renders nothing (editor/preview exempt). This is the fix for the
		// duplicate payment-info defect: a second gateway selector /
		// #edd_purchase_form_wrap / duplicated field ids break the gateway switch.
		if ( $this->is_duplicate_section() ) {
			return;
		}

		$this->maybe_setup_editor_preview();

		self::enqueue_style();

		$attrs = \EDD\Blocks\Checkout\Attributes::get();

		// In the editor there is no live cart, so the gateway selector
		// (#edd_payment_mode_select) is never rendered — leaving the payment-method
		// title toggle with no target to preview. The shared renderer shows the
		// selector only when edd_show_gateways() AND edd_get_cart_total() > 0
		// (PaymentDetails::do_details()), and both are false with no cart, so force
		// them on for the editor preview only. maybe_setup_editor_preview() self-guards
		// internally and render() continues past it, so this needs its own explicit
		// edit-mode guard; both filters are removed immediately after the delegated
		// render so they can never affect a real front-end request.
		$force_preview = Page::is_edit_mode();
		$force_total   = null;
		if ( $force_preview ) {
			add_filter( 'edd_show_gateways', '__return_true' );
			// Give the preview a nominal positive total so the selector's cart-total
			// gate passes; real front-end totals are untouched (edit mode only).
			$force_total = static function ( $total ) {
				return $total > 0 ? $total : 1;
			};
			add_filter( 'edd_get_cart_total', $force_total );
		}

		ob_start();
		\EDD\Blocks\Checkout\Elements\PaymentDetails::render( $attrs, null );
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped by the shared template.

		if ( $force_preview ) {
			remove_filter( 'edd_show_gateways', '__return_true' );
			remove_filter( 'edd_get_cart_total', $force_total );
		}
	}
}
