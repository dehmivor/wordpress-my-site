<?php
/**
 * PayPal Commerce Payment Routing.
 *
 * Subscribes to checkout, refund, SDK, and webhook hooks to apply v3
 * (Connect) behavior. v2 paths are handled by the procedural
 * functions in `includes/gateways/paypal/` and are intentionally left
 * untouched by this class.
 *
 * @package     EDD\Gateways\PayPal
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\EventManagement\SubscriberInterface;
use EDD\Orders\Order;

/**
 * Routes PayPal Commerce payment requests through the v3 (Connect)
 * integration. Acts as the single entry point for create-order, capture, and
 * refund flows on v3 stores; legacy v2 stores continue to go through the
 * procedural functions in `includes/gateways/paypal/`.
 *
 * @since 3.6.9
 */
class Payments implements SubscriberInterface {

	/**
	 * Returns the events to subscribe to.
	 *
	 * @since 3.6.9
	 *
	 * @return array
	 */
	public static function get_subscribed_events() {
		return array(
			'edd_gateway_checkout_label'      => array( 'payment_source_checkout_label', 10, 3 ),
			'edd_gateway_admin_label'         => array( 'payment_source_checkout_label', 10, 3 ),
			'edd_order_receipt_order_details' => array( 'render_payer_email_block_receipt', 10, 1 ),
			'edd_order_receipt_after'         => array( 'render_payer_email_shortcode_receipt', 10, 2 ),
		);
	}

	/**
	 * Filters the gateway label to show the specific payment source.
	 *
	 * On the customer-facing checkout/receipt label, displays just the instrument
	 * ("Venmo", "Apple Pay", "Google Pay", or "PayPal") so buyers see what they
	 * actually used. On the admin label, appends the instrument in parentheses
	 * (e.g. "PayPal Commerce (Venmo)") so the gateway name is preserved alongside
	 * the funding-source detail — matching the Stripe pattern.
	 *
	 * @since 3.6.9
	 *
	 * @param string     $label   The default gateway label.
	 * @param string     $gateway The gateway ID.
	 * @param Order|null $order   The order object.
	 * @return string
	 */
	public function payment_source_checkout_label( $label, $gateway, $order = null ) {
		if ( 'paypal_commerce' !== $gateway || ! $order instanceof Order ) {
			return $label;
		}

		$payment_source = edd_get_order_meta( $order->id, '_edd_paypal_payment_source', true );
		if ( empty( $payment_source ) ) {
			return $label;
		}

		$labels = self::get_payment_source_labels();
		if ( ! isset( $labels[ $payment_source ] ) ) {
			return $label;
		}

		$instrument_label = $labels[ $payment_source ];

		// On the admin label, append the instrument in parens but skip the suffix
		// when the instrument is itself "PayPal" to avoid "PayPal Commerce (PayPal)".
		if ( 'edd_gateway_admin_label' === current_filter() ) {
			if ( 'paypal' === $payment_source ) {
				return $label;
			}

			return $label . ' (' . $instrument_label . ')';
		}

		return $instrument_label;
	}

	/**
	 * Renders the buyer's PayPal account email on the block-based order receipt.
	 *
	 * Mirrors the markup of the surrounding block receipt rows so styling is
	 * consistent without touching any template file (PayPal IWT requirement:
	 * the buyer's PayPal email must appear on the thank-you page).
	 *
	 * @since 3.6.9
	 *
	 * @param Order $order The order being displayed.
	 * @return void
	 */
	public function render_payer_email_block_receipt( $order ) {
		// The block receipt template fires both 'edd_order_receipt_order_details'
		// and the legacy 'edd_order_receipt_after' for back-compat — remove our
		// shortcode handler so the row doesn't render twice on block receipts.
		remove_action( 'edd_order_receipt_after', array( $this, 'render_payer_email_shortcode_receipt' ), 10 );

		$email = $this->get_payer_email_for_receipt( $order );
		if ( empty( $email ) ) {
			return;
		}
		?>
		<div class="edd-blocks__row edd-blocks-receipt__row-item">
			<div class="edd-blocks__row-label"><?php esc_html_e( 'PayPal Account', 'easy-digital-downloads' ); ?>:</div>
			<div class="edd-blocks__row-value"><?php echo esc_html( $email ); ?></div>
		</div>
		<?php
	}

	/**
	 * Renders the buyer's PayPal account email on the legacy shortcode receipt.
	 *
	 * @since 3.6.9
	 *
	 * @param Order $order            The order being displayed.
	 * @param array $edd_receipt_args [edd_receipt] shortcode arguments.
	 * @return void
	 */
	public function render_payer_email_shortcode_receipt( $order, $edd_receipt_args = array() ) {
		$email = $this->get_payer_email_for_receipt( $order );
		if ( empty( $email ) ) {
			return;
		}
		?>
		<tr>
			<td><strong><?php esc_html_e( 'PayPal Account', 'easy-digital-downloads' ); ?>:</strong></td>
			<td><?php echo esc_html( $email ); ?></td>
		</tr>
		<?php
	}

