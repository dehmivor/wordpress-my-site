<?php
/**
 * Template Browser Trait
 *
 * Shared functionality for template browser components.
 *
 * @package     EDD\Checkout\Templates\Traits
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Checkout\Templates\Traits;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Checkout\Templates\Config\Constants;
use EDD\Checkout\Templates\Config\EditorRegistry;
use EDD\Checkout\Templates\License;

/**
 * Trait TemplateBrowserTrait
 *
 * Provides shared methods for Browser and ElementorEditor classes.
 *
 * @since 3.7.0
 */
trait TemplateBrowserTrait {

	/**
	 * Get available page builders/editors on this site.
	 *
	 * @since 3.7.0
	 * @return array Array of available editors with their status.
	 */
	private function get_available_editors(): array {
		$editors = EditorRegistry::descriptors();

		// Every editor is advertised as a label/availability entry: an absent
		// or below-floor editor carries available => false plus its reason so
		// the card can explain itself. The filter option SET is sourced
		// separately and must exclude available => false editors.
		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			$editors[ Constants::EDITOR_ELEMENTOR ]['version'] = ELEMENTOR_VERSION;
		}

		// Runtime overlay: Elementor can pass the version gate yet still have the
		// Flexbox Container experiment turned off, which the importer needs. Reflect
		// that at browse time so the card disables Import with a reason before the
		// click. The registry stays runtime-free, so the Elementor call lives here
		// and is guarded — accessing the static on an unloaded \Elementor\Plugin
		// would fatal.
		if (
			! empty( $editors[ Constants::EDITOR_ELEMENTOR ]['available'] )
			&& class_exists( '\\Elementor\\Plugin' )
			&& ! \EDD\Elementor\Utils\Page::is_container_active()
		) {
			$editors[ Constants::EDITOR_ELEMENTOR ]['available']        = false;
			$editors[ Constants::EDITOR_ELEMENTOR ]['reason']           = 'container';
			$editors[ Constants::EDITOR_ELEMENTOR ]['unavailable_text'] = EditorRegistry::container_unavailable_text();
		}

		return $editors;
	}

	/**
	 * Get information about the currently installed template.
	 *
	 * @since 3.7.0
	 * @param int|null $checkout_page Optional checkout page ID. Fetches from settings if not provided.
	 * @return array Template information.
	 */
	private function get_current_template_info( ?int $checkout_page = null ): array {
		if ( null === $checkout_page ) {
			$checkout_page = edd_get_option( 'purchase_page', 0 );
		}

		if ( ! $checkout_page ) {
			return array(
				'name'       => __( 'Default', 'easy-digital-downloads' ),
				'lastImport' => null,
				'templateId' => null,
				'version'    => null,
			);
		}

		$template_id = get_post_meta( $checkout_page, Constants::META_ID, true );
		$version     = get_post_meta( $checkout_page, Constants::META_VERSION, true );
		$import_date = get_post_meta( $checkout_page, Constants::META_IMPORTED, true );

		if ( ! $template_id ) {
			return array(
				'name'       => __( 'Custom', 'easy-digital-downloads' ),
				'lastImport' => null,
				'templateId' => null,
				'version'    => null,
			);
		}

		$timestamp = $import_date ? strtotime( $import_date ) : false;

		return array(
			'name'       => get_post_meta( $checkout_page, Constants::META_NAME, true ),
			'lastImport' => ( $import_date && false !== $timestamp ) ? date_i18n( get_option( 'date_format' ), $timestamp ) : null,
			'templateId' => $template_id,
			'version'    => $version,
		);
	}

	/**
	 * Enqueue browser scripts.
	 *
	 * @since 3.7.0
	 * @param array $extra_dependencies Additional script dependencies.
	 * @return void
	 */
	protected function enqueue_browser_scripts( array $extra_dependencies = array() ): void {
		$dependencies = array( 'react-jsx-runtime', 'wp-api-fetch', 'wp-components', 'wp-data', 'wp-data-controls', 'wp-element', 'wp-i18n' );

		if ( ! empty( $extra_dependencies ) ) {
			$dependencies = array_merge( $dependencies, $extra_dependencies );
		}

		wp_enqueue_script(
			'edd-checkout-templates',
			edd_get_assets_url( 'js/admin' ) . 'checkout-templates.js',
			$dependencies,
			edd_admin_get_script_version(),
			true
		);

		wp_set_script_translations( 'edd-checkout-templates', 'easy-digital-downloads' );
	}

	/**
	 * Enqueue browser styles.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	protected function enqueue_browser_styles(): void {
		wp_enqueue_style(
			'edd-checkout-templates',
			edd_get_assets_url( 'css/admin' ) . 'checkout-templates.min.css',
			array( 'wp-components' ),
			edd_admin_get_script_version()
		);
		wp_style_add_data( 'edd-checkout-templates', 'rtl', 'replace' );
		wp_style_add_data( 'edd-checkout-templates', 'suffix', '.min' );
	}

	/**
	 * Get base script data for the template browser.
	 *
	 * @since 3.7.0
	 * @param string   $utm_content      UTM content parameter for upgrade URL.
	 * @param int|null $checkout_page_id Optional checkout page ID.
	 * @return array Script localization data.
	 */
	protected function get_base_script_data( string $utm_content = 'upgrade-prompt', ?int $checkout_page_id = null ): array {
		if ( null === $checkout_page_id ) {
			$checkout_page_id = edd_get_option( 'purchase_page', 0 );
		}

		$data = array(
			'restUrl'         => rest_url( 'edd/v3/checkout-templates' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'canImport'       => License::can_import(),
			'licenseStatus'   => License::get_status(),
			'isLite'          => ! edd_is_pro(),
			'editors'         => $this->get_available_editors(),
			'checkoutPageId'  => $checkout_page_id,
			'checkoutPageUrl' => edd_get_checkout_uri(),
			'currentTemplate' => $this->get_current_template_info( $checkout_page_id ),
			'upgradeUrl'      => edd_link_helper(
				Constants::UPGRADE_URL,
				array(
					'utm_medium'  => 'checkout-templates',
					'utm_content' => $utm_content,
				)
			),
			'accountUrl'      => edd_link_helper(
				Constants::ACCOUNT_URL,
				array(
					'utm_medium'  => 'checkout-templates',
					'utm_content' => $utm_content,
				)
			),
			'licensesUrl'     => edd_get_admin_url( array( 'page' => 'edd-settings' ) ),
		);

		return $data;
	}
}
