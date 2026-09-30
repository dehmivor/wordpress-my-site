<?php
/**
 * Get Global Settings.
 *
 * Reads the site's global design settings — colors, fonts and spacing.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Discovery;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Get_Global_Settings implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-global-settings';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Elementor Global Settings', 'premium-addons-for-elementor' ),
			'description'         => __( "Reads the site's global colors, fonts and layout settings.", 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The active Elementor kit global design tokens.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'colors'     => array(
						'type'        => 'object',
						'description' => __( 'Global colors, split into system and custom sets. Each entry carries its _id, title and color value.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'system' => array(
								'type'  => 'array',
								'items' => array(
									'type'                 => 'object',
									'additionalProperties' => true,
								),
							),
							'custom' => array(
								'type'  => 'array',
								'items' => array(
									'type'                 => 'object',
									'additionalProperties' => true,
								),
							),
						),
					),
					'typography' => array(
						'type'        => 'object',
						'description' => __( 'Global typography, split into system and custom sets. Each entry carries its _id, title and typography values.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'system' => array(
								'type'  => 'array',
								'items' => array(
									'type'                 => 'object',
									'additionalProperties' => true,
								),
							),
							'custom' => array(
								'type'  => 'array',
								'items' => array(
									'type'                 => 'object',
									'additionalProperties' => true,
								),
							),
						),
					),
					'settings'   => array(
						'type'                 => 'object',
						'description'          => __( 'Key layout tokens (content width, space between widgets).', 'premium-addons-for-elementor' ),
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

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

		if ( ! $kit || ! $kit->get_main_id() ) {
			return new \WP_Error(
				'premium_addons_no_active_kit',
				__( 'There is no active Elementor kit.', 'premium-addons-for-elementor' )
			);
		}

		$kit_settings = $kit->get_settings();

		$get = function ( $key ) use ( $kit_settings ) {
			return isset( $kit_settings[ $key ] ) && is_array( $kit_settings[ $key ] ) ? $kit_settings[ $key ] : array();
		};

		return array(
			'colors'     => array(
				'system' => $get( 'system_colors' ),
				'custom' => $get( 'custom_colors' ),
			),
			'typography' => array(
				'system' => $get( 'system_typography' ),
				'custom' => $get( 'custom_typography' ),
			),
			'settings'   => array(
				'container_width'       => isset( $kit_settings['container_width'] ) ? $kit_settings['container_width'] : null,
				'space_between_widgets' => isset( $kit_settings['space_between_widgets'] ) ? $kit_settings['space_between_widgets'] : null,
			),
		);
	}
}
