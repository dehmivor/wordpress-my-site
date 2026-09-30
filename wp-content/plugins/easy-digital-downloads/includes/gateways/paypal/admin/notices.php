<?php
/**
 * PayPal Admin Notices
 *
 * @package   easy-digital-downloads
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     2.11
 */

namespace EDD\Gateways\PayPal\Admin;

/**
 * Shows an IPN configuration notice when a store has migrated from v2 to v3 PayPal Commerce
 * and still has active legacy (non-vault) subscriptions.
 *
 * Legacy subscriptions renew via IPN (PayPal v2 Commerce webhooks also send IPN when
 * configured on the merchant's account). This notice prompts the merchant to confirm IPN
 * is enabled so renewals are processed correctly.
 *
 * @since 3.6.9
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Only show on EDD settings or dashboard pages to avoid polluting unrelated screens.
	$screen = get_current_screen();
	if ( ! $screen || ( 'download_page_edd-settings' !== $screen->id && 'dashboard' !== $screen->id ) ) {
		return;
	}

	// Only relevant after a v2 → v3 migration with the Connect proxy active.
	$mode = \EDD\Gateways\PayPal\Gateway::get_paypal_mode();
	if ( ! \EDD\Gateways\PayPal\V3\Onboarding::is_v3_onboarded( $mode ) ) {
		return;
	}
	if ( ! get_option( "edd_paypal_{$mode}_had_v2_connection" ) ) {
		return;
	}

	// Only relevant when Recurring Payments is active.
	if ( ! class_exists( 'EDD_Recurring' ) ) {
		return;
	}

	// Bail if the merchant has already dismissed this notice.
	if ( get_user_meta( get_current_user_id(), '_edd_paypal_v3_legacy_ipn_dismissed', true ) ) {
		return;
	}

	// Check for at least one active non-vault PayPal Commerce subscription.
	global $wpdb;
	$sub_table  = $wpdb->prefix . 'edd_subscriptions';
	$meta_table = $wpdb->prefix . 'edd_subscriptionmeta';
	$has_legacy = $wpdb->get_var(
		"SELECT s.id FROM {$sub_table} s
		 LEFT JOIN {$meta_table} m
		     ON m.edd_subscription_id = s.id AND m.meta_key = 'paypal_gateway_subtype'
		 WHERE s.gateway = 'paypal_commerce'
		   AND s.status IN ('active','trialling')
		   AND ( m.meta_value IS NULL OR m.meta_value != 'paypal_commerce_vault' )
		 LIMIT 1"
	);

	if ( ! $has_legacy ) {
		return;
	}

	$ipn_url    = home_url( '?edd-listener=eppe' );
	$paypal_url = edd_is_test_mode()
		? 'https://www.sandbox.paypal.com/merchantnotification/ipn/preference'
		: 'https://www.paypal.com/merchantnotification/ipn/preference';
	$dismiss_url = wp_nonce_url( add_query_arg( array(
		'edd-action' => 'dismiss_paypal_v3_legacy_ipn_notice',
	) ), 'edd_paypal_v3_legacy_ipn_dismiss' );

	?>
	<div class="notice edd-notice notice-warning">
		<h3><?php esc_html_e( 'Action Required: Configure PayPal IPN for existing subscriptions', 'easy-digital-downloads' ); ?></h3>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: 1: Opening anchor tag to PayPal IPN settings; 2: Closing anchor tag */
					__( 'You have active PayPal subscriptions that were created before your upgrade to the new PayPal integration. These subscriptions renew automatically at PayPal and their renewal payments are processed via PayPal\'s Instant Payment Notification (IPN). To ensure renewals are recorded in EDD, please verify that IPN is enabled in your %1$sPayPal account settings%2$s.', 'easy-digital-downloads' ),
					'<a href="' . esc_url( $paypal_url ) . '" target="_blank" rel="noopener noreferrer">',
					'</a>'
				),
				array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) )
			);
			?>
		</p>
		<p>
			<?php
			echo wp_kses(
				sprintf(
					/* translators: %s: IPN notification URL */
					__( 'Your IPN notification URL is: <code>%s</code>', 'easy-digital-downloads' ),
					esc_url( $ipn_url )
				),
				array( 'code' => array() )
			);
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button"><?php esc_html_e( 'Dismiss', 'easy-digital-downloads' ); ?></a>
		</p>
	</div>
	<?php
} );

/**
 * Handles dismissal of the PayPal v3 legacy IPN notice.
 *
 * @since 3.6.9
 */
add_action( 'edd_dismiss_paypal_v3_legacy_ipn_notice', function () {
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_paypal_v3_legacy_ipn_dismiss' ) ) {
		return;
	}
	update_user_meta( get_current_user_id(), '_edd_paypal_v3_legacy_ipn_dismissed', true );
	wp_safe_redirect( remove_query_arg( array( 'edd-action', '_wpnonce' ) ) );
	exit;
} );

add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Bail if this notice has already been dismissed.
	if ( get_user_meta( get_current_user_id(), '_edd_paypal_commerce_dismissed' ) ) {
		return;
	}

	$enabled_gateways = array_keys( edd_get_enabled_payment_gateways() );
	$enabled_gateways = array_diff( $enabled_gateways, array( 'paypal_commerce' ) );

	// Show a notice if any PayPal gateway is enabled, other than PayPal Commerce.
	$paypal_gateways = array_filter( $enabled_gateways, function( $gateway ) {
		return false !== strpos( strtolower( $gateway ), 'paypal' );
	} );

	if ( ! $paypal_gateways ) {
		return;
	}

	$dismiss_url = wp_nonce_url( add_query_arg( array(
		'edd_action' => 'dismiss_notices',
		'edd_notice' => 'paypal_commerce'
	) ), 'edd_notice_nonce' );

	$setup_url = add_query_arg( array(
		'post_type' => 'download',
		'page'      => 'edd-settings',
		'tab'       => 'gateways',
		'section'   => 'paypal_commerce'
	), admin_url( 'edit.php' ) );

	?>
	<div class="notice notice-info">
		<h2><?php esc_html_e( 'Enable the new PayPal gateway for Easy Digital Downloads', 'easy-digital-downloads' ); ?></h2>
		<p>
			<?php
			echo wp_kses( sprintf(
				/* translators: %1$s opening anchor tag; %2$s closing anchor tag */
				__( 'A new, improved PayPal experience is now available in Easy Digital Downloads. You can learn more about the new integration in %1$sour documentation%2$s.', 'easy-digital-downloads' ),
				'<a href="https://easydigitaldownloads.com/docs/paypal-setup/#upgrade" target="_blank">',
				'</a>'
			), array( 'a' => array( 'href' => true, 'target' => true ) ) );
			?>
		</p>
		<p>
			<a href="<?php echo esc_url( $setup_url ); ?>" class="button button-primary"><?php esc_html_e( 'Activate the New PayPal', 'easy-digital-downloads' ); ?></a>
			<a href="<?php echo esc_url( $dismiss_url ); ?>" class="button"><?php esc_html_e( 'Dismiss Notice', 'easy-digital-downloads' ); ?></a>
		</p>
	</div>
	<?php
} );
