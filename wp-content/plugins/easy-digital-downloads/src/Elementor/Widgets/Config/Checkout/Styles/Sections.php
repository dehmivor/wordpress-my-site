<?php
/**
 * Sections Style Controls Configuration for Checkout Widget.
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
 * Sections Style Controls Configuration for Checkout Widget.
 *
 * @since 3.6.0
 */
class Sections extends Base {

	/**
	 * The purchase-form ancestor every section selector is written against.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const FORM_ANCESTOR = 'form#edd_purchase_form ';

	/**
	 * The personal-info slot's legend, named per form so the selector keeps an id.
	 *
	 * The slot's box is styled on the wrapper so one card covers whichever form renders, but a
	 * wrapper class alone is outranked by the block stylesheet's
	 * `#edd_purchase_form .edd-blocks-form legend { margin: 0 }`.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const PERSONAL_INFO_LEGENDS = 'form#edd_purchase_form .edd-checkout-block__personal-info #edd_checkout_user_info legend, form#edd_purchase_form .edd-checkout-block__personal-info #edd_register_fields legend, form#edd_purchase_form .edd-checkout-block__personal-info #edd_login_fields legend';

	/**
	 * Get selector mappings that strip the purchase-form ancestor from every section selector.
	 *
	 * A widget whose {{WRAPPER}} sits INSIDE the purchase form needs this: its selectors would
	 * otherwise compile to `{{WRAPPER}} form#edd_purchase_form …` and match nothing, silently. Derived
	 * from the section configs rather than hand-listed, so adding a section cannot leave its controls dead.
	 *
	 * @since 3.7.0
	 * @return array Map of full selector to form-relative selector.
	 */
	public static function get_form_ancestor_mappings(): array {
		$mappings = array();
		foreach ( self::get_section_configs() as $config ) {
			if ( 0 === strpos( $config['selector'], self::FORM_ANCESTOR ) ) {
				$mappings[ $config['selector'] ] = substr( $config['selector'], strlen( self::FORM_ANCESTOR ) );
			}
		}

		return $mappings;
	}

	/**
	 * Get sections style controls configuration.
	 *
	 * @since 3.6.0
	 *
	 * @param string $context The checkout context: 'composable' (default) or 'monolith'.
	 * @return array
	 */
	public static function get_controls( string $context = 'composable' ): array {
		$sections = self::get_section_configs( $context );
		$controls = array();

		foreach ( $sections as $section_name => $config ) {
			$controls[ "{$section_name}_section_style" ] = array(
				'label'    => $config['title'],
				'controls' => self::get_section_controls( $section_name, $config['selector'], $config['title_selector'] ?? '' ),
			);
		}

		return $controls;
	}

	/**
	 * Get section configurations.
	 *
	 * @since 3.6.0
	 *
	 * @param string $context The checkout context: 'composable' (default) or 'monolith'.
	 * @return array Section configurations.
	 */
	protected static function get_section_configs( string $context = 'composable' ): array {
		// The composable box targets the personal-info WRAPPER, not the guest fieldset: EDD swaps the
		// guest, log in and register forms into this one slot via AJAX, so only the wrapper is present
		// for all three. The monolith shipped in 3.6.0 targeting the guest fieldset, so it keeps that
		// selector for backward compatibility; this branch is transitional (see get_controls' $context).
		$personal_information = 'monolith' === $context
			? array(
				'title'    => __( 'Personal Information', 'easy-digital-downloads' ),
				'selector' => 'form#edd_purchase_form #edd_checkout_user_info',
			)
			: array(
				'title'          => __( 'Personal Information', 'easy-digital-downloads' ),
				'selector'       => 'form#edd_purchase_form .edd-checkout-block__personal-info',
				'title_selector' => self::PERSONAL_INFO_LEGENDS,
			);

		return array(
			'personal_information' => $personal_information,
			'billing_details'      => array(
				'title'    => __( 'Billing Details', 'easy-digital-downloads' ),
				'selector' => 'form#edd_purchase_form #edd_cc_address',
			),
			// Both render only for a guest, and only when Customer Registration is not set to
			// automatic, so a template built against one registration setting never sees them.
			'login_fields'         => array(
				'title'    => __( 'Log In', 'easy-digital-downloads' ),
				'selector' => 'form#edd_purchase_form #edd_login_fields',
			),
			'register_fields'      => array(
				'title'    => __( 'Register', 'easy-digital-downloads' ),
				'selector' => 'form#edd_purchase_form #edd_register_fields',
			),
			'payment_method'       => array(
				'title'    => __( 'Payment Method', 'easy-digital-downloads' ),
				'selector' => 'form#edd_purchase_form #edd_payment_mode_select',
			),
			'cc_details'           => array(
				'title'    => __( 'Card Details', 'easy-digital-downloads' ),
				'selector' => 'form#edd_purchase_form #edd_cc_fields',
			),
		);
	}

	/**
	 * Get controls for a specific section.
	 *
	 * @since 3.6.0
	 * @param string $section_name     Section name.
	 * @param string $section_selector Section selector.
	 * @param string $title_selector   Optional. Selector for the section's title, when the section
	 *                                 selector alone would not reach it or would be outranked.
	 * @return array Section controls.
	 */
	protected static function get_section_controls( string $section_name, string $section_selector, string $title_selector = '' ): array {
		$legend = '' !== $title_selector ? $title_selector : "{$section_selector} legend";
		return array(
			// Section controls.
			"{$section_name}_section_heading"          => array(
				'label' => __( 'Section', 'easy-digital-downloads' ),
				'type'  => 'heading',
			),
			"{$section_name}_section_background_color" => self::create_color_control(
				__( 'Background Color', 'easy-digital-downloads' ),
				$section_selector,
				'background-color'
			),
			"{$section_name}_text_color"               => self::create_color_control(
				__( 'Text Color', 'easy-digital-downloads' ),
				$section_selector
			),
			"{$section_name}_border"                   => self::create_border_group(
				__( 'Border', 'easy-digital-downloads' ),
				$section_selector
			),
			"{$section_name}_border_radius"            => self::create_dimensions_control(
				__( 'Border Radius', 'easy-digital-downloads' ),
				$section_selector,
				'border-radius',
				array( 'size_units' => array( 'px', '%' ) )
			),
			"{$section_name}_box_shadow"               => self::create_box_shadow_group(
				__( 'Box Shadow', 'easy-digital-downloads' ),
				$section_selector
			),
			"{$section_name}_padding"                  => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				$section_selector,
				'padding'
			),
			"{$section_name}_margin"                   => self::create_dimensions_control(
				__( 'Margin', 'easy-digital-downloads' ),
				$section_selector,
				'margin'
			),

			// Title controls.
			"{$section_name}_title_heading"            => array(
				'label' => __( 'Title', 'easy-digital-downloads' ),
				'type'  => 'heading',
			),
			"{$section_name}_title_typography"         => self::create_typography_group(
				__( 'Typography', 'easy-digital-downloads' ),
				$legend
			),
			"{$section_name}_title_color"              => self::create_color_control(
				__( 'Color', 'easy-digital-downloads' ),
				$legend
			),
			"{$section_name}_title_padding"            => self::create_dimensions_control(
				__( 'Padding', 'easy-digital-downloads' ),
				$legend,
				'padding'
			),
			"{$section_name}_title_margin"             => self::create_dimensions_control(
				__( 'Margin', 'easy-digital-downloads' ),
				$legend,
				'margin'
			),
		);
	}
}
