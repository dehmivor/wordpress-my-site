<?php
/**
 * Ability to get an EDD store health snapshot.
 *
 * @package     EDD\Abilities\Store
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Store;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\ReadAbility;

/**
 * Gets a snapshot of store health and environment information.
 *
 * @since 3.7.1
 */
class HealthCheck extends ReadAbility {

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input Unused; the ability accepts no input.
	 * @return array|\WP_Error
	 */
	protected function read_data( array $input ) {
		$order_counts = array();
		foreach ( edd_get_order_counts() as $status => $count ) {
			$order_counts[ $status ] = (int) $count;
		}

		$product_counts = wp_count_posts( 'download' );

		return array(
			'edd_version'      => EDD_VERSION,
			'wp_version'       => get_bloginfo( 'version' ),
			'php_version'      => phpversion(),
			'pro'              => (bool) edd_is_pro(),
			'test_mode'        => (bool) edd_is_test_mode(),
			'currency'         => edd_get_currency(),
			'order_counts'     => $order_counts,
			'customer_count'   => (int) edd_count_customers(),
			'product_count'    => isset( $product_counts->publish ) ? (int) $product_counts->publish : 0,
			'taxes_enabled'    => (bool) edd_use_taxes(),
			'gateways_enabled' => array_map( 'strval', array_keys( edd_get_enabled_payment_gateways() ) ),
		);
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'health-check';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Store Health Check', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Get a snapshot of store health: software versions, store mode, order and customer counts, and enabled payment gateways.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'manage_shop_settings';
	}

	/**
	 * Gets the output schema.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_output_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'edd_version'      => array( 'type' => 'string' ),
				'wp_version'       => array( 'type' => 'string' ),
				'php_version'      => array( 'type' => 'string' ),
				'pro'              => array( 'type' => 'boolean' ),
				'test_mode'        => array( 'type' => 'boolean' ),
				'currency'         => array( 'type' => 'string' ),
				'order_counts'     => array(
					'type'                 => 'object',
					'description'          => __( 'Order counts keyed by status, plus a total.', 'easy-digital-downloads' ),
					'additionalProperties' => array( 'type' => 'integer' ),
				),
				'customer_count'   => array( 'type' => 'integer' ),
				'product_count'    => array(
					'type'        => 'integer',
					'description' => __( 'Number of published products (downloads).', 'easy-digital-downloads' ),
				),
				'taxes_enabled'    => array( 'type' => 'boolean' ),
				'gateways_enabled' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'edd_version', 'wp_version', 'php_version', 'pro', 'test_mode', 'currency', 'order_counts', 'customer_count', 'product_count', 'taxes_enabled', 'gateways_enabled' ),
		);
	}
}
