/**
 * Shared checkout-field helpers for the on-page PayPal card methods.
 */

/**
 * Resolves the active checkout form element.
 *
 * @returns {HTMLFormElement|null} The purchase form, or null.
 */
export function getCheckoutForm() {
	return document.getElementById( 'edd_purchase_form' );
}

/**
 * Collects the checkout form fields for the on-page card REST requests.
 *
 * Returns a plain object so the REST controller can array-merge it directly
 * into $_POST — no parse_str round-trip, no data loss on special characters
 * or multi-value fields.
 *
 * @param {HTMLFormElement} [form] Form to read from. Defaults to the checkout form.
 * @returns {Object} Field name => value.
 */
export function collectCheckoutFields( form = getCheckoutForm() ) {
	return Object.fromEntries( new FormData( form ) );
}
