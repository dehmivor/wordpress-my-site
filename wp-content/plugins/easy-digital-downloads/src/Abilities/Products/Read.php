<?php
/**
 * Ability to read EDD products.
 *
 * @package     EDD\Abilities\Products
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities\Products;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Abilities\Pagination;
use EDD\Abilities\ReadAbility;

/**
 * Reads a single product or a filtered list of products.
 *
 * EDD products are the `download` custom post type.
 *
 * @since 3.7.1
 */
class Read extends ReadAbility {

	/**
	 * The post statuses exposed by this ability.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	private const STATUSES = array( 'publish', 'draft', 'pending', 'private', 'future' );

	/**
	 * Reads the store data the ability describes.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input The validated input.
	 * @return array|\WP_Error
	 */
	protected function read_data( array $input ) {
		if ( ! empty( $input['id'] ) ) {
			$product_id = absint( $input['id'] );
			$download   = edd_get_download( $product_id );
			if ( ! $download ) {
				return new \WP_Error(
					'edd_ability_product_not_found',
					__( 'No product exists with that ID.', 'easy-digital-downloads' ),
					array( 'status' => 404 )
				);
			}

			// The ability capability is store-wide; this is the per-product check.
			// read_post, not edit_post: a reporting role may read a product it
			// cannot edit, but nobody reads another author's unpublished one.
			if ( ! current_user_can( 'read_post', $product_id ) ) {
				return new \WP_Error(
					'edd_ability_product_not_found',
					__( 'No product exists with that ID.', 'easy-digital-downloads' ),
					array( 'status' => 404 )
				);
			}

			return Pagination::envelope( 'products', array( self::format_product( $download, true ) ), 1, 1, 0 );
		}

		$pagination = Pagination::parse( $input );
		$query      = new \WP_Query( $this->build_query_args( $input, $pagination ) );

		$formatted = array();
		foreach ( $query->posts as $post ) {
			$download = edd_get_download( $post->ID );
			if ( $download ) {
				$formatted[] = self::format_product( $download );
			}
		}

		return Pagination::envelope( 'products', $formatted, (int) $query->found_posts, $pagination['limit'], $pagination['offset'] );
	}

