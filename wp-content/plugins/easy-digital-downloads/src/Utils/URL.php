<?php
/**
 * URL utility class.
 *
 * @package     EDD\Utils
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Utils;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

/**
 * URL utility class.
 *
 * @since 3.7.0
 */
class URL {

	/**
	 * Local TLDs and known host-provider domains that indicate a non-production
	 * (staging or local) environment, matched as a suffix of the host.
	 *
	 * @since 3.7.0
	 * @var string[]
	 */
	const NON_PRODUCTION_HOST_SUFFIXES = array(
		'.local',
		'.test',
		'.localhost',
		'.wpengine.com',
		'.wpenginepowered.com',
		'.instawp.xyz',
		'.cloudwaysapps.com',
		'.flywheelsites.com',
		'.flywheelstaging.com',
		'.myftpupload.com',
		'.kinsta.cloud',
		'.pantheonsite.io',
		'.stage.site',
		'.dreamhosters.com',
		'.dream.press',
		'.mystagingwebsite.com',
		'.wpcomstaging.com',
		'.bigscoots-staging.com',
	);

	/**
	 * Host prefixes that indicate a non-production (staging or local) environment.
	 *
	 * @since 3.7.0
	 * @var string[]
	 */
	const NON_PRODUCTION_HOST_PREFIXES = array( 'dev.', 'staging.', 'staging-', 'test.' );

	/**
	 * Host-anchored regex patterns that indicate a non-production (staging or
	 * local) environment.
	 *
	 * @since 3.7.0
	 * @var string[]
	 */
	const NON_PRODUCTION_HOST_PATTERNS = array(
		'/^staging\d+\.[^.]+\.[^.]+$/', // SiteGround-style numbered staging subdomains.
	);

	/**
	 * URL regex patterns that indicate a non-production (staging or local)
	 * environment.
	 *
	 * @since 3.7.0
	 * @var string[]
	 */
	const NON_PRODUCTION_URL_PATTERNS = array(
		'/\/staging\/\d{3,}/', // Newfold-family numbered staging paths.
	);

	/**
	 * Normalizes a URL for comparison.
	 *
	 * Strips a trailing slash, lowercases the scheme and host, and converts
	 * IDN (internationalized) hostnames to their ASCII/Punycode form so that
	 * Unicode and Punycode representations of the same host compare as equal.
	 *
	 * @since 3.7.0
	 *
	 * @param string $url The URL to normalize.
	 * @return string The normalized URL.
	 */
	public static function normalize( string $url ): string {
		$url = untrailingslashit( trim( $url ) );

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return strtolower( $url );
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) . '://' : '';
		$host   = self::to_ascii( strtolower( $parts['host'] ) );

		$port = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path = isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : '';

		return $scheme . $host . $port . $path;
	}

	/**
	 * Returns the bare, display-friendly host of a URL.
	 *
	 * Lowercases the host, converts IDN (internationalized) hostnames to their
	 * ASCII/Punycode form, and strips a leading "www." so the result is a
	 * compact identifier suitable for display (e.g. a fallback brand name).
	 * Returns an empty string when the URL has no host.
	 *
	 * @since 3.7.0
	 *
	 * @param string $url The URL to extract the host from.
	 * @return string The normalized host without a leading "www.", or '' if none.
	 */
	public static function host( string $url ): string {
		$host = (string) wp_parse_url( trim( $url ), PHP_URL_HOST );
		if ( '' === $host ) {
			return '';
		}

		$host = self::to_ascii( strtolower( $host ) );

		return (string) preg_replace( '/^www\./i', '', $host );
	}

	/**
	 * Determines whether a URL is likely a real production host.
	 *
	 * Use this for guard clauses ("only proceed if this is production") rather
	 * than inverting a "looks like staging" check.
	 *
	 * @since 3.7.0
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL is likely a real production host.
	 */
	public static function is_production_url( string $url ): bool {
		$is_non_production = self::is_non_production( $url );

		/**
		 * Filters whether a URL is considered a real production host.
		 *
		 * Runs regardless of which detector produced the verdict (Software
		 * Licensing's domain-pattern service, or the static fallback list),
		 * so a store can always correct a wrong verdict without a code change.
		 *
		 * @since 3.7.0
		 * @param bool   $is_production True if the URL is considered production.
		 * @param string $url           The URL that was checked.
		 * @return bool True if the URL is considered production.
		 */
		return (bool) apply_filters( 'edd_url_is_production', ! $is_non_production, $url );
	}

	/**
	 * Determines whether a URL looks like a non-production (staging or local) host.
	 *
	 * Checks the current site's configured environment first (independent of
	 * the $url argument, since callers always pass home_url()), then prefers
	 * Software Licensing's own domain-pattern detector when the plugin is
	 * active, since it stays current via the EDD Services API. Falls back to
	 * a static list of common staging/local host patterns otherwise (e.g. on
	 * Lite, which can't call third-party services).
	 *
	 * @since 3.7.0
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL looks like a non-production host.
	 */
	private static function is_non_production( string $url ): bool {
		if ( 'production' !== wp_get_environment_type() ) {
			return true;
		}

		if ( function_exists( 'edd_software_licensing' ) ) {
			return (bool) edd_software_licensing()->is_local_url( $url );
		}

		$host = self::host( $url );

		return '' !== $host && self::matches_non_production_pattern( $host, $url );
	}

	/**
	 * Checks a host and URL against the static fallback non-production
	 * (staging/local) patterns.
	 *
	 * TLDs and host-provider domains are matched as a suffix, and "staging"
	 * as its own path segment (dot-delimited label) anywhere in the host, to
	 * avoid false positives on hosts that merely contain one of these as a
	 * substring (e.g. "mystore.testing.com").
	 *
	 * @since 3.7.0
	 *
	 * @param string $host The (already lowercased) hostname.
	 * @param string $url  The URL the host was extracted from.
	 * @return bool
	 */
	private static function matches_non_production_pattern( string $host, string $url ): bool {
		if ( 'localhost' === $host ) {
			return true;
		}

		// wp_parse_url() keeps the brackets on an IPv6 literal (e.g. "[::1]"),
		// which filter_var() does not accept, so strip them before validating.
		$ip = trim( $host, '[]' );
		if ( false !== filter_var( $ip, FILTER_VALIDATE_IP ) && ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return true;
		}

		if ( false !== strpos( $host, '.staging.' ) ) {
			return true;
		}

		foreach ( self::NON_PRODUCTION_HOST_PREFIXES as $prefix ) {
			if ( 0 === strpos( $host, $prefix ) ) {
				return true;
			}
		}

		foreach ( self::NON_PRODUCTION_HOST_SUFFIXES as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return true;
			}
		}

		foreach ( self::NON_PRODUCTION_HOST_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $host ) ) {
				return true;
			}
		}

		foreach ( self::NON_PRODUCTION_URL_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $url ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Converts an IDN (internationalized) hostname to its ASCII/Punycode form.
	 *
	 * A Unicode form (e.g. münchen.de) and its Punycode equivalent
	 * (xn--mnchen-3ya.de) both reduce to the same canonical ASCII string.
	 * Returns the host unchanged when the intl extension is unavailable or the
	 * conversion fails.
	 *
	 * @since 3.7.0
	 *
	 * @param string $host The (already lowercased) hostname.
	 * @return string The ASCII/Punycode hostname.
	 */
	private static function to_ascii( string $host ): string {
		if ( ! function_exists( 'idn_to_ascii' ) ) {
			return $host;
		}

		$ascii = idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );

		return ! empty( $ascii ) ? $ascii : $host;
	}
}
