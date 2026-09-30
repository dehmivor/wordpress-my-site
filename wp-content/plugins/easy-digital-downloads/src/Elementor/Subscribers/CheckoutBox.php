<?php
/**
 * EDD Checkout Box element registration subscriber.
 *
 * Registers the EDD Checkout container element with the Elementor ELEMENTS
 * manager (not the widgets manager). Registering here keys the element into
 * config.elements so it is a valid document-root child, while the element's
 * include_in_widgets_config flag merges it into the widgets cache the Add
 * Element panel reads.
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
 * Class CheckoutBox
 *
 * @since 3.7.0
 */
class CheckoutBox implements SubscriberInterface {

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.7.0
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'elementor/elements/elements_registered' => 'register_element',
			'elementor/controls/register'            => 'register_controls',
		);
	}

	/**
	 * Register the checkout box's custom Elementor controls.
	 *
	 * Registers the data-less layout-picker control type so the box's
	 * register_controls() can add it to the options panel (the panel-hosted
	 * five-pattern picker + re-add affordance that replaces the native Container
	 * layout controls). The control is guarded on the controls manager exposing a
	 * register() method so a shape change degrades to a no-op.
	 *
	 * @since 3.7.0
	 *
	 * @param \Elementor\Controls_Manager $controls_manager The Elementor controls manager.
	 * @return void
	 */
	public function register_controls( $controls_manager ) {
		if ( ! is_object( $controls_manager ) || ! method_exists( $controls_manager, 'register' ) ) {
			return;
		}

		$controls_manager->register( new \EDD\Elementor\Controls\LayoutPicker() );
	}

	/**
	 * Register the EDD Checkout container element with the elements manager.
	 *
	 * Gated on the Container experiment via Page::is_container_active(): the box
	 * extends the native Container, so it is only registered when containers are on.
	 * The native Container and element classes are then force-autoloaded; the element
	 * load is retried on every call (the Composer autoloader includes the file, not
	 * include_once) so it still defines once Container is present.
	 *
	 * @since 3.7.0
	 *
	 * @param \Elementor\Includes\Managers\Elements_Manager $elements_manager The elements manager.
	 * @return void
	 */
	public function register_element( $elements_manager ) {
		// The composable box requires Elementor containers; skip when the experiment is off.
		if ( ! Page::is_container_active() ) {
			return;
		}

		// Force-autoload the native Container; the element extends it, so it must be loadable.
		if ( ! class_exists( '\Elementor\Includes\Elements\Container', true ) ) {
			return;
		}

		// Force-autoload the element; Container is now present, so an earlier
		// no-op load (guarded by the class body) is retried and defines it.
		if ( class_exists( '\EDD\Elementor\Elements\CheckoutBox', true ) ) {
			$elements_manager->register_element_type( new \EDD\Elementor\Elements\CheckoutBox() );
		}
	}
}
