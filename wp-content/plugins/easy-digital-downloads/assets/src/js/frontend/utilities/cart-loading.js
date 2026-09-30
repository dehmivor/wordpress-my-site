/* global edd_global_vars */

/**
 * Cart loading indicator.
 *
 * A single, reference-counted loading overlay for the checkout. Rather than
 * every cart mutation (discount, tax recalc, quantity, gateway load) and every
 * gateway painting its own spinner, each asynchronous operation brackets its
 * work with `beginLoading()` / `endLoading()`. The controller shows one
 * full-screen overlay while any operation is outstanding and hides it once the
 * last one finishes.
 *
 * Overlapping operations are tracked by token in a Map, so the overlay only
 * hides when the final token is released -- a discount apply that also triggers
 * a tax recalculation never flickers mid-flight. The hide is trailing-debounced
 * so a brief gap between one operation ending and the next beginning (e.g. the
 * 50ms `edd:cart-updated` debounce handing off to a gateway re-render) does not
 * blink the overlay off and back on.
 *
 * Non-bundled / third-party code that cannot import this module can drive the
 * same overlay via the `edd:loading-start` / `edd:loading-end` events or the
 * `globalThis.eddCartLoading` API.
 *
 * @since 3.7.0
 */

/**
 * Shared, page-wide loading state.
 *
 * Every checkout bundle (edd-ajax, checkout, and each gateway) that imports this
 * module gets its own webpack copy of these functions -- there is no shared
 * chunk. To keep the reference count and the overlay a true singleton across all
 * of them, the mutable state lives on `globalThis` rather than in module scope,
 * so every copy reads and writes the same Map, sequence, timer, and overlay.
 *
 * @since 3.7.0
 *
 * @return {{active: Map<string, {source: string, timer: number}>, sequence: number, hideTimer: number, overlay: HTMLElement}} The shared state.
 */
function getState() {
	if ( ! globalThis.eddCartLoadingState ) {
		globalThis.eddCartLoadingState = {
			active: new Map(),
			sequence: 0,
			hideTimer: null,
			overlay: null,
		};
	}

	return globalThis.eddCartLoadingState;
}

/**
 * Trailing delay (ms) before the overlay hides once the last token is released.
 *
 * Bridges the gap between a cart operation completing and a gateway picking up
 * the `edd:cart-updated` event (debounced at 50ms) to re-render, so the overlay
 * stays up across the whole "cart settling" interval instead of flickering.
 *
 * @since 3.7.0
 */
const HIDE_DELAY = 120;

/**
 * Safety-net timeout (ms) after which an unreleased token is force-released.
 *
 * Guarantees an operation that errors without calling `endLoading()` cannot
 * wedge the overlay open forever.
 *
 * @since 3.7.0
 */
const DEFAULT_TIMEOUT = 8000;

/**
 * Build (once) and return the overlay element.
 *
 * @since 3.7.0
 *
 * @return {HTMLElement} The overlay element.
 */
function getOverlay() {
	const state = getState();
	if ( state.overlay ) {
		return state.overlay;
	}

	const overlay = document.createElement( 'div' );
	overlay.className = 'edd-cart-loading';
	overlay.setAttribute( 'role', 'status' );
	overlay.setAttribute( 'aria-live', 'polite' );
	overlay.hidden = true;

	const spinner = document.createElement( 'span' );
	spinner.className = 'edd-cart-loading__spinner';
	overlay.appendChild( spinner );

	const label = document.createElement( 'span' );
	label.className = 'edd-cart-loading__label screen-reader-text';
	label.textContent =
		( 'undefined' !== typeof edd_global_vars && edd_global_vars.purchase_loading ) ||
		'Loading';
	overlay.appendChild( label );

	document.body.appendChild( overlay );

	state.overlay = overlay;

	return overlay;
}

/**
 * Mark the checkout busy and reveal the overlay.
 *
 * @since 3.7.0
 */
function show() {
	const state = getState();
	clearTimeout( state.hideTimer );
	state.hideTimer = null;

	getOverlay().hidden = false;

	const wrap = document.getElementById( 'edd_checkout_form_wrap' );
	if ( wrap ) {
		wrap.setAttribute( 'aria-busy', 'true' );
	}
}

/**
 * Hide the overlay and clear the busy state.
 *
 * @since 3.7.0
 */
function hide() {
	getOverlay().hidden = true;

	const wrap = document.getElementById( 'edd_checkout_form_wrap' );
	if ( wrap ) {
		wrap.removeAttribute( 'aria-busy' );
	}
}