	/**
	 * Returns the buyer's PayPal account email for an order, when available.
	 *
	 * Returns an empty string when the order was not funded by PayPal Commerce
	 * or when no payer email was captured at order completion.
	 *
	 * @since 3.6.9
	 *
	 * @param mixed $order The order being displayed.
	 * @return string
	 */
	protected function get_payer_email_for_receipt( $order ): string {
		if ( ! $order instanceof Order || 'paypal_commerce' !== $order->gateway ) {
			return '';
		}

		$email = edd_get_order_meta( $order->id, '_edd_paypal_payer_email', true );

		return is_string( $email ) && is_email( $email ) ? $email : '';
	}

	/**
	 * Returns the slug-to-label map for the `_edd_paypal_payment_source`
	 * order meta values written at capture time.
	 *
	 * Used by the checkout-label resolver and the Payment Gateways report
	 * breakdown so both surface identical, translated labels.
	 *
	 * @since 3.6.9
	 *
	 * @return array<string,string>
	 */
	public static function get_payment_source_labels(): array {
		return array(
			'paypal'         => __( 'PayPal', 'easy-digital-downloads' ),
			'venmo'          => __( 'Venmo', 'easy-digital-downloads' ),
			'card'           => __( 'Credit Card (PayPal)', 'easy-digital-downloads' ),
			'unbranded_card' => __( 'Credit/Debit Card', 'easy-digital-downloads' ),
			'apple_pay'      => __( 'Apple Pay', 'easy-digital-downloads' ),
			'google_pay'     => __( 'Google Pay', 'easy-digital-downloads' ),
			'fastlane'       => __( 'Fastlane', 'easy-digital-downloads' ),
		);
	}

	/**
	 * Creates a PayPal order via Connect.
	 *
	 * Only valid for stores running the v3 integration. Buyer-facing error
	 * strings are surfaced via `edd_set_error`; detailed diagnostics (Connect
	 * error code, full response, EDD order ID) are recorded with
	 * `edd_record_gateway_error()` so support has full context when triaging.
	 *
	 * @since 3.6.9
	 *
	 * @param array $order_data The order data for PayPal (intent, purchase_units, etc.).
	 * @param int   $order_id   The EDD order ID this PayPal order is being created for.
	 * @return object|array The PayPal order response (or Connect error response on failure).
	 */
	public static function create_order( $order_data, $order_id ) {
		$proxy    = new V3\ConnectAPI();
		$response = $proxy->post( '/v3/paypal/orders', $order_data );

		if ( V3\ConnectAPI::is_error( $response ) ) {
			$error_code = V3\ConnectAPI::get_error_code( $response );

			switch ( $error_code ) {
				case 'payment_declined':
					edd_set_error( 'paypal_declined', __( 'Your payment method was declined. Please try another.', 'easy-digital-downloads' ) );
					break;

				case 'paypal_rate_limited':
					edd_set_error( 'paypal_error', __( 'PayPal is busy. Please try again in a moment.', 'easy-digital-downloads' ) );
					break;

				case 'paypal_error':
					edd_set_error( 'paypal_error', __( 'PayPal is temporarily unavailable. Please try again later.', 'easy-digital-downloads' ) );
					break;

				default:
					edd_set_error( 'paypal_error', __( 'An error occurred processing your payment. Please try again.', 'easy-digital-downloads' ) );
					break;
			}

			$error_message = V3\ConnectAPI::get_error_message( $response );

			edd_debug_log( 'PayPal v3 order creation failed: ' . $error_message );

			edd_record_gateway_error(
				__( 'PayPal Gateway Error', 'easy-digital-downloads' ),
				sprintf(
					/* translators: 1: Connect error code, 2: Connect error message, 3: Full Connect response. */
					__( 'PayPal v3 order creation failed. Error code: %1$s. Message: %2$s. Response: %3$s', 'easy-digital-downloads' ),
					$error_code,
					$error_message,
					wp_json_encode( $response )
				),
				$order_id
			);

			return $response;
		}

		return $response;
	}

	/**
	 * Captures a PayPal order via Connect.
	 *
	 * Only valid for stores running the v3 integration. Failures are logged
	 * via `edd_debug_log` and the raw Connect response is returned so callers
	 * can branch on `V3\ConnectAPI::is_error()`.
	 *
	 * @since 3.6.9
	 *
	 * @param string $paypal_order_id The PayPal order ID to capture.
	 * @return object|array The capture response (or Connect error response on failure).
	 */
	public static function capture_order( $paypal_order_id ) {
		$proxy    = new V3\ConnectAPI();
		$response = $proxy->post( '/v3/paypal/orders/' . urlencode( $paypal_order_id ) . '/capture', array() );

		if ( V3\ConnectAPI::is_error( $response ) ) {
			edd_debug_log( 'PayPal v3 capture failed: ' . V3\ConnectAPI::get_error_message( $response ) );
		}

		return $response;
	}

