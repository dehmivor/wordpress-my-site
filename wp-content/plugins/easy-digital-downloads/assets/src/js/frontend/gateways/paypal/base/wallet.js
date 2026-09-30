/* global eddPayPalVars, edd_scripts */

import { showPayPalError, clearPayPalErrors } from '../errors.js';

// Shared base for the PayPal wallet payment methods (Apple Pay, Google Pay).
//
// Apple Pay and Google Pay run the same order lifecycle — create a PayPal
// order via the Smart Buttons AJAX endpoint, open the wallet sheet, confirm
// the order with the wallet token, then capture through the standard approval
// AJAX — and share the same processing lock, spinner, and error surface. Only
// the eligibility/render/sheet logic and the wallet-type hint differ, so each
// wallet module spreads this base and overrides just `walletType`,
// `containerId`, `init`, and `onClick`.

/**
 * Base behavior shared by the PayPal wallet payment methods.
 *
 * Adapters set `walletType` (the `edd_paypal_wallet_type` hint sent at order
 * creation) and `containerId` (the wallet's button container, used as the
 * processing-lock fallback when the global submit wrap is absent).
 */
const WalletPaymentMethod = {
	walletType: '',
	containerId: '',

	/**
	 * Locks the entire payment-button stack and reveals the processing
	 * overlay so the buyer sees their click was accepted and can't re-click
	 * any sibling button during the server round-trip.
	 *
	 * @returns {void}
	 */
	startProcessing: function() {
		const wrap = document.getElementById( 'edd_purchase_submit' )
			|| document.getElementById( this.containerId )?.parentElement;
		if ( wrap ) {
			wrap.classList.add( 'edd-paypal-processing' );
		}
		this.clearErrors();
		this.showSpinner();
	},

	/**
	 * Removes the processing overlay so buttons are interactive again.
	 *
	 * @returns {void}
	 */
	stopProcessing: function() {
		const wrap = document.getElementById( 'edd_purchase_submit' )
			|| document.getElementById( this.containerId )?.parentElement;
		if ( wrap ) {
			wrap.classList.remove( 'edd-paypal-processing' );
		}
		this.hideSpinner();
	},

	/**
	 * Shows the existing PayPal checkout spinner.
	 *
	 * @returns {void}
	 */
	showSpinner: function() {
		const spinner = document.getElementById( 'edd-paypal-spinner' );
		if ( spinner ) {
			spinner.style.display = 'block';
		}
	},

	/**
	 * Hides the PayPal checkout spinner.
	 *
	 * @returns {void}
	 */
	hideSpinner: function() {
		const spinner = document.getElementById( 'edd-paypal-spinner' );
		if ( spinner ) {
			spinner.style.display = 'none';
		}
	},

	/**
	 * Renders an error message in the standard PayPal errors wrapper.
	 *
	 * @param {string} message Error message HTML or text.
	 * @returns {void}
	 */
	showError: function( message ) {
		showPayPalError( message );
	},

	/**
	 * Clears any displayed errors.
	 *
	 * @returns {void}
	 */
	clearErrors: function() {
		clearPayPalErrors();
	},

	/**
	 * Creates a PayPal order via the same AJAX endpoint the Smart Buttons use.
	 *
	 * Hints the wallet type to the server so the order's payment-source meta
	 * can be stamped at create time. The capture response from PayPal isn't
	 * always specific enough to distinguish a wallet from a regular card
	 * payment, so the receipt label could fall back to "PayPal" without it.
	 *
	 * On failure the resolved object carries an `error` (the server's
	 * pre-rendered error HTML when available, e.g. a mixed-cart validation
	 * failure) so the caller can surface the real reason instead of the
	 * generic fallback.
	 *
	 * @param {HTMLFormElement} form Checkout form.
	 * @returns {Promise<{orderId: string, total: string, currency: string}|{error: string}|null>}
	 */
	createPayPalOrder: async function( form ) {
		// Guard against a missing form (custom templates / unexpected DOM) —
		// FormData throws on a null argument. Callers treat null as a failed
		// order create.
		if ( ! form ) {
			return null;
		}

		const body = new FormData( form );
		body.append( 'edd_paypal_wallet_type', this.walletType );

		const response = await fetch( edd_scripts.ajaxurl, {
			method: 'POST',
			body,
		} );
		const data = await response.json();
		const orderId = data?.data?.paypal_order_id;
		if ( ! orderId ) {
			return { error: this.extractErrorMessage( data ) };
		}
		return {
			orderId,
			total:    data.data.total || '0.00',
			currency: data.data.currency || 'USD',
		};
	},

	/**
	 * Pulls the error message out of a failed order-create response.
	 *
	 * The Smart Buttons AJAX endpoint reports validation/gateway failures via
	 * `wp_send_json_error( edd_build_errors_html( ... ) )`, so the error HTML
	 * arrives as the `data` string. Mirrors the extraction the Smart Buttons
	 * `createOrder` handler performs.
	 *
	 * @param {Object|string} data Parsed JSON response.
	 * @returns {string} Error HTML/text, or an empty string when none is present.
	 */
	extractErrorMessage: function( data ) {
		if ( data && 'string' === typeof data.data ) {
			return data.data;
		}
		if ( 'string' === typeof data ) {
			return data;
		}
		return '';
	},

	/**
	 * Captures the order via the standard approval AJAX so the EDD-side order
	 * transitions to complete and the buyer is redirected to the receipt.
	 *
	 * @param {string} orderId PayPal order ID.
	 * @returns {Promise<void>}
	 */
	captureOrder: async function( orderId ) {
		const form    = document.getElementById( 'edd_purchase_form' );
		const nonceEl = form?.querySelector( 'input[name="edd_process_paypal_nonce"]' );
		const tokenEl = form?.querySelector( 'input[name="edd-process-paypal-token"]' );

		const data = new FormData();
		data.append( 'action', eddPayPalVars.approvalAction || 'edd_capture_paypal_order' );
		data.append( 'paypal_order_id', orderId );
		if ( nonceEl ) {
			data.append( 'edd_process_paypal_nonce', nonceEl.value );
		}
		if ( tokenEl ) {
			data.append( 'token', tokenEl.dataset.token );
			data.append( 'timestamp', tokenEl.dataset.timestamp );
		}

		const response = await fetch( edd_scripts.ajaxurl, { method: 'POST', body: data } );
		const result   = await response.json();

		if ( result?.success && result.data?.redirect_url ) {
			globalThis.location = result.data.redirect_url;
		}
	},
};

export default WalletPaymentMethod;
