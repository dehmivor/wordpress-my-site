<?php
/**
 * Detect Atomic Support.
 *
 * Reports whether the site supports Elementor's newer (v4) elements.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Discovery;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Helpers;
use PremiumAddons\Includes\Helper_Functions;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Detect_Atomic_Support implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'detect-atomic-support';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Detect Atomic Support', 'premium-addons-for-elementor' ),
			'description'         => __( 'Checks whether the site supports Elementor v4 atomic elements.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The atomic support state.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'supports_atomic'   => array(
						'type'        => 'boolean',
						'description' => __( 'True when atomic (v4) elements are registered and will persist on save.', 'premium-addons-for-elementor' ),
					),
					'elementor_version' => array(
						'type'        => 'string',
						'description' => __( 'The active Elementor version.', 'premium-addons-for-elementor' ),
					),
					'recommended_mode'  => array(
						'type'        => 'string',
						'enum'        => array( 'atomic', 'legacy' ),
						'description' => __( 'atomic → build with premium-addons/add-flexbox; legacy → build with premium-addons/add-container.', 'premium-addons-for-elementor' ),
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

		$error = Helpers::guard_elementor();

		if ( $error ) {
			return $error;
		}

		// Registration is the authoritative signal — Document::save() drops
		// element types that are not registered, whatever any experiment says.
		$flexbox_registered = (bool) \Elementor\Plugin::$instance->elements_manager->get_element_types( 'e-flexbox' );

		$supports_atomic = $flexbox_registered || Helper_Functions::check_elementor_experiment( 'e_atomic_elements' );

		return array(
			'supports_atomic'   => $supports_atomic,
			'elementor_version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
			'recommended_mode'  => $supports_atomic ? 'atomic' : 'legacy',
		);
	}
}
