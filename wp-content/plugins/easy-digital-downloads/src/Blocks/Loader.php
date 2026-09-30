<?php
/**
 * Blocks loader.
 *
 * @package     EDD\Blocks
 * @copyright   Copyright (c) 2025, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.0
 */

namespace EDD\Blocks;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\MiniManager;

/**
 * Class Loader
 *
 * @since 3.6.0
 */
class Loader extends MiniManager {

	/**
	 * Get the subscribed events.
	 *
	 * @since 3.7.0
	 * @return array
	 */
	public static function get_subscribed_events() {
		$events           = parent::get_subscribed_events();
		$events['init'][] = 'register_block_metadata_collection';

		return $events;
	}

	/**
	 * Register the block metadata collection.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public static function register_block_metadata_collection() {
		if ( ! defined( 'EDD_BLOCKS_DIR' ) ) {
			return;
		}

		wp_register_block_metadata_collection(
			EDD_BLOCKS_DIR . 'build',
			EDD_BLOCKS_DIR . 'build/blocks-manifest.php'
		);
	}

	/**
	 * Get the event classes.
	 *
	 * @since 3.6.0
	 * @return array
	 */
	protected function get_event_classes(): array {
		return array(
			new Checkout\Elements\UserDetails(),
			new Forms\ProfileEditor(),
			new Checkout\Cart(),
			new Checkout\DiscountForm(),
			new Checkout\Patterns(),
			new Checkout\PaymentInfo(),
			new Checkout\PersonalInfo(),
		);
	}
}
