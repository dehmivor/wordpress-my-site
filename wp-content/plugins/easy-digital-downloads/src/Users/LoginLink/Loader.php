<?php
/**
 * One-Time Login Link Loader Class
 *
 * Registers subscriber classes for the one-time login link feature.
 *
 * @package     EDD\Users\LoginLink
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

namespace EDD\Users\LoginLink;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\MiniManager;

/**
 * Loader class.
 *
 * Extends MiniManager to register one-time login link event classes.
 *
 * @since 3.6.7
 */
class Loader extends MiniManager {

	/**
	 * Gets the event classes to register.
	 *
	 * @since 3.6.7
	 * @return array
	 */
	protected function get_event_classes(): array {
		if ( ! edd_get_option( 'login_link' ) ) {
			return array();
		}

		return array(
			new Verify\Handler(),
			new Send\Handler(),
			new Assets(),
		);
	}
}
