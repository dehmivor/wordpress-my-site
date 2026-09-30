<?php
/**
 * OAuth Fallback.
 *
 * The three default endpoints an MCP client falls back to when it cannot read
 * the discovery documents: `POST /register`, `GET /authorize` and
 * `POST /token` at the site root. Some hosts (SiteGround, Hostinger) answer
 * every `/.well-known/` request at the edge and never pass it to PHP, so
 * discovery returns their 404 and the client tries these paths instead of the
 * ones the metadata would have named. Each is served only when WordPress would
 * otherwise return 404, so a real page or another plugin's rewrite rule keeps
 * the slug.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Fallback.
 *
 * @since 4.11.101
 */
class Fallback {

	/**
	 * Query parameters forwarded from `/authorize` to the real authorize path.
	 * Anything else is dropped; Authorize validates what arrives.
	 *
	 * @var string[]
	 */
	const AUTHORIZE_PARAMS = array(
		'response_type',
		'client_id',
		'redirect_uri',
		'code_challenge',
		'code_challenge_method',
		'scope',
		'state',
		'resource',
	);

	/**
	 * Serve a fallback endpoint when the request names one; no-op otherwise.
	 * Hooked on template_redirect (priority 0) only when OAuth is registered:
	 * after WordPress has resolved the request, so is_404() is reliable, and
	 * ahead of the canonical redirect and any 404-handling plugin.
	 *
	 * @return void
	 */
	public static function maybe_serve() {

		if ( ! is_404() ) {
			return;
		}

		$method   = isset( $_SERVER['REQUEST_METHOD'] ) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- HTTP verb, compared against literals.
		$endpoint = self::endpoint_for_request( Bootstrap::request_path(), $method );

		if ( null === $endpoint ) {
			return;
		}

		if ( 'authorize' === $endpoint ) {
			self::redirect_to_authorize();
		}

		self::serve_rest_route( Bootstrap::REST_NAMESPACE . '/oauth/' . $endpoint );
	}

	/**
	 * The fallback endpoint a request names, or null. Matched exactly, on the
	 * root form clients construct and, on a subdirectory install, the same slug
	 * under the site path (they collapse on a root install).
	 *
	 * @param string $path   Request path, no query string, no trailing slash.
	 * @param string $method HTTP method.
	 * @return string|null register|authorize|token, or null.
	 */
	public static function endpoint_for_request( $path, $method ) {

		$method = strtoupper( $method );
		$home   = Bootstrap::home_path();

		$endpoints = array(
			'/register'  => array( 'register', 'POST' ),
			'/authorize' => array( 'authorize', 'GET' ),
			'/token'     => array( 'token', 'POST' ),
		);

		foreach ( $endpoints as $slug => $spec ) {
			if ( ( $slug === $path || $home . $slug === $path ) && $spec[1] === $method ) {
				return $spec[0];
			}
		}

		return null;
	}

	/**
	 * Send the browser to the real authorize path with the OAuth request
	 * parameters. A redirect rather than an in-place render keeps one
	 * authorize implementation, and the target is this site's own URL.
	 *
	 * @return void
	 */
	private static function redirect_to_authorize() {

		$params = array();

		foreach ( self::AUTHORIZE_PARAMS as $name ) {
			// OAuth authorization requests are cross-site GETs and carry no nonce
			// by design. Values are forwarded verbatim (URL-encoded, since
			// add_query_arg() does not encode) and validated by Authorize.
			if ( isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$params[ $name ] = rawurlencode( wp_unslash( $_GET[ $name ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
		}

		nocache_headers();
		wp_safe_redirect( add_query_arg( $params, home_url( Authorize::PATH ) ) );
		exit;
	}

	/**
	 * Dispatch the current request through the REST server to one of the OAuth
	 * routes. Method, headers and body come from the live request, so the
	 * route's own validation, rate limits and responses apply unchanged.
	 *
	 * @param string $route REST route, e.g. premium-addons/v1/oauth/register.
	 * @return void
	 */
	private static function serve_rest_route( $route ) {

		if ( ! defined( 'REST_REQUEST' ) ) {
			define( 'REST_REQUEST', true );
		}

		nocache_headers();
		rest_get_server()->serve_request( '/' . ltrim( $route, '/' ) );
		exit;
	}
}
