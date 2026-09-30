<?php
/**
 * Unbranded Card REST Routes
 *
 * Registers REST API routes for PayPal Advanced Card Processing (unbranded,
 * on-checkout card fields).
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
use EDD\REST\Controllers\UnbrandedCard as Controller;

/**
 * UnbrandedCard class.
 *
 * @since 3.6.9
 */
class UnbrandedCard extends Route {

	/**
	 * REST API base.
	 *
	 * @since 3.6.9
	 * @var string
	 */
	const BASE = 'unbranded-card';

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
		$base = '/' . self::$version . '/' . self::BASE;

		// Create the EDD + PayPal order for the Card Fields component.
		register_rest_route(
			self::NAMESPACE,
			$base . '/create',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->controller, 'create' ),
				'permission_callback' => array( $this->security, 'validate_cart_token' ),
			)
		);

		// Verify 3DS liability and capture after the buyer clears the challenge.
		register_rest_route(
			self::NAMESPACE,
			$base . '/capture',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( $this->controller, 'capture' ),
				'permission_callback' => array( $this->security, 'validate_cart_token' ),
			)
		);
	}
}
