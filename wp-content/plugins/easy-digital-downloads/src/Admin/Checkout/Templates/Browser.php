<?php
/**
 * Checkout Templates Browser
 *
 * Handles asset loading for the React-based template browser.
 *
 * @package     EDD\Admin\Checkout\Templates
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Admin\Checkout\Templates;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Checkout\Templates\Traits\TemplateBrowserTrait;

/**
 * Browser class
 *
 * Manages the loading of React application assets for the
 * Checkout Template Browser interface.
 *
 * @since 3.7.0
 */
class Browser implements SubscriberInterface {

	use TemplateBrowserTrait;

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.7.0
	 * @return array Array of event subscriptions.
	 */
	public static function get_subscribed_events() {
		return array(
			'admin_enqueue_scripts' => 'maybe_enqueue_assets',
		);
	}

	/**
	 * Conditionally enqueue assets on the EDD settings page.
	 *
	 * @since 3.7.0
	 * @param string $hook The current admin page hook.
	 * @return void
	 */
	public function maybe_enqueue_assets( $hook ) {
		// Only load on EDD settings page.
		if ( ! edd_is_admin_page( 'settings' ) ) {
			return;
		}

		// Only load on the General tab (where the Pages section lives).
		// When no tab is specified, EDD defaults to 'general'.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'general' !== $tab ) {
			return;
		}

		// Only load on the Pages section, where the checkout page setting lives.
		// When no section is specified, the General tab defaults to 'main'.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'main'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( 'pages' !== $section ) {
			return;
		}

		$this->enqueue_scripts();
		$this->enqueue_styles();
	}

	/**
	 * Enqueue JavaScript files.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	private function enqueue_scripts() {
		$this->enqueue_browser_scripts();

		wp_localize_script(
			'edd-checkout-templates',
			'eddCheckoutTemplates',
			$this->get_base_script_data( 'upgrade-prompt' )
		);
	}

	/**
	 * Enqueue CSS files.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	private function enqueue_styles() {
		$this->enqueue_browser_styles();
	}
}
