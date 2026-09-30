<?php
/**
 * Saving and restoring a cart.
 *
 * @package     EDD\Cart
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Cart;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * SavedCart class.
 *
 * @since 3.7.1
 */
class SavedCart {

	/**
	 * The cart this saves and restores.
	 *
	 * @since 3.7.1
	 * @var \EDD_Cart
	 */
	private $cart;

	/**
	 * SavedCart constructor.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD_Cart $cart The cart this saves and restores.
	 */
	public function __construct( \EDD_Cart $cart ) {
		$this->cart = $cart;
	}

	/**
	 * Save Cart
	 *
	 * @since 2.7
	 * @since 3.7.1 A guest's cart token is signed with the store's own key.
	 *
	 * @return bool
	 */
	public function save() {

		// Bail if carts cannot be saved.
		if ( ! $this->cart->is_saving_enabled() ) {
			return false;
		}

		// Get cart & cart token.
		$cart = EDD()->session->get( 'edd_cart' );

		if ( empty( $cart ) ) {
			return false;
		}

		$token = $this->generate_token();

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			update_user_meta( $user_id, 'edd_saved_cart', $cart, false );
			update_user_meta( $user_id, 'edd_cart_token', $token, false );
		} else {
			$expiration = time() + WEEK_IN_SECONDS;
			$cart       = wp_json_encode( $cart );

			// A guest's saved cart is a cookie, so its token has to be one only the store can mint.
			$token = $this->get_saved_cart_token( $cart );

			\EDD\Utils\Cookies::set( 'edd_saved_cart', $cart, $expiration );
			\EDD\Utils\Cookies::set( 'edd_cart_token', $token, $expiration );
		}

		// Get all cart messages.
		$messages = EDD()->session->get( 'edd_cart_messages' );

		// Make sure it's an array, if empty.
		if ( empty( $messages ) ) {
			$messages = array();
		}

		$restore_url = esc_url( edd_get_checkout_uri() . '?edd_action=restore_cart&edd_cart_token=' . urlencode( $token ) );

		// Add the success message.
		$messages['edd_cart_save_successful'] = sprintf(
			'<strong>%1$s</strong>: %2$s',
			__( 'Success', 'easy-digital-downloads' ),
			sprintf(
				/* translators: 1: Opening anchor tag for the restore link, 2: Closing anchor tag. */
				__( 'Cart saved successfully. You can restore your cart using %1$sthis URL%2$s.', 'easy-digital-downloads' ),
				'<a href="' . $restore_url . '">',
				'</a>'
			)
		);

		// Set these messages in the session.
		EDD()->session->set( 'edd_cart_messages', $messages );

