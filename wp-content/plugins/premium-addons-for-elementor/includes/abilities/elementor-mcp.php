<?php
/**
 * Elementor MCP bridge.
 *
 * Elementor MCP (the `elementor/elementor-mcp-composer` package bundled with
 * Elementor) serves one unified server at /wp-json/elementor/mcp/. Its tool
 * list is an explicit slug registry that third parties extend only through
 * the `elementor/mcp/server/*` filters; it does not scan wp_get_abilities()
 * and ignores `meta.mcp.public`. This bridge appends the enabled Premium
 * Addons abilities to that list, so an AI client connected through Elementor
 * MCP can call them without also configuring Premium Addons MCP.
 *
 * Execution is unchanged: the adapter runs the same WP_Ability objects the
 * Premium Addons server exposes, with their own permission callbacks and Pro
 * gating. Authentication is Elementor's (an Application Password).
 *
 * @package PremiumAddons\Includes\Abilities
 */

namespace PremiumAddons\Includes\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Elementor_MCP
 *
 * @since 4.11.104
 */
class Elementor_MCP {

	/**
	 * Elementor MCP filter that appends tool ability slugs to the unified server.
	 *
	 * @var string
	 */
	const TOOLS_FILTER = 'elementor/mcp/server/tools';

	/**
	 * Hook the bridge.
	 *
	 * Hooked whether or not Elementor MCP is present: the filter never fires
	 * without it, so there is nothing to detect.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( self::TOOLS_FILTER, array( __CLASS__, 'add_tools' ) );
	}

	/**
	 * Append the enabled Premium Addons abilities to Elementor MCP's tool list.
	 *
	 * Elementor applies the filter inside its registry getters, which run on
	 * `mcp_adapter_init` (after init), so the abilities are registered by the
	 * time this runs. Angie pushes third-party abilities into the same registry
	 * on its own; Elementor de-duplicates, so the two sources may overlap.
	 *
	 * @param mixed $tools Ability slugs collected so far.
	 * @return array
	 */
	public static function add_tools( $tools ) {
		$tools = is_array( $tools ) ? $tools : array();

		// wp_get_abilities() is not usable before init.
		if ( ! did_action( 'init' ) ) {
			return $tools;
		}

		$names = Bootstrap::get_instance()->get_server_tool_names();

		/**
		 * Filters the Premium Addons abilities exposed on Elementor MCP.
		 *
		 * @since 4.11.104
		 *
		 * @param string[] $names Ability names, e.g. 'premium-addons/insert-widget'.
		 */
		$names = apply_filters( 'pa_elementor_mcp_tools', $names );

		if ( ! is_array( $names ) || empty( $names ) ) {
			return $tools;
		}

		return array_values( array_unique( array_merge( $tools, array_filter( $names, 'is_string' ) ) ) );
	}
}
