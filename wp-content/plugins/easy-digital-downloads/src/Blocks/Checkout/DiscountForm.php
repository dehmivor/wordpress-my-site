<?php
/**
 * Checkout discount form block.
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
 * DiscountForm class.
 */
class DiscountForm implements SubscriberInterface {

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
	 * Register the discount form block.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function register(): void {
		if ( ! defined( 'EDD_BLOCKS_DIR' ) ) {
			return;
		}

		register_block_type(
			EDD_BLOCKS_DIR . 'build/checkout-discount-form',
			array(
				'render_callback' => array( $this, 'render' ),
			)
		);
	}

	/**
	 * Renders the checkout discount component.
	 *
	 * @since 3.7.0
	 * @param array $block_attributes The block attributes.
	 * @return string DiscountForm HTML.
	 */
	public function render( $block_attributes = array() ) {
		$classes = \EDD\Blocks\Functions\get_block_classes(
			$block_attributes,
			array(
				'wp-block-edd-checkout-discount-form',
			)
		);
		ob_start();
		echo '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		include EDD_BLOCKS_DIR . 'views/checkout/discount.php';
		echo '</div>';
		return ob_get_clean();
	}
}
