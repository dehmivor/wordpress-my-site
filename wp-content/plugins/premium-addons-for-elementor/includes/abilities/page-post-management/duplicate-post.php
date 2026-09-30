<?php
/**
 * Duplicate Post.
 *
 * Duplicates an existing page or post as a new draft.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\PagePostManagement;

use PremiumAddons\Admin\Includes\Duplicator;
use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Duplicate_Post implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'duplicate-post';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Duplicate Page/Post', 'premium-addons-for-elementor' ),
			'description'         => __( 'Duplicates an existing page or post as a new draft, copying its content, taxonomies, and meta.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-page-post-management',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id' ),
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the source page or post to duplicate. The copy is created as a draft owned by the current user.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The duplicated draft post.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'post_id'  => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the new duplicated draft.', 'premium-addons-for-elementor' ),
					),
					'title'    => array(
						'type'        => 'string',
						'description' => __( 'The title of the new duplicated draft.', 'premium-addons-for-elementor' ),
					),
					'status'   => array(
						'type'        => 'string',
						'description' => __( 'The status of the new post. Always draft.', 'premium-addons-for-elementor' ),
					),
					'edit_url' => array(
						'type'        => 'string',
						'description' => __( 'The Elementor editor URL for the new draft.', 'premium-addons-for-elementor' ),
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

		$post_id = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

		if ( ! $post_id ) {
			return new \WP_Error(
				'premium_addons_missing_post_id',
				__( 'A post_id is required to duplicate a post.', 'premium-addons-for-elementor' )
			);
		}

		$new_id = Duplicator::duplicate( $post_id );

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $new_id );

		return array(
			'post_id'  => $new_id,
			'title'    => get_the_title( $new_id ),
			'status'   => 'draft',
			'edit_url' => $document ? $document->get_edit_url() : '',
		);
	}
}
