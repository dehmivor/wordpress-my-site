<?php
/**
 * PayPal V3 admin account status renderer.
 *
 * Builds the connection status list shown on the PayPal settings page when
 * the v3 (3rd-party) integration is active. Fetches live state from the
 * EDD Connect, performs the license-resync self-heal, and renders an
 * `<li>` per check (connected account, payment status, platform fee, ACDC,
 * vaulting, webhook, Apple Pay, gateway active).
 *
 * @package     EDD\Gateways\PayPal\V3\Admin
 * @copyright   Copyright (c) 2026, Sandhills Development, LLC
 * @license     https://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.6.9
 */

namespace EDD\Gateways\PayPal\V3\Admin;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit; // @codeCoverageIgnore

use EDD\Gateways\PayPal;
use EDD\Gateways\PayPal\V3\ConnectSync;
use EDD\Gateways\PayPal\V3\Merchant;
use EDD\Gateways\PayPal\V3\MerchantStatus;
use EDD\Gateways\PayPal\V3\Onboarding;
use EDD\Gateways\PayPal\V3\ConnectAPI;
use EDD\Gateways\PayPal\V3\ApplePay\DomainAssociation;

/**
 * Account
 *
 * @since 3.6.9
 */
class Account {

	/**
	 * PayPal mode — `live` or `sandbox`.
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Translated mode label for the connected-account line.
	 *
	 * @var string
	 */
	private $mode_label;

	/**
	 * Store ID stored locally for the current mode.
	 *
	 * @var string
	 */
	private $store_id;

	/**
	 * Latest store-status response from the Connect service, or null when unreachable.
	 *
	 * @var array|null
	 */
	private $proxy_status;

	/**
	 * Merchant row resolved from the Connect response (or the legacy `merchant`
	 * field), matching the current admin mode.
	 *
	 * @var array|null
	 */
	private $current_merchant;

	/**
	 * Merchant ID for the current mode, from the merchant row or wp_options.
	 *
	 * @var string
	 */
	private $merchant_id;

	/**
	 * Seller email for the current mode, from the merchant row or wp_options.
	 *
	 * @var string
	 */
	private $seller_email;

	/**
	 * Whether the merchant is able to receive payments.
	 *
	 * @var bool
	 */
	private $payments_receivable;

	/**
	 * Whether the merchant's primary email is confirmed.
	 *
	 * @var bool
	 */
	private $primary_email_confirmed;

	/**
	 * Capabilities granted to the merchant, from the merchant row or wp_options.
	 *
	 * @var array
	 */
	private $granted_scopes;

	/**
	 * Whether webhooks are active for the connection.
	 *
	 * @var bool|null
	 */
	private $webhooks_active;

	/**
	 * Overall status severity. `success` upgrades to `warning` when any
	 * individual item reports a non-success state; `error` is set by the
	 * connected-account check when there's no merchant ID.
	 *
	 * @var string
	 */
	private $status = 'success';

	/**
	 * Constructor.
	 *
	 * Resolves the current mode, fetches Connect status, runs the
	 * license-resync self-heal when applicable, and materializes the
	 * per-mode merchant row.
	 *
	 * @since 3.6.9
	 */
	public function __construct() {
		$this->mode       = PayPal\Gateway::get_paypal_mode();
		$this->mode_label = edd_is_test_mode() ? __( 'sandbox', 'easy-digital-downloads' ) : __( 'live', 'easy-digital-downloads' );
		$this->store_id   = get_option( "edd_paypal_{$this->mode}_store_id", '' );

		$this->fetch_connect_status();
		$this->maybe_self_heal_license();
		$this->resolve_merchant();
		$this->persist_seller_email();
	}

	/**
	 * Returns the resolved PayPal mode (live or sandbox).
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public function get_mode() {
		return $this->mode;
	}

	/**
	 * Whether a merchant ID is known for the current mode.
	 *
	 * Used by the settings page to decide whether to render the
	 * refresh-merchant-status button alongside the disconnect link.
	 *
	 * @since 3.6.9
	 *
	 * @return bool
	 */
	public function has_merchant_id() {
		return ! empty( $this->merchant_id );
	}

