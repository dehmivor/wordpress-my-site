<?php
/**
 * PayPal Vault operations.
 *
 * Handles saving, listing, deleting, and charging with vaulted payment methods
 * through the ConnectAPI. All vault operations require v3 commerce version.
 *
 * @package     EDD\Gateways\PayPal\V3
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Database\Queries\PaymentToken as PaymentTokenQuery;
use EDD\Database\Rows\PaymentToken;
use EDD\Gateways\PayPal\BrandName;
use EDD\Gateways\PayPal\CommerceVersion;
use EDD\Gateways\PayPal\Gateway;

/**
 * Vault class.
 *
 * Handles saving, retrieving, and charging PayPal vault tokens through EDD's
 * payment tokens system. Customer-facing management of saved payment methods
 * is intentionally not exposed: letting a buyer delete a token from EDD would
 * silently break any subscription billed against it (the same pitfall the
 * Stripe gateway hit when it moved from customer-level cards to
 * per-subscription payment methods). Tokens are stored automatically at
 * checkout when PayPal returns vault data, used by Recurring for renewals,
 * and removed via the upstream VAULT.PAYMENT-TOKEN.DELETED webhook.
 *
 * @since 3.6.9
 */
class Vault {

	/**
	 * Maximum number of saved tokens to list for a customer.
	 *
	 * @since 3.6.9
	 */
	const TOKEN_LIST_LIMIT = 100;

	/**
	 * Creates a vault setup token for standalone vaulting.
	 *
	 * @since 3.6.9
	 *
	 * @param int    $customer_id EDD customer ID.
	 * @param string $return_url  URL to redirect after approval.
	 * @param string $cancel_url  URL to redirect on cancel.
	 * @param array  $args        Optional. Additional request shaping:
	 *                            - 'brand_name'   string Merchant name displayed on the PayPal approval screen.
	 *                            - 'billing_plan' array  Billing plan describing the recurring schedule the buyer is agreeing to.
	 * @return array|false Setup token response or false on failure.
	 */
	public static function create_setup_token( $customer_id, $return_url, $cancel_url, $args = array() ) {
		$proxy = new ConnectAPI();

		$body = array(
			'payment_source' => array(
				'paypal' => array(
					'usage_type'         => 'MERCHANT',
					'customer_type'      => 'CONSUMER',
					'usage_pattern'      => 'SUBSCRIPTION_PREPAID',
					'experience_context' => array(
						'return_url'          => $return_url,
						'cancel_url'          => $cancel_url,
						'shipping_preference' => 'NO_SHIPPING',
						'vault_instruction'   => 'ON_CREATE_PAYMENT_TOKENS',
					),
				),
			),
		);

		// Always required — PayPal rejects an empty string with INVALID_STRING_LENGTH.
		$brand_name = ! empty( $args['brand_name'] ) ? $args['brand_name'] : BrandName::get();
		$body['payment_source']['paypal']['experience_context']['brand_name'] = sanitize_text_field( $brand_name );

		// Attach a billing_plan so the buyer sees what they're agreeing to
		// (trial period + future recurring amount). Mirrors the same field
		// the vault-with-purchase flow sends in `add_vault_order_attributes`.
		if ( ! empty( $args['billing_plan'] ) && is_array( $args['billing_plan'] ) ) {
			$body['payment_source']['paypal']['billing_plan'] = $args['billing_plan'];
		}

		// If the customer already has a PayPal vault customer ID, pass it.
		$paypal_customer_id = Customer::get_id( $customer_id );
		if ( ! empty( $paypal_customer_id ) ) {
			$body['customer'] = array(
				'id' => $paypal_customer_id,
			);
		}

		edd_debug_log( 'PayPal Vault: setup-tokens body = ' . wp_json_encode( $body ) );

		$response = $proxy->post( '/v3/paypal/vault/setup-tokens', $body );

		if ( ConnectAPI::is_error( $response ) ) {
			edd_debug_log(
				sprintf(
					'PayPal Vault: Failed to create setup token. code=%s, message=%s, debug_id=%s, raw=%s',
					ConnectAPI::get_error_code( $response ),
					ConnectAPI::get_error_message( $response ),
					ConnectAPI::get_paypal_debug_id( $response ) ? ConnectAPI::get_paypal_debug_id( $response ) : 'n/a',
					wp_json_encode( $response )
				)
			);
			return false;
		}

		return $response;
	}

