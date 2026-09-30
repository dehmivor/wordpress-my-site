<?php
/**
 * Account Form Switcher Style Controls Configuration for the Checkout Widget.
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
 * Account form-switch button style controls.
 *
 * The "Log In" / "Register for a new account" buttons that swap the guest, login and register forms
 * had no controls, so a template could not restyle or reposition them without custom CSS. A lone
 * button defaults to an absolute top corner that assumes a section title beside it; the Layout
 * control drops it into normal flow for templates that hide the title.
 *
 * @since 3.7.0
 */
class FormSwitcher extends Base {

	/**
	 * The row that holds the form-switch buttons.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const WRAP = '#edd-account-forms';

	/**
	 * A form-switch button (Log In or Register).
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const BUTTON = '#edd-account-forms button';

	/**
	 * Get the account form switcher style controls configuration.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_controls(): array {
		return array(
			'form_switcher_style' => array(
				'label'    => __( 'Log In / Register Buttons', 'easy-digital-downloads' ),
				'tab'      => 'style',
				'controls' => array_merge(
					self::get_layout_controls(),
					self::get_button_controls()
				),
			),
		);
	}

	/**
	 * Controls for the row that holds the switch buttons.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_layout_controls(): array {
		return array(
			'form_switcher_layout_heading' => array(
				'label' => __( 'Layout', 'easy-digital-downloads' ),
				'type'  => 'heading',
			),
			// Default (empty value) leaves the stylesheet's top-corner positioning in place. "Above the
			// fields" takes the button out of absolute position so it sits in the flow — the layout to
			// pick when the section title is hidden.
			'form_switcher_layout'         => array(
				'label'                => __( 'Button Placement', 'easy-digital-downloads' ),
				'type'                 => 'select',
				'default'              => '',
				'options'              => array(
					''        => __( 'Top corner', 'easy-digital-downloads' ),
					'stacked' => __( 'Above the fields', 'easy-digital-downloads' ),
				),
				'selectors_dictionary' => array(
					''        => '',
					'stacked' => 'position: static; margin-bottom: 1rem;',
				),
				'selectors'            => array(
					self::WRAP => '{{VALUE}}',
				),
				'description'          => __( 'The top corner placement expects a section title beside it. Choose "Above the fields" when the Personal Info title is hidden.', 'easy-digital-downloads' ),
			),
			'form_switcher_align'          => array(
				'label'                => __( 'Alignment', 'easy-digital-downloads' ),
				'type'                 => 'select',
				'default'              => '',
				'options'              => array(
					''       => __( 'Default', 'easy-digital-downloads' ),
					'start'  => __( 'Left', 'easy-digital-downloads' ),
					'center' => __( 'Center', 'easy-digital-downloads' ),
					'end'    => __( 'Right', 'easy-digital-downloads' ),
				),
				'selectors_dictionary' => array(
					''       => '',
					'start'  => 'justify-content: flex-start;',
					'center' => 'justify-content: center;',
					'end'    => 'justify-content: flex-end;',
				),
				'selectors'            => array(
					self::WRAP => '{{VALUE}}',
				),
			),
			'form_switcher_gap'            => self::create_slider_control(
				__( 'Gap', 'easy-digital-downloads' ),
				self::WRAP,
				'gap',
				array( 'size_units' => array( 'px', 'em', 'rem' ) )
			),
			'form_switcher_margin'         => self::create_dimensions_control(
				__( 'Margin', 'easy-digital-downloads' ),
				self::WRAP,
				'margin'
			),
		);
	}

	/**
	 * Controls for the switch buttons themselves.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_button_controls(): array {
		return array(
			'form_switcher_button_heading' => array(
				'label'     => __( 'Buttons', 'easy-digital-downloads' ),
				'type'      => 'heading',
				'separator' => 'before',
			),
			'form_switcher_typography'     => self::create_typography_group(
				__( 'Typography', 'easy-digital-downloads' ),
				self::BUTTON
			),
			'form_switcher_color'          => self::create_color_control(
				__( 'Text Color', 'easy-digital-downloads' ),
				self::BUTTON
			),
			'form_switcher_background'     => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				self::BUTTON,
				'background-color'
			),
			'form_switcher_border'         => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				self::BUTTON
			),
			'form_switcher_radius'         => self::create_dimensions_control(
				__( 'Border Radius', 'easy-digital-downloads' ),
				self::BUTTON,
				'border-radius',
				array( 'size_units' => array( 'px', '%' ) )
			),
			'form_switcher_padding'        => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				self::BUTTON,
				'padding'
			),
			'form_switcher_hover_color'    => self::create_color_control(
				__( 'Text Color (Hover)', 'easy-digital-downloads' ),
				self::BUTTON . ':hover'
			),
			'form_switcher_hover_bg'       => self::create_color_control(
				__( 'Background Color (Hover)', 'easy-digital-downloads' ),
				self::BUTTON . ':hover',
				'background-color'
			),
		);
	}
}
