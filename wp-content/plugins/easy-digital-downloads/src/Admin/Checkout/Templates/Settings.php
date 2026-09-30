<?php
/**
 * Checkout Templates Settings
 *
 * Handles the integration of the "Browse Templates" button into the
 * EDD settings page and renders the React mount container.
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

/**
 * Settings class
 *
 * Adds the "Browse Templates" button to the Settings > General > Pages section
 * and renders the React mount container for the template browser.
 *
 * @since 3.7.0
 */
class Settings implements SubscriberInterface {

	/**
	 * Returns an array of events that this subscriber wants to listen to.
	 *
	 * @since 3.7.0
	 * @return array Array of event subscriptions.
	 */
	public static function get_subscribed_events() {
		return array(
			'edd_after_setting_output' => array( 'add_templates_button', 10, 2 ),
		);
	}

	/**
	 * Add "Browse Templates" React mount point after the checkout page setting.
	 *
	 * The React application will mount here and render a button that opens
	 * the template browser modal using WordPress Modal component.
	 *
	 * @since 3.7.0
	 * @param string $html The setting HTML output.
	 * @param array  $args Setting arguments including 'id'.
	 * @return string Modified HTML with the React mount point added.
	 */
	public function add_templates_button( $html, $args ) {
		// Only add to the purchase_page setting.
		// Note: The select callback modifies $args['id'] to 'edd_settings[purchase_page]'.
		if ( empty( $args['id'] ) || 'edd_settings[purchase_page]' !== $args['id'] ) {
			return $html;
		}

		// Only show on the settings page (General tab).
		if ( ! $this->is_settings_page() ) {
			return $html;
		}

		// React mount point - the React app renders the button and modal.
		$mount_point = '<div id="edd-checkout-templates-root" class="edd-checkout-templates-mount"></div>';

		return $html . $mount_point;
	}

	/**
	 * Check if the current screen is the EDD settings page.
	 *
	 * @since 3.7.0
	 * @return bool
	 */
	private function is_settings_page() {
		return edd_is_admin_page( 'settings' );
	}
}
