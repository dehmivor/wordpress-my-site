<?php
/**
 * Get ID by Title.
 *
 * Finds the ID of a page or post from its title.
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
class Get_Id_By_Title implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-id-by-title';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Post/Page ID by Title', 'premium-addons-for-elementor' ),
			'description'         => __( 'Finds the ID of a page or post by its title.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'title' ),
				'properties'           => array(
					'title'     => array(
						'type'        => 'string',
						'description' => __( 'The exact title of the post or page to resolve.', 'premium-addons-for-elementor' ),
					),
					'post_type' => array(
						'type'        => 'string',
						'default'     => 'page',
						'description' => __( 'The post type to search. A specific type (e.g. page, post) or "any" to search every public type. Defaults to page.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The resolved post ID, or null when no post matches the title.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'id' => array(
						'type'        => array( 'integer', 'null' ),
						'description' => __( 'The ID of the first matching post, or null when there is no match.', 'premium-addons-for-elementor' ),
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

		$title     = isset( $input['title'] ) ? trim( $input['title'] ) : '';
		$post_type = ! empty( $input['post_type'] ) ? $input['post_type'] : 'page';

		if ( '' === $title ) {
			return array( 'id' => null );
		}

		$query = new \WP_Query(
			array(
				'title'                  => $title,
				'post_type'              => $post_type,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array(
			'id' => ! empty( $query->posts ) ? $query->posts[0] : null,
		);
	}
}
