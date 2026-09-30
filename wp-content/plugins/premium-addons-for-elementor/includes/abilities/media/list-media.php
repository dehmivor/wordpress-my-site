<?php
/**
 * List Media.
 *
 * Lists the images in the WordPress media library so an existing image can be
 * matched to a slot instead of uploading a new one.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Media;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class List_Media implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list-media';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Media Library Images', 'premium-addons-for-elementor' ),
			'description'         => __( 'Lists the images in the media library so an existing one can be reused.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-media',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'additionalProperties' => false,
				'properties'           => array(
					'search'    => array(
						'type'        => 'string',
						'description' => __( 'Keyword matched against the attachment title, description and filename. Alt text is not searchable. Omit to list all.', 'premium-addons-for-elementor' ),
					),
					'ids'       => array(
						'type'        => 'array',
						'description' => __( 'Fetch these attachment IDs exactly, e.g. to re-resolve a previously chosen image. Overrides search, paging and the MIME filter.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type' => 'integer',
						),
					),
					'mime_type' => array(
						'type'        => 'string',
						'default'     => 'image',
						'description' => __( 'Restrict by MIME type. "image" matches every image/* type; pass a specific type such as image/svg+xml to narrow it.', 'premium-addons-for-elementor' ),
					),
					'per_page'  => array(
						'type'        => 'integer',
						'default'     => 20,
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => __( 'How many images to return per page. Defaults to 20.', 'premium-addons-for-elementor' ),
					),
					'page'      => array(
						'type'        => 'integer',
						'default'     => 1,
						'minimum'     => 1,
						'description' => __( 'The page of results to return. Defaults to 1.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The matching media library images, newest first.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'items'       => array(
						'type'        => 'array',
						'description' => __( 'The matching images.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'id'        => array(
									'type'        => 'integer',
									'description' => __( 'The attachment ID. Pass it with the URL as the media control value in premium-addons/insert-widget or premium-addons/update-element-settings.', 'premium-addons-for-elementor' ),
								),
								'url'       => array(
									'type'        => 'string',
									'description' => __( 'The full size image URL.', 'premium-addons-for-elementor' ),
								),
								'width'     => array(
									'type'        => 'integer',
									'description' => __( 'The full size width in pixels, 0 when the file carries no dimensions.', 'premium-addons-for-elementor' ),
								),
								'height'    => array(
									'type'        => 'integer',
									'description' => __( 'The full size height in pixels, 0 when the file carries no dimensions.', 'premium-addons-for-elementor' ),
								),
								'alt'       => array(
									'type'        => 'string',
									'description' => __( 'The stored alt text. Describes what the image shows.', 'premium-addons-for-elementor' ),
								),
								'title'     => array(
									'type'        => 'string',
									'description' => __( 'The attachment title.', 'premium-addons-for-elementor' ),
								),
								'mime_type' => array(
									'type'        => 'string',
									'description' => __( 'The attachment MIME type.', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
					'total'       => array(
						'type'        => 'integer',
						'description' => __( 'The total number of matching images.', 'premium-addons-for-elementor' ),
					),
					'page'        => array(
						'type'        => 'integer',
						'description' => __( 'The returned page.', 'premium-addons-for-elementor' ),
					),
					'per_page'    => array(
						'type'        => 'integer',
						'description' => __( 'The page size used.', 'premium-addons-for-elementor' ),
					),
					'total_pages' => array(
						'type'        => 'integer',
						'description' => __( 'The number of pages available.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'upload_files' );
			},
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
			),
		);
	}

	/**
	 * Execute the ability.
	 *
	 * @param array|null $input Ability input.
	 * @return array|\WP_Error
	 */
	public function execute( $input = null ) {

		// The schema top-level default arrives as an empty stdClass, not an array.
		$input = is_array( $input ) ? $input : array();

		$per_page = isset( $input['per_page'] ) ? absint( $input['per_page'] ) : 20;
		$page     = isset( $input['page'] ) ? absint( $input['page'] ) : 1;
		$ids      = isset( $input['ids'] ) && is_array( $input['ids'] ) ? array_filter( array_map( 'absint', $input['ids'] ) ) : array();

		$args = array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'update_post_term_cache' => false,
		);

		if ( isset( $input['ids'] ) ) {

			// An id request is answered exactly — no MIME filter, so a requested id
			// never comes back as silence. An empty post__in is ignored by WP_Query
			// and would return the whole library, so match nothing instead.
			$args['post__in']       = empty( $ids ) ? array( 0 ) : $ids;
			$args['orderby']        = 'post__in';
			$args['posts_per_page'] = count( $args['post__in'] );

		} else {

			$args['post_mime_type'] = ! empty( $input['mime_type'] ) ? $input['mime_type'] : 'image';
			$args['posts_per_page'] = $per_page;
			$args['paged']          = $page;

			if ( ! empty( $input['search'] ) ) {
				$args['s'] = $input['search'];

				// WP_Query searches the title, description and excerpt only; the
				// filename lives in _wp_attached_file and is opted into. WP_Query
				// reads this filter once and drops it itself, so it needs no cleanup.
				add_filter( 'wp_allow_query_attachment_by_filename', '__return_true' );
			}
		}

		$query = new \WP_Query( $args );
		$items = array();

		foreach ( $query->posts as $attachment ) {
			$items[] = array_merge(
				Helpers::format_attachment( $attachment->ID ),
				array( 'title' => get_the_title( $attachment ) )
			);
		}

		return array(
			'items'       => $items,
			'total'       => (int) $query->found_posts,
			'page'        => isset( $args['paged'] ) ? $page : 1,
			'per_page'    => $args['posts_per_page'],
			'total_pages' => (int) $query->max_num_pages,
		);
	}
}