		// Return if cart saved.
		return ! empty( $cart );
	}

	/**
	 * Restore Cart
	 *
	 * @since 2.7
	 * @since 3.7.1 A guest's cart restores through a store-signed token, rebuilt via
	 *                       `EDD_Cart::add()`. A request with nothing saved for it is refused,
	 *                       and `edd_get_cart_token` does not apply on the guest path — a
	 *                       guest's token is checked against the store's signature, a logged in
	 *                       customer's against their own user meta.
	 *
	 * @return bool|\WP_Error
	 */
	public function restore() {
		if ( ! $this->cart->is_saving_enabled() ) {
			return false;
		}

		$messages = EDD()->session->get( 'edd_cart_messages' );
		if ( ! is_array( $messages ) ) {
			$messages = array();
		}

		$user_id       = get_current_user_id();
		$saved_cart    = get_user_meta( $user_id, 'edd_saved_cart', true );
		$token         = $this->get_token();
		$request_token = isset( $_GET['edd_cart_token'] ) ? sanitize_text_field( wp_unslash( $_GET['edd_cart_token'] ) ) : '';
		$rebuild       = false;

		if ( is_user_logged_in() && $saved_cart ) {
			$invalid_token = '' !== $request_token && ! hash_equals( (string) $token, $request_token );

			if ( $invalid_token ) {
				$messages['edd_cart_restoration_failed'] = sprintf( '<strong>%1$s</strong>: %2$s', __( 'Error', 'easy-digital-downloads' ), __( 'Cart restoration failed. Invalid token.', 'easy-digital-downloads' ) );
				EDD()->session->set( 'edd_cart_messages', $messages );
			}

			delete_user_meta( $user_id, 'edd_saved_cart' );
			delete_user_meta( $user_id, 'edd_cart_token' );

			if ( $invalid_token ) {
				return new \WP_Error( 'invalid_cart_token', __( 'The cart cannot be restored. Invalid token.', 'easy-digital-downloads' ) );
			}
		} elseif ( ! is_user_logged_in() && isset( $_COOKIE['edd_saved_cart'] ) ) {
			$saved_cart_json = stripslashes( $_COOKIE['edd_saved_cart'] );

			// A guest's token has to be one the store signed for this exact cart.
			if ( ! $this->is_saved_cart_token_valid( $request_token, $saved_cart_json ) ) {
				$messages['edd_cart_restoration_failed'] = sprintf( '<strong>%1$s</strong>: %2$s', __( 'Error', 'easy-digital-downloads' ), __( 'Cart restoration failed. Invalid token.', 'easy-digital-downloads' ) );
				EDD()->session->set( 'edd_cart_messages', $messages );

				return new \WP_Error( 'invalid_cart_token', __( 'The cart cannot be restored. Invalid token.', 'easy-digital-downloads' ) );
			}

			$saved_cart = json_decode( $saved_cart_json, true );
			$rebuild    = true;

			\EDD\Utils\Cookies::set( 'edd_saved_cart' );
			\EDD\Utils\Cookies::set( 'edd_cart_token' );
		} else {
			// Nothing was saved for this requester, so there is nothing to restore.
			return false;
		}

		if ( ! is_array( $saved_cart ) ) {
			return new \WP_Error( 'invalid_saved_cart', __( 'The cart cannot be restored.', 'easy-digital-downloads' ) );
		}

		if ( $rebuild ) {
			$saved_cart = $this->rebuild_restored_cart( $saved_cart );

			// A saved product which is no longer purchasable is dropped, so the rebuild can
			// come back empty. Nothing was restored, so no success message is shown.
			if ( empty( $saved_cart ) ) {
				return false;
			}
		}

		$messages['edd_cart_restoration_successful'] = sprintf( '<strong>%1$s</strong>: %2$s', __( 'Success', 'easy-digital-downloads' ), __( 'Cart restored successfully.', 'easy-digital-downloads' ) );
		EDD()->session->set( 'edd_cart', $saved_cart );
		EDD()->session->set( 'edd_cart_messages', $messages );

		// The cart instance also has to reflect what the session now holds.
		$this->cart->contents = $saved_cart;

		return true;
	}

	/**
	 * Retrieve a saved cart token. Used in validating saved carts
	 *
	 * @since 2.7
	 * @return int
	 */
	public function get_token() {
		$user_id = get_current_user_id();

		if ( is_user_logged_in() ) {
			$token = get_user_meta( $user_id, 'edd_cart_token', true );
		} else {
			// The cookie save() writes, not derived from the cart cookie beside it.
			$token = isset( $_COOKIE['edd_cart_token'] ) ? $_COOKIE['edd_cart_token'] : false;
		}

		return apply_filters( 'edd_get_cart_token', $token, $user_id );
	}

	/**
	 * Generate URL token to restore the cart via a URL
	 *
	 * @since 2.7
	 * @return int
	 */
	public function generate_token() {
		return apply_filters( 'edd_generate_cart_token', bin2hex( random_bytes( 20 ) ) );
	}

	/**
	 * Gets the token which authorizes restoring a guest's saved cart.
	 *
	 * Signed with the store's own key, over the saved cart's own contents.
	 *
	 * @since 3.7.1
	 *
	 * @param string $cart The JSON encoded cart the token is for.
	 * @return string
	 */
	private function get_saved_cart_token( $cart ) {
		return \EDD\Utils\Tokenizer::tokenize( $this->get_saved_cart_token_data( $cart ) );
	}

	/**
	 * Whether the supplied token authorizes restoring the supplied guest cart.
	 *
	 * @since 3.7.1
	 *
	 * @param string $token The token from the request.
	 * @param string $cart  The JSON encoded cart from the cookie.
	 * @return bool
	 */
	private function is_saved_cart_token_valid( $token, $cart ) {
		if ( empty( $token ) || empty( $cart ) ) {
			return false;
		}

		return \EDD\Utils\Tokenizer::is_token_valid( $token, $this->get_saved_cart_token_data( $cart ) );
	}

	/**
	 * The data a saved cart token is generated from.
	 *
	 * @since 3.7.1
	 *
	 * @param string $cart The JSON encoded cart.
	 * @return string
	 */
	private function get_saved_cart_token_data( $cart ) {
		return 'edd-restore-cart-' . $cart;
	}

	/**
	 * Rebuilds a restored cart by adding each of its items to an empty cart.
	 *
	 * A saved cart arrives as data rather than as something add() produced, so its items are
	 * added again: that resolves each item's price option against the product's real price
	 * options, drops products which can no longer be purchased, and stamps the item hash.
	 *
	 * @since 3.7.1
	 *
	 * @param array $saved_cart The saved cart's items.
	 * @return array The rebuilt cart contents.
	 */
	private function rebuild_restored_cart( $saved_cart ) {
		$this->cart->contents = array();
		EDD()->session->set( 'edd_cart', array() );

		foreach ( $saved_cart as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}

			$options = ! empty( $item['options'] ) && is_array( $item['options'] ) ? $item['options'] : array();
			if ( isset( $item['quantity'] ) ) {
				$options['quantity'] = $item['quantity'];
			}

			$this->cart->add( $item['id'], $options );
		}

		return $this->cart->contents;
	}
}
