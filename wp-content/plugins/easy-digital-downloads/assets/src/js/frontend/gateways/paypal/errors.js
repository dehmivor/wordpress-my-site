/* global jQuery */

/**
 * Shared error rendering helpers for the PayPal frontend modules.
 *
 * Centralises show/clear logic across the four entry points
 * (paypal.entry, paypal/applepay, paypal/googlepay, paypal/fastlane) so the
 * standard PayPal error wrapper (`#edd-paypal-errors-wrap`) and the Buy Now
 * button's error wrapper (`.edd-paypal-checkout-buy-now-error-wrapper`) both
 * render with the same `edd-alert edd-alert-error` markup the rest of EDD
 * uses. Previously each module duplicated the show/clear code with subtle
 * differences — Fastlane and the main entry helper both wrote plain text
 * directly into `innerHTML`, producing an unstyled error message.
 *
 * All callers should use these helpers; do not assign to `innerHTML`
 * directly.
 */

/**
 * Resolves the error wrapper element for a given scope.
 *
 * @param {string} scope   Either 'checkout' (default checkout page) or
 *                          'buy_now' (single-product Buy Now button).
 * @param {HTMLElement} [container] Optional ancestor element used to scope
 *                          the Buy Now wrapper lookup. Required when scope
 *                          is 'buy_now', ignored otherwise.
 * @returns {HTMLElement|null}
 */
function resolveWrapper( scope, container ) {
	if ( 'buy_now' === scope ) {
		const form = container?.closest?.( '.edd_download_purchase_form' );
		return form ? form.querySelector( '.edd-paypal-checkout-buy-now-error-wrapper' ) : null;
	}

	return document.getElementById( 'edd-paypal-errors-wrap' );
}

/**
 * Renders an error message in the appropriate PayPal error wrapper.
 *
 * Plain-text messages are wrapped in the standard EDD alert markup; messages
 * already containing HTML (heuristic: contain `<`) are written as-is to
 * support pre-formatted error HTML returned by the server.
 *
 * Also fires the `edd_checkout_error` jQuery event for parity with the
 * pre-existing handlers other gateways rely on.
 *
 * @param {string}      message     Error message text or HTML.
 * @param {string}      [scope]     'checkout' or 'buy_now'. Defaults to 'checkout'.
 * @param {HTMLElement} [container] Required for 'buy_now' scope to locate the
 *                                  form-scoped error wrapper.
 * @returns {void}
 */
export function showPayPalError( message, scope = 'checkout', container = null ) {
	const wrap = resolveWrapper( scope, container );

	if ( ! wrap || ! message ) {
		return;
	}

	const html = 'string' === typeof message && message.includes( '<' )
		? message
		: `<div class="edd-alert edd-alert-error"><p class="edd_error">${ message }</p></div>`;

	wrap.innerHTML = html;

	if ( 'undefined' !== typeof jQuery ) {
		jQuery( document.body ).trigger( 'edd_checkout_error', [ html ] );
	}
}

/**
 * Clears any displayed error in the PayPal error wrapper.
 *
 * @param {string}      [scope]     'checkout' or 'buy_now'. Defaults to 'checkout'.
 * @param {HTMLElement} [container] Required for 'buy_now' scope.
 * @returns {void}
 */
export function clearPayPalErrors( scope = 'checkout', container = null ) {
	const wrap = resolveWrapper( scope, container );

	if ( wrap ) {
		wrap.innerHTML = '';
	}
}
