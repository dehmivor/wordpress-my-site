<?php
/**
 * OAuth consent screen.
 *
 * Included by OAuth\Authorize::render_consent() with $ctx in scope: client_id,
 * client_name, redirect_uri, code_challenge, state, scope. client_name comes
 * from anonymous registration, so it is escaped and labelled as client-supplied.
 *
 * @package PremiumAddons
 */

use PremiumAddons\Includes\Abilities\OAuth\Authorize;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$user = wp_get_current_user();
?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex" />
	<title><?php esc_html_e( 'Authorize connection', 'premium-addons-for-elementor' ); ?></title>
	<style>
		body{margin:0;background:#f5f6fa;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;color:#0a0a14}
		.wrap{max-width:460px;margin:8vh auto;padding:0 20px}
		.card{background:#fff;border:1px solid #0a0a141a;border-radius:16px;padding:32px}
		.eyebrow{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#252c59;font-weight:700;margin-bottom:14px}
		h1{font-size:22px;line-height:1.25;margin:0 0 6px}
		.client-src{font-size:12px;color:#71748b;margin:0 0 14px}
		p{font-size:15px;line-height:1.6;color:#3a3b52;margin:0 0 14px}
		.who{background:#f5f6fa;border:1px solid #0a0a1410;border-radius:10px;padding:12px 14px;font-size:14px;margin:0 0 20px}
		.who b{color:#0a0a14}
		.warn{font-size:13px;color:#71748b;margin:0 0 22px}
		.row{display:flex;gap:10px}
		button{flex:1;padding:12px 16px;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;border:1px solid transparent}
		.approve{background:#252c59;color:#fff}
		.deny{background:#fff;border-color:#0a0a1428;color:#3a3b52}
	</style>
</head>
<body>
	<div class="wrap">
		<div class="card">
			<div class="eyebrow"><?php esc_html_e( 'Authorize MCP connection', 'premium-addons-for-elementor' ); ?></div>

			<h1>
				<?php
				printf(
					/* translators: 1: client name, 2: site name */
					esc_html__( '%1$s wants to connect to %2$s', 'premium-addons-for-elementor' ),
					'<b>' . esc_html( $ctx['client_name'] ) . '</b>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline.
					esc_html( get_bloginfo( 'name' ) )
				);
				?>
			</h1>

			<p class="client-src"><?php esc_html_e( 'Name provided by the connecting client.', 'premium-addons-for-elementor' ); ?></p>

			<p><?php esc_html_e( 'It will connect as your WordPress account and can use the Premium Addons abilities you have enabled.', 'premium-addons-for-elementor' ); ?></p>

			<div class="who">
				<?php
				printf(
					/* translators: 1: display name, 2: user login */
					esc_html__( 'Signed in as %1$s (%2$s)', 'premium-addons-for-elementor' ),
					'<b>' . esc_html( $user->display_name ) . '</b>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline.
					esc_html( $user->user_login )
				);
				?>
			</div>

			<p class="warn"><?php esc_html_e( 'Only approve connections you started yourself. You can turn off MCP access anytime from the MCP Config & AI Abilities tab in the Premium Addons dashboard.', 'premium-addons-for-elementor' ); ?></p>

			<form method="post" action="<?php echo esc_url( home_url( Authorize::PATH ) ); ?>">
				<?php foreach ( array( 'client_id', 'redirect_uri', 'response_type', 'code_challenge', 'code_challenge_method', 'state' ) as $field ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $field ); ?>" value="<?php echo esc_attr( isset( $ctx[ $field ] ) ? $ctx[ $field ] : '' ); ?>" />
				<?php endforeach; ?>
				<input type="hidden" name="_pa_oauth_nonce" value="<?php echo esc_attr( wp_create_nonce( Authorize::NONCE_ACTION ) ); ?>" />

				<div class="row">
					<button class="deny" type="submit" name="decision" value="deny"><?php esc_html_e( 'Deny', 'premium-addons-for-elementor' ); ?></button>
					<button class="approve" type="submit" name="decision" value="approve"><?php esc_html_e( 'Approve', 'premium-addons-for-elementor' ); ?></button>
				</div>
			</form>
		</div>
	</div>
</body>
</html>
