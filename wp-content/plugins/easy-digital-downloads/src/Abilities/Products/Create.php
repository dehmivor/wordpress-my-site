<?php
/**
 * Ability to create an EDD product.
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
 * Creates a new product (download).
 *
 * @since 3.7.1
 */
class Create extends WriteAbility {

	/**
	 * Writes the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function write_data( array $input ) {
		$status = ! empty( $input['status'] ) && in_array( $input['status'], array( 'draft', 'publish' ), true )
			? sanitize_key( $input['status'] )
			: 'draft';

		$product_id = wp_insert_post(
			array(
				'post_type'    => 'download',
				'post_title'   => sanitize_text_field( $input['name'] ),
				'post_content' => ! empty( $input['description'] ) ? wp_kses_post( $input['description'] ) : '',
				'post_excerpt' => ! empty( $input['excerpt'] ) ? sanitize_text_field( $input['excerpt'] ) : '',
				'post_status'  => $status,
			),
			true
		);

		if ( is_wp_error( $product_id ) ) {
			return new \WP_Error(
				'edd_ability_product_not_created',
				$product_id->get_error_message(),
				array( 'status' => 500 )
			);
		}

		$price = null;
		if ( isset( $input['price'] ) ) {
			$price = edd_sanitize_amount( (string) $input['price'] );
			update_post_meta( $product_id, 'edd_price', $price );
		}

		$product = get_post( $product_id );
		if ( ! $product instanceof \WP_Post ) {
			return $this->saved_but_unreadable( (int) $product_id );
		}

		return array(
			'product_id' => (int) $product_id,
			'name'       => (string) $product->post_title,
			'status'     => (string) $product->post_status,
			'price'      => is_null( $price ) ? null : floatval( $price ),
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
		return 'product-create';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Create Product', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Create a new product (download) with an optional single price. Products are created as drafts unless a publish status is requested.', 'easy-digital-downloads' );
	}

	/**
	 * Gets the capability required to run the ability.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'publish_products';
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
				'name'        => array(
					'type'        => 'string',
					'minLength'   => 1,
					'maxLength'   => 200,
					'description' => __( 'The product name.', 'easy-digital-downloads' ),
				),
				'price'       => array(
					'type'        => 'number',
					'minimum'     => 0,
					'description' => __( 'The single price for the product.', 'easy-digital-downloads' ),
				),
				'description' => array(
					'type'        => 'string',
					'maxLength'   => 100000,
					'description' => __( 'The full product description.', 'easy-digital-downloads' ),
				),
				'excerpt'     => array(
					'type'        => 'string',
					'maxLength'   => 1000,
					'description' => __( 'A short product summary.', 'easy-digital-downloads' ),
				),
				'status'      => array(
					'type'        => 'string',
					'enum'        => array( 'draft', 'publish' ),
					'default'     => 'draft',
					'description' => __( 'The status for the new product.', 'easy-digital-downloads' ),
				),
			),
			'required'             => array( 'name' ),
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
				'price'      => array( 'type' => array( 'number', 'null' ) ),
			),
			'required'   => array( 'product_id', 'name', 'status' ),
		);
	}
}
