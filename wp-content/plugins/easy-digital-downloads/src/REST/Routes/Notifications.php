<?php
/**
 * Notifications REST Routes
 *
 * Registers REST API routes for notification management.
 *
 * @package     EDD\REST\Routes
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.6
 */

namespace EDD\REST\Routes;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\REST\Controllers\Notifications as Controller;

/**
 * Notifications class
 *
 * Handles REST API route registration for notification operations.
 *
 * @since 3.6.6
 */
class Notifications extends Route {

	/**
	 * REST API base.
	 *
	 * @since 3.6.6
	 * @var string
	 */
	const BASE = 'notifications';

	/**
	 * Allowed notification sources for filtering.
	 *
	 * @since 3.6.6
	 * @var string[]
	 */
	const ALLOWED_SOURCES = array( 'api', 'local' );

	/**
	 * Allowed notification types for filtering.
	 *
	 * @since 3.6.6
	 * @var string[]
	 */
	const ALLOWED_TYPES = array( 'success', 'warning', 'error', 'info' );

	/**
	 * Constructor.
	 *
	 * @since 3.6.6
	 */
	public function __construct() {
		$this->controller = new Controller();
	}

	/**
	 * Register routes.
	 *
	 * @since 3.6.6
	 * @return void
	 */
	public function register() {
		// List notifications endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::$version . '/' . self::BASE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->controller, 'list_notifications' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'dismissed' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 0,
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							if ( ! in_array( absint( $value ), array( 0, 1 ), true ) ) {
								return new \WP_Error(
									'rest_invalid_param',
									__( 'Dismissed must be 0 or 1.', 'easy-digital-downloads' ),
									array( 'status' => 400 )
								);
							}
							return true;
						},
						'description'       => __( 'Filter by dismissed status.', 'easy-digital-downloads' ),
					),
					'source'    => array(
						'required'          => false,
						'type'              => 'string',
						'enum'              => self::ALLOWED_SOURCES,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $value ) {
							if ( ! in_array( sanitize_key( $value ), self::ALLOWED_SOURCES, true ) ) {
								return new \WP_Error(
									'rest_invalid_param',
									sprintf(
										/* translators: %s: comma-separated list of valid source values. */
										__( 'Source must be one of: %s.', 'easy-digital-downloads' ),
										implode( ', ', self::ALLOWED_SOURCES )
									),
									array( 'status' => 400 )
								);
							}
							return true;
						},
						'description'       => __( 'Filter by notification source.', 'easy-digital-downloads' ),
					),
					'type'      => array(
						'required'          => false,
						'type'              => 'string',
						'enum'              => self::ALLOWED_TYPES,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $value ) {
							if ( ! in_array( sanitize_key( $value ), self::ALLOWED_TYPES, true ) ) {
								return new \WP_Error(
									'rest_invalid_param',
									sprintf(
										/* translators: %s: comma-separated list of valid type values. */
										__( 'Type must be one of: %s.', 'easy-digital-downloads' ),
										implode( ', ', self::ALLOWED_TYPES )
									),
									array( 'status' => 400 )
								);
							}
							return true;
						},
						'description'       => __( 'Filter by notification type.', 'easy-digital-downloads' ),
					),
				),
			)
		);

		// Dismiss notification endpoint.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::$version . '/' . self::BASE . '/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this->controller, 'dismiss_notification' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'id' => array(
						'description'       => __( 'ID of the notification.', 'easy-digital-downloads' ),
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => function ( $param ) {
							$notification = EDD()->notifications->get( intval( $param ) );

							return ! empty( $notification );
						},
						'sanitize_callback' => function ( $param ) {
							return intval( $param );
						},
					),
				),
			)
		);
	}

	/**
	 * Check permission for notification operations.
	 *
	 * Uses standard WordPress REST authentication (cookies + nonce).
	 *
	 * @since 3.6.6
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_permission( $request ) {
		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}
}
