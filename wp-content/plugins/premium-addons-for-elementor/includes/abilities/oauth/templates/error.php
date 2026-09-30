<?php
/**
 * OAuth error page, shown when there is no safe redirect target.
 *
 * Included by OAuth\Authorize::error_page() with $message in scope. The status
 * header and content type are sent by the caller before this is included.
 *
 * @package PremiumAddons
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

?>
<!doctype html>
<meta charset="utf-8" />
<title><?php esc_html_e( 'Connection error', 'premium-addons-for-elementor' ); ?></title>
<div style="max-width:460px;margin:12vh auto;font-family:sans-serif;text-align:center;color:#0a0a14">
	<h1 style="font-size:20px"><?php esc_html_e( 'Connection error', 'premium-addons-for-elementor' ); ?></h1>
	<p style="color:#3a3b52"><?php echo esc_html( $message ); ?></p>
</div>
