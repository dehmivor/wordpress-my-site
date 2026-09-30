<?php
/**
 * Form Elements Style Controls Configuration for Checkout Widget.
 *
 * @package     EDD\Elementor\Widgets\Config\Checkout\Styles
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Elementor\Widgets\Config\Checkout\Styles;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Widgets\Config\Base;

/**
 * Form Elements Style Controls Configuration for Checkout Widget.
 *
 * @since 3.6.0
 */
class FormElements extends Base {

	/**
	 * Get form elements style controls configuration.
	 *
	 * Field selectors double their class (`.edd-input.edd-input`) instead of qualifying with
	 * `form`: the editor renders the box client-side with no `<form>` ancestor, and the repeat
	 * buys back the specificity that qualifier contributed.
	 *
	 * @since 3.6.0
	 * @return array
	 */
	public static function get_controls(): array {
		return array(
			'section_form_elements_style' => array(
				'label'    => __( 'Form Elements', 'easy-digital-downloads' ),
				'controls' => array(
					// Form fields controls.
					'form_fields'             => array(
						'label' => __( 'Fields', 'easy-digital-downloads' ),
						'type'  => 'heading',
					),
					// None of these target .StripeElement: the gateway owns that container, and both Stripe
					// integrations sample .edd-input and carry the styling into the iframe themselves.
					'input_typography'        => self::create_typography_group(
						__( 'Typography', 'easy-digital-downloads' ),
						'.edd-input.edd-input'
					),
					'input_background_color'  => self::create_color_control(
						__( 'Background Color', 'easy-digital-downloads' ),
						'.edd-input.edd-input',
						'background-color'
					),
					'input_text_color'        => self::create_color_control(
						__( 'Text Color', 'easy-digital-downloads' ),
						'.edd-input.edd-input'
					),
					'input_border'            => self::create_border_group(
						__( 'Border', 'easy-digital-downloads' ),
						'.edd-input.edd-input'
					),
					'input_border_radius'     => self::create_dimensions_control(
						__( 'Border Radius', 'easy-digital-downloads' ),
						'.edd-input.edd-input',
						'border-radius',
						array( 'size_units' => array( 'px', '%' ) )
					),
					'input_box_shadow'        => self::create_box_shadow_group(
						__( 'Box Shadow', 'easy-digital-downloads' ),
						'.edd-input.edd-input'
					),
					'input_padding'           => self::create_dimensions_control(
						__( 'Padding', 'easy-digital-downloads' ),
						'.edd-input.edd-input',
						'padding'
					),

					// Form labels controls.
					'form_labels'             => array(
						'label'     => __( 'Labels', 'easy-digital-downloads' ),
						'type'      => 'heading',
						'separator' => 'before',
					),
					'label_typography'        => self::create_typography_group(
						__( 'Typography', 'easy-digital-downloads' ),
						'.edd-label.edd-label'
					),
					'label_color'             => self::create_color_control(
						__( 'Color', 'easy-digital-downloads' ),
						'.edd-label.edd-label'
					),
					'label_text_shadow'       => self::create_text_shadow_group(
						__( 'Text Shadow', 'easy-digital-downloads' ),
						'.edd-label.edd-label'
					),
					'label_margin'            => self::create_dimensions_control(
						__( 'Margin', 'easy-digital-downloads' ),
						'.edd-label.edd-label',
						'margin'
					),

					// Form descriptions controls. No show/hide switcher: in block context only the
					// personal-info descriptions render, and those are suppressed by the single-line layout.
					'form_descriptions'       => array(
						'label'     => __( 'Descriptions', 'easy-digital-downloads' ),
						'type'      => 'heading',
						'separator' => 'before',
					),
					'description_typography'  => self::create_typography_group(
						__( 'Typography', 'easy-digital-downloads' ),
						'.edd-description.edd-description'
					),
					'description_color'       => self::create_color_control(
						__( 'Color', 'easy-digital-downloads' ),
						'.edd-description.edd-description'
					),
					'description_text_shadow' => self::create_text_shadow_group(
						__( 'Text Shadow', 'easy-digital-downloads' ),
						'.edd-description.edd-description'
					),
					'description_margin'      => self::create_dimensions_control(
						__( 'Margin', 'easy-digital-downloads' ),
						'.edd-description.edd-description',
						'margin'
					),
				),
			),
		);
	}
}
