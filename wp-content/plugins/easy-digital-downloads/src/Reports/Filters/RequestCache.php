<?php
/**
 * Reports API - Request Cache
 *
 * Memoizes reports filter state for the life of a single request, keyed on the request values the
 * filters are derived from.
 *
 * @package     EDD\Reports\Filters
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\Reports\Filters;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Reports;
use EDD\Utils\Date;

/**
 * Request scoped store for resolved reports filter values.
 *
 * @since 3.7.1
 */
class RequestCache {

	/**
	 * Resolved values, keyed by scope and request signature.
	 *
	 * @since 3.7.1
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Request keys the signature is built from.
	 *
	 * @since 3.7.1
	 * @var array|null
	 */
	private static $keys = null;

	/**
	 * Builds a cache key for a scope and its arguments.
	 *
	 * @since 3.7.1
	 *
	 * @param string $scope Identifier for the value being cached, usually the calling function.
	 * @param array  $args  Optional. Arguments the value depends on. Default empty array.
	 * @return string
	 */
	public static function key( string $scope, array $args = array() ): string {
		return md5( serialize( array( $scope, $args, self::signature() ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}

	/**
	 * Retrieves a cached value.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key Cache key.
	 * @return mixed Cached value, or null when the key has not been set.
	 */
	public static function get( string $key ) {
		return array_key_exists( $key, self::$cache ) ? self::$cache[ $key ] : null;
	}

	/**
	 * Stores a value.
	 *
	 * @since 3.7.1
	 *
	 * @param string $key   Cache key.
	 * @param mixed  $value Value to store.
	 * @return void
	 */
	public static function set( string $key, $value ) {
		self::$cache[ $key ] = $value;
	}

	/**
	 * Copies the date objects in a dates array.
	 *
	 * The date helpers in `includes/date-functions.php` call `setTimezone()` on the object they are
	 * given and hand back that same instance, so a shared instance would be re-zoned by the first
	 * caller that formats it for display.
	 *
	 * @since 3.7.1
	 *
	 * @param array $dates Dates array, as returned by the reports date parsers.
	 * @return array
	 */
	public static function clone_dates( array $dates ): array {
		foreach ( array( 'start', 'end' ) as $key ) {
			if ( isset( $dates[ $key ] ) && $dates[ $key ] instanceof Date ) {
				$dates[ $key ] = $dates[ $key ]->copy();
			}
		}

		return $dates;
	}

	/**
	 * Empties the store.
	 *
	 * @since 3.7.1
	 *
	 * @return void
	 */
	public static function reset() {
		self::$cache = array();
		self::$keys  = null;
	}

	/**
	 * Builds a hash of every request value the reports filters are derived from.
	 *
	 * One signature covers all filters: over-invalidating costs a recomputation, while
	 * under-invalidating hands back dates for the wrong range with no error.
	 *
	 * @since 3.7.1
	 *
	 * @return string
	 */
	private static function signature(): string {
		// Report filters must be registered before the first filter value is resolved; the key list is fixed from then on.
		if ( is_null( self::$keys ) ) {
			self::$keys = array_merge( Reports\get_persisted_filters(), array_keys( Reports\get_filters() ) );
		}

		$parts = array( \edd_get_timezone_id() );

		foreach ( self::$keys as $key ) {
			$parts[ $key ] = isset( $_GET[ $key ] ) ? $_GET[ $key ] : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		return md5( serialize( $parts ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
	}
}
