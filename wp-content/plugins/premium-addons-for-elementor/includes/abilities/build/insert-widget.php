<?php
/**
 * Insert Widget.
 *
 * Inserts any registered classic (v3) widget into a page, post or template,
 * with its settings.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Build;

use PremiumAddons\Includes\Abilities\Helpers;
use PremiumAddons\Includes\Abilities\Design\Design_Guide;

use PremiumAddons\Includes\Abilities\Contracts\Ability_Handler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Ability handler.
 */
class Insert_Widget implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'insert-widget';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Use Premium Addons Widgets', 'premium-addons-for-elementor' ),
			'description'         => __( 'Inserts a widget from Elementor core or Premium Addons into a page, post or template with its settings.', 'premium-addons-for-elementor' ) . Design_Guide::build_hint(),
			'category'            => 'pa-build',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id', 'widget_type' ),
				'properties'           => array(
					'post_id'     => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the Elementor page, post or template to insert the widget into.', 'premium-addons-for-elementor' ),
					),
					'widget_type' => array(
						'type'        => 'string',
						'description' => __( 'The widget type name to insert (e.g. premium-carousel, heading, button). Must be registered — a Premium Addons widget disabled in the dashboard is not. Third-party plugin widgets require Premium Addons Pro; without it only Elementor and Premium Addons widgets can be inserted.', 'premium-addons-for-elementor' ),
					),
					'settings'    => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'description'          => __( 'The widget settings as native control keys. Call premium-addons/get-widget-schema for this widget_type first to get the exact keys, types and prerequisites. Repeater controls take an array of row objects. Keys Elementor does not know are reported in unknown_keys, not rejected. Group prerequisite toggles you omit (e.g. background_background for background_color) are auto-set and echoed in auto_set.', 'premium-addons-for-elementor' ),
					),
					'parent_id'   => array(
						'type'        => 'string',
						'description' => __( 'The id of an existing container element to nest the widget inside. Omit to insert at the document root.', 'premium-addons-for-elementor' ),
					),
					'position'    => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Zero-based index among the parent\'s children. Omit to append at the end.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The inserted widget.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'element_id'   => array(
						'type'        => 'string',
						'description' => __( 'The id of the new widget element.', 'premium-addons-for-elementor' ),
					),
					'post_id'      => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the document the widget was inserted into.', 'premium-addons-for-elementor' ),
					),
					'widget_type'  => array(
						'type'        => 'string',
						'description' => __( 'The widget type that was inserted.', 'premium-addons-for-elementor' ),
					),
					'unknown_keys' => array(
						'type'        => 'array',
						'items'       => array( 'type' => 'string' ),
						'description' => __( 'Setting keys the caller passed that are not controls on this widget — kept out of the saved settings so the agent learns it guessed a key wrong.', 'premium-addons-for-elementor' ),
					),
					'auto_set'     => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'description'          => __( 'Group prerequisite toggles that were auto-set to make the passed values take effect (toggle key => value).', 'premium-addons-for-elementor' ),
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

		$widget_type = isset( $input['widget_type'] ) ? trim( (string) $input['widget_type'] ) : '';

		if ( '' === $widget_type ) {
			return new \WP_Error(
				'premium_addons_missing_widget_type',
				__( 'A widget_type is required.', 'premium-addons-for-elementor' )
			);
		}

		// Prime the widget registry — elementor/widgets/register may not have
		// fired yet on a REST/MCP request, so an un-primed lookup would miss.
		\Elementor\Plugin::$instance->widgets_manager->get_widget_types();

		$type_object = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );

		// Document::save() silently drops unknown widget types — a Premium
		// Addons widget disabled in the dashboard is never registered — so bail
		// loudly instead of persisting nothing.
		if ( ! $type_object ) {
			return new \WP_Error(
				'premium_addons_invalid_widget_type',
				/* translators: %s: widget type name. */
				sprintf( __( 'No widget type %s is registered on this site. A Premium Addons widget disabled in the dashboard is not registered.', 'premium-addons-for-elementor' ), $widget_type )
			);
		}

		$source_error = Helpers::guard_widget_source( $type_object, $widget_type );

		if ( $source_error ) {
			return $source_error;
		}

		// Atomic (v4) widgets store their props as $$type envelopes and their
		// visual props on a styles map — a different shape this ability does not
		// build yet. Reject them cleanly rather than saving broken flat settings.
		$is_atomic = class_exists( '\Elementor\Modules\AtomicWidgets\Utils\Utils' )
			&& \Elementor\Modules\AtomicWidgets\Utils\Utils::is_atomic( $type_object );

		if ( $is_atomic ) {
			return new \WP_Error(
				'premium_addons_atomic_widget_unsupported',
				/* translators: %s: widget type name. */
				sprintf( __( 'The widget type %s is an Elementor v4 atomic widget, which premium-addons/insert-widget does not support yet.', 'premium-addons-for-elementor' ), $widget_type )
			);
		}

		$post_id  = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$resolved = Helpers::resolve_document( $post_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( $document, $elements ) = $resolved;

		$element_id     = Helpers::generate_element_id( Helpers::collect_element_ids( $elements ) );
		$input_settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		$controls = $type_object->get_controls();

		// Unknown keys are reported, not rejected — Elementor discards them
		// silently on save, so surfacing them is the only feedback an agent gets.
		$unknown_keys = array_values( array_diff( array_keys( $input_settings ), array_keys( $controls ) ) );

		// A value whose gating group toggle is unset is inert (background_color
		// without background_background = classic renders nothing), so fill any
		// unmet prerequisite toggle and echo what was set back.
		list( $settings, $auto_set ) = Helpers::apply_prerequisite_toggles( $controls, $input_settings );

		$node = array(
			'id'         => $element_id,
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'settings'   => $settings,
			'elements'   => array(),
		);

		$parent_id = isset( $input['parent_id'] ) ? (string) $input['parent_id'] : '';
		$position  = isset( $input['position'] ) ? absint( $input['position'] ) : null;

		$found    = false;
		$elements = Helpers::insert_element( $elements, $parent_id, $position, $node, $found );

		if ( ! $found ) {
			return new \WP_Error(
				'premium_addons_invalid_parent_id',
				/* translators: %s: element id. */
				sprintf( __( 'No element with id %s exists in this document.', 'premium-addons-for-elementor' ), $parent_id )
			);
		}

		$saved = Helpers::save_document( $document, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'element_id'   => $element_id,
			'post_id'      => $post_id,
			'widget_type'  => $widget_type,
			'unknown_keys' => $unknown_keys,
			'auto_set'     => (object) $auto_set,
		);
	}
}
