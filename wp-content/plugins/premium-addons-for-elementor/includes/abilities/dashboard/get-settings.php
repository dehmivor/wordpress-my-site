<?php
/**
 * Get Settings.
 *
 * Lists the Premium Addons dashboard settings and their current values.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Dashboard;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Helper_Functions;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Get_Settings implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-settings';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Premium Addons Settings', 'premium-addons-for-elementor' ),
			'description'         => __( 'Lists all Premium Addons settings and their current values.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-dashboard',
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'Premium Addons dashboard settings grouped by store.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'widgets_addons' => array(
						'type'                 => 'object',
						'description'          => __( 'Widget, addon and global-feature toggles. true = enabled, false = disabled.', 'premium-addons-for-elementor' ),
						'additionalProperties' => array(
							'type' => 'boolean',
						),
					),
					'integrations'   => array(
						'type'                 => 'object',
						'description'          => __( 'Maps and third-party integration settings (pa_maps_save_settings).', 'premium-addons-for-elementor' ),
						'additionalProperties' => true,
					),
					'white_label'    => array(
						'type'                 => 'object',
						'description'          => __( 'White-label settings (pa_wht_lbl_save_settings). Empty object when Premium Addons Pro is not active.', 'premium-addons-for-elementor' ),
						'additionalProperties' => true,
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

		$settings = array(
			'widgets_addons' => Admin_Helper::get_enabled_elements(),
			'integrations'   => Admin_Helper::get_integrations_settings(),
			'white_label'    => array(),
		);

		// White labeling lives in Premium Addons Pro; stay empty when it is absent.
		if ( Helper_Functions::check_papro_version() && class_exists( '\PremiumAddonsPro\Includes\White_Label\Helper' ) ) {
			$settings['white_label'] = \PremiumAddonsPro\Includes\White_Label\Helper::get_white_labeling_settings();
		}

		return $settings;
	}
}
