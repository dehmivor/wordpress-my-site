<?php
/**
 * No-Cache Utility
 *
 * Static utility for suppressing caching on token-bearing URLs. Sets the
 * DONOTCACHEPAGE constant, hooks the nocache_headers filter to add
 * Referrer-Policy and LiteSpeed headers, then fires nocache_headers().
 *
 * @package     EDD\Cache
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.7
 */

declare(strict_types=1);

namespace EDD\Cache;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * NoCache class.
 *
 * @since 3.6.7
 */
class NoCache {

	/**
	 * Default headers added to every nocache_headers call.
	 *
	 * @since 3.6.7
	 * @var array<string,string>
	 */
	private const DEFAULTS = array(
		'Referrer-Policy'           => 'no-referrer',
		'X-LiteSpeed-Cache-Control' => 'no-store',
	);

	/**
	 * The single nocache_headers filter callback registered for this request.
	 *
	 * Tracked so subsequent set_headers() calls can remove it before registering
	 * a replacement that carries the merged accumulated state.
	 *
	 * @since 3.6.7
	 * @var \Closure|null
	 */
	private static ?\Closure $filter_callback = null;

	/**
	 * Accumulated extra-header overrides from all set_headers() calls this request.
	 *
	 * Merged across calls via array_merge so an earlier empty-string suppression
	 * is preserved when a subsequent call passes no overrides.
	 *
	 * @since 3.6.7
	 * @var array<string,string>
	 */
	private static array $accumulated_extra = array();

	/**
	 * Sets no-cache headers for the current request.
	 *
	 * Defines DONOTCACHEPAGE, hooks nocache_headers to inject Referrer-Policy
	 * and X-LiteSpeed-Cache-Control, and calls nocache_headers().
	 *
	 * Callers may pass $extra to add headers or suppress a default by passing
	 * an empty string as the value. Multiple calls within the same request
	 * accumulate their overrides; a suppression set by an earlier call is
	 * preserved even when a later call passes no overrides.
	 *
	 * @since 3.6.7
	 *
	 * @param array<string,string> $extra Additional headers to merge. An empty-string
	 *                                    value removes the corresponding default header.
	 * @return void
	 */
	public static function set_headers( array $extra = array() ): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		$extra = self::sanitize_headers( $extra );

		// Remove the current filter before replacing it; this ensures exactly
		// one filter lives on nocache_headers at any point in the request.
		if ( ! is_null( self::$filter_callback ) ) {
			remove_filter( 'nocache_headers', self::$filter_callback );
		}

		// Merge so earlier suppressions survive subsequent calls with no $extra.
		self::$accumulated_extra = array_merge( self::$accumulated_extra, $extra );

		// Capture the accumulated state by value; the closure is self-contained
		// and unaffected by any future mutations to the static property.
		$snapshot = self::$accumulated_extra;

		self::$filter_callback = static function ( array $headers ) use ( $snapshot ): array {
			$merged = array_merge( self::DEFAULTS, $snapshot );

			foreach ( $merged as $name => $value ) {
				if ( '' === $value ) {
					unset( $headers[ $name ] );
					continue;
				}
				$headers[ $name ] = $value;
			}

			return $headers;
		};

		add_filter( 'nocache_headers', self::$filter_callback );

		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Resets the class state for the current request.
	 *
	 * Removes the registered nocache_headers filter and clears the accumulated
	 * extra-header overrides. Should be called in test tearDown methods to
	 * prevent state from bleeding between tests.
	 *
	 * @since 3.6.7
	 *
	 * @return void
	 */
	public static function reset(): void {
		if ( null !== self::$filter_callback ) {
			remove_filter( 'nocache_headers', self::$filter_callback );
		}

		self::$filter_callback   = null;
		self::$accumulated_extra = array();
	}

	/**
	 * Sanitizes header names and values to prevent header injection.
	 *
	 * @since 3.6.7
	 *
	 * @param array<string,string> $headers Raw caller-supplied headers.
	 * @return array<string,string>
	 */
	private static function sanitize_headers( array $headers ): array {
		$clean = array();

		foreach ( $headers as $name => $value ) {
			$name  = sanitize_text_field( (string) $name );
			$value = '' !== $value ? sanitize_text_field( (string) $value ) : '';

			if ( '' !== $name ) {
				$clean[ $name ] = $value;
			}
		}

		return $clean;
	}
}