	/**
	 * Creates a payment token from an approved setup token.
	 *
	 * @since 3.6.9
	 *
	 * @param string $setup_token_id The approved setup token ID.
	 * @param int    $customer_id    EDD customer ID.
	 * @return array|false Payment token response or false on failure.
	 */
	public static function create_payment_token( $setup_token_id, $customer_id ) {
		$proxy = new ConnectAPI();

		$body = array(
			'payment_source' => array(
				'token' => array(
					'id'   => $setup_token_id,
					'type' => 'SETUP_TOKEN',
				),
			),
		);

		$response = $proxy->post( '/v3/paypal/vault/payment-tokens', $body );

		if ( ConnectAPI::is_error( $response ) ) {
			edd_debug_log( 'PayPal Vault: Failed to create payment token. Error: ' . ConnectAPI::get_error_message( $response ) );
			return false;
		}

		// Save the token locally.
		self::save_token_from_response( $response, $customer_id );

		return $response;
	}

	/**
	 * Saves a payment token to the local database from a PayPal response.
	 *
	 * @since 3.6.9
	 *
	 * @param array $response    The PayPal payment token response.
	 * @param int   $customer_id EDD customer ID.
	 * @return int|false The local token ID or false on failure.
	 */
	public static function save_token_from_response( $response, $customer_id ) {
		if ( empty( $response['id'] ) ) {
			return false;
		}

		$paypal_customer_id = ! empty( $response['customer']['id'] ) ? $response['customer']['id'] : '';
		$token_id           = $response['id'];
		$mode               = Gateway::get_paypal_mode();

		// The PayPal customer ID is the link between an EDD customer and
		// their saved methods on PayPal's side, but it is not always
		// returned with the vault response (e.g. when PayPal creates a
		// brand-new vault token for a guest-then-logged-in flow). The
		// token itself is still valid for renewals — Recurring charges
		// against the vault_id directly via /v3/paypal/orders/recurring
		// without needing the customer ID. Log so the merchant can audit
		// if it shows up repeatedly, but don't drop the token.
		if ( empty( $paypal_customer_id ) ) {
			edd_debug_log(
				sprintf(
					'PayPal Vault: save_token_from_response called without customer.id for token %s, EDD customer %d. Token will be saved without the PayPal customer linkage.',
					$token_id,
					(int) $customer_id
				)
			);
		} else {
			// Save the PayPal customer ID to customer meta if not already set.
			Customer::save_id( $customer_id, $paypal_customer_id );
		}

		// Dedup — the edd_payment_tokens table is the customer's wallet,
		// not a per-subscription log. PayPal returns the same vault id when
		// the same card is vaulted for the same buyer multiple times, and
		// several code paths (standalone vault setup, payment-method update,
		// initial-purchase webhook) can each call this method for the same
		// underlying token. Without this check we'd insert N duplicate rows
		// that all render as identical entries in Saved Payment Methods.
		$existing = new PaymentTokenQuery(
			array(
				'customer_id' => $customer_id,
				'gateway'     => 'paypal',
				'token_id'    => $token_id,
				'mode'        => $mode,
				'number'      => 1,
			)
		);
		if ( ! empty( $existing->items ) ) {
			$existing_token = $existing->items[0];

			// If the existing row was soft-deleted, re-activate it instead
			// of leaving the customer stranded with no usable wallet entry.
			if ( 'active' !== $existing_token->status ) {
				$query = new PaymentTokenQuery();
				$query->update_item( $existing_token->id, array( 'status' => 'active' ) );
			}

			return $existing_token->id;
		}

		$label          = self::build_label_from_payment_source( $response );
		$type           = self::detect_type_from_payment_source( $response );
		$payment_source = ! empty( $response['payment_source'] ) ? wp_json_encode( $response['payment_source'] ) : '';

		$query = new PaymentTokenQuery();

		return $query->add_item(
			array(
				'customer_id'         => $customer_id,
				'gateway'             => 'paypal',
				'token_id'            => $token_id,
				'gateway_customer_id' => $paypal_customer_id,
				'type'                => $type,
				'label'               => $label,
				'payment_source'      => $payment_source,
				'mode'                => $mode,
				'status'              => 'active',
			)
		);
	}

