<?php
/**
 * Ability to update an EDD product.
 *
 * @package     EDD\Abilities\Products
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Products;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\WriteAbility;

/**
 * Updates an existing product (download).
 *
 * Only the provided fields are changed; everything else is left as-is.
 *
 * @since 3.7.1
 */
class Update extends WriteAbility {

	/**
	 * Checks whether the current user may run this ability.
	 *
	 * Editing a product owned by another user additionally requires the
	 * `edit_others_products` capability.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $input The validated input, or null when unavailable.
	 * @return bool
	 */
	public function check_permissions( $input = null ): bool {
		if ( ! parent::check_permissions( $input ) ) {
			return false;
		}

		// Without a product there is nothing to check ownership against. Core
		// validates input before permissions, so the sanctioned path has already
		// returned a 400 naming the field; only a direct call reaches this.
		if ( ! is_array( $input ) || empty( $input['product_id'] ) ) {
			return false;
		}

		$product_id = absint( $input['product_id'] );

		// The ability capability is store-wide, so the per-post check is what
		// enforces ownership and the published-post rules.
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return false;
		}

		// wp_update_post() enforces no capability of its own, so publishing has
		// to be checked here or any holder of edit_products could publish.
		$requested_status = ! empty( $input['status'] ) ? $input['status'] : '';
		if ( in_array( $requested_status, array( 'publish', 'private' ), true ) && ! current_user_can( 'publish_products' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Writes the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function write_data( array $input ) {
		$product_id = absint( $input['product_id'] );

		$download = edd_get_download( $product_id );
		if ( ! $download ) {
			return new \WP_Error(
				'edd_ability_product_not_found',
				__( 'No product exists with that ID.', 'easy-digital-downloads' ),
				array( 'status' => 404 )
			);
		}

		$post_args = array();

		if ( isset( $input['name'] ) && '' !== $input['name'] ) {
			$post_args['post_title'] = sanitize_text_field( $input['name'] );
		}

		if ( isset( $input['description'] ) ) {
			$post_args['post_content'] = wp_kses_post( $input['description'] );
		}

		if ( ! empty( $input['status'] ) && in_array( $input['status'], array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
			$post_args['post_status'] = sanitize_key( $input['status'] );
		}

		// Reject before writing: a rejection after wp_update_post() would leave a
		// partial change behind while reporting failure.
		if ( isset( $input['price'] ) && $download->has_variable_prices() ) {
			return new \WP_Error(
				'edd_ability_variable_price_product',
				__( 'This product uses variable pricing, which cannot be changed here. Update its price options in the product editor instead.', 'easy-digital-downloads' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $post_args ) ) {
			$post_args['ID'] = $product_id;

			$updated = wp_update_post( $post_args, true );
			if ( is_wp_error( $updated ) ) {
				return new \WP_Error(
					'edd_ability_product_not_updated',
					$updated->get_error_message(),
					array( 'status' => 500 )
				);
			}
		}

		if ( isset( $input['price'] ) ) {
			update_post_meta( $product_id, 'edd_price', edd_sanitize_amount( (string) $input['price'] ) );
		}

		$download = edd_get_download( $product_id );
		if ( empty( $download ) ) {
			return $this->saved_but_unreadable( (int) $product_id );
		}

		return array(
			'product_id' => $product_id,
			'name'       => (string) $download->post_title,
			'status'     => (string) $download->post_status,
			'price'      => $download->has_variable_prices() ? null : floatval( $download->get_price() ),
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
		return 'product-update';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Update Product', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Update an existing product (download). Only the fields provided are changed: name, single price, status, or description.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'edit_products';
	}

	/**
	 * Gets the input schema.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_input_schema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'product_id'  => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The product ID.', 'easy-digital-downloads' ),
				),
				'name'        => array(
					'type'        => 'string',
					'maxLength'   => 200,
					'description' => __( 'The new product name.', 'easy-digital-downloads' ),
				),
				'price'       => array(
					'type'        => 'number',
					'minimum'     => 0,
					'description' => __( 'The new single price for the product.', 'easy-digital-downloads' ),
				),
				'status'      => array(
					'type'        => 'string',
					'enum'        => array( 'draft', 'publish', 'pending', 'private' ),
					'description' => __( 'The new post status.', 'easy-digital-downloads' ),
				),
				'description' => array(
					'type'        => 'string',
					'maxLength'   => 100000,
					'description' => __( 'The new full product description.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'product_id' ),
			'additionalProperties' => false,
		);
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
				'product_id' => array( 'type' => 'integer' ),
				'name'       => array( 'type' => 'string' ),
				'status'     => array( 'type' => 'string' ),
				'price'      => array(
					'type'        => array( 'number', 'null' ),
					'description' => __( 'The single price after the update. Null when the product uses variable pricing.', 'easy-digital-downloads' ),
				),
			),
			'required'   => array( 'product_id', 'name', 'status' ),
		);
	}
}
