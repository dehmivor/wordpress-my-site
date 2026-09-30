<?php
/**
 * OAuth Bearer.
 *
 * Bearer-token authentication for the MCP transport, wired in as the server's
 * `transport_permission_callback`. Resolves OAuth access tokens to the
 * WordPress user they were issued for, and with no Bearer header reproduces
 * the adapter's default behaviour exactly, so Application Password and cookie
 * auth are untouched. Also emits the WWW-Authenticate challenge that points
 * clients at the OAuth flow (RFC 9728 §5.1).
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

use PremiumAddons\Admin\Includes\Admin_Helper;
use PremiumAddons\Includes\Abilities\Bootstrap as Abilities_Bootstrap;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Bearer.
 *
 * @since 4.11.90
 */
class Bearer {

	/**
	 * Transport permission callback. Returns bool, fail-closed.
	 *
	 * @param \WP_REST_Request $request The MCP request.
	 * @return bool
	 */
	public static function permission_callback( $request ) {

		$token = self::bearer_token( $request );

		if ( '' !== $token ) {

			$row = Store::find_token( $token, 'access' );

			if ( null === $row ) {
				return false;
			}

			wp_set_current_user( (int) $row['user_id'] );

			// Re-check after setting the user: a demoted administrator's token
			// must stop working now, not when it expires.
			return Admin_Helper::check_user_can( Authorize::CAPABILITY );
		}

		// No Bearer token: reproduce HttpTransport::check_permission()'s default
		// shape-for-shape. The filter contract passes an HttpRequestContext,
		// not the WP_REST_Request — third-party filters type-hint it.
		//
		// Guarded because the adapter has relocated transport classes before, and
		// a fatal here would 500 the auth path.
		if ( ! class_exists( \WP\MCP\Transport\Infrastructure\HttpRequestContext::class ) ) {
			return Admin_Helper::check_user_can( 'read' );
		}

		$context = new \WP\MCP\Transport\Infrastructure\HttpRequestContext( $request );

		/** This filter is documented in includes/abilities/vendor/wordpress/mcp-adapter/includes/Transport/HttpTransport.php */
		$user_capability = apply_filters( 'mcp_adapter_default_transport_permission_user_capability', 'read', $context );

		if ( ! is_string( $user_capability ) || empty( $user_capability ) ) {
			$user_capability = 'read';
		}

		return Admin_Helper::check_user_can( $user_capability );
	}

	/**
	 * Extract the Bearer token from a request (or the raw Authorization header).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return string Raw token, or '' when absent.
	 */
	public static function bearer_token( $request ) {

		$header = (string) $request->get_header( 'authorization' );

		// Some Apache rewrite setups strip Authorization into REDIRECT_*.
		if ( '' === $header && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			$header = (string) wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- credential parsed against a strict pattern below.
		}

		return preg_match( '/^\s*Bearer\s+([A-Za-z0-9\-._~+\/]+=*)\s*$/i', $header, $matches ) ? $matches[1] : '';
	}

	/**
	 * Add the WWW-Authenticate challenge to unauthorized responses on the MCP
	 * route, pointing clients at the protected-resource metadata. The
	 * permission callback cannot emit this itself — the transport downgrades
	 * its return value to a bool — so it is attached on rest_post_dispatch.
	 *
	 * @param \WP_HTTP_Response $response Response.
	 * @param \WP_REST_Server   $server   Server (unused).
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_HTTP_Response
	 */
	public static function maybe_challenge( $response, $server, $request ) {

		$status = (int) $response->get_status();

		if ( 401 !== $status && 403 !== $status ) {
			return $response;
		}

		if ( false === strpos( (string) $request->get_route(), Abilities_Bootstrap::server_route() ) ) {
			return $response;
		}

		$response->header( 'WWW-Authenticate', sprintf( 'Bearer resource_metadata="%s"', Metadata::challenge_url() ) );

		return $response;
	}
}
