<?php
/**
 * Change Post Status.
 *
 * Changes the status of a page, post or Elementor template.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\PagePostManagement;

use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Change_Post_Status implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'change-post-status';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Change Post Status', 'premium-addons-for-elementor' ),
			'description'         => __( 'Changes the status of a page, post or Elementor template, e.g. from draft to published.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-page-post-management',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id', 'status' ),
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the page, post or template to update.', 'premium-addons-for-elementor' ),
					),
					'status'  => array(
						'type'        => 'string',
						'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
						'description' => __( 'The new post status.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The updated post and its status transition.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'post_id'         => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the updated post.', 'premium-addons-for-elementor' ),
					),
					'title'           => array(
						'type'        => 'string',
						'description' => __( 'The title of the updated post.', 'premium-addons-for-elementor' ),
					),
					'previous_status' => array(
						'type'        => 'string',
						'description' => __( 'The post status before the change.', 'premium-addons-for-elementor' ),
					),
					'new_status'      => array(
						'type'        => 'string',
						'description' => __( 'The post status after the change.', 'premium-addons-for-elementor' ),
					),
					'view_url'        => array(
						'type'        => 'string',
						'description' => __( 'The permalink when published, otherwise the preview URL.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function ( $input = null ) {
				return Helpers::can_edit_input_post( $input );
			},
			'meta'                => array(
				'show_in_rest' => true,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
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

		$post_id = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$post    = $post_id ? get_post( $post_id ) : null;

		if ( ! $post ) {
			return new \WP_Error(
				'premium_addons_invalid_post_id',
				/* translators: %d: post ID. */
				sprintf( __( 'No page/post found with ID %d.', 'premium-addons-for-elementor' ), $post_id )
			);
		}

		$previous_status = $post->post_status;
		$new_status      = $input['status'];

		$result = wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => $new_status,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'post_id'         => $post_id,
			'title'           => get_the_title( $post_id ),
			'previous_status' => $previous_status,
			'new_status'      => get_post_status( $post_id ),
			'view_url'        => 'publish' === $new_status ? get_permalink( $post_id ) : get_preview_post_link( $post_id ),
		);
	}
}