	/**
	 * Extracts, stamps, and persists vault data from a v3 capture response.
	 *
	 * The single capture-side vault path shared by every v3 flow (smart-button
	 * checkout, Fastlane, and the on-checkout card fields). It stamps the vault
	 * ids onto order meta, persists the token row for a logged-in buyer, and
	 * fires `edd_paypal_v3_order_vaulted` — replacing the near-identical blocks
	 * those flows each carried, where the vault customer.id handling had drifted.
	 *
	 * MUST run before the order is moved to a complete status, because
	 * `maybe_create_vault_subscriptions` reads the `_edd_paypal_vault_id` order
	 * meta stamped here to seed subscription meta synchronously.
	 *
	 * @since 3.6.9
	 *
	 * @param int    $order_id       EDD order ID.
	 * @param string $transaction_id PayPal capture ID for this order.
	 * @param array  $response       Capture response, normalized to an array.
	 * @return array {
	 *     The extracted vault ids (empty strings when the capture did not vault).
	 *
	 *     @type string $vault_id          PayPal vault token ID.
	 *     @type string $vault_customer_id PayPal vault customer ID.
	 * }
	 */
	public static function persist_from_capture( $order_id, $transaction_id, array $response ) {
		$vault             = self::extract_vault_ids( $response );
		$vault_id          = $vault['vault_id'];
		$vault_customer_id = $vault['vault_customer_id'];

		if ( '' === $vault_id ) {
			return $vault;
		}

		// Stamp the vault data onto order meta so any downstream listener
		// (subscription meta writers, audit, etc.) can resolve it without
		// re-parsing the PayPal response.
		edd_update_order_meta( $order_id, '_edd_paypal_vault_id', $vault_id );
		if ( '' !== $vault_customer_id ) {
			edd_update_order_meta( $order_id, '_edd_paypal_vault_customer_id', $vault_customer_id );
		}

		// Persist the vault row whenever the buyer is logged in and PayPal
		// returned vault data. There's no customer-facing opt-in — Recurring
		// needs the token stored for renewals and the buyer has no UI to manage
		// saved methods, so there's nothing to gate against.
		if ( is_user_logged_in() ) {
			$customer = edd_get_customer_by( 'user_id', get_current_user_id() );
			if ( $customer ) {
				$payload = array(
					'id'             => $vault_id,
					'payment_source' => ! empty( $response['payment_source'] ) ? $response['payment_source'] : array(),
				);

				// Only pass the customer block when PayPal returned an ID for
				// it — an empty customer.id gets logged as a missing linkage
				// even when we know there is nothing to link.
				if ( '' !== $vault_customer_id ) {
					$payload['customer'] = array( 'id' => $vault_customer_id );
				}

				self::save_token_from_response( $payload, $customer->id );

				if ( '' !== $vault_customer_id ) {
					Customer::save_id( $customer->id, $vault_customer_id );
				}
			}
		}

		/**
		 * Fires when a PayPal v3 capture returns vault data, after the data has
		 * been stamped onto order meta. Downstream components (notably EDD
		 * Recurring) listen for this to synchronously write `paypal_vault_id`
		 * onto subscription meta so renewals can charge even when the
		 * PAYMENT.CAPTURE.COMPLETED webhook fails to deliver.
		 *
		 * @since 3.6.9
		 *
		 * @param int    $order_id          EDD order ID.
		 * @param string $vault_id          PayPal vault token ID.
		 * @param string $vault_customer_id PayPal vault customer ID (may be empty).
		 * @param string $transaction_id    PayPal capture ID for this order.
		 */
		do_action( 'edd_paypal_v3_order_vaulted', $order_id, $vault_id, $vault_customer_id, $transaction_id );

		return $vault;
	}

