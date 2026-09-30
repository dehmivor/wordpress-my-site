/**
 * Disable or enable a control passed as a DOM element or jQuery collection (first element).
 *
 * @param {jQuery|HTMLElement|null|undefined} busy
 * @param {boolean}                          disabled
 */
function setBusyDisabled( busy, disabled ) {
	if ( ! busy ) {
		return;
	}
	const el = busy.jquery ? busy[ 0 ] : busy;
	if ( el && 'disabled' in el ) {
		el.disabled = disabled;
	}
}

/**
 * Build `application/x-www-form-urlencoded` body from a plain object.
 *
 * @param {Object} data POST fields
 * @return {URLSearchParams}
 */
function encodeFormBody( data ) {
	const params = new URLSearchParams();
	Object.keys( data ).forEach( function ( key ) {
		const value = data[ key ];
		if ( value !== undefined && value !== null ) {
			params.append( key, String( value ) );
		}
	} );
	return params;
}

/**
 * POST to WordPress admin-ajax.php with optional optimistic UI and shared failure handling.
 *
 * Runs `applyOptimistic` after the busy control is disabled. Invokes `revert` when the request
 * fails (network, non-OK HTTP, invalid JSON) or when the JSON body has falsy `success` (after
 * `debug` logging, before `onResponse`).
 *
 * @param {Object}          options
 * @param {string}          options.url              admin-ajax.php URL
 * @param {Object}          options.data             POST fields (must include `action`)
 * @param {jQuery|HTMLElement} [options.$busy]       Control disabled for the request duration
 * @param {Function}        [options.applyOptimistic] Invoked after `$busy` is disabled, before POST
 * @param {Function}        [options.revert]          Invoked on transport failure or JSON `success === false`
 * @param {Function}        [options.onResponse]      Invoked on successful HTTP response with parsed JSON `( response )`
 * @param {boolean}         [options.debug]           Log response to console
 * @return {Promise<*|undefined>} Parsed JSON when the request succeeds; `undefined` if fetch/parse failed
 */
export async function postAdminAjax( options ) {
	const {
		url,
		data,
		$busy,
		applyOptimistic,
		revert,
		onResponse,
		debug,
	} = options;

	setBusyDisabled( $busy, true );

	try {
		if ( applyOptimistic ) {
			applyOptimistic();
		}

		const res = await fetch( url, {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			credentials: 'same-origin',
			body: encodeFormBody( data ),
		} );

		if ( ! res.ok ) {
			throw new Error( 'Network response was not ok' );
		}

		const response = await res.json();

		if ( debug ) {
			// eslint-disable-next-line no-console
			console.log( response );
		}

		if ( ! response || ! response.success ) {
			if ( revert ) {
				revert();
			}
		}

		if ( onResponse ) {
			onResponse( response );
		}

		return response;
	} catch {
		if ( revert ) {
			revert();
		}
	} finally {
		setBusyDisabled( $busy, false );
	}
}
