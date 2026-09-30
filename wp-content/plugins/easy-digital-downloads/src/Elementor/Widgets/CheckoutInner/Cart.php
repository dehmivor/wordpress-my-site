<?php
/**
 * Checkout Cart Elementor Widget
 *
 * Thin child section widget that renders the EDD checkout cart by delegating to
 * the shared static cart render class.
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
use EDD\Elementor\Widgets\CheckoutInner\Concerns\EditorPreview;
use EDD\Elementor\Widgets\CheckoutInner\Concerns\SectionGuard;
use EDD\Elementor\Utils\Page;

/**
 * EDD Checkout Cart Widget for Elementor.
 *
 * @since 3.7.0
 */
class Cart extends Base {

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

		if ( ! wp_style_is( 'edd-checkout-cart-style', 'enqueued' ) ) {
			wp_enqueue_style(
				'edd-checkout-cart-style',
				EDD_BLOCKS_URL . 'build/checkout-cart/style-index.css',
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
		return 'edd-checkout-cart';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.7.0
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'EDD Checkout Cart', 'easy-digital-downloads' );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.7.0
	 * @return string Widget icon.
	 */
	public function get_icon(): string {
		return 'dashicons dashicons-cart';
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 3.7.0
	 * @return array Widget keywords.
	 */
	public function get_keywords(): array {
		return array( 'edd', 'checkout', 'cart' );
	}

	/**
	 * Get the style dependencies for the widget.
	 *
	 * @since 3.7.0
	 * @return array The style dependencies for the widget.
	 */
	public function get_style_depends() {
		return array( 'edd-checkout-style', 'edd-checkout-cart-style' );
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
	 * Owns the cart display toggles (section_cart minus the retired
	 * show_discount_form control) and the cart styling group.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	protected function register_controls() {
		$this->register_controls_from_config( Controls::get_cart_controls() );
	}

	/**
	 * Get the selector prefix for this widget.
	 *
	 * The cart widget wrapper wraps only the cart markup (#edd_checkout_cart_form
	 * and its #edd_checkout_cart child), so {{WRAPPER}} is the correct prefix; the
	 * source selectors' leading `form ` ancestor is mapped away below since the
	 * form element lives on the parent Container, outside this child's wrapper.
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
	 * Strips the `form ` ancestor from the cart styling selectors so they resolve
	 * relative to this child widget's wrapper.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	protected function get_selector_mappings(): array {
		return array(
			'form #edd_checkout_cart' => '#edd_checkout_cart',
		);
	}

	/**
	 * Render the widget output on the frontend.
	 *
	 * Delegates to the shared cart render class. The buffered output is already
	 * escaped by the underlying template.
	 *
	 * In the Elementor editor, activates the EDD block editor preview context and
	 * seeds sample cart items via the block checkout helper so the cart section
	 * renders preview content rather than an empty cart.
	 *
	 * @since 3.7.0
	 */
	protected function render() {
		// A section rendered outside an engaged checkout box renders nothing
		// (editor/preview exempt) — the render-inert backstop. See SectionGuard.
		if ( $this->is_render_inert() ) {
			return;
		}

		// First instance of this section type wins; a duplicate inside the engaged
		// box renders nothing (editor/preview exempt). See SectionGuard.
		if ( $this->is_duplicate_section() ) {
			return;
		}

		$this->maybe_setup_editor_preview();

		self::enqueue_style();

		$args = array();

		if ( Page::is_edit_mode() ) {
			// Seed the preview cart with the chosen sample product when "Cart Item" is
			// set, mirroring the deprecated monolith widget's setup_preview_settings().
			// get_cart_contents() below reads $_GET['cart_item']; it is set only in edit
			// mode so it can never affect a real front-end request.
			$cart_item = $this->get_settings_for_display( 'preview_cart_item' );
			if ( ! empty( $cart_item ) ) {
				$_GET['cart_item'] = $cart_item; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- editor-only preview seed, set server-side in edit mode.
			}

			$args['cart_items'] = \EDD\Blocks\Checkout\get_cart_contents();
		}

		// The DiscountForm widget is the sole coupon source on the composable
		// checkout, so the cart never renders the inline coupon (default-hidden per
		// the box's discount switcher).
		$args['block_attributes']                       = \EDD\Blocks\Checkout\Attributes::get();
		$args['block_attributes']['show_discount_form'] = false;

		ob_start();
		\EDD\Blocks\Checkout\Elements\Cart::render( $args );
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped by the shared template.
	}
}
