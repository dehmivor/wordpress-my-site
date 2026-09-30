<?php
/**
 * Titles Controls Configuration for Checkout Widget.
 *
 * @package     EDD\Elementor\Widgets\Config\Checkout
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Elementor\Widgets\Config\Checkout;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Widgets\Config\Base;

/**
 * Titles Controls Configuration for Checkout Widget.
 *
 * @since 3.6.0
 */
class Titles extends Base {

	/**
	 * Get titles controls configuration.
	 *
	 * @since 3.6.0
	 * @return array
	 */
	public static function get_controls(): array {
		return array(
			'section_titles' => array(
				'label'    => __( 'Section Titles', 'easy-digital-downloads' ),
				'tab'      => 'content', // Override default 'style' tab.
				'controls' => array(
					'show_personal_info_title'   => self::create_title_switcher(
						__( 'Personal Info', 'easy-digital-downloads' ),
						'#edd_checkout_user_info'
					),
					// Login and register render into the personal-info slot, one at a time per the store's
					// Customer Registration setting. Each gets its own toggle so hiding one legend never
					// hides another.
					'show_login_title'           => self::create_title_switcher(
						__( 'Log In', 'easy-digital-downloads' ),
						'#edd_login_fields'
					),
					'show_register_title'        => self::create_title_switcher(
						__( 'Register', 'easy-digital-downloads' ),
						'#edd_register_fields'
					),
					'show_billing_details_title' => self::create_title_switcher(
						__( 'Billing Details', 'easy-digital-downloads' ),
						'#edd_cc_address'
					),
					'show_payment_method_title'  => self::create_title_switcher(
						__( 'Payment Method', 'easy-digital-downloads' ),
						'#edd_payment_mode_select'
					),
					'show_cc_details_title'      => self::create_title_switcher(
						__( 'Card Info', 'easy-digital-downloads' ),
						'#edd_cc_fields'
					),
				),
			),
		);
	}

	/**
	 * Build a section-title visibility switcher.
	 *
	 * Toggles the legend's display only. The fieldset's own display is left alone: a flex column here
	 * overrode the block grid. Templates that need the legend lifted out of the fieldset border slot
	 * apply that float themselves as custom CSS.
	 *
	 * @since 3.7.0
	 * @param string $label    Control label.
	 * @param string $selector Fieldset selector; the legend is styled beneath it.
	 * @return array
	 */
	private static function create_title_switcher( string $label, string $selector ): array {
		return array(
			'label'                => $label,
			'type'                 => 'switcher',
			'label_on'             => __( 'Show', 'easy-digital-downloads' ),
			'label_off'            => __( 'Hide', 'easy-digital-downloads' ),
			'return_value'         => 'yes',
			'default'              => 'yes',
			'selectors_dictionary' => array(
				'yes' => 'block',
				''    => 'none',
			),
			'selectors'            => array(
				$selector . ' legend' => 'display: {{VALUE}};',
			),
		);
	}
}