	/**
	 * Gets the ability slug.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'product-read';
	}

	/**
	 * Gets the ability label.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_label(): string {
		return __( 'Read Products', 'easy-digital-downloads' );
	}

	/**
	 * Gets the ability description.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	protected function get_description(): string {
		return __( 'Look up a single product by ID (including its pricing, files, and download limit), or list products filtered by search term, status, category, or tag.', 'easy-digital-downloads' );
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
			'properties'           => array_merge(
				array(
					'id'       => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => __( 'Product ID for a single-product lookup. When provided, all other filters are ignored.', 'easy-digital-downloads' ),
					),
					'search'   => array(
						'type'        => 'string',
						'description' => __( 'Search products by keyword.', 'easy-digital-downloads' ),
					),
					'status'   => array(
						'type'        => 'string',
						'enum'        => self::STATUSES,
						'description' => __( 'Filter by post status. Defaults to all statuses.', 'easy-digital-downloads' ),
					),
					'category' => array(
						'type'        => 'string',
						'description' => __( 'Filter by download category term slug.', 'easy-digital-downloads' ),
					),
					'tag'      => array(
						'type'        => 'string',
						'description' => __( 'Filter by download tag term slug.', 'easy-digital-downloads' ),
					),
				),
				Pagination::input_properties()
			),
			'additionalProperties' => false,
			'default'              => array(),
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
		return Pagination::output_schema( 'products', self::get_product_schema() );
	}

	/**
	 * Gets the JSON Schema describing one product.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	private static function get_product_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'              => array( 'type' => 'integer' ),
				'name'            => array( 'type' => 'string' ),
				'status'          => array( 'type' => 'string' ),
				'date_created'    => array(
					'type'        => array( 'string', 'null' ),
					'description' => __( 'When the product was created, in UTC. Null for a draft, which has no published date yet.', 'easy-digital-downloads' ),
				),
				'type'            => array( 'type' => 'string' ),
				'is_bundle'       => array( 'type' => 'boolean' ),
				'price'           => array(
					'type'        => array( 'number', 'null' ),
					'description' => __( 'The single price. Null when the product uses variable pricing.', 'easy-digital-downloads' ),
				),
				'is_variable'     => array( 'type' => 'boolean' ),
				'variable_prices' => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'price_id' => array( 'type' => 'integer' ),
							'name'     => array( 'type' => 'string' ),
							'amount'   => array( 'type' => 'number' ),
						),
					),
				),
				'sales'           => array(
					'type'        => 'integer',
					'description' => __( 'Lifetime sales count. Included only when the current user can view sensitive shop data.', 'easy-digital-downloads' ),
				),
				'earnings'        => array(
					'type'        => 'number',
					'description' => __( 'Lifetime earnings. Included only when the current user can view sensitive shop data.', 'easy-digital-downloads' ),
				),
				'sku'             => array( 'type' => array( 'string', 'null' ) ),
				'excerpt'         => array(
					'type'        => 'string',
					'description' => __( 'Included on single-product lookups only.', 'easy-digital-downloads' ),
				),
				'download_limit'  => array(
					'type'        => 'integer',
					'description' => __( 'File download limit. Included on single-product lookups only.', 'easy-digital-downloads' ),
				),
				'files'           => array(
					'type'        => 'array',
					'description' => __( 'Downloadable files, by ID and name only. File URLs are never included because they can point to protected or pre-signed assets. Included on single-product lookups only.', 'easy-digital-downloads' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'file_id' => array( 'type' => 'integer' ),
							'name'    => array( 'type' => 'string' ),
						),
					),
				),
			),
			'required'   => array( 'id', 'name', 'status' ),
		);
	}

	/**
	 * Formats a product for ability output.
	 *
	 * @since 3.7.1
	 *
	 * @param \EDD_Download $download        The download object.
	 * @param bool          $include_details Whether to include single-lookup details.
	 * @return array
	 */
	private static function format_product( $download, bool $include_details = false ): array {
		$is_variable = $download->has_variable_prices();

		$variable_prices = array();
		if ( $is_variable ) {
			foreach ( $download->get_prices() as $price_id => $price ) {
				$variable_prices[] = array(
					'price_id' => (int) $price_id,
					'name'     => (string) $price['name'],
					'amount'   => floatval( $price['amount'] ),
				);
			}
		}

		$sku = null;
		if ( edd_use_skus() ) {
			$raw_sku = $download->get_sku();
			$sku     = ( '-' !== $raw_sku ) ? (string) $raw_sku : null;
		}

		$formatted = array(
			'id'              => (int) $download->ID,
			'name'            => (string) $download->post_title,
			'status'          => (string) $download->post_status,
			// A draft carries the zero date in post_date_gmt, which reads as a real date.
			'date_created'    => '0000-00-00 00:00:00' === $download->post_date_gmt ? null : (string) $download->post_date_gmt,
			'type'            => (string) $download->get_type(),
			'is_bundle'       => $download->is_bundled_download(),
			'price'           => $is_variable ? null : floatval( $download->get_price() ),
			'is_variable'     => $is_variable,
			'variable_prices' => $variable_prices,
			'sku'             => $sku,
		);

		// Sales and earnings are revenue figures; EDD's own API gates them on
		// this capability rather than on product access.
		if ( current_user_can( 'view_shop_sensitive_data' ) ) {
			$formatted['sales']    = (int) $download->get_sales();
			$formatted['earnings'] = floatval( $download->get_earnings() );
		}

		if ( $include_details ) {
			$formatted['excerpt']        = (string) $download->post_excerpt;
			$formatted['download_limit'] = (int) $download->get_file_download_limit();

			if ( current_user_can( 'view_shop_sensitive_data' ) ) {
				$files = array();
				foreach ( (array) $download->get_files() as $file_id => $file ) {
					$files[] = array(
						'file_id' => (int) $file_id,
						'name'    => isset( $file['name'] ) ? (string) $file['name'] : '',
					);
				}

				$formatted['files'] = $files;
			}
		}

		return $formatted;
	}

	/**
	 * Builds the WP_Query arguments from the ability input.
	 *
	 * @since 3.7.1
	 *
	 * @param array $input      The validated input.
	 * @param array $pagination The parsed limit and offset.
	 * @return array
	 */
	private function build_query_args( array $input, array $pagination ): array {
		$status = ! empty( $input['status'] ) && in_array( $input['status'], self::STATUSES, true )
			? array( sanitize_key( $input['status'] ) )
			: self::STATUSES;

		$args = array(
			'post_type'      => 'download',
			'post_status'    => $status,
			'posts_per_page' => $pagination['limit'],
			'offset'         => $pagination['offset'],
			'no_found_rows'  => false,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$tax_query = array();
		if ( ! empty( $input['category'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'download_category',
				'field'    => 'slug',
				'terms'    => sanitize_title( $input['category'] ),
			);
		}

		if ( ! empty( $input['tag'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'download_tag',
				'field'    => 'slug',
				'terms'    => sanitize_title( $input['tag'] ),
			);
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		// Restricts draft, pending and scheduled products to their own author
		// unless the user can edit others'. Published products stay visible.
		$args['perm'] = 'editable';

		return $args;
	}
}
