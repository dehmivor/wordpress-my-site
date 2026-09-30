<?php
/**
 * Command Palette Assets.
 *
 * Registers and enqueues the command palette script on every admin page (the
 * palette is global), gated to WordPress 6.9+ where the admin-wide command
 * palette and the Abilities API are available.
 *
 * @package     EDD\CommandPalette
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\CommandPalette;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\REST\Routes\Route;
use EDD\REST\Routes\CommandPalette as SearchRoute;

/**
 * Assets class.
 *
 * @since 3.7.1
 */
class Assets implements SubscriberInterface {

	/**
	 * The script handle.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const HANDLE = 'edd-admin-command-palette';

	/**
	 * Get the events this subscriber listens to.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'admin_enqueue_scripts' => 'enqueue',
		);
	}

	/**
	 * Enqueue the command palette script.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! self::is_supported() ) {
			return;
		}

		wp_register_script(
			self::HANDLE,
			edd_get_assets_url( 'js/admin' ) . 'command-palette.js',
			array( 'wp-commands', 'wp-element', 'wp-data', 'wp-api-fetch', 'wp-url' ),
			edd_admin_get_script_version(),
			true
		);

		wp_localize_script(
			self::HANDLE,
			'eddCommandPalette',
			array(
				'searchPath' => '/' . Route::NAMESPACE . '/' . Route::$version . '/' . SearchRoute::BASE,
				'minChars'   => SearchRoute::MIN_SEARCH_LENGTH,
				'navigation' => ( new Navigation() )->get(),
			)
		);

		wp_enqueue_script( self::HANDLE );
	}

	/**
	 * Whether the command palette is supported in this environment.
	 *
	 * Requires WordPress 6.9+ (admin-wide palette + Abilities API) and the
	 * core command palette script to be registered.
	 *
	 * @since 3.7.1
	 *
	 * @return bool
	 */
	private static function is_supported() {
		if ( ! is_wp_version_compatible( '6.9' ) ) {
			return false;
		}

		return wp_script_is( 'wp-commands', 'registered' );
	}
}
