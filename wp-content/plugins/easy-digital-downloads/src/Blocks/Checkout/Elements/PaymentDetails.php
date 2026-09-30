<?php
/**
 * Checkout Block Elements Payment Details
 *
 * @package     EDD\Blocks\Checkout\Elements
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Blocks\Checkout\Elements;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Payment Details class.
 *
 * @since 3.6.0
 */
class PaymentDetails {

	/**
	 * Outputs the payment details form for checkout.
	 *
	 * @since 3.6.0
	 * @param array     $block_attributes The block attributes.
	 * @param \WP_Block $block            The block object.
	 * @return void
	 */
	public static function render( $block_attributes, $block = null ) {
		?>
		<div class="edd-blocks__payment-details">
			<?php self::do_details( $block_attributes, $block ); ?>
		</div>
		<?php
	}

	/**
	 * Outputs the payment details for the checkout form.
	 *
	 * @since 3.6.0
	 * @param array     $block_attributes The block attributes.
	 * @param \WP_Block $block The block object.
	 * @return void
	 */
	private static function do_details( $block_attributes, $block = null ) {
		$show_gateways = edd_show_gateways();
		if ( $show_gateways && edd_get_cart_total() > 0 ) {
			include EDD_BLOCKS_DIR . 'views/checkout/purchase-form/gateways.php';
		}

		if ( \EDD\Blocks\Utility::is_block_editor( 'edit_shop_payments', $block ) ) {
			?>
			<div id="edd_purchase_form_wrap">
				<?php
				printf( '<p class="description">%s</p>', esc_html__( 'This is a sample credit card form.', 'easy-digital-downloads' ) );
				include EDD_BLOCKS_DIR . 'views/checkout/purchase-form/credit-card.php';
				if ( ! $block instanceof \WP_Block || 'edd/checkout-payment-info' !== $block->name ) {
					\EDD\Blocks\Utility::do_preview_purchase_button();
				}
				?>
			</div>
			<?php
			return;
		}

		if ( ! $show_gateways ) {
			do_action( 'edd_purchase_form' );
			return;
		}

		?>
		<div id="edd_purchase_form_wrap"></div>
		<?php
	}
}
