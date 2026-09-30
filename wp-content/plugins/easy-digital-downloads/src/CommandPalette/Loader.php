<?php
/**
 * Command Palette Loader.
 *
 * Registers the subscribers that wire the command palette into WordPress.
 *
 * @package     EDD\CommandPalette
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\CommandPalette;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\MiniManager;

/**
 * Loader class.
 *
 * @since 3.7.1
 */
class Loader extends MiniManager {

	/**
	 * Get the event classes.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	protected function get_event_classes(): array {
		return array(
			new CoreSources(),
			new Assets(),
		);
	}
}
