<?php
/**
 * PayPal Connect API Client
 *
 * Makes HMAC-signed HTTP requests to EDD Connect for PayPal Commerce integration.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal\CommerceVersion;
use EDD\Gateways\PayPal\Gateway;
use EDD\Gateways\PayPal\V3\Credentials;
use WP_Error;

/**
 * ConnectAPI class.
 *
 * Handles communication with EDD Connect, including HMAC authentication,
 * idempotency key generation, and standardized error handling.
 *
 * @since 3.6.9
 */
class ConnectAPI {

	/**
	 * The Connect base URL.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	private $connect_url;

	/**
	 * The store ID (UUID).
	 *
	 * @since 3.6.9
	 * @var string
	 */
	private $store_id;

	/**
	 * The HMAC shared secret.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	private $hmac_key;

	/**
	 * The PayPal environment this client targets ('sandbox' or 'live').
	 *
	 * Sent on every outbound request as the `X-EDD-PayPal-Mode` header so the
	 * Connect service can route to the correct paypal_merchants row on stores that have
	 * both environments connected.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	private $mode;

	/**
	 * The HTTP response code from the last request.
	 *
	 * @since 3.6.9
	 * @var int
	 */
	private $last_response_code = 0;

	/**
	 * Constructor.
	 *
	 * @since 3.6.9
	 *
	 * @param string $mode Optional. 'sandbox' or 'live'. Defaults to current mode.
	 */
	public function __construct( $mode = '' ) {
		if ( empty( $mode ) ) {
			$mode = Gateway::get_paypal_mode();
		}

		$this->connect_url = CommerceVersion::get_connect_url();

		$this->mode     = $mode;
		$this->store_id = get_option( "edd_paypal_{$mode}_store_id", '' );
		$this->hmac_key = Credentials::get_hmac_key( $mode );
	}

	/**
	 * Gets the HTTP response code from the last request.
	 *
	 * @since 3.6.9
	 *
	 * @return int The HTTP response code.
	 */
	public function get_last_response_code() {
		return $this->last_response_code;
	}

	/**
	 * Makes a GET request to the Connect service.
	 *
	 * @since 3.6.9
	 *
	 * @param string $endpoint The API endpoint path (e.g., '/v3/paypal/orders/123').
	 * @return array|WP_Error The decoded response body, or WP_Error on failure.
	 */
	public function get( $endpoint ) {
		return $this->make_request( 'GET', $endpoint );
	}

	/**
	 * Makes a POST request to the Connect service.
	 *
	 * @since 3.6.9
	 *
	 * @param string $endpoint        The API endpoint path.
	 * @param array  $body            The request body data.
	 * @param string $idempotency_key Optional. A deterministic idempotency key. When
	 *                                provided, it is sent instead of a random per-request
	 *                                key so the proxy can collapse a re-fired charge onto
	 *                                the original result instead of charging twice.
	 * @return array|WP_Error The decoded response body, or WP_Error on failure.
	 */
	public function post( $endpoint, $body = array(), $idempotency_key = '' ) {
		return $this->make_request( 'POST', $endpoint, $body, $idempotency_key );
	}

	/**
	 * Makes a DELETE request to the Connect service.
	 *
	 * @since 3.6.9
	 *
	 * @param string $endpoint The API endpoint path.
	 * @param array  $body     Optional. The request body data.
	 * @return array|WP_Error The decoded response body, or WP_Error on failure.
	 */
	public function delete( $endpoint, $body = array() ) {
		return $this->make_request( 'DELETE', $endpoint, $body );
	}

