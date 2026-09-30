<?php
namespace ElementsKit_Lite\Mcp;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the ElementsKit MCP server with the WordPress MCP adapter.
 *
 * This exposes the ElementsKit abilities as MCP tools at /wp-json/elementskit/mcp
 * for external MCP clients. It is entirely optional: when the MCP adapter library
 * (WP\MCP\Core\McpAdapter) is not installed the hook simply never fires and the
 * abilities + REST proxy continue to work on their own.
 *
 * @since 3.10.x
 */
class Server {

	public function hooks() {
		add_action( 'mcp_adapter_init', [ $this, 'register_server' ] );
	}

	/**
	 * @param mixed $adapter The MCP adapter instance passed by mcp_adapter_init.
	 */
	public function register_server( $adapter ) {
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) || ! $adapter instanceof \WP\MCP\Core\McpAdapter ) {
			return;
		}

		$result = $adapter->create_server(
			'elementskit-mcp-server',
			'elementskit',
			'mcp',
			'ElementsKit MCP',
			__( 'Read and modify ElementsKit widgets and templates for Elementor.', 'elementskit-lite' ),
			'v1.0.0',
			[ \WP\MCP\Transport\HttpTransport::class ],
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			Abilities::ability_ids(),
			[],
			[]
		);

		if ( is_wp_error( $result ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[ElementsKit MCP] Server registration failed: %s', $result->get_error_message() ) );
		}
	}
}
