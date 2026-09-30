<?php
/**
 * PayPal Payment Methods Report Table.
 *
 * Surfaces the per-source breakdown of PayPal Commerce sales (PayPal,
 * Venmo, Card, Apple Pay, Google Pay, Fastlane, Pay Later) inside the
 * Payment Gateways report when the gateway filter is set to PayPal.
 *
 * @package     EDD\Reports\Endpoints\Tables
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Reports\Endpoints\Tables;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * PayPal Payment Methods Table.
 *
 * @since 3.6.9
 */
class PayPalPaymentMethods extends Table {

	/**
	 * Returns the endpoint ID used by the report registry.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	protected function get_id(): string {
		return 'paypal_payment_methods';
	}

	/**
	 * Returns the rendered table label, including the active date range.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'PayPal Payment Methods', 'easy-digital-downloads' ) . ' &mdash; ' . $this->get_chart_label();
	}

	/**
	 * Returns the fully-qualified class name for the data backing this table.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	protected function get_class_name(): string {
		return '\\EDD\\Reports\\Data\\Gateways\\PayPalPaymentMethods';
	}
}
