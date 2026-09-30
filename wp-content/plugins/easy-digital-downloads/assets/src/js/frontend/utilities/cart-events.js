/**
 * Cart events.
 *
 * Bridges EDD's legacy jQuery cart events to a single native
 * `edd:cart-updated` CustomEvent. Rather than asking every cart mutation to
 * dispatch the native event itself, this listens to the jQuery body events EDD
 * has always fired (`edd_discount_applied`, `edd_quantity_updated`, etc.) and
 * re-broadcasts them, debounced, as one native event. Any code -- core or
 * third-party -- that triggers a legacy event therefore also drives the native
 * event, keeping `edd:cart-updated` a reliable single source of truth.
 *
 * @since 3.7.0
 */

/**
 * Maps each legacy jQuery cart event to its `edd:cart-updated` detail type.
 *
 * @since 3.7.0
 */
const CART_EVENTS = {
	edd_cart_item_added: 'item_added',
	edd_cart_item_removed: 'item_removed',
	edd_quantity_updated: 'quantity_updated',
	edd_discount_applied: 'discount_applied',
	edd_discount_removed: 'discount_removed',
	edd_taxes_recalculated: 'taxes_recalculated',
};

let cartUpdatedTimer = null;

/**
 * Dispatch the edd:cart-updated CustomEvent.
 *
 * Fires on document after the DOM reflects the current cart state. A short
 * trailing debounce coalesces rapid back-to-back changes (e.g. a discount
 * removal that also recalculates taxes within the window) into a single event.
 *
 * The `type` value is metadata for debugging; listeners must not branch on it
 * for business logic. When debounced, it reflects whichever event fired last.
 *
 * @since 3.7.0
 *
 * @param {string} type Identifies the source action.
 */
function dispatchCartUpdated( type ) {
	clearTimeout( cartUpdatedTimer );
	cartUpdatedTimer = setTimeout( () => {
		document.dispatchEvent(
			new CustomEvent( 'edd:cart-updated', {
				detail: { type },
			} )
		);
	}, 50 );
}

/**
 * Bind the legacy jQuery cart events to the native dispatcher.
 *
 * Guarded by a global flag so the listeners bind exactly once, even when more
 * than one built bundle imports this module on the same page.
 *
 * @since 3.7.0
 */
export function initCartEventsBridge() {
	if ( 'undefined' === typeof jQuery || globalThis.eddCartEventsBridged ) {
		return;
	}
	globalThis.eddCartEventsBridged = true;

	const $body = jQuery( document.body );
	Object.keys( CART_EVENTS ).forEach( ( legacyEvent ) => {
		$body.on( legacyEvent, () => dispatchCartUpdated( CART_EVENTS[ legacyEvent ] ) );
	} );
}
