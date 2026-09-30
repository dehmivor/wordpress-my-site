<?php
/**
 * Cart template for the checkout block.
 *
 * @package     EDD\Blocks\Checkout
 * @copyright   Copyright (c) Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 *
 * @var array $block_attributes The block attributes.
 * @var array $cart_items       The items in the cart.
 * @var bool  $is_cart_widget   Whether this cart is being rendered in the context of the cart widget or the full checkout block.
 */

use EDD\Blocks\Checkout\Functions as CheckoutFunctions;

// Ensure display attributes default to true for callers that don't define them (e.g. the legacy checkout block).
$block_attributes = wp_parse_args(
	$block_attributes,
	array(
		'show_header'            => true,
		'show_quantity_controls' => true,
	)
);

?>
<div id="edd_checkout_cart_form" class="edd-blocks-form__cart">
	<?php
	// The full checkout cart (not the mini widget) shows the header, quantity, and discount form.
	$is_checkout_block = empty( $is_cart_widget );
	$cart_classes      = array(
		'edd-blocks-cart',
		'ajaxed',
	);
	?>
	<div id="edd_checkout_cart" class="<?php echo esc_attr( implode( ' ', $cart_classes ) ); ?>">
		<?php if ( $is_checkout_block && ! empty( $block_attributes['show_header'] ) ) : ?>
			<div class="edd-blocks-cart__row edd-blocks-cart__row-header edd_cart_header_row">
				<div class="edd_cart_item_name"><?php esc_html_e( 'Item Name', 'easy-digital-downloads' ); ?></div>
				<div class="edd_cart_item_price"><?php esc_html_e( 'Item Price', 'easy-digital-downloads' ); ?></div>
			</div>
			<?php
		endif;
		do_action( 'edd_cart_items_before' );
		if ( $cart_items ) {
			?>
			<div class="edd-blocks-cart__items">
			<?php
			foreach ( $cart_items as $key => $item ) {
				include 'cart-item.php';
			}
			?>
			</div>
			<?php
		}
		do_action( 'edd_cart_items_middle' );
		if ( edd_cart_has_fees() ) {
			include 'cart-fees.php';
		}
		CheckoutFunctions\do_cart_action( 'edd_cart_items_after' );

		if ( edd_use_taxes() && ! edd_prices_include_tax() ) {
			include 'cart-subtotal.php';
		}

		require 'cart-discounts.php';

		if ( edd_use_taxes() ) {
			include 'cart-taxes.php';
		}

		require 'cart-total.php';

		if ( has_action( 'edd_cart_footer_buttons' ) ) {
			include 'cart-footer-row.php';
		}
		?>
	</div>
</div>