/**
 * Notify listeners that the aggregate loading state changed.
 *
 * Fires on the 0->1 and 1->0 transitions so themes/extensions can react
 * without patching the controller.
 *
 * @since 3.7.0
 *
 * @param {boolean} loading Whether anything is now loading.
 */
function dispatchChanged( loading ) {
	document.dispatchEvent(
		new CustomEvent( 'edd:loading-changed', {
			bubbles: true,
			detail: { loading, count: getState().active.size },
		} )
	);
}

/**
 * Register a token in the active set, showing the overlay on the 0->1 edge.
 *
 * @since 3.7.0
 *
 * @param {string} token   Unique token.
 * @param {string} source  Free-form label for debugging.
 * @param {number} timeout Safety-net timeout in ms.
 */
function register( token, source, timeout ) {
	const state = getState();
	const wasEmpty = 0 === state.active.size;
	const timer = setTimeout( () => {
		if ( 'undefined' !== typeof edd_global_vars && edd_global_vars.showStoreErrors ) {
			// eslint-disable-next-line no-console
			console.warn( `edd-cart-loading: token "${ token }" timed out and was released.` );
		}
		endLoading( token );
	}, timeout );

	state.active.set( token, { source, timer } );

	if ( wasEmpty ) {
		show();
		dispatchChanged( true );
	}
}

/**
 * Begin a loading operation.
 *
 * @since 3.7.0
 *
 * @param {string} source            Free-form label for debugging (e.g. 'discount').
 * @param {Object} [options]         Options.
 * @param {number} [options.timeout] Override the safety-net timeout (ms).
 * @return {string} A token to pass to `endLoading()`.
 */
export function beginLoading( source = 'unknown', { timeout = DEFAULT_TIMEOUT } = {} ) {
	const state = getState();
	const token = `${ source }-${ ++state.sequence }`;
	register( token, source, timeout );
	return token;
}

/**
 * End a loading operation.
 *
 * Safe to call with an unknown or already-released token (no-op), so duplicate
 * `endLoading()` calls cannot drive the count negative.
 *
 * @since 3.7.0
 *
 * @param {string} token The token returned by `beginLoading()`.
 */
export function endLoading( token ) {
	const state = getState();
	const entry = state.active.get( token );
	if ( ! entry ) {
		return;
	}

	clearTimeout( entry.timer );
	state.active.delete( token );

	if ( 0 === state.active.size ) {
		clearTimeout( state.hideTimer );
		state.hideTimer = setTimeout( () => {
			hide();
			dispatchChanged( false );
		}, HIDE_DELAY );
	}
}

/**
 * Run an async operation while the overlay is shown.
 *
 * The recommended path for new producers: the token is always released, even if
 * the operation rejects.
 *
 * @since 3.7.0
 *
 * @param {string}   source  Free-form label for debugging.
 * @param {Function} factory A function that starts the work and returns its promise.
 * @return {Promise} The operation's promise.
 */
export function withLoading( source, factory ) {
	const token = beginLoading( source );
	let result;
	try {
		result = factory();
	} catch ( error ) {
		endLoading( token );
		throw error;
	}
	return Promise.resolve( result ).finally( () => endLoading( token ) );
}

/**
 * Whether any loading operation is currently outstanding.
 *
 * @since 3.7.0
 *
 * @return {boolean} True if loading.
 */
export function isLoading() {
	return getState().active.size > 0;
}

/**
 * The sources of the currently outstanding operations (for debugging).
 *
 * @since 3.7.0
 *
 * @return {string[]} Active tokens.
 */
export function getActiveTokens() {
	return [ ...getState().active.keys() ];
}

/**
 * Initialize the loading controller.
 *
 * Idempotent: guarded by a global flag so the overlay and event listeners are
 * created exactly once even when more than one built bundle imports this module
 * on the same page.
 *
 * @since 3.7.0
 */
export function initCartLoading() {
	if ( globalThis.eddCartLoadingInitialized ) {
		return;
	}
	globalThis.eddCartLoadingInitialized = true;

	getOverlay();

	// Stable hook for non-bundled / extension code.
	globalThis.eddCartLoading = {
		begin: beginLoading,
		end: endLoading,
		withLoading,
		isLoading,
		getActiveTokens,
	};

	// Event escape hatch: callers bring their own token id and pair start/end.
	document.addEventListener( 'edd:loading-start', ( event ) => {
		const token = event.detail?.token;
		if ( ! token || getState().active.has( token ) ) {
			return;
		}
		register( token, ( event.detail?.source ) || 'external', DEFAULT_TIMEOUT );
	} );

	document.addEventListener( 'edd:loading-end', ( event ) => {
		if ( event.detail?.token ) {
			endLoading( event.detail.token );
		}
	} );
}
