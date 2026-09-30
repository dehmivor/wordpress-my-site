<?php
/**
 * Input Normalizer.
 *
 * Reshapes ability input that reaches WordPress core's REST run endpoint
 * (/wp-abilities/v1/abilities/{name}/run) in a non-canonical form, so the
 * call still reaches the Premium Addons handler.
 *
 * @package PremiumAddons\Includes\Abilities
 */

namespace PremiumAddons\Includes\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Input_Normalizer
 */
class Input_Normalizer {

	/**
	 * Core REST run routes of Premium Addons abilities.
	 *
	 * @var string
	 */
	const ROUTE_PATTERN = '#^/wp-abilities/v1/abilities/premium-addons/[a-z0-9-]+/run$#';

	/**
	 * Ability name prefix handled by the normalizer.
	 *
	 * @var string
	 */
	const NAME_PREFIX = 'premium-addons/';

	/**
	 * Hook the normalizer.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'rest_request_before_callbacks', array( __CLASS__, 'reshape_rest_request' ), 10, 3 );
		add_filter( 'wp_ability_normalize_input', array( __CLASS__, 'normalize_input' ), 10, 2 );
	}

	/**
	 * Put a stringified or misplaced `input` where core's run controller reads it.
	 *
	 * @param \WP_REST_Response|\WP_HTTP_Response|\WP_Error|mixed $response Result to send.
	 * @param array                                               $handler  Route handler.
	 * @param \WP_REST_Request                                    $request  Request.
	 * @return mixed The unchanged $response.
	 */
	public static function reshape_rest_request( $response, $handler, $request ) {

		if ( ! $request instanceof \WP_REST_Request || ! preg_match( self::ROUTE_PATTERN, $request->get_route() ) ) {
			return $response;
		}

		$input = self::read_input( $request );

		if ( null === $input ) {
			return $response;
		}

		$input = self::decode( $input );

		if ( ! is_array( $input ) ) {
			// Still not an object; let core report the validation error.
			return $response;
		}

		if ( in_array( $request->get_method(), array( 'GET', 'DELETE' ), true ) ) {
			$request->set_query_params( array_merge( $request->get_query_params(), array( 'input' => $input ) ) );
		} else {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
		}

		return $response;
	}

	/**
	 * Decode stringified JSON in the input of a Premium Addons ability.
	 *
	 * @param mixed  $input        Input after core defaults.
	 * @param string $ability_name Ability name.
	 * @return mixed
	 */
	public static function normalize_input( $input, $ability_name ) {

		if ( 0 !== strpos( (string) $ability_name, self::NAME_PREFIX ) ) {
			return $input;
		}

		return self::decode( $input );
	}

	/**
	 * Read `input` from wherever the client put it: JSON body, form body, query.
	 *
	 * Raw sources are read on purpose; `get_param()` returns the value after
	 * core's sanitize callback, which coerces against the ability schema.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed|null
	 */
	private static function read_input( $request ) {

		foreach ( array( $request->get_json_params(), $request->get_body_params(), $request->get_query_params() ) as $params ) {
			if ( is_array( $params ) && array_key_exists( 'input', $params ) ) {
				return $params['input'];
			}
		}

		return null;
	}

	/**
	 * Decode a JSON string into an array, then decode stringified values one level down.
	 *
	 * @param mixed $value Input value.
	 * @return mixed
	 */
	private static function decode( $value ) {

		if ( is_string( $value ) ) {
			$decoded = self::maybe_json_decode( $value );

			if ( null !== $decoded ) {
				$value = $decoded;
			}
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $value as $key => $item ) {
			if ( ! is_string( $item ) ) {
				continue;
			}

			$decoded = self::maybe_json_decode( $item );

			if ( null !== $decoded ) {
				$value[ $key ] = $decoded;
			}
		}

		return $value;
	}

	/**
	 * Decode a string only when it is a JSON object or array.
	 *
	 * @param string $json Candidate string.
	 * @return array|null Decoded array, or null when the string is not JSON.
	 */
	private static function maybe_json_decode( $json ) {

		$json = trim( $json );

		if ( '' === $json || ( '{' !== $json[0] && '[' !== $json[0] ) ) {
			return null;
		}

		$decoded = json_decode( $json, true );

		return ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) ? $decoded : null;
	}
}
