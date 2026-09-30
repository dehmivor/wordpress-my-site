<?php
/**
 * Update Element Settings.
 *
 * Updates the settings of an existing element on a page.
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
class Update_Element_Settings implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'update-element-settings';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Update Element Settings', 'premium-addons-for-elementor' ),
			'description'         => __( 'Updates the settings of an existing element on a page.', 'premium-addons-for-elementor' ) . Design_Guide::build_hint(),
			'category'            => 'pa-build',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id', 'element_id', 'settings' ),
				'properties'           => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the Elementor page, post or template containing the element.', 'premium-addons-for-elementor' ),
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'The id of the element to update.', 'premium-addons-for-elementor' ),
					),
					'settings'   => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'minProperties'        => 1,
						'description'          => __( 'The partial settings to merge into the element — keys you do not pass keep their current values. For v3 elements pass native control keys (e.g. background_background, flex_direction). For atomic (v4) elements pass CSS props in kebab-case (e.g. flex-direction, background) — applied to the element\'s local style class — and content props (tag, _cssid) — stored in the element settings; values may be plain scalars (auto-wrapped; keys ending in "color" become color values) or full { "$$type": …, "value": … } envelopes. Use premium-addons/get-page-structure to find element ids and current settings.', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The update result.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'success'      => array(
						'type'        => 'boolean',
						'description' => __( 'True when the element was updated and saved.', 'premium-addons-for-elementor' ),
					),
					'element_id'   => array(
						'type'        => 'string',
						'description' => __( 'The id of the updated element.', 'premium-addons-for-elementor' ),
					),
					'element_type' => array(
						'type'        => 'string',
						'description' => __( 'The element type (elType, or the widget type for widgets).', 'premium-addons-for-elementor' ),
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

		$post_id  = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$resolved = Helpers::resolve_document( $post_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( $document, $elements ) = $resolved;

		$element_id     = isset( $input['element_id'] ) ? (string) $input['element_id'] : '';
		$input_settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		// Wrap a raw scalar in a transformable envelope for atomic elements;
		// pre-enveloped values pass through verbatim.
		$wrap_atomic = function ( $key, $value ) {

			if ( is_array( $value ) && isset( $value['$$type'] ) ) {
				return $value;
			}

			if ( is_bool( $value ) ) {
				return Helpers::atomic_value( 'boolean', $value );
			}

			if ( is_int( $value ) || is_float( $value ) ) {
				return Helpers::atomic_value( 'number', $value );
			}

			if ( is_string( $value ) && 'color' === substr( $key, -5 ) ) {
				return Helpers::atomic_value( 'color', $value );
			}

			return Helpers::atomic_value( 'string', $value );
		};

		$element_type = '';
		$route_error  = null;

		$modify = function ( $element ) use ( $input_settings, $wrap_atomic, &$element_type, &$route_error ) {

			$el_type      = isset( $element['elType'] ) ? $element['elType'] : '';
			$widget_type  = isset( $element['widgetType'] ) ? $element['widgetType'] : '';
			$element_type = 'widget' === $el_type ? $widget_type : $el_type;

			if ( 'widget' === $el_type ) {
				$type_object = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
			} else {
				$type_object = \Elementor\Plugin::$instance->elements_manager->get_element_types( $el_type );
			}

			// Document::save() drops elements whose type is not registered, so a
			// merge into one could never persist — fail loudly instead.
			if ( ! $type_object ) {
				$route_error = new \WP_Error(
					'premium_addons_invalid_element_type',
					/* translators: %s: element type name. */
					sprintf( __( 'The element type %s is not registered on this site, so the element cannot be updated.', 'premium-addons-for-elementor' ), $element_type )
				);

				return $element;
			}

			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

			$is_atomic = class_exists( '\Elementor\Modules\AtomicWidgets\Utils\Utils' )
				&& \Elementor\Modules\AtomicWidgets\Utils\Utils::is_atomic( $type_object );

			if ( ! $is_atomic ) {

				// v3: flat partial merge into the native settings.
				$element['settings'] = array_merge( $settings, $input_settings );

				return $element;
			}

			// Atomic: props-schema keys are content settings, the rest are CSS
			// props on the element's local style class.
			$content_keys = array( 'classes', 'tag', 'link', 'attributes', '_cssid' );

			if ( method_exists( $type_object, 'get_props_schema' ) ) {

				$schema = $type_object->get_props_schema();

				if ( is_array( $schema ) && ! empty( $schema ) ) {
					$content_keys = array_keys( $schema );
				}
			}

			$style_updates = array();

			foreach ( $input_settings as $key => $value ) {

				$value = $wrap_atomic( $key, $value );

				if ( in_array( $key, $content_keys, true ) ) {
					$settings[ $key ] = $value;
				} else {
					$style_updates[ $key ] = $value;
				}
			}

			if ( ! empty( $style_updates ) ) {

				$styles = isset( $element['styles'] ) && is_array( $element['styles'] ) ? $element['styles'] : array();

				if ( empty( $styles ) ) {

					$style_id = 'e-' . $element['id'] . '-' . substr( bin2hex( random_bytes( 4 ) ), 0, 7 );

					$styles[ $style_id ] = array(
						'id'       => $style_id,
						'type'     => 'class',
						'label'    => 'local',
						'variants' => array(),
					);

					$classes = isset( $settings['classes'] ) && is_array( $settings['classes'] ) && isset( $settings['classes']['value'] )
						? $settings['classes']
						: Helpers::atomic_value( 'classes', array() );

					$classes['value'][]  = $style_id;
					$settings['classes'] = $classes;
				} else {
					$style_id = array_key_first( $styles );
				}

				$variants = isset( $styles[ $style_id ]['variants'] ) && is_array( $styles[ $style_id ]['variants'] ) ? $styles[ $style_id ]['variants'] : array();
				$target   = null;

				foreach ( $variants as $index => $variant ) {

					$meta = isset( $variant['meta'] ) && is_array( $variant['meta'] ) ? $variant['meta'] : array();

					$state      = isset( $meta['state'] ) ? $meta['state'] : null;
					$breakpoint = isset( $meta['breakpoint'] ) ? $meta['breakpoint'] : null;

					if ( null === $state && ( 'desktop' === $breakpoint || null === $breakpoint ) ) {
						$target = $index;
						break;
					}
				}

				if ( null === $target ) {

					$variants[] = array(
						'meta'  => array(
							'breakpoint' => 'desktop',
							'state'      => null,
						),
						'props' => array(),
					);

					$target = count( $variants ) - 1;
				}

				$existing_props               = isset( $variants[ $target ]['props'] ) && is_array( $variants[ $target ]['props'] ) ? $variants[ $target ]['props'] : array();
				$variants[ $target ]['props'] = array_merge( $existing_props, $style_updates );

				$styles[ $style_id ]['variants'] = $variants;
				$element['styles']               = $styles;
			}

			$element['settings'] = $settings;

			return $element;
		};

		$found    = false;
		$elements = Helpers::apply_to_element( $elements, $element_id, $modify, $found );

		if ( ! $found ) {
			return new \WP_Error(
				'premium_addons_invalid_element_id',
				/* translators: %s: element id. */
				sprintf( __( 'No element with id %s exists in this document.', 'premium-addons-for-elementor' ), $element_id )
			);
		}

		if ( $route_error ) {
			return $route_error;
		}

		$saved = Helpers::save_document( $document, $elements );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return array(
			'success'      => true,
			'element_id'   => $element_id,
			'element_type' => $element_type,
		);
	}
}
