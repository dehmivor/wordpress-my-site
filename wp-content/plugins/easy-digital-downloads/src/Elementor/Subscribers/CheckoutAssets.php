<?php
/**
 * EDD Checkout Assets Subscriber
 *
 * @package     EDD\Elementor\Subscribers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Subscribers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Elementor\Utils\Page;
use EDD\Elementor\Widgets\CheckoutInner;

/**
 * Loads the checkout block stylesheets ahead of Elementor's generated CSS.
 *
 * Enqueued at priority 10, against Elementor's own 20: a widget enqueueing its stylesheet as it
 * renders lands after wp_head, so the block CSS won every tie and the controls did nothing.
 *
 * @since 3.7.0
 */
class CheckoutAssets implements SubscriberInterface {

	/**
	 * Widget type => the widget class that owns its stylesheet.
	 *
	 * @since 3.7.0
	 * @var array
	 */
	private const WIDGETS = array(
		'edd-checkout-personal-info' => CheckoutInner\PersonalInfo::class,
		'edd-checkout-payment-info'  => CheckoutInner\PaymentInfo::class,
		'edd-checkout-cart'          => CheckoutInner\Cart::class,
		'edd-checkout-discount-form' => CheckoutInner\DiscountForm::class,
	);

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'wp_enqueue_scripts' => array( 'enqueue_checkout_styles', 10 ),
		);
	}

	/**
	 * Enqueue the stylesheet for every checkout section on the page.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function enqueue_checkout_styles() {
		if ( is_admin() ) {
			return;
		}

		// Ask for the box before reading the tree: this runs on every front-end pageview, and
		// on a page without a box the lookup answers from the raw meta without decoding it.
		if ( ! Page::has_widget( 'edd-checkout-box' ) ) {
			return;
		}

		$elements = Page::get_page_data();

		// The box needs this whether or not any section widget is present: it carries the box's own
		// slot rules, and every section enqueues it too, so a box on its own would otherwise miss it.
		self::enqueue_shared_style();

		foreach ( self::WIDGETS as $widget_type => $class ) {
			if ( Page::has_widget( $widget_type, $elements ) ) {
				$class::enqueue_style();
			}
		}
	}

	/**
	 * Enqueue the checkout stylesheet shared by the box and every section.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	private static function enqueue_shared_style() {
		if ( wp_style_is( 'edd-checkout-style', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'edd-checkout-style',
			EDD_BLOCKS_URL . 'build/checkout/style-index.css',
			array(),
			edd_admin_get_script_version()
		);
	}
}
