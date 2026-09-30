<?php
/**
 * Checkout cart block
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
 * Cart class.
 */
class Cart implements SubscriberInterface {

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
	 * Register the cart block.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'EDD_BLOCKS_DIR' ) ) {
			return;
		}

		register_block_type(
			EDD_BLOCKS_DIR . 'build/checkout-cart',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Renders the checkout cart component.
	 *
	 * @since 3.7.0
	 * @param array     $block_attributes The block attributes.
	 * @param string    $content          The block inner content.
	 * @param \WP_Block $block            The block object.
	 * @return string Cart HTML.
	 */
	public function render( $block_attributes = array(), $content = '', $block = null ) {
		// The cart block can render outside the checkout, so it removes the in-cart
		// renewal form itself rather than relying on the checkout's reposition.
		if ( \EDD\Integrations\SoftwareLicensing::should_move_renewal_form() ) {
			remove_action( 'edd_after_checkout_cart', 'edd_sl_renewal_form' );
		}
		$block_attributes = wp_parse_args(
			$block_attributes,
			array(
				'show_header'            => true,
				'show_thumbnails'        => true,
				'thumbnail_width'        => 25,
				'show_quantity_controls' => true,
				'show_discount_form'     => true,
			)
		);

		$cart_items = get_cart_contents( $block );
		if ( ! $cart_items && ! edd_cart_has_fees() ) {
			return '<p class="edd-empty-cart">' . edd_empty_cart_message() . '</p>';
		}

		ob_start();
		Elements\Cart::render(
			array(
				'block_attributes' => $block_attributes,
				'cart_items'       => $cart_items,
			)
		);
		return ob_get_clean();
	}
}
