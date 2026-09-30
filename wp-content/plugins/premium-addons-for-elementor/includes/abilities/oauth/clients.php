<?php
/**
 * OAuth Clients.
 *
 * Dynamic Client Registration (RFC 7591) — MCP clients self-register and get a
 * public `client_id` (no secret; they use PKCE). Registration is anonymous, as
 * the spec expects, so it is rate limited per IP and capped per site.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Clients.
 *
 * @since 4.11.90
 */
class Clients {

	/**
	 * Registrations allowed per IP per hour.
	 *
	 * @var int
	 */
	const RATE_LIMIT = 10;

	/**
	 * Register the /oauth/register REST route.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			Bootstrap::REST_NAMESPACE,
			'/oauth/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_register' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle a Dynamic Client Registration request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_register( $request ) {

		// Checked before the throttle so a request arriving while registration is
		// closed costs one cache read and stores nothing — no client row, and no
		// per-IP rate-limit transient for a stranger to accumulate.
		if ( ! Bootstrap::registration_open() ) {
			return Bootstrap::error_response( 'access_denied', 'Client registration is closed. An administrator must open the Premium Addons dashboard to allow new connections.', 403 );
		}

		if ( ! Store::throttle( 'dcr', self::RATE_LIMIT, HOUR_IN_SECONDS ) ) {
			return Bootstrap::error_response( 'slow_down', 'Too many registrations from this address. Try again later.', 429 );
		}


		$body = (array) $request->get_json_params();

		if ( array() === $body ) {
			$body = $request->get_params();
		}

		$result = self::validate_registration( $body );

		if ( is_wp_error( $result ) ) {
			return Bootstrap::error_response( $result->get_error_code(), $result->get_error_message() );
		}

		$client = Store::create_client( $result['client_name'], $result['redirect_uris'] );

		if ( is_wp_error( $client ) ) {
			return Bootstrap::error_response( $client->get_error_code(), $client->get_error_message(), 'temporarily_unavailable' === $client->get_error_code() ? 429 : 500 );
		}

		return new \WP_REST_Response(
			array(
				'client_id'                  => $client['client_id'],
				'client_name'                => $client['client_name'],
				'redirect_uris'              => $client['redirect_uris'],
				'token_endpoint_auth_method' => 'none',
				'grant_types'                => array( 'authorization_code', 'refresh_token' ),
				'response_types'             => array( 'code' ),
				'client_id_issued_at'        => time(),
			),
			201
		);
	}

	/**
	 * Validate and normalize a registration body.
	 *
	 * @param array $body Request body.
	 * @return array|\WP_Error Normalized client_name + redirect_uris, or an error.
	 */
	public static function validate_registration( array $body ) {

		$uris = isset( $body['redirect_uris'] ) ? $body['redirect_uris'] : null;

		if ( ! is_array( $uris ) || array() === $uris ) {
			return new \WP_Error( 'invalid_redirect_uri', 'redirect_uris is required and must be a non-empty array.' );
		}

		foreach ( $uris as $uri ) {
			if ( ! is_string( $uri ) || ! Util::is_allowed_redirect_uri( $uri ) ) {
				return new \WP_Error( 'invalid_redirect_uri', 'Each redirect_uri must be an absolute URL without a fragment; plaintext http is allowed for loopback hosts only.' );
			}
		}

		$name = isset( $body['client_name'] ) && is_string( $body['client_name'] )
			? sanitize_text_field( $body['client_name'] )
			: '';

		return array(
			'client_name'   => '' !== $name ? $name : 'MCP Client',
			'redirect_uris' => array_values( array_unique( $uris ) ),
		);
	}
}
