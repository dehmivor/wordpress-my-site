<?php
/**
 * List Available Addons.
 *
 * Returns the list of available Premium Addons — Equal Height, Display Conditions, Floating
 * Effects, Global Tooltips, and the rest — each with the key to toggle it, its
 * title and a short description.
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
class List_Pa_Addons implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'list-pa-addons';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'List Available Addons', 'premium-addons-for-elementor' ),
			'description'         => __( 'Lists the Premium Addons global features, each with its key, title and description.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The Premium Addons global features.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'features' => array(
						'type'        => 'array',
						'description' => __( 'The available global features.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'key'         => array(
									'type'        => 'string',
									'description' => __( 'The feature key — the enabled-element setting id used to toggle it (e.g. premium-equal-height, pa-display-conditions).', 'premium-addons-for-elementor' ),
								),
								'title'       => array(
									'type'        => 'string',
									'description' => __( 'The human-readable feature name shown in the dashboard.', 'premium-addons-for-elementor' ),
								),
								'description' => array(
									'type'        => 'string',
									'description' => __( 'A short sentence describing what the feature does.', 'premium-addons-for-elementor' ),
								),
							),
						),
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

		$list = Admin_Helper::get_elements_list();

		$elements = isset( $list['cat-13']['elements'] ) ? $list['cat-13']['elements'] : array();

		$features = array();

		foreach ( $elements as $element ) {

			// Internal-only toggles (e.g. premium-assets-generator) carry a key but no
			// user-facing title/description — they are not features to surface here.
			if ( empty( $element['title'] ) ) {
				continue;
			}

			$features[] = array(
				'key'         => $element['key'],
				'title'       => $element['title'],
				'description' => isset( $element['desc'] ) ? $element['desc'] : '',
			);
		}

		return array( 'features' => $features );
	}
}
