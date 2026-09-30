<?php
/**
 * Checkout Templates REST Routes
 *
 * @package     EDD\REST\Routes
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.0
 */

namespace EDD\REST\Routes;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\REST\Controllers\CheckoutTemplates as Controller;
use EDD\Checkout\Templates\Config\Constants;

/**
 * CheckoutTemplates Route class
 *
 * Handles REST API route registration for checkout template browsing.
 *
 * @since 3.7.0
 */
class CheckoutTemplates extends Route {

	/**
	 * REST API base.
	 *
	 * @since 3.7.0
	 * @var string
	 */
	const BASE = 'checkout-templates';

	/**
	 * Constructor.
	 *
	 * @since 3.7.0
	 */
	public function __construct() {
		$this->controller = new Controller();
	}

	/**
	 * Register routes.
	 *
	 * @since 3.7.0
	 * @return void
	 */
	public function register() {
		// GET /edd/v3/checkout-templates - List all templates.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::$version . '/' . self::BASE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->controller, 'get_templates' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		// Lite-only stub for the import route so non-Pro users get a clear
		// upgrade message instead of a generic 404. Pro users get the real
		// route registered by the Pro Route class.
		if ( ! edd_is_pro() ) {
			register_rest_route(
				self::NAMESPACE,
				'/' . self::$version . '/' . self::BASE . '/(?P<id>[\w-]+)/import',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_requires_pro' ),
					'permission_callback' => array( $this, 'check_permission' ),
				)
			);

			// Lite-only stub for the restore route so non-Pro users get a clear
			// upgrade message instead of a generic 404. Pro users get the real
			// route registered by the Pro Route class.
			register_rest_route(
				self::NAMESPACE,
				'/' . self::$version . '/' . self::BASE . '/restore',
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'restore_requires_pro' ),
					'permission_callback' => array( $this, 'check_permission' ),
				)
			);
		}
	}

	/**
	 * Check permission for checkout template operations.
	 *
	 * @since 3.7.0
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_permission( \WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'easy-digital-downloads' ), array( 'status' => 403 ) );
		}

		if ( ! current_user_can( 'manage_shop_settings' ) ) {
			return new \WP_Error(
				'edd_rest_forbidden',
				__( 'You do not have permission to manage checkout templates.', 'easy-digital-downloads' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}

	/**
	 * Callback for the Lite import stub.
	 *
	 * @since 3.7.0
	 * @return \WP_Error Upgrade-required error.
	 */
	public function import_requires_pro() {
		return new \WP_Error(
			'pro_required',
			__( 'Importing checkout templates requires EDD Pro. Please upgrade to unlock this feature.', 'easy-digital-downloads' ),
			array(
				'status'      => 403,
				'upgrade_url' => edd_link_helper(
					Constants::UPGRADE_URL,
					array(
						'utm_medium'  => 'checkout-templates',
						'utm_content' => 'import-requires-pro',
					)
				),
			)
		);
	}

	/**
	 * Callback for the Lite restore stub.
	 *
	 * @since 3.7.0
	 * @return \WP_Error Upgrade-required error.
	 */
	public function restore_requires_pro() {
		return new \WP_Error(
			'pro_required',
			__( 'Restoring checkout pages requires EDD Pro. Please upgrade to unlock this feature.', 'easy-digital-downloads' ),
			array(
				'status'      => 403,
				'upgrade_url' => edd_link_helper(
					Constants::UPGRADE_URL,
					array(
						'utm_medium'  => 'checkout-templates',
						'utm_content' => 'restore-requires-pro',
					)
				),
			)
		);
	}
}
