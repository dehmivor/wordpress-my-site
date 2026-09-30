<?php
/**
 * Checkout Templates Remote API
 *
 * Handles communication with the remote template API server.
 *
 * @package     EDD\Checkout\Templates
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\Checkout\Templates;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Checkout\Templates\Config\Constants;
use EDD\Utils\RemoteRequest;
use EDD\Utils\Transient;

/**
 * RemoteAPI class
 *
 * Service class for fetching templates from the remote API,
 * with caching support.
 *
 * @since 3.7.0
 */
class RemoteAPI {

	/**
	 * Remote API base URL.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const API_URL = 'https://services.easydigitaldownloads.com/checkout-templates';

	/**
	 * Cache key for template list.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const CACHE_KEY = 'edd_checkout_templates_list';

	/**
	 * Cache timeout (1 hour).
	 *
	 * @since 3.7.0
	 * @var string
	 */
	private const CACHE_TIMEOUT = '+1 hour';

	/**
	 * Get all available templates.
	 *
	 * Fetches templates from the remote API with caching.
	 *
	 * @since 3.7.0
	 * @param bool $force_refresh Whether to bypass the cache.
	 * @return array Array of template data.
	 */
	public function get_templates( bool $force_refresh = false ): array {
		// Try cache first (unless force refresh).
		$cache  = new Transient( self::CACHE_KEY, self::CACHE_TIMEOUT );
		$cached = $cache->get();
		$stale  = $cache->get( true );

		if ( ! $force_refresh && false !== $cached ) {
			return $cached;
		}

		edd_debug_log( 'CTI: Fetching templates from remote API' );

		// Fetch from remote API.
		$response = $this->make_request( '' );

		// A WP_Error means the HTTP request itself failed — fall back to stale cache.
		if ( is_wp_error( $response ) ) {
			edd_debug_log( 'CTI: Remote fetch failed, checking fallbacks' );

			// Return stale cache if available (better than nothing).
			if ( false !== $stale ) {
				edd_debug_log( 'CTI: Using stale cache' );
				return $stale;
			}

			return array(
				'templates'         => array(),
				'available_tags'    => array(),
				'available_editors' => array(),
			);
		}

		// Guard against malformed responses (valid JSON that lacks the expected shape).
		// Treat these like a failed request: prefer stale cache, fall through to an empty payload.
		if (
			! is_array( $response )
			|| ! isset( $response['templates'] ) || ! is_array( $response['templates'] )
			|| ! isset( $response['available_tags'] ) || ! is_array( $response['available_tags'] )
		) {
			edd_debug_log( 'CTI: Remote response malformed, checking fallbacks' );

			if ( false !== $stale ) {
				edd_debug_log( 'CTI: Using stale cache' );
				return $stale;
			}

			return array(
				'templates'         => array(),
				'available_tags'    => array(),
				'available_editors' => array(),
			);
		}

		// An empty templates array is a valid API response and should be cached normally.
		edd_debug_log( sprintf( 'CTI: Retrieved %d templates', count( $response['templates'] ?? array() ) ) );

		// Cache the full response (templates + available_tags + available_editors).
		$data = array(
			'templates'         => $response['templates'] ?? array(),
			'available_tags'    => $response['available_tags'] ?? array(),
			'available_editors' => $response['available_editors'] ?? array(),
		);
		$cache->set( $data );

		return $data;
	}

	/**
	 * Make an HTTP request to the remote API.
	 *
	 * Uses EDD\Utils\RemoteRequest for consistent HTTP handling.
	 *
	 * @since 3.7.0
	 * @param string $endpoint API endpoint path.
	 * @param array  $params   Request parameters.
	 * @param string $method   HTTP method (GET, POST).
	 * @param array  $args     Additional wp_remote_request args.
	 * @return array|\WP_Error Response data or error.
	 */
	protected function make_request( string $endpoint, array $params = array(), string $method = 'GET', array $args = array() ) {
		$base_url = self::API_URL;
		$url      = $base_url . $endpoint;

		if ( 'GET' === $method && ! empty( $params ) ) {
			$url = add_query_arg( $params, $url );
		}

		// Only send the license key to trusted EDD infrastructure hosts to
		// prevent accidental key leakage when an override URL is configured.
		$url_host     = (string) wp_parse_url( $url, PHP_URL_HOST );
		$is_edd_host  = (bool) preg_match( '/(?:^|\.)easydigitaldownloads\.com$/i', $url_host );
		$headers      = array(
			'Content-Type'        => 'application/json',
			'X-Site-URL'          => home_url(),
			'X-EDD-Version'       => defined( 'EDD_VERSION' ) ? EDD_VERSION : '',
			'X-WP-Version'        => get_bloginfo( 'version' ),
			'X-PHP-Version'       => phpversion(),
			'X-Elementor-Version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
		);
		if ( $is_edd_host ) {
			$headers['X-License-Key'] = License::get_key();
		}

		$request_args = wp_parse_args(
			$args,
			array(
				'method'  => $method,
				'headers' => $headers,
			)
		);

		if ( 'POST' === $method && ! empty( $params ) ) {
			$request_args['body'] = wp_json_encode( $params );
		}

		// Use EDD's RemoteRequest utility (handles timeout, SSL, user-agent).
		$request = new RemoteRequest( $url, $request_args );

		if ( is_wp_error( $request->response ) ) {
			edd_debug_log( 'CTI: Request failed - ' . $request->response->get_error_message() );
			return $request->response;
		}

		if ( 200 !== $request->code ) {
			edd_debug_log( sprintf( 'CTI: Request returned code %d', $request->code ) );

			// Handle different status code ranges appropriately.
			$status      = $request->code;
			$is_server_error = $status >= 500;

			if ( $is_server_error ) {
				// 5xx errors are server-side and potentially recoverable via retry.
				return new \WP_Error(
					'api_server_error',
					__( 'Template server is temporarily unavailable. Please try again later.', 'easy-digital-downloads' ),
					array(
						'status'      => $status,
						'support_url' => Constants::SUPPORT_URL,
						'recoverable' => true,
						'retry'       => true,
					)
				);
			}

			// 4xx errors are client-side (not found, unauthorized, etc.).
			return new \WP_Error(
				'api_client_error',
				__( 'Failed to fetch templates from server. Please contact support if the issue persists.', 'easy-digital-downloads' ),
				array(
					'status'      => $status,
					'support_url' => Constants::SUPPORT_URL,
					'recoverable' => false,
					'retry'       => false,
				)
			);
		}

		$body = json_decode( $request->body, true );

		// Check for JSON decode errors.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			edd_debug_log( 'CTI: JSON decode error - ' . json_last_error_msg() );
			return new \WP_Error(
				'json_error',
				__( 'Invalid response from template server. Please try again or contact support.', 'easy-digital-downloads' ),
				array(
					'status'      => 502,
					'support_url' => Constants::SUPPORT_URL,
					'recoverable' => true,
					'retry'       => true,
				)
			);
		}

		return is_array( $body ) ? $body : array();
	}
}
