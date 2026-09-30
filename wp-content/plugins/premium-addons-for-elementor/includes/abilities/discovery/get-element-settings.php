<?php
/**
 * Get Element Settings.
 *
 * Reads the current settings of a single element on a page.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Discovery;

use PremiumAddons\Includes\Abilities\Helpers;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Get_Element_Settings implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'get-element-settings';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Get Element Settings', 'premium-addons-for-elementor' ),
			'description'         => __( 'Reads the current settings of a single element on a page.', 'premium-addons-for-elementor' ),
			'category'            => 'pa-discovery',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id', 'element_id' ),
				'properties'           => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the page, post or template containing the element.', 'premium-addons-for-elementor' ),
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'The id of the element to read. Use premium-addons/get-page-structure to find element ids.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The element and its stored settings.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the document the element lives in.', 'premium-addons-for-elementor' ),
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'The id of the element.', 'premium-addons-for-elementor' ),
					),
					'elType'     => array(
						'type'        => 'string',
						'description' => __( 'The element type (e.g. container, e-flexbox, widget).', 'premium-addons-for-elementor' ),
					),
					'widgetType' => array(
						'type'        => 'string',
						'description' => __( 'The widget type when elType is widget (e.g. premium-carousel), empty otherwise.', 'premium-addons-for-elementor' ),
					),
					'is_atomic'  => array(
						'type'        => 'boolean',
						'description' => __( 'True when the element is an Elementor v4 atomic element, whose visual props live in styles rather than settings.', 'premium-addons-for-elementor' ),
					),
					'settings'   => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'description'          => __( 'The stored settings map. Only controls that were set are present — there is no control-default merge. For atomic elements this holds content props (tag, link, _cssid, …); visual props are in styles.', 'premium-addons-for-elementor' ),
					),
					'styles'     => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'description'          => __( 'The atomic (v4) styles map — local style classes and their CSS-prop variants. Always {} for v3 elements.', 'premium-addons-for-elementor' ),
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

		$post_id    = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$element_id = isset( $input['element_id'] ) ? (string) $input['element_id'] : '';

		if ( ! $post_id ) {
			return new \WP_Error(
				'premium_addons_missing_post_id',
				__( 'A post_id is required to read an element.', 'premium-addons-for-elementor' )
			);
		}

		if ( '' === $element_id ) {
			return new \WP_Error(
				'premium_addons_missing_element_id',
				__( 'An element_id is required to read an element.', 'premium-addons-for-elementor' )
			);
		}

		$resolved = Helpers::resolve_document( $post_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( , $elements ) = $resolved;

		$element = Helpers::find_element( $elements, $element_id );

		if ( null === $element ) {
			return new \WP_Error(
				'premium_addons_invalid_element_id',
				/* translators: %s: element id. */
				sprintf( __( 'No element with id %s exists in this document.', 'premium-addons-for-elementor' ), $element_id )
			);
		}

		$el_type     = isset( $element['elType'] ) ? $element['elType'] : '';
		$widget_type = isset( $element['widgetType'] ) ? $element['widgetType'] : '';

		// Route by element family the same way update-element-settings does, to
		// report whether the element is atomic (v4).
		if ( 'widget' === $el_type ) {
			$type_object = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
		} else {
			$type_object = \Elementor\Plugin::$instance->elements_manager->get_element_types( $el_type );
		}

		$is_atomic = $type_object
			&& class_exists( '\Elementor\Modules\AtomicWidgets\Utils\Utils' )
			&& \Elementor\Modules\AtomicWidgets\Utils\Utils::is_atomic( $type_object );

		$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();
		$styles   = isset( $element['styles'] ) && is_array( $element['styles'] ) ? $element['styles'] : array();

		return array(
			'post_id'    => $post_id,
			'element_id' => $element_id,
			'elType'     => $el_type,
			'widgetType' => $widget_type,
			'is_atomic'  => (bool) $is_atomic,
			'settings'   => (object) $settings,
			'styles'     => (object) $styles,
		);
	}
}
