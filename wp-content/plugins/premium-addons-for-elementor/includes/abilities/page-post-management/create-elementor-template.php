<?php
/**
 * Create Elementor Template.
 *
 * Creates a new saved Elementor template (elementor_library post) ready to build into.
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
class Create_Elementor_Template implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'create-elementor-template';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Create Elementor Template', 'premium-addons-for-elementor' ),
			'description'         => __( 'Creates a new saved Elementor template ready to edit with Elementor.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-page-post-management',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'title' ),
				'properties'           => array(
					'title'         => array(
						'type'        => 'string',
						'description' => __( 'The title of the new template.', 'premium-addons-for-elementor' ),
					),
					'template_type' => array(
						'type'        => 'string',
						'enum'        => array( 'container', 'section', 'page', 'header', 'footer', 'popup' ),
						'default'     => 'container',
						'description' => __( 'The Elementor template type. Defaults to container. header, footer and popup require Elementor Pro to be usable in the Theme Builder.', 'premium-addons-for-elementor' ),
					),
					'status'        => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft' ),
						'default'     => 'publish',
						'description' => __( 'The template status. Defaults to publish so it appears in the Templates library.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The created template and its Elementor URLs.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'post_id'       => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the created template.', 'premium-addons-for-elementor' ),
					),
					'title'         => array(
						'type'        => 'string',
						'description' => __( 'The title of the created template.', 'premium-addons-for-elementor' ),
					),
					'template_type' => array(
						'type'        => 'string',
						'description' => __( 'The Elementor template type that was assigned.', 'premium-addons-for-elementor' ),
					),
					'edit_url'      => array(
						'type'        => 'string',
						'description' => __( 'The Elementor editor URL for the new template.', 'premium-addons-for-elementor' ),
					),
					'preview_url'   => array(
						'type'        => 'string',
						'description' => __( 'The preview URL for the new template.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function () {
				return Admin_Helper::check_user_can( 'edit_posts' );
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
				__( 'A title is required to create a template.', 'premium-addons-for-elementor' )
			);
		}

		$status        = ! empty( $input['status'] ) ? $input['status'] : 'publish';
		$template_type = ! empty( $input['template_type'] ) ? $input['template_type'] : 'container';

		// Created with an empty element tree so _elementor_data / _elementor_version
		// are written and the template opens straight into the editor.
		$post_id = Helpers::create_library_template( $title, $template_type, array(), $status );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		return array(
			'post_id'       => $post_id,
			'title'         => get_the_title( $post_id ),
			'template_type' => $template_type,
			'edit_url'      => $document ? $document->get_edit_url() : '',
			'preview_url'   => 'publish' === $status ? get_permalink( $post_id ) : get_preview_post_link( $post_id ),
		);
	}
}
