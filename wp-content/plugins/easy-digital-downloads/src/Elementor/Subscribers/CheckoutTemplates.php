<?php
/**
 * Checkout Templates - Elementor Editor Integration
 *
 * Adds a "Browse Templates" button to Elementor's editor panel
 * when editing the checkout page.
 *
 * @package     EDD\Elementor\Subscribers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Subscribers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Checkout\Templates\Traits\TemplateBrowserTrait;

/**
 * CheckoutTemplates class
 *
 * Integrates the template browser with Elementor's editor interface.
 * Provides a "Browse Templates" button in the Elementor panel footer
 * when the user is editing the EDD checkout page.
 *
 * @since 3.7.0
 */
class CheckoutTemplates implements SubscriberInterface {

	use TemplateBrowserTrait;

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.7.0
	 * @return array Array of event subscriptions.
	 */
	public static function get_subscribed_events() {
		return array(
			'elementor/editor/after_enqueue_scripts' => 'enqueue_assets',
		);
	}

	/**
	 * Check if we're editing the checkout page.
	 *
	 * @since 3.7.0
	 * @return bool True if editing the checkout page.
	 */
	private function is_checkout_page(): bool {
		$checkout_page_id = edd_get_option( 'purchase_page', 0 );
		if ( ! $checkout_page_id ) {
			return false;
		}

		$current_post_id = get_the_ID();
		if ( ! $current_post_id ) {
			// Try to get from query var.
			$current_post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return (int) $current_post_id === (int) $checkout_page_id;
	}

	/**
	 * Enqueue assets for the Elementor editor.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_checkout_page() ) {
			return;
		}

		// Enqueue the React template browser with elementor-editor as an extra dependency.
		$this->enqueue_browser_scripts( array( 'elementor-editor' ) );

		wp_localize_script(
			'edd-checkout-templates',
			'eddCheckoutTemplates',
			$this->get_script_data()
		);

		$this->enqueue_browser_styles();

		// Enqueue the compiled Elementor panel button script and its styles.
		$this->enqueue_elementor_button_script();
		$this->enqueue_elementor_button_styles();
	}

	/**
	 * Enqueue the Elementor panel "Browse Templates" button script.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	private function enqueue_elementor_button_script(): void {
		wp_enqueue_script(
			'edd-checkout-templates-elementor',
			edd_get_assets_url( 'js/admin' ) . 'checkout-templates-elementor.js',
			array( 'elementor-editor' ),
			edd_admin_get_script_version(),
			true
		);

		// Pass translated strings as a JS object so the built file has no PHP dependency.
		wp_add_inline_script(
			'edd-checkout-templates-elementor',
			'globalThis.eddElementorEditorI18n = ' . wp_json_encode(
				array(
					'browse' => __( 'Browse Checkout Templates', 'easy-digital-downloads' ),
					'button' => __( 'EDD Checkout Templates', 'easy-digital-downloads' ),
				)
			) . ';',
			'before'
		);

		// Render the React mount point in the Elementor editor footer.
		add_action(
			'elementor/editor/footer',
			static function (): void {
				echo '<div id="edd-checkout-templates-root"></div>';
			}
		);
	}

	/**
	 * Enqueue the Elementor panel button stylesheet.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	private function enqueue_elementor_button_styles(): void {
		wp_enqueue_style(
			'edd-checkout-templates-elementor',
			edd_get_assets_url( 'css/admin' ) . 'checkout-templates-elementor.min.css',
			array(),
			EDD_VERSION
		);
		wp_style_add_data( 'edd-checkout-templates-elementor', 'rtl', 'replace' );
		wp_style_add_data( 'edd-checkout-templates-elementor', 'suffix', '.min' );
	}

	/**
	 * Get data to pass to the JavaScript.
	 *
	 * @since 3.7.0
	 * @return array Script localization data.
	 */
	private function get_script_data(): array {
		$data = $this->get_base_script_data( 'upgrade-prompt-elementor' );

		// Add Elementor-specific flag.
		$data['isElementor'] = true;

		return $data;
	}
}
