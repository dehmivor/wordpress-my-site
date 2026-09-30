<?php
/**
 * OAuth Token.
 *
 * The `/oauth/token` and `/oauth/revoke` endpoints — exchange an authorization
 * code (with PKCE) for an access + refresh pair, rotate refresh tokens, and
 * revoke (RFC 7009). Public clients only: there is no client secret; codes are
 * PKCE-bound at authorize time and verified here.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\OAuth;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Token.
 *
 * @since 4.11.90
 */
class Token {

	/**
	 * Access-token lifetime: 1 hour.
	 *
	 * @var int
	 */
	const ACCESS_TTL = 3600;

	/**
	 * Refresh-token lifetime: 14 days, rotating.
	 *
	 * @var int
	 */
	const REFRESH_TTL = 1209600;

	/**
	 * Requests allowed per IP per minute on token and revoke.
	 *
	 * @var int
	 */
	const RATE_LIMIT = 30;

	/**
	 * Register the /oauth/token and /oauth/revoke REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			Bootstrap::REST_NAMESPACE,
			'/oauth/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_token' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			Bootstrap::REST_NAMESPACE,
			'/oauth/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_revoke' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Token endpoint dispatcher.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_token( $request ) {

		if ( ! Store::throttle( 'token', self::RATE_LIMIT, MINUTE_IN_SECONDS ) ) {
			return Bootstrap::error_response( 'slow_down', 'Too many requests from this address. Try again in a minute.', 429 );
		}

		$params     = self::body_params( $request );
		$grant_type = isset( $params['grant_type'] ) ? (string) $params['grant_type'] : '';

		if ( 'authorization_code' === $grant_type ) {
			return self::exchange_code( $params );
		}

		if ( 'refresh_token' === $grant_type ) {
			return self::refresh( $params );
		}

		return Bootstrap::error_response( 'unsupported_grant_type', 'Unsupported grant_type.' );
	}

	/**
	 * Revoke a token (RFC 7009). Always 200, even for unknown tokens, so the
	 * endpoint cannot be used as a token oracle.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function handle_revoke( $request ) {

		if ( ! Store::throttle( 'revoke', self::RATE_LIMIT, MINUTE_IN_SECONDS ) ) {
			return Bootstrap::error_response( 'slow_down', 'Too many requests from this address. Try again in a minute.', 429 );
		}

		$params = self::body_params( $request );
		$token  = isset( $params['token'] ) ? (string) $params['token'] : '';

		if ( '' !== $token ) {
			foreach ( array( 'access', 'refresh' ) as $type ) {
				$row = Store::find_token( $token, $type );

				if ( null !== $row ) {
					// Cascade from the refresh row when an access token is
					// presented, so revoking does not leave its refresh token
					// alive to mint a replacement.
					Store::revoke_token( $row['refresh_of'] ? (int) $row['refresh_of'] : (int) $row['id'] );
					break;
				}
			}
		}

		return new \WP_REST_Response( array( 'revoked' => true ), 200 );
	}

	/**
	 * Read the request body. RFC 6749 §4.1.3 mandates form encoding and that is
	 * what real clients send, but a JSON body is accepted too so a client posting
	 * one does not get an opaque unsupported_grant_type.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private static function body_params( $request ) {

		$params = $request->get_body_params();

		if ( empty( $params ) ) {
			$params = (array) $request->get_json_params();
		}

		return array_filter( $params, 'is_string' );
	}

	/**
	 * Exchange an authorization code for a token pair.
	 *
	 * @param array $params Request body params.
	 * @return \WP_REST_Response
	 */
	private static function exchange_code( array $params ) {

		$code         = isset( $params['code'] ) ? (string) $params['code'] : '';
		$client_id    = isset( $params['client_id'] ) ? (string) $params['client_id'] : '';
		$redirect_uri = isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '';
		$verifier     = isset( $params['code_verifier'] ) ? (string) $params['code_verifier'] : '';

		$payload = '' === $code ? null : Store::consume_code( $code );
		$check   = self::validate_code_exchange( $payload, $client_id, $redirect_uri, $verifier );

		if ( is_wp_error( $check ) ) {
			return Bootstrap::error_response( $check->get_error_code(), $check->get_error_message() );
		}

		$pair = self::issue_pair( $client_id, (int) $payload['user_id'], (string) $payload['scopes'] );

		if ( is_wp_error( $pair ) ) {
			return Bootstrap::error_response( $pair->get_error_code(), $pair->get_error_message(), 500 );
		}

		return self::token_response( $pair['access'], $pair['refresh'], (string) $payload['scopes'] );
	}

