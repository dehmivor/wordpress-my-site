<?php
/**
 * Checkout block layout patterns.
 *
 * @package     EDD\Blocks\Checkout
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Blocks\Checkout;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Registers block patterns for the checkout block.
 *
 * @since 3.7.0
 */
class Patterns implements SubscriberInterface {

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return array(
			'init' => 'register',
		);
	}

	/**
	 * Register the checkout layout patterns.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function register(): void {
		foreach ( $this->get_patterns() as $slug => $pattern ) {
			register_block_pattern( $slug, $pattern );
		}
	}

	/**
	 * Public accessor for the checkout layout pattern definitions.
	 *
	 * Exposes the same array the private get_patterns() returns (slug => title,
	 * blockTypes, serialized-markup content) so a single-source consumer — the
	 * Elementor checkout box's layout-picker drift-guard — can read the block's
	 * canonical pattern set instead of hand-copying it. The keys are namespaced
	 * (`edd-checkout/…`); a consumer comparing against bare Elementor slugs must
	 * strip the `edd-checkout/` prefix first.
	 *
	 * @since 3.7.0
	 * @return array The pattern definitions keyed by namespaced slug.
	 */
	public function get_layout_patterns(): array {
		return $this->get_patterns();
	}

	/**
	 * Get the pattern definitions.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private function get_patterns(): array {
		return array(
			'edd-checkout/single-column'      => array(
				'title'      => esc_html__( 'Single Column', 'easy-digital-downloads' ),
				'blockTypes' => array( 'edd/checkout' ),
				'content'    => $this->single_column(),
			),
			'edd-checkout/two-column-50-50'   => array(
				'title'      => esc_html__( 'Two Columns: 50/50', 'easy-digital-downloads' ),
				'blockTypes' => array( 'edd/checkout' ),
				'content'    => $this->two_column( '50%', '50%' ),
			),
			'edd-checkout/two-column-70-30'   => array(
				'title'      => esc_html__( 'Two Columns: 70/30', 'easy-digital-downloads' ),
				'blockTypes' => array( 'edd/checkout' ),
				'content'    => $this->two_column( '70%', '30%' ),
			),
			'edd-checkout/two-column-80-20'   => array(
				'title'      => esc_html__( 'Two Columns: 80/20', 'easy-digital-downloads' ),
				'blockTypes' => array( 'edd/checkout' ),
				'content'    => $this->two_column( '80%', '20%' ),
			),
			'edd-checkout/cart-top-two-column' => array(
				'title'      => esc_html__( 'Cart Top, Two-Column Form', 'easy-digital-downloads' ),
				'blockTypes' => array( 'edd/checkout' ),
				'content'    => $this->cart_top_two_column(),
			),
		);
	}

	/**
	 * Serialized block markup for the single-column layout.
	 * Cart stacked above the purchase form.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	private function single_column(): string {
		return '<!-- wp:edd/checkout-cart -->
<div class="wp-block-edd-checkout-cart"></div>
<!-- /wp:edd/checkout-cart -->

<!-- wp:edd/checkout-personal-info -->
<div class="wp-block-edd-checkout-personal-info"></div>
<!-- /wp:edd/checkout-personal-info -->

<!-- wp:edd/checkout-payment-info -->
<div class="wp-block-edd-checkout-payment-info"></div>
<!-- /wp:edd/checkout-payment-info -->';
	}

	/**
	 * Serialized block markup for a two-column layout.
	 * Purchase form on the left, cart on the right.
	 *
	 * @since 3.7.0
	 * @param string $form_width CSS width for the purchase form column (e.g. "70%").
	 * @param string $cart_width CSS width for the cart column (e.g. "30%").
	 * @return string
	 */
	private function two_column( string $form_width, string $cart_width ): string {
		$form_attr = ( '50%' === $form_width ) ? '' : sprintf( ' {"width":"%s"}', $form_width );
		$cart_attr = ( '50%' === $cart_width ) ? '' : sprintf( ' {"width":"%s"}', $cart_width );

		$form_style = ( '50%' === $form_width ) ? '' : sprintf( ' style="flex-basis:%s"', $form_width );
		$cart_style = ( '50%' === $cart_width ) ? '' : sprintf( ' style="flex-basis:%s"', $cart_width );

		return sprintf(
			'<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column%s -->
<div class="wp-block-column"%s><!-- wp:edd/checkout-personal-info -->
<div class="wp-block-edd-checkout-personal-info"></div>
<!-- /wp:edd/checkout-personal-info -->

<!-- wp:edd/checkout-payment-info -->
<div class="wp-block-edd-checkout-payment-info"></div>
<!-- /wp:edd/checkout-payment-info --></div>
<!-- /wp:column -->

<!-- wp:column%s -->
<div class="wp-block-column"%s><!-- wp:edd/checkout-cart -->
<div class="wp-block-edd-checkout-cart"></div>
<!-- /wp:edd/checkout-cart --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->',
			$form_attr,
			$form_style,
			$cart_attr,
			$cart_style
		);
	}

	/**
	 * Serialized block markup for the cart-top, two-column form layout.
	 * Cart spans full width; personal info and payment info sit side by side inside the purchase form.
	 *
	 * @since 3.7.0
	 * @return string
	 */
	private function cart_top_two_column(): string {
		return '<!-- wp:edd/checkout-cart -->
<div class="wp-block-edd-checkout-cart"></div>
<!-- /wp:edd/checkout-cart -->

<!-- wp:columns -->
<div class="wp-block-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:edd/checkout-personal-info -->
<div class="wp-block-edd-checkout-personal-info"></div>
<!-- /wp:edd/checkout-personal-info --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:edd/checkout-payment-info -->
<div class="wp-block-edd-checkout-payment-info"></div>
<!-- /wp:edd/checkout-payment-info --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->';
	}
}
