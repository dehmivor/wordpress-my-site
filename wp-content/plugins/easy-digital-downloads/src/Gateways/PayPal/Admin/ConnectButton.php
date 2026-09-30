<?php
/**
 * PayPal Connect Button
 *
 * Renders the branded "Connect with PayPal" button used to start the v3
 * onboarding flow. Centralizing the markup here (instead of echoing it inline
 * in the settings field) lets other admin screens — e.g. a future setup
 * checklist — print the same button without duplicating it.
 *
 * @package     EDD\Gateways\PayPal\Admin
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Gateways\PayPal\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Builds the branded PayPal Connect button markup.
 *
 * @since 3.7.0
 */
class ConnectButton {

	/**
	 * Returns the branded "Connect with PayPal" button markup.
	 *
	 * The markup is returned as a single line so it survives `wpautop()`.
	 * The v3 connect JS binds to the `edd-paypal-commerce-v3-connect` id and
	 * reads the `data-nonce` attribute; both are always rendered.
	 *
	 * @since 3.7.0
	 *
	 * @param array $args {
	 *     Optional. Arguments to customize the button.
	 *
	 *     @type string $text    Button label. Defaults to "Connect with PayPal in {mode} mode".
	 *     @type array  $classes Additional CSS classes to add to the button.
	 *     @type array  $data    Additional `data-*` attributes, as `key => value` pairs
	 *                           (without the `data-` prefix).
	 * }
	 * @return string The button markup, or an empty string if the current user
	 *                cannot manage shop settings.
	 */
	public static function get( array $args = array() ): string {
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return '';
		}

		$mode = edd_is_test_mode() ? __( 'sandbox', 'easy-digital-downloads' ) : __( 'live', 'easy-digital-downloads' );

		$args = wp_parse_args(
			$args,
			array(
				/* translators: %s: the store mode, either `sandbox` or `live` */
				'text'    => sprintf( __( 'Connect with PayPal in %s mode', 'easy-digital-downloads' ), $mode ),
				'classes' => array(),
				'data'    => array(),
			)
		);

		$classes = array_merge( array( 'edd-paypal-connect' ), (array) $args['classes'] );

		$data = array_merge(
			array( 'nonce' => wp_create_nonce( 'edd_paypal_v3_onboarding' ) ),
			(array) $args['data']
		);

		$attributes = '';
		foreach ( $data as $key => $value ) {
			$attributes .= sprintf( ' data-%s="%s"', esc_attr( sanitize_key( $key ) ), esc_attr( $value ) );
		}

		return sprintf(
			'<button type="button" id="edd-paypal-commerce-v3-connect" class="%1$s"%2$s><img src="%3$s" alt=""><span>%4$s</span></button>',
			esc_attr( implode( ' ', array_map( 'sanitize_html_class', $classes ) ) ),
			$attributes,
			esc_url( EDD_PLUGIN_URL . 'assets/images/paypal-logo.svg' ),
			esc_html( $args['text'] )
		);
	}
}
