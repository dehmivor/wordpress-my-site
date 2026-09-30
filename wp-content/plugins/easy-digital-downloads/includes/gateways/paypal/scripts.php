<?php
/**
 * PayPal Commerce Scripts
 *
 * @package    EDD\Gateways\PayPal
 * @copyright  Copyright (c) 2021, Sandhills Development, LLC
 * @license    https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since      2.11
 */

namespace EDD\Gateways\PayPal;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal\Exceptions\Authentication_Exception;
use EDD\Gateways\PayPal\V3\Customer;
use EDD\Gateways\PayPal\V3\ConnectAPI;
use EDD\Gateways\PayPal\V3\Vault;

/**
 * Enqueues polyfills for Promise and Fetch.
 *
 * @since 2.11
 */
function maybe_enqueue_polyfills() {
	/**
	 * Filters whether or not IE11 polyfills should be loaded.
	 * Note: This filter may have its default changed at any time, or may entirely
	 * go away at one point.
	 *
	 * @since 2.11
	 * @since 3.3.7 - Defaults to false, so that loading polyfills is opt-in.
	 */
	if ( ! apply_filters( 'edd_load_ie11_polyfills', false ) ) {
		return;
	}

	wp_enqueue_script( 'wp-polyfill' );
}

/**
 * Registers PayPal JavaScript
 *
 * @param bool $force_load Whether to force load the scripts.
 *
 * @since 2.11
 * @return void
 */
