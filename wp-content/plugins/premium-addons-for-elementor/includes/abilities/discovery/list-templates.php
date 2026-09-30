<?php
/**
 * List Templates.
 *
 * Lists the saved templates in the Elementor template library.
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
class List_Templates implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list-templates';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Elementor Templates', 'premium-addons-for-elementor' ),
			'description'         => __( 'Lists the saved Elementor templates.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'additionalProperties' => false,
				'properties'           => array(
					'template_type' => array(
						'type'        => 'string',
						'description' => __( 'Optional. Restrict to a single template type, e.g. page, section, container. Omit to list every type.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'Saved Elementor templates.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'templates' => array(
						'type'        => 'array',
						'description' => __( 'The matching templates, newest first (up to 100).', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'id'    => array(
									'type'        => 'integer',
									'description' => __( 'The template post ID.', 'premium-addons-for-elementor' ),
								),
								'title' => array(
									'type'        => 'string',
									'description' => __( 'The template title.', 'premium-addons-for-elementor' ),
								),
								'type'  => array(
									'type'        => 'string',
									'description' => __( 'The Elementor template type, from the _elementor_template_type meta.', 'premium-addons-for-elementor' ),
								),
								'date'  => array(
									'type'        => 'string',
									'description' => __( 'The template creation date (site time).', 'premium-addons-for-elementor' ),
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

		$args = array(
			'post_type'              => 'elementor_library',
			'post_status'            => 'publish',
			'posts_per_page'         => 100,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		);

		$template_type = ! empty( $input['template_type'] ) ? $input['template_type'] : '';

		if ( '' !== $template_type ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_elementor_template_type',
					'value' => $template_type,
				),
			);
		}

		$query = new \WP_Query( $args );

		$templates = array();

		foreach ( $query->posts as $post ) {
			$templates[] = array(
				'id'    => $post->ID,
				'title' => get_the_title( $post ),
				'type'  => get_post_meta( $post->ID, '_elementor_template_type', true ),
				'date'  => $post->post_date,
			);
		}

		return array( 'templates' => $templates );
	}
}
