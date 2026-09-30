<?php
/**
 * Manage Connections — the viewing user's Premium Addons MCP connections, one
 * Revoke per row. Rendered only when there is at least one row.
 *
 * Runs in ai-abilities.php's scope and reads $connections from it (see
 * Connection_Log::get_connections()). Revoking goes through the
 * pa_mcp_revoke_connection AJAX action.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$kind_labels = array(
	'password' => __( 'Application Password', 'premium-addons-for-elementor' ),
	'oauth'    => __( 'OAuth', 'premium-addons-for-elementor' ),
);
?>

					<div class="pa-ai-accordion-item" id="pa-mcp-connections">
						<h3 class="pa-ai-accordion-title">
							<button type="button" class="pa-ai-accordion-toggle" aria-expanded="false" aria-controls="pa-ai-panel-connections">
								<span class="pa-ai-accordion-icon" aria-hidden="true"></span>
								<?php esc_html_e( 'Manage Connections', 'premium-addons-for-elementor' ); ?>
							</button>
						</h3>

						<div id="pa-ai-panel-connections" class="pa-ai-accordion-body" hidden>

							<p class="pa-mcp-step-desc">
								<?php esc_html_e( 'AI clients connected to Premium Addons MCP with your account. Revoking a connection blocks that client right away.', 'premium-addons-for-elementor' ); ?>
							</p>

							<ul class="pa-mcp-connection-list">

								<?php foreach ( $connections as $connection ) : ?>

									<?php
									$created = wp_date( $date_format, $connection['created'] );

									if ( 'oauth' === $connection['kind'] ) {
										/* translators: %s: date and time the OAuth grant expires. */
										$usage = sprintf( __( 'Expires %s', 'premium-addons-for-elementor' ), wp_date( $date_format, $connection['expires'] ) );
									} elseif ( null !== $connection['last_used'] ) {
										/* translators: %s: date and time the application password was last used. */
										$usage = sprintf( __( 'Last used %s', 'premium-addons-for-elementor' ), wp_date( $date_format, $connection['last_used'] ) );
									} else {
										$usage = __( 'Never used', 'premium-addons-for-elementor' );
									}
									?>

									<li class="pa-mcp-connection" data-pa-kind="<?php echo esc_attr( $connection['kind'] ); ?>" data-pa-id="<?php echo esc_attr( $connection['id'] ); ?>">
										<div class="pa-mcp-connection-meta">
											<div class="pa-mcp-connection-head">
												<span class="pa-mcp-connection-name"><?php echo esc_html( $connection['name'] ); ?></span>
												<span class="pa-mcp-connection-badge"><?php echo esc_html( $kind_labels[ $connection['kind'] ] ); ?></span>
											</div>
											<p class="pa-mcp-connection-dates">
												<?php
												printf(
													/* translators: %s: date and time the connection was created. */
													esc_html__( 'Created %s', 'premium-addons-for-elementor' ),
													esc_html( $created )
												);
												?>
												<span class="pa-mcp-connection-sep" aria-hidden="true">·</span>
												<?php echo esc_html( $usage ); ?>
											</p>
										</div>

										<button type="button" class="button pa-mcp-revoke">
											<?php esc_html_e( 'Revoke', 'premium-addons-for-elementor' ); ?>
										</button>
									</li>

								<?php endforeach; ?>

							</ul>

						</div>
					</div>