	/**
	 * Extracts the vault token and customer ids from a capture response.
	 *
	 * PayPal places vault details under `payment_source.{type}.attributes.vault`.
	 * The PayPal wallet source is checked before the card source so a wallet
	 * capture that also carries card data resolves to the wallet token; the id
	 * and the customer id are always read from the same source so they can't be
	 * mixed across payment sources.
	 *
	 * Shared so EDD Recurring's PAYMENT.CAPTURE.COMPLETED webhook reads vault
	 * data from the same definition the synchronous capture paths use. Callers
	 * holding the resource as a stdClass (e.g. the webhook payload) must
	 * normalize it to an array first.
	 *
	 * @since 3.6.9
	 *
	 * @param array $response Capture response, normalized to an array.
	 * @return array {
	 *     @type string $vault_id          PayPal vault token ID, or empty string.
	 *     @type string $vault_customer_id PayPal vault customer ID, or empty string.
	 * }
	 */
	public static function extract_vault_ids( array $response ) {
		foreach ( array( 'paypal', 'card' ) as $source ) {
			if ( empty( $response['payment_source'][ $source ]['attributes']['vault']['id'] ) ) {
				continue;
			}

			$vault = $response['payment_source'][ $source ]['attributes']['vault'];

			return array(
				'vault_id'          => sanitize_text_field( $vault['id'] ),
				'vault_customer_id' => ! empty( $vault['customer']['id'] ) ? sanitize_text_field( $vault['customer']['id'] ) : '',
			);
		}

		return array(
			'vault_id'          => '',
			'vault_customer_id' => '',
		);
	}

	/**
	 * Gets active payment tokens for an EDD customer.
	 *
	 * @since 3.6.9
	 *
	 * @param int    $customer_id EDD customer ID.
	 * @param string $gateway     Gateway identifier. Default 'paypal'.
	 * @return PaymentToken[] Array of payment tokens.
	 */
	public static function get_tokens_for_customer( $customer_id, $gateway = 'paypal' ) {
		$query = new PaymentTokenQuery(
			array(
				'customer_id' => $customer_id,
				'gateway'     => $gateway,
				'status'      => 'active',
				'mode'        => Gateway::get_paypal_mode(),
				'orderby'     => 'date_created',
				'order'       => 'DESC',
				'number'      => self::TOKEN_LIST_LIMIT,
			)
		);

		return ! empty( $query->items ) ? $query->items : array();
	}

	/**
	 * Gets a single payment token by its gateway token ID.
	 *
	 * @since 3.6.9
	 *
	 * @param string $token_id The gateway token ID.
	 * @return PaymentToken|false The token object or false.
	 */
	public static function get_token_by_token_id( $token_id ) {
		$query = new PaymentTokenQuery(
			array(
				'token_id' => $token_id,
				'number'   => 1,
			)
		);

		return ! empty( $query->items[0] ) ? $query->items[0] : false;
	}

