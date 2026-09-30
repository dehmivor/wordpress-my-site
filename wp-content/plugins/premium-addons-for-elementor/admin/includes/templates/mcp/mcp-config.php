<?php
/**
 * Premium Addons MCP setup — body of the setup fold on the AI Abilities tab.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PremiumAddons\Admin\Includes\MCP_Settings;
use PremiumAddons\Includes\Abilities\Bootstrap as Abilities_Bootstrap;
use PremiumAddons\Includes\Abilities\OAuth;

$pw_status         = MCP_Settings::app_passwords_status();
$mcp_endpoint      = rest_url( Abilities_Bootstrap::server_route() );
$mcp_endpoint_host = MCP_Settings::get_endpoint_host( $mcp_endpoint );
$mcp_scheme        = MCP_Settings::endpoint_scheme( $mcp_endpoint );
$mcp_is_local      = MCP_Settings::is_local_host( $mcp_endpoint_host );

// OAuth connect method. The snippets embed no secret, so the OAuth branch is
// rendered on every load and the chooser only switches visibility; enabling is
// an AJAX opt-in that creates the tables on the way.
$oauth_reason    = OAuth\Bootstrap::unavailable_reason();
$oauth_available = '' === $oauth_reason;

// Keep the form on this tab after submitting (tabs are routed by URL hash).
$form_action = esc_url( admin_url( 'admin.php?page=' . self::$page_slug . '#tab=ai-abilities' ) );

// A client this user already connected turns the setup steps into a reference
// they open on purpose, instead of a wall of instructions on every visit.
// $mcp, $pa_connected, $oauth_enabled, $new_password and $password_error come
// from ai-abilities.php, which includes this file into its scope.
$setup_steps = PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-setup-steps.php';
$client_tabs = PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-client-tabs.php';
?>

		<div class="pa-mcp-method-chooser">
			<p class="pa-mcp-field-label"><?php esc_html_e( 'Connection method', 'premium-addons-for-elementor' ); ?></p>

			<?php
			// Both methods keep working at once, so the chooser is a view preference,
			// not a reflection of which one the site has enabled. Nothing is selected
			// on a first visit — both branches stay hidden until the admin picks one —
			// and the script restores this admin's last pick on later visits.
			?>
			<div class="pa-mcp-method-cards">
				<label class="pa-mcp-method-card<?php echo esc_attr( $oauth_available ? '' : ' is-locked' ); ?>">
					<input type="radio" name="pa-mcp-method" value="oauth" <?php disabled( ! $oauth_available ); ?> data-pa-oauth-enabled="<?php echo esc_attr( $oauth_enabled ? '1' : '0' ); ?>">
					<span class="pa-mcp-method-title">
						<?php esc_html_e( 'OAuth', 'premium-addons-for-elementor' ); ?>
						<?php if ( $oauth_available ) : ?>
							<span class="pa-mcp-method-badge"><?php esc_html_e( 'Recommended', 'premium-addons-for-elementor' ); ?></span>
						<?php endif; ?>
					</span>
					<span class="pa-mcp-method-desc"><?php esc_html_e( 'Approve the connection in your browser — no secret to copy, and access tokens expire automatically.', 'premium-addons-for-elementor' ); ?></span>
					<?php if ( ! $oauth_available ) : ?>
						<span class="pa-mcp-method-desc"><strong><?php echo esc_html( $oauth_reason ); ?></strong></span>
					<?php endif; ?>
				</label>

				<label class="pa-mcp-method-card">
					<input type="radio" name="pa-mcp-method" value="password">
					<span class="pa-mcp-method-title"><?php esc_html_e( 'Application Password', 'premium-addons-for-elementor' ); ?></span>
					<span class="pa-mcp-method-desc"><?php esc_html_e( 'Generate an application password here and paste the configuration into your client.', 'premium-addons-for-elementor' ); ?></span>
				</label>
			</div>

			<div class="pa-mcp-method-status" role="status"></div>
		</div>

		<div id="pa-mcp-branch-password" class="pa-mcp-branch" hidden>

			<?php if ( $pa_connected ) : ?>
				<?php // Stays open after a submission so the form's own result is not hidden. ?>
				<details class="pa-mcp-setup-fold"<?php echo null !== $new_password || null !== $password_error ? ' open' : ''; ?>>
					<summary><?php esc_html_e( 'Connect another client or reconnect', 'premium-addons-for-elementor' ); ?></summary>
					<?php include $setup_steps; ?>
				</details>
			<?php else : ?>
				<?php include $setup_steps; ?>
			<?php endif; ?>

			<?php
			// "Connect Your AI Client" — shown only right after a password is created,
			// never on a normal page load, since the connection details embed the secret.
			$connect_password = $new_password;

			if ( null !== $connect_password ) :
				$mcp_username = wp_get_current_user()->user_login;
				$configs      = $mcp->build_client_configs( $mcp_endpoint, $mcp_username, $connect_password );
				$tabs_prefix  = 'pw';
				?>

				<hr class="pa-mcp-divider">

				<div class="pa-mcp-connect">

					<h4 class="pa-mcp-step-heading">
						<?php esc_html_e( 'Connect Your AI Client', 'premium-addons-for-elementor' ); ?>
					</h4>

					<p class="pa-mcp-step-desc">
						<?php esc_html_e( 'Pick your AI client and copy its configuration or click the prompt below it.', 'premium-addons-for-elementor' ); ?>
					</p>

					<?php include $client_tabs; ?>

					<p class="description pa-mcp-connect-note">
						<?php esc_html_e( 'These connection details contain your application password. Treat them like a password: the config file stores the credential, and anyone with it can act on your site as you. If it is exposed, revoke it under Manage Connections below.', 'premium-addons-for-elementor' ); ?>
					</p>

				</div>
			<?php endif; ?>
		</div>

		<?php if ( $oauth_available ) : ?>
			<div id="pa-mcp-branch-oauth" class="pa-mcp-branch" hidden>

				<div class="pa-mcp-connect">

					<p class="pa-mcp-step-desc">
						<?php esc_html_e( 'Pick your AI client and copy its configuration.', 'premium-addons-for-elementor' ); ?>
					</p>

					<?php
					$configs     = $mcp->build_client_configs( $mcp_endpoint );
					$tabs_prefix = 'oauth';
					include $client_tabs;
					?>

					<p class="description pa-mcp-connect-note">
						<?php esc_html_e( 'These connection details contain no secret. Each client registers itself and gets its own access token when you approve it in the browser.', 'premium-addons-for-elementor' ); ?>
					</p>

				</div>

			</div>
		<?php endif; ?>

		<?php // Last on purpose: a first-time visitor meets the method and their client before a diagnostic. ?>
		<?php require PREMIUM_ADDONS_PATH . 'admin/includes/templates/mcp/mcp-connection-check.php'; ?>
