<?php
/**
 * Checkout logged-in account line.
 *
 * @package     EDD\Blocks\Checkout
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Blocks\Checkout;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Renders the "Account Information" line for a logged-in customer.
 *
 * Shared by every checkout that renders it, so the block, inner-block and Elementor paths
 * cannot drift apart in wording or markup.
 *
 * @since 3.7.0
 */
class AccountLine {

	/**
	 * Emit the account line.
	 *
	 * Gated on the `logged_in` attribute rather than on data completeness, so a logged-in
	 * customer with incomplete details still gets the account context.
	 *
	 * @since 3.7.0
	 *
	 * @param array $attributes The resolved checkout block attributes.
	 * @return void
	 */
	public static function render( array $attributes ): void {
		if ( empty( $attributes['logged_in'] ) ) {
			return;
		}

		$customer = get_customer();
		?>
		<p class="edd-blocks__logged-in">
			<strong><?php esc_html_e( 'Account Information', 'easy-digital-downloads' ); ?>:</strong>
			&nbsp;
			<?php
			printf(
				/* translators: 1: The current user's email address, 2: opening anchor tag, do not translate, 3: closing anchor tag, do not translate. */
				esc_html__( 'You are currently logged in as %1$s. (%2$slog out%3$s)', 'easy-digital-downloads' ),
				esc_html( $customer['email'] ),
				'<a href="' . esc_url( wp_logout_url( edd_get_current_page_url() ) ) . '">',
				'</a>'
			);
			?>
		</p>
		<?php
	}
}
