/* global eddPayPalVars, google */

import WalletPaymentMethod from './base/wallet.js';

// EDD PayPal Google Pay integration — renders the Google Pay button on the
// checkout page and tokenizes via PayPal's Googlepay component.

/**
 * EDD PayPal Google Pay integration.
 *
 * Loads Google's `pay.js` library on first use, gates on `isReadyToPay`,
 * renders Google's official button via `PaymentsClient.createButton()`,
 * and on click creates a PayPal order, opens the Google Pay sheet, and
 * confirms the order with the resulting token. Falls back silently when
 * any prerequisite is missing.
 *
 * The processing lock, spinner, error surface, order creation, and capture
 * are inherited from WalletPaymentMethod; this module supplies only the
 * Google-Pay-specific eligibility, button render, and sheet flow.
 */
const EDDPayPalGooglePay = {
	...WalletPaymentMethod,

	walletType: 'google_pay',
	containerId: 'edd-paypal-googlepay-container',
	googlepay: null,
	config: null,
	paymentsClient: null,

	/**
	 * Bootstraps Google Pay if eligible.
	 */
	init: async function() {
		if ( typeof paypal === 'undefined' || typeof paypal.Googlepay !== 'function' ) {
			return;
		}

		const container = document.getElementById( this.containerId );
		if ( ! container ) {
			return;
		}

		try {
			this.googlepay = paypal.Googlepay();
			this.config    = await this.googlepay.config();

			if ( ! this.config.isEligible ) {
				return;
			}

			await this.loadGooglePayScript();

			this.paymentsClient = new google.payments.api.PaymentsClient( {
				environment: this.detectEnvironment(),
			} );

			const isReady = await this.paymentsClient.isReadyToPay( {
				apiVersion:       this.config.apiVersion,
				apiVersionMinor:  this.config.apiVersionMinor,
				allowedPaymentMethods: this.config.allowedPaymentMethods,
			} );

			if ( ! isReady?.result ) {
				return;
			}

			this.renderButton( container );
		} catch ( e ) {
			console.warn( 'EDD PayPal Google Pay unavailable:', e );
		}
	},

	/**
	 * Loads Google's `pay.js` library if it's not already on the page.
	 *
	 * @returns {Promise<void>}
	 */
	loadGooglePayScript: function() {
		if ( typeof google !== 'undefined' && google.payments?.api?.PaymentsClient ) {
			return Promise.resolve();
		}

		return new Promise( ( resolve, reject ) => {
			const existing = document.querySelector( 'script[src="https://pay.google.com/gp/p/js/pay.js"]' );
			if ( existing ) {
				existing.addEventListener( 'load', resolve );
				existing.addEventListener( 'error', reject );
				return;
			}

			const script = document.createElement( 'script' );
			script.src    = 'https://pay.google.com/gp/p/js/pay.js';
			script.async  = true;
			script.onload = resolve;
			script.onerror = reject;
			document.head.appendChild( script );
		} );
	},

	/**
	 * Returns Google Pay's environment string based on EDD's test mode.
	 *
	 * @returns {string} `'TEST'` or `'PRODUCTION'`.
	 */
	detectEnvironment: function() {
		return eddPayPalVars.isSandbox ? 'TEST' : 'PRODUCTION';
	},

	/**
	 * Inserts the Google Pay button (rendered by Google) into the container.
	 *
	 * @param {HTMLElement} container Container element.
	 */
	renderButton: function( container ) {
		const button = this.paymentsClient.createButton( {
			onClick: this.onClick.bind( this ),
			buttonColor: 'black',
			buttonType: 'pay',
			buttonSizeMode: 'fill',
		} );
		container.appendChild( button );
		container.style.display = '';
	},

	/**
	 * Google Pay button click handler — creates the PayPal order first so the
	 * sheet displays the server-authoritative total, then opens Google's
	 * sheet, then confirms with the Google Pay token.
	 *
	 * Creating the order before opening the sheet avoids a stale
	 * `eddPayPalVars.cartTotal` (which can be out of sync after a tax
	 * recalculation, discount/fee change, or any other server-side total
	 * adjustment that didn't fire a JS event we listen for).
	 */
	onClick: async function() {
		const form = document.getElementById( 'edd_purchase_form' );
		if ( form && ! form.reportValidity() ) {
			return;
		}

		this.startProcessing();

		let sheetClosed = false;

		try {
			const orderInfo = await this.createPayPalOrder( form );
			if ( ! orderInfo?.orderId ) {
				this.stopProcessing();
				this.showError( orderInfo?.error || eddPayPalVars.defaultError );
				return;
			}

			const paymentDataRequest = {
				apiVersion:           this.config.apiVersion,
				apiVersionMinor:      this.config.apiVersionMinor,
				allowedPaymentMethods: this.config.allowedPaymentMethods,
				// PayPal's config() merchantInfo carries the registered
				// merchantId/origin but no display name. Adding the store name
				// makes Google Pay show the store identity on the sheet instead
				// of the "Unverified merchant" placeholder.
				merchantInfo: {
					...this.config.merchantInfo,
					merchantName: eddPayPalVars.storeName,
				},
				transactionInfo: {
					countryCode:         this.config.countryCode,
					currencyCode:        orderInfo.currency || this.config.currencyCode || 'USD',
					totalPriceStatus:    'FINAL',
					totalPrice:          orderInfo.total,
				},
			};

			// Hide the spinner while Google's sheet is up — its UI takes over.
			this.hideSpinner();
			const paymentData = await this.paymentsClient.loadPaymentData( paymentDataRequest );
			sheetClosed = true;
			this.showSpinner();

			const result = await this.googlepay.confirmOrder( {
				orderId: orderInfo.orderId,
				paymentMethodData: paymentData.paymentMethodData,
			} );

			const status = result.status || result.googlePayConfirmation?.status;
			if ( 'APPROVED' !== status ) {
				console.warn( 'Google Pay confirmOrder did not approve:', result );
				this.stopProcessing();
				this.showError( eddPayPalVars.defaultError );
				return;
			}

			// captureOrder() redirects on success — leave the processing
			// overlay up until the navigation actually happens so the
			// buyer doesn't see live buttons during the redirect window.
			await this.captureOrder( orderInfo.orderId );
		} catch ( err ) {
			// Buyer-initiated cancel from the Google sheet surfaces as `CANCELED`.
			// The PayPal order will auto-expire on PayPal's side.
			if ( err?.statusCode === 'CANCELED' ) {
				this.stopProcessing();
				return;
			}
			console.error( 'Google Pay payment failed:', err );
			this.stopProcessing();
			if ( sheetClosed ) {
				this.showError( err?.message || eddPayPalVars.defaultError );
			}
		}
	},
};

export default EDDPayPalGooglePay;
