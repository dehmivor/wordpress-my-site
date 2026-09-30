<?php
/**
 * IPN Functions
 *
 * This serves as a fallback for the webhooks in the event that the app becomes disconnected.
 *
 * @package    easy-digital-downloads
 * @subpackage Gateways\PayPal\Webhooks
 * @copyright  Copyright (c) 2021, Sandhills Development, LLC
 * @license    GPL2+
 * @since      2.11
 */

namespace EDD\Gateways\PayPal\IPN;

/**
 * Listens for an IPN call from PayPal
 *
 * This is intended to be a 'backup' listener, for if the webhook is no longer connected for a specific PayPal object.
 *
 * @since 3.1.0.3
 * @since 3.2.0 Uses the new PayPal IPN class.
 */
function listen_for_ipn() {
	if ( empty( $_GET['edd-listener'] ) || 'eppe' !== $_GET['edd-listener'] ) {
		return;
	}

	// Delay execution to allow webhooks to process first. v2 receives both an
	// IPN and a 1st-party webhook for each event, and the sleep prevents the
	// IPN from racing the webhook. v3 receives Connect-relayed webhooks on a
	// separate route, so there's nothing to race — skip the delay there.
	$mode = \EDD\Gateways\PayPal\Gateway::get_paypal_mode();
	if ( 'v3' !== get_option( "edd_paypal_{$mode}_commerce_version", '' ) ) {
		sleep( 3 );
	}

	new \EDD\Gateways\PayPal\IPN( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
}
add_action( 'init', __NAMESPACE__ . '\listen_for_ipn' );

/**
 * Helper method to prefix any calls to edd_debug_log
 *
 * @since 3.1.0.3
 * @deprecated 3.2.0
 *
 * @param string $message The message to send to the debug logging.
 */
function ipn_debug_log( $message ) {
	edd_debug_log( 'PayPal Commerce IPN: ' . $message );
}
