<?php
/**
 * Command Palette core sources.
 *
 * Registers EDD's own searchable sources (orders, customers, discounts and
 * downloads) through the same registry available to add-ons, then opens
 * registration to add-ons.
 *
 * @package     EDD\CommandPalette
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\CommandPalette;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\REST\Controllers\CommandPalette as Controller;

/**
 * CoreSources class.
 *
 * The search methods are static because each is a pure lookup over its search
 * term, called by the ability's execute callback.
 *
 * @since 3.7.1
 */
class CoreSources implements SubscriberInterface {

	/**
	 * Get the events this subscriber listens to.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'wp_abilities_api_categories_init' => 'register_category',
			'wp_abilities_api_init'            => 'register_sources',
		);
	}

	/**
	 * Register the EDD ability category every EDD source is grouped under.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	public function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			Registry::CATEGORY,
			array(
				'label'       => __( 'Easy Digital Downloads', 'easy-digital-downloads' ),
				'description' => __( 'Abilities that retrieve or act on Easy Digital Downloads store data.', 'easy-digital-downloads' ),
			)
		);
	}

	/**
	 * Register EDD's core sources, then let add-ons register their own.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	public function register_sources() {
		if ( ! Registry::is_supported() ) {
			return;
		}

		Registry::register(
			'edd/search-orders',
			array(
				'label'           => __( 'Orders', 'easy-digital-downloads' ),
				'capability'      => 'edit_shop_payments',
				'search_callback' => array( __CLASS__, 'search_orders' ),
				// Numeric searches are most likely an order number.
				'numeric'         => true,
			)
		);

		Registry::register(
			'edd/search-customers',
			array(
				'label'           => __( 'Customers', 'easy-digital-downloads' ),
				'capability'      => edd_get_view_customers_role(),
				'search_callback' => array( __CLASS__, 'search_customers' ),
			)
		);

		Registry::register(
			'edd/search-discounts',
			array(
				'label'           => __( 'Discounts', 'easy-digital-downloads' ),
				'capability'      => 'manage_shop_discounts',
				'search_callback' => array( __CLASS__, 'search_discounts' ),
			)
		);

		Registry::register(
			'edd/search-downloads',
			array(
				'label'           => edd_get_label_plural(),
				'capability'      => 'edit_products',
				'search_callback' => array( __CLASS__, 'search_downloads' ),
			)
		);

		/**
		 * Fires when command palette search sources should be registered.
		 *
		 * Only fires when the Abilities API is available, so a callback does not
		 * need to check the WordPress version.
		 *
		 * @since 3.7.1
		 */
		do_action( 'edd/command_palette/register_sources' );
	}

	/**
	 * Search orders by ID, order number, email or payment key.
	 *
	 * @since 3.7.1
	 *
	 * @param string $term The search term.
	 * @return array[]
	 */
	public static function search_orders( $term ) {
		$orders = array();

		// The generic search covers the payment key, where a short number matches
		// inside the random hash. A numeric term is an ID or an order number, so
		// resolve the ID directly and restrict the search to the order number.
		if ( is_numeric( $term ) ) {
			$order = edd_get_order( absint( $term ) );
			if ( $order && 'sale' === $order->type ) {
				$orders[ (int) $order->id ] = $order;
			}

			$query = array(
				'search'         => $term,
				'search_columns' => array( 'order_number' ),
			);
		} else {
			$query = array( 'search' => $term );
		}

		foreach ( edd_get_orders(
			array_merge(
				$query,
				array(
					'type'   => 'sale',
					'number' => Controller::MAX_RESULTS_PER_SOURCE,
				)
			)
		) as $order ) {
			$orders[ (int) $order->id ] = $order;
		}

		$results = array();
		foreach ( $orders as $order ) {
			$label = sprintf(
				/* translators: %s: order number */
				__( 'EDD Order: %s', 'easy-digital-downloads' ),
				$order->get_number()
			);
			if ( ! empty( $order->email ) ) {
				$label = sprintf(
					/* translators: 1: order number, 2: customer email */
					__( 'EDD Order: %1$s — %2$s', 'easy-digital-downloads' ),
					$order->get_number(),
					$order->email
				);
			}

			$results[] = array(
				'label' => $label,
				'url'   => edd_get_admin_url(
					array(
						'page' => 'edd-payment-history',
						'view' => 'view-order-details',
						'id'   => $order->id,
					)
				),
			);
		}

		return $results;
	}

	/**
	 * Search customers by name or email.
	 *
	 * @since 3.7.1
	 *
	 * @param string $term The search term.
	 * @return array[]
	 */
	public static function search_customers( $term ) {
		$customers = edd_get_customers(
			array(
				'search' => $term,
				'number' => Controller::MAX_RESULTS_PER_SOURCE,
			)
		);

		$results = array();
		foreach ( $customers as $customer ) {
			$label = sprintf(
				/* translators: %s: customer email */
				__( 'EDD Customer: %s', 'easy-digital-downloads' ),
				$customer->email
			);
			if ( ! empty( $customer->name ) ) {
				$label = sprintf(
					/* translators: 1: customer name, 2: customer email */
					__( 'EDD Customer: %1$s — %2$s', 'easy-digital-downloads' ),
					$customer->name,
					$customer->email
				);
			}

			$results[] = array(
				'label' => $label,
				'url'   => edd_get_admin_url(
					array(
						'page' => 'edd-customers',
						'view' => 'overview',
						'id'   => $customer->id,
					)
				),
			);
		}

		return $results;
	}

	/**
	 * Search discounts by code, name or description.
	 *
	 * @since 3.7.1
	 *
	 * @param string $term The search term.
	 * @return array[]
	 */
	public static function search_discounts( $term ) {
		$discounts = edd_get_discounts(
			array(
				'search' => $term,
				'number' => Controller::MAX_RESULTS_PER_SOURCE,
			)
		);

		$results = array();
		foreach ( $discounts as $discount ) {
			$label = sprintf(
				/* translators: %s: discount code */
				__( 'EDD Discount: %s', 'easy-digital-downloads' ),
				$discount->code
			);
			if ( ! empty( $discount->name ) ) {
				$label = sprintf(
					/* translators: 1: discount code, 2: discount name */
					__( 'EDD Discount: %1$s — %2$s', 'easy-digital-downloads' ),
					$discount->code,
					$discount->name
				);
			}

			$results[] = array(
				'label' => $label,
				'url'   => edd_get_admin_url(
					array(
						'page'     => 'edd-discounts',
						'view'     => 'edit_discount',
						'discount' => $discount->id,
					)
				),
			);
		}

		return $results;
	}

	/**
	 * Search downloads by title.
	 *
	 * @since 3.7.1
	 *
	 * @param string $term The search term.
	 * @return array[]
	 */
	public static function search_downloads( $term ) {
		$downloads = get_posts(
			array(
				'post_type'        => 'download',
				's'                => $term,
				'posts_per_page'   => Controller::MAX_RESULTS_PER_SOURCE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'suppress_filters' => false,
			)
		);

		$results = array();
		foreach ( $downloads as $download ) {
			$url = get_edit_post_link( $download->ID, 'raw' );
			if ( empty( $url ) ) {
				continue;
			}

			$results[] = array(
				'label' => sprintf(
					/* translators: 1: Download singular label, 2: download title */
					__( 'EDD %1$s: %2$s', 'easy-digital-downloads' ),
					edd_get_label_singular(),
					get_the_title( $download )
				),
				'url'   => $url,
			);
		}

		return $results;
	}
}
