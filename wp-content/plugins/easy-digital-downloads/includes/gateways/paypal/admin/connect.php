<?php
/**
 * PayPal Commerce Connect
 *
 * @package   easy-digital-downloads
 * @copyright Copyright (c) 2021, Sandhills Development, LLC
 * @license   GPL2+
 * @since     2.11
 */

namespace EDD\Gateways\PayPal\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal;
use EDD\Gateways\PayPal\AccountStatusValidator;
use EDD\Gateways\PayPal\API;
use EDD\Gateways\PayPal\V3\Credentials;
use EDD\Gateways\PayPal\V3\Merchant;
use EDD\Utils\URL;

if ( ! defined( 'EDD_PAYPAL_PARTNER_CONNECT_URL' ) ) {
	define( 'EDD_PAYPAL_PARTNER_CONNECT_URL', 'https://easydigitaldownloads.com/wp-json/paypal-connect/v1/' );
}

/**
 * Returns the content for the PayPal Commerce Connect fields.
 *
 * If the account is not yet connected, the user is shown a "Connect with PayPal" button.
 * If they are connected, their account details are shown instead.
 *
 * @since 2.11
 * @since 3.7.0 Limits the "disconnect and reconnect" notice to genuine
 *        production key-rotation failures. A staging/local host intentionally
 *        skips recovery and needs no admin action, so no notice is shown there.
 *
 * @return void
 */
