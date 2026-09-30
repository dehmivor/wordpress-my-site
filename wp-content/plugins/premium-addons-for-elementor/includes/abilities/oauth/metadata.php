<?php
/**
 * OAuth Metadata.
 *
 * The two discovery documents MCP clients fetch to bootstrap the flow:
 * Protected Resource Metadata (RFC 9728) and Authorization Server Metadata
 * (RFC 8414), served under `/.well-known/` on `parse_request`.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

use PremiumAddons\Includes\Abilities\Bootstrap as Abilities_Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Metadata.
 *
 * @since 4.11.90
 */
class Metadata {

	/**
	 * Protected-resource well-known base path.
	 *
	 * @var string
	 */
	const PATH_PROTECTED_RESOURCE = '/.well-known/oauth-protected-resource';

	/**
	 * Authorization-server well-known base path.
	 *
	 * @var string
	 */
	const PATH_AUTH_SERVER = '/.well-known/oauth-authorization-server';

	/**
	 * The issuer identifier (the site's home URL, no trailing slash).
	 *
	 * @return string
	 */
	public static function issuer() {
		return untrailingslashit( home_url() );
	}

	/**
	 * The protected resource identifier — the MCP endpoint clients call.
	 *
	 * @return string
	 */
	public static function resource() {
		return rest_url( Abilities_Bootstrap::server_route() );
	}

	/**
	 * The protected-resource metadata URL advertised in the WWW-Authenticate
	 * challenge. The append form under the site's own path, so this install
	 * serves it even in a subdirectory.
	 *
	 * @return string
	 */
	public static function challenge_url() {
		return home_url( self::PATH_PROTECTED_RESOURCE );
	}

	/**
	 * Every request path a discovery document answers on, keyed by document.
	 * Covers both the append form this site advertises and the insert form
	 * clients construct (RFC 9728 §3.1, RFC 8414); they collapse on a root install.
	 *
	 * @return array Lists of paths keyed by protected_resource|authorization_server.
	 */
	public static function discovery_paths() {

		$home_path     = Bootstrap::home_path();
		$resource_path = (string) wp_parse_url( self::resource(), PHP_URL_PATH );

		return array(
			'protected_resource'   => array_values(
				array_unique(
					array(
						$home_path . self::PATH_PROTECTED_RESOURCE,
						self::PATH_PROTECTED_RESOURCE . $resource_path,
					)
				)
			),
			'authorization_server' => array_values(
				array_unique(
					array(
						$home_path . self::PATH_AUTH_SERVER,
						self::PATH_AUTH_SERVER . $home_path,
					)
				)
			),
		);
	}

	/**
	 * Protected Resource Metadata document (RFC 9728).
	 *
	 * @return array
	 */
	public static function protected_resource_document() {
		return array(
			'resource'                 => self::resource(),
			'authorization_servers'    => array( self::issuer() ),
			'bearer_methods_supported' => array( 'header' ),
			'scopes_supported'         => array( Bootstrap::SCOPE ),
		);
	}

	/**
	 * Authorization Server Metadata document (RFC 8414). Each endpoint URL is
	 * resolved from its complete route path in one rest_url() call: resolving the
	 * namespace on its own and appending to the result yields a wrong URL.
	 *
	 * @return array
	 */
	public static function authorization_server_document() {
		return array(
			'issuer'                                => self::issuer(),
			'authorization_endpoint'                => home_url( Authorize::PATH ),
			'token_endpoint'                        => rest_url( Bootstrap::REST_NAMESPACE . '/oauth/token' ),
			'registration_endpoint'                 => rest_url( Bootstrap::REST_NAMESPACE . '/oauth/register' ),
			'revocation_endpoint'                   => rest_url( Bootstrap::REST_NAMESPACE . '/oauth/revoke' ),
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
			'scopes_supported'                      => array( Bootstrap::SCOPE ),
		);
	}

	/**
	 * Serve a discovery document when the request path matches, then exit.
	 * Hooked on parse_request (priority 0) only when OAuth is registered.
	 *
	 * @return void
	 */
	public static function maybe_serve() {

		$path = Bootstrap::request_path();

		if ( false === strpos( $path, '/.well-known/' ) ) {
			return;
		}

		$paths = self::discovery_paths();

		if ( in_array( $path, $paths['protected_resource'], true ) ) {
			self::emit( self::protected_resource_document() );
		}

		if ( in_array( $path, $paths['authorization_server'], true ) ) {
			self::emit( self::authorization_server_document() );
		}
	}

	/**
	 * Emit a JSON document and exit. Discovery is an anonymous front-end GET:
	 * it must not be cacheable, or a full-page cache serves the pre-opt-in 404
	 * forever.
	 *
	 * @param array $doc Document.
	 * @return void
	 */
	private static function emit( array $doc ) {

		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Cache-Control: no-store' );
		}

		echo wp_json_encode( $doc ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON document.
		exit;
	}
}
