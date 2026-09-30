<?php
/**
 * Add to Cart Redirect Cleanup
 *
 * Scrubs the `discount` and add-to-cart plumbing query args from the URL once a
 * request has landed, after `init` has already re-applied the discount to the
 * cart. This lets a `?discount=CODE` buy link survive the add-to-cart redirect
 * long enough to be re-applied (the #2609 fix) without the code lingering in the
 * URL indefinitely.
 *
 * @package     EDD\Cart
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Cart;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Add to Cart Redirect Cleanup subscriber.
 *
 * @since 3.7.0
 */
class AddToCartRedirectCleanup implements SubscriberInterface {

	/**
	 * The query arg that carries a preset discount from a buy link.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const DISCOUNT_ARG = 'discount';

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'template_redirect' => array( 'clean_url', 100 ),
		);
	}

	/**
	 * Removes the preset-discount and add-to-cart plumbing args from the URL.
	 *
	 * The priority (> 10) is load-bearing: the delayed add-to-cart action
	 * redirects and exits at `template_redirect` priority 10, so this never runs
	 * on the add-to-cart request itself. It only runs on the request the redirect
	 * lands on, where `edd_apply_preset_discount()` (init:999) has already consumed
	 * the discount, so scrubbing the URL here is safe. See
	 * https://github.com/awesomemotive/easy-digital-downloads-pro/issues/2609.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function clean_url() {

		// Non-page front-end requests never need URL cleanup.
		if ( is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		$url    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		$target = self::get_redirect_target( $method, $url );
		if ( '' !== $target ) {
			edd_redirect( $target );
		}
	}

	/**
	 * Determines the URL to redirect to in order to clean the current request.
	 *
	 * Returns an empty string when there is nothing to do: non-GET requests (a
	 * POST add-to-cart is already redirected and carries its discount in the
	 * body), requests without the discount arg (the trigger and loop-breaker),
	 * or when removing the args would not change the URL.
	 *
	 * @since 3.7.0
	 *
	 * @param string $method The request method.
	 * @param string $url    The current request URL (may be relative).
	 * @return string The cleaned URL to redirect to, or an empty string.
	 */
	public static function get_redirect_target( string $method, string $url ) {
		if ( 'get' !== strtolower( $method ) ) {
			return '';
		}

		if ( ! self::url_has_preset_discount( $url ) ) {
			return '';
		}

		return self::get_cleaned_url( $url );
	}

	/**
	 * Builds the URL with the preset-discount and add-to-cart plumbing args removed.
	 *
	 * The removal set deliberately includes `discount` — unlike the two call sites
	 * in edd_process_add_to_cart(), which keep it so it survives to this landing.
	 *
	 * @since 3.7.0
	 *
	 * @param string $url URL to clean.
	 * @return string Cleaned URL, or an empty string if nothing changed.
	 */
	public static function get_cleaned_url( string $url ) {
		$removable = array_merge( edd_cart_removable_query_args(), array( self::DISCOUNT_ARG ) );
		$cleaned   = remove_query_arg( $removable, $url );

		return $cleaned === $url ? '' : $cleaned;
	}

	/**
	 * Determines whether a URL carries the preset-discount query arg.
	 *
	 * @since 3.7.0
	 *
	 * @param string $url URL to inspect.
	 * @return bool
	 */
	public static function url_has_preset_discount( string $url ) {
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( empty( $query ) ) {
			return false;
		}

		$args = array();
		wp_parse_str( $query, $args );

		return ! empty( $args[ self::DISCOUNT_ARG ] );
	}
}
