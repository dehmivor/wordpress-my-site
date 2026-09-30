<?php
/**
 * Clear Dynamic Assets.
 *
 * Clears the generated CSS and JS asset files across the site.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Dashboard;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Assets_Manager;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Clear_Dynamic_Assets implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'clear-dynamic-assets';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Clear Premium Addons Dynamic Assets', 'premium-addons-for-elementor' ),
			'description'         => __( 'Clears the generated CSS/JS files and related caches.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-dashboard',
			'input_schema'        => array(
				'type'                 => 'object',
				'default'              => (object) array(),
				'additionalProperties' => false,
				'properties'           => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the page/post whose dynamic assets to clear. Omit to clear every page site-wide. Use premium-addons/get-id-by-title to resolve a page title to its ID.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The result of the clear operation.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'cleared' => array(
						'type'        => 'boolean',
						'description' => __( 'Always true when the assets were cleared.', 'premium-addons-for-elementor' ),
					),
					'post_id' => array(
						'type'        => array( 'integer', 'null' ),
						'description' => __( 'The page that was cleared, or null when the assets were cleared site-wide.', 'premium-addons-for-elementor' ),
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

		// The schema top-level default arrives as an empty stdClass, not an array.
		$input = is_array( $input ) ? $input : array();

		$enabled = Admin_Helper::get_enabled_elements();

		if ( empty( $enabled['premium-assets-generator'] ) ) {
			return new \WP_Error(
				'premium_addons_assets_generator_disabled',
				__( 'The Assets Generator feature is disabled, so there are no dynamic assets to clear. Enable it from the Premium Addons dashboard first.', 'premium-addons-for-elementor' )
			);
		}

		$post_id = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;

		if ( $post_id && ! get_post( $post_id ) ) {
			return new \WP_Error(
				'premium_addons_invalid_post_id',
				/* translators: %d: post ID. */
				sprintf( __( 'No page/post found with ID %d.', 'premium-addons-for-elementor' ), $post_id )
			);
		}

		Assets_Manager::clear_dynamic_assets( $post_id );

		return array(
			'cleared' => true,
			'post_id' => $post_id ? $post_id : null,
		);
	}
}