	/**
	 * Refunds a captured transaction via Connect.
	 *
	 * Only valid for stores running the v3 integration. When `$refund_object`
	 * is supplied and its total does not match the original order's total,
	 * a partial refund is requested.
	 *
	 * @since 3.6.9
	 *
	 * @param Order      $order         The original order being refunded.
	 * @param Order|null $refund_object Optional. The EDD refund order, when one already exists.
	 * @return array|false The refund response on success, false if the order has no transaction ID.
	 */
	public static function refund_order( Order $order, ?Order $refund_object = null ) {
		$transaction_id = $order->get_transaction_id();
		if ( empty( $transaction_id ) ) {
			return false;
		}

		$body = array(
			'capture_id' => $transaction_id,
		);

		if ( $refund_object instanceof Order ) {
			$body['note_to_payer'] = sprintf(
				/* translators: %d: refund ID. */
				__( 'Refund for order #%d', 'easy-digital-downloads' ),
				$order->id
			);

			// Partial refund.
			if ( abs( $refund_object->total ) !== abs( $order->total ) ) {
				$body['amount'] = array(
					'currency_code' => $refund_object->currency,
					'value'         => (string) abs( $refund_object->total ),
				);
			}
		}

		$proxy    = new V3\ConnectAPI();
		$response = $proxy->post( '/v3/paypal/orders/' . urlencode( $transaction_id ) . '/refund', $body );

		if ( V3\ConnectAPI::is_error( $response ) ) {
			edd_debug_log( 'PayPal v3 refund failed: ' . V3\ConnectAPI::get_error_message( $response ) );
			return false;
		}

		return $response;
	}

	/**
	 * Verifies a Connect webhook signature (for v3 relayed webhooks).
	 *
	 * @since 3.6.9
	 *
	 * @param string $body      The raw request body.
	 * @param array  $headers   The request headers (server format: HTTP_X_EDD_*).
	 * @param string $hmac_key  The store's HMAC key.
	 * @return bool True if the signature is valid.
	 */
	public static function verify_connect_webhook_signature( $body, $headers, $hmac_key ) {
		$timestamp  = isset( $headers['HTTP_X_EDD_TIMESTAMP'] ) ? $headers['HTTP_X_EDD_TIMESTAMP'] : '';
		$webhook_id = isset( $headers['HTTP_X_EDD_WEBHOOK_ID'] ) ? $headers['HTTP_X_EDD_WEBHOOK_ID'] : '';
		$signature  = isset( $headers['HTTP_X_EDD_PROXY_SIGNATURE'] ) ? $headers['HTTP_X_EDD_PROXY_SIGNATURE'] : '';

		if ( empty( $timestamp ) || empty( $webhook_id ) || empty( $signature ) ) {
			return false;
		}

		// Check timestamp tolerance (5 minutes).
		if ( abs( time() - intval( $timestamp ) ) > 300 ) {
			return false;
		}

		$body_hash = hash( 'sha256', $body );
		$message   = sprintf(
			'%s.%s.POST./wp-json/edd/webhooks/v1/paypal.%s',
			$timestamp,
			$webhook_id,
			$body_hash
		);

		$expected = hash_hmac( 'sha256', $message, $hmac_key );

		return hash_equals( $expected, $signature );
	}

	/**
	 * Determines if the current webhook request is from the Connect service (v3).
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_connect_webhook() {
		return isset( $_SERVER['HTTP_X_EDD_INTEGRATION_TYPE'] )
			&& 'third_party' === $_SERVER['HTTP_X_EDD_INTEGRATION_TYPE'];
	}

	/**
	 * Gets the HMAC key for webhook verification.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public static function get_webhook_hmac_key() {
		return V3\Credentials::get_hmac_key( Gateway::get_paypal_mode() );
	}

	/**
	 * Checks if a v3 store is ready to accept payments.
	 *
	 * For v3 stores, we check that the store ID and HMAC key are set,
	 * and that a merchant ID is stored.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public static function is_v3_ready() {
		if ( 'v3' !== CommerceVersion::get_version() ) {
			return false;
		}

		$mode        = Gateway::get_paypal_mode();
		$store_id    = get_option( "edd_paypal_{$mode}_store_id", '' );
		$hmac_key    = get_option( "edd_paypal_{$mode}_hmac_key", '' );
		$merchant_id = get_option( "edd_paypal_{$mode}_merchant_id", '' );

		return ! empty( $store_id ) && ! empty( $hmac_key ) && ! empty( $merchant_id );
	}
}
