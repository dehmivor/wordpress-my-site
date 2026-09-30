<?php
/**
 * Checkout payment info block.
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
use EDD\Blocks\Utility;

/**
 * PaymentInfo class.
 */
class PaymentInfo implements SubscriberInterface {

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_subscribed_events(): array {
		return array(
			'init'                     => 'register',
			'edd_checkout_form_bottom' => 'render_fallback',
		);
	}

	/**
	 * Register the payment info block.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'EDD_BLOCKS_DIR' ) ) {
			return;
		}

		register_block_type(
			EDD_BLOCKS_DIR . 'build/checkout-payment-info',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Renders the checkout cart component.
	 *
	 * @since 3.7.0
	 * @since 3.7.1 Passes `edit_posts` explicitly to `Utility::is_block_editor()`.
	 * @param array     $block_attributes The block attributes.
	 * @param string    $content          The block inner content.
	 * @param \WP_Block $block            The block object.
	 * @return string PaymentInfo HTML.
	 */
	public function render( $block_attributes = array(), $content = '', $block = null ) {
		ob_start();

		// Don't attempt to render the fallback hook in the block editor; it will duplicate fields.
		// Only fire it here if there's no personal-info block to own it.
		if ( ! Utility::is_block_editor( 'edit_posts', $block ) && ! \EDD\Checkout\Validator::has_block( null, 'edd/checkout-personal-info' ) ) {
			Utility::do_checkout_form_top( $block_attributes );
		}

		Elements\PaymentDetails::render( $block_attributes, $block );
		return ob_get_clean();
	}

	/**
	 * Render the payment details as a fallback in case the block is missing.
	 *
	 * @since 3.7.0
	 * @param array $block_attributes The block attributes.
	 * @return void
	 */
	public function render_fallback( $block_attributes = array() ) {
		if ( ! did_action( 'edd_blocks_checkout_inner_blocks' ) ) {
			return;
		}

		if ( \EDD\Checkout\Validator::has_block( null, 'edd/checkout-payment-info' ) ) {
			return;
		}

		Elements\PaymentDetails::render( $block_attributes );
	}
}