	/**
	 * Overall status severity for the connection.
	 *
	 * @since 3.6.9
	 *
	 * @return string One of `success`, `warning`, or `error`.
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Renders the full status list as a `<ul>`.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	public function render() {
		$items = array(
			$this->build_account_item(),
			$this->build_payment_status_item(),
			$this->build_platform_fee_item(),
			$this->build_acdc_item(),
			$this->build_vault_item(),
			$this->build_webhook_item(),
			$this->build_applepay_item(),
			$this->build_gateway_active_item(),
		);

		return '<ul class="edd-paypal-account-status">' . implode( '', array_filter( $items ) ) . '</ul>';
	}

	/**
	 * Builds a single status `<li>`.
	 *
	 * @since 3.6.9
	 *
	 * @param string $dashicon     Dashicon suffix (yes, no, warning).
	 * @param string $message      HTML message body (already wp_kses-prepared by the caller through $allowed_html).
	 * @param array  $allowed_html Tags/attributes allowed in $message (passed to wp_kses).
	 * @param string $extra_html   Optional extra HTML (e.g. button) appended after the message, escaped by the caller.
	 * @return string
	 */
	private function list_item( $dashicon, $message, array $allowed_html, $extra_html = '' ) {
		$html  = '<li>';
		$html .= '<span class="dashicons dashicons-' . esc_attr( $dashicon ) . '"></span>';
		$html .= '<span>' . wp_kses( $message, $allowed_html );
		if ( '' !== $extra_html ) {
			$html .= $extra_html;
		}
		$html .= '</span>';
		$html .= '</li>';

		return $html;
	}

	/**
	 * Bumps the overall status severity, but never lowers it.
	 *
	 * @since 3.6.9
	 *
	 * @param string $severity One of `warning`, `error`.
	 * @return void
	 */
	private function bump_status( $severity ) {
		if ( 'error' === $this->status ) {
			return;
		}
		if ( 'error' === $severity ) {
			$this->status = 'error';
			return;
		}
		if ( 'warning' === $severity && 'success' === $this->status ) {
			$this->status = 'warning';
		}
	}

	/**
	 * Fetches the Connect store-status response and stores it on the instance.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	private function fetch_connect_status() {
		if ( empty( $this->store_id ) ) {
			return;
		}

		$api      = new ConnectAPI( $this->mode );
		$response = $api->get( '/v3/stores/' . $this->store_id . '/status' );

		if ( is_wp_error( $response ) || ConnectAPI::is_error( $response ) ) {
			$this->proxy_status = null;
			return;
		}

		$this->proxy_status = $response;
	}

	/**
	 * Self-heals the Connect service's license cache when it reports `unknown` but a
	 * Pro license is active locally. Rate-limited to once per 5 minutes per
	 * mode so settings page loads can't hammer the licensing endpoint.
	 *
	 * Common cause: a transient network failure on the Connect service's first
	 * validateLicense() call leaves the store's license status stuck on `unknown`
	 * until something triggers another refresh.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	private function maybe_self_heal_license() {
		if ( ! is_array( $this->proxy_status ) ) {
			return;
		}
		if ( 'unknown' !== ( $this->proxy_status['license_status'] ?? '' ) ) {
			return;
		}
		if ( empty( Onboarding::get_license_key() ) ) {
			return;
		}
		$lock_key = "edd_paypal_{$this->mode}_license_resync_lock";
		if ( false !== get_transient( $lock_key ) ) {
			return;
		}

		set_transient( $lock_key, 1, 5 * MINUTE_IN_SECONDS );
		edd_debug_log( sprintf( 'PayPal v3: Connect API reports license_status=unknown in %s mode while a Pro license is active locally — triggering refresh-license self-heal.', $this->mode ) );
		( new ConnectSync() )->sync_license();

		// Re-fetch status so the rest of this render reflects the post-resync result.
		$api       = new ConnectAPI( $this->mode );
		$refreshed = $api->get( '/v3/stores/' . $this->store_id . '/status' );
		if ( is_array( $refreshed ) && ! ConnectAPI::is_error( $refreshed ) ) {
			$this->proxy_status = $refreshed;
		}
	}

	/**
	 * Resolves the merchant row for the current mode and materializes the
	 * downstream identifiers/capability flags used by the status items.
	 *
	 * Prefers the `merchants[]` array (one row per connected mode), falling
	 * back to the legacy `merchant` field that mirrors the row matching the
	 * X-EDD-PayPal-Mode header. This keeps the panel rendering correctly
	 * when the current admin mode is not the connected mode.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	private function resolve_merchant() {
		$this->current_merchant = null;

		if ( is_array( $this->proxy_status['merchants'] ?? null ) ) {
			foreach ( $this->proxy_status['merchants'] as $candidate ) {
				if ( ( $candidate['mode'] ?? '' ) === $this->mode ) {
					$this->current_merchant = $candidate;
					break;
				}
			}
		}
		if ( null === $this->current_merchant && is_array( $this->proxy_status['merchant'] ?? null ) ) {
			$this->current_merchant = $this->proxy_status['merchant'];
		}

		$merchant                      = $this->current_merchant ?? array();
		$this->merchant_id             = $merchant['merchant_id'] ?? get_option( "edd_paypal_{$this->mode}_merchant_id", '' );
		$this->seller_email            = $merchant['primary_email'] ?? get_option( "edd_paypal_{$this->mode}_seller_email", '' );
		$this->payments_receivable     = $merchant['payments_receivable'] ?? ! empty( get_option( "edd_paypal_{$this->mode}_capabilities", array() ) );
		$this->primary_email_confirmed = $merchant['primary_email_confirmed'] ?? true;
		$this->granted_scopes          = $merchant['capabilities'] ?? get_option( "edd_paypal_{$this->mode}_capabilities", array() );
		$this->webhooks_active         = $this->proxy_status['webhooks_active'] ?? null;
	}

	/**
	 * Persists the latest seller email locally so future page loads have it
	 * even when the Connect status fetch fails.
	 *
	 * @since 3.6.9
	 *
	 * @return void
	 */
	private function persist_seller_email() {
		if ( empty( $this->current_merchant['primary_email'] ) ) {
			return;
		}
		update_option( "edd_paypal_{$this->mode}_seller_email", sanitize_email( $this->current_merchant['primary_email'] ) );
	}

