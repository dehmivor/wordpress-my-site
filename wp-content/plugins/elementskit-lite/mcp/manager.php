<?php
namespace ElementsKit_Lite\Mcp;

use ElementsKit_Lite\Traits\Singleton;

defined( 'ABSPATH' ) || exit;

/**
 * Entry point for ElementsKit's MCP support.
 *
 * Wires together the layers described in the architecture:
 *   - shared PHP service ({@see Service}),
 *   - the public backend contract: WP Abilities ({@see Abilities}) and the
 *     optional MCP server ({@see Server}),
 *   - the editor convenience layer: the REST proxy ({@see Rest_Proxy}) and the
 *     Angie integration JS.
 *
 * The Angie integration is a browser bundle built with @elementor/angie-sdk that
 * registers an ElementsKit MCP server with Angie over postMessage. Angie loads
 * globally in wp-admin, so this is enqueued globally (not only in the Elementor
 * editor) and gated on the Angie plugin being active — the same mechanism the
 * Amelia plugin uses. Each tool forwards to the REST proxy.
 *
 * Instantiated directly from the plugin bootstrap; not a toggleable module.
 *
 * @since 3.10.x
 */
class Manager {

	use Singleton;

	const ANGIE_HANDLE = 'elementskit-angie-mcp';
	const ANGIE_PLUGIN = 'angie/angie.php';

	public function __construct() {
		( new Abilities() )->hooks();
		( new Server() )->hooks();
		( new Rest_Proxy() )->hooks();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_angie_integration' ] );
	}

	/**
	 * Register the ElementsKit MCP server with Angie, wherever Angie is available
	 * in wp-admin (dashboard and the Elementor editor alike).
	 */
	public function enqueue_angie_integration() {
		if ( ! $this->angie_active() ) {
			return;
		}

		// The Script Modules API (wp_enqueue_script_module) landed in WordPress 6.5.
		if ( ! function_exists( 'wp_enqueue_script_module' ) ) {
			return;
		}

		$path = \ElementsKit_Lite::plugin_dir() . 'mcp/assets/elementskit-angie.js';

		if ( ! file_exists( $path ) ) {
			return;
		}

		// Script modules cannot receive wp_localize_script, so hand the bundle its
		// REST endpoint + nonce through a global printed before the module runs.
		add_action( 'admin_print_scripts', [ $this, 'print_angie_config' ] );

		// Version by file mtime so every rebuild busts the browser's module cache.
		$version = \ElementsKit_Lite::version() . '.' . (string) filemtime( $path );

		wp_enqueue_script_module(
			self::ANGIE_HANDLE,
			\ElementsKit_Lite::plugin_url() . 'mcp/assets/elementskit-angie.js',
			[],
			$version
		);
	}

	/**
	 * Print the config the Angie bundle reads (window.ElementsKitMcpConfig).
	 */
	public function print_angie_config() {
		static $printed = false;

		if ( $printed ) {
			return;
		}
		$printed = true;

		$config = [
			'restUrl'  => esc_url_raw( get_rest_url( null, Rest_Proxy::NAMESPACE . Rest_Proxy::ROUTE ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'adminUrl' => esc_url_raw( admin_url() ),
		];

		printf(
			"<script>window.ElementsKitMcpConfig = %s;</script>\n",
			wp_json_encode( $config )
		);
	}

	/**
	 * @return bool Whether the Angie plugin is active.
	 */
	private function angie_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return function_exists( 'is_plugin_active' ) && is_plugin_active( self::ANGIE_PLUGIN );
	}
}
