<?php
/**
 * Remove Element.
 *
 * Removes an element from a page by its element id.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Build;

use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Remove_Element implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'remove-element';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Remove Element', 'premium-addons-for-elementor' ),
			'description'         => __( 'Removes an element (widget or container) from a page by its element ID.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-build',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id', 'element_id' ),
				'properties'           => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the Elementor page, post or template containing the element.', 'premium-addons-for-elementor' ),
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'The id of the element to remove. Removing a container removes every element nested inside it. Use premium-addons/get-page-structure to find element ids.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The removal result.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the document the element was removed from.', 'premium-addons-for-elementor' ),
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'The id of the removed element.', 'premium-addons-for-elementor' ),
					),
					'removed'    => array(
						'type'        => 'boolean',
						'description' => __( 'True when the element was removed and the document saved.', 'premium-addons-for-elementor' ),
					),
					'edit_url'   => array(
						'type'        => 'string',
						'description' => __( 'The Elementor editor URL for the document.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'permission_callback' => function ( $input = null ) {
				return Helpers::can_edit_input_post( $input );
			},
			'meta'                => array(
				// Don't want Angie to see this.
				'show_in_rest' => false,
				'mcp'          => array( 'public' => true ),
				'annotations'  => array(
					'readonly'    => false,
					'destructive' => true,
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

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		$post_id = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

		if ( ! $post_id ) {
			return new \WP_Error(
				'premium_addons_missing_post_id',
				__( 'A post_id is required to remove an element.', 'premium-addons-for-elementor' )
			);
		}

		$element_id = isset( $input['element_id'] ) ? (string) $input['element_id'] : '';

		if ( '' === $element_id ) {
			return new \WP_Error(
				'premium_addons_missing_element_id',
				__( 'An element_id is required to remove an element.', 'premium-addons-for-elementor' )
			);
		}

		$resolved = Helpers::resolve_document( $post_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( $document, $elements ) = $resolved;

		$found    = false;
		$elements = Helpers::remove_element( $elements, $element_id, $found );

		if ( ! $found ) {
			return new \WP_Error(
				'premium_addons_invalid_element_id',
				/* translators: %s: element id. */
				sprintf( __( 'No element with id %s exists in this document.', 'premium-addons-for-elementor' ), $element_id )
			);
		}

		$saved = Helpers::save_document( $document, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'post_id'    => $post_id,
			'element_id' => $element_id,
			'removed'    => true,
			'edit_url'   => $document->get_edit_url(),
		);
	}
}
