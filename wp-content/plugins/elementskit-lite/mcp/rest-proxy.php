<?php
namespace ElementsKit_Lite\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * REST proxy for the Angie editor tools.
 *
 * Exposes a single endpoint — POST /wp-json/elementskit/v1/mcp-proxy — that the
 * editor-side Angie tools call. It validates the requested tool and input,
 * enforces permissions and delegates to the shared {@see Service}. No insertion
 * or template logic lives here; this is purely a dispatcher.
 *
 * Request body:
 *   { "tool": "insert-widget", "input": { ... } }
 *
 * @since 3.10.x
 */
class Rest_Proxy {

	const NAMESPACE = 'elementskit/v1';
	const ROUTE     = '/mcp-proxy';

	/**
	 * Map of proxy tool names to the Service method that handles them, together
	 * with whether the tool mutates a document (which raises the capability bar).
	 *
	 * @return array<string, array{method:string, mutating:bool}>
	 */
	private function tool_map() {
		return [
			'list-widgets'            => [ 'method' => 'list_widgets', 'mutating' => false ],
			'insert-widget'           => [ 'method' => 'insert_widget', 'mutating' => true ],
			'list-templates'          => [ 'method' => 'list_templates', 'mutating' => false ],
			'insert-template'         => [ 'method' => 'insert_template', 'mutating' => true ],
			'create-page'             => [ 'method' => 'create_page', 'mutating' => true ],
			'create-template'         => [ 'method' => 'create_template', 'mutating' => true ],
			'create-header-footer'    => [ 'method' => 'create_header_footer', 'mutating' => true ],
			'list-custom-widgets'     => [ 'method' => 'list_custom_widgets', 'mutating' => false ],
			'create-custom-widget'    => [ 'method' => 'create_custom_widget', 'mutating' => true ],
			'list-mega-menus'         => [ 'method' => 'list_mega_menus', 'mutating' => false ],
			'enable-mega-menu'        => [ 'method' => 'enable_mega_menu', 'mutating' => true ],
			'update-mega-menu-item'   => [ 'method' => 'update_mega_menu_item', 'mutating' => true ],
			'create-mega-menu'        => [ 'method' => 'create_mega_menu', 'mutating' => true ],
			'list-library-templates'  => [ 'method' => 'list_library_templates', 'mutating' => false ],
			'import-library-template' => [ 'method' => 'import_library_template', 'mutating' => true ],
		];
	}

	public function hooks() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'tool'  => [
						'type'     => 'string',
						'required' => true,
					],
					'input' => [
						'type'     => 'object',
						'required' => false,
						'default'  => [],
					],
				],
			]
		);
	}

	/**
	 * Base gate: the user must at least be able to edit posts. Per-document
	 * capability checks happen inside the Service for mutating tools.
	 *
	 * @return bool
	 */
	public function permission() {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function handle( \WP_REST_Request $request ) {
		$tool  = (string) $request->get_param( 'tool' );
		$input = $request->get_param( 'input' );
		$input = is_array( $input ) ? $input : [];

		$map = $this->tool_map();

		if ( ! isset( $map[ $tool ] ) ) {
			return $this->error_response(
				'ekit_mcp_unknown_tool',
				/* translators: %s: tool name */
				sprintf( __( 'Unknown proxy tool: %s', 'elementskit-lite' ), $tool ),
				404
			);
		}

		$method = $map[ $tool ]['method'];
		$result = ( new Service() )->$method( $input );

		if ( is_wp_error( $result ) ) {
			return $this->error_from_wp_error( $result );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * @param \WP_Error $error
	 * @return \WP_REST_Response
	 */
	private function error_from_wp_error( \WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

		return $this->error_response( $error->get_error_code(), $error->get_error_message(), $status );
	}

	/**
	 * @param string $code
	 * @param string $message
	 * @param int    $status
	 * @return \WP_REST_Response
	 */
	private function error_response( $code, $message, $status ) {
		return new \WP_REST_Response(
			[
				'success' => false,
				'error'   => [
					'code'    => $code,
					'message' => $message,
				],
			],
			$status
		);
	}
}
