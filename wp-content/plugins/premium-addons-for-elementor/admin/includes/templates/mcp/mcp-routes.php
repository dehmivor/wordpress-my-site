<?php
/**
 * Your AI client connection — rendered when another MCP server on this site
 * already carries Premium Addons abilities.
 *
 * Runs in ai-abilities.php's scope and reads $routes and $pa_connected from
 * it. Ends with the Premium Addons MCP setup fold, closed, so the direct
 * route stays one click away.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$route_count = count( $routes ) + ( $pa_connected ? 1 : 0 );
?>

<section class="pa-mcp-routes" aria-labelledby="pa-mcp-routes-title">

	<h3 id="pa-mcp-routes-title" class="pa-mcp-routes-title"><?php esc_html_e( 'Your AI client connection', 'premium-addons-for-elementor' ); ?></h3>

	<p class="pa-mcp-routes-intro">
		<?php
		echo esc_html(
			_n(
				'Premium Addons abilities are already available through the MCP server below. Nothing else to set up.',
				'Premium Addons abilities are already available through the MCP servers below. Nothing else to set up.',
				$route_count,
				'premium-addons-for-elementor'
			)
		);
		?>
	</p>

	<ul class="pa-mcp-route-list">

		<?php if ( $pa_connected ) : ?>
			<li class="pa-mcp-route is-self">
				<div class="pa-mcp-route-meta">
					<span class="pa-mcp-route-name"><?php esc_html_e( 'Premium Addons MCP', 'premium-addons-for-elementor' ); ?></span>
					<span class="pa-mcp-status-pill is-connected"><?php esc_html_e( 'Connected', 'premium-addons-for-elementor' ); ?></span>
				</div>
			</li>
		<?php endif; ?>

		<?php foreach ( $routes as $route ) : ?>
			<li class="pa-mcp-route">
				<div class="pa-mcp-route-meta">
					<span class="pa-mcp-route-name"><?php echo esc_html( $route['label'] ); ?></span>
					<span class="pa-mcp-status-pill is-connected"><?php esc_html_e( 'Connected', 'premium-addons-for-elementor' ); ?></span>
				</div>

				<a href="<?php echo esc_url( $route['config_url'] ); ?>">
					<?php
					printf(
						/* translators: %s: MCP server name, e.g. Elementor MCP. */
						esc_html__( 'Go to %s Config', 'premium-addons-for-elementor' ),
						esc_html( $route['label'] )
					);
					?>
				</a>
			</li>
		<?php endforeach; ?>

	</ul>

	<?php require PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-server-fold.php'; ?>

</section>
