<?php
/**
 * List Pages.
 *
 * Lists the pages and posts on the site that are built with Elementor.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Discovery;

use PremiumAddons\Admin\Includes\Admin_Helper;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class List_Pages implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list-pages';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Elementor Pages', 'premium-addons-for-elementor' ),
			'description'         => __( 'Lists the pages and posts built with Elementor.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'additionalProperties' => false,
				'properties'           => array(
					'post_type' => array(
						'type'        => 'string',
						'default'     => 'any',
						'description' => __( 'The post type to list. A specific type (e.g. page, post) or "any" to list every public type. Defaults to any.', 'premium-addons-for-elementor' ),
					),
					'status'    => array(
						'type'        => 'string',
						'default'     => 'any',
						'description' => __( 'The post status to include (e.g. publish, draft) or "any". Defaults to any.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'Elementor-built pages and posts.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'pages' => array(
						'type'        => 'array',
						'description' => __( 'The matching Elementor-built posts, newest edited first (up to 100).', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'post_id'  => array(
									'type'        => 'integer',
									'description' => __( 'The post ID.', 'premium-addons-for-elementor' ),
								),
								'title'    => array(
									'type'        => 'string',
									'description' => __( 'The post title.', 'premium-addons-for-elementor' ),
								),
								'type'     => array(
									'type'        => 'string',
									'description' => __( 'The post type.', 'premium-addons-for-elementor' ),
								),
								'status'   => array(
									'type'        => 'string',
									'description' => __( 'The post status.', 'premium-addons-for-elementor' ),
								),
								'modified' => array(
									'type'        => 'string',
									'description' => __( 'The last modified date (site time).', 'premium-addons-for-elementor' ),
								),
							),
						),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'manage_options' );
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

		$post_type = ! empty( $input['post_type'] ) ? $input['post_type'] : 'any';
		$status    = ! empty( $input['status'] ) ? $input['status'] : 'any';

		$query = new \WP_Query(
			array(
				'post_type'              => $post_type,
				'post_status'            => $status,
				'posts_per_page'         => 100,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array(
					array(
						'key'   => '_elementor_edit_mode',
						'value' => 'builder',
					),
				),
			)
		);

		$pages = array();

		foreach ( $query->posts as $post ) {
			$pages[] = array(
				'post_id'  => $post->ID,
				'title'    => get_the_title( $post ),
				'type'     => $post->post_type,
				'status'   => $post->post_status,
				'modified' => $post->post_modified,
			);
		}

		return array( 'pages' => $pages );
	}
}