function register_js( $force_load = false ) {
	if ( ! edd_is_gateway_active( 'paypal_commerce' ) ) {
		return;
	}

	if ( ! ready_to_accept_payments() ) {
		return;
	}

	$mode = Gateway::get_paypal_mode();

	// Fetch SDK token early so we can decide whether to load Fastlane in the SDK URL.
	$client_token      = '';
	$is_checkout       = edd_is_checkout() || $force_load;
	$has_vaulting      = get_option( "edd_paypal_{$mode}_vaulting_available", false );
	$fastlane_enabled  = $has_vaulting && PaymentMethods::is_enabled( 'fastlane', true );
	$venmo_enabled     = PaymentMethods::is_enabled( 'venmo', true );
	$pay_later_enabled = PaymentMethods::is_enabled( 'pay_later', true );
	$card_enabled      = PaymentMethods::is_enabled( 'card', PaymentMethods::default_state( 'card' ) );

	$unbranded_card_enabled = PaymentMethods::is_enabled( 'unbranded_card', PaymentMethods::default_state( 'unbranded_card' ) );
	// Apple Pay needs a verifiable public domain and a sandbox-iCloud device,
	// neither of which exists in dev/test, so gate it off there. Mirrors
	// `DomainSubscriber::should_verify()`.
	$apple_pay_enabled  = PaymentMethods::is_enabled( 'apple_pay', true )
		&& ! edd_is_dev_environment()
		&& ! edd_is_test_mode();
	$google_pay_enabled = PaymentMethods::is_enabled( 'google_pay', true );

	// Assemble the set of active methods once. The payment methods registry
	// derives the SDK components, funding lists, and client-token requirement
	// from this set, so the SDK loader and the frontend gate read one
	// definition instead of each re-deriving it.
	$active_methods = array( 'paypal' );
	if ( $card_enabled ) {
		$active_methods[] = 'card';
	}
	if ( $unbranded_card_enabled ) {
		$active_methods[] = 'unbranded_card';
	}
	if ( $pay_later_enabled ) {
		$active_methods[] = 'pay_later';
	}
	if ( $venmo_enabled ) {
		$active_methods[] = 'venmo';
	}
	if ( $apple_pay_enabled ) {
		$active_methods[] = 'apple_pay';
	}
	if ( $google_pay_enabled ) {
		$active_methods[] = 'google_pay';
	}
	if ( $fastlane_enabled ) {
		$active_methods[] = 'fastlane';
	}

	// Gate the client-token fetch on a real checkout only, never on $force_load.
	// Buy Now buttons force the SDK to load but never use Fastlane or the
	// on-checkout card fields, so they must not trigger the blocking Connect API call.
	if ( edd_is_checkout() && ! empty( PaymentMethods::methods_requiring_client_token( $active_methods ) ) ) {
		$paypal_customer_id = '';
		if ( is_user_logged_in() ) {
			$customer           = edd_get_customer_by( 'user_id', get_current_user_id() );
			$paypal_customer_id = $customer ? Customer::get_id( $customer->id ) : '';
		}

		// Active host only; wp_parse_url() can return string|false|null, so coerce.
		$default_domain = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		/**
		 * Filters the root domain sent to PayPal for Fastlane SDK token generation.
		 *
		 * PayPal requires a publicly-resolvable root domain (no subdomains,
		 * protocols, or path) and rejects local hostnames, raw IPs, and
		 * single-label hosts. The result is validated by
		 * `is_public_paypal_domain()` before sending, and Fastlane is skipped
		 * if it fails. Only a single domain is honored per token.
		 *
		 * @since 3.6.9
		 *
		 * @param string $domain Auto-detected root domain (host of home_url()).
		 */
		$domain = apply_filters( 'edd_paypal_fastlane_domain', $default_domain );

		// Skip the Fastlane SDK-token request on non-public domains; the rest
		// of PayPal still works.
		$is_public = is_public_paypal_domain( $domain );

		if ( ! $is_public && '' !== $domain ) {
			edd_debug_log( sprintf( 'Fastlane: skipped — host is not publicly resolvable: %s', $domain ) );
		}

		if ( $is_public ) {
			$client_token = SdkToken::fetch( $mode, $paypal_customer_id, $domain );
		}
	}

	// Fastlane needs both the toggle and a token. The token is also fetched
	// for unbranded card fields, so a token alone no longer implies Fastlane.
	$has_fastlane = $fastlane_enabled && ! empty( $client_token );

	// On-checkout card fields. Set in the v3 branch below; initialized here
	// for the v2 branch and the localization block.
	$has_unbranded_card = false;

	if ( 'v3' === CommerceVersion::get_version() ) {
		// v3 (Connect): use the partner client ID and merchant ID.
		$client_id   = get_option( "edd_paypal_{$mode}_partner_client_id", '' );
		$merchant_id = get_option( "edd_paypal_{$mode}_merchant_id", '' );

		if ( empty( $client_id ) || empty( $merchant_id ) ) {
			return;
		}

		// Whether the on-checkout card fields (Advanced Card Processing) will
		// load: the method is active and the buyer client token was generated.
		$has_unbranded_card = $unbranded_card_enabled && ! empty( $client_token );

		// The funding lists and SDK components are assembled by the payment
		// methods registry from the active methods, centralizing rules that
		// previously drifted between this loader and the frontend gate — most
		// notably that the shared `card` funding is only disabled when neither
		// card method is active. Venmo and Pay Later still render only when
		// PayPal's SDK `isEligible()` check passes at button render time, so a
		// non-eligible buyer never sees them even though the funding is enabled.
		$enable_funding  = PaymentMethods::get_enable_funding( $active_methods );
		$disable_funding = PaymentMethods::get_disable_funding( $active_methods );
		$components      = PaymentMethods::get_sdk_components( $active_methods, ! empty( $client_token ) );

		$sdk_args = array(
			'client-id'      => urlencode( $client_id ),
			'merchant-id'    => urlencode( $merchant_id ),
			'currency'       => urlencode( strtoupper( edd_get_currency() ) ),
			'intent'         => 'capture',
			'commit'         => 'true',
			'components'     => implode( ',', $components ),
			'enable-funding' => implode( ',', $enable_funding ),
		);

		// Sandbox-only: simulate a US buyer so Venmo renders in test checkouts.
		// `buyer-country` is ignored in live mode.
		if ( 'sandbox' === $mode ) {
			$sdk_args['buyer-country'] = 'US';
		}

		if ( ! empty( $disable_funding ) ) {
			$sdk_args['disable-funding'] = implode( ',', $disable_funding );
		}

		// Fastlane and the on-checkout card fields both store the payment method
		// in the buyer's vault for subscription renewals, so enable vaulting
		// whenever either component loaded.
		if ( $has_fastlane || $has_unbranded_card ) {
			$sdk_args['vault'] = 'true';
		}

		/**
		 * Filters the query arguments added to the SDK URL for v3 (Connect).
		 *
		 * @since 3.6.9
		 */
		$sdk_query_args = apply_filters( 'edd_paypal_js_sdk_query_args', $sdk_args );
	} else {
		// v2 (1st party): use merchant's own client ID.
		try {
			$api = new API();
		} catch ( Authentication_Exception $e ) {
			return;
		}

		/**
		 * Filters the query arguments added to the SDK URL.
		 *
		 * @link  https://developer.paypal.com/docs/checkout/reference/customize-sdk/#query-parameters
		 *
		 * @since 2.11
		 */
		$sdk_query_args = apply_filters(
			'edd_paypal_js_sdk_query_args',
			array(
				'client-id'       => urlencode( $api->client_id ),
				'currency'        => urlencode( strtoupper( edd_get_currency() ) ),
				'intent'          => 'capture',
				'disable-funding' => 'card,credit,bancontact,blik,eps,giropay,ideal,mercadopago,mybank,p24,sepa,sofort,venmo',
			)
		);
	}

	wp_register_script(
		'sandhills-paypal-js-sdk',
		esc_url_raw( add_query_arg( array_filter( $sdk_query_args ), 'https://www.paypal.com/sdk/js' ) )
	);

	// Apple's SDK defines the <apple-pay-button> element our integration
	// renders; the PayPal Applepay component handles the flow but not the
	// button itself.
	wp_register_script(
		'apple-pay-sdk',
		'https://applepay.cdn-apple.com/jsapi/1.latest/apple-pay-sdk.js',
		array(),
		null,
		false
	);

	wp_register_script(
		'edd-paypal',
		edd_get_assets_url( 'js/gateways/' ) . 'paypal.js',
		array(
			'sandhills-paypal-js-sdk',
			'jquery',
			'edd-ajax',
			'wp-hooks',
		),
		EDD_VERSION,
		true
	);

	wp_register_style(
		'edd-paypal',
		edd_get_assets_url( 'css/gateways/' ) . 'paypal.min.css',
		array(),
		EDD_VERSION
	);
	wp_style_add_data( 'edd-paypal', 'rtl', 'replace' );
	wp_style_add_data( 'edd-paypal', 'suffix', '.min' );

	if ( $is_checkout ) {
		maybe_enqueue_polyfills();

		wp_enqueue_script( 'sandhills-paypal-js-sdk' );
		wp_enqueue_script( 'edd-paypal' );
		wp_enqueue_style( 'edd-paypal' );

		// Only enqueue Apple's SDK when Apple Pay is enabled.
		if ( $apple_pay_enabled ) {
			wp_enqueue_script( 'apple-pay-sdk' );
		}

		// Fastlane and the card-fields component need the client token on the
		// SDK script tag to initialize.
		if ( $has_fastlane || $has_unbranded_card ) {
			add_filter(
				'edd_paypal_js_sdk_data_attributes',
				function ( $attrs ) use ( $client_token ) {
					$attrs['sdk-client-token'] = $client_token;
					return $attrs;
				}
			);
		}

		$cart_timestamp = time();

		$paypal_script_vars = array(
			/**
			 * Filters the order approval handler.
			 *
			 * @since 2.11
			 */
			'approvalAction'          => apply_filters( 'edd_paypal_on_approve_action', 'edd_capture_paypal_order' ),
			'defaultError'            => edd_build_errors_html(
				array(
					'paypal-error' => esc_html__( 'An unexpected error occurred. Please try again.', 'easy-digital-downloads' ),
				)
			),
			'fastlaneValidationError' => edd_build_errors_html(
				array(
					'paypal-error' => esc_html__( 'Please check your card details and try again.', 'easy-digital-downloads' ),
				)
			),
			'intent'                  => ! empty( $sdk_query_args['intent'] ) ? $sdk_query_args['intent'] : 'capture',
			'style'                   => get_button_styles(),
			'clientToken'             => $client_token,
			'restNonce'               => wp_create_nonce( 'wp_rest' ),
			'cartToken'               => \EDD\REST\Security::generate_token( $cart_timestamp ),
			'cartTimestamp'           => $cart_timestamp,
			'restBase'                => esc_url_raw( rest_url( 'edd/v3/fastlane/' ) ),
			'payLaterEnabled'         => $pay_later_enabled,
			'cartTotal'               => edd_get_cart_total(),
			'enabledFundingSources'   => PaymentMethods::get_button_funding_sources( $active_methods ),
			'fundingSlugMap'          => PaymentMethods::get_funding_slug_map(),
			'isSandbox'               => 'sandbox' === $mode,
			'storeName'               => BrandName::get(),
		);

		// Unbranded card (Advanced Card Processing) config for the on-checkout
		// card-fields component. Only present when the component is loaded.
		if ( $has_unbranded_card ) {
			$paypal_script_vars['unbrandedCard'] = array(
				'createUrl'  => esc_url_raw( rest_url( 'edd/v3/unbranded-card/create' ) ),
				'captureUrl' => esc_url_raw( rest_url( 'edd/v3/unbranded-card/capture' ) ),
			);
		}

		/**
		 * Filters the variables localized for the PayPal Buttons frontend script.
		 *
		 * Allows extensions to inject additional configuration (such as alternate
		 * intents, approval actions, nonces, or REST endpoints) used by the
		 * PayPal Buttons integration on checkout.
		 *
		 * @since 3.6.9
		 *
		 * @param array $paypal_script_vars The localized PayPal script variables.
		 */
		$paypal_script_vars = apply_filters( 'edd_paypal_button_vars', $paypal_script_vars );

		wp_localize_script( 'edd-paypal', 'eddPayPalVars', $paypal_script_vars );
	}
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\register_js', 100 );

/**
 * Removes the "?ver=" query arg from the PayPal JS SDK URL, because PayPal will throw an error
 * if it's included.
 *
 * @param string $url The URL for the script source.
 *
 * @since 2.11
 * @return string
 */
function remove_ver_query_arg( $url ) {
	// Account for a possibly empty URL here.
	if ( empty( $url ) ) {
		return $url;
	}

	$sdk_url = 'https://www.paypal.com/sdk/js';

	if ( false !== strpos( $url, $sdk_url ) ) {
		$new_url = preg_split( '/(&ver|\?ver)/', $url );

		return $new_url[0];
	}

	return $url;
}

add_filter( 'script_loader_src', __NAMESPACE__ . '\remove_ver_query_arg', 100 );

/**
 * Adds data attributes to the PayPal JS SDK <script> tag.
 *
 * @link  https://developer.paypal.com/docs/checkout/reference/customize-sdk/#script-parameters
 *
 * @since 2.11
 *
 * @param string $script_tag HTML <script> tag.
 * @param string $handle     Registered handle.
 * @param string $src        Script SRC value.
 *
 * @return string
 */
function add_data_attributes( $script_tag, $handle, $src ) {
	if ( 'sandhills-paypal-js-sdk' !== $handle ) {
		return $script_tag;
	}

	/**
	 * Filters the data attributes to add to the <script> tag.
	 *
	 * @since 2.11
	 *
	 * @param array $data_attributes
	 */
	$data_attributes = apply_filters(
		'edd_paypal_js_sdk_data_attributes',
		array(
			'partner-attribution-id' => EDD_PAYPAL_PARTNER_ATTRIBUTION_ID,
			'page-type'              => 'checkout',
		)
	);

	if ( empty( $data_attributes ) || ! is_array( $data_attributes ) ) {
		return $script_tag;
	}

	$formatted_attributes = array_map(
		function ( $key, $value ) {
			return sprintf( 'data-%s="%s"', sanitize_html_class( $key ), esc_attr( $value ) );
		},
		array_keys( $data_attributes ),
		$data_attributes
	);

	return str_replace( ' src', ' ' . implode( ' ', $formatted_attributes ) . ' src', $script_tag );
}

add_filter( 'script_loader_tag', __NAMESPACE__ . '\add_data_attributes', 10, 3 );
