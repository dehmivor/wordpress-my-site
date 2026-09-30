<?php
/**
 * Cart total template.
 *
 * @package     EDD\Blocks\Checkout
 * @copyright   Copyright (c) Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 *
 * @var bool  $is_checkout_block Whether this is rendered in the context of the checkout block.
 * @var array $block_attributes  The block attributes.
 */

?>

<div class="edd-blocks-cart__row edd-blocks-cart__row-footer edd_cart_footer_row">
	<?php
	if ( $is_checkout_block && ! empty( $block_attributes['show_discount_form'] ) ) {
		include EDD_BLOCKS_DIR . 'views/checkout/discount.php';
	}
	?>
	<div class="edd_cart_total">
		<?php esc_html_e( 'Total', 'easy-digital-downloads' ); ?>: <span class="edd_cart_amount" data-subtotal="<?php echo esc_attr( edd_get_cart_subtotal() ); ?>" data-total="<?php echo esc_attr( edd_get_cart_total() ); ?>"><?php edd_cart_total(); // Escaped. ?></span>
	</div>
</div>
