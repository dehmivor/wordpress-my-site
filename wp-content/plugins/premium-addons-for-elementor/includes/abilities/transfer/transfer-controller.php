<?php
/**
 * Transfer Controller.
 *
 * The Source-side REST route a Destination site fetches a copy payload from. It
 * carries no WordPress authentication on purpose — the Destination calls it
 * anonymously, server to server, and the HMAC signature is the capability.
 *
 * @package PremiumAddons
 */

namespace PremiumAddons\Includes\Abilities\Transfer;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class Transfer_Controller.
 *
 * @since 4.11.76
 */
class Transfer_Controller {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'premium-addons/v1';

	/**
	 * REST route.
	 */
	const REST_ROUTE = '/transfer';

	/**
	 * Register the transfer route.
	 *
	 * @return void
	 */
	public static function register_routes() {

		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'serve' ),
				'permission_callback' => array( __CLASS__, 'check_signature' ),
				'args'                => array(
					'token'   => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'exp'     => array(
						'type'     => 'integer',
						'required' => true,
					),
					'sig'     => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'consume' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Authorize the request from its signature alone.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_signature( $request ) {

		$token = (string) $request->get_param( 'token' );
		$exp   = (int) $request->get_param( 'exp' );
		$sig   = (string) $request->get_param( 'sig' );

		$is_valid = '' !== $token
			&& $exp >= time()
			&& Transfer_Signer::verify( $token, $exp, $sig );

		if ( ! $is_valid ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'This transfer link is not valid or has expired.', 'premium-addons-for-elementor' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Serve the stored payload.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function serve( $request ) {

		$token   = (string) $request->get_param( 'token' );
		$payload = Transfer_Store::get( $token );

		if ( null === $payload ) {
			return new \WP_Error(
				'premium_addons_transfer_data_unavailable',
				__( 'This transfer is no longer available. It has expired or has already been imported.', 'premium-addons-for-elementor' ),
				array( 'status' => 410 )
			);
		}

		// Single-use: the import consumes, the compatibility pre-flight does not.
		if ( $request->get_param( 'consume' ) ) {
			Transfer_Store::consume( $token );
		}

		return rest_ensure_response( $payload );
	}
}
