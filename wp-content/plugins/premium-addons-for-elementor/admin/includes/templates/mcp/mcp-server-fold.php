<?php
/**
 * Premium Addons MCP setup fold — wraps mcp-config.php in a <details>.
 *
 * Card-styled and open when no other route is configured; a closed text link
 * inside the connection card otherwise. Runs in ai-abilities.php's scope and
 * reads $has_route, $fold_open and $fold_summary from it; mcp-config.php
 * reads the rest of that scope through this include.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<details class="pa-mcp-server-fold <?php echo esc_attr( $has_route ? 'is-link' : 'is-card' ); ?>" id="pa-mcp-server"<?php echo $fold_open ? ' open' : ''; ?>>
	<summary><?php echo esc_html( $fold_summary ); ?></summary>

	<div class="pa-mcp-server-body">
		<?php require PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-config.php'; ?>
	</div>
</details>
