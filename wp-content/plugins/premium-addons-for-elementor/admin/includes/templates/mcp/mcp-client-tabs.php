<?php
/**
 * AI-client picker and config panels.
 *
 * Included from mcp-config.php into its scope, once per connection method.
 * Expects from that scope:
 * - $configs      Client configuration map from MCP_Settings::build_client_configs().
 * - $tabs_prefix  Branch id prefix (e.g. 'pw', 'oauth') — both branches live in
 *                 the DOM at once, so every element id must be branch-unique.
 * - $mcp_is_local / $mcp_scheme for the bridge TLS note.
 *
 * Two levels: three group cards, each opening into its clients. No panel
 * shows until a group is opened; opening one selects its first client.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PremiumAddons\Admin\Includes\MCP_Settings;

// Read by every mcp-copy-block.php include below, directly and through
// mcp-oauth-setup.php, which this file includes into its own scope.
$copy_alias = MCP_Settings::default_server_name();

$client_groups = MCP_Settings::grouped_clients( $configs );
$group_labels  = MCP_Settings::group_labels();
$client_kinds  = MCP_Settings::client_kinds();
$panel_prefix  = 'pa-mcp-' . $tabs_prefix;

// Wordmark logo, or a placeholder square when none ships. Decorative: the
// card name stays in the markup as the accessible label.
$render_logo = static function ( $logo, $mark ) {
	if ( '' === $logo ) {
		echo '<span class="pa-mcp-client-logo is-placeholder" aria-hidden="true">' . esc_html( $mark ) . '</span>';
		return;
	}

	$allowed_svg = array(
		'svg'  => array(
			'viewbox' => true,
			'fill'    => true,
		),
		'path' => array(
			'd'    => true,
			'fill' => true,
		),
	);

	echo '<span class="pa-mcp-client-logo" aria-hidden="true">' . wp_kses( $logo, $allowed_svg ) . '</span>';
};
?>

				<div class="pa-mcp-client-picker">
					<p class="pa-mcp-field-label"><?php esc_html_e( 'Your AI client', 'premium-addons-for-elementor' ); ?></p>

					<div class="pa-mcp-client-groups">
						<?php foreach ( $client_groups as $group_slug => $group_clients ) : ?>
							<?php $group_logo = MCP_Settings::client_logo( $group_slug ); ?>
							<button type="button" class="pa-mcp-group<?php echo '' !== $group_logo ? ' has-logo' : ''; ?>" data-pa-mcp-group="<?php echo esc_attr( $group_slug ); ?>">
								<?php $render_logo( $group_logo, $group_labels[ $group_slug ]['mark'] ); ?>
								<span class="pa-mcp-card-name">
									<span<?php echo '' !== $group_logo ? ' class="screen-reader-text"' : ''; ?>><?php echo esc_html( $group_labels[ $group_slug ]['label'] ); ?></span>
									<small><?php echo esc_html( $group_labels[ $group_slug ]['desc'] ); ?></small>
								</span>
							</button>
						<?php endforeach; ?>
					</div>

					<?php foreach ( $client_groups as $group_slug => $group_clients ) : ?>
						<?php
						// A vendor group's clients would all repeat the group's logo, so they stay text-only.
						$group_has_logo = '' !== MCP_Settings::client_logo( $group_slug );
						?>
						<div class="pa-mcp-client-cards" data-pa-mcp-group="<?php echo esc_attr( $group_slug ); ?>" hidden>
							<button type="button" class="pa-mcp-clients-back"><?php esc_html_e( 'Go back', 'premium-addons-for-elementor' ); ?></button>

							<div class="pa-mcp-client-grid" role="group" aria-label="<?php esc_attr_e( 'Choose your AI client', 'premium-addons-for-elementor' ); ?>">
								<?php foreach ( $group_clients as $client_key => $config ) : ?>
									<?php $client_logo = $group_has_logo ? '' : MCP_Settings::client_logo( $client_key ); ?>
									<button type="button" class="pa-mcp-client-card<?php echo '' !== $client_logo ? ' has-logo' : ''; ?>" aria-pressed="false" data-pa-mcp-panel="<?php echo esc_attr( $panel_prefix . '-panel-' . $client_key ); ?>">
										<?php if ( ! $group_has_logo ) : ?>
											<?php $render_logo( $client_logo, ucfirst( mb_substr( (string) $config['label'], 0, 2 ) ) ); ?>
										<?php endif; ?>
										<span class="pa-mcp-card-name">
											<span<?php echo '' !== $client_logo ? ' class="screen-reader-text"' : ''; ?>><?php echo esc_html( (string) $config['label'] ); ?></span>
											<?php if ( isset( $client_kinds[ $client_key ] ) ) : ?>
												<small><?php echo esc_html( $client_kinds[ $client_key ] ); ?></small>
											<?php endif; ?>
										</span>
									</button>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="pa-mcp-client-panels">
					<?php foreach ( $configs as $client_key => $config ) : ?>
						<div class="pa-mcp-client-panel" id="<?php echo esc_attr( $panel_prefix . '-panel-' . $client_key ); ?>" hidden>
							<?php if ( ! empty( $config['oauth'] ) ) : ?>
								<?php include PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-oauth-setup.php'; ?>
							<?php elseif ( ! empty( $config['code'] ) ) : ?>
								<?php if ( ! empty( $config['steps'] ) ) : ?>
									<?php
									$steps_blocks    = $config['steps'];
									$steps_id_prefix = $panel_prefix . '-steps-' . $client_key;
									include PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-steps.php';
									?>
								<?php else : ?>
									<p class="pa-mcp-hint"><?php echo esc_html( (string) $config['hint'] ); ?></p>

									<?php
									$copy_id      = $panel_prefix . '-code-' . $client_key;
									$copy_text    = $config['code'];
									$copy_label   = __( 'Copy configuration', 'premium-addons-for-elementor' );
									$copy_primary = true;
									include PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-copy-block.php';
									?>
								<?php endif; ?>

								<?php if ( null !== $config['deeplink'] ) : ?>
									<p><a class="button pa-mcp-deeplink" href="<?php echo esc_url( (string) $config['deeplink'], array( 'cursor' ) ); ?>"><?php esc_html_e( 'Install in Cursor', 'premium-addons-for-elementor' ); ?></a></p>
									<p class="description pa-mcp-hint">
										<?php
										printf(
											/* translators: %s: connection name written into the client configuration. */
											esc_html__( 'This installs as %s; rename it later in mcp.json if needed.', 'premium-addons-for-elementor' ),
											esc_html( $copy_alias )
										);
										?>
									</p>
								<?php endif; ?>

								<?php if ( 'bridge' === $config['shape'] && $mcp_is_local && 'https' === $mcp_scheme ) : ?>
									<div class="pa-mcp-advisory pa-mcp-tls-advisory">
										<pre class="pa-mcp-tls-note" id="<?php echo esc_attr( $panel_prefix . '-tls-note-' . $client_key ); ?>"><?php esc_html_e( 'If your client rejects the certificate, trust your local CA (LocalWP/mkcert). As a last resort, you can set NODE_TLS_REJECT_UNAUTHORIZED=0 for the bridge; understand that this disables certificate checks.', 'premium-addons-for-elementor' ); ?></pre>
										<button type="button" class="button pa-mcp-copy" data-pa-mcp-copy="<?php echo esc_attr( $panel_prefix . '-tls-note-' . $client_key ); ?>" data-pa-mcp-copied="<?php esc_attr_e( 'Copied!', 'premium-addons-for-elementor' ); ?>"><?php esc_html_e( 'Copy TLS note', 'premium-addons-for-elementor' ); ?></button>
									</div>
								<?php endif; ?>

								<details class="pa-mcp-agent-prompt">
									<summary><?php esc_html_e( 'Ask your agent to configure it', 'premium-addons-for-elementor' ); ?></summary>
									<?php
									$copy_id      = $panel_prefix . '-prompt-' . $client_key;
									$copy_text    = $config['prompt'];
									$copy_label   = __( 'Copy agent prompt', 'premium-addons-for-elementor' );
									$copy_primary = false;
									include PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-copy-block.php';
									?>
								</details>
							<?php else : ?>
								<?php
								$copy_id      = $panel_prefix . '-prompt-' . $client_key;
								$copy_text    = $config['prompt'];
								$copy_label   = __( 'Copy agent prompt', 'premium-addons-for-elementor' );
								$copy_primary = true;
								include PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-copy-block.php';
								?>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
