<?php
/**
 * Fastlane REST Routes
 *
 * Registers REST API routes for PayPal Fastlane checkout.
 *
 * @package     EDD\REST\Routes
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\REST\Routes;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\REST\Security;
use EDD\REST\Controllers\Fastlane as Controller;

/**
 * Fastlane class.
 *
 * Handles REST API route registration for PayPal Fastlane operations.
 *
 * @since 3.6.9
 */
class Fastlane extends Route {

	/**
	 * REST API base.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	const BASE = 'fastlane';

	/**
	 * Constructor.
	 *
	 * @since 3.6.9
	 */
	public function __construct() {
		$this->security   = new Security();
		$this->controller = new Controller( $this->security );
	}

	/**
	 * Register routes.
	 *
	 * @since 3.6.9
	 * @return void
	 */
	public function register() {
		// Process a Fastlane card payment.
		register_rest_route(
			self::NAMESPACE,
			'/' . self::$version . '/' . self::BASE . '/process-payment',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->controller, 'process_payment' ),
				'permission_callback' => array( $this->security, 'validate_cart_token' ),
			)
		);
	}
}
