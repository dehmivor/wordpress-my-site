/* global edd_vars */

/**
 * Makes a REST API request to the EDD notifications endpoint.
 *
 * @param {string} endpoint The endpoint path relative to the REST base.
 * @param {string} method   The HTTP method.
 * @param {Object} params   Optional query parameters for GET requests.
 * @return {Promise} The response data.
 */
export function apiRequest( endpoint, method = 'GET', params = {} ) {
	let url = edd_vars.restBase + endpoint;

	if ( 'GET' === method && Object.keys( params ).length ) {
		const queryString = new URLSearchParams( params ).toString();
		url += '?' + queryString;
	}

	return fetch( url, {
		method,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': edd_vars.restNonce,
		},
	} ).then( ( response ) => {
		if ( ! response.ok ) {
			throw response;
		}

		/*
		 * Returning response.text() instead of response.json() because dismissing
		 * a notification doesn't return a JSON response, so response.json() will break.
		 */
		return response.text();
	} ).then( ( data ) => {
		return data ? JSON.parse( data ) : null;
	} );
}
