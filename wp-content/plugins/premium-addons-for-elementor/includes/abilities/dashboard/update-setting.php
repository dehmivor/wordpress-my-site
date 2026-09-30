<?php
/**
 * Update Setting.
 *
 * Enables or disables Premium Addons widgets, addons and features.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Dashboard;

use PremiumAddons\Admin\Includes\Admin_Helper;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Update_Setting implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'update-setting';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Update Premium Addons Settings', 'premium-addons-for-elementor' ),
			'description'         => __( 'Enables or disables Premium Addons widgets and features, or updates integration settings.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-dashboard',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'changes' => array(
						'type'        => 'array',
						'description' => __( 'The settings to change. Use premium-addons/get-settings to discover the available keys and their current values.', 'premium-addons-for-elementor' ),
						'minItems'    => 1,
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => false,
							'properties'           => array(
								'key'   => array(
									'type'        => 'string',
									'description' => __( 'The setting key, e.g. "premium-blog" (a widget/addon/feature toggle) or "premium-map-cluster" (an integration setting).', 'premium-addons-for-elementor' ),
								),
								'value' => array(
									'type'        => array( 'boolean', 'string', 'integer' ),
									'description' => __( 'The new value. Boolean for widget/addon/feature toggles; the typed value for integration settings.', 'premium-addons-for-elementor' ),
								),
							),
							'required'             => array( 'key', 'value' ),
						),
					),
				),
				'required'             => array( 'changes' ),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The applied changes and any keys that could not be matched to a settings store.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'updated'      => array(
						'type'        => 'array',
						'description' => __( 'The settings that were changed, each with its new and previous value.', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'key'            => array(
									'type' => 'string',
								),
								'value'          => array(
									'type' => array( 'boolean', 'string', 'integer' ),
								),
								'previous_value' => array(
									'type' => array( 'boolean', 'string', 'integer' ),
								),
							),
						),
					),
					'unknown_keys' => array(
						'type'        => 'array',
						'description' => __( 'Requested keys that match neither the widgets/addons store nor the integrations store (e.g. white-label keys).', 'premium-addons-for-elementor' ),
						'items'       => array(
							'type' => 'string',
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

		$changes = isset( $input['changes'] ) ? $input['changes'] : array();

		// Flatten the [ { key, value } ] list into a key => value map.
		$map = array();
		foreach ( $changes as $change ) {
			if ( isset( $change['key'] ) ) {
				$map[ $change['key'] ] = isset( $change['value'] ) ? $change['value'] : false;
			}
		}

		// Each service applies only the keys it owns and reports the rest;
		// a key is truly unknown only when neither store claims it.
		$elements     = Admin_Helper::update_elements_settings( $map );
		$integrations = Admin_Helper::update_integrations_settings( $map );

		return array(
			'updated'      => array_merge( $elements['updated'], $integrations['updated'] ),
			'unknown_keys' => array_values( array_intersect( $elements['unknown'], $integrations['unknown'] ) ),
		);
	}
}
