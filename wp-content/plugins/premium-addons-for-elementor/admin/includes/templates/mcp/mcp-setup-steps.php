<?php
/**
 * Generate an application password — included by mcp-config.php.
 *
 * Split out so the form can be rendered plainly for a user who never connected
 * and folded inside a <details> for one who did, without either tag being opened
 * in one conditional and closed in another.
 *
 * Runs in mcp-config.php's scope and reads $pw_status, $form_action,
 * $password_error and $new_password from it.
 */

use PremiumAddons\Admin\Includes\MCP_Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

		<h4 class="pa-mcp-step-heading">
			<?php esc_html_e( 'Create an Application Password', 'premium-addons-for-elementor' ); ?>
		</h4>

		<?php if ( ! $pw_status['available'] ) : ?>
			<div class="notice notice-error inline">
				<p><strong><?php echo esc_html( $pw_status['message'] ); ?></strong></p>
			</div>
		<?php else : ?>

			<p class="pa-mcp-step-desc">
				<?php esc_html_e( 'Creates an application password for your account. It is shown once, inside the connection details below.', 'premium-addons-for-elementor' ); ?>
			</p>

			<form method="post" class="pa-mcp-password-form" action="<?php echo $form_action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Already escaped above. ?>">
				<?php wp_nonce_field( 'pa_mcp_generate_password' ); ?>
				<input type="hidden" name="pa_mcp_generate_token" value="<?php echo esc_attr( MCP_Settings::issue_form_token() ); ?>">
				<?php // The trigger is a hidden field, not the button's name: the script disables the button on submit, and a disabled button is left out of the POST body. ?>
				<input type="hidden" name="pa_mcp_generate_password" value="1">
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Generate password', 'premium-addons-for-elementor' ); ?>
				</button>
				<?php if ( null !== $password_error ) : ?>
					<div class="notice notice-error inline">
						<p><?php echo esc_html( $password_error->get_error_message() ); ?></p>
					</div>
				<?php elseif ( null !== $new_password ) : ?>
					<div class="notice notice-success inline">
						<p><?php esc_html_e( 'Password created. Connection details are below and shown only once.', 'premium-addons-for-elementor' ); ?></p>
					</div>
				<?php endif; ?>
			</form>
		<?php endif; ?>
