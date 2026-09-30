<?php
/**
 * Add Flexbox.
 *
 * Adds a new v4 (atomic) flexbox container to a page.
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
class Add_Flexbox implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'add-flexbox';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Add Flexbox (Elementor v4 atomic)', 'premium-addons-for-elementor' ),
			'description'         => __( 'Adds an Elementor v4 flexbox container to a page.', 'premium-addons-for-elementor' ) . Design_Guide::build_hint(),
			'category'            => 'pa-build',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id' ),
				'properties'           => array(
					'post_id'       => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the Elementor page, post or template to add the flexbox to.', 'premium-addons-for-elementor' ),
					),
					'parent_id'     => array(
						'type'        => 'string',
						'description' => __( 'The id of an existing container element to nest the new flexbox inside. Omit to insert at the document root.', 'premium-addons-for-elementor' ),
					),
					'position'      => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Zero-based index among the parent\'s children. Omit to append at the end.', 'premium-addons-for-elementor' ),
					),
					'tag'           => array(
						'type'        => 'string',
						'enum'        => array( 'div', 'header', 'section', 'article', 'aside', 'footer' ),
						'description' => __( 'The HTML tag the flexbox renders as. Defaults to div.', 'premium-addons-for-elementor' ),
					),
					'css_id'        => array(
						'type'        => 'string',
						'description' => __( 'A CSS id attribute for the element.', 'premium-addons-for-elementor' ),
					),
					'direction'     => array(
						'type'        => 'string',
						'enum'        => array( 'row', 'column', 'row-reverse', 'column-reverse' ),
						'description' => __( 'flex-direction of the flexbox. Elementor default is row.', 'premium-addons-for-elementor' ),
					),
					'justify'       => array(
						'type'        => 'string',
						'description' => __( 'justify-content value (e.g. flex-start, center, flex-end, space-between, space-around, space-evenly).', 'premium-addons-for-elementor' ),
					),
					'align'         => array(
						'type'        => 'string',
						'description' => __( 'align-items value (e.g. flex-start, center, flex-end, stretch).', 'premium-addons-for-elementor' ),
					),
					'wrap'          => array(
						'type'        => 'string',
						'enum'        => array( 'wrap', 'nowrap', 'wrap-reverse' ),
						'description' => __( 'flex-wrap value.', 'premium-addons-for-elementor' ),
					),
					'gap'           => array(
						'type'        => 'number',
						'description' => __( 'Gap between the flexbox children.', 'premium-addons-for-elementor' ),
					),
					'gap_unit'      => array(
						'type'        => 'string',
						'description' => __( 'Unit for gap (px, em, rem, %, …). Defaults to px.', 'premium-addons-for-elementor' ),
					),
					'width'         => array(
						'type'        => 'object',
						'description' => __( 'width { size, unit? (default px) }.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'size' => array( 'type' => 'number' ),
							'unit' => array( 'type' => 'string' ),
						),
					),
					'height'        => array(
						'type'        => 'object',
						'description' => __( 'height { size, unit? (default px) }.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'size' => array( 'type' => 'number' ),
							'unit' => array( 'type' => 'string' ),
						),
					),
					'min_height'    => array(
						'type'        => 'object',
						'description' => __( 'min-height { size, unit? (default px) }.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'size' => array( 'type' => 'number' ),
							'unit' => array( 'type' => 'string' ),
						),
					),
					'background'    => array(
						'type'        => 'object',
						'description' => __( 'Background. Pass color for a solid background, or gradient for a gradient one (both may be combined — the gradient renders above the color).', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'color'    => array(
								'type'        => 'string',
								'description' => __( 'CSS background color.', 'premium-addons-for-elementor' ),
							),
							'gradient' => array(
								'type'       => 'object',
								'properties' => array(
									'color'   => array(
										'type'        => 'string',
										'description' => __( 'Gradient start color.', 'premium-addons-for-elementor' ),
									),
									'color_b' => array(
										'type'        => 'string',
										'description' => __( 'Gradient end color.', 'premium-addons-for-elementor' ),
									),
									'angle'   => array(
										'type'        => 'number',
										'description' => __( 'Gradient angle in degrees (linear type only). Defaults to 180.', 'premium-addons-for-elementor' ),
									),
									'type'    => array(
										'type' => 'string',
										'enum' => array( 'linear', 'radial' ),
									),
								),
							),
						),
					),
					'border'        => array(
						'type'        => 'object',
						'description' => __( 'Border { style? (default solid when width/color set), width? { size, unit? }, color? }.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'style' => array(
								'type' => 'string',
								'enum' => array( 'solid', 'double', 'dotted', 'dashed', 'groove', 'none' ),
							),
							'width' => array(
								'type'       => 'object',
								'properties' => array(
									'size' => array( 'type' => 'number' ),
									'unit' => array( 'type' => 'string' ),
								),
							),
							'color' => array( 'type' => 'string' ),
						),
					),
					'border_radius' => array(
						'type'        => 'object',
						'description' => __( 'border-radius { size, unit? (default px) }, applied to all corners.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'size' => array( 'type' => 'number' ),
							'unit' => array( 'type' => 'string' ),
						),
					),
					'box_shadow'    => array(
						'type'        => 'object',
						'description' => __( 'Box shadow { h_offset?, v_offset?, blur?, spread?, color?, inset? } — offsets/blur/spread in px.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'h_offset' => array( 'type' => 'number' ),
							'v_offset' => array( 'type' => 'number' ),
							'blur'     => array( 'type' => 'number' ),
							'spread'   => array( 'type' => 'number' ),
							'color'    => array( 'type' => 'string' ),
							'inset'    => array( 'type' => 'boolean' ),
						),
					),
					'margin'        => array(
						'type'        => 'object',
						'description' => __( 'Margin { top?, right?, bottom?, left?, unit? } in px by default.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'top'    => array( 'type' => 'number' ),
							'right'  => array( 'type' => 'number' ),
							'bottom' => array( 'type' => 'number' ),
							'left'   => array( 'type' => 'number' ),
							'unit'   => array( 'type' => 'string' ),
						),
					),
					'padding'       => array(
						'type'        => 'object',
						'description' => __( 'Padding { top?, right?, bottom?, left?, unit? } in px by default.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'top'    => array( 'type' => 'number' ),
							'right'  => array( 'type' => 'number' ),
							'bottom' => array( 'type' => 'number' ),
							'left'   => array( 'type' => 'number' ),
							'unit'   => array( 'type' => 'string' ),
						),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The created flexbox element.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'The id of the new flexbox element.', 'premium-addons-for-elementor' ),
					),
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the document the flexbox was added to.', 'premium-addons-for-elementor' ),
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

		// Registration is the authoritative atomic gate — Document::save()
		// silently drops unknown element types, so bail out loudly instead.
		if ( ! \Elementor\Plugin::$instance->elements_manager->get_element_types( 'e-flexbox' ) ) {
			return new \WP_Error(
				'premium_addons_atomic_not_supported',
				__( 'Atomic (v4) elements are not available on this site — the e-flexbox element is not registered. Use premium-addons/add-container instead.', 'premium-addons-for-elementor' )
			);
		}

		$post_id  = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$resolved = Helpers::resolve_document( $post_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( $document, $elements ) = $resolved;

		$element_id = Helpers::generate_element_id( Helpers::collect_element_ids( $elements ) );

		// Content props → $$type envelopes in settings.
		$settings = array();

		if ( ! empty( $input['tag'] ) ) {
			$settings['tag'] = Helpers::atomic_value( 'string', $input['tag'] );
		}

		if ( ! empty( $input['css_id'] ) ) {
			$settings['_cssid'] = Helpers::atomic_value( 'string', $input['css_id'] );
		}

		// Visual props → a local style class (desktop variant).
		$style_props = array();

		if ( ! empty( $input['direction'] ) ) {
			$style_props['flex-direction'] = Helpers::atomic_value( 'string', $input['direction'] );
		}

		if ( ! empty( $input['justify'] ) ) {
			$style_props['justify-content'] = Helpers::atomic_value( 'string', $input['justify'] );
		}

		if ( ! empty( $input['align'] ) ) {
			$style_props['align-items'] = Helpers::atomic_value( 'string', $input['align'] );
		}

		if ( ! empty( $input['wrap'] ) ) {
			$style_props['flex-wrap'] = Helpers::atomic_value( 'string', $input['wrap'] );
		}

		if ( isset( $input['gap'] ) ) {
			$style_props['gap'] = Helpers::atomic_size( $input['gap'], ! empty( $input['gap_unit'] ) ? $input['gap_unit'] : 'px' );
		}

		foreach ( array(
			'width'      => 'width',
			'height'     => 'height',
			'min_height' => 'min-height',
		) as $key => $css_prop ) {
			if ( ! empty( $input[ $key ] ) && isset( $input[ $key ]['size'] ) ) {
				$style_props[ $css_prop ] = Helpers::atomic_size(
					$input[ $key ]['size'],
					! empty( $input[ $key ]['unit'] ) ? $input[ $key ]['unit'] : 'px'
				);
			}
		}

		if ( ! empty( $input['background'] ) ) {

			$background       = $input['background'];
			$background_value = array();

			if ( ! empty( $background['color'] ) ) {
				$background_value['color'] = Helpers::atomic_value( 'color', $background['color'] );
			}

			if ( ! empty( $background['gradient'] ) ) {

				$gradient = $background['gradient'];

				$stops = array(
					array(
						'color'  => Helpers::atomic_value( 'color', ! empty( $gradient['color'] ) ? $gradient['color'] : '#000000' ),
						'offset' => Helpers::atomic_value( 'number', 0 ),
					),
					array(
						'color'  => Helpers::atomic_value( 'color', ! empty( $gradient['color_b'] ) ? $gradient['color_b'] : '#ffffff' ),
						'offset' => Helpers::atomic_value( 'number', 100 ),
					),
				);

				$background_value['background-overlay'] = Helpers::atomic_value(
					'background-overlay',
					array(
						Helpers::atomic_value(
							'background-gradient-overlay',
							array(
								'type'  => Helpers::atomic_value( 'string', ! empty( $gradient['type'] ) ? $gradient['type'] : 'linear' ),
								'angle' => Helpers::atomic_value( 'number', isset( $gradient['angle'] ) ? $gradient['angle'] : 180 ),
								'stops' => Helpers::atomic_value(
									'gradient-color-stop',
									array_map(
										function ( $stop ) {
											return Helpers::atomic_value( 'color-stop', $stop );
										},
										$stops
									)
								),
							)
						),
					)
				);
			}

			if ( ! empty( $background_value ) ) {
				$style_props['background'] = Helpers::atomic_value( 'background', $background_value );
			}
		}

		if ( ! empty( $input['border'] ) ) {

			$border = $input['border'];

			if ( ! empty( $border['width'] ) || ! empty( $border['color'] ) || ! empty( $border['style'] ) ) {
				$style_props['border-style'] = Helpers::atomic_value( 'string', ! empty( $border['style'] ) ? $border['style'] : 'solid' );
			}

			if ( ! empty( $border['width'] ) && isset( $border['width']['size'] ) ) {
				$style_props['border-width'] = Helpers::atomic_size(
					$border['width']['size'],
					! empty( $border['width']['unit'] ) ? $border['width']['unit'] : 'px'
				);
			}

			if ( ! empty( $border['color'] ) ) {
				$style_props['border-color'] = Helpers::atomic_value( 'color', $border['color'] );
			}
		}

		if ( ! empty( $input['border_radius'] ) && isset( $input['border_radius']['size'] ) ) {
			$style_props['border-radius'] = Helpers::atomic_size(
				$input['border_radius']['size'],
				! empty( $input['border_radius']['unit'] ) ? $input['border_radius']['unit'] : 'px'
			);
		}

		if ( ! empty( $input['box_shadow'] ) ) {

			$shadow       = $input['box_shadow'];
			$shadow_value = array(
				'hOffset' => Helpers::atomic_size( isset( $shadow['h_offset'] ) ? $shadow['h_offset'] : 0 ),
				'vOffset' => Helpers::atomic_size( isset( $shadow['v_offset'] ) ? $shadow['v_offset'] : 0 ),
				'blur'    => Helpers::atomic_size( isset( $shadow['blur'] ) ? $shadow['blur'] : 0 ),
				'spread'  => Helpers::atomic_size( isset( $shadow['spread'] ) ? $shadow['spread'] : 0 ),
				'color'   => Helpers::atomic_value( 'color', ! empty( $shadow['color'] ) ? $shadow['color'] : 'rgba(0,0,0,0.5)' ),
			);

			if ( ! empty( $shadow['inset'] ) ) {
				$shadow_value['position'] = Helpers::atomic_value( 'string', 'inset' );
			}

			$style_props['box-shadow'] = Helpers::atomic_value( 'box-shadow', array( Helpers::atomic_value( 'shadow', $shadow_value ) ) );
		}

		if ( ! empty( $input['margin'] ) ) {
			$style_props['margin'] = Helpers::atomic_dimensions( $input['margin'] );
		}

		if ( ! empty( $input['padding'] ) ) {
			$style_props['padding'] = Helpers::atomic_dimensions( $input['padding'] );
		}

		$node = array(
			'id'       => $element_id,
			'elType'   => 'e-flexbox',
			'settings' => $settings,
			'elements' => array(),
		);

		if ( ! empty( $style_props ) ) {

			$style_id = 'e-' . $element_id . '-' . substr( bin2hex( random_bytes( 4 ) ), 0, 7 );

			$node['styles'] = array(
				$style_id => array(
					'id'       => $style_id,
					'type'     => 'class',
					'label'    => 'local',
					'variants' => array(
						array(
							'meta'  => array(
								'breakpoint' => 'desktop',
								'state'      => null,
							),
							'props' => $style_props,
						),
					),
				),
			);

			$node['settings']['classes'] = Helpers::atomic_value( 'classes', array( $style_id ) );
		}

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
			'element_id' => $element_id,
			'post_id'    => $post_id,
		);
	}
}