	/**
	 * Rotate a refresh token: revoke the presented one (and its bound access
	 * token via refresh_of), then issue a fresh pair. A replayed refresh token
	 * therefore fails with invalid_grant.
	 *
	 * @param array $params Request body params.
	 * @return \WP_REST_Response
	 */
	private static function refresh( array $params ) {

		$refresh_token = isset( $params['refresh_token'] ) ? (string) $params['refresh_token'] : '';
		$client_id     = isset( $params['client_id'] ) ? (string) $params['client_id'] : '';

		$row = '' === $refresh_token ? null : Store::consume_refresh_token( $refresh_token, $client_id );

		if ( null === $row ) {
			return Bootstrap::error_response( 'invalid_grant', 'Refresh token is invalid or expired.' );
		}

		$pair = self::issue_pair( $client_id, (int) $row['user_id'], (string) $row['scopes'] );

		if ( is_wp_error( $pair ) ) {
			return Bootstrap::error_response( $pair->get_error_code(), $pair->get_error_message(), 500 );
		}

		return self::token_response( $pair['access'], $pair['refresh'], (string) $row['scopes'] );
	}

	/**
	 * Validate an authorization-code exchange against the stored code payload.
	 *
	 * @param array|null $payload      Stored code payload (null if unknown/used).
	 * @param string     $client_id    Presented client id.
	 * @param string     $redirect_uri Presented redirect URI.
	 * @param string     $verifier     PKCE code_verifier.
	 * @return true|\WP_Error
	 */
	public static function validate_code_exchange( $payload, $client_id, $redirect_uri, $verifier ) {

		if ( null === $payload ) {
			return new \WP_Error( 'invalid_grant', 'Authorization code is invalid or expired.' );
		}

		if ( ! hash_equals( (string) $payload['client_id'], $client_id ) ) {
			return new \WP_Error( 'invalid_grant', 'Client mismatch for this authorization code.' );
		}

		if ( ! hash_equals( (string) $payload['redirect_uri'], $redirect_uri ) ) {
			return new \WP_Error( 'invalid_grant', 'redirect_uri does not match the authorization request.' );
		}

		if ( ! Util::verify_pkce( $verifier, (string) $payload['code_challenge'] ) ) {
			return new \WP_Error( 'invalid_grant', 'PKCE verification failed.' );
		}

		return true;
	}

	/**
	 * Issue a refresh token and an access token bound to it.
	 *
	 * @param string $client_id Client id.
	 * @param int    $user_id   User the tokens act as.
	 * @param string $scope     Granted scope.
	 * @return array|\WP_Error Raw access + refresh tokens, or an error when persistence fails.
	 */
	private static function issue_pair( $client_id, $user_id, $scope ) {

		$refresh = Store::issue_token( 'refresh', $client_id, $user_id, $scope, self::REFRESH_TTL );

		if ( is_wp_error( $refresh ) ) {
			return $refresh;
		}

		$access = Store::issue_token( 'access', $client_id, $user_id, $scope, self::ACCESS_TTL, (int) $refresh['id'] );

		if ( is_wp_error( $access ) ) {
			Store::revoke_token( (int) $refresh['id'] );

			return $access;
		}

		return array(
			'access'  => $access['token'],
			'refresh' => $refresh['token'],
		);
	}

	/**
	 * Build the RFC 6749 token response body.
	 *
	 * @param string $access_token  Access token.
	 * @param string $refresh_token Refresh token.
	 * @param string $scope         Granted scope.
	 * @return \WP_REST_Response
	 */
	private static function token_response( $access_token, $refresh_token, $scope ) {

		$response = new \WP_REST_Response(
			array(
				'access_token'  => $access_token,
				'token_type'    => 'Bearer',
				'expires_in'    => self::ACCESS_TTL,
				'refresh_token' => $refresh_token,
				'scope'         => $scope,
			),
			200
		);
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );

		return $response;
	}
}
