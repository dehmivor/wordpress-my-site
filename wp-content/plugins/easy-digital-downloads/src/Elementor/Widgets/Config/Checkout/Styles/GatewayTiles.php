<?php
/**
 * Gateway Selector Style Controls Configuration for the Checkout Widget.
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
 * Gateway selector style controls.
 *
 * The gateway selector only renders when a store has two or more gateways enabled, and it had no
 * controls, so every designed template restyled the tiles in custom CSS. These cover the wrapper
 * layout, the tile box model and the selected state.
 *
 * @since 3.7.0
 */
class GatewayTiles extends Base {

	/**
	 * The row of gateway options.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const WRAP = '#edd-payment-mode-wrap';

	/**
	 * A single gateway option, which EDD renders as a label.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const TILE = '#edd-payment-mode-wrap .edd-gateway-option';

	/**
	 * The selected gateway option.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const SELECTED = '#edd-payment-mode-wrap .edd-gateway-option.edd-gateway-option-selected';

	/**
	 * Get the gateway selector style controls configuration.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_controls(): array {
		return array(
			'gateway_tiles_style' => array(
				'label'    => __( 'Gateway Selector', 'easy-digital-downloads' ),
				'tab'      => 'style',
				'controls' => array_merge(
					self::get_wrap_controls(),
					self::get_tile_controls(),
					self::get_selected_controls()
				),
			),
		);
	}

	/**
	 * Controls for the row that holds the gateway options.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_wrap_controls(): array {
		return array(
			'gateway_wrap_heading'    => array(
				'label' => __( 'Layout', 'easy-digital-downloads' ),
				'type'  => 'heading',
			),
			'gateway_wrap_gap'        => self::create_slider_control(
				__( 'Gap', 'easy-digital-downloads' ),
				self::WRAP,
				'gap',
				array( 'size_units' => array( 'px', 'em', 'rem' ) )
			),
			'gateway_tile_stretch'    => array(
				'label'                => __( 'Tile Width', 'easy-digital-downloads' ),
				'type'                 => 'select',
				'default'              => 'stretch',
				'options'              => array(
					'stretch' => __( 'Fill the row', 'easy-digital-downloads' ),
					'hug'     => __( 'Fit the label', 'easy-digital-downloads' ),
				),
				'selectors_dictionary' => array(
					'stretch' => '1',
					'hug'     => '0',
				),
				'selectors'            => array(
					self::TILE => 'flex-grow: {{VALUE}};',
				),
				'description'          => __( 'Gateway count varies by store, so filling the row keeps the tiles even.', 'easy-digital-downloads' ),
			),
			'gateway_wrap_background' => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				self::WRAP,
				'background-color'
			),
			'gateway_wrap_border'     => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				self::WRAP
			),
			'gateway_wrap_radius'     => self::create_dimensions_control(
				__( 'Border Radius', 'easy-digital-downloads' ),
				self::WRAP,
				'border-radius',
				array( 'size_units' => array( 'px', '%' ) )
			),
			'gateway_wrap_padding'    => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				self::WRAP,
				'padding'
			),
			'gateway_wrap_margin'     => self::create_dimensions_control(
				__( 'Margin', 'easy-digital-downloads' ),
				self::WRAP,
				'margin'
			),
		);
	}

	/**
	 * Controls for a gateway option tile.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_tile_controls(): array {
		return array(
			'gateway_tile_heading'     => array(
				'label'     => __( 'Tiles', 'easy-digital-downloads' ),
				'type'      => 'heading',
				'separator' => 'before',
			),
			// Stripe's Payment Element samples the tile's background, border and radius and applies
			// them to its own payment-method tabs. Off by default, so no existing checkout changes.
			'gateway_tile_skip_stripe' => array(
				'label'        => __( "Leave Stripe's Methods Unstyled", 'easy-digital-downloads' ),
				'type'         => 'switcher',
				'label_on'     => __( 'Yes', 'easy-digital-downloads' ),
				'label_off'    => __( 'No', 'easy-digital-downloads' ),
				'return_value' => 'off',
				'default'      => '',
				'selectors'    => array(
					self::TILE => '--edd-stripe-match-tiles: {{VALUE}};',
				),
				'description'  => __( 'Stripe styles its own payment method tabs to match these tiles. Turn this on to leave them alone.', 'easy-digital-downloads' ),
			),
			'gateway_tile_typography'  => self::create_typography_group(
				__( 'Typography', 'easy-digital-downloads' ),
				self::TILE
			),
			'gateway_tile_color'       => self::create_color_control(
				__( 'Text Color', 'easy-digital-downloads' ),
				self::TILE
			),
			'gateway_tile_background'  => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				self::TILE,
				'background-color'
			),
			'gateway_tile_border'      => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				self::TILE
			),
			'gateway_tile_radius'      => self::create_dimensions_control(
				__( 'Border Radius', 'easy-digital-downloads' ),
				self::TILE,
				'border-radius',
				array( 'size_units' => array( 'px', '%' ) )
			),
			'gateway_tile_padding'     => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				self::TILE,
				'padding'
			),
			'gateway_tile_height'      => self::create_slider_control(
				__( 'Minimum Height', 'easy-digital-downloads' ),
				self::TILE,
				'min-height',
				array( 'size_units' => array( 'px', 'em', 'rem' ) )
			),
			'gateway_tile_icon_height' => self::create_slider_control(
				__( 'Icon Height', 'easy-digital-downloads' ),
				self::TILE . ' .edd-payment-icons .payment-icon',
				'max-height',
				array( 'size_units' => array( 'px', 'em', 'rem' ) )
			),
		);
	}

	/**
	 * Controls for the selected gateway option.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_selected_controls(): array {
		return array(
			'gateway_selected_heading'    => array(
				'label'     => __( 'Selected Tile', 'easy-digital-downloads' ),
				'type'      => 'heading',
				'separator' => 'before',
			),
			'gateway_selected_color'      => self::create_color_control(
				__( 'Text Color', 'easy-digital-downloads' ),
				self::SELECTED
			),
			'gateway_selected_background' => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				self::SELECTED,
				'background-color'
			),
			'gateway_selected_border'     => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				self::SELECTED
			),
			'gateway_hover_background'    => self::create_color_control(
				__( 'Background Color (Hover)', 'easy-digital-downloads' ),
				self::TILE . ':hover',
				'background-color'
			),
		);
	}
}
