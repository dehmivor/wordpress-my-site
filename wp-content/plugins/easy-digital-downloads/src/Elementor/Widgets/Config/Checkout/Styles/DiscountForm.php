<?php
/**
 * Discount Form Style Controls Configuration for the Checkout Widget.
 *
 * @package     EDD\Elementor\Widgets\Config\Checkout\Styles
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Widgets\Config\Checkout\Styles;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Widgets\Config\Base;

/**
 * Discount Form style controls.
 *
 * The widget exposed no controls, so every designed template restyled the field and the apply button
 * in custom CSS a store owner could not edit. These cover what those templates set.
 *
 * @since 3.7.0
 */
class DiscountForm extends Base {

	/**
	 * The discount code input.
	 *
	 * Every selector in this group is anchored on `#edd_discount_code` so it carries two ids.
	 * EDD styles the same nodes from `#edd_checkout_form_wrap #edd-discount-code-wrap`, and a
	 * single-id control selector loses to that, which left the panel with no effect.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const FIELD = '#edd_discount_code #edd-discount';

	/**
	 * The apply button, which EDD renders as a link.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const BUTTON = '#edd_discount_code .edd-apply-discount';

	/**
	 * The row holding the field and the button.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const ROW = '#edd_discount_code #edd-discount-code-wrap';

	/**
	 * The link that reveals the discount field.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const LINK = '#edd_discount_code #edd_show_discount .edd_discount_link';

	/**
	 * Get the discount form controls configuration.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_controls(): array {
		return array(
			'discount_form_content' => array(
				'label'    => __( 'Discount Form', 'easy-digital-downloads' ),
				'tab'      => 'content',
				'controls' => self::get_display_controls(),
			),
			'discount_form_style'   => array(
				'label'    => __( 'Discount Form', 'easy-digital-downloads' ),
				'tab'      => 'style',
				'controls' => array_merge(
					self::get_row_controls(),
					self::get_link_controls(),
					self::get_field_controls(),
					self::get_button_controls()
				),
			),
		);
	}

	/**
	 * Controls for whether the discount field starts open or behind the link.
	 *
	 * EDD hides the field until the link is clicked. A designed template often shows it inline, so
	 * this trades the link for the field. The override beats the inline style EDD's script sets.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_display_controls(): array {
		return array(
			'discount_expanded' => array(
				'label'        => __( 'Show Discount Field', 'easy-digital-downloads' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'easy-digital-downloads' ),
				'label_off'    => __( 'No', 'easy-digital-downloads' ),
				'return_value' => 'yes',
				'default'      => '',
				'selectors'    => array(
					// EDD's script writes an inline display:block here, so !important is the only
					// way a control can hide it.
					'#edd_discount_code #edd_show_discount' => 'display: none !important;',
					// A distinct selector: EDD's script writes an inline display:none, and merging
					// into the Gap control's rule would drop the !important that beats it.
					self::ROW . '.edd-cart-adjustment' => 'display: flex !important;',
				),
				'description'  => __( 'Show the discount code field on load instead of behind the "Enter a discount code" link.', 'easy-digital-downloads' ),
			),
		);
	}

	/**
	 * Controls for the link that reveals the discount field.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_link_controls(): array {
		return array(
			'discount_link_heading'    => array(
				'label'     => __( 'Toggle Link', 'easy-digital-downloads' ),
				'type'      => 'heading',
				'separator' => 'before',
				'condition' => array( 'discount_expanded' => '' ),
			),
			'discount_link_typography' => self::create_typography_group(
				__( 'Typography', 'easy-digital-downloads' ),
				self::LINK,
				array( 'condition' => array( 'discount_expanded' => '' ) )
			),
			'discount_link_color'      => self::create_color_control(
				__( 'Color', 'easy-digital-downloads' ),
				self::LINK,
				'color',
				array( 'condition' => array( 'discount_expanded' => '' ) )
			),
			'discount_link_decoration' => array(
				'label'                => __( 'Underline', 'easy-digital-downloads' ),
				'type'                 => 'switcher',
				'label_on'             => __( 'Yes', 'easy-digital-downloads' ),
				'label_off'            => __( 'No', 'easy-digital-downloads' ),
				// `yes` like every other switcher, mapped to the CSS keyword below: a `return_value` of
				// `underline` would emit `text-decoration: yes`, which the browser drops.
				'return_value'         => 'yes',
				'default'              => '',
				'selectors_dictionary' => array(
					'yes' => 'underline',
				),
				'selectors'            => array(
					self::LINK => 'text-decoration: {{VALUE}};',
				),
				'condition'            => array( 'discount_expanded' => '' ),
			),
			// EDD renders the toggle as a <button class="edd-button-secondary">, so it arrives with a
			// background, border and radius a template must be able to strip to reach a plain link.
			'discount_link_background' => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				self::LINK,
				'background-color',
				array( 'condition' => array( 'discount_expanded' => '' ) )
			),
			'discount_link_border'     => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				self::LINK,
				array( 'condition' => array( 'discount_expanded' => '' ) )
			),
			'discount_link_radius'     => self::create_dimensions_control(
				__( 'Border Radius', 'easy-digital-downloads' ),
				self::LINK,
				'border-radius',
				array(
					'size_units' => array( 'px', '%' ),
					'condition'  => array( 'discount_expanded' => '' ),
				)
			),
			'discount_link_padding'    => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				'#edd_discount_code #edd_show_discount, ' . self::LINK,
				'padding',
				array( 'condition' => array( 'discount_expanded' => '' ) )
			),
			'discount_link_margin'     => self::create_dimensions_control(
				__( 'Margin', 'easy-digital-downloads' ),
				'#edd_discount_code #edd_show_discount',
				'margin',
				array( 'condition' => array( 'discount_expanded' => '' ) )
			),
		);
	}

	/**
	 * Controls for the row that holds the field and the button.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_row_controls(): array {
		return array(
			'discount_row_heading'    => array(
				'label' => __( 'Layout', 'easy-digital-downloads' ),
				'type'  => 'heading',
			),
			'discount_row_gap'        => self::create_slider_control(
				__( 'Gap', 'easy-digital-downloads' ),
				self::ROW,
				'gap',
				array(
					'size_units' => array( 'px', 'em', 'rem' ),
					'selectors'  => array(
						// The row is a block by default, so the gap only reads once it is a flex row.
						// flex-wrap lets the invalid-code notice drop below the field instead of
						// sharing the row with it.
						self::ROW   => 'display: flex; flex-wrap: wrap; align-items: stretch; gap: {{SIZE}}{{UNIT}};',
						// EDD nests the field and the button in a span, so the gap has to reach it too.
						self::ROW . ' .edd-discount-code-field-wrap' => 'display: flex; flex: 1 1 auto; align-items: stretch; gap: {{SIZE}}{{UNIT}};',
						self::FIELD => 'flex: 1 1 auto;',
						// The invalid-code notice is a flex sibling of the field. Give it the whole
						// row so it wraps onto its own line under the field, not a slab beside it.
						self::ROW . ' #edd-discount-error-wrap' => 'flex: 0 0 100%;',
					),
				)
			),
			'discount_row_background' => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				self::ROW,
				'background-color'
			),
			'discount_row_border'     => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				self::ROW
			),
			'discount_row_margin'     => self::create_dimensions_control(
				__( 'Margin', 'easy-digital-downloads' ),
				self::ROW,
				'margin'
			),
			'discount_row_padding'    => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				self::ROW,
				'padding'
			),
		);
	}

	/**
	 * Controls for the discount code input.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_field_controls(): array {
		return array(
			'discount_field_heading'    => array(
				'label'     => __( 'Field', 'easy-digital-downloads' ),
				'type'      => 'heading',
				'separator' => 'before',
			),
			'discount_field_typography' => self::create_typography_group(
				__( 'Typography', 'easy-digital-downloads' ),
				self::FIELD
			),
			'discount_field_color'      => self::create_color_control(
				__( 'Text Color', 'easy-digital-downloads' ),
				self::FIELD
			),
			'discount_field_background' => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				self::FIELD,
				'background-color'
			),
			'discount_field_border'     => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				self::FIELD
			),
			'discount_field_radius'     => self::create_dimensions_control(
				__( 'Border Radius', 'easy-digital-downloads' ),
				self::FIELD,
				'border-radius',
				array( 'size_units' => array( 'px', '%' ) )
			),
			'discount_field_padding'    => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				self::FIELD,
				'padding'
			),
			'discount_field_height'     => self::create_slider_control(
				__( 'Height', 'easy-digital-downloads' ),
				self::FIELD,
				'height',
				array( 'size_units' => array( 'px', 'em', 'rem' ) )
			),
		);
	}

	/**
	 * Controls for the apply button.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_button_controls(): array {
		return array(
			'discount_button_heading'          => array(
				'label'     => __( 'Apply Button', 'easy-digital-downloads' ),
				'type'      => 'heading',
				'separator' => 'before',
			),
			'discount_button_typography'       => self::create_typography_group(
				__( 'Typography', 'easy-digital-downloads' ),
				self::BUTTON
			),
			'discount_button_color'            => self::create_color_control(
				__( 'Text Color', 'easy-digital-downloads' ),
				self::BUTTON
			),
			'discount_button_background'       => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				self::BUTTON,
				'background-color'
			),
			'discount_button_hover_color'      => self::create_color_control(
				__( 'Text Color (Hover)', 'easy-digital-downloads' ),
				self::BUTTON . ':hover'
			),
			'discount_button_hover_background' => self::create_color_control(
				__( 'Background Color (Hover)', 'easy-digital-downloads' ),
				self::BUTTON . ':hover',
				'background-color'
			),
			'discount_button_border'           => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				self::BUTTON
			),
			'discount_button_radius'           => self::create_dimensions_control(
				__( 'Border Radius', 'easy-digital-downloads' ),
				self::BUTTON,
				'border-radius',
				array( 'size_units' => array( 'px', '%' ) )
			),
			'discount_button_padding'          => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				self::BUTTON,
				'padding'
			),
			'discount_button_height'           => self::create_slider_control(
				__( 'Height', 'easy-digital-downloads' ),
				self::BUTTON,
				'height',
				array( 'size_units' => array( 'px', 'em', 'rem' ) )
			),
		);
	}
}
