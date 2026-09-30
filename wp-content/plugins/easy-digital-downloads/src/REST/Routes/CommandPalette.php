<?php
/**
 * Command Palette REST Route.
 *
 * Registers the aggregated search endpoint used by the WordPress command
 * palette to search every registered EDD command palette source in one request.
 *
 * @package     EDD\REST\Routes
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.7.1
 */

namespace EDD\REST\Routes;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\REST\Controllers\CommandPalette as Controller;

/**
 * CommandPalette class.
 *
 * @since 3.7.1
 */
class CommandPalette extends Route {

	/**
	 * REST API base.
	 *
	 * @since 3.7.1
	 * @var string
	 */
	const BASE = 'command-palette/search';

	/**
	 * The shortest search term the endpoint will act on.
	 *
	 * Shared with the palette script so the client stops short of a request the
	 * endpoint would only reject.
	 *
	 * @since 3.7.1
	 * @var int
	 */
	const MIN_SEARCH_LENGTH = 2;

	/**
	 * Constructor.
	 *
	 * @since 3.7.1
	 */
	public function __construct() {
		$this->controller = new Controller();
	}

	/**
	 * Register routes.
	 *
	 * @since 3.7.1
	 * @return void
	 */
	public function register() {
		register_rest_route(
			self::NAMESPACE,
			'/' . self::$version . '/' . self::BASE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this->controller, 'search' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'search' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							// A validate_callback replaces the type check WordPress
							// would otherwise run, so reject the shape here.
							if ( ! is_string( $value ) ) {
								return new \WP_Error(
									'rest_invalid_param',
									__( 'The search term must be a string.', 'easy-digital-downloads' ),
									array( 'status' => 400 )
								);
							}

							// WordPress validates before it sanitizes, and the query
							// layer reads `*` as a wildcard, so measure what the
							// sources will actually match on.
							$term = trim( str_replace( '*', '', sanitize_text_field( $value ) ) );

							if ( mb_strlen( $term ) < self::MIN_SEARCH_LENGTH ) {
								return new \WP_Error(
									'rest_invalid_param',
									sprintf(
										/* translators: %d: the minimum number of characters a search term needs */
										__( 'The search term must be at least %d characters.', 'easy-digital-downloads' ),
										self::MIN_SEARCH_LENGTH
									),
									array( 'status' => 400 )
								);
							}
							return true;
						},
						'description'       => __( 'The search term.', 'easy-digital-downloads' ),
					),
				),
			)
		);
	}

	/**
	 * Check permission for the command palette search.
	 *
	 * The request only needs an authenticated user; each source enforces its
	 * own capability, and sources the user cannot access are skipped rather
	 * than failing the whole request.
	 *
	 * @since 3.7.1
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}

		return true;
	}
}