	/**
	 * Soft-deletes a payment token locally and deletes from PayPal vault.
	 *
	 * @since 3.6.9
	 *
	 * @param int $local_token_id The local database token ID.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_token( $local_token_id ) {
		$query = new PaymentTokenQuery();
		$token = $query->get_item( $local_token_id );

		if ( empty( $token ) ) {
			return false;
		}

		// Delete from PayPal vault via Connect.
		$proxy    = new ConnectAPI();
		$response = $proxy->delete( '/v3/paypal/vault/payment-tokens/' . $token->token_id );

		// Soft-delete locally regardless of remote result.
		$query->update_item(
			$local_token_id,
			array(
				'status' => 'deleted',
			)
		);

		if ( ConnectAPI::is_error( $response ) ) {
			$error_code = ConnectAPI::get_error_code( $response );

			// token_not_found means it was already deleted remotely — that is fine.
			if ( 'token_not_found' !== $error_code ) {
				edd_debug_log( 'PayPal Vault: Failed to delete remote token. Error: ' . ConnectAPI::get_error_message( $response ) );
				return false;
			}
		}

		return true;
	}

	/**
	 * Charges a vaulted payment method (merchant-initiated transaction).
	 *
	 * @since 3.6.9
	 *
	 * @param array $args {
	 *     Required arguments.
	 *
	 *     @type string $vault_id                       PayPal payment token ID.
	 *     @type string $amount                         Decimal string (e.g., '29.99').
	 *     @type string $currency_code                  ISO 4217 currency code.
	 *     @type string $custom_id                      EDD subscription or order ID.
	 *     @type string $description                    Charge description.
	 *     @type string $previous_transaction_reference Previous capture ID for recurring.
	 * }
	 * @return array|\WP_Error Capture response on success, WP_Error carrying
	 *                          the Connect error code/message on failure.
	 */
	public static function charge_vault( $args ) {
		$defaults = array(
			'vault_id'                       => '',
			'amount'                         => '',
			'currency_code'                  => edd_get_currency(),
			'custom_id'                      => '',
			'invoice_id'                     => '',
			'description'                    => '',
			'previous_transaction_reference' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		if ( empty( $args['vault_id'] ) || empty( $args['amount'] ) ) {
			return new \WP_Error(
				'invalid_arguments',
				__( 'A vault ID and amount are required to charge a vaulted payment method.', 'easy-digital-downloads' )
			);
		}

		$body = array(
			'vault_id'          => $args['vault_id'],
			'amount'            => $args['amount'],
			'currency_code'     => $args['currency_code'],
			'custom_id'         => $args['custom_id'],
			'description'       => $args['description'],
			'stored_credential' => array(
				'payment_initiator' => 'MERCHANT',
				'payment_type'      => 'RECURRING',
			),
		);

		if ( ! empty( $args['invoice_id'] ) ) {
			$body['invoice_id'] = $args['invoice_id'];
		}

		if ( ! empty( $args['previous_transaction_reference'] ) ) {
			$body['stored_credential']['previous_transaction_reference'] = $args['previous_transaction_reference'];
		}

		$proxy    = new ConnectAPI();
		$response = $proxy->post( '/v3/paypal/orders/recurring', $body );

		if ( is_wp_error( $response ) ) {
			edd_debug_log( 'PayPal Vault: Recurring charge failed. Error: ' . $response->get_error_message() );
			return $response;
		}

		if ( ConnectAPI::is_error( $response ) ) {
			$error_code    = ConnectAPI::get_error_code( $response );
			$error_message = ConnectAPI::get_error_message( $response );
			edd_debug_log( 'PayPal Vault: Recurring charge failed. Error: ' . $error_message );

			return new \WP_Error(
				$error_code ? $error_code : 'paypal_vault_charge_failed',
				$error_message,
				$response
			);
		}

		return $response;
	}

	/**
	 * Builds a human-readable label from the payment source response.
	 *
	 * @since 3.6.9
	 *
	 * @param array $response The PayPal payment token response.
	 * @return string
	 */
	private static function build_label_from_payment_source( $response ) {
		$payment_source = ! empty( $response['payment_source'] ) ? $response['payment_source'] : array();

		// PayPal account.
		if ( ! empty( $payment_source['paypal']['email_address'] ) ) {
			return sprintf( 'PayPal - %s', $payment_source['paypal']['email_address'] );
		}

		// Card.
		if ( ! empty( $payment_source['card']['last_digits'] ) ) {
			$brand = ! empty( $payment_source['card']['brand'] ) ? $payment_source['card']['brand'] : __( 'Card', 'easy-digital-downloads' );
			return sprintf( '%s ending in %s', ucfirst( strtolower( $brand ) ), $payment_source['card']['last_digits'] );
		}

		return __( 'PayPal', 'easy-digital-downloads' );
	}

	/**
	 * Detects the payment method type from the payment source.
	 *
	 * Shared so EDD Recurring's vault-trial flow resolves the source type from
	 * the same definition Pro uses when saving a token.
	 *
	 * @since 3.6.9
	 *
	 * @param array $response The PayPal payment token response.
	 * @return string 'paypal' or 'card'.
	 */
	public static function detect_type_from_payment_source( $response ) {
		$payment_source = ! empty( $response['payment_source'] ) ? $response['payment_source'] : array();

		if ( ! empty( $payment_source['card'] ) ) {
			return 'card';
		}

		return 'paypal';
	}
}
