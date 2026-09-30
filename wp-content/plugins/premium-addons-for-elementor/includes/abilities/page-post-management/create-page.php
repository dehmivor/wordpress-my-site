<?php
/**
 * Create Page.
 *
 * Creates a new Elementor-ready page or post.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\PagePostManagement;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Create_Page implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'create-page';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Create Elementor Page', 'premium-addons-for-elementor' ),
			'description'         => __( 'Creates a new page or post ready to edit with Elementor.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-page-post-management',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'title' ),
				'properties'           => array(
					'title'     => array(
						'type'        => 'string',
						'description' => __( 'The title of the new page or post.', 'premium-addons-for-elementor' ),
					),
					'status'    => array(
						'type'        => 'string',
						'enum'        => array( 'draft', 'publish' ),
						'default'     => 'draft',
						'description' => __( 'The post status. Defaults to draft.', 'premium-addons-for-elementor' ),
					),
					'post_type' => array(
						'type'        => 'string',
						'enum'        => array( 'page', 'post' ),
						'default'     => 'page',
						'description' => __( 'The post type to create. Defaults to page.', 'premium-addons-for-elementor' ),
					),
					'template'  => array(
						'type'        => 'string',
						'description' => __( 'Optional page-template slug to assign (e.g. elementor_canvas, elementor_header_footer). Omit to use the theme default.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The created post and its Elementor URLs.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'post_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the created post.', 'premium-addons-for-elementor' ),
					),
					'title'       => array(
						'type'        => 'string',
						'description' => __( 'The title of the created post.', 'premium-addons-for-elementor' ),
					),
					'edit_url'    => array(
						'type'        => 'string',
						'description' => __( 'The Elementor editor URL for the new post.', 'premium-addons-for-elementor' ),
					),
					'preview_url' => array(
						'type'        => 'string',
						'description' => __( 'The preview URL for the new post.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'edit_pages' );
			},
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
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

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		$title = isset( $input['title'] ) ? trim( $input['title'] ) : '';

		if ( '' === $title ) {
			return new \WP_Error(
				'premium_addons_missing_title',
				__( 'A title is required to create a page.', 'premium-addons-for-elementor' )
			);
		}

		$status    = ! empty( $input['status'] ) ? $input['status'] : 'draft';
		$post_type = ! empty( $input['post_type'] ) ? $input['post_type'] : 'page';

		$post_args = array(
			'post_title'  => $title,
			'post_status' => $status,
			'post_type'   => $post_type,
			'meta_input'  => array(
				'_elementor_edit_mode'     => 'builder',
				'_elementor_template_type' => 'wp-' . $post_type,
			),
		);

		if ( ! empty( $input['template'] ) ) {
			$post_args['page_template'] = $input['template'];
		}

		$post_id = wp_insert_post( $post_args, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Initialize the Elementor document with an empty element tree so
		// _elementor_data / _elementor_version are written and CSS regenerates.
		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		if ( $document ) {
			$document->save( array( 'elements' => array() ) );
		}

		return array(
			'post_id'     => $post_id,
			'title'       => get_the_title( $post_id ),
			'edit_url'    => $document ? $document->get_edit_url() : '',
			'preview_url' => 'publish' === $status ? get_permalink( $post_id ) : get_preview_post_link( $post_id ),
		);
	}
}
