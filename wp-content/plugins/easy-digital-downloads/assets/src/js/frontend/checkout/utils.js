/* global edd_global_vars */

import { beginLoading, endLoading } from '@easy-digital-downloads/cart-loading';

/**
 * Generate markup for a credit card icon based on a passed type.
 *
 * @param {string} type Credit card type.
 * @return HTML markup.
 */
export const getCreditCardIcon = ( type ) => {
	let width;
	let name = type;

	switch ( type ) {
		case 'amex':
			name = 'americanexpress';
			width = 32;
			break;
		default:
			width = 50;
			break;
	}

	return `
    <svg
      width=${ width }
      height=${ 32 }
      class="payment-icon icon-${ name }"
      role="img"
    >
      <use
        href="#icon-${ name }"
        xlink:href="#icon-${ name }">
      </use>
    </svg>`;
};

let ajax_tax_count = 0;

/**
 * Recalulate taxes.
 *
 * @param {string} state State to calculate taxes for.
 * @return {Promise}
 */
export function recalculateTaxes( state ) {
	if ( '1' != edd_global_vars.taxes_enabled ) {
		return;
	} // Taxes not enabled

	const cart = document.getElementById( 'edd_checkout_cart' );
	if ( ! cart ) {
		return;
	}

	let current_tax_amount_raw = 0;

	// Capture current cart total for error restoration
	const cart_total_element = document.querySelector( '.edd_cart_amount' );
	const current_cart_total = cart_total_element ? cart_total_element.dataset.total || cart_total_element.textContent : 0;

	// Capture the current tax amount to send with the request. The loading overlay
	// (see utilities/cart-loading.js) covers the busy state, so the tax row is left
	// in place and simply updates when the response arrives.
	const current_tax_amount = cart.getElementsByClassName( 'edd_cart_tax_amount' );
	for ( let i = 0; i < current_tax_amount.length; i++ ) {
		current_tax_amount_raw = current_tax_amount[ i ].dataset.tax;
	}

	const $edd_cc_address = jQuery( '#edd_cc_address' );

	const billing_country = $edd_cc_address.find( '#billing_country' ).val(),
		card_address = $edd_cc_address.find( '#card_address' ).val(),
		card_address_2 = $edd_cc_address.find( '#card_address_2' ).val(),
		card_city = $edd_cc_address.find( '#card_city' ).val(),
		card_state = $edd_cc_address.find( '#card_state' ).val(),
		card_zip = $edd_cc_address.find( '#card_zip' ).val();

	if ( ! state ) {
		state = card_state;
	}

	const postData = {
		action: 'edd_recalculate_taxes',
		card_address: card_address,
		card_address_2: card_address_2,
		card_city: card_city,
		card_zip: card_zip,
		state: state,
		billing_country: billing_country,
		nonce: jQuery( '#edd-checkout-address-fields-nonce' ).val(),
		current_page: edd_global_vars.current_page,
		current_tax_amount: current_tax_amount_raw,
	};

	const current_ajax_count = ++ajax_tax_count;
	const loadingToken = beginLoading( 'taxes' );

	// Initialize tax_data object that will be accessible to both success and fail callbacks
	const tax_data = new Object();
	tax_data.postdata = postData;

	return jQuery.ajax( {
		type: 'POST',
		data: postData,
		dataType: 'json',
		url: edd_global_vars.ajaxurl,
		xhrFields: {
			withCredentials: true,
		},
		success: function( tax_response ) {
			// Only update tax info if this response is the most recent ajax call.
			// Avoids bug with form autocomplete firing multiple ajax calls at the same time and not
			// being able to predict the call response order. Guard tax_response: the
			// endpoint can return null when no tax applies to the region, and reading
			// .html off null would throw before the loading token is released.
			if ( current_ajax_count === ajax_tax_count ) {
				if ( tax_response?.html ) {
					jQuery( '#edd_checkout_cart_form' ).replaceWith( tax_response.html );
				}
				if ( tax_response ) {
					jQuery( '.edd_cart_amount' ).html( tax_response.total );
				}
				tax_data.response = tax_response;
				jQuery( 'body' ).trigger( 'edd_taxes_recalculated', [ tax_data ] );
			}
		},
	} ).fail( function( data ) {
		console.log( 'Tax recalculation failed:', data );

		if ( current_ajax_count === ajax_tax_count ) {
			// Create standardized error response structure to maintain consistency
			// with success callback and prevent issues in event listeners
			tax_data.response = {
				success: false,
				error: true,
				tax_raw: tax_data.postdata.current_tax_amount || 0,
				total_raw: current_cart_total,
				debug_data: data
			};
			jQuery( 'body' ).trigger( 'edd_taxes_recalculated', [ tax_data ] );
		}
	} ).always( function() {
		endLoading( loadingToken );
	} );
}
