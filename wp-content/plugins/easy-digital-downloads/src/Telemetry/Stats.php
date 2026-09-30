<?php
/**
 * Gets the store stats to send to our telemetry server.
 *
 * @package   easy-digital-downloads
 * @copyright Copyright (c) 2023, Easy Digital Downloads
 * @license   GPL2+
 * @since     3.1.1
 */

namespace EDD\Telemetry;
use EDD\Admin\Pass_Manager;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Class Stats
 *
 * @since 3.1.1
 */
class Stats {

	/**
	 * The number of products on the site.
	 *
	 * @since 3.1.1
	 * @var int
	 */
	private $product_count;

	public function get() {
		$data = array(
			'activated'                  => $this->convert_timestamp( edd_get_activation_date() ),
			'pro_activated'              => $this->convert_timestamp( get_option( 'edd_pro_activation_date' ) ),
			'first_order'                => $this->get_first_order_date(),
			'onboarding_started'         => get_option( 'edd_onboarding_started' ),
			'onboarding_completed'       => get_option( 'edd_onboarding_completed' ),
			'products'                   => $this->get_product_count(),
			'categories'                 => $this->get_category_count(),
			'tags'                       => $this->get_tag_count(),
			'customer_count'             => $this->get_customer_count(),
			'median_orders_per_customer' => $this->get_median_orders_per_customer(),
			'pass_id'                    => $this->get_pass_id(),
			'discounts'                  => $this->get_discount_count(),
		);

		/**
		 * Filters the stats data to send to the telemetry server.
		 *
		 * @since 3.6.9
		 *
		 * @param array $data The stats data.
		 */
		return apply_filters( 'edd_telemetry_stats', $data );
	}

	/**
	 * Gets the total number of customers.
	 *
	 * @since 3.7.0
	 * @return int
	 */
	private function get_customer_count() {
		return edd_count_customers();
	}

	/**
	 * Gets the median number of orders per customer.
	 *
	 * "Orders" here is the customer's purchase_count, which counts only completed
	 * sale orders (net statuses, type "sale") -- consistent with EDD's sales
	 * reporting. Pending, abandoned, failed, refunded and refund-type orders are
	 * not included.
	 *
	 * We report the median rather than the mean because a small number of
	 * high-volume customers can heavily skew the average, making it a poor
	 * representation of a typical customer.
	 *
	 * @since 3.7.0
	 * @return float
	 */
	private function get_median_orders_per_customer() {
		global $wpdb;

		// Count rows from the same table we order over, so the median offset stays accurate.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->edd_customers}" );
		if ( empty( $count ) ) {
			return 0;
		}

		$offset = intdiv( $count - 1, 2 ); // Lower of the two middle rows (zero-indexed).
		$limit  = 2 - ( $count % 2 );      // One row for an odd count, two for an even count.

		$median = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(purchase_count) FROM (
					SELECT purchase_count FROM {$wpdb->edd_customers}
					ORDER BY purchase_count ASC
					LIMIT %d OFFSET %d
				) AS middle",
				$limit,
				$offset
			)
		);

		return round( (float) $median, 2 );
	}

	/**
	 * Gets the date of the first completed order.
	 *
	 * @since 3.1.1
	 * @return string
	 */
	private function get_first_order_date() {
		$orders = edd_get_orders(
			array(
				'mode'       => 'live',
				'status__in' => edd_get_complete_order_statuses(),
				'number'     => 1,
				'fields'     => 'date_completed',
				'orderby'    => 'id',
				'order'      => 'ASC',
			),
		);

		return ! empty( $orders ) ? reset( $orders ) : '';
	}

	/**
	 * Converts a timestamp value to a date string for consistent dates.
	 *
	 * @since 3.1.1
	 * @param string $timestamp The timestamp to convert.
	 * @return string
	 */
	private function convert_timestamp( $timestamp = '' ) {
		return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : '';
	}

	/**
	 * Gets the site pass ID.
	 *
	 * @since 3.1.1
	 * @return int|string
	 */
	private function get_pass_id() {
		$pass_manager = new Pass_Manager();

		return $pass_manager->highest_pass_id;
	}

	/**
	 * Gets the number of published products on the website.
	 *
	 * @since 3.1.1
	 * @return int
	 */
	private function get_product_count() {
		if ( $this->product_count ) {
			return $this->product_count;
		}
		$query               = new \WP_Query(
			array(
				'post_type' => 'download',
				'status'    => 'publish',
				'nopaging'  => true,
			)
		);
		$this->product_count = $query->found_posts;

		return $this->product_count;
	}

	/**
	 * Gets the average total earnings per product.
	 *
	 * @since 3.1.1
	 * @return float
	 */
	private function get_average_per_product() {
		global $wpdb;

		$results = $wpdb->get_results(
			"SELECT SUM(total) as earnings
			 FROM {$wpdb->edd_orders}
			 WHERE type = 'sale'
			 AND status IN ('" . implode( "', '", edd_get_gross_order_statuses() ) . "')
			 LIMIT 0, 99999;"
		);
		if ( empty( $results ) ) {
			return 0;
		}
		$results  = reset( $results );
		$products = $this->get_product_count();

		return $results->earnings / $products;
	}

	/**
	 * Gets the number of product categories on the website.
	 *
	 * @since 3.2.10
	 * @return int
	 */
	private function get_category_count() {
		return wp_count_terms( 'download_category' );
	}

	/**
	 * Gets the number of product tags on the website.
	 *
	 * @since 3.2.10
	 * @return int
	 */
	private function get_tag_count() {
		return wp_count_terms( 'download_tag' );
	}

	/**
	 * Gets the number of active discount codes on the website.
	 *
	 * @since 3.6.9
	 * @return int
	 */
	private function get_discount_count() {
		return edd_get_discount_count( array( 'status' => 'active' ) );
	}
}
