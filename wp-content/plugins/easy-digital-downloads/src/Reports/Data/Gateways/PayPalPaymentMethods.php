<?php
/**
 * PayPal Payment Methods report table data.
 *
 * Breaks down PayPal Commerce orders by the specific payment source the
 * buyer used (PayPal, Venmo, Card, Apple Pay, Google Pay, Fastlane, Pay
 * Later) so store owners can see which methods are actually getting used.
 * Mirrors the EDD Stripe Payment Methods report — same columns, same
 * filtering, same response shape.
 *
 * @package     EDD\Reports\Data\Gateways
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Reports\Data\Gateways;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Admin\List_Table;
use EDD\Gateways\PayPal\Payments;
use EDD\Reports;

/**
 * PayPalPaymentMethods class.
 *
 * @since 3.6.9
 */
class PayPalPaymentMethods extends List_Table {

	/**
	 * Constructor.
	 *
	 * @since 3.6.9
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'paypal-payment-method',
				'plural'   => 'paypal-payment-methods',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Primary column name.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'label';
	}

	/**
	 * Renders columns.
	 *
	 * @since 3.6.9
	 *
	 * @param array  $item        Row data.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		return $item[ $column_name ];
	}

	/**
	 * Columns.
	 *
	 * @since 3.6.9
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'label'          => __( 'Payment Method', 'easy-digital-downloads' ),
			'complete_sales' => __( 'Complete Sales', 'easy-digital-downloads' ),
			'pending_sales'  => __( 'Pending / Failed Sales', 'easy-digital-downloads' ),
			'refunded_sales' => __( 'Refunded Sales', 'easy-digital-downloads' ),
			'total_sales'    => __( 'Total Sales', 'easy-digital-downloads' ),
		);
	}

	/**
	 * Disable bulk actions for this read-only report.
	 *
	 * @since 3.6.9
	 *
	 * @param string $which Tablenav location.
	 * @return void
	 */
	public function bulk_actions( $which = '' ) {}

	/**
	 * Builds the table rows.
	 *
	 * @since 3.6.9
	 *
	 * @return array
	 */
	public function get_data() {
		$rows = array();

		foreach ( Payments::get_payment_source_labels() as $source => $label ) {
			$complete_count = $this->query( $source, array( 'status' => edd_get_gross_order_statuses() ) );

			// Skip sources the merchant has never received.
			if ( empty( $complete_count ) ) {
				continue;
			}

			$pending_count  = $this->query( $source, array( 'status' => edd_get_incomplete_order_statuses() ) );
			$refunded_count = $this->query( $source, array( 'status' => array( 'complete' ), 'type' => array( 'refund' ) ) );
			$total_count    = $this->query( $source, array() );

			$rows[] = array(
				'ID'             => $source,
				'label'          => $label,
				'complete_sales' => edd_format_amount( $complete_count, false ),
				'pending_sales'  => edd_format_amount( $pending_count, false ),
				'refunded_sales' => edd_format_amount( $refunded_count, false ),
				'total_sales'    => edd_format_amount( $total_count, false ),
			);
		}

		return $rows;
	}

	/**
	 * Prepares items for rendering.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $this->get_data();
	}

	/**
	 * Counts orders for a given PayPal payment source within the report's
	 * active date range and currency filter.
	 *
	 * @since 3.6.9
	 *
	 * @param string $source PayPal payment source slug (e.g. `paypal`, `venmo`).
	 * @param array  $args   Additional `edd_count_orders` args.
	 * @return int
	 */
	private function query( string $source, array $args ): int {
		$filter   = Reports\get_filter_value( 'dates' );
		$currency = Reports\get_filter_value( 'currencies' );
		$dates    = Reports\parse_dates_for_range( $filter['range'] );

		$args = wp_parse_args(
			$args,
			array(
				'gateway' => 'paypal_commerce',
				'type'    => array( 'sale' ),
			)
		);

		$args['meta_query'] = $this->get_meta_query( $source );

		if ( ! empty( $currency ) && 'convert' !== $currency ) {
			$args['currency'] = $currency;
		}

		if ( ! empty( $dates['start'] ) ) {
			$args['date_created_query']['after']     = array(
				'year'  => $dates['start']->format( 'Y' ),
				'month' => $dates['start']->format( 'm' ),
				'day'   => $dates['start']->format( 'd' ),
			);
			$args['date_created_query']['inclusive'] = true;
		}

		if ( ! empty( $dates['end'] ) ) {
			$args['date_created_query']['before']    = array(
				'year'  => $dates['end']->format( 'Y' ),
				'month' => $dates['end']->format( 'm' ),
				'day'   => $dates['end']->format( 'd' ),
			);
			$args['date_created_query']['inclusive'] = true;
		}

		return (int) edd_count_orders( $args );
	}

	/**
	 * Returns the meta query that scopes results to a payment source.
	 *
	 * Treats `paypal` as the default: orders where the meta key is missing
	 * (legacy V2 captures pre-V3 source tracking) or explicitly `paypal`
	 * are both counted under the PayPal row. Mirrors how the Stripe report
	 * groups orders missing `stripe_payment_method_type` with `card`.
	 *
	 * @since 3.6.9
	 *
	 * @param string $source PayPal payment source slug.
	 * @return array
	 */
	private function get_meta_query( string $source ): array {
		if ( 'paypal' === $source ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => '_edd_paypal_payment_source',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_edd_paypal_payment_source',
					'value'   => 'paypal',
					'compare' => '=',
				),
			);
		}

		return array(
			array(
				'key'     => '_edd_paypal_payment_source',
				'value'   => $source,
				'compare' => '=',
			),
		);
	}
}
