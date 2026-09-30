<?php
/**
 * OAuth setup instructions for one AI client.
 *
 * Included by mcp-client-tabs.php with $config, $client_key and $panel_prefix in
 * scope. Renders whichever shape the client uses — a terminal command, the
 * client's own Connectors UI, a config-file snippet, an ordered walkthrough, or
 * the bare endpoint URL. There is no credential in any of them: the browser
 * sign-in supplies the token.
 *
 * @package PremiumAddons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PremiumAddons\Admin\Includes\MCP_Settings;

$setup     = $config['oauth'];
$signin_of = __( 'The next time this client connects, your browser opens so you can approve it. Approve once and it stays connected.', 'premium-addons-for-elementor' );
?>

								<?php if ( ! empty( $setup['deeplink'] ) ) : ?>
									<p>
										<a class="button button-primary pa-mcp-deeplink" href="<?php echo esc_url( $setup['deeplink'], array( 'cursor', 'https' ) ); ?>"<?php echo 0 === strpos( $setup['deeplink'], 'https' ) ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>>
											<?php esc_html_e( 'Add it automatically', 'premium-addons-for-elementor' ); ?>
										</a>
									</p>
									<p class="description pa-mcp-hint"><?php esc_html_e( 'Opens the client with the connection already filled in. The manual steps below do the same thing.', 'premium-addons-for-elementor' ); ?></p>
								<?php endif; ?>

								<?php if ( ! empty( $setup['note'] ) ) : ?>
									<p class="description pa-mcp-hint"><?php echo esc_html( $setup['note'] ); ?></p>
								<?php endif; ?>

								<?php if ( ! empty( $setup['docs'] ) ) : ?>
									<p class="description pa-mcp-hint pa-mcp-docs-link">
										<?php esc_html_e( 'Prefer a walkthrough with screenshots?', 'premium-addons-for-elementor' ); ?>
										<a href="<?php echo esc_url( $setup['docs'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Read the step-by-step guide', 'premium-addons-for-elementor' ); ?></a>
									</p>
								<?php endif; ?>

								<?php
								$blocks = array();

								if ( 'cmd' === $setup['type'] ) {
									$blocks[] = array(
										'title' => __( 'Run this in your terminal', 'premium-addons-for-elementor' ),
										'copy'  => $setup['cmd'],
									);
									$blocks[] = array(
										'title' => __( 'Start Claude Code and run /mcp to sign in', 'premium-addons-for-elementor' ),
										'desc'  => __( 'Pick the connection in the /mcp panel — your browser opens so you can approve it.', 'premium-addons-for-elementor' ),
									);
									$blocks[] = array(
										'title' => __( 'Check it worked', 'premium-addons-for-elementor' ),
										'desc'  => __( 'The same /mcp panel shows the connection with its tools once it is approved.', 'premium-addons-for-elementor' ),
									);
								} elseif ( 'connector' === $setup['type'] ) {
									$blocks[] = array(
										'title' => sprintf(
											/* translators: %s: AI client name. */
											__( 'In %s, open Settings and go to Connectors', 'premium-addons-for-elementor' ),
											$setup['app']
										),
										'desc'  => '',
										'copy'  => '',
									);
									$blocks[] = array(
										'title' => __( 'Add a custom connector with this name', 'premium-addons-for-elementor' ),
										'desc'  => '',
										'copy'  => MCP_Settings::NAME_TOKEN,
									);
									$blocks[] = array(
										'title' => __( 'Paste this server URL and save', 'premium-addons-for-elementor' ),
										'desc'  => __( 'Leave the OAuth Client ID and Client Secret empty. Your browser opens so you can approve the connection.', 'premium-addons-for-elementor' ),
										'copy'  => $mcp_endpoint,
									);
									$blocks[] = array(
										'title' => __( 'Check it worked', 'premium-addons-for-elementor' ),
										'desc'  => __( 'The connection appears in that Connectors list, and its tools show under the + button in the chat box.', 'premium-addons-for-elementor' ),
									);
								} elseif ( 'config' === $setup['type'] ) {
									$blocks[] = array(
										'title' => sprintf(
											/* translators: %s: one or more configuration file paths. */
											__( 'Add this to %s', 'premium-addons-for-elementor' ),
											implode( __( ' or ', 'premium-addons-for-elementor' ), $setup['paths'] )
										),
										'desc'  => '',
										'copy'  => $setup['template'],
									);
									$blocks[] = array(
										'title' => __( 'Approve the connection', 'premium-addons-for-elementor' ),
										'desc'  => $signin_of,
										'copy'  => '',
									);
								} elseif ( 'steps' === $setup['type'] ) {
									$blocks = $setup['steps'];
								} else {
									$blocks[] = array(
										'title' => __( 'Add this site as a Streamable HTTP MCP server', 'premium-addons-for-elementor' ),
										'desc'  => __( 'Use this URL. There is no key or header to enter.', 'premium-addons-for-elementor' ),
										'copy'  => $mcp_endpoint,
									);
									$blocks[] = array(
										'title' => __( 'Approve the connection', 'premium-addons-for-elementor' ),
										'desc'  => $signin_of,
										'copy'  => '',
									);
								}
								?>

								<?php
								$steps_blocks    = $blocks;
								$steps_id_prefix = $panel_prefix . '-oauth-' . $client_key;
								require PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-steps.php';
								?>
