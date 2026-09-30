<?php
/**
 * Per-widget control configuration for the composed (inner-block) checkout widgets.
 *
 * Re-homes the existing Config\Checkout\* control groups across the four
 * CheckoutInner section widgets, slicing multi-target groups by key. No control
 * definition is duplicated here; every method returns a slice of the existing
 * source group output.
 *
 * @package     EDD\Elementor\Widgets\Config\CheckoutInner
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Widgets\Config\CheckoutInner;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Widgets\Config\Checkout\General;
use EDD\Elementor\Widgets\Config\Checkout\Cart;
use EDD\Elementor\Widgets\Config\Checkout\Titles;
use EDD\Elementor\Widgets\Config\Checkout\Styles\Buttons;
use EDD\Elementor\Widgets\Config\Checkout\Styles\Cart as StylesCart;
use EDD\Elementor\Widgets\Config\Checkout\Styles\Sections;
use EDD\Elementor\Widgets\Config\Checkout\Styles\DiscountForm as StylesDiscountForm;
use EDD\Elementor\Widgets\Config\Checkout\Styles\FormSwitcher;
use EDD\Elementor\Widgets\Config\Checkout\Styles\GatewayTiles;

/**
 * Per-widget control configuration for the composed checkout widgets.
 *
 * @since 3.7.0
 */
class Controls {

	/**
	 * Get the control configuration for the Cart widget.
	 *
	 * Owns the cart display toggles (section_cart minus the retired
	 * `show_discount_form` control), the sample-cart-item preview control (re-homed
	 * from General), and the cart styling group.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_cart_controls(): array {
		$cart = Cart::get_controls();
		$cart = self::remove_section_controls( $cart, 'section_cart', array( 'show_discount_form' ) );

		// The sample-cart-item preview control lives on the Cart widget because the
		// editor renders each section widget in its own request, so only the Cart
		// widget's render can seed $_GET['cart_item'] for its preview. Re-keyed to a
		// widget-unique section id so it does not collide with section_cart.
		$preview = self::slice_section_controls( General::get_controls(), 'section_general', array( 'preview_cart_item' ), 'section_cart_preview' );

		// The composable box uses the row-divider variant of the item border (between items only,
		// "Row Divider"). Swap it in place of the shipped monolith's full-item "Border" control so
		// the two never diverge on the legacy widget: the monolith keeps cart_items_border untouched.
		$styles   = StylesCart::get_controls();
		$controls = array();
		foreach ( $styles['cart_section_style']['controls'] as $key => $control ) {
			if ( 'cart_items_border' === $key ) {
				$controls['cart_items_row_divider'] = StylesCart::get_row_divider_control();
				continue;
			}
			// The row divider is a bottom border only, so a border radius has no corners to round.
			// Drop it here; the monolith keeps its radius for the full-item border.
			if ( 'cart_items_border_radius' === $key ) {
				continue;
			}
			$controls[ $key ] = $control;
		}
		$styles['cart_section_style']['controls'] = $controls;

		return array_merge(
			$cart,
			$preview,
			$styles
		);
	}

	/**
	 * Get the control configuration for the Personal Info widget.
	 *
	 * Billing controls live here because billing renders inside the personal-info section, and
	 * guest preview because the editor renders each section in its own request, making this
	 * widget's render the only place that filter can apply.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_personal_info_controls(): array {
		// Re-key the split fragments to widget-unique section ids so no section id
		// is registered on more than one widget (the parent keeps section_general;
		// PaymentInfo keeps section_titles).
		$general = self::slice_section_controls( General::get_controls(), 'section_general', array( 'preview_as_guest', 'login_details_alignment' ), 'section_personal_general' );
		$titles  = self::slice_section_controls( Titles::get_controls(), 'section_titles', array( 'show_personal_info_title', 'show_login_title', 'show_register_title', 'show_billing_details_title' ), 'section_personal_title' );
		// The login and register fieldsets render inside this widget too, for a guest on a store
		// whose Customer Registration setting is not automatic.
		$styles = self::slice_sections(
			Sections::get_controls(),
			array(
				'personal_information_section_style',
				'billing_details_section_style',
				'login_fields_section_style',
				'register_fields_section_style',
			)
		);

		$general['section_personal_general']['controls']['name_single_line'] = self::get_single_line_name_control();

		return array_merge(
			$general,
			$titles,
			FormSwitcher::get_controls(),
			$styles
		);
	}

	/**
	 * Get the control that pairs First Name and Last Name on one row.
	 *
	 * Declares no spacing: the fieldset's own `gap` applies to flex too, so the pair keeps the
	 * template's rhythm. Flex, not a grid, whose `auto-fit` track count leaves an empty track when
	 * wide. Only the name groups share a row, so disabling Last Name keeps one full-width field.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	private static function get_single_line_name_control(): array {
		return array(
			'label'       => __( 'Single Line Name', 'easy-digital-downloads' ),
			'type'        => 'switcher',
			'default'     => '',
			'label_on'    => __( 'Yes', 'easy-digital-downloads' ),
			'label_off'   => __( 'No', 'easy-digital-downloads' ),
			// No selectors: the widget adds PersonalInfo::CLASS_NAME_SINGLE_LINE to its wrapper and
			// the checkout block's stylesheet owns the geometry, so both editors share one definition.
			'description' => __( 'Show First Name and Last Name side by side.', 'easy-digital-downloads' ),
		);
	}

	/**
	 * Get the control configuration for the Discount Form widget.
	 *
	 * The widget registered nothing, so templates styled the field and the apply button in custom CSS.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_discount_form_controls(): array {
		return StylesDiscountForm::get_controls();
	}

	/**
	 * Get the control configuration for the Payment Info widget.
	 *
	 * The personal-info and billing-details title toggles are excluded: they belong to the
	 * Personal Info widget, which owns the billing address.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_payment_info_controls(): array {
		$titles = self::remove_section_controls( Titles::get_controls(), 'section_titles', array( 'show_personal_info_title', 'show_login_title', 'show_register_title', 'show_billing_details_title' ) );
		$styles = self::slice_sections(
			Sections::get_controls(),
			array(
				'payment_method_section_style',
				'cc_details_section_style',
			)
		);

		return array_merge(
			$titles,
			Buttons::get_controls(),
			GatewayTiles::get_controls(),
			$styles
		);
	}

	/**
	 * Slice a single section down to only the named controls.
	 *
	 * Preserves the section label and tab, returning a one-section config array
	 * containing only the requested control keys (in source order).
	 *
	 * @since 3.7.0
	 * @param array  $config         The source group config (section_id => section).
	 * @param string $section_id     The section id to slice.
	 * @param array  $control_keys   The control keys to keep.
	 * @param string $new_section_id Optional. Re-key the sliced section under this
	 *                               id so split fragments stay unique per widget.
	 *                               Default '' (keep the source id).
	 * @return array
	 */
	private static function slice_section_controls( array $config, string $section_id, array $control_keys, string $new_section_id = '' ): array {
		if ( empty( $config[ $section_id ]['controls'] ) ) {
			return array();
		}

		$section             = $config[ $section_id ];
		$section['controls'] = self::filter_controls( $section['controls'], $control_keys, true );

		$target_id = '' !== $new_section_id ? $new_section_id : $section_id;

		return array( $target_id => $section );
	}

