/**
 * Date picker
 *
 * Exports a reusable initDatePicker function and auto-initializes
 * any .edd_datepicker inputs on DOMContentLoaded.
 *
 * This juggles a few CSS classes to avoid styling collisions with other
 * third-party plugins.
 */

/**
 * Initialize a jQuery UI datepicker on a single input element.
 *
 * Handles dialog-aware positioning so the calendar renders correctly
 * inside <dialog> modals (top-layer).
 *
 * @param {HTMLInputElement} input   The input element.
 * @param {Object}           options Optional overrides.
 * @param {string}           options.dateFormat jQuery UI date format (default: edd_vars.date_picker_format).
 * @param {Function|null}    options.onSelect   Callback when a date is picked.
 */
export function initDatePicker( input, options = {} ) {
	const $ = globalThis.jQuery;
	if ( ! $?.fn.datepicker ) {
		return;
	}

	const $input = $( input );
	const $dialog = $input.closest( 'dialog' );
	const isInDialog = $dialog.length > 0;

	$input.attr( 'autocomplete', 'off' ).datepicker( {
		dateFormat: options.dateFormat || ( typeof edd_vars === 'undefined' ? 'yy-mm-dd' : edd_vars.date_picker_format ),
		beforeShow: function( el ) {
			$( '#ui-datepicker-div' )
				.removeClass( 'ui-datepicker' )
				.addClass( 'edd-datepicker' );

			if ( isInDialog ) {
				// Move the calendar div into the <dialog> so it
				// renders in the browser's top layer above the
				// modal backdrop. appendTo option is unreliable
				// in the WordPress-bundled jQuery UI version.
				$dialog.append( $( '#ui-datepicker-div' ) );

				// jQuery UI calculates position using document
				// offsets, which are unreliable inside a <dialog>.
				// After jQuery UI finishes positioning, override
				// with fixed coordinates from getBoundingClientRect
				// so it appears just below the input regardless of
				// the dialog's overflow: hidden or transforms.
				setTimeout( function() {
					const rect = el.getBoundingClientRect();
					$( '#ui-datepicker-div' ).css( {
						position: 'fixed',
						top: rect.bottom + 'px',
						left: rect.left + 'px',
					} );
				}, 0 );
			}
		},
		onSelect: options.onSelect || null,
	} );
}

// Auto-initialize existing .edd_datepicker inputs on page load.
globalThis.jQuery( document ).ready( function( $ ) {
	$( 'input.edd_datepicker' ).each( function() {
		initDatePicker( this );
	} );
} );
