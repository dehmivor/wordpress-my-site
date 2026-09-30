<?php
/**
 * EDD Elementor editor assets subscriber.
 *
 * Enqueues the editor-only script that registers the EDD Checkout container
 * element type on the JS side (delegating to the native container model/view)
 * and seeds the four checkout section widgets when a new element is added.
 *
 * @package     EDD\Elementor\Subscribers
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Elementor\Subscribers;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Elementor\Utils\Page;
use EDD\EventManagement\SubscriberInterface;

/**
 * Class EditorAssets
 *
 * @since 3.7.0
 */
class EditorAssets implements SubscriberInterface {

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'elementor/editor/before_enqueue_scripts' => 'enqueue_editor_scripts',
		);
	}

	/**
	 * Enqueue the EDD Checkout box editor script.
	 *
	 * Registers the EDD Checkout container JS element type and the section-widget
	 * seeding hook. The built bundle is emitted by webpack from
	 * assets/src/js/elementor/checkout-box.entry.js to the path enqueued here.
	 *
	 * @since 3.7.0
	 *
	 * @return void
	 */
	public function enqueue_editor_scripts() {
		// The composable checkout requires Elementor containers. When the
		// Container experiment is off, this script has no element type to
		// register and would error, so it must not enqueue.
		if ( ! Page::is_container_active() ) {
			return;
		}

		wp_enqueue_script(
			'edd-elementor-checkout-box',
			edd_get_assets_url( 'js/elementor' ) . 'checkout-box.js',
			array( 'elementor-editor', 'wp-i18n' ),
			edd_admin_get_script_version(),
			true
		);

		// The editor script localizes the missing-section overlay and delete-lock strings.
		wp_set_script_translations( 'edd-elementor-checkout-box', 'easy-digital-downloads' );
	}
}