	/**
	 * Remove the named controls from a section, keeping the rest.
	 *
	 * @since 3.7.0
	 * @param array  $config       The source group config (section_id => section).
	 * @param string $section_id   The section id to filter.
	 * @param array  $control_keys The control keys to remove.
	 * @return array
	 */
	private static function remove_section_controls( array $config, string $section_id, array $control_keys ): array {
		if ( empty( $config[ $section_id ]['controls'] ) ) {
			return $config;
		}

		$config[ $section_id ]['controls'] = self::filter_controls( $config[ $section_id ]['controls'], $control_keys, false );

		return $config;
	}

	/**
	 * Slice a multi-section group down to only the named section ids.
	 *
	 * Used for the Styles\Sections group, which returns four `*_section_style`
	 * entries from one call.
	 *
	 * @since 3.7.0
	 * @param array $config      The source group config (section_id => section).
	 * @param array $section_ids The section ids to keep.
	 * @return array
	 */
	private static function slice_sections( array $config, array $section_ids ): array {
		$sliced = array();
		foreach ( $section_ids as $section_id ) {
			if ( isset( $config[ $section_id ] ) ) {
				$sliced[ $section_id ] = $config[ $section_id ];
			}
		}

		return $sliced;
	}

	/**
	 * Filter a controls array by key, keeping or removing the named keys.
	 *
	 * @since 3.7.0
	 * @param array $controls     The controls array (control_id => config).
	 * @param array $control_keys The control keys to act on.
	 * @param bool  $keep         True to keep only the named keys; false to remove them.
	 * @return array
	 */
	private static function filter_controls( array $controls, array $control_keys, bool $keep ): array {
		$filtered = array();
		foreach ( $controls as $control_id => $control_config ) {
			$in_list = in_array( $control_id, $control_keys, true );
			if ( $keep === $in_list ) {
				$filtered[ $control_id ] = $control_config;
			}
		}

		return $filtered;
	}
}