function connect_settings_field() {
	$commerce_version = PayPal\CommerceVersion::get_version();
	$mode             = edd_is_test_mode() ? __( 'sandbox', 'easy-digital-downloads' ) : __( 'live', 'easy-digital-downloads' );

	// Determine connection status based on commerce version.
	if ( 'v3' === $commerce_version ) {
		$is_connected = PayPal\V3\Onboarding::is_v3_onboarded();
	} else {
		$is_connected = PayPal\has_rest_api_connection();
	}

	// If EDD Recurring is active but the installed version doesn't support v3
	// vault, surface a clear update notice and fall back to the v2 onboarding
	// path. Recurring 2.13.11 and earlier predate v3 support — checking for the
	// vault-attribute method is more durable than version-pinning when Recurring
	// versioning changes.
	$recurring_supports_v3 = ! class_exists( 'EDD_Recurring_PayPal_Commerce' )
		|| method_exists( 'EDD_Recurring_PayPal_Commerce', 'add_vault_order_attributes' );

	if ( ! $is_connected ) {
		if ( 'v3' === $commerce_version && $recurring_supports_v3 ) {
			// Clear any stale v2 connect details.
			$raw_mode = PayPal\Gateway::get_paypal_mode();
			delete_option( 'edd_paypal_commerce_connect_details_' . $raw_mode );

			connect_settings_field_v3( $mode );
		} else {
			if ( 'v3' === $commerce_version && ! $recurring_supports_v3 ) {
				echo '<div class="notice edd-notice notice-warning inline"><p>';
				esc_html_e( "Update Recurring Payments to access PayPal's improved checkout experience. Until then, your existing PayPal integration will continue to work as is.", 'easy-digital-downloads' );
				echo '</p></div>';
			}

			// Legacy v2 connect flow: shown when a store was previously connected via v2 and has since been disconnected, but has not yet been migrated to v3, or when EDD Recurring is too old for v3.
			connect_settings_field_v2( $mode );
		}
		?>
		<div id="edd-paypal-commerce-errors"></div>
		<?php
	} else {
		// Prompt to re-establish only on a genuine production failure; a
		// staging/local host intentionally skips recovery and needs no action.
		if ( 'v3' === $commerce_version && ! PayPal\V3\KeyRotation::ensure( PayPal\Gateway::get_paypal_mode() ) && URL::is_production_url( home_url() ) ) {
			?>
			<div class="notice edd-notice notice-warning inline">
				<p>
					<strong><?php esc_html_e( 'PayPal connection needs re-establishing.', 'easy-digital-downloads' ); ?></strong>
					<?php esc_html_e( "Your site's security keys changed, so the stored PayPal credentials can no longer be read and could not be recovered automatically. Disconnect and reconnect to restore the connection.", 'easy-digital-downloads' ); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Show Account Info & Disconnect
		 */
		?>
		<div id="edd-paypal-commerce-connect-wrap" class="edd-paypal-connect-account-info notice inline loading" data-nonce="<?php echo esc_attr( wp_create_nonce( 'edd_paypal_account_information' ) ); ?>">
			<ul class="edd-paypal-account-status">
				<li>
					<span></span>
				</li>
				<li>
					<span></span>
				</li>
				<li>
					<span></span>
						<ul class="edd-paypal-webhook-events">
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
							<li>
								<span></span>
							</li>
						</ul>
				</li>
				<li>
					<span></span>
				</li>
			</ul>
			<p class="edd-paypal-connect-actions">
				<span></span>
				<span></span>
			</p>
		</div>
		<div id="edd-paypal-disconnect"></div>
		<?php
	}
	?>

	<?php
}
add_action( 'edd_paypal_connect_button', __NAMESPACE__ . '\connect_settings_field' );

/**
 * Renders the v2 (1st party) connect button.
 *
 * @since 3.6.9
 *
 * @param string $mode Translated mode label.
 */
function connect_settings_field_v2( $mode ) {
	$onboarding_data = get_onboarding_data();
	if ( 200 !== $onboarding_data['code'] || empty( $onboarding_data['body']->signupLink ) ) {
		?>
		<div class="notice notice-error inline">
			<p>
				<?php
				echo wp_kses(
					sprintf(
					/* translators: 1. opening <strong> tag, 2. closing </strong> tag */
						__( '%1$sPayPal Communication Error:%2$s We are having trouble communicating with PayPal at the moment. Please try again later, and if the issue persists, reach out to our support team.', 'easy-digital-downloads' ),
						'<strong>',
						'</strong>'
					),
					array( 'strong' => array() )
				);
				?>
			</p>
		</div>
		<?php
	} else {
		?>
		<a type="button" target="_blank" id="edd-paypal-commerce-link" class="edd-paypal-connect" href="<?php echo $onboarding_data['body']->signupLink; ?>&displayMode=minibrowser" data-paypal-onboard-complete="eddPayPalOnboardingCallback" data-paypal-button="true" data-paypal-onboard-button="true" data-nonce="<?php echo esc_attr( wp_create_nonce( 'edd_process_paypal_connect' ) ); ?>">
			<?php
			/* translators: %s: the store mode, either `sandbox` or `live` */
			printf( esc_html__( 'Connect with PayPal in %s mode', 'easy-digital-downloads' ), esc_html( $mode ) );
			?>
		</a>
		<?php
	}
}

/**
 * Renders the v3 (Connect) connect button.
 *
 * The button triggers an AJAX call to register the store with the Connect service
 * and retrieve a PayPal signup link. On success, the PayPal minibrowser
 * opens for merchant onboarding.
 *
 * @since 3.6.9
 * @since 3.7.0 The button markup is built by ConnectButton::get().
 *
 * @param string $mode Translated mode label. Unused — kept for back-compat; the
 *                     button renderer derives the mode itself.
 */
function connect_settings_field_v3( $mode ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- kept for back-compat.
	if ( ! \EDD\Utils\Validators\Salts::are_secure() ) {
		?>
		<div class="notice edd-notice notice-warning inline">
			<p>
				<strong><?php esc_html_e( 'Set unique WordPress security keys to connect PayPal.', 'easy-digital-downloads' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'To connect securely with PayPal, your site needs unique WordPress security keys (salts). This install is currently using default, empty, or missing keys, which are not safe for processing payments. Generate a fresh set of keys, add them to your wp-config.php file, and reload this page.', 'easy-digital-downloads' ); ?>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: opening link tag to the WordPress.org key generator, 2: closing link tag, 3: opening link tag to a guide on WordPress security keys, 4: closing link tag. */
					esc_html__( '%1$sGenerate new keys%2$s, then %3$sread how to add them to wp-config.php%4$s.', 'easy-digital-downloads' ),
					'<a href="https://api.wordpress.org/secret-key/1.1/salt/" target="_blank" rel="noopener noreferrer">',
					'</a>',
					'<a href="https://www.wpbeginner.com/beginners-guide/what-why-and-hows-of-wordpress-security-keys/" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
				?>
			</p>
		</div>
		<?php
		return;
	}

	// Surface the last onboarding failure (if any) so the admin can see
	// what went wrong on the return trip from PayPal. `handle_paypal_redirect`
	// stamps the message into a 5-minute transient and tags the redirect URL
	// with ?edd_paypal_onboarding_error=<code>. We read both — the transient
	// holds the human message, the query string is the trigger.
	if ( ! empty( $_GET['edd_paypal_onboarding_error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display.
		$transient_key = 'edd_paypal_v3_onboarding_error_' . get_current_user_id();
		$message       = get_transient( $transient_key );
		delete_transient( $transient_key );

		$error_code = sanitize_key( wp_unslash( $_GET['edd_paypal_onboarding_error'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $message ) ) {
			$message = __( 'PayPal onboarding could not be completed. Please try again or check the debug log for details.', 'easy-digital-downloads' );
		}
		?>
		<div class="notice edd-notice notice-error inline edd-paypal-onboarding-error">
			<p>
				<strong><?php esc_html_e( 'PayPal connection could not be completed:', 'easy-digital-downloads' ); ?></strong>
				<?php echo esc_html( $message ); ?>
				<?php if ( $error_code && 'unknown' !== $error_code ) : ?>
					<br><small><code><?php echo esc_html( $error_code ); ?></code></small>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	echo ConnectButton::get(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped during generation.
}

/**
 * Single function to make a request to get the onboarding URL and nonce.
 *
 * Previously we did this in process_connect method, but we've moved away from the AJAX useage of this
 * in favor of doing it on loading the settings field, to make loading the modal more reliable and faster.
 *
 * @since 3.1.2
 */
function get_onboarding_data() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return array(
			'code' => 403,
			'body' => array(
				'message' => __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ),
			),
		);
	}

	$mode = edd_is_test_mode() ? API::MODE_SANDBOX : API::MODE_LIVE;

	$existing_connect_details = get_partner_details( $mode );

	if ( ! empty( $existing_connect_details ) ) {
		// Ensure the data we have contains all necessary details.
		if (
			( ! empty( $existing_connect_details->expires ) && $existing_connect_details->expires > time() ) &&
			! empty( $existing_connect_details->nonce ) &&
			! empty( $existing_connect_details->signupLink ) && // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			! empty( $existing_connect_details->product )
		) {
			return array(
				'code' => 200,
				'body' => $existing_connect_details,
			);
		}
	}

	$request = new \EDD\Utils\RemoteRequest(
		EDD_PAYPAL_PARTNER_CONNECT_URL . 'signup-link',
		array(
			'headers'    => array(
				'Content-Type' => 'application/json',
			),
			'user-agent' => 'Easy Digital Downloads/' . EDD_VERSION . '; ' . get_bloginfo( 'name' ),
			'body'       => wp_json_encode(
				array(
					'mode'          => $mode,
					'country_code'  => edd_get_shop_country(),
					'currency_code' => edd_get_currency(),
					'return_url'    => get_settings_url(),
				)
			),
			'method'     => 'POST',
		)
	);

	if ( is_wp_error( $request->response ) ) {

		return array(
			'code' => $request->code,
			'body' => $request->response->get_error_message(),
		);
	}

	$body = json_decode( $request->body );

	// We're storing an expiration so we can get a new one if it's been a day.
	$body->expires = time() + DAY_IN_SECONDS;

	// We need to store this temporarily so we can use the nonce again in the next request.
	update_option( 'edd_paypal_commerce_connect_details_' . $mode, wp_json_encode( $body ), false );

	return array(
		'code' => $request->code,
		'body' => $body,
	);
}

/**
 * AJAX handler for processing the PayPal Connection.
 *
 * @since 2.11
 * @deprecated 3.1.2 Instead of doing this via an AJAX request, we now do this on page load.
 *
 * @return void
 */
function process_connect() {
	_edd_deprecated_function( __FUNCTION__, '3.1.2', 'EDD_PayPal_Commerce::get_onboarding_data()' );

	// This validates the nonce.
	check_ajax_referer( 'edd_process_paypal_connect' );

	$onboarding_data = get_onboarding_data();

	if ( 200 !== intval( $onboarding_data['code'] ) ) {
		wp_send_json_error(
			sprintf(
			/* translators: 1: HTTP response code, 2: error message */
				__( 'Unexpected response code: %1$d. Error: %2$s', 'easy-digital-downloads' ),
				$onboarding_data['code'],
				wp_json_encode( $onboarding_data['body'] )
			)
		);
	}

	if ( empty( $onboarding_data['body']->signupLink ) || empty( $onboarding_data['body']->nonce ) ) {
		wp_send_json_error( __( 'An unexpected error occurred.', 'easy-digital-downloads' ) );
	}

	wp_send_json_success( $onboarding_data['body'] );
}

/**
 * AJAX handler for processing the PayPal Reconnect.
 *
 * @since 3.1.0.3
 * @return void
 */
function process_reconnect() {
	// This validates the nonce.
	check_ajax_referer( 'edd_process_paypal_connect' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	$mode = edd_is_test_mode() ? API::MODE_SANDBOX : API::MODE_LIVE;

	/**
	 * Make sure we still have connection details from the previously connected site.
	 */
	$connection_details = get_option( 'edd_paypal_commerce_connect_details_' . $mode );

	if ( empty( $connection_details ) ) {
		// Somehow we ended up here, but now that we're in an invalid state, remove all settings so we can fully reset.
		delete_option( 'edd_paypal_commerce_connect_details_' . $mode );
		delete_option( 'edd_paypal_commerce_webhook_id_' . $mode );
		delete_option( 'edd_paypal_' . $mode . '_merchant_details' );
		wp_send_json_error( __( 'Failure reconnecting to PayPal. Please try again', 'easy-digital-downloads' ) );
	}

	try {
		PayPal\Webhooks\create_webhook( $mode );
	} catch ( \Exception $e ) {
		$message = esc_html__( 'Your account has been successfully reconnected, but an error occurred while creating a webhook.', 'easy-digital-downloads' );
	}

	wp_safe_redirect( esc_url_raw( get_settings_url() ) );
}
add_action( 'wp_ajax_edd_paypal_commerce_reconnect', __NAMESPACE__ . '\process_reconnect' );

/**
 * Retrieves partner Connect details for the given mode.
 *
 * @param string $mode Store mode. If omitted, current mode is used.
 *
 * @return stdObj|null
 */
function get_partner_details( $mode = '' ) {
	if ( ! $mode ) {
		$mode = edd_is_test_mode() ? API::MODE_SANDBOX : API::MODE_LIVE;
	}
	return json_decode( get_option( 'edd_paypal_commerce_connect_details_' . $mode ) );
}

/**
 * AJAX handler for retrieving a one-time access token, then used to retrieve
 * the seller's API credentials.
 *
 * @since 2.11
 * @return void
 */
function get_and_save_credentials() {
	// This validates the nonce.
	check_ajax_referer( 'edd_process_paypal_connect' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	if ( empty( $_POST['auth_code'] ) || empty( $_POST['share_id'] ) ) {
		wp_send_json_error( __( 'Missing PayPal authentication information. Please try again.', 'easy-digital-downloads' ) );
	}

	$mode = edd_is_test_mode() ? PayPal\API::MODE_SANDBOX : PayPal\API::MODE_LIVE;

	// Store a transient to indicate that we've started the connect process.
	set_transient(
		'edd_paypal_commerce_connect_started_' . $mode,
		wp_hash( get_current_user_id() . '_' . $mode . '_started', 'nonce' ),
		15 * MINUTE_IN_SECONDS
	);

	$partner_details = get_partner_details( $mode );
	if ( empty( $partner_details->nonce ) ) {
		wp_send_json_error( __( 'Missing nonce. Please refresh the page and try again.', 'easy-digital-downloads' ) );
	}

	$paypal_subdomain = edd_is_test_mode() ? '.sandbox' : '';
	$api_url          = 'https://api-m' . $paypal_subdomain . '.paypal.com/';
	$api_args         = array(
		'headers'    => array(
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'Authorization' => sprintf( 'Basic %s', base64_encode( $_POST['share_id'] ) ),
			'timeout'       => 15,
		),
		'body'       => array(
			'grant_type'    => 'authorization_code',
			'code'          => $_POST['auth_code'],
			'code_verifier' => $partner_details->nonce,
		),
		'user-agent' => 'Easy Digital Downloads/' . EDD_VERSION . '; ' . get_bloginfo( 'name' ),
		'method'     => 'POST',
	);

	/*
	 * First get a temporary access token from PayPal.
	 */
	$request = new \EDD\Utils\RemoteRequest(
		$api_url . 'v1/oauth2/token',
		$api_args
	);

	if ( is_wp_error( $request->response ) ) {
		wp_send_json_error( $request->response->get_error_message() );
	}

	$body = json_decode( $request->body );

	if ( empty( $body->access_token ) ) {
		wp_send_json_error(
			sprintf(
				/* translators: %d: HTTP response code */
				__( 'Unexpected response from PayPal while generating token. Response code: %d. Please try again.', 'easy-digital-downloads' ),
				$request->code
			)
		);
	}

	/*
	 * Now we can use this access token to fetch the seller's credentials for all future
	 * API requests.
	 */
	$request = new \EDD\Utils\RemoteRequest(
		$api_url . 'v1/customer/partners/' . urlencode( \EDD\Gateways\PayPal\get_partner_merchant_id( $mode ) ) . '/merchant-integrations/credentials/',
		array(
			'headers'    => array(
				'Authorization' => sprintf( 'Bearer %s', $body->access_token ),
				'Content-Type'  => 'application/json',
				'timeout'       => 15,
			),
			'user-agent' => 'Easy Digital Downloads/' . EDD_VERSION . '; ' . get_bloginfo( 'name' ),
		)
	);

	if ( is_wp_error( $request->response ) ) {
		wp_send_json_error( $request->response->get_error_message() );
	}

	$code = $request->code;
	$body = json_decode( $request->body );

	if ( empty( $body->client_id ) || empty( $body->client_secret ) ) {
		wp_send_json_error(
			sprintf(
			/* translators: %d: HTTP response code */
				__( 'Unexpected response from PayPal. Response code: %d. Please try again.', 'easy-digital-downloads' ),
				$code
			)
		);
	}

	edd_update_option( 'paypal_' . $mode . '_client_id', sanitize_text_field( $body->client_id ) );
	edd_update_option( 'paypal_' . $mode . '_client_secret', sanitize_text_field( $body->client_secret ) );

	$message = esc_html__( 'Successfully connected.', 'easy-digital-downloads' );

	try {
		PayPal\Webhooks\create_webhook( $mode );
	} catch ( \Exception $e ) {
		$message = esc_html__( 'Your account has been successfully connected, but an error occurred while creating a webhook.', 'easy-digital-downloads' );
	}

	/**
	 * Triggers when an account is successfully connected to PayPal.
	 *
	 * @param string $mode The mode that the account was connected in. Either `sandbox` or `live`.
	 *
	 * @since 2.11
	 */
	do_action( 'edd_paypal_commerce_connected', $mode );

	wp_send_json_success( $message );
}

add_action( 'wp_ajax_edd_paypal_commerce_get_access_token', __NAMESPACE__ . '\get_and_save_credentials' );

/**
 * Verifies the connected account.
 *
 * @since 2.11
 * @return void
 */
function get_account_info() {
	check_ajax_referer( 'edd_paypal_account_information' );

	if ( ! current_user_can( 'manage_shop_settings' ) ) {
		wp_send_json_error( wpautop( __( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) ) );
	}

	// Branch on commerce version.
	if ( 'v3' === PayPal\CommerceVersion::get_version() ) {
		get_account_info_v3();
		return;
	}

	try {
		$status         = 'success';
		$account_status = '';
		$actions        = array(
			'refresh_merchant' => '<button type="button" class="button edd-paypal-connect-action" data-nonce="' . esc_attr( wp_create_nonce( 'edd_check_merchant_status' ) ) . '" data-action="edd_paypal_commerce_check_merchant_status">' . esc_html__( 'Re-Check Payment Status', 'easy-digital-downloads' ) . '</button>',
			'webhook'          => '<button type="button" class="button edd-paypal-connect-action" data-nonce="' . esc_attr( wp_create_nonce( 'edd_update_paypal_webhook' ) ) . '" data-action="edd_paypal_commerce_update_webhook">' . esc_html__( 'Sync Webhook', 'easy-digital-downloads' ) . '</button>',
		);

		$disconnect_links = array(
			'disconnect' => '<a class="button-secondary" id="edd-paypal-disconnect-link" href="' . esc_url( get_disconnect_url() ) . '">' . __( 'Disconnect webhooks from PayPal', 'easy-digital-downloads' ) . '</a>',
			'delete'     => '<a class="button button-secondary" id="edd-paypal-delete-link" href="' . esc_url( get_delete_url() ) . '">' . __( 'Disconnect from PayPal', 'easy-digital-downloads' ) . '</a>',
		);

		$validator = new AccountStatusValidator();
		$validator->check();

		/*
		 * 1. Check REST API credentials
		 */
		$rest_api_message = '<strong>' . __( 'API:', 'easy-digital-downloads' ) . '</strong>' . ' ';
		if ( $validator->errors_for_credentials->errors ) {
			$rest_api_dashicon = 'no';
			$status            = 'error';
			$rest_api_message .= $validator->errors_for_credentials->get_error_message();
		} else {
			$rest_api_dashicon = 'yes';
			$mode_string       = edd_is_test_mode() ? __( 'sandbox', 'easy-digital-downloads' ) : __( 'live', 'easy-digital-downloads' );

			/* translators: %s: the connected mode, either `sandbox` or `live` */
			$rest_api_message .= sprintf( __( 'Your PayPal account is successfully connected in %s mode.', 'easy-digital-downloads' ), $mode_string );
		}

		ob_start();
		?>
		<li>
			<span class="dashicons dashicons-<?php echo esc_attr( $rest_api_dashicon ); ?>"></span>
			<span><?php echo wp_kses( $rest_api_message, array( 'strong' => array() ) ); ?></span>
		</li>
		<?php
		$account_status .= ob_get_clean();

		/*
		 * 2. Check merchant account
		 */
		$merchant_account_message = '<strong>' . __( 'Payment Status:', 'easy-digital-downloads' ) . '</strong>' . ' ';
		if ( $validator->errors_for_merchant_account->errors ) {
			$merchant_dashicon         = 'no';
			$status                    = 'error';
			$merchant_account_message .= __( 'You need to address the following issues before you can start receiving payments:', 'easy-digital-downloads' );

			// We can only refresh the status if we have a merchant ID.
			if ( in_array( 'missing_merchant_details', $validator->errors_for_merchant_account->get_error_codes(), true ) ) {
				unset( $actions['refresh_merchant'] );
			}
		} else {
			$merchant_dashicon         = 'yes';
			$merchant_account_message .= __( 'Ready to accept payments.', 'easy-digital-downloads' );
		}

		ob_start();
		?>
		<li>
			<span class="dashicons dashicons-<?php echo esc_attr( $merchant_dashicon ); ?>"></span>
			<span><?php echo wp_kses_post( $merchant_account_message ); ?></span>
			<?php if ( $validator->errors_for_merchant_account->errors ) : ?>
				<ul>
					<?php foreach ( $validator->errors_for_merchant_account->get_error_codes() as $code ) : ?>
						<li><?php echo wp_kses( $validator->errors_for_merchant_account->get_error_message( $code ), array( 'strong' => array() ) ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</li>
		<?php
		$account_status .= ob_get_clean();

		/*
		 * 3. Webhooks
		 */
		$webhook_message = '<strong>' . __( 'Webhook:', 'easy-digital-downloads' ) . '</strong>' . ' ';
		if ( $validator->errors_for_webhook->errors ) {
			$webhook_dashicon = 'no';
			$status           = ( 'success' === $status ) ? 'warning' : $status;
			$webhook_message .= $validator->errors_for_webhook->get_error_message();

			if ( in_array( 'webhook_missing', $validator->errors_for_webhook->get_error_codes(), true ) ) {
				unset( $disconnect_links['disconnect'] );
				$actions['webhook'] = '<button type="button" class="button edd-paypal-connect-action" data-nonce="' . esc_attr( wp_create_nonce( 'edd_create_paypal_webhook' ) ) . '" data-action="edd_paypal_commerce_create_webhook">' . esc_html__( 'Create Webhooks', 'easy-digital-downloads' ) . '</button>';
			}
		} else {
			unset( $disconnect_links['delete'] );
			$webhook_dashicon = 'yes';
			$webhook_message .= __( 'Webhook successfully configured for the following events:', 'easy-digital-downloads' );
		}

		ob_start();
		?>
		<li>
			<span class="dashicons dashicons-<?php echo esc_attr( $webhook_dashicon ); ?>"></span>
			<span><?php echo wp_kses( $webhook_message, array( 'strong' => array() ) ); ?></span>
			<?php if ( $validator->webhook ) : ?>
				<ul class="edd-paypal-webhook-events">
					<?php foreach ( array_keys( PayPal\Webhooks\get_webhook_events() ) as $event_name ) : ?>
						<li>
							<span class="dashicons dashicons-<?php echo in_array( $event_name, $validator->enabled_webhook_events, true ) ? 'yes' : 'no'; ?>"></span>
							<span><?php echo esc_html( $event_name ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</li>
		<?php
		$account_status .= ob_get_clean();

		if ( ! edd_is_gateway_active( 'paypal_commerce' ) ) {
			$account_status .= sprintf(
				/* translators: %1$s opening anchor tag; %2$s closing anchor tag; %3$s: opening line item/status/strong tags; %4$s closing strong tag; %5$s: closing list item tag */
				__( '%3$sGateway Status: %4$s PayPal is not currently active. %1$sEnable PayPal%2$s in the general gateway settings to start using it.%5$s', 'easy-digital-downloads' ),
				'<a href="' . esc_url( admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=main' ) ) . '">',
				'</a>',
				'<li><span class="dashicons dashicons-no"></span><strong>',
				'</strong>',
				'</li>'
			);
		}

		wp_send_json_success(
			array(
				'status'           => $status,
				'account_status'   => '<ul class="edd-paypal-account-status">' . $account_status . '</ul>',
				'webhook_object'   => isset( $validator ) ? $validator->webhook : null,
				'actions'          => array_values( $actions ),
				'disconnect_links' => array_values( $disconnect_links ),
			)
		);
	} catch ( \Exception $e ) {
		wp_send_json_error(
			array(
				'status'  => isset( $status ) ? $status : 'error',
				'message' => wpautop( $e->getMessage() ),
			)
		);
	}
}
add_action( 'wp_ajax_edd_paypal_commerce_get_account_info', __NAMESPACE__ . '\get_account_info' );

/**
 * Returns v3 account status information.
 *
 * Builds the connection status panel via the Account renderer, which
 * queries EDD Connect and falls back to wp_options when the
 * Connect service is unreachable.
 *
 * @since 3.6.9
 * @return void
 */
function get_account_info_v3() {
	$account          = new PayPal\V3\Admin\Account();
	$disconnect_links = array(
		'delete' => '<a class="button button-secondary" id="edd-paypal-delete-link" href="' . esc_url( get_delete_url() ) . '">' . __( 'Disconnect from PayPal', 'easy-digital-downloads' ) . '</a>',
	);
	// Force-refreshes the 4-hour transient cache that
	// Merchant::get_status() reads from.
	if ( $account->has_merchant_id() ) {
		$disconnect_links['refresh_merchant'] = sprintf(
			'<button type="button" class="button button-secondary edd-paypal-connect-action" data-nonce="%1$s" data-action="edd_paypal_v3_get_merchant_status">%2$s</button>',
			esc_attr( wp_create_nonce( 'edd_paypal_v3_onboarding' ) ),
			esc_html__( 'Refresh Merchant Status', 'easy-digital-downloads' )
		);

		// Rotate Credentials UI is deferred to a follow-up; the backend is in place.
	}

	wp_send_json_success(
		array(
			'status'           => $account->get_status(),
			'account_status'   => $account->render(),
			'actions'          => array(),
			'disconnect_links' => array_values( $disconnect_links ),
		)
	);
}

/**
 * Returns the URL for disconnecting from PayPal Commerce.
 *
 * @since 2.11
 * @return string
 */
function get_disconnect_url() {
	return wp_nonce_url(
		add_query_arg(
			array(
				'edd_action' => 'disconnect_paypal_commerce',
			),
			admin_url()
		),
		'edd_disconnect_paypal_commerce'
	);
}

/**
 * Returns the URL for deleting the app PayPal Commerce.
 *
 * @since 3.1.0.3
 * @return string
 */
function get_delete_url() {
	return wp_nonce_url(
		add_query_arg(
			array(
				'edd_action' => 'delete_paypal_commerce',
			),
			admin_url()
		),
		'edd_delete_paypal_commerce'
	);
}

/**
 * Disconnects from PayPal in the current mode.
 *
 * @since 2.11
 * @return void
 */
function process_disconnect() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_disconnect_paypal_commerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	$mode = edd_is_test_mode() ? PayPal\API::MODE_SANDBOX : PayPal\API::MODE_LIVE;

	// v3 stores don't have local webhooks or API credentials to disconnect.
	if ( 'v3' !== PayPal\CommerceVersion::get_version() ) {
		try {
			$api = new PayPal\API();

			try {
				// Disconnect the webhook.
				// This is in another try/catch because we want to delete the token cache (below) even if this fails.
				// This only deletes the webhooks in PayPal, we do not remove the webhook ID in EDD until we delete the connection.
				PayPal\Webhooks\delete_webhook( $mode );
			} catch ( \Exception $e ) {
				// We don't want to stop the process if we can't delete the webhooks.
			}

			// Also delete the token cache key, to ensure we fetch a fresh one if they connect to a different account later.
			delete_option( $api->token_cache_key );
		} catch ( \Exception $e ) {
			// We don't want to stop the process if we can't delete the webhook.
		}
	}

	wp_safe_redirect( esc_url_raw( get_settings_url() ) );
	exit;
}
add_action( 'edd_disconnect_paypal_commerce', __NAMESPACE__ . '\process_disconnect' );

/**
 * Fully deletes past Merchant Information from PayPal in the current mode.
 *
 * @since 3.1.0.3
 * @return void
 */
function process_delete() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'edd_delete_paypal_commerce' ) ) {
		wp_die( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ), esc_html__( 'Error', 'easy-digital-downloads' ), array( 'response' => 403 ) );
	}

	$mode = edd_is_test_mode() ? PayPal\API::MODE_SANDBOX : PayPal\API::MODE_LIVE;

	// Delete v2 merchant information and partner connect details.
	delete_option( 'edd_paypal_' . $mode . '_merchant_details' );
	delete_option( 'edd_paypal_commerce_connect_details_' . $mode );

	// Record that a v2 REST connection existed before any credentials are wiped,
	// so the legacy IPN notice can surface after the merchant reconnects via v3.
	if ( edd_get_option( "paypal_{$mode}_client_id" ) ) {
		update_option( "edd_paypal_{$mode}_had_v2_connection", true, false );
	}

	// v2-specific cleanup: webhooks, API credentials, token cache.
	if ( 'v3' !== PayPal\CommerceVersion::get_version() ) {
		try {
			$api = new PayPal\API();

			try {
				PayPal\Webhooks\delete_webhook( $mode );
			} catch ( \Exception $e ) {
				// We don't want to stop the process if we can't delete the webhooks.
			}

			delete_option( $api->token_cache_key );
		} catch ( \Exception $e ) {
			// We don't want to stop the process if we can't delete the webhooks.
		}

		delete_option( sanitize_key( 'edd_paypal_commerce_webhook_id_' . $mode ) );

		$edd_settings_to_delete = array(
			'paypal_' . $mode . '_client_id',
			'paypal_' . $mode . '_client_secret',
		);

		foreach ( $edd_settings_to_delete as $option_name ) {
			edd_delete_option( $option_name );
		}
	}

	// For v3 stores, notify the Connect service to clean up merchant record and deregister webhook.
	$v3_merchant_id = get_option( 'edd_paypal_' . $mode . '_merchant_id', '' );
	if ( ! empty( $v3_merchant_id ) ) {
		$proxy_api = new PayPal\V3\ConnectAPI( $mode );
		$proxy_api->delete(
			'/v3/paypal/merchants',
			array(
				'merchant_id' => $v3_merchant_id,
				'mode'        => $mode,
			)
		);
		// Fire and forget — don't block delete on the Connect response.
	}

	// Clear v3 Connect credentials via the canonical helpers so no option
	// or transient is left behind (hmac_key_fingerprint, hmac_key_previous,
	// seller_email, partner_client_id, advanced_card_available, etc.).
	Credentials::forget( $mode );
	Merchant::forget( $mode );
	delete_option( 'edd_paypal_' . $mode . '_tracking_id' );

	// Tear down Apple Pay domain registration state. The registration on
	// PayPal's side belongs to the merchant we're disconnecting from, so
	// the stored host + docroot .well-known file are stale once we wipe
	// the connection. The next admin_init after reconnecting will
	// re-install fresh against the new merchant.
	PayPal\V3\ApplePay\DomainAssociation::uninstall();

	// Advance commerce version to v3 so the settings UI shows the v3
	// onboarding flow after a full disconnect, even when the store was
	// previously pinned to v2.
	update_option( 'edd_paypal_' . $mode . '_commerce_version', 'v3' );

	// Unset the PayPal Commerce gateway as an enabled gateway.
	$enabled_gateways = edd_get_option( 'gateways', array() );
	unset( $enabled_gateways['paypal_commerce'] );
	edd_update_option( 'gateways', $enabled_gateways );

	wp_safe_redirect( esc_url_raw( get_settings_url() ) );
	exit;
}
add_action( 'edd_delete_paypal_commerce', __NAMESPACE__ . '\process_delete' );

/**
 * AJAX callback for refreshing payment status.
 *
 * @since 2.11
 * @throws \Exception If the merchant ID is not found.
 */
function refresh_merchant_status() {
	check_ajax_referer( 'edd_check_merchant_status' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	$merchant_details = PayPal\MerchantAccount::retrieve();

	try {
		if ( empty( $merchant_details->merchant_id ) ) {
			throw new \Exception( __( 'No merchant ID saved. Please reconnect to PayPal.', 'easy-digital-downloads' ) );
		}

		$partner_details = get_partner_details();
		$nonce           = isset( $partner_details->nonce ) ? $partner_details->nonce : null;

		$new_details      = get_merchant_status( $merchant_details->merchant_id, $nonce );
		$merchant_account = new PayPal\MerchantAccount( $new_details );
		$merchant_account->save();

		wp_send_json_success();
	} catch ( \Exception $e ) {
		wp_send_json_error( esc_html( $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_edd_paypal_commerce_check_merchant_status', __NAMESPACE__ . '\refresh_merchant_status' );

/**
 * AJAX callback for creating a webhook.
 *
 * @since 2.11
 */
function create_webhook() {
	check_ajax_referer( 'edd_create_paypal_webhook' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	try {
		PayPal\Webhooks\create_webhook();

		wp_send_json_success();
	} catch ( \Exception $e ) {
		wp_send_json_error( esc_html( $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_edd_paypal_commerce_create_webhook', __NAMESPACE__ . '\create_webhook' );

/**
 * AJAX callback for syncing a webhook. This is used to fix issues with missing events.
 *
 * @since 2.11
 */
function update_webhook() {
	check_ajax_referer( 'edd_update_paypal_webhook' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( esc_html__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ) );
	}

	try {
		PayPal\Webhooks\sync_webhook();

		wp_send_json_success();
	} catch ( \Exception $e ) {
		wp_send_json_error( esc_html( $e->getMessage() ) );
	}
}
add_action( 'wp_ajax_edd_paypal_commerce_update_webhook', __NAMESPACE__ . '\update_webhook' );

/**
 * PayPal Redirect Callback
 *
 * This processes after the merchant is redirected from PayPal. We immediately
 * check their seller status via partner connect and save their merchant status.
 * The user is then redirected back to the settings page.
 *
 * @since 2.11
 */
add_action(
	'load-download_page_edd-settings',
	function () {
		if ( ! isset( $_GET['merchantIdInPayPal'] ) || ! edd_is_admin_page( 'settings' ) ) {
			return;
		}

		// Bail when the store is connecting via v3 (Connect). This
		// callback dates from the v2 (1st-party) integration and uses the v2
		// API client + MerchantAccount model — running it during a v3 redirect
		// would race \EDD\Gateways\PayPal\V3\Onboarding::handle_paypal_redirect
		// on the same hook, consume the connect-started transient, and leave
		// the v3 handler with nothing to validate against. v3's onboarding
		// completes through its own handler against the Connect service.
		if ( 'v3' === PayPal\CommerceVersion::get_version() ) {
			return;
		}

		$mode            = PayPal\Gateway::get_paypal_mode();
		$connect_process = get_transient( 'edd_paypal_commerce_connect_started_' . $mode );
		if ( empty( $connect_process ) ) {
			return;
		}
		$check = wp_hash( get_current_user_id() . '_' . $mode . '_started', 'nonce' );

		if ( ! hash_equals( $connect_process, $check ) ) {
			wp_die(
				__( 'There was an error processing the connection to PayPal. Please attempt to connect again.', 'easy-digital-downloads' ),
				__( 'Error', 'easy-digital-downloads' ),
				array(
					'response'  => 403,
					'link_text' => __( 'Return to settings', 'easy-digital-downloads' ),
					'link_url'  => get_settings_url(),
				)
			);
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				__( 'You do not have permission to perform this action.', 'easy-digital-downloads' ),
				__( 'Error', 'easy-digital-downloads' ),
				array( 'response' => 403 )
			);
		}

		edd_debug_log( 'PayPal Connect - Checking merchant status.' );

		$merchant_id = urldecode( $_GET['merchantIdInPayPal'] );

		try {
			$details = get_merchant_status( $merchant_id );
			edd_debug_log( 'PayPal Connect - Successfully retrieved merchant status.' );
		} catch ( \Exception $e ) {
			/*
			 * This won't be enough to actually validate the merchant status, but we want to ensure
			 * we save the merchant ID no matter what.
			 */
			$details = array(
				'merchant_id' => $merchant_id,
			);

			edd_debug_log( sprintf( 'PayPal Connect - Failed to retrieve merchant status from PayPal. Error: %s', $e->getMessage() ) );
		}

		$merchant_account = new PayPal\MerchantAccount( $details );
		$merchant_account->save();

		// Remove our transient, instead of waiting for it to be removed automatically.
		delete_transient( 'edd_paypal_commerce_connect_started_' . $mode );

		edd_redirect( esc_url_raw( get_settings_url() ) );
	}
);

/**
 * Retrieves the merchant's status in PayPal.
 *
 * @param string $merchant_id The merchant ID to check.
 * @param string $nonce       The nonce to use for the request.
 *
 * @return array
 * @throws PayPal\Exceptions\API_Exception If the request fails.
 */
function get_merchant_status( $merchant_id, $nonce = '' ) {
	$api = new API();

	$response = $api->make_request(
		sprintf(
			'v1/customer/partners/%s/merchant-integrations/%s',
			\EDD\Gateways\PayPal\get_partner_merchant_id(),
			$merchant_id
		),
		array(),
		array(),
		'GET'
	);

	if ( 200 === (int) $api->last_response_code ) {
		return $response;
	}

	$response = (array) $response;

	if ( ! empty( $response['error'] ) ) {
		$error_message = $response['error'];
	} elseif ( ! empty( $response['message'] ) ) {
		$error_message = $response['message'];
	} else {
		$error_message = sprintf(
			'Invalid HTTP response code: %d. Response: %s',
			$api->last_response_code,
			$response
		);
	}

	// If the response code is a string, we'll default to a 403 because the API Exception requires an integer.
	if ( ! is_int( $api->last_response_code ) ) {
		throw new PayPal\Exceptions\API_Exception( $error_message, 403 );
	}

	throw new PayPal\Exceptions\API_Exception( $error_message, $api->last_response_code );
}
