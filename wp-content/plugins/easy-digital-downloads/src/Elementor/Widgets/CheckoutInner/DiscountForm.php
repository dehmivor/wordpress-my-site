<?php
/**
 * Checkout Discount Form Elementor Widget
 *
 * Thin child section widget that renders the EDD checkout discount form by
 * including the shared discount view template.
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
use EDD\Elementor\Widgets\Traits\ConfigurableControls;
use EDD\Elementor\Widgets\CheckoutInner\Concerns\EditorPreview;
use EDD\Elementor\Widgets\CheckoutInner\Concerns\SectionGuard;

/**
 * EDD Checkout Discount Form Widget for Elementor.
 *
 * @since 3.7.0
 */
class DiscountForm extends Base {

	use ConfigurableControls;
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

		if ( ! wp_style_is( 'edd-checkout-discount-form-style', 'enqueued' ) ) {
			wp_enqueue_style(
				'edd-checkout-discount-form-style',
				EDD_BLOCKS_URL . 'build/checkout-discount-form/style-index.css',
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
		return 'edd-checkout-discount-form';
	}

	/**
	 * Register the discount form's style controls.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	protected function register_controls() {
		$this->register_controls_from_config( Controls::get_discount_form_controls() );
	}

	/**
	 * Get the selector prefix for this widget.
	 *
	 * The widget wraps the discount form, so its own wrapper is the right scope.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	protected function get_selector_prefix(): string {
		return '{{WRAPPER}}';
	}

	/**
	 * Get widget title.
	 *
	 * @since 3.7.0
	 * @return string Widget title.
	 */
	public function get_title(): string {
		return __( 'EDD Checkout Discount Form', 'easy-digital-downloads' );
	}

	/**
	 * Get widget icon.
	 *
	 * @since 3.7.0
	 * @return string Widget icon.
	 */
	public function get_icon(): string {
		return 'dashicons dashicons-tag';
	}

	/**
	 * Get widget keywords.
	 *
	 * @since 3.7.0
	 * @return array Widget keywords.
	 */
	public function get_keywords(): array {
		return array( 'edd', 'checkout', 'discount', 'coupon' );
	}

	/**
	 * Get the style dependencies for the widget.
	 *
	 * @since 3.7.0
	 * @return array The style dependencies for the widget.
	 */
	public function get_style_depends() {
		return array( 'edd-checkout-style', 'edd-checkout-discount-form-style' );
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
	 * Render the widget output on the frontend.
	 *
	 * Delegates to the shared discount view template. The buffered output is
	 * already escaped by the underlying template. The widget carries no visibility
	 * toggle: its presence in the checkout box is the gate, so a present widget
	 * always renders the discount form (subject to the render-inert and dedupe
	 * backstops below).
	 *
	 * @since 3.7.0
	 */
	protected function render() {
		// A section rendered outside an engaged checkout box renders nothing
		// (editor/preview exempt) — the render-inert backstop. See SectionGuard.
		if ( $this->is_render_inert() ) {
			return;
		}

		$this->maybe_setup_editor_preview();

		// First instance of this section type wins; a duplicate inside the engaged
		// box renders nothing (editor/preview exempt). See SectionGuard.
		if ( $this->is_duplicate_section() ) {
			return;
		}

		self::enqueue_style();

		// Wrap the shared discount template in the block's wrapper div so the
		// composed output matches the edd/checkout-discount-form block markup
		// (\EDD\Blocks\Checkout\DiscountForm::render()).
		$classes = \EDD\Blocks\Functions\get_block_classes( array(), array( 'wp-block-edd-checkout-discount-form' ) );

		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		include EDD_BLOCKS_DIR . 'views/checkout/discount.php'; // NOSONAR include (not include_once): the template must render for every DiscountForm widget instance; include_once would output nothing for a second instance on the page.
		echo '</div>';
		echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is escaped by the shared template.
	}
}
