<?php
/**
 * Connection check — last block of the Premium Addons MCP setup.
 *
 * Nothing runs on page load: the button asks the server for the rows and
 * the script renders them (see MCP_Settings::run_connection_check()).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

		<div class="pa-mcp-check">
			<div class="pa-mcp-check-head">
				<div>
					<p class="pa-mcp-field-label"><?php esc_html_e( 'Connection not working?', 'premium-addons-for-elementor' ); ?></p>
					<p class="pa-mcp-check-desc"><?php esc_html_e( 'Run a check on what usually blocks a connection: HTTPS, permalinks, application passwords, and whether your host lets AI clients reach this site.', 'premium-addons-for-elementor' ); ?></p>
				</div>
				<button type="button" class="button pa-mcp-check-run"><?php esc_html_e( 'Run connection check', 'premium-addons-for-elementor' ); ?></button>
			</div>

			<ul class="pa-mcp-check-results" role="status" hidden></ul>
		</div>