	/**
	 * Connected Account item.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function build_account_item() {
		if ( ! empty( $this->merchant_id ) ) {
			$identifier = ! empty( $this->seller_email ) ? $this->seller_email : $this->merchant_id;
			$message    = sprintf(
				/* translators: 1: mode label (sandbox or live), 2: seller PayPal email or merchant ID */
				__( '<strong>Connected Account:</strong> Your PayPal account is connected in %1$s mode as %2$s', 'easy-digital-downloads' ),
				esc_html( $this->mode_label ),
				'<code>' . esc_html( $identifier ) . '</code>'
			);

			return $this->list_item(
				'yes',
				$message,
				array(
					'strong' => array(),
					'code'   => array(),
				)
			);
		}

		$this->bump_status( 'error' );

		return $this->list_item(
			'no',
			__( '<strong>Connected Account:</strong> Your PayPal account is not connected. Please reconnect your PayPal account.', 'easy-digital-downloads' ),
			array( 'strong' => array() )
		);
	}

	/**
	 * Payment Status item.
	 *
	 * Identifies the specific blocker preventing the seller from accepting
	 * payments so the admin can act on it (PayPal IWT requirement: surface
	 * unconfirmed email, non-receivable account, and missing partner scopes
	 * individually).
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function build_payment_status_item() {
		$blocking_messages = array();
		if ( ! $this->primary_email_confirmed ) {
			$blocking_messages[] = __( 'Your PayPal primary email address is not yet confirmed. Please confirm it in your PayPal account before accepting payments.', 'easy-digital-downloads' );
		}
		if ( ! $this->payments_receivable ) {
			$blocking_messages[] = __( 'Your PayPal account is currently unable to receive payments. Please sign in to PayPal to resolve any holds or restrictions.', 'easy-digital-downloads' );
		}
		if ( empty( $this->granted_scopes ) ) {
			$blocking_messages[] = __( 'Required permissions were not granted to Easy Digital Downloads during onboarding. Please disconnect and reconnect your PayPal account, accepting all requested permissions.', 'easy-digital-downloads' );
		}

		if ( empty( $blocking_messages ) ) {
			return $this->list_item(
				'yes',
				__( '<strong>Payment Status:</strong> Ready to accept payments.', 'easy-digital-downloads' ),
				array( 'strong' => array() )
			);
		}

		$this->bump_status( 'warning' );

		return $this->list_item(
			'no',
			sprintf(
				/* translators: %s: One or more reasons the PayPal account cannot currently accept payments. */
				__( '<strong>Payment Status:</strong> %s', 'easy-digital-downloads' ),
				implode( ' ', $blocking_messages )
			),
			array( 'strong' => array() )
		);
	}

	/**
	 * Platform Fee item — only rendered when the Connect service reports a non-zero fee rate.
	 *
	 * The CTA branches on Pass_Manager::has_pass() rather than edd_is_pro()
	 * so a Pro install with no pass activated sees "Upgrade to Pro" instead
	 * of a license CTA they can't act on.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function build_platform_fee_item() {
		$fee_rate = $this->proxy_status['platform_fee_rate'] ?? 0;
		if ( $fee_rate <= 0 ) {
			return '';
		}

		$fee_percentage = round( $fee_rate * 100 );
		$message        = sprintf(
			/* translators: 1: opening strong tag, 2: closing strong tag, 3: fee percentage. */
			__( '%1$sPay as you go pricing:%2$s %3$d%% per-transaction fee + PayPal fees.', 'easy-digital-downloads' ),
			'<strong>',
			'</strong>',
			$fee_percentage
		);

		$pass_manager = new \EDD\Admin\Pass_Manager();
		if ( ! $pass_manager->has_pass() ) {
			$message .= ' ' . sprintf(
				/* translators: 1: opening link tag, 2: closing link tag. */
				__( '%1$sUpgrade to Pro%2$s to remove transaction fees.', 'easy-digital-downloads' ),
				'<a href="' . esc_url(
					edd_link_helper(
						'https://easydigitaldownloads.com/pricing/',
						array(
							'utm_medium'  => 'paypal-settings',
							'utm_content' => 'upgrade-to-pro',
						)
					)
				) . '" target="_blank">',
				'</a>'
			);
		} else {
			$message .= ' ' . sprintf(
				/* translators: 1: opening link tag, 2: closing link tag. */
				__( '%1$sActivate or upgrade your license%2$s to remove transaction fees.', 'easy-digital-downloads' ),
				'<a href="' . esc_url(
					edd_get_admin_url(
						array(
							'page' => 'edd-settings',
							'tab'  => 'general',
						)
					)
				) . '">',
				'</a>'
			);
		}

		$this->bump_status( 'warning' );

		return $this->list_item(
			'warning',
			$message,
			array(
				'strong' => array(),
				'a'      => array(
					'href'   => array(),
					'target' => array(),
				),
			)
		);
	}

	/**
	 * ACDC (Advanced Card Processing) vetting status.
	 *
	 * PayPal's product name for Advanced Card Processing is `PPCP_CUSTOM` —
	 * there is no plain `PPCP` entry in the products array
	 * (`PPCP_STANDARD` is the hosted flow).
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function build_acdc_item() {
		return $this->build_vetting_item( 'PPCP_CUSTOM', __( 'Advanced Card Payments:', 'easy-digital-downloads' ) );
	}

	/**
	 * Vaulting vetting status.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function build_vault_item() {
		return $this->build_vetting_item( 'ADVANCED_VAULTING', __( 'Vaulting:', 'easy-digital-downloads' ) );
	}

	/**
	 * Shared renderer for PayPal product vetting items (ACDC, Vaulting).
	 *
	 * Routes through Onboarding so we hit the 4-hour transient cache and
	 * don't fire a live PayPal request on every settings page load.
	 *
	 * @since 3.6.9
	 *
	 * @param string $product_name PayPal product identifier (e.g. PPCP_CUSTOM, ADVANCED_VAULTING).
	 * @param string $prefix       Translated label prefix shown to admins (e.g. "Vaulting:").
	 * @return string
	 */
	private function build_vetting_item( $product_name, $prefix ) {
		if ( empty( $this->merchant_id ) || ! $this->payments_receivable ) {
			return '';
		}

		$merchant_status = Merchant::get_status( $this->merchant_id, $this->mode );
		if ( is_wp_error( $merchant_status ) || ConnectAPI::is_error( $merchant_status ) ) {
			return '';
		}

		$vetting_status = MerchantStatus::get_product_vetting_status( $merchant_status, $product_name );
		if ( null === $vetting_status ) {
			return '';
		}

		$dashicon     = 'SUBSCRIBED' === $vetting_status ? 'yes' : 'warning';
		$status_label = MerchantStatus::get_vetting_status_label( $vetting_status );

		if ( 'SUBSCRIBED' !== $vetting_status ) {
			$this->bump_status( 'warning' );
		}

		return $this->list_item(
			$dashicon,
			'<strong>' . esc_html( $prefix ) . '</strong> ' . esc_html( $status_label ),
			array( 'strong' => array() )
		);
	}

	/**
	 * Webhook item — shows whether EDD Connect's webhook is delivering events.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function build_webhook_item() {
		if ( null === $this->webhooks_active ) {
			// Connect service unreachable — show a neutral message.
			return $this->list_item(
				'yes',
				'<strong>' . __( 'Webhook:', 'easy-digital-downloads' ) . '</strong> ' . __( 'Managed by EDD Connect. No additional configuration needed.', 'easy-digital-downloads' ),
				array( 'strong' => array() )
			);
		}

		if ( ! $this->webhooks_active ) {
			$this->bump_status( 'warning' );
		}

		$message  = '<strong>' . __( 'Webhook:', 'easy-digital-downloads' ) . '</strong> ';
		$message .= $this->webhooks_active
			? __( 'Managed by EDD Connect. Receiving events.', 'easy-digital-downloads' )
			: __( 'Managed by EDD Connect. Webhook is not currently active.', 'easy-digital-downloads' );

		return $this->list_item(
			$this->webhooks_active ? 'yes' : 'no',
			$message,
			array( 'strong' => array() )
		);
	}

	/**
	 * Apple Pay domain registration item.
	 *
	 * Surfaces the registration state and offers a Re-verify action only
	 * when Apple Pay is enabled and the store is connected — admins can use
	 * it to force PayPal to re-run Apple's domain validation if the
	 * merchant gets into a "PayPal thinks the domain is registered, but
	 * Apple never validated" state.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function build_applepay_item() {
		if ( empty( $this->merchant_id ) || edd_is_dev_environment() || edd_is_test_mode() ) {
			return '';
		}
		if ( ! class_exists( '\\EDD\\Gateways\\PayPal\\PaymentMethods' ) ) {
			return '';
		}
		if ( ! PayPal\PaymentMethods::is_enabled( 'apple_pay', true ) ) {
			return '';
		}

		$host  = get_option( DomainAssociation::HOST_OPTION, '' );
		$error = get_option( DomainAssociation::ERROR_OPTION, '' );

		if ( ! empty( $error ) ) {
			$dashicon = 'no';
			$this->bump_status( 'warning' );
			$message = sprintf(
				/* translators: %s: Apple Pay domain registration error message returned from PayPal. */
				__( '<strong>Apple Pay:</strong> Domain registration failed — %s', 'easy-digital-downloads' ),
				esc_html( $error )
			);
		} elseif ( ! empty( $host ) ) {
			$dashicon = 'yes';
			$message  = sprintf(
				/* translators: %s: registered domain (e.g. example.com). */
				__( '<strong>Apple Pay:</strong> Domain %s registered with PayPal.', 'easy-digital-downloads' ),
				'<code>' . esc_html( $host ) . '</code>'
			);
		} else {
			$dashicon = 'warning';
			$this->bump_status( 'warning' );
			$message = __( '<strong>Apple Pay:</strong> Domain not yet registered with PayPal.', 'easy-digital-downloads' );
		}

		$button = sprintf(
			'<br><button type="button" class="button button-small edd-paypal-connect-action" style="margin-top:8px;" data-nonce="%1$s" data-action="edd_paypal_v3_applepay_reverify">%2$s</button>',
			esc_attr( wp_create_nonce( 'edd_paypal_v3_applepay_reverify' ) ),
			esc_html__( 'Re-verify Domain', 'easy-digital-downloads' )
		);

		return $this->list_item(
			$dashicon,
			$message,
			array(
				'strong' => array(),
				'code'   => array(),
			),
			$button
		);
	}

	/**
	 * Gateway Active item — surfaces a prominent error when the PayPal
	 * gateway is connected but not enabled in EDD's gateway settings.
	 *
	 * @since 3.6.9
	 *
	 * @return string
	 */
	private function build_gateway_active_item() {
		if ( edd_is_gateway_active( 'paypal_commerce' ) ) {
			return '';
		}

		$this->bump_status( 'warning' );

		$url = esc_url( admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways&section=main' ) );

		return $this->list_item(
			'no',
			sprintf(
				/* translators: 1: opening anchor tag, 2: closing anchor tag. */
				__( '<strong>Gateway Status:</strong> PayPal is not currently active. %1$sEnable PayPal%2$s in the general gateway settings to start using it.', 'easy-digital-downloads' ),
				'<a href="' . $url . '">',
				'</a>'
			),
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		);
	}
}
