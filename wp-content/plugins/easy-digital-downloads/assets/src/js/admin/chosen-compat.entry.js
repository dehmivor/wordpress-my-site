/**
 * Chosen-to-Tom Select Compatibility Shim
 *
 * Provides a jQuery $.fn.chosen() API that transparently uses Tom Select
 * under the hood. This allows existing code (including extensions) that
 * calls .chosen() to continue working without modification.
 *
 * Requires: jQuery, TomSelect (registered as edd-tom-select)
 *
 * @package EDD
 */

/* global jQuery */

import TomSelect from 'tom-select';
import { buildTomSelectConfig, addCompatClasses, getChosenVars } from 'utils/chosen.js';

const warnedElements = new WeakSet();


/**
 * Bind Chosen event compatibility.
 */
function bindEvents( $el, instance ) {
	// Incoming: chosen:updated — re-sync options from the DOM.
	$el.on( 'chosen:updated.tomselect', () => {
		// Clear existing options before syncing so removed DOM options
		// don't persist in the dropdown (e.g. region options after country change).
		instance.clear( true );
		instance.clearOptions( () => false );
		instance.sync();
		instance.refreshOptions( false );
	} );

	// Incoming: chosen:open.
	$el.on( 'chosen:open.tomselect', () => instance.open() );

	// Incoming: chosen:close.
	$el.on( 'chosen:close.tomselect', () => instance.close() );

	// Incoming: chosen:activate.
	$el.on( 'chosen:activate.tomselect', () => instance.focus() );

	// Outgoing: fire chosen:* events when Tom Select state changes.
	instance.on( 'dropdown_open', () => {
		$el.trigger( 'chosen:showing_dropdown' );
		if ( instance.wrapper ) {
			instance.wrapper.classList.add( 'chosen-with-drop', 'chosen-container-active' );
		}
	} );

	instance.on( 'dropdown_close', () => {
		$el.trigger( 'chosen:hiding_dropdown' );
		if ( instance.wrapper ) {
			instance.wrapper.classList.remove( 'chosen-with-drop', 'chosen-container-active' );
		}
	} );

	// Fire chosen:ready after initialization.
	$el.trigger( 'chosen:ready' );
}

/**
 * jQuery .chosen() plugin backed by Tom Select.
 *
 * Usage:
 *   $( 'select' ).chosen( { options } )  — initialize
 *   $( 'select' ).chosen( 'destroy' )    — tear down
 */
jQuery.fn.chosen = function( optionsOrCommand ) {
	return this.each( function() {
		const el = this;
		const $el = jQuery( this );

		// Handle string commands.
		if ( typeof optionsOrCommand === 'string' ) {
			const existing = el.tomselect;
			if ( ! existing ) {
				return;
			}

			if ( 'destroy' === optionsOrCommand ) {
				$el.off( '.tomselect' );
				existing.destroy();
				$el.removeData( 'chosen' );
				return;
			}

			return;
		}

		// Deprecation warning — fires once per element to avoid log spam.
		if ( ! warnedElements.has( el ) ) {
			warnedElements.add( el );
			// eslint-disable-next-line no-console
			console.warn(
				'[EDD] jQuery .chosen() is deprecated and will be removed in a future version. ' +
				'Use initChosen( el ) from utils/chosen.js instead, passing a native HTMLSelectElement.',
				el
			);
		}

		// If already initialized, destroy first.
		if ( el.tomselect ) {
			$el.off( '.tomselect' );
			el.tomselect.destroy();
		}

		const options  = { ...getChosenVars( el ), ...( optionsOrCommand || {} ) };

		// Save the select's original classes before TomSelect copies them
		// to the wrapper. Extensions use class selectors (e.g. $( '.git-tag' ))
		// to target the original <select>; if those classes also appear on
		// the wrapper, jQuery matches both elements and DOM mutations
		// (like appending <option> tags) corrupt the wrapper.
		const originalClasses = el.className.split( /\s+/ ).filter( Boolean );

		const instance = new TomSelect( el, buildTomSelectConfig( el, options ) );

		// Strip the select's classes from the wrapper.
		if ( instance.wrapper ) {
			instance.wrapper.classList.remove( ...originalClasses );
		}

		// Add Chosen-compatible CSS classes.
		addCompatClasses( instance, el.multiple );

		// Store the instance for jQuery access.
		$el.data( 'chosen', instance );

		// Set up event bridging.
		bindEvents( $el, instance );
	} );
};
