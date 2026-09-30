<?php
/**
 * PayPal V3 Merchant Status
 *
 * Provides helpers for reading ACDC and vaulting vetting status
 * from the Connect service's merchant-status response.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * MerchantStatus class.
 *
 * @since 3.6.9
 */
class MerchantStatus {

	/**
	 * Gets the vetting status for a specific product from a merchant status response.
	 *
	 * @since 3.6.9
	 *
	 * @param array  $merchant_status The merchant status response from the Connect service.
	 * @param string $product_name    The product name to look up (e.g., 'PPCP', 'ADVANCED_VAULTING').
	 * @return string|null The vetting status or null if not found.
	 */
	public static function get_product_vetting_status( $merchant_status, $product_name ) {
		if ( empty( $merchant_status['product_details'] ) || ! is_array( $merchant_status['product_details'] ) ) {
			return null;
		}

		foreach ( $merchant_status['product_details'] as $product ) {
			$name = is_array( $product ) ? ( $product['name'] ?? '' ) : ( $product->name ?? '' );
			if ( $product_name === $name ) {
				return is_array( $product ) ? ( $product['vetting_status'] ?? null ) : ( $product->vetting_status ?? null );
			}
		}

		return null;
	}

	/**
	 * Determines whether one of the named capabilities is granted and active.
	 *
	 * PayPal returns granted capabilities in the merchant-status response under
	 * a `capabilities[]` array (per-account features such as Pay Later or
	 * Venmo). Capability naming varies by region and API version, so accept
	 * multiple possible names for a single conceptual capability.
	 *
	 * @since 3.6.9
	 *
	 * @param array        $merchant_status The merchant status response from the Connect service.
	 * @param string|array $capability_name A capability name or an array of acceptable names.
	 * @return bool True if any of the named capabilities is present with status `ACTIVE`.
	 */
	public static function has_capability( $merchant_status, $capability_name ): bool {
		if ( empty( $merchant_status['capabilities'] ) || ! is_array( $merchant_status['capabilities'] ) ) {
			return false;
		}

		$names = (array) $capability_name;

		foreach ( $merchant_status['capabilities'] as $capability ) {
			$name   = is_array( $capability ) ? ( $capability['name'] ?? '' ) : ( $capability->name ?? '' );
			$status = is_array( $capability ) ? ( $capability['status'] ?? '' ) : ( $capability->status ?? '' );

			if ( 'ACTIVE' === $status && in_array( $name, $names, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Gets a human-readable label for a PayPal vetting status.
	 *
	 * @since 3.6.9
	 *
	 * @param string $vetting_status The vetting status from PayPal.
	 * @return string
	 */
	public static function get_vetting_status_label( $vetting_status ) {
		$labels = array(
			'SUBSCRIBED'       => __( 'Approved and active.', 'easy-digital-downloads' ),
			'PENDING'          => __( 'Application is in review. This may take a few business days.', 'easy-digital-downloads' ),
			'NEED_MORE_DATA'   => __( 'Additional information is required. Please check your PayPal account for details.', 'easy-digital-downloads' ),
			'DENIED'           => __( 'Application was denied. Please contact PayPal for more information.', 'easy-digital-downloads' ),
			'SUSPENDED'        => __( 'Currently suspended. Please contact PayPal for more information.', 'easy-digital-downloads' ),
			'IN_REVIEW'        => __( 'Application is in review. This may take a few business days.', 'easy-digital-downloads' ),
			'APPROVAL_PENDING' => __( 'Approval is pending. Please check your PayPal account.', 'easy-digital-downloads' ),
		);

		return isset( $labels[ $vetting_status ] ) ? $labels[ $vetting_status ] : $vetting_status;
	}
}
