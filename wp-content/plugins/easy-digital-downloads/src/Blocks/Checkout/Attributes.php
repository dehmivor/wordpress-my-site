<?php
/**
 * Checkout Block Attributes Manager
 *
 * @package     EDD\Blocks\Checkout
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Blocks\Checkout;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Checkout\Validator;

/**
 * Manages a cached version of checkout block attributes for the current request.
 *
 * @since 3.6.0
 */
class Attributes {

	/**
	 * Cached attributes for the current request.
	 *
	 * @var array|null
	 */
	private static $cached_attributes = null;

	/**
	 * Gets checkout block attributes from the current page or a specific page.
	 *
	 * @since 3.6.0
	 * @param int|null $page_id The page ID to get attributes from.
	 * @return array The block attributes with default values.
	 */
	public static function get( $page_id = null ) {
		if ( null !== self::$cached_attributes ) {
			return self::$cached_attributes;
		}
		self::$cached_attributes = self::get_attributes_for_page( $page_id );

		return self::$cached_attributes;
	}

	/**
	 * Checks if the customer info is complete.
	 * This determines if the customer fields should be shown on the checkout form.
	 *
	 * @since 3.6.0
	 * @param array $block_attributes The block attributes.
	 * @return bool Whether the customer info is complete.
	 */
	public static function is_customer_info_complete( $block_attributes ): bool {
		if ( empty( $block_attributes['logged_in'] ) ) {
			return false;
		}

		if ( empty( $block_attributes['layout'] ) ) {
			return true;
		}

		return ! empty( $block_attributes['has_address'] ) || ! in_array( $block_attributes['layout'], array( 'half-bottom', 'two-thirds-bottom' ), true );
	}

	/**
	 * Gets attributes for a page.
	 *
	 * @since 3.6.0
	 * @param int|null $page_id The page ID, or null for current page.
	 * @return array The block attributes.
	 */
	private static function get_attributes_for_page( $page_id = null ) {
		if ( ! $page_id ) {
			$page_id = self::get_checkout_page_id();
		}

		return $page_id ? self::parse_attributes( $page_id ) : self::get_defaults();
	}

	/**
	 * Detects the checkout page ID from the current context.
	 *
	 * @since 3.6.0
	 * @return int|false The checkout page ID or false if not detected.
	 */
	private static function get_checkout_page_id() {
		// Handle AJAX requests.
		if ( edd_doing_ajax() && ! empty( $_POST['current_page'] ) ) {
			$page_id = absint( $_POST['current_page'] );

			return Validator::has_block( $page_id ) ? $page_id : false;
		}

		// Handle singular pages.
		if ( is_singular() ) {
			$page_id = get_queried_object_id();

			return Validator::has_block( $page_id ) ? $page_id : false;
		}

		$purchase_page = edd_get_option( 'purchase_page' );

		// If the current page is the purchase page, return it.
		if ( ! empty( $purchase_page ) && is_page( $purchase_page ) && Validator::has_block( $purchase_page ) ) {
			return $purchase_page;
		}

		return false;
	}

	/**
	 * Parses block attributes from post content.
	 *
	 * @since 3.6.0
	 * @param int $post_id The post ID.
	 * @return array The parsed attributes.
	 */
	private static function parse_attributes( $post_id ) {
		$post   = get_post( $post_id );
		$blocks = parse_blocks( $post->post_content );

		$checkout_block = self::find_block( $blocks, 'edd/checkout' );
		if ( ! $checkout_block ) {
			return self::get_defaults();
		}

		$attributes = wp_parse_args( $checkout_block['attrs'] ?? array(), self::get_defaults() );

		/**
		 * Cart display attributes (show_discount_form, show_header, etc.) live on the
		 * inner checkout-cart block, not the parent. Overlay them so AJAX re-renders
		 * honor the cart's settings.
		 */
		$cart_block = self::find_block( $checkout_block['innerBlocks'] ?? array(), 'edd/checkout-cart' );
		if ( $cart_block && ! empty( $cart_block['attrs'] ) ) {
			return wp_parse_args( $cart_block['attrs'], $attributes );
		}

		return $attributes;
	}

	/**
	 * Recursively locates a block by name within a list of parsed blocks.
	 *
	 * @since 3.7.0
	 * @param array  $blocks     The parsed blocks to search.
	 * @param string $block_name The block name to find.
	 * @return array|null The matching block, or null if not found.
	 */
	private static function find_block( $blocks, $block_name ) {
		foreach ( $blocks as $block ) {
			if ( $block_name === $block['blockName'] ) {
				return $block;
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::find_block( $block['innerBlocks'], $block_name );
				if ( $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Returns default attribute values.
	 *
	 * @since 3.6.0
	 * @return array The default attributes.
	 */
	private static function get_defaults() {
		return array(
			'layout'             => '',
			'show_discount_form' => true,
			'thumbnail_width'    => 25,
			'logged_in'          => is_user_logged_in() && ! \EDD\Blocks\Utility::doing_guest_preview(),
			'show_register_form' => edd_get_option( 'show_register_form' ),
		);
	}
}
