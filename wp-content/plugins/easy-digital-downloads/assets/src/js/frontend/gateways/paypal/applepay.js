/* global eddPayPalVars, paypal */

import WalletPaymentMethod from './base/wallet.js';

// EDD PayPal Apple Pay integration — renders the Apple Pay button on the
// checkout page and tokenizes via PayPal's Applepay component.

/**
 * EDD PayPal Apple Pay integration.
 *
 * Renders the Apple Pay button (when the browser + merchant + buyer all
 * support it), creates a PayPal order on click via the same AJAX endpoint
 * the Smart Buttons use, validates the merchant via PayPal's proxy, and
 * confirms the order with the Apple Pay token. Falls back to the standard
 * PayPal Smart Button stack when ineligible.
 *
 * The processing lock, spinner, error surface, order creation, and capture
 * are inherited from WalletPaymentMethod; this module supplies only the
 * Apple-Pay-specific eligibility, button render, and sheet flow.
 */
const EDDPayPalApplePay = {
	...WalletPaymentMethod,

	walletType: 'apple_pay',
	containerId: 'edd-paypal-applepay-container',
	applepay: null,
	config: null,

	/**
	 * Bootstraps the Apple Pay button if eligible.
	 */
	init: async function() {
		if ( undefined === globalThis.paypal || 'function' !== typeof globalThis.paypal.Applepay ) {
			return;
		}

		// Apple Pay JS API only exists in Safari (macOS or iOS) with a card set up.
		if ( undefined === globalThis.ApplePaySession || ! globalThis.ApplePaySession.canMakePayments() ) {
			return;
		}

		const container = document.getElementById( this.containerId );
		if ( ! container ) {
			return;
		}

		try {
			this.applepay = paypal.Applepay();
			this.config   = await this.applepay.config();

			if ( ! this.config.isEligible ) {
				return;
			}

			this.renderButton( container );
		} catch ( e ) {
			console.warn( 'EDD PayPal Apple Pay unavailable:', e );
		}
	},

	/**
	 * Inserts the Apple-Pay-branded button into the container.
	 *
	 * @param {HTMLElement} container Container element.
	 */
	renderButton: function( container ) {
		const button = document.createElement( 'apple-pay-button' );
		button.setAttribute( 'buttonstyle', 'black' );
		button.setAttribute( 'type', 'plain' );
		button.setAttribute( 'locale', this.normalizeLocale() );
		button.addEventListener( 'click', this.onClick.bind( this ) );
		container.appendChild( button );
		container.style.display = '';
	},

	/**
	 * Returns the buyer's locale in the format Apple expects (e.g. `en-US`).
	 *
	 * @returns {string}
	 */
	normalizeLocale: function() {
		const lang = ( navigator.language || 'en-US' ).replace( '_', '-' );
		return ! lang.includes( '-' ) ? `${ lang }-US` : lang;
	},

	/**
	 * Apple Pay button click handler — creates the PayPal order, opens the
	 * Apple Pay sheet, and confirms the order on payment authorization.
	 *
	 * @param {Event} event Click event.
	 */
	onClick: async function( event ) {
		event.preventDefault();

		const form = document.getElementById( 'edd_purchase_form' );
		if ( form && ! form.reportValidity() ) {
			return;
		}

		this.startProcessing();

		// Create the PayPal order before opening the Apple Pay sheet so the
		// sheet displays the server-authoritative total. The cart total in
		// `eddPayPalVars` can be stale after tax recalculation, discount
		// changes, or fee adjustments that didn't fire a JS event. Safari's
		// user-activation flag survives the one short AJAX await in
		// practice — PayPal's own Apple Pay sample code uses this ordering.
		let orderInfo;
		try {
			orderInfo = await this.createPayPalOrder( form );
		} catch ( err ) {
			console.error( 'Apple Pay createPayPalOrder failed:', err );
			this.stopProcessing();
			this.showError( eddPayPalVars.defaultError );
			return;
		}

		if ( ! orderInfo?.orderId ) {
			this.stopProcessing();
			this.showError( orderInfo?.error || eddPayPalVars.defaultError );
			return;
		}

		const session = new ApplePaySession( 4, {
			countryCode: this.config.countryCode,
			currencyCode: orderInfo.currency || this.config.currencyCode,
			merchantCapabilities: this.config.merchantCapabilities,
			supportedNetworks: this.config.supportedNetworks,
			requiredBillingContactFields: [ 'postalAddress', 'name' ],
			requiredShippingContactFields: [ 'email' ],
			total: {
				label: this.getStoreName(),
				amount: orderInfo.total,
				type: 'final',
			},
		} );

		// Hide the spinner while Apple's sheet is up — its UI takes over.
		this.hideSpinner();

		// Apple-side merchant validation — PayPal acts as the merchant identity here.
		// Note: Apple's event uses `validationURL` (uppercase URL); reading the
		// camelCase `validationUrl` returns undefined and PayPal's GraphQL
		// merchant-validation mutation then errors with "Variable \"$url\" of
		// required type \"String!\" was not provided.".
		// `displayName` is passed because PayPal's documented integration
		// includes it — without it Apple's session can be marked invalid
		// and reject the authorization at biometric time without surfacing
		// a useful error to us.
		session.onvalidatemerchant = async ( e ) => {
			try {
				const validation = await this.applepay.validateMerchant( {
					validationUrl: e.validationURL,
					displayName:   this.getStoreName(),
				} );

				session.completeMerchantValidation( validation.merchantSession );
			} catch ( err ) {
				console.error( 'Apple Pay validateMerchant failed:', err );
				session.abort();
			}
		};

		// Buyer authorized payment in the Apple sheet — confirm the existing
		// PayPal order with the Apple Pay token, then capture on our end.
		session.onpaymentauthorized = async ( e ) => {
			this.showSpinner();

			try {
				const result = await this.applepay.confirmOrder( {
					orderId: orderInfo.orderId,
					token: e.payment.token,
					billingContact: e.payment.billingContact,
					shippingContact: e.payment.shippingContact,
				} );

				if ( 'APPROVED' !== ( result.approveApplePayPayment?.status || result.status ) ) {
					session.completePayment( ApplePaySession.STATUS_FAILURE );
					this.stopProcessing();
					this.showError( eddPayPalVars.defaultError );
					return;
				}

				session.completePayment( ApplePaySession.STATUS_SUCCESS );

				// Capture the order on our end via the existing approval AJAX —
				// same path the Smart Buttons use on onApprove. Leave the
				// processing overlay up until the redirect navigates.
				await this.captureOrder( orderInfo.orderId );
			} catch ( err ) {
				console.error( 'Apple Pay payment failed:', err );
				session.completePayment( ApplePaySession.STATUS_FAILURE );
				this.stopProcessing();
				this.showError( err?.message || eddPayPalVars.defaultError );
			}
		};

		session.oncancel = () => {
			// Buyer dismissed the sheet — drop the overlay. The PayPal order
			// will auto-expire on PayPal's side.
			this.stopProcessing();
		};

		session.begin();
	},

	/**
	 * Retrieves the store name for the Apple Pay sheet `total.label` field.
	 *
	 * @returns {string}
	 */
	getStoreName: function() {
		return ( eddPayPalVars.storeName || document.title || 'Order' ).substring( 0, 64 );
	},
};

export default EDDPayPalApplePay;
