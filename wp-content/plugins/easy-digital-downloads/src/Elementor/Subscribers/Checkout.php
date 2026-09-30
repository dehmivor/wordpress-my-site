<?php
/**
 * EDD Checkout Subscriber
 *
 * @package     EDD\Elementor\Subscribers
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Elementor\Subscribers;

use EDD\EventManagement\SubscriberInterface;
use EDD\Elementor\Utils\Page;

/**
 * Class Checkout
 *
 * Handles the pieces of Elementor checkout support that the plain-content block
 * markers do not cover: it enqueues the editor preview styles and prevents the
 * edd/checkout Gutenberg block (and its inner edd/checkout-* markers) from
 * rendering when an Elementor checkout is present on the page.
 *
 * @package EDD\Elementor\Subscribers
 */
class Checkout implements SubscriberInterface {

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.6.0
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'elementor/preview/enqueue_scripts' => 'enqueue_preview_assets',
			'pre_render_block'                  => array( 'prevent_checkout_block_render', 10, 3 ),
		);
	}

	/**
	 * Enqueue editor styles for Elementor Checkout Widget.
	 *
	 * @since 3.6.0
	 */
	public function enqueue_preview_assets() {
		\EDD\Elementor\Widgets\Checkout::enqueue_style();
	}

	/**
	 * Prevent the checkout block from rendering.
	 *
	 * Suppresses the edd/checkout Gutenberg block and its inner edd/checkout-*
	 * markers when an Elementor checkout widget or checkout box is present on the
	 * page. The markers stay in post_content (has_block(), Attributes, and core
	 * purchase-field de-dup all read post_content statically, so those are
	 * unaffected); only their render output is short-circuited. This stops the
	 * throwaway do_blocks pass from firing edd_cc_billing_top before the visible
	 * Elementor pass renders the billing address, so the address renders exactly
	 * once on the composable Elementor checkout.
	 *
	 * @since 3.6.0
	 * @since 3.7.0 Extended to the edd-checkout-box container and the four inner edd/checkout-* markers.
	 *
	 * @param string|null $pre_render   Short-circuit value for block rendering.
	 * @param array       $parsed_block Parsed block data.
	 * @param WP_Block    $parent_block Parent block instance.
	 * @return string|null
	 */
	public function prevent_checkout_block_render( $pre_render, $parsed_block, $parent_block ) {
		$checkout_blocks = array(
			'edd/checkout',
			'edd/checkout-personal-info',
			'edd/checkout-payment-info',
			'edd/checkout-cart',
			'edd/checkout-discount-form',
		);
		if ( ! in_array( $parsed_block['blockName'], $checkout_blocks, true ) ) {
			return $pre_render;
		}

		if ( Page::has_widget( 'edd-checkout' ) || Page::has_widget( 'edd-checkout-box' ) ) {
			return '';
		}

		return $pre_render;
	}
}
