<?php
/**
 * The abilities registry.
 *
 * Holds the ability slugs and their associated classes. Extensions add their own
 * abilities through the `edd/abilities/registered` filter, which is also how the
 * Pro-only abilities are added.
 *
 * @package     EDD\Abilities
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Abilities;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * Registry class.
 *
 * @since 3.7.1
 */
class Registry {

	/**
	 * Gets the abilities which should be registered on this site.
	 *
	 * Skips anything which is not an EDD ability, and anything whose feature is
	 * not active, so a store only describes what it can actually do.
	 *
	 * @since 3.7.1
	 *
	 * @return Ability[] Available ability instances, keyed by slug.
	 */
	public static function get(): array {
		$abilities = array();

		foreach ( self::get_registered_classes() as $slug => $class ) {
			$ability = self::instantiate( $class );
			if ( ! $ability || ! $ability->is_available() ) {
				continue;
			}

			$abilities[ $slug ] = $ability;
		}

		return $abilities;
	}

	/**
	 * Whether a slug is registered.
	 *
	 * @since 3.7.1
	 *
	 * @param string $slug The ability slug.
	 * @return bool
	 */
	public static function is_registered( string $slug ): bool {
		return array_key_exists( $slug, self::get_registered_classes() );
	}

	/**
	 * Gets a single ability instance.
	 *
	 * @since 3.7.1
	 *
	 * @param string $slug The ability slug.
	 * @return Ability|false The ability, or false if it is not registered or not an EDD ability.
	 */
	public static function get_ability( string $slug ) {
		$registered = self::get_registered_classes();
		if ( ! array_key_exists( $slug, $registered ) ) {
			return false;
		}

		return self::instantiate( $registered[ $slug ] );
	}

	/**
	 * Builds one ability from a registered class name.
	 *
	 * @since 3.7.1
	 *
	 * @param mixed $class_name The class name from the registry.
	 * @return Ability|false The ability, or false if it is not an EDD ability.
	 */
	private static function instantiate( $class_name ) {
		if ( ! is_string( $class_name ) || ! class_exists( $class_name ) ) {
			return false;
		}

		/**
		 * Every ability has to extend the EDD base class. Beyond keeping the
		 * contract predictable, it is what applies the store's write setting to
		 * an extension's write abilities.
		 */
		if ( ! is_subclass_of( $class_name, Ability::class ) ) {
			return false;
		}

		return new $class_name();
	}

	/**
	 * Gets the registered ability classes, keyed by slug.
	 *
	 * @since 3.7.1
	 *
	 * @return array
	 */
	private static function get_registered_classes(): array {
		/**
		 * Filters the registered abilities.
		 *
		 * Each value must be the name of a class extending EDD\Abilities\ReadAbility
		 * or EDD\Abilities\WriteAbility. Anything else is skipped. Keys are the
		 * ability slug, which becomes the ability name under the `edd/` namespace.
		 *
		 * @since 3.7.1
		 *
		 * @param array $abilities Registered abilities, keyed by slug.
		 */
		return (array) apply_filters(
			'edd/abilities/registered',
			array(
				// Orders.
				'order-read'           => Orders\Read::class,
				'order-create'         => Orders\Create::class,
				'order-update-status'  => Orders\UpdateStatus::class,
				'order-resend-receipt' => Orders\ResendReceipt::class,
				'order-note-read'      => Orders\NoteRead::class,
				'order-note-add'       => Orders\NoteAdd::class,

				// Customers.
				'customer-read'        => Customers\Read::class,
				'customer-create'      => Customers\Create::class,
				'customer-update'      => Customers\Update::class,
				'customer-note-read'   => Customers\NoteRead::class,
				'customer-note-add'    => Customers\NoteAdd::class,

				// Products.
				'product-read'         => Products\Read::class,
				'product-create'       => Products\Create::class,
				'product-update'       => Products\Update::class,

				// Discounts.
				'discount-read'        => Discounts\Read::class,
				'discount-create'      => Discounts\Create::class,
				'discount-update'      => Discounts\Update::class,
				'discount-delete'      => Discounts\Delete::class,

				// Reports.
				'sales-report'         => Reports\Sales::class,
				'sales-summary'        => Reports\SalesSummary::class,

				// Store configuration.
				'settings'             => Store\Settings::class,
				'tax-rates'            => Store\TaxRates::class,
				'health-check'         => Store\HealthCheck::class,

				// Logs.
				'download-log'         => Logs\FileDownloads::class,
				'email-log'            => Logs\Emails::class,
			)
		);
	}
}