	/**
	 * Makes an HMAC-signed request to the Connect service.
	 *
	 * @since 3.6.9
	 *
	 * @param string $method          HTTP method (GET, POST, DELETE).
	 * @param string $endpoint        The API endpoint path.
	 * @param array  $body            Optional. The request body for POST requests.
	 * @param string $idempotency_key Optional. A deterministic idempotency key for mutating
	 *                                requests. Defaults to a random per-request key.
	 * @return array|WP_Error The decoded response body, or WP_Error on failure.
	 */
	public function make_request( $method, $endpoint, $body = array(), $idempotency_key = '' ) {
		$method = strtoupper( $method );

		// Serialize the body.
		$body_json = ! empty( $body ) ? wp_json_encode( $body ) : '';

		// The Connect service verifies signatures against the URL pathname only (no query
		// string), so we sign the pathname only. The full endpoint (with any
		// query string) is still used to build the outbound request URL below.
		$signing_endpoint = strtok( $endpoint, '?' );

		// Build HMAC headers.
		$headers = $this->build_hmac_headers( $method, $signing_endpoint, $body_json );

		// Store mode.
		if ( ! empty( $this->mode ) ) {
			$headers['X-EDD-PayPal-Mode'] = $this->mode;
		}

		// Add Content-Type for requests with a body.
		if ( ! empty( $body_json ) ) {
			$headers['Content-Type'] = 'application/json';
		}

		// Add idempotency key for mutating requests. A caller-supplied
		// deterministic key lets the proxy dedupe a re-fired charge (e.g. a
		// renewal that captured but whose PHP process died before recording the
		// order) onto the original result; otherwise a random key is generated.
		if ( in_array( $method, array( 'POST', 'DELETE' ), true ) ) {
			$headers['X-EDD-Idempotency-Key'] = ! empty( $idempotency_key ) ? $idempotency_key : wp_generate_uuid4();
		}

		$request_args = array(
			'method'     => $method,
			'timeout'    => 30,
			'headers'    => $headers,
			'user-agent' => 'Easy Digital Downloads/' . EDD_VERSION . '; ' . get_bloginfo( 'name' ),
		);

		if ( ! empty( $body_json ) ) {
			$request_args['body'] = $body_json;
		}

		$url = trailingslashit( $this->connect_url ) . ltrim( $endpoint, '/' );
		$response = wp_remote_request( $url, $request_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->last_response_code = intval( wp_remote_retrieve_response_code( $response ) );
		$response_body            = wp_remote_retrieve_body( $response );

		// 204 No Content (e.g., DELETE success).
		if ( 204 === $this->last_response_code ) {
			return array();
		}

		if ( $this->last_response_code >= 400 ) {
			// The Connect API returns structured `{ error: { code, message } }` bodies
			// on 4xx (e.g. 403 applepay_not_available). Surface that array so
			// callers can branch on the error code instead of a generic WP_Error.
			$decoded = json_decode( $response_body, true );
			if ( is_array( $decoded ) && isset( $decoded['error'] ) ) {
				return $decoded;
			}

			return new WP_Error(
				'proxy_http_error',
				wp_remote_retrieve_response_message( $response ),
				array( 'status' => $this->last_response_code )
			);
		}

		$content_type  = wp_remote_retrieve_header( $response, 'content-type' );
		$declares_json = false !== stripos( (string) $content_type, 'json' );

		$decoded = json_decode( $response_body, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// JSON was declared but decoding failed — that's a real error, not a
		// raw payload.
		if ( $declares_json && ! empty( $response_body ) ) {
			return new \WP_Error(
				'proxy_invalid_json',
				__( 'Invalid JSON response from proxy.', 'easy-digital-downloads' ),
				array( 'body' => $response_body )
			);
		}

		return is_string( $response_body ) ? $response_body : array();
	}

	/**
	 * Makes an unauthenticated POST request to register a store.
	 *
	 * This endpoint does not use HMAC authentication.
	 *
	 * @since 3.6.9
	 *
	 * @param array $body The registration request body.
	 * @return array|WP_Error The decoded response body.
	 */
	public function register_store( $body ) {
		$url = trailingslashit( $this->connect_url ) . 'v3/stores/register';

		$response = wp_remote_post(
			$url,
			array(
				'timeout'    => 30,
				'headers'    => array(
					'Content-Type' => 'application/json',
				),
				'body'       => wp_json_encode( $body ),
				'user-agent' => 'Easy Digital Downloads/' . EDD_VERSION . '; ' . get_bloginfo( 'name' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->last_response_code = intval( wp_remote_retrieve_response_code( $response ) );

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return new \WP_Error(
				'proxy_invalid_json',
				__( 'Invalid response from PayPal proxy during store registration.', 'easy-digital-downloads' ),
				array( 'status_code' => $this->last_response_code )
			);
		}

		return $decoded;
	}

	/**
	 * Builds the HMAC authentication headers.
	 *
	 * Internal helper invoked from `make_request()`. Tests exercise it
	 * via reflection — there is no other public caller.
	 *
	 * @since 3.6.9
	 *
	 * @param string $method    HTTP method in UPPERCASE.
	 * @param string $endpoint  The request path starting with '/'.
	 * @param string $body_json The raw request body as a UTF-8 string.
	 * @return array Headers array.
	 */
	private function build_hmac_headers( $method, $endpoint, $body_json = '' ) {
		$timestamp = (string) time();
		$nonce     = wp_generate_password( 32, false );

		$signature = $this->compute_signature( $timestamp, $nonce, $method, $endpoint, $body_json );

		return array(
			'X-EDD-Store-ID'  => $this->store_id,
			'X-EDD-Timestamp' => $timestamp,
			'X-EDD-Nonce'     => $nonce,
			'X-EDD-Signature' => $signature,
		);
	}

	/**
	 * Computes the HMAC-SHA256 signature per the contract spec.
	 *
	 * Internal helper invoked from `build_hmac_headers()`. Tests
	 * exercise it via reflection — there is no other public caller.
	 *
	 * @since 3.6.9
	 *
	 * @param string $timestamp Unix epoch integer as string.
	 * @param string $nonce     32-character alphanumeric string.
	 * @param string $method    HTTP method in UPPERCASE.
	 * @param string $endpoint  Request path starting with '/'.
	 * @param string $body_json Raw request body (empty string for no body).
	 * @return string Lowercase hex-encoded HMAC-SHA256 signature.
	 */
	private function compute_signature( $timestamp, $nonce, $method, $endpoint, $body_json = '' ) {
		$body_hash = hash( 'sha256', $body_json );

		$message = sprintf(
			'%s.%s.%s.%s.%s',
			$timestamp,
			$nonce,
			strtoupper( $method ),
			$endpoint,
			$body_hash
		);

		return hash_hmac( 'sha256', $message, $this->hmac_key );
	}

	/**
	 * Sets the store ID.
	 *
	 * @since 3.6.9
	 *
	 * @param string $store_id The store UUID.
	 */
	public function set_store_id( $store_id ) {
		$this->store_id = $store_id;
	}

	/**
	 * Sets the HMAC key.
	 *
	 * @since 3.6.9
	 *
	 * @param string $hmac_key The HMAC shared secret.
	 */
	public function set_hmac_key( $hmac_key ) {
		$this->hmac_key = $hmac_key;
	}

	/**
	 * Checks if the response contains an error.
	 *
	 * @since 3.6.9
	 *
	 * @param array $response The decoded Connect response.
	 * @return bool True if the response contains an error.
	 */
	public static function is_error( $response ) {
		return is_wp_error( $response ) || ( is_array( $response ) && isset( $response['error'] ) );
	}

	/**
	 * Extracts the error code from a Connect error response.
	 *
	 * @since 3.6.9
	 *
	 * @param array $response The decoded Connect response.
	 * @return string The error code, or empty string if not an error.
	 */
	public static function get_error_code( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_code();
		}

		if ( is_array( $response ) && isset( $response['error']['code'] ) ) {
			return $response['error']['code'];
		}

		return '';
	}

	/**
	 * Extracts the error message from a Connect error response.
	 *
	 * @since 3.6.9
	 *
	 * @param array $response The decoded Connect response.
	 * @return string The error message, or empty string if not an error.
	 */
	public static function get_error_message( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		if ( is_array( $response ) && isset( $response['error']['message'] ) ) {
			return $response['error']['message'];
		}

		return '';
	}

	/**
	 * Extracts the PayPal debug ID from a Connect error response.
	 *
	 * @since 3.6.9
	 *
	 * @param array $response The decoded Connect response.
	 * @return string|null The PayPal debug ID, or null.
	 */
	public static function get_paypal_debug_id( $response ) {
		if ( is_array( $response ) && isset( $response['error']['paypal_debug_id'] ) ) {
			return $response['error']['paypal_debug_id'];
		}

		return null;
	}
}
