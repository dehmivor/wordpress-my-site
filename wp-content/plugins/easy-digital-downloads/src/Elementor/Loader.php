<?php
/**
 * EDD Elementor Subscribers Loader
 *
 * @package     EDD\Elementor\Subscribers
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Elementor;

use EDD\EventManagement\MiniManager;

/**
 * EDD Elementor Subscriber
 *
 * @package EDD\Elementor\Subscribers
 */
class Loader extends MiniManager {

	/**
	 * Get the event classes.
	 *
	 * @return array
	 */
	protected function get_event_classes(): array {
		return array(
			new Subscribers\Widget(),
			new Subscribers\Checkout(),
			new Subscribers\CheckoutFormLayer(),
			new Subscribers\CheckoutBox(),
			new Subscribers\CheckoutAssets(),
			new Subscribers\EditorAssets(),
			new Subscribers\CheckoutTemplates(),
			new Subscribers\CheckoutEditorPreview(),
		);
	}
}
