<?php
/**
 * Ability to read curated EDD store settings.
 *
 * @package     EDD\Abilities\Store
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Store;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Loader;
use EDD\Abilities\ReadAbility;

/**
 * Reads a curated, read-only subset of store configuration.
 *
 * Only a fixed allow-list of settings is ever returned; the raw settings
 * array is never exposed.
 *
 * @since 3.7.1
 */
class Settings extends ReadAbility {

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input Unused; the ability accepts no input.
	 * @return array|\WP_Error
	 */
	protected function read_data( array $input ) {
		$gateways = array();
		foreach ( edd_get_enabled_payment_gateways() as $id => $gateway ) {
			$label = ! empty( $gateway['admin_label'] )
				? $gateway['admin_label']
				: ( $gateway['checkout_label'] ?? '' );

			$gateways[] = array(
				'id'    => (string) $id,
				'label' => (string) $label,
			);
		}

		return array(
			'currency'                 => edd_get_currency(),
			'currency_position'        => (string) edd_get_option( 'currency_position', 'before' ),
			'test_mode'                => (bool) edd_is_test_mode(),
			'default_gateway'          => (string) edd_get_default_gateway(),
			'enabled_gateways'         => $gateways,
			'taxes_enabled'            => (bool) edd_use_taxes(),
			'sequential_order_numbers' => (bool) edd_get_option( 'enable_sequential' ),
			'purchase_page_url'        => self::normalize_url( edd_get_checkout_uri() ),
			'success_page_url'         => self::normalize_url( edd_get_success_page_uri() ),
			'edd_version'              => EDD_VERSION,
			'pro'                      => (bool) edd_is_pro(),
			'writes_enabled'           => Loader::writes_enabled(),
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
		return 'settings';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Store Settings', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Get a read-only summary of store configuration: currency, payment gateways, taxes, key pages, and version information.', 'easy-digital-downloads' );
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
				'currency'                 => array( 'type' => 'string' ),
				'currency_position'        => array(
					'type'        => 'string',
					'description' => __( 'Whether the currency symbol renders before or after the amount.', 'easy-digital-downloads' ),
				),
				'test_mode'                => array( 'type' => 'boolean' ),
				'default_gateway'          => array( 'type' => 'string' ),
				'enabled_gateways'         => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'id'    => array( 'type' => 'string' ),
							'label' => array( 'type' => 'string' ),
						),
						'required'   => array( 'id', 'label' ),
					),
				),
				'taxes_enabled'            => array( 'type' => 'boolean' ),
				'sequential_order_numbers' => array( 'type' => 'boolean' ),
				'purchase_page_url'        => array( 'type' => array( 'string', 'null' ) ),
				'success_page_url'         => array( 'type' => array( 'string', 'null' ) ),
				'edd_version'              => array( 'type' => 'string' ),
				'pro'                      => array( 'type' => 'boolean' ),
				'writes_enabled'           => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the store allows abilities to change its data. When false, only read abilities can run, and the store owner enables the rest under Downloads > Tools > AI.', 'easy-digital-downloads' ),
				),
			),
			'required'   => array( 'currency', 'currency_position', 'test_mode', 'default_gateway', 'enabled_gateways', 'taxes_enabled', 'sequential_order_numbers', 'edd_version', 'pro', 'writes_enabled' ),
		);
	}

	/**
	 * Normalizes a page URL from one of EDD's page helpers.
	 *
	 * The helpers return an empty string when the page is unset or missing, but
	 * the schema types these as string-or-null.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $url The URL from an EDD page helper.
	 * @return string|null The URL, or null when there is not one.
	 */
	private static function normalize_url( $url ) {
		return ! empty( $url ) && is_string( $url ) ? $url : null;
	}
}
