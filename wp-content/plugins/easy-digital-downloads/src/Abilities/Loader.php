<?php
/**
 * Registers EDD abilities with the WordPress Abilities API.
 *
 * @package     EDD\Abilities
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;

/**
 * Abilities Loader.
 *
 * Registers the EDD ability category and all core abilities. The entire
 * surface is gated on the WordPress Abilities API (WordPress 6.9+); on older
 * versions nothing subscribes and nothing registers.
 *
 * @since 3.7.1
 */
class Loader implements SubscriberInterface {

	/**
	 * The ability category slug for all EDD abilities.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const CATEGORY = 'edd';

	/**
	 * The option key which gates every ability that modifies store data.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const WRITE_SETTING = 'abilities_write_enabled';

	/**
	 * Gets the events that this class is subscribed to.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		// The Abilities API can arrive through the feature plugin on WP 6.7 and
		// 6.8, whose function set need not move as one, so check each one used.
		foreach ( array( 'wp_register_ability', 'wp_register_ability_category', 'wp_has_ability' ) as $function ) {
			if ( ! function_exists( $function ) ) {
				return array();
			}
		}

		return array(
			'wp_abilities_api_categories_init' => 'register_category',
			'wp_abilities_api_init'            => 'register_abilities',
		);
	}

	/**
	 * Whether abilities are allowed to modify store data.
	 *
	 * Store owners opt in from Downloads > Tools > AI. Read abilities are
	 * unaffected, and an enabled setting never replaces the capability check
	 * each ability performs on the acting user.
	 *
	 * @since 3.7.1
	 *
	 * @return bool
	 */
	public static function writes_enabled(): bool {
		return (bool) edd_get_option( self::WRITE_SETTING, false );
	}

	/**
	 * Registers the EDD ability category.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Easy Digital Downloads', 'easy-digital-downloads' ),
				'description' => __( 'Store management abilities for Easy Digital Downloads: orders, customers, products, discounts, reports, and store configuration.', 'easy-digital-downloads' ),
			)
		);
	}

	/**
	 * Registers all EDD abilities.
	 *
	 * Nothing stops `wp_abilities_api_init` firing more than once in a request,
	 * and the WordPress registry treats a repeat name as a developer error, so
	 * an ability already registered is skipped rather than re-registered.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	public function register_abilities() {
		foreach ( Registry::get() as $ability ) {
			if ( wp_has_ability( $ability->get_name() ) ) {
				continue;
			}

			$ability->register();
		}
	}
}
