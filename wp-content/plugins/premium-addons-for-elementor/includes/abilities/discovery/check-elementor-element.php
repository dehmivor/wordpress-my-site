<?php
/**
 * Check Elementor Element.
 *
 * Checks whether an Elementor element or widget is available on the site.
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
class Check_Elementor_Element implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'check-elementor-element';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Check Elementor Element', 'premium-addons-for-elementor' ),
			'description'         => __( 'Checks whether an Elementor element or widget is available on the site.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'element' ),
				'properties'           => array(
					'element' => array(
						'type'        => 'string',
						'description' => __( 'The element or widget type name to check (e.g. e-flexbox, container, premium-carousel).', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'Whether the type is registered and where.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'exists'       => array(
						'type'        => 'boolean',
						'description' => __( 'True when the type is registered on this site.', 'premium-addons-for-elementor' ),
					),
					'type'         => array(
						'type'        => array( 'string', 'null' ),
						'enum'        => array( 'element', 'widget', null ),
						'description' => __( 'element for structural types (container, e-flexbox, section), widget for widgets, null when not registered.', 'premium-addons-for-elementor' ),
					),
					'title'        => array(
						'type'        => 'string',
						'description' => __( 'The human-readable title of the registered type, when available.', 'premium-addons-for-elementor' ),
					),
					'available'    => array(
						'type'        => 'boolean',
						'description' => __( 'True when this install may use the type through these abilities. False for a third-party widget while Premium Addons Pro is inactive.', 'premium-addons-for-elementor' ),
					),
					'requires'     => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( 'The plugin required to unlock the type when locked (premium-addons-pro), or null when available.', 'premium-addons-for-elementor' ),
					),
					'upgrade_link' => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( 'The Premium Addons Pro upgrade URL when the type is locked, or null when available.', 'premium-addons-for-elementor' ),
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

		$name = isset( $input['element'] ) ? trim( (string) $input['element'] ) : '';

		if ( '' === $name ) {
			return new \WP_Error(
				'premium_addons_missing_element',
				__( 'An element type name is required.', 'premium-addons-for-elementor' )
			);
		}

		$element_type = \Elementor\Plugin::$instance->elements_manager->get_element_types( $name );

		if ( $element_type ) {
			return array(
				'exists'       => true,
				'type'         => 'element',
				'title'        => $element_type->get_title(),
				'available'    => true,
				'requires'     => null,
				'upgrade_link' => null,
			);
		}

		$widget_type = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $name );

		if ( $widget_type ) {

			$locked = is_wp_error( Helpers::guard_widget_source( $widget_type, $name ) );

			return array(
				'exists'       => true,
				'type'         => 'widget',
				'title'        => $widget_type->get_title(),
				'available'    => ! $locked,
				'requires'     => $locked ? 'premium-addons-pro' : null,
				'upgrade_link' => $locked ? Helper_Functions::get_campaign_link( 'https://premiumaddons.com/pro/#get-pa-pro', 'ai-abilities', 'mcp', 'get-pro' ) : null,
			);
		}

		return array(
			'exists'       => false,
			'type'         => null,
			'available'    => false,
			'requires'     => null,
			'upgrade_link' => null,
		);
	}
}
