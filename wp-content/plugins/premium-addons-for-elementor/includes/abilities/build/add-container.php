<?php
/**
 * Add Container.
 *
 * Adds a new v3 (legacy) container to a page.
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
class Add_Container implements Ability_Handler {

	/**
	 * Get the short ability name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'add-container';
	}

	/**
	 * Get the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_registration_args() {
		return array(
			'label'               => __( 'Add Container (Elementor v3)', 'premium-addons-for-elementor' ),
			'description'         => __( 'Adds an Elementor container to a page.', 'premium-addons-for-elementor' ) . Design_Guide::build_hint(),
			'category'            => 'pa-build',
			'input_schema'        => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'required'             => array( 'post_id' ),
				'properties'           => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the Elementor page, post or template to add the container to.', 'premium-addons-for-elementor' ),
					),
					'parent_id'  => array(
						'type'        => 'string',
						'description' => __( 'The id of an existing container to nest the new container inside. Omit to insert at the document root.', 'premium-addons-for-elementor' ),
					),
					'position'   => array(
						'type'        => 'integer',
						'minimum'     => 0,
						'description' => __( 'Zero-based index among the parent\'s children. Omit to append at the end.', 'premium-addons-for-elementor' ),
					),
					'html_tag'   => array(
						'type'        => 'string',
						'enum'        => array( 'div', 'header', 'footer', 'main', 'article', 'section', 'aside', 'nav' ),
						'description' => __( 'The HTML tag the container renders as. Defaults to div.', 'premium-addons-for-elementor' ),
					),
					'width'      => array(
						'type'        => 'object',
						'description' => __( 'Container width. Sets content_width=full plus the width control. { size, unit? (px|%|vw, default %) }.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'size' => array( 'type' => 'number' ),
							'unit' => array( 'type' => 'string' ),
						),
					),
					'min_height' => array(
						'type'        => 'object',
						'description' => __( 'Minimum height of the container (the container has no fixed-height control). { size, unit? (px|vh, default px) }.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'size' => array( 'type' => 'number' ),
							'unit' => array( 'type' => 'string' ),
						),
					),
					'background' => array(
						'type'        => 'object',
						'description' => __( 'Container background. Pass color for a classic background, or gradient for a gradient one (gradient wins when both are set).', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'color'    => array(
								'type'        => 'string',
								'description' => __( 'CSS color for a classic background.', 'premium-addons-for-elementor' ),
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
					'border'     => array(
						'type'        => 'object',
						'description' => __( 'Container border. Setting width or color applies style (default solid) as the prerequisite border_border toggle.', 'premium-addons-for-elementor' ),
						'properties'  => array(
							'style'  => array(
								'type' => 'string',
								'enum' => array( 'solid', 'double', 'dotted', 'dashed', 'groove' ),
							),
							'width'  => array(
								'type'        => 'object',
								'description' => __( '{ top?, right?, bottom?, left?, unit? } in px by default.', 'premium-addons-for-elementor' ),
								'properties'  => array(
									'top'    => array( 'type' => 'number' ),
									'right'  => array( 'type' => 'number' ),
									'bottom' => array( 'type' => 'number' ),
									'left'   => array( 'type' => 'number' ),
									'unit'   => array( 'type' => 'string' ),
								),
							),
							'color'  => array( 'type' => 'string' ),
							'radius' => array(
								'type'        => 'object',
								'description' => __( 'Border radius { top?, right?, bottom?, left?, unit? } — not gated on the border style.', 'premium-addons-for-elementor' ),
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
					'margin'     => array(
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
					'padding'    => array(
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
					'settings'   => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'description'          => __( 'Passthrough for any other native container control key, applied verbatim and overriding the typed inputs. Responsive variants use the _tablet/_mobile suffixes. Conditional controls need their toggles (e.g. flex_* need container_type=flex, box_shadow_box_shadow needs box_shadow_box_shadow_type=yes).', 'premium-addons-for-elementor' ),
					),
				),
			),
			'output_schema'       => array(
				'type'        => 'object',
				'description' => __( 'The created container element.', 'premium-addons-for-elementor' ),
				'properties'  => array(
					'element_id' => array(
						'type'        => 'string',
						'description' => __( 'The id of the new container element.', 'premium-addons-for-elementor' ),
					),
					'post_id'    => array(
						'type'        => 'integer',
						'description' => __( 'The ID of the document the container was added to.', 'premium-addons-for-elementor' ),
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

		// Document::save() silently drops unknown element types, so verify the
		// container element is registered (the 'container' experiment can be
		// inactive on sites installed before Elementor 3.16).
		if ( ! \Elementor\Plugin::$instance->elements_manager->get_element_types( 'container' ) ) {
			return new \WP_Error(
				'premium_addons_container_not_supported',
				__( 'The Elementor container element is not registered on this site. Activate the Flexbox Container feature in Elementor settings, or use section-based layouts.', 'premium-addons-for-elementor' )
			);
		}

		$post_id  = ! empty( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$resolved = Helpers::resolve_document( $post_id );

		if ( is_wp_error( $resolved ) ) {
			return $resolved;
		}

		list( $document, $elements ) = $resolved;

		$settings = array();

		if ( ! empty( $input['html_tag'] ) ) {
			$settings['html_tag'] = $input['html_tag'];
		}

		if ( ! empty( $input['width'] ) ) {
			$settings['content_width'] = 'full';
			$settings['width']         = Helpers::format_slider(
				array(
					'size' => isset( $input['width']['size'] ) ? $input['width']['size'] : '',
					'unit' => ! empty( $input['width']['unit'] ) ? $input['width']['unit'] : '%',
				)
			);
		}

		if ( ! empty( $input['min_height'] ) ) {
			$settings['min_height'] = Helpers::format_slider( $input['min_height'] );
		}

		if ( ! empty( $input['background'] ) ) {

			$background = $input['background'];

			if ( ! empty( $background['gradient'] ) ) {

				$gradient = $background['gradient'];

				$settings['background_background'] = 'gradient';

				if ( ! empty( $gradient['color'] ) ) {
					$settings['background_color'] = $gradient['color'];
				}

				if ( ! empty( $gradient['color_b'] ) ) {
					$settings['background_color_b'] = $gradient['color_b'];
				}

				if ( ! empty( $gradient['type'] ) ) {
					$settings['background_gradient_type'] = $gradient['type'];
				}

				if ( isset( $gradient['angle'] ) ) {
					$settings['background_gradient_angle'] = array(
						'unit' => 'deg',
						'size' => $gradient['angle'],
					);
				}
			} elseif ( ! empty( $background['color'] ) ) {

				$settings['background_background'] = 'classic';
				$settings['background_color']      = $background['color'];
			}
		}

		if ( ! empty( $input['border'] ) ) {

			$border = $input['border'];

			if ( ! empty( $border['width'] ) || ! empty( $border['color'] ) || ! empty( $border['style'] ) ) {
				$settings['border_border'] = ! empty( $border['style'] ) ? $border['style'] : 'solid';
			}

			if ( ! empty( $border['width'] ) ) {
				$settings['border_width'] = Helpers::format_dimensions( $border['width'] );
			}

			if ( ! empty( $border['color'] ) ) {
				$settings['border_color'] = $border['color'];
			}

			if ( ! empty( $border['radius'] ) ) {
				$settings['border_radius'] = Helpers::format_dimensions( $border['radius'] );
			}
		}

		if ( ! empty( $input['margin'] ) ) {
			$settings['margin'] = Helpers::format_dimensions( $input['margin'] );
		}

		if ( ! empty( $input['padding'] ) ) {
			$settings['padding'] = Helpers::format_dimensions( $input['padding'] );
		}

		// Passthrough wins over the typed inputs.
		if ( ! empty( $input['settings'] ) && is_array( $input['settings'] ) ) {
			$settings = array_merge( $settings, $input['settings'] );
		}

		$parent_id  = isset( $input['parent_id'] ) ? (string) $input['parent_id'] : '';
		$position   = isset( $input['position'] ) ? absint( $input['position'] ) : null;
		$element_id = Helpers::generate_element_id( Helpers::collect_element_ids( $elements ) );

		$node = array(
			'id'       => $element_id,
			'elType'   => 'container',
			'isInner'  => '' !== $parent_id,
			'settings' => $settings,
			'elements' => array(),
		);

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
